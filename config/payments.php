<?php

return [
    'default_provider' => env('PAYMENTS_DEFAULT_PROVIDER', 'stripe'),

    'stripe' => [
        'mode' => env('STRIPE_MODE', 'test'),
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
