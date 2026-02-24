<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Supported: "mock", "paymob"
    |
    */
    'default' => env('PAYMENT_GATEWAY', 'mock'),

    'gateways' => [

        'mock' => [
            'driver' => 'mock',
        ],

        'paymob' => [
            'driver' => 'paymob',
            'api_key' => env('PAYMOB_API_KEY'),
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
            'iframe_id' => env('PAYMOB_IFRAME_ID'),
            'merchant_id' => env('PAYMOB_MERCHANT_ID'),
            'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
            'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),
        ],

    ],

];

