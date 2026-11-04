<?php

namespace App\Services\Payment;

use App\Contracts\Payable;
use Illuminate\Http\Request;

/**
 * Contract every payment gateway implements.
 *
 * Supports two-step gateways (PayPal: create → buyer approves → capture)
 * and offline ones (pay at hotel). Adding a gateway = implement this
 * interface + register it in GatewayFactory.
 */
interface PaymentGatewayInterface
{
    /** Gateway key stored on payments.gateway */
    public function name(): string;

    /**
     * Start a payment. For PayPal this creates an Order the buyer must approve;
     * PaymentResult::$paymentDetails carries what the frontend needs
     * (paypal_order_id, approve_url, ...).
     *
     * @param array $options return_url / cancel_url for redirect flows, etc.
     */
    public function createPayment(Payable $payable, array $options = []): PaymentResult;

    /**
     * Capture an approved payment (two-step gateways). Must be safe to call twice.
     */
    public function capturePayment(string $gatewayId): PaymentResult;

    /** Fetch the current state of a payment from the gateway. */
    public function getPaymentStatus(string $gatewayId): PaymentResult;

    /**
     * Refund a captured payment, fully or partially.
     *
     * @param string $amount decimal string, e.g. "120.00"
     */
    public function refundPayment(string $captureId, string $amount, string $currency): RefundResult;

    /** Verify an incoming webhook really comes from the gateway. */
    public function validateWebhook(Request $request): bool;

    /** Normalize a webhook into a WebhookEvent, or null when the event is irrelevant. */
    public function parseWebhook(Request $request): ?WebhookEvent;
}
