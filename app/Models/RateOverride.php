<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Seasonal price for a room type. end_date is inclusive. */
class RateOverride extends Model
{
    protected $fillable = ['room_type_id', 'label', 'start_date', 'end_date', 'price', 'min_nights', 'priority'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
