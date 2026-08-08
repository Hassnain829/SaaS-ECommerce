<?php

namespace App\Services\Carriers\FedEx\Support;

use App\Models\Store;
use App\Services\Delivery\StoreShippingPreferences;

/**
 * Unifies FedEx pickup/handoff type across rate and ship builders.
 */
final class FedExHandoffTypeResolver
{
    public const USE_SCHEDULED_PICKUP = StoreShippingPreferences::HANDOFF_USE_SCHEDULED_PICKUP;

    public const DROPOFF_AT_FEDEX_LOCATION = StoreShippingPreferences::HANDOFF_DROPOFF;

    public function __construct(
        private readonly StoreShippingPreferences $preferences,
    ) {}

    public function resolve(?Store $store = null, ?string $override = null): string
    {
        $override = strtoupper(trim((string) $override));
        if (in_array($override, StoreShippingPreferences::HANDOFF_TYPES, true)) {
            return $override;
        }

        if ($store) {
            return $this->preferences->defaultHandoffType($store);
        }

        return self::USE_SCHEDULED_PICKUP;
    }
}
