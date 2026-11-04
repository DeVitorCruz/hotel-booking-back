<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'app_type_id', 'name', 'slug', 'description',
        'preview_image', 'thumbnail', 'is_active', 'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get app type matching the theme
     * 
     * @return BelongsTo
     */
    public function appType(): BelongsTo 
    {
        return $this->belongsTo(AppType::class);
    }

    /**
     * Get all Tenant's apps matching the theme
     * 
     * @return HasMany
     */
    public function tenantApps(): HasMany 
    {
        return $this->hasMany(TenantApp::class);
    }
}
