<?php

namespace App\Services\Payment;

/** Normalized refund outcome. status: completed | pending | failed */
final class RefundResult
{
    public function __construct(
        public readonly string $refundId,
        public readonly string $status,
        public readonly string $amount,
        public readonly array $rawResponse = [],
    ) {}

    public function succeeded(): bool
    {
        return in_array($this->status, ['completed', 'pending'], true);
    }
}
