<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Payment record - one per order.
 * 
 * getaway: mercadopago | pagseguro
 * method: pix | boleto | card
 * status: pending | paid | failed | refunded
 */
class Payment extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'order_id',
        'gateway',
        'method',
        'gateway_id',
        'gateway_status',
        'amount',
        'status',
        'gateway_response',
        'payment_details',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'payment_details' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Get orders by payment parameter
     * 
     * @return BelongsTo<Order>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get refund among payments
     * 
     * @return HasOne<Refund>
     */
    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    /**
     * Check if the status is "pending"
     * 
     * @return bool  
     */
    public function isPending(): bool 
    { 
        return $this->status === 'pending'; 
    }

    /**
     * Check if the status is paid
     * 
     * @return bool
     */
    public function isPaid(): bool 
    {
        return $this->status === 'paid';
    }

    /**
     * Check if the status is Failed
     * 
     * @return bool
     */
    public function isFailed(): bool 
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the status is refunded
     * 
     * @return bool
     */
    public function isRefunded(): bool 
    {
        return $this->status === 'refunded';
    }

    /**
     * Check if the method is "pix"
     * 
     * @return bool
     */
    public function isPix(): bool 
    {
        return $this->method === 'pix';
    }

    /**
     * Check if the method is "boleto"
     * 
     * @return bool
     */
    public function isBoleto(): bool 
    {
        return $this->method === 'boleto';
    }   

    /**
     * Check if the method is "card"
     * 
     * @return bool
     */
    public function isCard(): bool 
    {
        return $this->method === 'card';
    }
}


