<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Refund request - admin managed.
 * 
 * Status: pending | processed | rejected
 */
class Refund extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'payment_id',
        'order_id',
        'amount',
        'reason',
        'status',
        'requested_by',
        'processed_by',
        'processed_at',
        'admin_notes',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Get payment by refund
     * 
     * @return BelongsTo<Payment>
     */
    public function payment(): BelongsTo 
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get orders by refund
     * 
     * @return BelongsTo<Order>
     */
    public function order(): BelongsTo 
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get user who requested the refund
     * 
     * @return BelongsTo<User>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get user who processed the refund 
     * 
     * @return BelongsTo<User>
     */
    public function processedBy(): BelongsTo 
    {
        return $this->belongsTo(User::class, 'processed_by'); 
    }

    /**
     * Check if the refund status is pending
     * 
     * @return bool
     */
    public function isPending(): bool 
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the refund status is processed
     * 
     * @return bool
     */
    public function isProcessed(): bool 
    {
        return $this->status === 'processed';
    }

    /**
     * Check if the refund status is rejected
     * 
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
