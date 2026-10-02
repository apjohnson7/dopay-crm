<?php

/*
| Online payment providers. Keys are entered per country account under Payments & tax → Online payments and stored
| encrypted in payment_gateways; nothing secret lives in this file. Each provider starts in test mode (its sandbox)
| and going live needs a second person's authorization.
|
| 'driver' => the class that talks to the provider. With no keys saved, test mode uses the built-in sandbox so the
| whole flow (request → customer approves → notification → payment and receipt) can be tried end to end.
*/

return [
    'providers' => [
        'mtn_momo' => [
            'label' => 'MTN MoMo',
            'mobile_money' => true,
            'driver' => \App\Services\Payments\Drivers\MtnMomoDriver::class,
            'countries' => ['UG', 'CM', 'CI'],
            'settlement_role' => 'momo',
            'base_url' => ['test' => 'https://sandbox.momodeveloper.mtn.com', 'live' => env('MTN_MOMO_LIVE_URL', 'https://proxy.momoapi.mtn.com')],
            'target_environment' => ['test' => 'sandbox', 'live' => env('MTN_MOMO_TARGET_ENV', 'mtnuganda')],
        ],
        'airtel_money' => [
            'label' => 'Airtel Money',
            'mobile_money' => true,
            'driver' => \App\Services\Payments\Drivers\AirtelMoneyDriver::class,
            'countries' => ['UG', 'NG'],
            'settlement_role' => 'momo',
            'base_url' => ['test' => 'https://openapiuat.airtel.africa', 'live' => 'https://openapi.airtel.africa'],
        ],
        'flutterwave' => [
            'label' => 'Flutterwave',
            'mobile_money' => false,
            'driver' => \App\Services\Payments\Drivers\FlutterwaveDriver::class,
            'countries' => ['UG', 'NG', 'CM', 'CI'],
            'settlement_role' => 'clearing',
            'base_url' => ['test' => 'https://api.flutterwave.com', 'live' => 'https://api.flutterwave.com'],
        ],
        'paystack' => [
            'label' => 'Paystack',
            'mobile_money' => false,
            'driver' => \App\Services\Payments\Drivers\PaystackDriver::class,
            'countries' => ['NG'],
            'settlement_role' => 'clearing',
            'base_url' => ['test' => 'https://api.paystack.co', 'live' => 'https://api.paystack.co'],
        ],
    ],

    // A payment request the customer never completes is marked expired after this many minutes.
    'intent_expiry_minutes' => 30,

    // Timeout for calls to providers, in seconds.
    'http_timeout' => 20,
];
