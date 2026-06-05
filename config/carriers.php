<?php

return [
    'default_environment' => env('CARRIER_DEFAULT_ENVIRONMENT', 'sandbox'),

    'fedex' => [
        'enabled' => env('FEDEX_ENABLED', false),
        'environment' => env('FEDEX_ENVIRONMENT', 'sandbox'),

        'oauth_path' => env('FEDEX_OAUTH_PATH', '/oauth/token'),

        // Deprecated — do not use: POST /irc/v2/customerkeys, /registration/v1/address/keysgeneration
        // Current Credential Registration API (FedEx Developer Portal): POST /registration/v2/address/keysgeneration
        'account_registration_path' => env('FEDEX_ACCOUNT_REGISTRATION_PATH'),

        // Diagnostic-only in local/testing: omit | boolean | string (default omit — FedEx Credential Registration rejects residential)
        'account_registration_residential_mode' => env('FEDEX_ACCOUNT_REGISTRATION_RESIDENTIAL_MODE', 'omit'),

        // Local/testing only: allow sandbox platform OAuth fallback when Credential Registration is blocked
        'sandbox_allow_platform_fallback' => env('FEDEX_SANDBOX_ALLOW_PLATFORM_FALLBACK', false),

        'sandbox' => [
            'base_url' => env('FEDEX_SANDBOX_BASE_URL', 'https://apis-sandbox.fedex.com'),
            'client_id' => env('FEDEX_SANDBOX_CLIENT_ID'),
            'client_secret' => env('FEDEX_SANDBOX_CLIENT_SECRET'),
            'account_registration_path' => env(
                'FEDEX_SANDBOX_ACCOUNT_REGISTRATION_PATH',
                '/registration/v2/address/keysgeneration'
            ),
        ],

        'live' => [
            'base_url' => env('FEDEX_LIVE_BASE_URL', 'https://apis.fedex.com'),
            'client_id' => env('FEDEX_LIVE_CLIENT_ID'),
            'client_secret' => env('FEDEX_LIVE_CLIENT_SECRET'),
            'account_registration_path' => env(
                'FEDEX_LIVE_ACCOUNT_REGISTRATION_PATH',
                '/registration/v2/address/keysgeneration'
            ),
        ],
    ],
];
