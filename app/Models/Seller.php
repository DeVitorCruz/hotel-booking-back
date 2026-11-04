<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Seller store profile - extends a User with store capabilities.
 * 
 * A user becomes a seller by submitting an onboarding request.
 * The seller profile must be approved by an admin/owner before the user can list products.
 * 
 * status: pending | active | suspended | rejected
 * is_marketplace: if true, this seller can hire store employees
 * 
 * @property int         $id
 * @property int         $user_id
 * @property string      $store_name 
 * @property string      $slug
 * @property string|null $description
 * @property string|null $store_logo
 * @property string|null $store_banner
 * @property float       $commission_rate
 * @property bool        $is_marketplace
 * @property string      $status
 * @property string|null $rejection_reason
 */
class Seller extends Model
{
    protected $connection = 'tenant';


    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * 
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'store_name',
        'slug',
        'description',
        'store_logo',
        'store_banner',
        'commission_rate',
        'is_marketplace',
        'status',
        'rejection_reason'
    ];

    protected $casts = [
        'is_marketplace' => 'boolean',
        'commission_rate' => 'decimal:2',
    ];

    /**
     * The user who owns this seller profile.
     * 
     * @return BelongsTo<User, Seller> 
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Products listed by this seller.
     * 
     * @return HasMany<Product>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Teams scoped to this seller store.
     * 
     * return HasMany<Team>
     */
    public function teams(): HasMany 
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Employment records for this store.
     * 
     * @return HasMany<Employment>
     */
    public function storeEmployments(): HasMany 
    {
        return $this->hasMany(Employment::class);
    }

    /**
     * Check if the status of the store is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the status of the store is pending
     * 
     * @return bool
     */
    public function isPending(): bool 
    {
        return $this->status === 'pending';        
    }

    /**
     * Check if the status of the store is suspended
     * 
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Retrieve the reviews for this Store
     * 
     * @return MorphMany<Review>
     */
    public function reviews(): MorphMany 
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Retrieve only the approved for this Store 
     * 
     * @return MorphMany<Review>
     */
    public function approvedReviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable')
            ->where('status', 'approved');
    }
}
