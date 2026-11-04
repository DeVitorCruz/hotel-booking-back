<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * Represents a single item in a shopping cart.
 * 
 * Links a specific ProductVariant to a Cart with a quantity.
 * The buyer selects a variant (e.g. red, size XL) not just a product.
 * 
 * @property int $id
 * @property int $cart_id
 * @property int $product_variant_id
 * @property int $quantity
 */
class CartItem extends Model
{
    protected $connection = 'tenant';

    use HasFactory;
    
    protected $fillable = [
      'cart_id',
      'product_variant_id',
      'quantity',
    ];
    
    /**
     * The cart this item belongs to.
     * 
     * @return BelongsTo<Cart, CartItem>
     */
    public function cart(): BelongsTo
    {
		  return $this->belongsTo(Cart::class);
    }
    
    /**
     * The product variant selected by the buyer.
     * 
     * @return BelongsTo<ProductVariant, CartItem>
     */
    public function variant(): BelongsTo
    {
		  return $this->belongsTo(ProductVariant::class, 'product_variant_id');
	  }
}
