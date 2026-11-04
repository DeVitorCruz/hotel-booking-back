<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'tenant_id', 'plan_id', 'gateway',
        'gateway_subscription_id', 'status',
        'current_period_start', 'current_period_end',
        'cancelled_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get subscription's tenant
     * 
     * @return BelongsTo
     */
    public function tenant(): BelongsTo 
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get suscription's plan
     * 
     * @return BelongsTo
     */
    public function plan(): BelongsTo 
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if the subscription is active
     * 
     * @return bool
     */
    public function isActive(): bool { return $this->status === 'active'; }
}
