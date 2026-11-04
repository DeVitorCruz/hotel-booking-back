<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Represents a buyer's order.
 * 
 * An order is created form a cart at checkout. It contains a snapshot
 * of all purchased items and tracks its status through the fulfillment
 * lifecycle via OrderStatusHistory.
 * 
 * Status flow:
 *   pending -> paid -> processing -> shipped -> delivered
 *                                            -> cancelled / refunded
 * 
 * @property int    $id
 * @property int    $user_id
 * @property string $status
 * @property float  $total_amount
 * @property array  $shipping_address
 * @property string|null $notes
 */
class Order extends Model
{
    protected $connection = 'tenant';

    use HasFactory;
    
    protected $fillable = [
		'user_id',
		'status',
		'total_amount',
		'shipping_address',
		'recipient_name',
		'recipient_phone',
		'notes'
    ];
    
    protected $casts = [
		'shipping_address' => 'array',
		'total_amount' => 'float',
    ];
    
    /** Available order statuses */
    const STATUSES = [
		'pending', 
		'paid', 
		'processing',
		'shipped',
		'delivered', 
		'cancelled', 
		'refunded',
    ];
    
    /**
     * The buyer who placed this order.
     * 
     * @return BelongsTo<User, Order>
     */
    public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}	
	
	/**
	 * The items in this order.
	 * 
	 * @return HasMany<OrderItem> 
	 */
	public function items(): HasMany
	{
		return $this->hasMany(OrderItem::class);
	}
	
	/**
	 * The full status change history for this order.
	 * 
	 * @return HasMany<OrderStatusHistory>
	 */
	public function statusHistory(): HasMany
	{
		return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'asc');
	}
	
	/**
	 * Update the order status and record it in the history.
	 * 
	 * @param string      $status The new status.
	 * @param int|null    $changedBy The user ID performing the change.
	 * @param string|null $comment Optional comment about the change.
	 * @return void
	 */
	public function updateStatus(string $status, ?int $changedBy = null, ?string $comment = null): void 
	{
		$this->update(['status' => $status]);
		
		$this->statusHistory()->create([
			'status' => $status,
			'changed_by' => $changedBy,
			'comment' => $comment,
		]);
	}

	/**
	 * Get orders by the payment
	 * 
	 * @return HasOne<Payment>
	 */
	public function payment(): HasOne 
	{
		return $this->hasOne(Payment::class);
	}

	/**
	 * Get orders by the refund
	 * 
	 * @return HasOne<Refund>
	 */
	public function refund(): HasOne 
	{
		return $this->hasOne(Refund::class);
	}
}
