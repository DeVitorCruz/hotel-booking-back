<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Internal working group.
 * 
 * owner_id: user who created/owns this team
 * seller_id: null = platform team | set = store-scoped team
 */
class Team extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'owner_id',
        'seller_id',
        'name', 
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
    
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role', 'joined_at', 'left_at', 'status')
            ->withTimestamps();
    }

    public function leader(): ?User
    {
        return $this->members()
            ->wherePivot('role', 'leader')
            ->wherePivot('status', 'active')
            ->first();
    }

    public function isPlatformTeam(): bool
    {
        return is_null($this->seller_id);
    }
}
