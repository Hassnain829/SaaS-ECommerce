<?php

namespace App\Support;

use App\Models\CarrierAccount;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\FedEx\Support\FedExConfig;

final class CarrierCountryOptions
{
    /**
     * Merchant-facing production FedEx countries (US + Canada).
     *
     * @return array<string, string> ISO-2 => merchant label
     */
    public static function fedExProductionOptions(): array
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
        ];
    }

    /**
     * Sandbox validation countries (includes Sweden for integrator validation only).
     *
     * @return array<string, string> ISO-2 => merchant label
     */
    public static function fedExValidationOptions(): array
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'SE' => 'Sweden — validation only',
        ];
    }

    /**
     * Default options for merchant FedEx UI (production scope).
     *
     * @return array<string, string> ISO-2 => merchant label
     */
    public static function fedExOptions(): array
    {
        return self::fedExProductionOptions();
    }

    /**
     * @return array<string, string>
     */
    public static function fedExOptionsForContext(?string $environment = null, ?bool $validationMode = null): array
    {
        $environment = strtolower((string) ($environment ?? CarrierAccount::ENVIRONMENT_SANDBOX));
        $validationMode ??= app(FedExConfig::class)->validationModeEnabled();

        if ($environment === CarrierAccount::ENVIRONMENT_LIVE) {
            $allowed = app(FedExConfig::class)->liveAllowedCountries();
            $labels = self::fedExProductionOptions();

            return array_filter(
                $labels,
                static fn (string $code): bool => in_array($code, $allowed, true),
                ARRAY_FILTER_USE_KEY
            );
        }

        return $validationMode ? self::fedExValidationOptions() : self::fedExProductionOptions();
    }

    public static function defaultFedExCountry(?string $originCountryCode = null): string
    {
        $normalized = app(CarrierOriginReadinessService::class)->normalizeCountryCode($originCountryCode);

        if ($normalized !== null && self::isAllowedFedExCountry($normalized)) {
            return $normalized;
        }

        return 'US';
    }

    public static function isAllowedFedExCountry(?string $countryCode): bool
    {
        $code = strtoupper(trim((string) $countryCode));

        return array_key_exists($code, self::fedExProductionOptions());
    }

    public static function isAllowedFedExRegistrationCountry(
        ?string $countryCode,
        ?string $environment = null,
        ?bool $validationMode = null,
    ): bool {
        $code = strtoupper(trim((string) $countryCode));
        if ($code === '') {
            return false;
        }

        return array_key_exists($code, self::fedExOptionsForContext($environment, $validationMode));
    }

    /**
     * @return list<string>
     */
    public static function canadianProvinceCodes(): array
    {
        return ['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'];
    }

    /**
     * USPS/FedEx-compatible two-letter codes for the 50 states and DC.
     *
     * @return list<string>
     */
    public static function unitedStatesStateCodes(): array
    {
        return [
            'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
            'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
            'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
            'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
            'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
            'DC',
        ];
    }
}
