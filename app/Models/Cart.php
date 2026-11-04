<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a shopping cart.
 * 
 * A cart can belong to an authenticated user or a guest (identified
 * by session_id). When a guest logs in, their cart is merged into
 * their user cart.
 * 
 * @property int         $id
 * @property int|null    $user_id
 * @property string|null $session_id
 */
class Cart extends Model
{
    protected $connection = 'tenant';

  use HasFactory;
  
  protected $fillable = [
    'user_id',
    'session_id',
  ];
    
  /**
   * The user who owns this cart.
   * Null for guest carts.
   * 
   * @return BelongsTo<User, Cart>
   */
	public function user(): BelongsTo 
	{
		return $this->belongsTo(User::class);
  }
    
  /**
   * The items in this cart.
   * 
   * @return HasMany<CartItem>
   */
  public function items(): HasMany
  {
    return $this->hasMany(CartItem::class);
  }
  
  /**
   * Calculate the total price of all items in the cart.
   * 
   * @return float
   */
  public function getTotal(): float 
  {
    return $this->items->sum(function (CartItem $item) {
      return $item->quantity * $item->variant->price;
    });
  }
}
