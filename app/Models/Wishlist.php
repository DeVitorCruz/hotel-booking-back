<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * User wishlist - one per user, registered users only (no guest wishlist).
 */
class Wishlist extends Model
{
    protected $connection = 'tenant';

    protected $fillable = ['user_id'];

    /**
     * get the user owning these related wishlists
     * 
     * @return BelongsTo view user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * get wishlist_items of this related wishlist   
     * 
     * @return HasMany view wishlist_items
     */
    public function items(): HasMany 
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * get products owning theses related wishlist_items
     * 
     * @return BelongsToMany view products
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlist_items')->withTimestamps();
    }
}
