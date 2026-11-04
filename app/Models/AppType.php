<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppType extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'name', 'slug', 'description',
        'icon', 'db_template', 'is_active'
    ];

    protected $casts = ['is_active'=> 'boolean'];

    /**
     * Get all appType's themes
     * 
     * @return HasMany
     */
    public function themes(): HasMany 
    {
        return $this->hasMany(Theme::class);
    }

    /**
     * Get all tenant's apps
     * 
     * @return HasMany
     */
    public function tenantApps(): HasMany
    {
        return $this->hasMany(TenantApp::class);
    }
}
