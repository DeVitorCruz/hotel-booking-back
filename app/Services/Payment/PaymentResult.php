<?php

namespace App\Services\Payment;

/**
 * Normalized result returned by any gateway after createPayment().
 * Controllers and services always work with this object,
 * never with raw gateway responses.
 */
class PaymentResult
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly string  $gatewayId,
        public readonly string  $status,          // pending|paid|failed
        public readonly array   $paymentDetails,  // pix_code, boleto_url, etc
        public readonly array   $rawResponse,     // full gateway response for logging
        public readonly ?string $expiresAt = null,
    ) {}

    /**
     * Check if the payment result is "pending"
     * 
     * @return bool
     */
    public function isPending(): bool 
    { 
        return $this->status === 'pending';
    }

    /**
     * Check if the payment result is "paid"
     * 
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }   

    /**
     * Check if the payment result is "Failed"
     * 
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * get normalized gateway 
     * 
     * @return array
     */
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
