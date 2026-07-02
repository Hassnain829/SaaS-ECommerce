<?php

namespace App\Services\Delivery;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxSetting;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Support\Collection;

class DeliverySetupStatusService
{
    /**
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, ShippingZone>  $shippingZones
     * @param  Collection<int, ShippingMethod>  $shippingMethods
     * @param  Collection<int, CarrierAccount>  $carrierAccounts
     * @return array{
     *     is_ready: bool,
     *     ship_from: array<string, mixed>,
     *     delivery_areas: array<string, mixed>,
     *     delivery_options: array<string, mixed>,
     *     delivery_providers: array<string, mixed>,
     *     tax_summary: array<string, mixed>,
     *     health_items: list<array<string, mixed>>
     * }
     */
    public function assess(
        Store $store,
        Collection $locations,
        Collection $shippingZones,
        Collection $shippingMethods,
        Collection $carrierAccounts,
        ?TaxSetting $taxSetting,
    ): array {
        $defaultLocation = $locations->firstWhere('is_default', true)
            ?? $locations->firstWhere('is_active', true)
            ?? $locations->first();

        $activeLocations = $locations->where('is_active', true);
        $onlineFulfillmentLocations = $activeLocations->where('fulfills_online_orders', true);
        $activeZones = $shippingZones->where('is_active', true);
        $activeMethods = $shippingMethods->where('is_active', true);
        $checkoutMethods = $shippingMethods
            ->where('is_active', true)
            ->where('enabled_for_checkout', true);

        $healthItems = [];

        $this->assessShipFrom($defaultLocation, $onlineFulfillmentLocations, $healthItems);
        $this->assessDeliveryAreas($activeZones, $healthItems);
        $this->assessDeliveryOptions($shippingMethods, $activeMethods, $checkoutMethods, $carrierAccounts, $healthItems);
        $this->assessDeliveryProviders($carrierAccounts, $healthItems);

        $blocking = collect($healthItems)->contains(fn (array $item): bool => ($item['severity'] ?? '') === 'error');

        return [
            'is_ready' => ! $blocking
                && $defaultLocation !== null
                && $this->locationAddressComplete($defaultLocation)
                && $onlineFulfillmentLocations->isNotEmpty()
                && $activeZones->isNotEmpty()
                && $checkoutMethods->isNotEmpty(),
            'ship_from' => $this->shipFromSummary($defaultLocation),
            'delivery_areas' => $this->deliveryAreasSummary($activeZones),
            'delivery_options' => $this->deliveryOptionsSummary($checkoutMethods, $activeMethods),
            'delivery_providers' => $this->deliveryProvidersSummary($carrierAccounts),
            'tax_summary' => $this->taxSummary($taxSetting),
            'health_items' => $healthItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeShipFromLocation(?Location $location): array
    {
        return $this->shipFromSummary($location);
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeDeliveryArea(?ShippingZone $zone): array
    {
        if ($zone === null) {
            return $this->deliveryAreasSummary(collect());
        }

        return [
            'status' => $zone->is_active ? 'complete' : 'needs_attention',
            'title' => $zone->name,
            'detail' => $this->zoneCoverageLabel($zone),
            'count' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeDeliveryOption(?ShippingMethod $method): array
    {
        if ($method === null) {
            return $this->deliveryOptionsSummary(collect(), collect());
        }

        return [
            'status' => ($method->is_active && $method->enabled_for_checkout) ? 'complete' : 'needs_attention',
            'title' => $method->name,
            'detail' => $this->methodPriceLabel($method),
            'count' => 1,
        ];
    }

    /**
     * @param  Collection<int, Location>  $onlineFulfillmentLocations
     * @param  list<array<string, mixed>>  $healthItems
     */
    private function assessShipFrom(?Location $defaultLocation, Collection $onlineFulfillmentLocations, array &$healthItems): void
    {
        if ($defaultLocation === null || ! $defaultLocation->is_active) {
            $healthItems[] = $this->healthItem(
                id: 'ship_from_missing',
                label: 'Ship-from location',
                severity: 'error',
                message: 'Add a ship-from location so orders know where to ship from.',
                actionLabel: 'Add ship-from address',
                actionHref: route('settings.locations.index'),
            );

            return;
        }

        if (! $this->locationAddressComplete($defaultLocation)) {
            $healthItems[] = $this->healthItem(
                id: 'ship_from_address_incomplete',
                label: 'Ship-from address',
                severity: 'error',
                message: 'Complete the main ship-from address before checkout can use it.',
                actionLabel: 'Add ship-from address',
                actionHref: route('settings.locations.index'),
            );
        }

        if ($onlineFulfillmentLocations->isEmpty()) {
            $healthItems[] = $this->healthItem(
                id: 'ship_from_online_fulfillment',
                label: 'Online fulfillment',
                severity: 'error',
                message: 'Turn on online fulfillment for at least one active ship-from location.',
                actionLabel: 'Fix fulfillment setting',
                actionHref: route('settings.locations.index'),
            );
        }
    }

    /**
     * @param  Collection<int, ShippingZone>  $activeZones
     * @param  list<array<string, mixed>>  $healthItems
     */
    private function assessDeliveryAreas(Collection $activeZones, array &$healthItems): void
    {
        if ($activeZones->isEmpty()) {
            $healthItems[] = $this->healthItem(
                id: 'delivery_area_missing',
                label: 'Delivery area',
                severity: 'error',
                message: 'Choose where you deliver before customers can receive orders.',
                actionLabel: 'Choose a delivery area',
                actionTab: 'areas',
            );

            return;
        }

        foreach ($activeZones as $zone) {
            if (collect($zone->countries)->filter()->isEmpty()) {
                $healthItems[] = $this->healthItem(
                    id: 'delivery_area_no_country_'.$zone->id,
                    label: 'Delivery area coverage',
                    severity: 'error',
                    message: '"'.$zone->name.'" does not include a country yet.',
                    actionLabel: 'Choose a delivery area',
                    actionTab: 'areas',
                );
            }
        }
    }

    /**
     * @param  Collection<int, ShippingMethod>  $shippingMethods
     * @param  Collection<int, ShippingMethod>  $activeMethods
     * @param  Collection<int, ShippingMethod>  $checkoutMethods
     * @param  Collection<int, CarrierAccount>  $carrierAccounts
     * @param  list<array<string, mixed>>  $healthItems
     */
    private function assessDeliveryOptions(
        Collection $shippingMethods,
        Collection $activeMethods,
        Collection $checkoutMethods,
        Collection $carrierAccounts,
        array &$healthItems,
    ): void {
        if ($activeMethods->isEmpty()) {
            $healthItems[] = $this->healthItem(
                id: 'delivery_option_missing',
                label: 'Delivery option',
                severity: 'error',
                message: 'Add at least one delivery option for customers to choose at checkout.',
                actionLabel: 'Add a delivery option',
                actionTab: 'options',
            );
        }

        if ($checkoutMethods->isEmpty() && $activeMethods->isNotEmpty()) {
            $healthItems[] = $this->healthItem(
                id: 'delivery_option_checkout_hidden',
                label: 'Checkout delivery options',
                severity: 'error',
                message: 'You have delivery options, but none are shown at checkout.',
                actionLabel: 'Fix checkout visibility',
                actionTab: 'options',
            );
        }

        foreach ($shippingMethods as $method) {
            if ($method->shipping_zone_id === null || ! $method->shippingZone) {
                $healthItems[] = $this->healthItem(
                    id: 'delivery_option_no_area_'.$method->id,
                    label: 'Delivery option setup',
                    severity: 'error',
                    message: '"'.$method->name.'" is not linked to a delivery area.',
                    actionLabel: 'Add a delivery option',
                    actionTab: 'options',
                );
            }

            if ($method->is_active && ! $method->enabled_for_checkout) {
                $healthItems[] = $this->healthItem(
                    id: 'delivery_option_active_hidden_'.$method->id,
                    label: 'Checkout visibility',
                    severity: 'warning',
                    message: '"'.$method->name.'" is active but hidden from checkout.',
                    actionLabel: 'Fix checkout visibility',
                    actionTab: 'options',
                );
            }

            if (! $method->is_active && $method->enabled_for_checkout) {
                $healthItems[] = $this->healthItem(
                    id: 'delivery_option_inactive_shown_'.$method->id,
                    label: 'Checkout visibility',
                    severity: 'warning',
                    message: '"'.$method->name.'" is shown at checkout but currently inactive.',
                    actionLabel: 'Fix checkout visibility',
                    actionTab: 'options',
                );
            }

            if ($method->min_order_amount !== null
                && $method->max_order_amount !== null
                && (float) $method->max_order_amount > 0
                && (float) $method->min_order_amount > (float) $method->max_order_amount) {
                $healthItems[] = $this->healthItem(
                    id: 'delivery_option_invalid_eligibility_'.$method->id,
                    label: 'Order eligibility',
                    severity: 'error',
                    message: '"'.$method->name.'" has a minimum order greater than its maximum order.',
                    actionLabel: 'Add a delivery option',
                    actionTab: 'options',
                );
            }

            if ((float) ($method->flat_rate ?? 0) < 0
                || ($method->free_over_amount !== null && (float) $method->free_over_amount < 0)
                || ($method->min_order_amount !== null && (float) $method->min_order_amount < 0)
                || ($method->max_order_amount !== null && (float) $method->max_order_amount < 0)) {
                $healthItems[] = $this->healthItem(
                    id: 'delivery_option_invalid_pricing_'.$method->id,
                    label: 'Delivery pricing',
                    severity: 'error',
                    message: '"'.$method->name.'" has invalid negative pricing or threshold values.',
                    actionLabel: 'Add a delivery option',
                    actionTab: 'options',
                );
            }

            if ($method->rate_type === ShippingMethod::RATE_CARRIER_CALCULATED_LATER && $method->carrier_account_id === null) {
                $healthItems[] = $this->healthItem(
                    id: 'delivery_option_provider_required_'.$method->id,
                    label: 'Delivery provider',
                    severity: 'error',
                    message: '"'.$method->name.'" needs a delivery provider for carrier-calculated pricing.',
                    actionLabel: 'Open advanced delivery settings',
                    actionTab: 'providers',
                );
            }

            if ($method->carrier_account_id !== null) {
                $account = $carrierAccounts->firstWhere('id', $method->carrier_account_id);
                if ($account === null || (int) $account->store_id !== (int) $method->store_id) {
                    $healthItems[] = $this->healthItem(
                        id: 'delivery_option_invalid_provider_'.$method->id,
                        label: 'Delivery provider',
                        severity: 'error',
                        message: '"'.$method->name.'" points to a delivery provider that is missing or unavailable.',
                        actionLabel: 'Open advanced delivery settings',
                        actionTab: 'providers',
                    );
                }
            }
        }
    }

    /**
     * @param  Collection<int, CarrierAccount>  $carrierAccounts
     * @param  list<array<string, mixed>>  $healthItems
     */
    private function assessDeliveryProviders(Collection $carrierAccounts, array &$healthItems): void
    {
        $manualAccounts = $carrierAccounts->filter(
            fn (CarrierAccount $account): bool => $account->isManualProvider() && $account->status === CarrierAccount::STATUS_ENABLED
        );

        if ($manualAccounts->isEmpty()) {
            $healthItems[] = $this->healthItem(
                id: 'manual_provider_missing',
                label: 'Manual delivery provider',
                severity: 'warning',
                message: 'No manual delivery provider is enabled yet. Flat-rate options still work, but provider setup may be needed later.',
                actionLabel: 'Open advanced delivery settings',
                actionTab: 'providers',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function shipFromSummary(?Location $location): array
    {
        if ($location === null) {
            return [
                'status' => 'missing',
                'title' => 'Not configured',
                'detail' => 'Add where orders ship from.',
            ];
        }

        $addressParts = collect([
            $location->address_line1,
            $location->city,
            $location->state,
            $location->postal_code,
            $this->countryLabel($location->country_code),
        ])->filter()->values();

        return [
            'status' => $this->locationAddressComplete($location) ? 'complete' : 'needs_attention',
            'title' => $location->name,
            'detail' => $addressParts->isNotEmpty() ? $addressParts->implode(', ') : 'Address incomplete',
            'fulfills_online_orders' => (bool) $location->fulfills_online_orders,
        ];
    }

    /**
     * @param  Collection<int, ShippingZone>  $activeZones
     * @return array<string, mixed>
     */
    private function deliveryAreasSummary(Collection $activeZones): array
    {
        if ($activeZones->isEmpty()) {
            return [
                'status' => 'missing',
                'title' => 'Not configured',
                'detail' => 'Choose where you deliver.',
                'count' => 0,
            ];
        }

        $first = $activeZones->first();
        $extra = max(0, $activeZones->count() - 1);

        return [
            'status' => 'complete',
            'title' => $first?->name ?? 'Delivery area',
            'detail' => $this->zoneCoverageLabel($first).($extra > 0 ? ' · +'.$extra.' more area(s)' : ''),
            'count' => $activeZones->count(),
        ];
    }

    /**
     * @param  Collection<int, ShippingMethod>  $checkoutMethods
     * @param  Collection<int, ShippingMethod>  $activeMethods
     * @return array<string, mixed>
     */
    private function deliveryOptionsSummary(Collection $checkoutMethods, Collection $activeMethods): array
    {
        if ($checkoutMethods->isEmpty()) {
            return [
                'status' => $activeMethods->isEmpty() ? 'missing' : 'needs_attention',
                'title' => $activeMethods->isEmpty() ? 'Not configured' : 'Not shown at checkout',
                'detail' => $activeMethods->isEmpty()
                    ? 'Add what customers see at checkout.'
                    : 'Fix checkout visibility on your delivery options.',
                'count' => $activeMethods->count(),
            ];
        }

        $first = $checkoutMethods->first();
        $extra = max(0, $checkoutMethods->count() - 1);

        return [
            'status' => 'complete',
            'title' => $first?->name ?? 'Delivery option',
            'detail' => $this->methodPriceLabel($first).($extra > 0 ? ' · +'.$extra.' more at checkout' : ''),
            'count' => $checkoutMethods->count(),
        ];
    }

    /**
     * @param  Collection<int, CarrierAccount>  $carrierAccounts
     * @return array<string, mixed>
     */
    private function deliveryProvidersSummary(Collection $carrierAccounts): array
    {
        $connected = $carrierAccounts->filter(
            fn (CarrierAccount $account): bool => $account->isConnected() || ($account->isManualProvider() && $account->status === CarrierAccount::STATUS_ENABLED)
        );

        if ($connected->isEmpty()) {
            return [
                'status' => 'optional',
                'title' => 'Manual delivery',
                'detail' => 'Optional until you connect FedEx, USPS, or another provider.',
                'count' => 0,
            ];
        }

        $manual = $connected->first(fn (CarrierAccount $account): bool => $account->isManualProvider());

        return [
            'status' => 'complete',
            'title' => $manual?->display_name ?? $connected->first()?->display_name ?? 'Delivery provider',
            'detail' => $connected->count().' provider(s) connected',
            'count' => $connected->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taxSummary(?TaxSetting $taxSetting): array
    {
        if ($taxSetting === null || ! $taxSetting->enabled) {
            return [
                'status' => 'off',
                'title' => 'Tax is off',
                'detail' => 'Platform checkout will not add calculated tax.',
                'edit_href' => route('settings.taxes.index'),
            ];
        }

        if ($taxSetting->prices_include_tax) {
            return [
                'status' => 'included',
                'title' => 'Prices include tax',
                'detail' => 'Eligible platform checkouts treat product prices as tax-inclusive.',
                'edit_href' => route('settings.taxes.index'),
            ];
        }

        return [
            'status' => 'added',
            'title' => 'Tax is added at checkout',
            'detail' => 'Platform checkout applies your configured tax rates.',
            'edit_href' => route('settings.taxes.index'),
        ];
    }

    private function locationAddressComplete(?Location $location): bool
    {
        if ($location === null) {
            return false;
        }

        return filled($location->address_line1)
            && filled($location->city)
            && filled($location->country_code);
    }

    private function zoneCoverageLabel(?ShippingZone $zone): string
    {
        if ($zone === null) {
            return '';
        }

        $countries = collect($zone->countries)
            ->filter()
            ->map(fn ($code): string => $this->countryLabel((string) $code))
            ->unique()
            ->values();

        $regions = collect($zone->regions)->filter()->values();
        $postalCount = collect($zone->postal_patterns)->filter()->count();

        $parts = [];
        if ($countries->isNotEmpty()) {
            $parts[] = $countries->implode(', ');
        }
        if ($regions->isNotEmpty()) {
            $parts[] = $regions->implode(', ');
        }
        if ($postalCount > 0) {
            $parts[] = $postalCount.' ZIP/postal rule(s)';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Coverage not set';
    }

    private function methodPriceLabel(?ShippingMethod $method): string
    {
        if ($method === null) {
            return '';
        }

        return match ($method->rate_type) {
            ShippingMethod::RATE_FREE => 'Free delivery',
            ShippingMethod::RATE_FLAT, ShippingMethod::RATE_MANUAL => $method->free_over_amount !== null
                ? 'From '.number_format((float) $method->flat_rate, 2).' · free over '.number_format((float) $method->free_over_amount, 2)
                : number_format((float) $method->flat_rate, 2),
            default => ucfirst(str_replace('_', ' ', (string) $method->rate_type)),
        };
    }

    private function countryLabel(mixed $code): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return '';
        }
        $catalog = TaxCountryCatalog::all();

        return $catalog[$code] ?? $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function healthItem(
        string $id,
        string $label,
        string $severity,
        string $message,
        string $actionLabel,
        ?string $actionHref = null,
        ?string $actionTab = null,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'severity' => $severity,
            'message' => $message,
            'action_label' => $actionLabel,
            'action_href' => $actionHref,
            'action_tab' => $actionTab,
        ];
    }
}
