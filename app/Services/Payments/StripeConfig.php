<?php

namespace App\Services\Payments;

final class StripeConfig
{
    public const LIVE_CONFIG_REAL = 'real';

    public const LIVE_CONFIG_LOCAL_MIRROR = 'local_mirror';

    public const LIVE_CONFIG_MISSING = 'missing';

    public function defaultMode(): string
    {
        $mode = strtolower((string) config('payments.default_mode', 'test'));

        return in_array($mode, ['test', 'live'], true) ? $mode : 'test';
    }

    public function stripePublicKey(string $mode): ?string
    {
        $mode = strtolower($mode);

        if ($mode === 'live' && $this->liveKeysMirroredFromTest()) {
            return $this->stripePublicKey('test');
        }

        $key = (string) ($this->modeConfig($mode)['key'] ?? '');

        return $key !== '' ? $key : null;
    }

    public function stripeSecretKey(string $mode): ?string
    {
        $mode = strtolower($mode);

        if ($mode === 'live' && $this->liveKeysMirroredFromTest()) {
            return $this->stripeSecretKey('test');
        }

        $secret = (string) ($this->modeConfig($mode)['secret'] ?? '');

        return $secret !== '' ? $secret : null;
    }

    public function stripeWebhookSecret(string $mode): ?string
    {
        $secret = (string) ($this->modeConfig($mode)['webhook_secret'] ?? '');

        return $secret !== '' ? $secret : null;
    }

    public function stripeConnectWebhookSecret(string $mode): ?string
    {
        $secret = (string) ($this->modeConfig($mode)['connect_webhook_secret'] ?? '');

        return $secret !== '' ? $secret : null;
    }

    public function stripeConnectClientId(string $mode): ?string
    {
        $clientId = (string) ($this->modeConfig($mode)['connect_client_id'] ?? '');

        return $clientId !== '' ? $clientId : null;
    }

    public function isModeConfigured(string $mode): bool
    {
        return filled($this->stripePublicKey($mode)) && filled($this->stripeSecretKey($mode));
    }

    public function isConnectModeConfigured(string $mode): bool
    {
        return $this->isModeConfigured($mode);
    }

    public function liveKeysMirroredFromTest(): bool
    {
        if ($this->hasDedicatedLiveKeys()) {
            return false;
        }

        return (bool) config('payments.stripe.live_mirrors_test_keys', false);
    }

    public function hasDedicatedLiveKeys(): bool
    {
        return (bool) config('payments.stripe.live_has_dedicated_env_keys', false);
    }

    public function liveConfigSource(): string
    {
        if ($this->hasDedicatedLiveKeys()) {
            return self::LIVE_CONFIG_REAL;
        }

        if ($this->liveKeysMirroredFromTest()) {
            return self::LIVE_CONFIG_LOCAL_MIRROR;
        }

        return self::LIVE_CONFIG_MISSING;
    }

    public function liveConfigSourceLabel(): string
    {
        return match ($this->liveConfigSource()) {
            self::LIVE_CONFIG_REAL => 'Real live configured',
            self::LIVE_CONFIG_LOCAL_MIRROR => 'Local simulation using test keys',
            default => 'Missing',
        };
    }

    public function connectRefreshUrl(string $mode): string
    {
        $configured = trim((string) config('payments.stripe.connect_refresh_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        return route('settings.payments.stripe.connect.return', ['mode' => $mode], true);
    }

    public function connectReturnUrl(string $mode): string
    {
        $configured = trim((string) config('payments.stripe.connect_return_url', ''));

        if ($configured !== '') {
            return $configured;
        }

        return route('settings.payments.stripe.connect.return', ['mode' => $mode], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function modeConfig(string $mode): array
    {
        $mode = strtolower($mode);
        $modes = config('payments.stripe.modes', []);

        if (is_array($modes[$mode] ?? null)) {
            return $modes[$mode];
        }

        if ($mode === $this->legacyMode()) {
            return [
                'key' => config('payments.stripe.key'),
                'secret' => config('payments.stripe.secret'),
                'webhook_secret' => config('payments.stripe.webhook_secret'),
                'connect_webhook_secret' => config('payments.stripe.connect_webhook_secret'),
                'connect_client_id' => config('payments.stripe.connect_client_id'),
            ];
        }

        return [];
    }

    private function legacyMode(): string
    {
        return strtolower((string) config('payments.stripe.mode', 'test'));
    }
}
