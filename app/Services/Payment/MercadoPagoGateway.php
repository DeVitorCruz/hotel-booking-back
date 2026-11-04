<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class MercadoPagoGateway implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $accessToken;
    private string $webhookSecret;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->baseUrl = config('payment.mercadopago.base_url', 'https://api.mercadopago.com');
        $this->accessToken = config('payment.mercadopago.access_token', '');
        $this->webhookSecret = config('payment.mercadopago.webhook_secret', '');
    }

    public function createPayment(Order $order, string $method, array $data): PaymentResult 
    {
        $payload = $this->buildPayload($order, $method, $data);

        // Live: uncomment when credentials are set
        // $response = Http::withToken($this->accessToken)
        //     ->post("{$this->baseUrl}/v1/payments", $payload);
        
        // if ($response->failed()) {
        //     Log::error('MercadoPago payment failed', $response->json());

        //     throw new \RuntimeException('MercadoPago payment creation failed.');
        // }

        // $body = $response->json();
        // return $this->normalizeResult($body, $method);

        // STUB: returns mock result for development
        Log::info('MercadoPago createPayment stub', ['order' => $order->id, 'method' => $method]);

        return match($method) {
            'pix' => new PaymentResult(
                gatewayId: 'MP-STUB-PIX-'.$order->id,
                status: 'pending',
                paymentDetails: [
                    'pix_code' => 'stub-pix-copia-cola-'.$order->id,
                    'pix_qr_base64' => 'stub-base64-qr-code',
                    'expires_at' => now()->addMinutes(30)->toISOString(),
                ],
                rawResponse: $payload,
                expiresAt: now()->addMinutes(30)->toISOString(),
            ),
            'boleto' => new PaymentResult(
                gatewayId: 'MP-STUB-BOLETO-'.$order->id,
                status: 'pending',
                paymentDetails: [
                    'boleto_url' => 'https://stub.mercadopago.com/boleto/'.$order->id,
                    'barcode' => '1234.5678 9012.3456 7890.1234 5 12340000049990',
                    'expires_at' => now()->addMinutes(3)->toISOString(),
                ],
                rawResponse: $payload,
                expiresAt: now()->addMinutes(3)->toISOString(),
            ),
            'card' => new PaymentResult(
                gatewayId: 'MP-STUB-CARD-'.$order->id,
                status: 'pending',
                paymentDetails: [
                    'redirect_url' => 'https://stub.mercadopago.com/card/'.$order->id,
                ],
                rawResponse: $payload,
            ),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };
    }

    public function getPaymentStatus(string $gatewayId): string 
    {
        // Live
        // $response = Http::withToken($this->accessToken)
        //     ->get("{$this->baseUrl}/v1/payments/{$gatewayId}");
        // return $this->mapStatus($response->json('charges.0.status'));

        return 'pending'; // stub
    }

    public function cancelPayment(string $gatewayId): bool 
    {
        // Live
        // $response = Http::withToken($this->accessToken)
        //     ->put("{$this->baseUrl}/v1/payments/{$gatewayId}", ['status' => 'cancelled']);

        // return $response->successfull();

        return true; // stub
    }

    public function validateWebhook(Request $request): bool 
    {
        // Live
        // $signature = $request->header('x-signature');
        // $requestId = $request->header('x-request-id');
        // $dataId = $request->query('data.id') ?? $request->input('data.id');
        // $ts = '';

        // $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        // $expected = hash_hmac('sha256', $manifest, $this->webhookSecret);

        // return hash_equals($expected, $signature);

        return true; // stub - always valid in dev
    }

    public function parseWebhookStatus(Request $request): string
    {
        $status = $request->input('data.status')
            ?? $request->input('status') ?? 'pending';

        return $this->mapStatus($status);
    }

    public function parseWebhookGatewayId(Request $request): string
    {
        return (string) ($request->input('data.id') ?? $request->input('id') ?? '');
    }

    // Private helpers

    /**
     * Amount all necessary information to build a payload
     * 
     * @param  Order  $order
     * @param  string $method
     * @param  array  $data
     * @return array
     */
    private function buildPayload(Order $order, string $method, array $data): array
    {
        return [
            'transaction_amount' => $order->total_amount,
            'description' => "Order #{$order->id}",
            'payment_method_id' => $this->mapMethod($method),
            'payer' => [
                'email' => $data['email'] ?? $order->user->email,
                'first_name' => $data['first_name'] ?? $order->user->name,
            ],
            'external_reference' => (string) $order->id,
            'notification_url' => route('webhooks.mercadopago'),
        ];
    }

    /**
     * Normalize the value of method
     * 
     * @param  string $methos
     * @return string
     */
    private function mapMethod(string $method): string 
    {
        return match($method) {
            'pix' => 'pix',
            'boleto' => 'boleto',
            'card' => 'credit_card',

            default => $method,
        };
    }

    /**
     * Normalize the value of status
     * 
     * @param  string $status 
     * @return string
     */
    private function mapStatus(string $status): string
    {
        return match($status) {
            'approved', 'paid' => 'paid',
            'pending', 'in_process', 'authorized' => 'pending',
            'rejected', 'cancelled', 'refunded' => match($status) {
                'refunded' => 'refunded',
                
                default => 'failed',
            },
            default => 'pending',
        };
    }

    private function normalizeResult(JsonResponse $body, string $method): PaymentResult
    {
        return new PaymentResult();
    }
}
