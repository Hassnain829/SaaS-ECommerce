<?php

namespace App\Services\Carriers\FedEx\Support;

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

    /**
     * Model B / sandbox-platform-fallback tools are local|testing only, even when the flag is on.
     */
    public function modelBRoutesEnabled(): bool
    {
        return app()->environment(['local', 'testing']) && $this->modelBDeveloperFallbackEnabled();
    }

    /**
     * Merchants should not pick sandbox vs live. Local/testing operators may.
     */
    public function merchantMayChooseEnvironment(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    /**
     * Environment used when the merchant is not offered a choice.
     */
    public function merchantDefaultEnvironment(): string
    {
        if ($this->productionEnabled()) {
            return CarrierAccount::ENVIRONMENT_LIVE;
        }

        if ($this->allowsIntegratorEnvironment(CarrierAccount::ENVIRONMENT_SANDBOX)) {
            return CarrierAccount::ENVIRONMENT_SANDBOX;
        }

        return CarrierAccount::ENVIRONMENT_LIVE;
    }

    public function productionEnabled(): bool
    {
        return $this->productionConfigurationErrors() === [];
    }

    /**
     * ISO-2 countries allowed for live Model A merchant onboarding.
     *
     * @return list<string>
     */
    public function liveAllowedCountries(): array
    {
        $raw = (string) config('carriers.fedex.live_allowed_countries', 'US,CA');
        $parts = preg_split('/[\s,]+/', strtoupper($raw)) ?: [];
        $allowed = [];

        foreach ($parts as $part) {
            $code = trim((string) $part);
            if ($code !== '' && preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, $allowed, true)) {
                $allowed[] = $code;
            }
        }

        return $allowed;
    }

    public function isLiveCountryAllowed(string $countryCode): bool
    {
        return in_array(strtoupper(trim($countryCode)), $this->liveAllowedCountries(), true);
    }

    /**
     * Human-readable blockers that prevent marking FedEx production ready.
     * Does not enable production — Batch 7 / preflight only.
     *
     * @return list<string>
     */
    public function productionConfigurationErrors(): array
    {
        $errors = [];

        if (! $this->isEnabled()) {
            $errors[] = 'FEDEX_ENABLED must be true.';
        }

        if (! $this->modelAEnabled()) {
            $errors[] = 'FEDEX_INTEGRATOR_MODEL_A_ENABLED must be true.';
        }

        if (! filter_var(config('carriers.fedex.integrator_production_enabled', false), FILTER_VALIDATE_BOOL)) {
            $errors[] = 'FEDEX_INTEGRATOR_PRODUCTION_ENABLED must be true.';
        }

        if (! filled($this->parentClientId(CarrierAccount::ENVIRONMENT_LIVE))
            || ! filled($this->parentClientSecret(CarrierAccount::ENVIRONMENT_LIVE))) {
            $errors[] = 'FEDEX_LIVE_CLIENT_ID and FEDEX_LIVE_CLIENT_SECRET must be set.';
        }

        $liveBase = (string) config('carriers.fedex.live.base_url', '');
        if ($liveBase !== 'https://apis.fedex.com') {
            $errors[] = 'FEDEX_LIVE_BASE_URL must be https://apis.fedex.com.';
        }

        if ($this->modelBDeveloperFallbackEnabled()) {
            $errors[] = 'Model B developer fallback must be disabled for production.';
        }

        if (filter_var(config('carriers.fedex.sandbox_allow_platform_fallback', false), FILTER_VALIDATE_BOOL)) {
            $errors[] = 'FEDEX_SANDBOX_ALLOW_PLATFORM_FALLBACK must be false for production.';
        }

        if ($this->environment() !== CarrierAccount::ENVIRONMENT_LIVE) {
            $errors[] = 'FEDEX_ENVIRONMENT must be live for production.';
        }

        $rawCountries = trim((string) config('carriers.fedex.live_allowed_countries', 'US,CA'));
        $configuredCountryTokens = $rawCountries === ''
            ? []
            : (preg_split('/[\s,]+/', strtoupper($rawCountries)) ?: []);
        $countries = $this->liveAllowedCountries();

        if ($configuredCountryTokens === [] || $countries === []) {
            $errors[] = 'FEDEX_LIVE_ALLOWED_COUNTRIES must include at least one ISO-2 country.';
        }

        foreach ($configuredCountryTokens as $code) {
            if (! preg_match('/^[A-Z]{2}$/', $code)) {
                $errors[] = 'FEDEX_LIVE_ALLOWED_COUNTRIES contains an invalid ISO-2 code ('.$code.').';

                continue;
            }

            if (! in_array($code, ['US', 'CA'], true)) {
                $errors[] = 'Live-allowed countries are limited to US and CA (found '.$code.').';
            }
        }

        return $errors;
    }

    public function assertProductionReady(): void
    {
        $errors = $this->productionConfigurationErrors();
        if ($errors === []) {
            return;
        }

        throw new \RuntimeException('FedEx production is not ready: '.implode(' ', $errors));
    }

    public function allowsIntegratorEnvironment(string $environment): bool
    {
        $environment = strtolower($environment);

        if ($environment === CarrierAccount::ENVIRONMENT_LIVE) {
            return $this->productionEnabled();
        }

        // Sandbox Model A ops are local/testing only — never production.
        return $environment === CarrierAccount::ENVIRONMENT_SANDBOX
            && $this->modelAEnabled()
            && app()->environment(['local', 'testing']);
    }

    public function eulaVersion(): string
    {
        return (string) config('carriers.fedex.integrator_eula_version', 'FedEx Form No. 2002382 v 4 June 2024 Rev');
    }

    public function eulaPath(): string
    {
        $path = (string) config('carriers.fedex.integrator_eula_path', 'resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf');

        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path)
            ? $path
            : base_path($path);
    }

    public function eulaFormNumber(): string
    {
        return (string) config('carriers.fedex.integrator_eula_form_number', '2002382');
    }

    public function eulaExpectedPages(): int
    {
        return max(1, (int) config('carriers.fedex.integrator_eula_expected_pages', 11));
    }

    public function eulaExpectedSha256(): string
    {
        return strtolower((string) config('carriers.fedex.integrator_eula_sha256', ''));
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

    public function shipCreatePath(?string $environment = null): string
    {
        return (string) config('carriers.fedex.ship_create_path', '/ship/v1/shipments');
    }

    public function documentApiBaseUrl(?string $environment = null): string
    {
        $environment = $this->environment($environment);

        return rtrim((string) config(
            $environment === 'live'
                ? 'carriers.fedex.document_api_live_base_url'
                : 'carriers.fedex.document_api_sandbox_base_url',
            $environment === 'live'
                ? 'https://documentapi.prod.fedex.com'
                : 'https://documentapitest.prod.fedex.com/sandbox'
        ), '/');
    }

    public function tradeDocumentsUploadDocumentPath(): string
    {
        return (string) config('carriers.fedex.trade_documents_upload_document_path', '/documents/v1/etds/upload');
    }

    public function shipCancelPath(?string $environment = null): string
    {
        return (string) config('carriers.fedex.ship_cancel_path', '/ship/v1/shipments/cancel');
    }

    public function basicIntegratedVisibilityPath(): ?string
    {
        $path = (string) config('carriers.fedex.basic_integrated_visibility_path', '');

        return $path !== '' ? $path : null;
    }

    public function allowsShipLabelGeneration(?string $environment = null): bool
    {
        return filter_var(config('carriers.fedex.ops_ship_labels_enabled', false), FILTER_VALIDATE_BOOL);
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

    public const COMPREHENSIVE_RATE_QUOTE_PATH = '/rate/v1/comprehensiverates/quotes';

    public function rateQuotePath(): string
    {
        return (string) config('carriers.fedex.rate_quote_path', '/rate/v1/rates/quotes');
    }

    public function comprehensiveRateQuotePath(): string
    {
        return (string) config('carriers.fedex.comprehensive_rate_quote_path', self::COMPREHENSIVE_RATE_QUOTE_PATH);
    }

    public function comprehensiveRateQuotePathConfigured(): bool
    {
        return $this->comprehensiveRateQuotePath() === self::COMPREHENSIVE_RATE_QUOTE_PATH;
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
