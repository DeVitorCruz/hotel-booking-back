<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Dates a room cannot be sold. end_date is exclusive (like check-out). */
class RoomBlock extends Model
{
    protected $fillable = ['room_id', 'start_date', 'end_date', 'reason', 'created_by'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function nights(): HasMany
    {
        return $this->hasMany(RoomNight::class);
    }
}
