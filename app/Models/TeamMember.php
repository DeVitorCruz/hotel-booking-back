<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Team membership record - tracks who belongs to which team.
 * 
 * role: leader | member
 * status: active | suspended | left
 * left_at: null = still active
 */
class TeamMember extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    { 
        return $this->belongsTo(User::class);
    }

    public function isLeader(): bool
    {
        return $this->role === 'leader';
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && is_null($this->left_at);
    }
}
