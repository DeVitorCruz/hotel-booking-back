<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\RoomNight;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Hotel\HotelSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Creates, confirms, cancels and moves bookings through their lifecycle.
 *
 * The overbooking guard: reserving a room inserts one room_nights row per night.
 * The unique (room_id, date) index rejects the second of two concurrent
 * requests for the same room, and we simply try the next free room.
 */
class BookingService
{
    public function __construct(
        private HotelSettings $settings,
        private AvailabilityService $availability,
        private PriceCalculator $prices,
        private HoldReleaser $holds,
    ) {}

    /**
     * Reserve a room of the requested type.
     *
     * @param array{
     *   room_type_id:int, check_in:string, check_out:string, adults:int, children?:int,
     *   guest_name:string, guest_email:string, guest_phone?:string|null,
     *   special_requests?:string|null, payment_method:string, source?:string
     * } $data
     */
    public function hold(array $data, ?User $user = null): Booking
    {
        $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();
        $adults = (int) $data['adults'];
        $children = (int) ($data['children'] ?? 0);
        $method = $data['payment_method'];
        $source = $data['source'] ?? 'web';

        if ($source === 'web') {
            $this->availability->assertValidStay($checkIn, $checkOut);
        }

        if ($source === 'web' && ! $this->settings->paymentMethodEnabled($method)) {
            throw new BookingException('This payment method is not available.', 'payment_method_disabled');
        }

        $roomType = RoomType::query()->active()->findOrFail($data['room_type_id']);

        if (! $roomType->fitsGuests($adults, $children)) {
            throw new BookingException('This room type cannot accommodate your party.', 'too_many_guests');
        }

        $quote = $this->prices->quote($roomType, $checkIn, $checkOut, $method);

        if ($quote->nights() < $quote->minNights) {
            throw new BookingException("A minimum stay of {$quote->minNights} nights applies to these dates.", 'min_nights');
        }

        $this->holds->releaseExpired();

        // Pay at hotel, admin bookings and 0% deposit need no online payment: confirm right away.
        $needsOnlinePayment = $method === 'paypal' && $quote->dueNowCents > 0;

        foreach ($this->availability->freeRoomIds($roomType, $checkIn, $checkOut) as $roomId) {
            try {
                $booking = DB::transaction(fn () => $this->createWithNights(
                    $roomId, $roomType, $checkIn, $checkOut, $quote, $data, $user, $needsOnlinePayment
                ));
            } catch (UniqueConstraintViolationException) {
                continue; // someone took this room a moment ago, try the next one
            }

            Log::info('Booking created', ['reference' => $booking->reference, 'status' => $booking->status]);

            if ($booking->status === Booking::STATUS_CONFIRMED) {
                $this->sendConfirmation($booking);
            }

            return $booking;
        }

        throw BookingException::noAvailability();
    }

