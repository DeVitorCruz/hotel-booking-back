<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RoomTypeImage extends Model
{
    protected $fillable = ['room_type_id', 'path', 'alt', 'sort_order'];

    protected $appends = ['url'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function getUrlAttribute(): string
    {
        return str_starts_with($this->path, 'http')
            ? $this->path
            : Storage::disk('public')->url($this->path);
    }
}
