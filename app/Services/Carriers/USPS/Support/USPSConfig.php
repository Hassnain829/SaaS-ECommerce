<?php

namespace App\Services\Carriers\USPS\Support;

use App\Models\CarrierAccount;

final class USPSConfig
{
    public const TEM_BASE_URL = 'https://apis-tem.usps.com';

    public const PRODUCTION_BASE_URL = 'https://apis.usps.com';

    public function environment(?string $environment = null): string
    {
        $environment = strtolower((string) ($environment ?? config('carriers.usps.environment', 'testing')));

        return in_array($environment, ['testing', 'production', 'live'], true) ? $environment : 'testing';
    }

    public function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('carriers.usps.base_url', self::TEM_BASE_URL), '/');

        return $baseUrl !== '' ? $baseUrl : self::TEM_BASE_URL;
    }

    public function allowsConfiguredBaseUrl(): bool
    {
        if ($this->isTemBaseUrl($this->baseUrl())) {
            return app()->environment(['local', 'testing']);
        }

        return true;
    }

    public function isTemBaseUrl(string $baseUrl): bool
    {
        return str_contains(strtolower($baseUrl), 'apis-tem.usps.com');
    }

    public function isEnabled(): bool
    {
        return (bool) config('carriers.usps.enabled', false);
    }

    public function isConfigured(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! $this->allowsConfiguredBaseUrl()) {
            return false;
        }

        return filled($this->consumerKey()) && filled($this->consumerSecret());
    }

    public function consumerKey(): ?string
    {
        $key = (string) config('carriers.usps.consumer_key', '');

        return $key !== '' ? $key : null;
    }

    public function consumerSecret(): ?string
    {
        $secret = (string) config('carriers.usps.consumer_secret', '');

        return $secret !== '' ? $secret : null;
    }

    public function oauthPath(): string
    {
        return (string) config('carriers.usps.oauth_path', '/oauth2/v3/token');
    }

    public function addressValidationPath(): string
    {
        return (string) config('carriers.usps.address_validation_path', '/addresses/v3/address');
    }

    public function domesticBaseRatesPath(): string
    {
        return (string) config('carriers.usps.domestic_base_rates_path', '/prices/v3/base-rates/search');
    }

    public function defaultMailClass(): string
    {
        return (string) config('carriers.usps.default_mail_class', 'USPS_GROUND_ADVANTAGE');
    }

    public function defaultPriceType(): string
    {
        return (string) config('carriers.usps.default_price_type', 'RETAIL');
    }

    public function labelsEnabled(): bool
    {
        return (bool) config('carriers.usps.labels_enabled', false);
    }

    public function platformLabelPurchaseEnabled(): bool
    {
        return (bool) config('carriers.usps.platform_label_purchase', false);
    }

    public function connectTimeout(): int
    {
        return max(1, (int) config('carriers.usps.timeouts.connect', 10));
    }

    public function requestTimeout(): int
    {
        return max(1, (int) config('carriers.usps.timeouts.request', 30));
    }

    public function allowsEnvironment(string $environment): bool
    {
        $environment = strtolower($environment);

        if (in_array($environment, [CarrierAccount::ENVIRONMENT_LIVE, 'production'], true)) {
            return false;
        }

        return in_array($environment, [CarrierAccount::ENVIRONMENT_TESTING, CarrierAccount::ENVIRONMENT_SANDBOX], true);
    }
}
