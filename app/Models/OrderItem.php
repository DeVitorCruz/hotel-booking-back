<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

/**
 * Represents a single item within an order.
 * 
 * Stores a snapshot of the product variant data at the time of purchase.
 * This ensures order history remains accurate even if the seller later
 * edits or deletes the products.
 * 
 * @property int         $id
 * @property int         $order_id
 * @property int|null    $product_variant_id
 * @property int|null    $seller_id
 * @property string      $product_name
 * @property string      $variant_sku
 * @property float       $unit_price
 * @property int         $quantity
 * @property string|null $image_path
 * @property array|null  $attributes
 */
class OrderItem extends Model
{
    protected $connection = 'tenant';

    use HasFactory;
    
    protected $fillable = [
		'order_id',
		'product_variant_id',
		'seller_id',
		'product_name',
		'variant_sku',
		'unit_price',
		'quantity',
		'image_path',
		'attributes',
    ];
    
    protected $casts = [
		'attributes' => 'array',
		'unit_price' => 'float'
    ];
    
    /**
     * The order this item belongs to.
     * 
     * @return BelongsTo<Order, OrderItem>
     */
    public function order(): BelongsTo
    {
		return $this->belongsTo(Order::class);
    }
    
    /**
     * The product variant (may be null if variant was deleted).
     * 
     * @return BelongsTo<ProductVariant, OrderItem>
     */
    public function variant(): BelongsTo
    {
		return $this->belongsTo(ProductVariant::class, 'product_variant_id');
	}
	
	/**
	 * The seller who listed this item (may be null if seller was deleted).
	 * 
	 * @return BelongsTo<Seller, OrderItem>
	 */
	public function seller(): BelongsTo
	{
		return $this->belongsTo(Seller::class);
	}
	
	/**
	 * Calculate the subtotal for this item.
	 * 
	 * @return float
	 */	
	public function getSubtotal(): float
	{
		return $this->unit_price * $this->quantity;
	}
}
