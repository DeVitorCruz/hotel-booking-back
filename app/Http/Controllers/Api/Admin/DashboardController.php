<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomNight;
use App\Services\Hotel\HotelSettings;
use Illuminate\Http\JsonResponse;

/**
 * Front-desk summary: today's movements, occupancy and this month's takings.
 */
class DashboardController extends Controller
{
    /** GET /admin/dashboard */
    public function __invoke(HotelSettings $settings): JsonResponse
    {
        $today = $settings->today();
        $todayStr = $today->toDateString();
        $sellable = Room::sellable()->count();

        $with = ['roomType:id,name', 'room:id,number'];
        $pick = fn (Booking $b) => [
            'id' => $b->id,
            'reference' => $b->reference,
            'guest_name' => $b->guest_name,
            'room_number' => $b->room?->number,
            'room_type' => $b->roomType?->name,
            'check_in' => $b->check_in->toDateString(),
            'check_out' => $b->check_out->toDateString(),
            'status' => $b->status,
            'adults' => $b->adults,
            'children' => $b->children,
        ];

        $arrivals = Booking::with($with)->whereDate('check_in', $todayStr)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
            ->orderBy('guest_name')->get()->map($pick);

        $departures = Booking::with($with)->whereDate('check_out', $todayStr)
            ->whereIn('status', [Booking::STATUS_CHECKED_IN, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_OUT])
            ->orderBy('guest_name')->get()->map($pick);

        $inHouse = Booking::where('status', Booking::STATUS_CHECKED_IN)->count();

        // Occupied nights over the next 30 days (bookings only, blocks excluded)
        $horizon = 30;
        $soldNext = RoomNight::whereNotNull('booking_id')
            ->whereBetween('date', [$todayStr, $today->addDays($horizon - 1)->toDateString()])->count();
        $soldToday = RoomNight::whereNotNull('booking_id')->whereDate('date', $todayStr)->count();

        $monthStart = $today->startOfMonth();
        $paid = Payment::where('payable_type', (new Booking)->getMorphClass())
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->where('paid_at', '>=', $monthStart->setTimezone('UTC'))
            ->get(['amount', 'refunded_amount']);
        $revenueCents = $paid->sum(fn ($p) => (int) round($p->amount * 100) - (int) round(($p->refunded_amount ?? 0) * 100));

        return response()->json([
            'date' => $todayStr,
            'currency' => $settings->currency(),
            'rooms_sellable' => $sellable,
            'occupancy_today' => $sellable ? round($soldToday / $sellable * 100, 1) : 0,
            'occupancy_next_30' => $sellable ? round($soldNext / ($sellable * $horizon) * 100, 1) : 0,
            'in_house' => $inHouse,
            'pending_holds' => Booking::where('status', Booking::STATUS_PENDING)->count(),
            'upcoming_confirmed' => Booking::where('status', Booking::STATUS_CONFIRMED)->whereDate('check_in', '>=', $todayStr)->count(),
            'revenue_month' => number_format(max(0, $revenueCents) / 100, 2, '.', ''),
            'unread_messages' => Contact::where('status', 'unread')->count(),
            'arrivals' => $arrivals,
            'departures' => $departures,
        ]);
    }
}
