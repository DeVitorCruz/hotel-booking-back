<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['wishlist_id', 'product_id'];

    /**
     * get the wishlist owning theses wishlist_items
     * 
     * @return BelongsTo view wishlist
     */
    public function wishlist(): BelongsTo 
    {
        return $this->belongsTo(Wishlist::class);
    }

    /**
     * get the product owning theses wishlist_items
     * 
     * @return BelongsTo view product
     */
    public function product(): BelongsTo 
    {
        return $this->belongsTo(Product::class); 
    }
}
