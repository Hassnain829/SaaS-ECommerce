<?php

return [
    'default_environment' => env('CARRIER_DEFAULT_ENVIRONMENT', 'sandbox'),

    'fedex' => [
        'enabled' => env('FEDEX_ENABLED', false),
        'environment' => env('FEDEX_ENVIRONMENT', 'sandbox'),

        'oauth_path' => env('FEDEX_OAUTH_PATH', '/oauth/token'),

        'address_validation_path' => env('FEDEX_ADDRESS_VALIDATION_PATH', '/address/v1/addresses/resolve'),
        'service_availability_path' => env('FEDEX_SERVICE_AVAILABILITY_PATH', '/availability/v1/packageandserviceoptions'),
        'rate_quote_path' => env('FEDEX_RATE_QUOTE_PATH', '/rate/v1/rates/quotes'),

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

    'usps' => [
        'enabled' => env('USPS_ENABLED', false),
        'environment' => env('USPS_ENVIRONMENT', 'testing'),
        'base_url' => env('USPS_BASE_URL', 'https://apis-tem.usps.com'),
        'consumer_key' => env('USPS_CONSUMER_KEY'),
        'consumer_secret' => env('USPS_CONSUMER_SECRET'),
        'crid' => env('USPS_CRID'),
        'master_mid' => env('USPS_MASTER_MID'),
        'labeler_mid' => env('USPS_LABELER_MID'),
        'labels_enabled' => env('USPS_LABELS_ENABLED', false),
        'platform_label_purchase' => env('USPS_PLATFORM_LABEL_PURCHASE', false),
        'oauth_path' => env('USPS_OAUTH_PATH', '/oauth2/v3/token'),
        'address_validation_path' => env('USPS_ADDRESS_VALIDATION_PATH', '/addresses/v3/address'),
        'domestic_base_rates_path' => env('USPS_DOMESTIC_BASE_RATES_PATH', '/prices/v3/base-rates/search'),
        'default_mail_class' => env('USPS_DEFAULT_MAIL_CLASS', 'USPS_GROUND_ADVANTAGE'),
        'default_price_type' => env('USPS_DEFAULT_PRICE_TYPE', 'RETAIL'),
        'timeouts' => [
            'connect' => (int) env('USPS_HTTP_CONNECT_TIMEOUT', 10),
            'request' => (int) env('USPS_HTTP_REQUEST_TIMEOUT', 30),
        ],
    ],
];
