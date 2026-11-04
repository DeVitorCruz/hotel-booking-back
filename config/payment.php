<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PayPal (Orders v2)
    |--------------------------------------------------------------------------
    | Create a REST app at https://developer.paypal.com → Apps & Credentials.
    | mode: sandbox | live
    | webhook_id: ID of the webhook you register for POST {APP_URL}/api/webhooks/paypal
    | Subscribe it to: CHECKOUT.ORDER.APPROVED, PAYMENT.CAPTURE.COMPLETED,
    | PAYMENT.CAPTURE.DENIED, PAYMENT.CAPTURE.REFUNDED
    */
    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
        'base_url' => env('PAYPAL_MODE', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com',
        'timeout' => (int) env('PAYPAL_TIMEOUT', 20),
    ],
];
