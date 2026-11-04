<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagSeguroGateway implements PaymentGatewayInterface
{
    private string $baseUrl;
    private string $token;
    private string $webhookSecret;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->baseUrl = config('payment.pagseguro.base_url', 'https://api.pagseguro.com');
        $this->token = config('payment.pagseguro.access_token', '');
        $this->webhookSecret = config('payment.pagseguro.webhook_secret', '');
    }

    public function createPayment(Order $order, string $method, array $data): PaymentResult
    {
        $payload = $this->buildPayload($order, $method, $data);

        // Live: uncomment when credentials are set
        // $response = Http::withHeaders([
        //     'Authorization' => "Bearer {$this->token}",
        //     'Content-Type' => 'application/json',
        // ])->post("{$this->baseUrl}/orders", $payload);

        // if ($response->failed()) {
        //     Log::error('PagSeguro payment failed', $response->json());
        //     throw new \RuntimeException('PagSeguro payment creation failed.');
        // }

        // $body = $response->json();
        // return $this->normalizeResult($body, $method);

        // STUB
        Log::info('PagSeguro createPayment stub', ['order' => $order->id, 'method' => $method]);

        return match($method) {
            'pix' => new PaymentResult(
              gatewayId: 'PS-STUB-PIX-' . $order->id,
              status: 'pending',
              paymentDetails: [
                'pix_code' => 'stub-ps-pix-copia-cola-' . $order->id,
                'pix_qr_base64' => 'stub-ps-base64-qr-code',
                'expires_at' => now()->addMinutes(30)->toISOString(),
              ],
              rawResponse: $payload,
              expiresAt: now()->addMinutes(30)->toISOString(),
            ),
            'boleto' => new PaymentResult(
              gatewayId: 'PS-STUB-BOLETO-' . $order->id,
              status: 'pending',
              paymentDetails: [
                'boleto_url' => 'https://stub.pagseguro.com/boleto/' . $order->id,
                'barcode' => '9876.5432 1098.7654 3210.9876 5 12340000049990',
                'expires_at' => now()->addMinutes(3)->toISOString(),
              ],
              rawResponse: $payload,
              expiresAt: now()->addMinutes(3)->toISOString(),
            ),
            'card' => new PaymentResult(
              gatewayId: 'PS-STUB-CARD-' . $order->id,
              status: 'pending',
              paymentDetails: [
                'redirect_url' => 'https://stub.pagseguro.com/card/' . $order->id,
              ],
              rawResponse: $payload,
            ),

            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };
    }

    public function getPaymentStatus(string $gatewayId): string
    {
        // Live
        // $response = Http::withToken($this->token)
        //     ->get("{$this->baseUrl}/orders/{$gatewayId}");
        // return $this->mapStatus($response->json('charges.0.status'));

        return 'pending'; // stub
    }

    public function cancelPayment(string $gatewayId): bool
    {
        // Live
        // $response = Http::withToken($this->token)
        //     ->delete("{$this->baseUrl}/orders/{$gatewayId}");
        // return $response->successful();

        return true; // stub
    }

    public function validateWebhook(Request $request): bool 
    {
        // Live
        // $signature = $request->header('x-pagseguro-signature');
        // $payload = $request->getContent();
        // $expected = hash_hmac('sha256', $payload, $this->webhookSecret);
        // return hash_equals($expected, $signature);

        return true; // stub
    }

    public function parseWebhookStatus(Request $request): string
    {   
        $status = $request->input('charges.0.status')
            ?? $request->input('status') ?? 'pending';

        return $this->mapStatus($status);
    }

    public function parseWebhookGatewayId(Request $request): string
    {
        return (string) ($request->input('id') ?? $request->input('reference_id') ?? '');
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
            'reference_id' => (string) $order->id, 
            'customer' => [
                'name' => $data['name'] ?? $order->user->name,
                'email' => $data['email'] ?? $order->user->email,
                'tax_id' => $data['cpf'] ?? '00000000000', // CPF required for PS
            ],
            'items' => [[
                'reference_id' => "order-{$order->id}",
                'name' => "Order #{$order->id}",
                'quantity' => 1, 
                'unit_amount' => (int) ($order->total_amount * 100), // PS uses cents
            ]],
            'charges' => [[
                'reference_id' => "charge-{$order->id}",
                'description' => "Order #{$order->id}",
                'amount' => [
                    'value' => (int) ($order->total_amount * 100),
                    'currency' => 'BRL',
                ],
                'payment_method' => [
                    'type' => $this->mapMethod($method),
                    'installments' => 1,
                    'capture' => true,
                ],
            ]],
            'notification_urls' => [route('webhooks.pagseguro')],
        ];
    }

    /**
     * Normalize the value of the Method
     * 
     * @param  stirng $method
     * @return string
     */
    private function mapMethod(string $method): string
    {
        return match($method) {
            'pix' => 'PIX',
            'boleto' => 'BOLETO',
            'card' => 'CREDIT_CARD',

            default => strtoupper($method),
        };
    }

    /**
     * Normalize the value of the status
     * 
     * @param  string $status
     * @return string
     */
    private function mapStatus(string $status): string 
    {
        return match($status) {
            'PAID', 'AUTHORIZED', 'AVAILABLE' => 'paid',
            'WAITING', 'IN_ANALYSIS' => 'pending',
            'DECLINED', 'CANCELLED' => 'failed',
            'REFUNDED', 'RETURNED' => 'refunded',

            default => 'pending',
        };
    }

    private function normalizeResult(JsonResponse $body, string $method): PaymentResult 
    {
        return new PaymentResult();
    }
}
