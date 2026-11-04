<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Seller;
use App\Models\Category;


class Product extends Model
{
    protected $connection = 'tenant';

    /**
     * The attribute that are mass assignable.
     * 
     * @var array<int, string>
     */
    protected $fillable = [
        'seller_id',
		'category_id',
        'name',
        'slug',
        'description',
    ];

    /**
     * Get the seller that owns the product.
     * 
     * @return BelongsTo<Seller>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

	/**
	 * Get the category that owns the product.
     * 
     * @return BelongsTo<Category>
	 */
	public function category(): BelongsTo 
	{
		return $this->belongsTo(Category::class);
	}

    /**
     * Get the variants for the product.
     * 
     * @return HasMany<ProductVariant>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Retrieve the reviews for this product
     * 
     * @return MorphMany<Review>
     */
    public function reviews(): MorphMany 
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Retrieve only the approved reviews 
     * 
     * @return MorphMany<Review>
     */
    public function approvedReviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable')
            ->where('status', 'approved');
    } 
}
