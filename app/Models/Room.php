<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical room. status: active | maintenance | inactive
 * Only active rooms are offered for sale.
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = ['room_type_id', 'number', 'floor', 'status', 'notes'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function nights(): HasMany
    {
        return $this->hasMany(RoomNight::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
