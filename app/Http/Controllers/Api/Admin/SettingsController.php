<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Hotel\HotelSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /** GET /admin/settings */
    public function show(HotelSettings $settings): JsonResponse
    {
        return response()->json($settings->all());
    }

    /** PATCH /admin/settings — only the keys sent are changed. */
    public function update(Request $request, HotelSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'email' => ['sometimes', 'email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'timezone' => ['sometimes', 'timezone:all'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'check_in_time' => ['sometimes', 'date_format:H:i'],
            'check_out_time' => ['sometimes', 'date_format:H:i'],
            'tax_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'deposit_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'min_nights' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'max_nights' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'max_advance_days' => ['sometimes', 'integer', 'min:1', 'max:1095'],
            'hold_minutes' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'cancellation_hours' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'auto_refund_on_cancel' => ['sometimes', 'boolean'],
            'payment_methods' => ['sometimes', 'array', 'min:1'],
            'payment_methods.*' => [Rule::in(['paypal', 'pay_at_hotel'])],
        ]);

        foreach ($data as $key => $value) {
            $settings->set($key, $key === 'currency' ? strtoupper($value) : $value);
        }

        return response()->json($settings->all());
    }
}
