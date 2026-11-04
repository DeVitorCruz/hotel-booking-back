<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\Hotel\HotelSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rooms × dates grid for the front desk ("tape chart").
 */
class CalendarController extends Controller
{
    /** GET /admin/calendar?from=YYYY-MM-DD&days=14 */
    public function __invoke(Request $request, HotelSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:62'],
        ]);

        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $settings->today();
        $days = (int) ($data['days'] ?? 14);
        $to = $from->addDays($days); // exclusive

        $rooms = Room::with('roomType:id,name,sort_order')->get()
            ->sortBy([fn ($a, $b) => ($a->roomType->sort_order ?? 0) <=> ($b->roomType->sort_order ?? 0), fn ($a, $b) => strnatcmp($a->number, $b->number)])
            ->values()
            ->map(fn (Room $r) => [
                'id' => $r->id, 'number' => $r->number, 'status' => $r->status,
                'room_type_id' => $r->room_type_id, 'room_type' => $r->roomType?->name,
            ]);

        $bookings = Booking::query()
            ->whereIn('status', [...Booking::ACTIVE_STATUSES, Booking::STATUS_CHECKED_OUT])
            ->whereDate('check_in', '<', $to->toDateString())
            ->whereDate('check_out', '>', $from->toDateString())
            ->get()->map(fn (Booking $b) => [
                'id' => $b->id, 'reference' => $b->reference, 'room_id' => $b->room_id,
                'guest_name' => $b->guest_name, 'status' => $b->status,
                'check_in' => $b->check_in->toDateString(), 'check_out' => $b->check_out->toDateString(),
            ]);

        $blocks = RoomBlock::query()
            ->whereDate('start_date', '<', $to->toDateString())
            ->whereDate('end_date', '>', $from->toDateString())
            ->get()->map(fn (RoomBlock $b) => [
                'id' => $b->id, 'room_id' => $b->room_id, 'reason' => $b->reason,
                'start_date' => $b->start_date instanceof \DateTimeInterface ? $b->start_date->format('Y-m-d') : (string) $b->start_date,
                'end_date' => $b->end_date instanceof \DateTimeInterface ? $b->end_date->format('Y-m-d') : (string) $b->end_date,
            ]);

        return response()->json([
            'from' => $from->toDateString(),
            'days' => $days,
            'rooms' => $rooms,
            'bookings' => $bookings,
            'blocks' => $blocks,
        ]);
    }
}
