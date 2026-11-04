<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public about page entry - owner curated, fully optional.
 * 
 * user_id: optional link to a real user account.
 *          null = static/manual entry added by owner.
 * is_visible: owner controls visibility per entry.
 */
class StaffSpotlight extends Model
{
    protected $connection = 'mercatura';

    protected $fillable = [
        'user_id',
        'name',
        'role_title',
        'bio',
        'photo',
        'linkedin',
        'twitter',
        'display_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAccount(): bool
    {
        return !is_null($this->user_id);
    }
}
