<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment for an order.
     * Returns a PaymentResult with gateway_id, status and payment_details
     * (PIX QR code, boleto URL, card redirect, etc.)
     * 
     * @param  Order         $order
     * @param  string        $method
     * @param  array         $data
     * @return PaymentResult
     */
    public function createPayment(Order $order, string $method, array $data): PaymentResult;

    /**
     * Query the current status of a payment from the gateway.
     * 
     * @param  string $gatewayId
     * @return string
     */
    public function getPaymentStatus(string $gatewayId): string;

    /**
     * Cancel a pending payment at the gateway level.
     * 
     * @param  string $gatewayId
     * @return bool
     */
    public function cancelPayment(string $gatewayId): bool;

    /**
     * Validate that an incoming webhook request is authentic.
     * Each gateway signs webhooks differently.
     * 
     * @param  Request $request
     * @return bool       
     */
    public function validateWebhook(Request $request): bool;

    /**
     * Parse a webhook payload into a normalized status string.
     * Returns: pending | paid | failed | refunded
     * 
     * @param  Request $request
     * @return string
     */
    public function parseWebhookStatus(Request $request): string;

    /**
     * Extract the gateway transaction ID from a webhook payload.
     * 
     * @param  Request $request
     * @return string
     */
    public function parseWebhookGatewayId(Request $request): string;
}
