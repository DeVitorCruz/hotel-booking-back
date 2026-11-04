<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomNight;
use App\Services\Hotel\HotelSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Physical rooms. Only `active` rooms are sold; `maintenance` and `inactive`
 * rooms stay in the calendar but are skipped by the availability search.
 */
class RoomAdminController extends Controller
{
    public const STATUSES = ['active', 'maintenance', 'inactive'];

    /** GET /admin/rooms?room_type_id= */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Room::with('roomType:id,name')
                ->when($request->room_type_id, fn ($q, $id) => $q->where('room_type_id', $id))
                ->get()
                ->sort(fn ($a, $b) => strnatcmp($a->number, $b->number))
                ->values()
                ->map(fn (Room $r) => $this->present($r))
        );
    }

    /** POST /admin/rooms */
    public function store(Request $request): JsonResponse
    {
        $room = Room::create($this->validated($request));

        return response()->json($this->present($room->refresh()->load('roomType:id,name')), 201);
    }

    /** PATCH /admin/rooms/{room} */
    public function update(Request $request, Room $room, HotelSettings $settings): JsonResponse
    {
        $data = $this->validated($request, $room);

        // Moving a room to another type would silently change upcoming guests' room category.
        if (isset($data['room_type_id']) && (int) $data['room_type_id'] !== $room->room_type_id && $this->hasUpcoming($room, $settings)) {
            throw new BookingException('This room has upcoming bookings; it cannot change room type.', 'has_bookings', 409);
        }

        $room->update($data);

        return response()->json($this->present($room->load('roomType:id,name')));
    }

    /** DELETE /admin/rooms/{room} — only rooms that were never booked. */
    public function destroy(Room $room): JsonResponse
    {
        if (Booking::where('room_id', $room->id)->exists()) {
            throw new BookingException('This room has booking history. Set it to inactive instead.', 'has_bookings', 409);
        }

        $room->delete();

        return response()->json(null, 204);
    }

    private function hasUpcoming(Room $room, HotelSettings $settings): bool
    {
        return RoomNight::where('room_id', $room->id)->whereNotNull('booking_id')
            ->whereDate('date', '>=', $settings->today()->toDateString())->exists();
    }

    private function validated(Request $request, ?Room $existing = null): array
    {
        $req = $existing ? 'sometimes' : 'required';

        return $request->validate([
            'room_type_id' => [$req, 'integer', 'exists:room_types,id'],
            'number' => [$req, 'string', 'max:50', Rule::unique('rooms', 'number')->ignore($existing?->id)],
            'floor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
    }

    private function present(Room $r): array
    {
        return [
            'id' => $r->id,
            'number' => $r->number,
            'floor' => $r->floor,
            'status' => $r->status,
            'notes' => $r->notes,
            'room_type_id' => $r->room_type_id,
            'room_type' => $r->roomType?->name,
        ];
    }
}
