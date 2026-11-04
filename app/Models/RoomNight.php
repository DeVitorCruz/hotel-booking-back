<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Occupancy of one room for one night, owned by a booking or a block.
 * Unique (room_id, date) at the database level prevents double booking.
 */
class RoomNight extends Model
{
    public $timestamps = false;

    protected $fillable = ['room_id', 'date', 'booking_id', 'room_block_id'];

    protected $casts = ['date' => 'date'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
