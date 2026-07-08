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

    public function merchantConnectionEnabled(): bool
    {
        return (bool) config('carriers.usps.merchant_connection_enabled', false);
    }

    public function platformEpa(): ?string
    {
        $value = (string) config('carriers.usps.platform_epa', '');

        return $value !== '' ? $value : null;
    }

    public function platformCrid(): ?string
    {
        $value = (string) config('carriers.usps.platform_crid', config('carriers.usps.crid', ''));

        return $value !== '' ? $value : null;
    }

    public function platformLabelProviderName(): string
    {
        return (string) config('carriers.usps.platform_label_provider_name', 'BmyBrand');
    }

    public function businessPortalUrl(): string
    {
        return (string) config('carriers.usps.business_portal_url', 'https://gateway.usps.com/eAdmin/action/home');
    }

    public function oauthCallbackPath(): string
    {
        return (string) config('carriers.usps.oauth_callback_path', '/settings/shipping/carriers/usps/oauth/callback');
    }

    public function oauthAuthorizePath(): string
    {
        return (string) config('carriers.usps.oauth_authorize_path', '/oauth2/v3/authorize');
    }

    public function oauthRedirectUrl(): string
    {
        $configured = trim((string) config('carriers.usps.oauth_redirect_url', ''));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return rtrim((string) config('app.url', ''), '/').$this->oauthCallbackPath();
    }

    public function merchantOAuthEnabled(): bool
    {
        return (bool) config('carriers.usps.merchant_oauth_enabled', false)
            && $this->merchantConnectionEnabled()
            && $this->isMerchantOAuthConfigured()
            && $this->merchantOAuthIcdConfirmed();
    }

    public function merchantOAuthIcdConfirmed(): bool
    {
        return (bool) config('carriers.usps.merchant_oauth_icd_confirmed', false);
    }

    public function merchantCopAuthorizationUrl(): ?string
    {
        $url = trim((string) config('carriers.usps.merchant_cop_authorization_url', ''));

        return $url !== '' ? $url : null;
    }

    public function isMerchantOAuthConfigured(): bool
    {
        return filled($this->merchantOAuthConsumerKey()) && filled($this->merchantOAuthConsumerSecret());
    }

    public function merchantOAuthConsumerKey(): ?string
    {
        $dedicated = trim((string) config('carriers.usps.merchant_oauth_consumer_key', ''));

        if ($dedicated !== '') {
            return $dedicated;
        }

        return $this->consumerKey();
    }

    public function merchantOAuthConsumerSecret(): ?string
    {
        $dedicated = trim((string) config('carriers.usps.merchant_oauth_consumer_secret', ''));

        if ($dedicated !== '') {
            return $dedicated;
        }

        return $this->consumerSecret();
    }

    public function merchantOAuthAllowHttpRedirect(): bool
    {
        return (bool) config('carriers.usps.merchant_oauth_allow_http_redirect', false)
            || app()->environment(['local', 'testing']);
    }

    public function merchantOAuthRedirectUrlIsValid(): bool
    {
        $redirectUrl = $this->oauthRedirectUrl();

        if ($redirectUrl === '') {
            return false;
        }

        if (str_starts_with(strtolower($redirectUrl), 'https://')) {
            return true;
        }

        return $this->merchantOAuthAllowHttpRedirect()
            && (str_starts_with(strtolower($redirectUrl), 'http://127.0.0.1')
                || str_starts_with(strtolower($redirectUrl), 'http://localhost'));
    }

    public function merchantOAuthScope(): ?string
    {
        $scope = trim((string) config('carriers.usps.merchant_oauth_scope', ''));

        return $scope !== '' ? $scope : null;
    }

    public function userinfoPath(): string
    {
        return (string) config('carriers.usps.userinfo_path', '/oauth2-oidc/v3/userinfo');
    }

    public function oauthRevokePath(): string
    {
        return (string) config('carriers.usps.oauth_revoke_path', '/oauth2/v3/revoke');
    }

    public function shipEnrollmentPath(): string
    {
        return (string) config('carriers.usps.ship_enrollment_path', '/shipments/v3/enrollment');
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
