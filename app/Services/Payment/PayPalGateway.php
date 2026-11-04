<?php

namespace App\Services\Payment;

use App\Contracts\Payable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PayPal Orders v2 (REST) gateway.
 *
 * Flow used by the Angular app (PayPal JS SDK buttons):
 *   1. POST /api/bookings              → we create a PayPal order, return its id
 *   2. Buyer approves in the PayPal popup
 *   3. POST /api/bookings/{ref}/paypal/capture → we capture server-side
 *   4. Webhooks (CHECKOUT.ORDER.APPROVED / PAYMENT.CAPTURE.*) cover closed tabs and refunds.
 *
 * No card data ever touches our servers.
 */
class PayPalGateway implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;
    private int $timeout;

    public function __construct()
    {
        $config = config('payment.paypal');
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->clientId = (string) $config['client_id'];
        $this->clientSecret = (string) $config['client_secret'];
        $this->webhookId = (string) $config['webhook_id'];
        $this->timeout = (int) ($config['timeout'] ?? 20);
    }

    public function name(): string
    {
        return 'paypal';
    }

    public function createPayment(Payable $payable, array $options = []): PaymentResult
    {
        $experience = array_filter([
            'brand_name' => mb_substr((string) ($options['brand_name'] ?? config('hotel.name')), 0, 127),
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
            'return_url' => $options['return_url'] ?? null,
            'cancel_url' => $options['cancel_url'] ?? null,
        ]);

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $payable->payableReference(),
                'custom_id' => $payable->payableReference(),
                'description' => $payable->payableDescription(),
                'amount' => [
                    'currency_code' => $payable->payableCurrency(),
                    'value' => $payable->payableAmount(),
                ],
            ]],
        ];

        // Redirect flow needs payment_source + return/cancel URLs; the JS SDK popup flow does not.
        if (isset($experience['return_url'])) {
            $body['payment_source'] = ['paypal' => ['experience_context' => $experience]];
        } else {
            $body['application_context'] = Arr::except($experience, ['return_url', 'cancel_url']);
        }

        $response = $this->client()
            ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid(), 'Prefer' => 'return=representation'])
            ->post('/v2/checkout/orders', $body);

        $this->failUnless($response, 'create order');
        $order = $response->json();

        $approveUrl = collect($order['links'] ?? [])
            ->firstWhere(fn ($l) => in_array($l['rel'] ?? '', ['approve', 'payer-action'], true))['href'] ?? null;

        return new PaymentResult(
            gatewayId: $order['id'],
            status: 'pending',
            paymentDetails: [
                'paypal_order_id' => $order['id'],
                'approve_url' => $approveUrl,
            ],
            rawResponse: $order,
            gatewayStatus: $order['status'] ?? null,
        );
    }

    public function capturePayment(string $gatewayId): PaymentResult
    {
        $response = $this->client()
            ->withHeaders(['PayPal-Request-Id' => 'capture-' . $gatewayId, 'Prefer' => 'return=representation'])
            ->withBody('{}', 'application/json')
            ->post("/v2/checkout/orders/{$gatewayId}/capture");

        if ($response->status() === 422) {
            $issue = $response->json('details.0.issue');

            if ($issue === 'ORDER_ALREADY_CAPTURED') {
                return $this->getPaymentStatus($gatewayId);
            }

            if (in_array($issue, ['INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED', 'ORDER_NOT_APPROVED'], true)) {
                return new PaymentResult(
                    gatewayId: $gatewayId,
                    status: $issue === 'INSTRUMENT_DECLINED' ? 'failed' : 'pending',
                    paymentDetails: ['issue' => $issue],
                    rawResponse: $response->json() ?? [],
                    gatewayStatus: $issue,
                );
            }
        }

        $this->failUnless($response, 'capture order');

        return $this->fromOrder($response->json());
    }

    public function getPaymentStatus(string $gatewayId): PaymentResult
    {
        $response = $this->client()->get("/v2/checkout/orders/{$gatewayId}");
        $this->failUnless($response, 'get order');

        return $this->fromOrder($response->json());
    }

    public function refundPayment(string $captureId, string $amount, string $currency): RefundResult
    {
        $response = $this->client()
            ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid(), 'Prefer' => 'return=representation'])
            ->post("/v2/payments/captures/{$captureId}/refund", [
                'amount' => ['value' => $amount, 'currency_code' => $currency],
            ]);

        $this->failUnless($response, 'refund capture');
        $refund = $response->json();

        return new RefundResult(
            refundId: $refund['id'],
            status: match ($refund['status'] ?? '') {
                'COMPLETED' => 'completed',
                'PENDING' => 'pending',
                default => 'failed',
            },
            amount: $refund['amount']['value'] ?? $amount,
            rawResponse: $refund,
        );
    }

    /**
     * Verify with PayPal's API. The original raw body must be sent back untouched,
     * so the JSON is assembled by hand around it.
     */
    public function validateWebhook(Request $request): bool
    {
        if ($this->webhookId === '' || $request->getContent() === '') {
            return false;
        }

        $meta = [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => $this->webhookId,
        ];

        if (in_array(null, $meta, true)) {
            return false;
        }

        $payload = rtrim(json_encode($meta, JSON_UNESCAPED_SLASHES), '}')
            . ',"webhook_event":' . $request->getContent() . '}';

        $response = $this->client()
            ->withBody($payload, 'application/json')
            ->post('/v1/notifications/verify-webhook-signature');

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $event = $request->json()->all();
        $type = $event['event_type'] ?? '';
        $resource = $event['resource'] ?? [];
        $base = ['eventId' => (string) ($event['id'] ?? ''), 'type' => $type, 'raw' => $event];

        return match ($type) {
            'CHECKOUT.ORDER.APPROVED' => new WebhookEvent(...$base, status: 'approved', gatewayId: $resource['id'] ?? null),

            'PAYMENT.CAPTURE.COMPLETED' => new WebhookEvent(
                ...$base,
                status: 'paid',
                gatewayId: Arr::get($resource, 'supplementary_data.related_ids.order_id'),
                captureId: $resource['id'] ?? null,
                amount: Arr::get($resource, 'amount.value'),
                currency: Arr::get($resource, 'amount.currency_code'),
            ),

            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED' => new WebhookEvent(
                ...$base,
                status: 'failed',
                gatewayId: Arr::get($resource, 'supplementary_data.related_ids.order_id'),
                captureId: $resource['id'] ?? null,
            ),

            'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED' => new WebhookEvent(
                ...$base,
                status: 'refunded',
                captureId: $this->captureIdFromRefund($resource, $type),
                amount: Arr::get($resource, 'amount.value'),
                currency: Arr::get($resource, 'amount.currency_code'),
                refundId: $type === 'PAYMENT.CAPTURE.REFUNDED' ? ($resource['id'] ?? null) : null,
            ),

            default => null,
        };
    }

    // --- internals ------------------------------------------------------

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }

    private function accessToken(): string
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('PayPal is not configured: set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET.');
        }

        $key = 'paypal.token.' . md5($this->baseUrl . $this->clientId);

        if ($token = Cache::get($key)) {
            return $token;
        }

        $response = Http::baseUrl($this->baseUrl)
            ->asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->timeout($this->timeout)
            ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        $this->failUnless($response, 'authenticate');

        $token = $response->json('access_token');
        Cache::put($key, $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }

    /** Map a PayPal order (create/capture/get response) to a PaymentResult. */
    private function fromOrder(array $order): PaymentResult
    {
        $capture = Arr::get($order, 'purchase_units.0.payments.captures.0');
        $orderStatus = $order['status'] ?? '';

        $status = match (true) {
            $capture !== null => match ($capture['status'] ?? '') {
                'COMPLETED', 'PARTIALLY_REFUNDED', 'REFUNDED' => 'paid',
                'PENDING' => 'pending',
                default => 'failed',
            },
            $orderStatus === 'APPROVED' => 'approved',
            $orderStatus === 'VOIDED' => 'failed',
            default => 'pending',
        };

        return new PaymentResult(
            gatewayId: $order['id'],
            status: $status,
            paymentDetails: array_filter([
                'paypal_order_id' => $order['id'],
                'capture_id' => $capture['id'] ?? null,
                'payer_email' => Arr::get($order, 'payer.email_address'),
                'pending_reason' => Arr::get($capture, 'status_details.reason'),
            ]),
            rawResponse: $order,
            captureId: $capture['id'] ?? null,
            amount: Arr::get($capture, 'amount.value'),
            currency: Arr::get($capture, 'amount.currency_code'),
            gatewayStatus: $capture['status'] ?? $orderStatus,
        );
    }

    private function captureIdFromRefund(array $resource, string $type): ?string
    {
        if ($type === 'PAYMENT.CAPTURE.REVERSED') {
            return $resource['id'] ?? null; // resource is the capture itself
        }

        $up = collect($resource['links'] ?? [])->firstWhere('rel', 'up')['href'] ?? '';

        return preg_match('~/captures/([^/?]+)~', $up, $m) ? $m[1] : null;
    }

    private function failUnless(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        Log::error("PayPal {$action} failed", [
            'status' => $response->status(),
            'debug_id' => $response->header('PayPal-Debug-Id'),
            'body' => $response->json(),
        ]);

        throw new RuntimeException("PayPal could not {$action} (HTTP {$response->status()}).");
    }
}
