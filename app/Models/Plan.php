<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'name', 'slug', 'description',
        'price_monthly', 'price_yearly',
        'max_apps', 'max_products', 'max_domains',
        'features', 'is_active',
    ];

    /**
     * Find subscriptions
     * 
     * @return HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Check the if it's free
     * 
     * @return bool
     */
    public function isFree(): bool { return $this->slug === 'free'; }
}
