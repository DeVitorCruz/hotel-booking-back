<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A payment attempt for any Payable (currently Booking).
 *
 * gateway: paypal | pay_at_hotel
 * status:  pending | paid | failed | refunded | partially_refunded
 */
class Payment extends Model
{
    protected $fillable = [
        'payable_type',
        'payable_id',
        'gateway',
        'method',
        'gateway_id',
        'gateway_capture_id',
        'gateway_status',
        'amount',
        'currency',
        'status',
        'refunded_amount',
        'gateway_response',
        'payment_details',
        'paid_at',
        'expires_at',
    ];

    protected $hidden = ['gateway_response'];

    protected $casts = [
        'gateway_response' => 'array',
        'payment_details' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return in_array($this->status, ['refunded', 'partially_refunded'], true);
    }
}
