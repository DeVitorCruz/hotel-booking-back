<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks every status change for an order.
 * 
 * Immutable - records are only created, never updated or deleted.
 * Provides a full audit trail of the order's lifecycle.
 * 
 * @property int         $id
 * @property int         $order_id
 * @property string      $status
 * @property string|null $comment
 * @property int|null    $changed_by
 * @property \Carbon\Carbon $created_at
 */
class OrderStatusHistory extends Model
{
    protected $connection = 'tenant';

    // Disable updated_at - history records are immutable
    const UPDATED_AT = null;

    /** @var string */
   protected $table = 'order_status_history';
    
    protected $fillable = [
		'order_id',
		'status',
		'comment',
		'changed_by'
    ];
    
    protected $casts = [
		'created_at' => 'datetime',
    ];
    
    /**
     * The order this history record belongs to.
     * 
     * @return BelongsTo<Order, OrderStatusHistory>
     */
    public function order(): BelongsTo
    {
		return $this->belongsTo(Order::class);
	}
	
	/**
	 * The user who made this status change.
	 * Null if changed by the system automatically.
	 * 
	 * @return BelongsTo<User, OrderStatusHistory>
	 */
	public function changedBy(): BelongsTo
	{
		return $this->belongsTo(User::class, 'changed_by');
	}
}
