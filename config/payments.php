<?php

$legacyMode = env('STRIPE_MODE', env('PAYMENTS_DEFAULT_MODE', 'test'));
$legacyKey = env('STRIPE_KEY');
$legacySecret = env('STRIPE_SECRET');
$legacyWebhook = env('STRIPE_WEBHOOK_SECRET');
$legacyConnectWebhook = env('STRIPE_CONNECT_WEBHOOK_SECRET');
$legacyConnectClientId = env('STRIPE_CONNECT_CLIENT_ID');

$isPlaceholderCredential = static function (?string $value): bool {
    if (! filled($value)) {
        return false;
    }

    return (bool) preg_match('/REPLACE_ME|your_(test|live)_|changeme/i', (string) $value);
};

$normalizeCredential = static function (?string $value) use ($isPlaceholderCredential): ?string {
    if (! filled($value) || $isPlaceholderCredential($value)) {
        return null;
    }

    return (string) $value;
};

$testKey = $normalizeCredential(env('STRIPE_TEST_KEY', $legacyMode === 'test' ? $legacyKey : null));
$testSecret = $normalizeCredential(env('STRIPE_TEST_SECRET', $legacyMode === 'test' ? $legacySecret : null));
$testWebhook = env('STRIPE_TEST_WEBHOOK_SECRET', $legacyMode === 'test' ? $legacyWebhook : null);
$testConnectWebhook = env('STRIPE_CONNECT_TEST_WEBHOOK_SECRET', $legacyMode === 'test' ? $legacyConnectWebhook : null);
$testConnectClientId = env('STRIPE_CONNECT_TEST_CLIENT_ID', $legacyMode === 'test' ? $legacyConnectClientId : null);

$liveKey = $normalizeCredential(env('STRIPE_LIVE_KEY', $legacyMode === 'live' ? $legacyKey : null));
$liveSecret = $normalizeCredential(env('STRIPE_LIVE_SECRET', $legacyMode === 'live' ? $legacySecret : null));
$liveWebhook = env('STRIPE_LIVE_WEBHOOK_SECRET', $legacyMode === 'live' ? $legacyWebhook : null);
$liveConnectWebhook = env('STRIPE_CONNECT_LIVE_WEBHOOK_SECRET', $legacyMode === 'live' ? $legacyConnectWebhook : null);
$liveConnectClientId = env('STRIPE_CONNECT_LIVE_CLIENT_ID', $legacyMode === 'live' ? $legacyConnectClientId : null);

$liveHasDedicatedEnvKeys = filled($liveKey) && filled($liveSecret);

$allowLocalLiveKeyMirror = filter_var(
    env('STRIPE_LOCAL_MIRROR_TEST_KEYS_FOR_LIVE', env('APP_ENV') === 'local'),
    FILTER_VALIDATE_BOOL
);
$liveMirrorsTestKeys = $allowLocalLiveKeyMirror
    && in_array(env('APP_ENV'), ['local', 'testing'], true)
    && ! filled($liveKey)
    && ! filled($liveSecret)
    && filled($testKey)
    && filled($testSecret);

if ($liveMirrorsTestKeys) {
    $liveKey = $testKey;
    $liveSecret = $testSecret;
    $liveWebhook = $liveWebhook ?: $testWebhook;
    $liveConnectWebhook = $liveConnectWebhook ?: $testConnectWebhook;
    $liveConnectClientId = $liveConnectClientId ?: $testConnectClientId;
}

return [
    'default_provider' => env('PAYMENTS_DEFAULT_PROVIDER', 'stripe'),
    'default_mode' => env('PAYMENTS_DEFAULT_MODE', $legacyMode),

    'stripe' => [
        // Legacy single-mode keys (backward compatible; maps to active STRIPE_MODE).
        'mode' => $legacyMode,
        'key' => $legacyKey,
        'secret' => $legacySecret,
        'webhook_secret' => $legacyWebhook,
        'connect_webhook_secret' => $legacyConnectWebhook,
        'connect_client_id' => $legacyConnectClientId,
        'connect_refresh_url' => env('STRIPE_CONNECT_REFRESH_URL'),
        'connect_return_url' => env('STRIPE_CONNECT_RETURN_URL'),
        'allow_platform_sandbox_fallback' => env('STRIPE_ALLOW_PLATFORM_SANDBOX_FALLBACK', env('APP_ENV') !== 'production'),
        'allow_local_live_key_mirror' => $allowLocalLiveKeyMirror,
        'live_mirrors_test_keys' => $liveMirrorsTestKeys,
        'live_has_dedicated_env_keys' => $liveHasDedicatedEnvKeys,

        'modes' => [
            'test' => [
                'key' => $testKey,
                'secret' => $testSecret,
                'webhook_secret' => $testWebhook,
                'connect_webhook_secret' => $testConnectWebhook,
                'connect_client_id' => $testConnectClientId,
            ],
            'live' => [
                'key' => $liveKey,
                'secret' => $liveSecret,
                'webhook_secret' => $liveWebhook,
                'connect_webhook_secret' => $liveConnectWebhook,
                'connect_client_id' => $liveConnectClientId,
            ],
        ],
    ],
];
