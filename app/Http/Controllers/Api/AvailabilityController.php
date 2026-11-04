<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Resources\RoomTypeResource;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    /**
     * GET /availability?check_in=2026-12-20&check_out=2026-12-23&adults=2&children=0
     * Room types that fit the party, with how many rooms are free and the price.
     */
    public function __invoke(AvailabilityRequest $request, AvailabilityService $availability): JsonResponse
    {
        $results = $availability->search(
            CarbonImmutable::parse($request->check_in),
            CarbonImmutable::parse($request->check_out),
            (int) $request->adults,
            (int) ($request->children ?? 0),
        );

        return response()->json([
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'adults' => (int) $request->adults,
            'children' => (int) ($request->children ?? 0),
            'results' => $results->map(fn ($r) => [
                'room_type' => new RoomTypeResource($r['room_type']),
                'available' => $r['available'],
                'quote' => $r['quote']->toArray(),
            ]),
        ]);
    }
}
