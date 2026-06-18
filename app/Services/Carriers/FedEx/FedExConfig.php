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
        return $this->parentClientId($environment);
    }

    public function clientSecret(?string $environment = null): ?string
    {
        return $this->parentClientSecret($environment);
    }

    public function parentClientId(?string $environment = null): ?string
    {
        $environment = $this->environment($environment);
        $clientId = (string) config("carriers.fedex.{$environment}.client_id", '');

        return $clientId !== '' ? $clientId : null;
    }

    public function parentClientSecret(?string $environment = null): ?string
    {
        $environment = $this->environment($environment);
        $secret = (string) config("carriers.fedex.{$environment}.client_secret", '');

        return $secret !== '' ? $secret : null;
    }

    public function registrationPath(?string $environment = null): string
    {
        return $this->accountRegistrationPath($environment);
    }

    public function defaultConnectionModel(): string
    {
        $model = strtolower((string) config('carriers.fedex.default_connection_model', 'integrator_provider'));

        return in_array($model, ['integrator_provider', 'merchant_developer'], true)
            ? $model
            : 'integrator_provider';
    }

    public function modelAEnabled(): bool
    {
        return $this->isEnabled()
            && filter_var(config('carriers.fedex.integrator_model_a_enabled', true), FILTER_VALIDATE_BOOL);
    }

    public function modelBDeveloperFallbackEnabled(): bool
    {
        return filter_var(config('carriers.fedex.model_b_developer_fallback_enabled', false), FILTER_VALIDATE_BOOL)
            || filter_var(config('carriers.fedex.developer_mode_enabled', false), FILTER_VALIDATE_BOOL);
    }

    public function validationModeEnabled(): bool
    {
        return filter_var(config('carriers.fedex.validation_mode_enabled', false), FILTER_VALIDATE_BOOL)
            && app()->environment(['local', 'testing']);
    }

    public function productionEnabled(): bool
    {
        if (! filter_var(config('carriers.fedex.integrator_production_enabled', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return $this->isConfigured(CarrierAccount::ENVIRONMENT_LIVE);
    }

    public function allowsIntegratorEnvironment(string $environment): bool
    {
        $environment = strtolower($environment);

        if ($environment === CarrierAccount::ENVIRONMENT_LIVE) {
            return $this->productionEnabled();
        }

        return $environment === CarrierAccount::ENVIRONMENT_SANDBOX && $this->modelAEnabled();
    }

    public function eulaVersion(): string
    {
        return (string) config('carriers.fedex.integrator_eula_version', '1.0');
    }

    public function eulaPath(): string
    {
        $path = (string) config('carriers.fedex.integrator_eula_path', 'resources/legal/fedex/end_user_license_agreement.html');

        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path)
            ? $path
            : base_path($path);
    }

    public function mfaPinGenerationPath(): ?string
    {
        $path = (string) config('carriers.fedex.mfa_pin_generation_path', '');

        return $path !== '' ? $path : null;
    }

    public function mfaPinValidationPath(): ?string
    {
        $path = (string) config('carriers.fedex.mfa_pin_validation_path', '');

        return $path !== '' ? $path : null;
    }

    public function mfaInvoiceValidationPath(): ?string
    {
        $path = (string) config('carriers.fedex.mfa_invoice_validation_path', '');

        return $path !== '' ? $path : null;
    }

    /**
     * @return list<string>
     */
    public function testCaseBaselinePaths(): array
    {
        $paths = config('carriers.fedex.test_case_baseline_paths', []);

        return is_array($paths) ? array_values(array_filter($paths, 'is_string')) : [];
    }

    public function oauthPath(): string
    {
        return (string) config('carriers.fedex.oauth_path', '/oauth/token');
    }

    public function addressValidationPath(): string
    {
        return (string) config('carriers.fedex.address_validation_path', '/address/v1/addresses/resolve');
    }

    public function serviceAvailabilityPath(): string
    {
        return (string) config('carriers.fedex.service_availability_path', '/availability/v1/packageandserviceoptions');
    }

    public function rateQuotePath(): string
    {
        return (string) config('carriers.fedex.rate_quote_path', '/rate/v1/rates/quotes');
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
            return $this->productionEnabled();
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
