<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Models\Room;
use App\Models\RoomNight;
use App\Models\RoomType;
use App\Services\Hotel\HotelSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Answers "which rooms are free for these nights?".
 * A room is free when it is active and has no RoomNight row in [check_in, check_out).
 */
class AvailabilityService
{
    public function __construct(
        private HotelSettings $settings,
        private PriceCalculator $prices,
        private HoldReleaser $holds,
    ) {}

    /**
     * Validate stay dates against hotel rules. Throws BookingException (422).
     */
    public function assertValidStay(CarbonImmutable $checkIn, CarbonImmutable $checkOut): void
    {
        $today = $this->settings->today();
        $nights = (int) $checkIn->diffInDays($checkOut);

        if ($checkIn->lt($today)) {
            throw new BookingException('Check-in date cannot be in the past.', 'invalid_dates');
        }

        if ($nights < 1) {
            throw new BookingException('Check-out must be after check-in.', 'invalid_dates');
        }

        $max = (int) $this->settings->get('max_nights', 30);
        if ($nights > $max) {
            throw new BookingException("Stays are limited to {$max} nights.", 'invalid_dates');
        }

        $advance = (int) $this->settings->get('max_advance_days', 365);
        if ($checkIn->gt($today->addDays($advance))) {
            throw new BookingException("Bookings open {$advance} days in advance.", 'invalid_dates');
        }
    }

    /**
     * IDs of sellable rooms of this type that are free for the whole stay.
     *
     * @return Collection<int, int>
     */
    public function freeRoomIds(RoomType $roomType, CarbonImmutable $checkIn, CarbonImmutable $checkOut): Collection
    {
        $occupied = $this->occupiedRoomIds($checkIn, $checkOut);

        return Room::query()
            ->sellable()
            ->where('room_type_id', $roomType->id)
            ->whereNotIn('id', $occupied)
            ->orderBy('id')
            ->pluck('id');
    }

    /**
     * Search all active room types that fit the party.
     *
     * @return Collection<int, array{room_type: RoomType, available: int, quote: Quote}>
     */
    public function search(CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $adults, int $children = 0): Collection
    {
        $this->assertValidStay($checkIn, $checkOut);
        $this->holds->releaseExpired();

        $occupied = $this->occupiedRoomIds($checkIn, $checkOut);

        $freeByType = Room::query()
            ->sellable()
            ->whereNotIn('id', $occupied)
            ->selectRaw('room_type_id, COUNT(*) as free')
            ->groupBy('room_type_id')
            ->pluck('free', 'room_type_id');

        $nights = (int) $checkIn->diffInDays($checkOut);

        return RoomType::query()
            ->active()
            ->with(['images', 'amenities'])
            ->orderBy('sort_order')
            ->orderBy('base_price')
            ->get()
            ->filter(fn (RoomType $type) => $type->fitsGuests($adults, $children))
            ->map(function (RoomType $type) use ($freeByType, $checkIn, $checkOut, $nights) {
                $quote = $this->prices->quote($type, $checkIn, $checkOut);

                return [
                    'room_type' => $type,
                    'available' => $nights >= $quote->minNights ? (int) ($freeByType[$type->id] ?? 0) : 0,
                    'quote' => $quote,
                ];
            })
            ->values();
    }

    /** @return Collection<int, int> */
    private function occupiedRoomIds(CarbonImmutable $checkIn, CarbonImmutable $checkOut): Collection
    {
        return RoomNight::query()
            ->whereDate('date', '>=', $checkIn->toDateString())
            ->whereDate('date', '<', $checkOut->toDateString())
            ->distinct()
            ->pluck('room_id');
    }
}
