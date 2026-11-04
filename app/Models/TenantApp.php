<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantApp extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'tenant_id', 'app_type_id', 'theme_id',
        'name', 'slug', 'db_name', 'status', 'settings',
    ];

    protected $casts = ['settings' => 'array'];

    /**
     * Get the Tenant of the app
     * 
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get app type of the Tenant's app
     * 
     * @return BelongsTo
     */
    public function appType(): BelongsTo
    {
        return $this->belongsTo(AppType::class);
    }

    /**
     * Get theme of the Tenant's app
     * 
     * @return BelongsTo
     */
    public function theme(): BelongsTo 
    {
        return $this->belongsTo(Theme::class);
    }

    /**
     * Get Tenant's app domains
     * 
     * @return HasMany
     */
    public function domains(): HasMany
    {
        return $this->hasMany(DomainMapping::class);
    }

    /**
     * Get frist domain
     * 
     * @return ?DomainMapping
     */
    public function primaryDomain(): ?DomainMapping
    {
        return $this->domains()->where('is_primary', true)->first();
    }

    /**
     * Check if the status of the Tenant's app is active
     * 
     * @return bool
     */
    public function isActive(): bool { return $this->status === 'active'; }
}
