<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sellable category of room ("Deluxe Double"). Guests book a room type;
 * the system assigns a concrete Room automatically.
 */
class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'short_description', 'description',
        'max_adults', 'max_children', 'max_occupancy',
        'beds', 'size_sqm', 'base_price', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomTypeImage::class)->orderBy('sort_order');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function rateOverrides(): HasMany
    {
        return $this->hasMany(RateOverride::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function fitsGuests(int $adults, int $children): bool
    {
        return $adults <= $this->max_adults
            && $children <= $this->max_children
            && ($adults + $children) <= $this->max_occupancy;
    }
}