    /**
     * Confirm a pending booking (after payment). Idempotent.
     */
    public function confirm(Booking $booking, ?string $note = null, ?User $actor = null): Booking
    {
        $confirmed = DB::transaction(function () use ($booking, $note, $actor) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status === Booking::STATUS_CONFIRMED) {
                return false;
            }

            if ($locked->status === Booking::STATUS_EXPIRED) {
                // Paid after the hold lapsed: take the same room back if it is still free.
                if (! $this->reReserveNights($locked)) {
                    throw BookingException::holdExpired();
                }
                $locked->status = Booking::STATUS_PENDING;
            }

            if (! $locked->isPending()) {
                throw BookingException::invalidState("Booking cannot be confirmed from status [{$locked->status}].");
            }

            $this->transition($locked, Booking::STATUS_CONFIRMED, $note ?? 'Payment received.', $actor, [
                'confirmed_at' => now(),
                'hold_expires_at' => null,
            ]);

            return true;
        });

        $booking->refresh();

        if ($confirmed) {
            $this->sendConfirmation($booking);
        }

        return $booking;
    }

    /**
     * Cancel a booking and free its nights. Refunds are handled by the caller
     * (BookingPaymentService) so this stays gateway-agnostic.
     */
    public function cancel(Booking $booking, string $reason, ?User $actor = null, bool $notifyGuest = true): Booking
    {
        DB::transaction(function () use ($booking, $reason, $actor) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $locked->canTransitionTo(Booking::STATUS_CANCELLED)) {
                throw BookingException::invalidState("Booking cannot be cancelled from status [{$locked->status}].");
            }

            $locked->nightsReserved()->delete();
            $locked->payments()->where('status', 'pending')->update(['status' => 'failed', 'gateway_status' => 'CANCELLED']);

            $this->transition($locked, Booking::STATUS_CANCELLED, $reason, $actor, [
                'cancelled_at' => now(),
                'cancellation_reason' => mb_substr($reason, 0, 255),
                'hold_expires_at' => null,
            ]);
        });

        $booking->refresh();

        if ($notifyGuest) {
            Mail::to($booking->guest_email)->queue(new BookingCancelledMail($booking));
        }

        return $booking;
    }

    /**
     * Staff status change: checked_in, checked_out, no_show.
     */
    public function changeStatus(Booking $booking, string $to, ?User $actor = null, ?string $note = null): Booking
    {
        if (in_array($to, [Booking::STATUS_CANCELLED, Booking::STATUS_CONFIRMED], true)) {
            throw BookingException::invalidState('Use the cancel/confirm actions for this change.');
        }

        DB::transaction(function () use ($booking, $to, $actor, $note) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $locked->canTransitionTo($to)) {
                throw BookingException::invalidState("Cannot move booking from [{$locked->status}] to [{$to}].");
            }

            $this->transition($locked, $to, $note, $actor);
        });

        return $booking->refresh();
    }

    /**
     * Whether a guest cancelling now is still inside the free-cancellation window.
     */
    public function isFreeCancellation(Booking $booking): bool
    {
        $tz = $this->settings->get('timezone', 'UTC');
        $checkInAt = CarbonImmutable::parse(
            $booking->check_in->toDateString() . ' ' . $this->settings->get('check_in_time', '14:00'),
            $tz
        );

        return CarbonImmutable::now($tz)->lte($checkInAt->subHours((int) $this->settings->get('cancellation_hours', 48)));
    }

    /**
     * Re-take the nights of an expired hold (caller holds the row lock).
     * Used when a guest completes payment right after the hold lapsed.
     */
    public function reclaim(Booking $locked, int $holdMinutes): bool
    {
        if ($locked->status !== Booking::STATUS_EXPIRED || ! $this->reReserveNights($locked)) {
            return false;
        }

        $this->transition($locked, Booking::STATUS_PENDING, 'Hold reclaimed for an in-flight payment.', null, [
            'hold_expires_at' => now()->addMinutes($holdMinutes),
        ]);

        return true;
    }

    public function manageUrl(Booking $booking): string
    {
        return rtrim((string) $this->settings->get('frontend_url'), '/')
            . '/booking/' . $booking->reference . '?token=' . urlencode((string) $booking->manage_token);
    }

    // --- internals ------------------------------------------------------

    private function createWithNights(
        int $roomId,
        RoomType $roomType,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        Quote $quote,
        array $data,
        ?User $user,
        bool $needsOnlinePayment,
    ): Booking {
        $booking = Booking::create([
            'reference' => $this->newReference(),
            'manage_token' => Str::random(40),
            'user_id' => $user?->id,
            'room_type_id' => $roomType->id,
            'room_id' => $roomId,
            'guest_name' => $data['guest_name'],
            'guest_email' => mb_strtolower($data['guest_email']),
            'guest_phone' => $data['guest_phone'] ?? null,
            'adults' => (int) $data['adults'],
            'children' => (int) ($data['children'] ?? 0),
            'special_requests' => $data['special_requests'] ?? null,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $quote->nights(),
            'currency' => $quote->currency,
            'nightly_rates' => $quote->nightlyRates(),
            'subtotal' => Quote::money($quote->subtotalCents),
            'taxes' => Quote::money($quote->taxCents),
            'total' => Quote::money($quote->totalCents),
            'amount_due_now' => Quote::money($needsOnlinePayment ? $quote->dueNowCents : 0),
            'status' => $needsOnlinePayment ? Booking::STATUS_PENDING : Booking::STATUS_CONFIRMED,
            'payment_method' => $data['payment_method'],
            'source' => $data['source'] ?? 'web',
            'hold_expires_at' => $needsOnlinePayment ? now()->addMinutes((int) $this->settings->get('hold_minutes', 15)) : null,
            'confirmed_at' => $needsOnlinePayment ? null : now(),
        ]);

        $this->insertNights($booking->id, $roomId, $checkIn, $checkOut);

        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => $booking->status,
            'note' => $needsOnlinePayment ? 'Room held, awaiting PayPal payment.' : 'Booking confirmed.',
            'changed_by' => ($data['source'] ?? 'web') === 'admin' ? $user?->id : null,
        ]);

        return $booking;
    }

    private function insertNights(int $bookingId, int $roomId, CarbonImmutable $checkIn, CarbonImmutable $checkOut): void
    {
        $rows = [];
        foreach (CarbonPeriod::create($checkIn, '1 day', $checkOut->subDay()) as $date) {
            $rows[] = ['room_id' => $roomId, 'date' => $date->toDateString(), 'booking_id' => $bookingId];
        }

        RoomNight::insert($rows); // throws UniqueConstraintViolationException on conflict
    }

    private function reReserveNights(Booking $booking): bool
    {
        try {
            DB::transaction(fn () => $this->insertNights(
                $booking->id,
                $booking->room_id,
                CarbonImmutable::parse($booking->check_in),
                CarbonImmutable::parse($booking->check_out),
            ));

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function transition(Booking $booking, string $to, ?string $note, ?User $actor, array $extra = []): void
    {
        $from = $booking->getOriginal('status');

        $booking->forceFill(array_merge($extra, ['status' => $to]))->save();

        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'changed_by' => $actor?->id,
        ]);
    }

    private function newReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I

        do {
            $ref = 'HB';
            for ($i = 0; $i < 6; $i++) {
                $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Booking::where('reference', $ref)->exists());

        return $ref;
    }

    private function sendConfirmation(Booking $booking): void
    {
        $mail = Mail::to($booking->guest_email);

        if ($hotelEmail = $this->settings->get('email')) {
            $mail->bcc($hotelEmail);
        }

        $mail->queue(new BookingConfirmedMail($booking, $this->manageUrl($booking)));
    }
}
