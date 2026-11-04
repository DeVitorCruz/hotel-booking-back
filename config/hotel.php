<?php

/*
|--------------------------------------------------------------------------
| Hotel defaults
|--------------------------------------------------------------------------
| Per-client values come from .env at install time. Staff can override any
| of these keys later from the back office (stored in the `settings` table,
| read through App\Services\Hotel\HotelSettings).
*/
return [
    'name' => env('HOTEL_NAME', 'Demo Hotel'),
    'email' => env('HOTEL_EMAIL', 'reservations@example.com'),
    'phone' => env('HOTEL_PHONE'),
    'address' => env('HOTEL_ADDRESS'),

    // IANA zone used to decide what "today" is for check-in dates
    'timezone' => env('HOTEL_TIMEZONE', 'UTC'),

    // ISO 4217, must be a currency your PayPal account accepts
    'currency' => env('HOTEL_CURRENCY', 'USD'),

    'check_in_time' => env('HOTEL_CHECK_IN_TIME', '14:00'),
    'check_out_time' => env('HOTEL_CHECK_OUT_TIME', '11:00'),

    // Pricing
    'tax_percent' => (float) env('HOTEL_TAX_PERCENT', 0),
    'deposit_percent' => (float) env('HOTEL_DEPOSIT_PERCENT', 100), // % charged online; 100 = full prepayment

    // Booking rules
    'min_nights' => (int) env('HOTEL_MIN_NIGHTS', 1),
    'max_nights' => (int) env('HOTEL_MAX_NIGHTS', 30),
    'max_advance_days' => (int) env('HOTEL_MAX_ADVANCE_DAYS', 365),
    'hold_minutes' => (int) env('HOTEL_HOLD_MINUTES', 15),          // unpaid PayPal holds are released after this

    // Cancellation: free (with automatic refund) until this many hours before check-in
    'cancellation_hours' => (int) env('HOTEL_CANCELLATION_HOURS', 48),
    'auto_refund_on_cancel' => (bool) env('HOTEL_AUTO_REFUND_ON_CANCEL', true),

    // Enabled payment options shown at checkout: paypal, pay_at_hotel
    'payment_methods' => array_values(array_filter(array_map('trim', explode(',', env('HOTEL_PAYMENT_METHODS', 'paypal,pay_at_hotel'))))),

    // Where the guest "manage booking" link points (Angular app)
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:4200'),
];
