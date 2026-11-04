<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Review - polymorphic, attached to Product or Seller.
 * 
 * Rules:
 * - Only registered users can review
 * - order _id required (verified purchase)
 * - One review per user per reviawable
 * - Editable while status = pending
 * - Locked once approved or rejected
 * - Admin approval required before public display
 * 
 * status: pending | approved | rejected
 */
class Review extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'reviewable_type',
        'reviewable_id',
        'order_id',
        'rating',
        'name',
        'email',
        'comment',
        'quality',
        'shipping',
        'value',
        'status',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rating' => 'integer',
        'quality' => 'integer',
        'shipping' => 'integer',
        'value' => 'integer'
    ];

    /**
     * Retrieve reviewable morph columns _id and _type
     * 
     * @return MorphTo
     */
    public function reviewable(): MorphTo 
    {
        return $this->morphTo();
    }

    /**
     * Retrieve the user responsible for the review
     * 
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retrieve the order related with the review
     * 
     * @return BelongsTo<Order>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Retrieve the user responsible for manage the permission 
     * 
     * @return BelongsTo<User>
     */
    public function approvedBy(): BelongsTo
    {   
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if the status of permission is pending
     * 
     * @return bool
     */
    public function isPending(): bool {
        return $this->status === 'pending';
    }

    /**
     * Check if the status of permission is approved
     * 
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the status of permission is locked
     * 
     * @return bool
     */
    public function isLocked(): bool
    {
        return in_array($this->status, ['approved', 'rejected']);
    }
}
