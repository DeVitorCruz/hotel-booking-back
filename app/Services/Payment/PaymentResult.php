<?php

namespace App\Services\Payment;

/**
 * Normalized result returned by any gateway.
 * Services always work with this object, never with raw gateway responses.
 *
 * status: pending | approved | paid | failed
 *   approved = buyer approved, capture still needed (PayPal)
 */
class PaymentResult
{
    public function __construct(
        public readonly string  $gatewayId,
        public readonly string  $status,
        public readonly array   $paymentDetails = [],   // approve_url, capture_id, instructions...
        public readonly array   $rawResponse = [],      // full gateway response for logging
        public readonly ?string $expiresAt = null,
        public readonly ?string $captureId = null,
        public readonly ?string $amount = null,         // captured amount, decimal string
        public readonly ?string $currency = null,
        public readonly ?string $gatewayStatus = null,  // raw status string from the gateway
    ) {}

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function toArray(): array
    {
        return [
            'gateway_id' => $this->gatewayId,
            'status' => $this->status,
            'payment_details' => $this->paymentDetails,
            'expires_at' => $this->expiresAt,
        ];
    }
}
