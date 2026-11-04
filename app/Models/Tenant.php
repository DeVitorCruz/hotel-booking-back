<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'user_id', 'name', 'slug',
        'status', 'trial_ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the user owner
     * 
     * @return BelongsTo
     */
    public function owner(): BelongsTo 
    {
        return $this->belongsTo(PlatformUser::class, 'user_id');
    }

    /**
     * Get all Tenant apps 
     * 
     * @return HasMany
     */
    public function apps(): HasMany
    {
        return $this->hasMany(TenantApp::class);
    }

    /**
     * Get Tenant's subscription
     *   
     * @return HasOne
     */
    public function subscription(): HasOne 
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Check if the "status" is active
     * 
     * @return bool
     */
    public function isActive(): bool { return $this->status === 'active'; }

    /**
     * Check if the "status" is trial
     * 
     * @return bool
     */
    public function isTrial(): bool { return $this->status === 'trial'; }

    /**
     * Check if the "status" is suspended
     * 
     * @return bool
     */
    public function isSuspended(): bool { return $this->status === 'suspended'; }
}
