<?php

namespace App\Http\Requests;

use App\Services\Payment\GatewayFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'guest_name' => ['required', 'string', 'max:191'],
            'guest_email' => ['required', 'email', 'max:191'],
            'guest_phone' => ['nullable', 'string', 'max:32'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', Rule::in(GatewayFactory::supported())],
            // Optional redirect flow (instead of the PayPal JS SDK popup)
            'return_url' => ['nullable', 'url', 'max:500'],
            'cancel_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
