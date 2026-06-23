<?php

namespace App\Services\Carriers\FedEx\Validation;

final class FedExValidationScopeService
{
    public const SCOPE_ACCOUNT_REGISTRATION = 'account_registration';

    public const SCOPE_ADDRESS_VALIDATION = 'address_validation';

    public const SCOPE_SERVICE_AVAILABILITY = 'service_availability';

    public const SCOPE_COMPREHENSIVE_RATES = 'comprehensive_rates';

    public const SCOPE_SHIP = 'ship';

    public const SCOPE_SHIP_CANCEL = 'ship_cancel';

    public const SCOPE_TRACKING = 'tracking';

    public const SCOPE_TRADE_DOCUMENTS = 'trade_documents';

    /**
     * @return list<string>
     */
    public function defaultRequiredScopes(): array
    {
        return [
            self::SCOPE_ACCOUNT_REGISTRATION,
            self::SCOPE_ADDRESS_VALIDATION,
            self::SCOPE_SERVICE_AVAILABILITY,
            self::SCOPE_COMPREHENSIVE_RATES,
            self::SCOPE_SHIP,
            self::SCOPE_TRACKING,
        ];
    }

    /**
     * @return list<string>
     */
    public function resolveRequiredScopes(?array $override = null): array
    {
        $configured = config('carriers.fedex.validation_required_scopes');

        if (is_array($override) && $override !== []) {
            return array_values(array_unique($override));
        }

        if (is_array($configured) && $configured !== []) {
            return array_values(array_unique($configured));
        }

        return $this->defaultRequiredScopes();
    }

    public function trackingRequired(?array $scopes = null): bool
    {
        return in_array(self::SCOPE_TRACKING, $this->resolveRequiredScopes($scopes), true);
    }

    public function shipCancelRequired(?array $scopes = null): bool
    {
        return in_array(self::SCOPE_SHIP_CANCEL, $this->resolveRequiredScopes($scopes), true);
    }

    public function tradeDocumentsRequired(?array $scopes = null): bool
    {
        return in_array(self::SCOPE_TRADE_DOCUMENTS, $this->resolveRequiredScopes($scopes), true);
    }
}
