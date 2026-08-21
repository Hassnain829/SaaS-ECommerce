<?php

return [
    'default_environment' => env('CARRIER_DEFAULT_ENVIRONMENT', 'sandbox'),

    'fedex' => [
        'enabled' => env('FEDEX_ENABLED', false),
        'environment' => env('FEDEX_ENVIRONMENT', 'sandbox'),
        'default_connection_model' => env('FEDEX_DEFAULT_CONNECTION_MODEL', 'integrator_provider'),
        'developer_mode_enabled' => filter_var(env('FEDEX_DEVELOPER_MODE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'integrator_model_a_enabled' => filter_var(env('FEDEX_INTEGRATOR_MODEL_A_ENABLED', true), FILTER_VALIDATE_BOOL),
        'integrator_production_enabled' => filter_var(env('FEDEX_INTEGRATOR_PRODUCTION_ENABLED', false), FILTER_VALIDATE_BOOL),
        // ISO-2 countries allowed for live Model A onboarding (comma-separated).
        'live_allowed_countries' => env('FEDEX_LIVE_ALLOWED_COUNTRIES', 'US,CA'),
        'model_b_developer_fallback_enabled' => filter_var(env('FEDEX_MODEL_B_DEVELOPER_FALLBACK_ENABLED', false), FILTER_VALIDATE_BOOL),

        // Phase 6C-5 Steps 2–4 production operation flags (sandbox Model A ok; live still needs productionEnabled()).
        'ops_address_validation_enabled' => filter_var(env('FEDEX_OPS_ADDRESS_VALIDATION_ENABLED', true), FILTER_VALIDATE_BOOL),
        'ops_service_availability_enabled' => filter_var(env('FEDEX_OPS_SERVICE_AVAILABILITY_ENABLED', true), FILTER_VALIDATE_BOOL),
        'ops_negotiated_rates_enabled' => filter_var(env('FEDEX_OPS_NEGOTIATED_RATES_ENABLED', true), FILTER_VALIDATE_BOOL),
        // Checkout live rates stay off until explicitly enabled (Step 4 wires path; Step 10 hardens).
        'checkout_rates_enabled' => filter_var(env('FEDEX_CHECKOUT_RATES_ENABLED', false), FILTER_VALIDATE_BOOL),
        'ops_ship_labels_enabled' => filter_var(env('FEDEX_OPS_SHIP_LABELS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'ops_tracking_enabled' => filter_var(env('FEDEX_OPS_TRACKING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'label_storage_disk' => env('FEDEX_LABEL_STORAGE_DISK', 'local'),
        'ops_max_ship_date_days' => (int) env('FEDEX_OPS_MAX_SHIP_DATE_DAYS', 10),
        'http_timeout_seconds' => (int) env('FEDEX_HTTP_TIMEOUT_SECONDS', 20),
        'http_safe_retry_attempts' => (int) env('FEDEX_HTTP_SAFE_RETRY_ATTEMPTS', 2),
        'checkout_rate_cache_seconds' => (int) env('FEDEX_CHECKOUT_RATE_CACHE_SECONDS', 120),
        'ops_rate_quote_ttl_seconds' => (int) env('FEDEX_OPS_RATE_QUOTE_TTL_SECONDS', 1800),
        'checkout_max_package_lines' => (int) env('FEDEX_CHECKOUT_MAX_PACKAGE_LINES', 20),
        'checkout_default_package' => [
            'weight' => (float) env('FEDEX_CHECKOUT_DEFAULT_WEIGHT_LB', 1),
            'weight_unit' => 'LB',
            'length' => (float) env('FEDEX_CHECKOUT_DEFAULT_LENGTH_IN', 9),
            'width' => (float) env('FEDEX_CHECKOUT_DEFAULT_WIDTH_IN', 6),
            'height' => (float) env('FEDEX_CHECKOUT_DEFAULT_HEIGHT_IN', 2),
            'dimension_unit' => 'IN',
        ],
        // Trade Documents Upload API uses a separate document host (not apis-sandbox.fedex.com).
        'document_api_sandbox_base_url' => env('FEDEX_DOCUMENT_API_SANDBOX_BASE_URL', 'https://documentapitest.prod.fedex.com/sandbox'),
        'document_api_live_base_url' => env('FEDEX_DOCUMENT_API_LIVE_BASE_URL', 'https://documentapi.prod.fedex.com'),
        'trade_documents_upload_document_path' => env('FEDEX_TRADE_DOCUMENTS_UPLOAD_DOCUMENT_PATH', '/documents/v1/etds/upload'),
        'integrator_eula_version' => env('FEDEX_INTEGRATOR_EULA_VERSION', 'FedEx Form No. 2002382 v 4 June 2024 Rev'),
        'integrator_eula_path' => env('FEDEX_INTEGRATOR_EULA_PATH', 'resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf'),
        'integrator_eula_form_number' => env('FEDEX_INTEGRATOR_EULA_FORM_NUMBER', '2002382'),
        'integrator_eula_expected_pages' => (int) env('FEDEX_INTEGRATOR_EULA_EXPECTED_PAGES', 11),
        'integrator_eula_sha256' => env('FEDEX_INTEGRATOR_EULA_SHA256', '3eea76a66fbae1d798c2069934ec9c2750c75e8f47879697cec16c84c47e8ab8'),

        'oauth_path' => env('FEDEX_OAUTH_PATH', '/oauth/token'),

        'address_validation_path' => env('FEDEX_ADDRESS_VALIDATION_PATH', '/address/v1/addresses/resolve'),
        'service_availability_path' => env('FEDEX_SERVICE_AVAILABILITY_PATH', '/availability/v1/packageandserviceoptions'),
        'rate_quote_path' => env('FEDEX_RATE_QUOTE_PATH', '/rate/v1/rates/quotes'),
        'comprehensive_rate_quote_path' => env('FEDEX_COMPREHENSIVE_RATE_PATH', '/rate/v1/comprehensiverates/quotes'),

        // Deprecated — do not use: POST /irc/v2/customerkeys, /registration/v1/address/keysgeneration
        // Current Credential Registration API (FedEx Developer Portal): POST /registration/v2/address/keysgeneration
        'account_registration_path' => env('FEDEX_ACCOUNT_REGISTRATION_PATH'),

        // Diagnostic-only in local/testing: omit | boolean | string (default omit — FedEx Credential Registration rejects residential)
        'account_registration_residential_mode' => env('FEDEX_ACCOUNT_REGISTRATION_RESIDENTIAL_MODE', 'omit'),

        // Local/testing only: allow sandbox platform OAuth fallback when Credential Registration is blocked
        'sandbox_allow_platform_fallback' => env('FEDEX_SANDBOX_ALLOW_PLATFORM_FALLBACK', false),

        // MFA endpoints — configure from FedEx Developer Portal when available.
        // Leave null until official paths are confirmed for your integrator project.
        'mfa_pin_generation_path' => env('FEDEX_MFA_PIN_GENERATION_PATH'),
        'mfa_pin_validation_path' => env('FEDEX_MFA_PIN_VALIDATION_PATH'),
        'mfa_invoice_validation_path' => env('FEDEX_MFA_INVOICE_VALIDATION_PATH'),

        'ship_create_path' => env('FEDEX_SHIP_CREATE_PATH', '/ship/v1/shipments'),
        'ship_cancel_path' => env('FEDEX_SHIP_CANCEL_PATH', '/ship/v1/shipments/cancel'),
        'basic_integrated_visibility_path' => env('FEDEX_BASIC_INTEGRATED_VISIBILITY_PATH'),

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
        'merchant_oauth_consumer_key' => env('USPS_MERCHANT_OAUTH_CONSUMER_KEY'),
        'merchant_oauth_consumer_secret' => env('USPS_MERCHANT_OAUTH_CONSUMER_SECRET'),
        'crid' => env('USPS_CRID'),
        'master_mid' => env('USPS_MASTER_MID'),
        'labeler_mid' => env('USPS_LABELER_MID'),
        'labels_enabled' => env('USPS_LABELS_ENABLED', false),
        'platform_label_purchase' => env('USPS_PLATFORM_LABEL_PURCHASE', false),
        'merchant_connection_enabled' => filter_var(env('USPS_MERCHANT_CONNECTION_ENABLED', false), FILTER_VALIDATE_BOOL),
        // Merchant Delivery UI / navigation. Keep false until USPS Label Provider approval is ready.
        // Local/testing can still open routes when merchant_connection_enabled is true (see USPSConfig).
        'merchant_visible' => filter_var(env('USPS_MERCHANT_VISIBLE', false), FILTER_VALIDATE_BOOL),
        'platform_epa' => env('USPS_PLATFORM_EPA'),
        'platform_crid' => env('USPS_CRID'),
        'platform_master_mid' => env('USPS_MASTER_MID'),
        'platform_label_provider_name' => env('USPS_PLATFORM_LABEL_PROVIDER_NAME', 'BmyBrand'),
        'business_portal_url' => env('USPS_BUSINESS_PORTAL_URL', 'https://gateway.usps.com/eAdmin/action/home'),
        'merchant_cop_authorization_url' => env('USPS_MERCHANT_COP_AUTHORIZATION_URL'),
        'merchant_oauth_icd_confirmed' => filter_var(env('USPS_MERCHANT_OAUTH_ICD_CONFIRMED', false), FILTER_VALIDATE_BOOL),
        'oauth_callback_path' => env('USPS_OAUTH_CALLBACK_PATH', '/settings/shipping/carriers/usps/oauth/callback'),
        'oauth_authorize_path' => env('USPS_OAUTH_AUTHORIZE_PATH', '/oauth2/v3/authorize'),
        'oauth_redirect_url' => env('USPS_OAUTH_REDIRECT_URL'),
        'merchant_oauth_enabled' => filter_var(env('USPS_MERCHANT_OAUTH_ENABLED', false), FILTER_VALIDATE_BOOL),
        'merchant_oauth_allow_http_redirect' => filter_var(env('USPS_MERCHANT_OAUTH_ALLOW_HTTP_REDIRECT', false), FILTER_VALIDATE_BOOL),
        'merchant_oauth_scope' => env('USPS_MERCHANT_OAUTH_SCOPE'),
        'userinfo_path' => env('USPS_USERINFO_PATH', '/oauth2-oidc/v3/userinfo'),
        'ship_enrollment_path' => env('USPS_SHIP_ENROLLMENT_PATH', '/ship-enrollment/v3/enrollment'),
        'payment_authorization_path' => env('USPS_PAYMENT_AUTHORIZATION_PATH', '/payments/v3/payment-authorization'),
        'merchant_ship_suite_verify_enabled' => filter_var(env('USPS_MERCHANT_SHIP_SUITE_VERIFY_ENABLED', false), FILTER_VALIDATE_BOOL),
        'payment_platform_role_name' => env('USPS_PAYMENT_PLATFORM_ROLE_NAME', 'PLATFORM'),
        'payment_label_provider_role_name' => env('USPS_PAYMENT_LABEL_PROVIDER_ROLE_NAME', 'LABEL_PROVIDER'),
        'payment_include_label_provider_role' => filter_var(env('USPS_PAYMENT_INCLUDE_LABEL_PROVIDER_ROLE', false), FILTER_VALIDATE_BOOL),
        'payment_account_type' => env('USPS_PAYMENT_ACCOUNT_TYPE', 'EPS'),
        'platform_master_mid' => env('USPS_MASTER_MID'),
        'oauth_path' => env('USPS_OAUTH_PATH', '/oauth2/v3/token'),
        'oauth_revoke_path' => env('USPS_OAUTH_REVOKE_PATH', '/oauth2/v3/revoke'),
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
