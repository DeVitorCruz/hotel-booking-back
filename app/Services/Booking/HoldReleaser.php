<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expires unpaid PayPal holds and frees their room nights.
 * Runs every minute from the scheduler and opportunistically before searches.
 */
class HoldReleaser
{
    /** @return int number of holds expired */
    public function releaseExpired(): int
    {
        $count = 0;

        Booking::query()->expiredHolds()->orderBy('id')->limit(200)->pluck('id')
            ->each(function (int $id) use (&$count) {
                if ($this->expire($id)) {
                    $count++;
                }
            });

        return $count;
    }

    public function expire(int $bookingId): bool
    {
        return DB::transaction(function () use ($bookingId) {
            /** @var Booking|null $booking */
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            // Re-check under the lock: a capture may have confirmed it meanwhile.
            if (! $booking || ! $booking->isHoldExpired()) {
                return false;
            }

            $booking->nightsReserved()->delete();
            $booking->update(['status' => Booking::STATUS_EXPIRED]);

            $booking->payments()->where('status', 'pending')->update([
                'status' => 'failed',
                'gateway_status' => 'HOLD_EXPIRED',
            ]);

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => Booking::STATUS_PENDING,
                'to_status' => Booking::STATUS_EXPIRED,
                'note' => 'Payment not completed before the hold expired.',
            ]);

            Log::info('Booking hold expired', ['reference' => $booking->reference]);

            return true;
        });
    }
}
