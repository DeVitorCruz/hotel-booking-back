<?php
return [

    /*
    |--------------------------------------------------------------------------
    | Default Gateway
    |--------------------------------------------------------------------------
    | Used when no gateway is specified at checkout.
    | Options: mercadopago | pagseguro
    */
    'default' => env('PAYMENT_GATEWAY', 'mercadopago'),

    /*
    |--------------------------------------------------------------------------
    | Mercado Pago
    |--------------------------------------------------------------------------
    */
    'mercadopago' => [
        'base_url' => env('MP_BASE_URL', 'https://api.mercadopago.com'),
        'access_token' => env('MP_ACCESS_TOKEN', ''),
        'public_key' => env('MP_PUBLIC_KEY', ''),
        'webhook_secret' => env('MP_WEBHOOK_SECRET', ''),
        'sandbox' => env('MP_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | PagSeguro
    |--------------------------------------------------------------------------
    */
    'pagseguro' => [
        'base_url' => env('PS_BASE_URL', 'https://api.pagseguro.com'),
        'token' => env('PS_TOKEN', ''),
        'webhook_secret' => env('PS_WEBHOOK_SECRET', ''),
        'sandbox' => env('PS_SANDBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | PIX expiration (minutes)
    | Boleto expiration (days)
    |--------------------------------------------------------------------------
    */
    'pix_expiration_minutes' => env('PIX_EXPIRATION_MINUTES', 30),
    'boleto_expiration_days' => env('BOLETO_EXPIRATION_DAYS', 3),
];