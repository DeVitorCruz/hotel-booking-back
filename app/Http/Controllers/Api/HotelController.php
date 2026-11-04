<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Services\Hotel\HotelSettings;
use Illuminate\Http\JsonResponse;

class HotelController extends Controller
{
    /**
     * GET /hotel — public hotel info + what the frontend needs to render checkout.
     */
    public function show(HotelSettings $settings): JsonResponse
    {
        return response()->json([
            ...$settings->public(),
            'paypal' => $settings->paymentMethodEnabled('paypal') ? [
                'client_id' => config('payment.paypal.client_id'),
                'mode' => config('payment.paypal.mode'),
                'currency' => $settings->currency(),
            ] : null,
        ]);
    }

    /** GET /amenities */
    public function amenities(): JsonResponse
    {
        return response()->json(Amenity::query()->orderBy('name')->get(['id', 'name', 'slug', 'icon']));
    }
}
