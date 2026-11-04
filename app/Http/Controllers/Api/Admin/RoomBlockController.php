<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\RoomBlock;
use App\Models\RoomNight;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Close a room for sale (maintenance, owner stay...). Blocks occupy room_nights
 * exactly like bookings, so the same unique index protects both.
 */
class RoomBlockController extends Controller
{
    /** GET /admin/room-blocks?from=YYYY-MM-DD */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            RoomBlock::query()->with('room:id,number')
                ->when($request->from, fn ($q, $from) => $q->whereDate('end_date', '>', $from))
                ->orderBy('start_date')->get()
        );
    }

    /** POST /admin/room-blocks { room_id, start_date, end_date (exclusive), reason? } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $block = DB::transaction(function () use ($data, $request) {
                $block = RoomBlock::create($data + ['created_by' => $request->user()->id]);

                $rows = [];
                foreach (CarbonPeriod::create(CarbonImmutable::parse($data['start_date']), '1 day', CarbonImmutable::parse($data['end_date'])->subDay()) as $d) {
                    $rows[] = ['room_id' => $data['room_id'], 'date' => $d->toDateString(), 'room_block_id' => $block->id];
                }
                RoomNight::insert($rows);

                return $block;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BookingException('The room already has bookings or blocks on some of these dates.', 'dates_taken', 409);
        }

        return response()->json($block->load('room:id,number'), 201);
    }

    /** DELETE /admin/room-blocks/{roomBlock} — frees its nights (cascade). */
    public function destroy(RoomBlock $roomBlock): JsonResponse
    {
        $roomBlock->delete();

        return response()->json(['message' => 'Block removed.']);
    }
}
