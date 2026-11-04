<?php

namespace App\Services\Payment;

/**
 * Normalized webhook.
 *
 * status: approved | paid | failed | refunded
 * gatewayId: the gateway payment/order ID we stored on payments.gateway_id
 * captureId: set for capture/refund events (PayPal)
 */
final class WebhookEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $type,
        public readonly string $status,
        public readonly ?string $gatewayId = null,
        public readonly ?string $captureId = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $refundId = null,
        public readonly array $raw = [],
    ) {}
}
