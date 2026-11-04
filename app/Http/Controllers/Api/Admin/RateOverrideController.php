<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RateOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Seasonal / special prices per room type. end_date is inclusive; when
 * ranges overlap, the highest priority wins. Existing bookings keep the
 * nightly rates they were sold at.
 */
class RateOverrideController extends Controller
{
    /** GET /admin/rates?room_type_id=&from= */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            RateOverride::with('roomType:id,name')
                ->when($request->room_type_id, fn ($q, $id) => $q->where('room_type_id', $id))
                ->when($request->from, fn ($q, $from) => $q->whereDate('end_date', '>=', $from))
                ->orderBy('start_date')->get()
                ->map(fn ($r) => $this->present($r))
        );
    }

    /** POST /admin/rates */
    public function store(Request $request): JsonResponse
    {
        $rate = RateOverride::create($this->validated($request));

        return response()->json($this->present($rate->load('roomType:id,name')), 201);
    }

    /** PATCH /admin/rates/{rate} */
    public function update(Request $request, RateOverride $rate): JsonResponse
    {
        $rate->update($this->validated($request, $rate));

        return response()->json($this->present($rate->load('roomType:id,name')));
    }

    /** DELETE /admin/rates/{rate} */
    public function destroy(RateOverride $rate): JsonResponse
    {
        $rate->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?RateOverride $existing = null): array
    {
        $req = $existing ? 'sometimes' : 'required';
        $start = $request->input('start_date', $existing?->start_date?->toDateString());

        return $request->validate([
            'room_type_id' => [$req, 'integer', 'exists:room_types,id'],
            'label' => ['sometimes', 'nullable', 'string', 'max:191'],
            'start_date' => [$req, 'date_format:Y-m-d'],
            'end_date' => [$req, 'date_format:Y-m-d', 'after_or_equal:' . ($start ?: 'start_date')],
            'price' => [$req, 'numeric', 'min:0', 'max:99999999'],
            'min_nights' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:60'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);
    }

    private function present(RateOverride $r): array
    {
        return [
            'id' => $r->id,
            'room_type_id' => $r->room_type_id,
            'room_type' => $r->roomType?->name,
            'label' => $r->label,
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'price' => $r->price,
            'min_nights' => $r->min_nights,
            'priority' => $r->priority,
        ];
    }
}
