<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainMapping extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'tenant_app_id', 'domain', 'type',
        'is_primary', 'is_verified', 'verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',      
    ];

    /**
     * Get Tenant's app of the domain mapping
     * 
     * @return BelongsTo
     */
    public function tenantApp(): BelongsTo
    {
        return $this->belongsTo(TenantApp::class);
    }

    /**
     * Check if the type is subdomain
     * 
     * @return bool
     */
    public function isSubdomain(): bool { return $this->type === 'subdomain'; }

    /**
     * Check if the type is custom
     * 
     * @return bool
     */
    public function isCustom(): bool { return $this->type === 'custom'; }
}
