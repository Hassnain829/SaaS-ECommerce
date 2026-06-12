<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;

final class FedExConfig
{
    public const DEPRECATED_REGISTRATION_PATHS = [
        '/irc/v2/customerkeys',
        '/registration/v1/address/keysgeneration',
    ];

    public const CURRENT_REGISTRATION_PATH = '/registration/v2/address/keysgeneration';

    public function environment(?string $environment = null): string
    {
        $environment = strtolower((string) ($environment ?? config('carriers.fedex.environment', 'sandbox')));

        return in_array($environment, ['sandbox', 'live'], true) ? $environment : 'sandbox';
    }

    public function baseUrl(?string $environment = null): string
    {
        $environment = $this->environment($environment);

        return rtrim((string) config("carriers.fedex.{$environment}.base_url"), '/');
    }

    public function clientId(?string $environment = null): ?string
    {
        $environment = $this->environment($environment);
        $clientId = (string) config("carriers.fedex.{$environment}.client_id", '');

        return $clientId !== '' ? $clientId : null;
    }

    public function clientSecret(?string $environment = null): ?string
    {
        $environment = $this->environment($environment);
        $secret = (string) config("carriers.fedex.{$environment}.client_secret", '');

        return $secret !== '' ? $secret : null;
    }

    public function oauthPath(): string
    {
        return (string) config('carriers.fedex.oauth_path', '/oauth/token');
    }

    public function accountRegistrationPath(?string $environment = null): string
    {
        $environment = $this->environment($environment);
        $path = (string) config("carriers.fedex.{$environment}.account_registration_path", '');

        if ($path === '') {
            $path = (string) config('carriers.fedex.account_registration_path', self::CURRENT_REGISTRATION_PATH);
        }

        if ($path === '') {
            $path = self::CURRENT_REGISTRATION_PATH;
        }

        return $path;
    }

    public function isDeprecatedRegistrationPath(string $path): bool
    {
        $normalized = strtolower($path);

        foreach (self::DEPRECATED_REGISTRATION_PATHS as $deprecated) {
            if (str_contains($normalized, strtolower($deprecated))) {
                return true;
            }
        }

        return false;
    }

    public function isEnabled(): bool
    {
        return (bool) config('carriers.fedex.enabled', false);
    }

    public function isConfigured(?string $environment = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return filled($this->clientId($environment)) && filled($this->clientSecret($environment));
    }

    public function allowsEnvironment(string $environment): bool
    {
        $environment = strtolower($environment);

        if ($environment === CarrierAccount::ENVIRONMENT_LIVE) {
            return false;
        }

        return $environment === CarrierAccount::ENVIRONMENT_SANDBOX;
    }

    public function allowsMerchantCredentialsEnvironment(string $environment): bool
    {
        $environment = strtolower($environment);

        return in_array($environment, [
            CarrierAccount::ENVIRONMENT_SANDBOX,
            CarrierAccount::ENVIRONMENT_LIVE,
        ], true);
    }

    /**
     * Credential Registration residential field mode. Production always omits the field.
     * Local/testing may set FEDEX_ACCOUNT_REGISTRATION_RESIDENTIAL_MODE for diagnostics.
     */
    public function accountRegistrationResidentialMode(): string
    {
        if (! app()->environment(['local', 'testing'])) {
            return 'omit';
        }

        $mode = strtolower((string) config('carriers.fedex.account_registration_residential_mode', 'omit'));

        return in_array($mode, ['omit', 'boolean', 'string'], true) ? $mode : 'omit';
    }

    /**
     * Sandbox platform OAuth fallback is never available in production.
     */
    public function allowsSandboxPlatformFallback(): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return false;
        }

        return filter_var(
            config('carriers.fedex.sandbox_allow_platform_fallback', false),
            FILTER_VALIDATE_BOOL,
        );
    }
}
