<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Anything a payment gateway can charge for (a Booking today; extras or invoices later).
 * Gateways only talk to this contract, never to a concrete model.
 */
interface Payable
{
    /** Reference shown to the payer and sent to the gateway (e.g. booking reference). */
    public function payableReference(): string;

    /** Amount to charge now, as a decimal string with 2 places ("150.00"). */
    public function payableAmount(): string;

    /** ISO 4217 currency code. */
    public function payableCurrency(): string;

    /** Short description for the gateway's checkout screen. */
    public function payableDescription(): string;

    public function payerName(): ?string;

    public function payerEmail(): ?string;

    public function payments(): MorphMany;
}
