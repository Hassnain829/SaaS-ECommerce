<?php

return [
    'default_provider' => env('PAYMENTS_DEFAULT_PROVIDER', 'stripe'),

    'stripe' => [
        'mode' => env('STRIPE_MODE', 'test'),
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
        'connect_client_id' => env('STRIPE_CONNECT_CLIENT_ID'),
        'connect_refresh_url' => env('STRIPE_CONNECT_REFRESH_URL'),
        'connect_return_url' => env('STRIPE_CONNECT_RETURN_URL'),
        'allow_platform_sandbox_fallback' => env('STRIPE_ALLOW_PLATFORM_SANDBOX_FALLBACK', env('APP_ENV') !== 'production'),
    ],
];
