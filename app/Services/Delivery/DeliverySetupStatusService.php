<?php

namespace App\Services\Delivery;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxSetting;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
        $this->assessPackageAndProductReadiness($store, $shippingMethods, $healthItems);

        $blocking = collect($healthItems)->contains(fn (array $item): bool => ($item['severity'] ?? '') === 'error');
        $configurationReady = $this->hasConfigurationReadyCheckoutOption($checkoutMethods, $activeZones, $carrierAccounts);

        if ($checkoutMethods->isNotEmpty() && ! $configurationReady) {
            $healthItems[] = $this->healthItem(
                id: 'delivery_option_not_configuration_ready',
                label: 'Checkout delivery options',
                severity: 'error',
                message: 'Delivery options are shown at checkout, but none are fully configured with an active delivery area and valid pricing.',
                actionLabel: 'Review delivery options',
                actionHref: route('settings.delivery.setup.delivery-option'),
            );
            $blocking = true;
        }

        return [
            'is_ready' => ! $blocking
                && $defaultLocation !== null
                && $this->locationAddressComplete($defaultLocation)
                && $onlineFulfillmentLocations->isNotEmpty()
                && $activeZones->isNotEmpty()
                && $checkoutMethods->isNotEmpty()
                && $configurationReady,
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
                    message: '"'.$method->name.'" uses carrier pricing but has no delivery provider linked.',
                    actionLabel: 'Fix provider',
                    actionTab: 'options',
                );
            }

            if ($method->rate_type === ShippingMethod::RATE_CARRIER_CALCULATED_LATER && $method->carrier_account_id !== null) {
                $account = $carrierAccounts->firstWhere('id', $method->carrier_account_id);
                if ($account !== null && $account->isManualProvider()) {
                    $healthItems[] = $this->healthItem(
                        id: 'delivery_option_manual_carrier_pricing_'.$method->id,
                        label: 'Delivery provider',
                        severity: 'error',
                        message: 'Checkout method uses carrier pricing but is still linked to Manual Delivery.',
                        actionLabel: 'Fix provider',
                        actionHref: route('settings.delivery.setup.delivery-option'),
                    );
                } elseif ($account !== null
                    && $account->isFedEx()
                    && $account->usesFedExIntegratorProvider()
                    && ! (bool) config('carriers.fedex.checkout_rates_enabled', false)) {
                    $healthItems[] = $this->healthItem(
                        id: 'fedex_checkout_platform_off_'.$method->id,
                        label: 'FedEx checkout rates',
                        severity: 'warning',
                        message: 'FedEx checkout rates are off at the platform. Saved options will show live prices once checkout rates are enabled.',
                        actionLabel: 'Open FedEx Center',
                        actionHref: route('settings.shipping.fedex-integrator.manage', $account),
                    );
                }
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

        $this->assessFedExCheckoutBinding($shippingMethods, $carrierAccounts, $healthItems);
    }

    /**
     * @param  Collection<int, ShippingMethod>  $shippingMethods
     * @param  Collection<int, CarrierAccount>  $carrierAccounts
     * @param  list<array<string, mixed>>  $healthItems
     */
    private function assessFedExCheckoutBinding(
        Collection $shippingMethods,
        Collection $carrierAccounts,
        array &$healthItems,
    ): void {
        $fedExAccounts = $carrierAccounts->filter(
            fn (CarrierAccount $account): bool => $account->isFedEx()
                && $account->usesFedExIntegratorProvider()
                && $account->disconnected_at === null
                && $account->replaced_at === null
                && $account->status === CarrierAccount::STATUS_ENABLED
        );

        if ($fedExAccounts->isEmpty()) {
            return;
        }

        $account = $fedExAccounts->first();
        $caps = is_array($account?->capabilities) ? $account->capabilities : [];
        $platformOn = (bool) config('carriers.fedex.checkout_rates_enabled', false);
        $accountCheckoutOn = (bool) ($account?->enabled_for_checkout) && (bool) ($caps['checkout_rates'] ?? false);

        $candidateMethods = $shippingMethods->filter(
            fn (ShippingMethod $method): bool => $method->isFedExLiveRateMethod()
                && ($method->is_active || $method->enabled_for_checkout)
        );

        if ($candidateMethods->isEmpty()) {
            $zones = $shippingMethods->pluck('shippingZone')->filter()->unique('id');
            $areaName = $zones->first()?->name ?? 'your delivery area';
            $healthItems[] = $this->healthItem(
                id: 'fedex_connected_no_live_rates',
                label: 'FedEx checkout rates',
                severity: 'warning',
                message: 'FedEx is connected, but no FedEx live-rate option is linked to '.$areaName.'.',
                actionLabel: 'Configure checkout shipping',
                actionHref: route('settings.delivery.setup.delivery-option'),
            );

            return;
        }

        if (! $platformOn) {
            $healthItems[] = $this->healthItem(
                id: 'fedex_checkout_platform_off',
                label: 'FedEx checkout rates',
                severity: 'warning',
                message: 'FedEx checkout rates are not currently available on this platform.',
                actionLabel: 'Open FedEx Center',
                actionHref: $account ? route('settings.shipping.fedex-integrator.manage', $account) : route('settings.delivery.setup.delivery-option'),
            );
        } elseif (! $accountCheckoutOn) {
            $healthItems[] = $this->healthItem(
                id: 'fedex_checkout_account_capability_off',
                label: 'FedEx checkout rates',
                severity: 'warning',
                message: 'FedEx is connected, but checkout rates are not enabled for this account yet.',
                actionLabel: 'Configure checkout shipping',
                actionHref: route('settings.delivery.setup.delivery-option'),
            );
        }

        foreach ($candidateMethods as $method) {
            if ($method->carrier_service_code === null || trim((string) $method->carrier_service_code) === '') {
                $healthItems[] = $this->healthItem(
                    id: 'fedex_method_missing_service_'.$method->id,
                    label: 'FedEx services',
                    severity: 'error',
                    message: '"'.$method->name.'" is missing a FedEx service selection.',
                    actionLabel: 'Select FedEx services',
                    actionHref: route('settings.delivery.setup.delivery-option'),
                );
            }
        }
    }

    /**
     * @param  Collection<int, CarrierAccount>  $carrierAccounts
     * @param  list<array<string, mixed>>  $healthItems
     */
    private function assessDeliveryProviders(Collection $carrierAccounts, array &$healthItems): void
    {
        // Manual delivery is optional; only warn when a fixed/manual checkout method
        // points at a missing or disabled manual provider (handled in assessDeliveryOptions).
    }

    /**
     * @param  Collection<int, ShippingMethod>  $shippingMethods
     * @param  list<array<string, mixed>>  $healthItems
     */
    private function assessPackageAndProductReadiness(Store $store, Collection $shippingMethods, array &$healthItems): void
    {
        $hasFedExLive = $shippingMethods->contains(
            fn (ShippingMethod $method): bool => $method->isFedExLiveRateMethod()
                && ($method->is_active || $method->enabled_for_checkout)
        );

        if (! $hasFedExLive) {
            return;
        }

        if (Schema::hasTable('shipping_package_presets')) {
            $defaultPreset = app(StoreShippingPreferences::class)->defaultPackagePreset($store);
            if (! $defaultPreset) {
                $healthItems[] = $this->healthItem(
                    id: 'default_package_missing',
                    label: 'Default package',
                    severity: 'warning',
                    message: 'Add a default shipping package before FedEx live rates can use real dimensions.',
                    actionLabel: 'Add a default package',
                    actionTab: 'packages',
                );
            }
        }

        $weightResolver = app(ShippingWeightResolver::class);
        $missingWeightCount = Product::query()
            ->where('store_id', $store->id)
            ->where('requires_shipping', true)
            ->where('status', true)
            ->limit(200)
            ->get(['id', 'name', 'meta'])
            ->filter(fn (Product $product): bool => $weightResolver->resolve($product) === null)
            ->count();

        if ($missingWeightCount > 0) {
            $healthItems[] = $this->healthItem(
                id: 'products_missing_shipping_weight',
                label: 'Product shipping weight',
                severity: 'warning',
                message: $missingWeightCount === 1
                    ? '1 product is missing shipping weight. FedEx live rates stay hidden for carts that include it.'
                    : $missingWeightCount.' products are missing shipping weight. FedEx live rates stay hidden for carts that include them.',
                actionLabel: 'Review products',
                actionHref: route('products'),
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
        $fedEx = $carrierAccounts->first(
            fn (CarrierAccount $account): bool => $account->isFedEx()
                && $account->usesFedExIntegratorProvider()
                && $account->disconnected_at === null
                && $account->replaced_at === null
                && ($account->isConnected() || $account->status === CarrierAccount::STATUS_ENABLED)
        );

        if ($fedEx) {
            return [
                'status' => 'complete',
                'title' => 'FedEx connected',
                'detail' => 'Manual Delivery remains available as a fixed-price or fallback option.',
                'count' => $carrierAccounts->count(),
            ];
        }

        $manual = $carrierAccounts->first(
            fn (CarrierAccount $account): bool => $account->isManualProvider() && $account->status === CarrierAccount::STATUS_ENABLED
        );

        if ($manual) {
            return [
                'status' => 'optional',
                'title' => 'Manual Delivery',
                'detail' => 'Fixed or free shipping. Connect FedEx when you want live rates and labels.',
                'count' => 1,
            ];
        }

        return [
            'status' => 'optional',
            'title' => 'Manual delivery',
            'detail' => 'Optional until you connect FedEx or another provider.',
            'count' => 0,
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
     * @param  Collection<int, ShippingMethod>  $checkoutMethods
     * @param  Collection<int, ShippingZone>  $activeZones
     * @param  Collection<int, CarrierAccount>  $carrierAccounts
     */
    private function hasConfigurationReadyCheckoutOption(
        Collection $checkoutMethods,
        Collection $activeZones,
        Collection $carrierAccounts,
    ): bool {
        if ($checkoutMethods->isEmpty()) {
            return false;
        }

        $activeZoneIds = $activeZones->pluck('id')->all();

        foreach ($checkoutMethods as $method) {
            if ($this->methodIsConfigurationReady($method, $activeZoneIds, $carrierAccounts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int|string>  $activeZoneIds
     * @param  Collection<int, CarrierAccount>  $carrierAccounts
     */
    private function methodIsConfigurationReady(
        ShippingMethod $method,
        array $activeZoneIds,
        Collection $carrierAccounts,
    ): bool {
        if ($method->shipping_zone_id === null || ! in_array($method->shipping_zone_id, $activeZoneIds, true)) {
            return false;
        }

        $zone = $method->shippingZone;
        if ($zone === null || ! $zone->is_active) {
            return false;
        }

        if (collect($zone->countries)->filter()->isEmpty()) {
            return false;
        }

        if ($method->min_order_amount !== null
            && $method->max_order_amount !== null
            && (float) $method->max_order_amount > 0
            && (float) $method->min_order_amount > (float) $method->max_order_amount) {
            return false;
        }

        if ((float) ($method->flat_rate ?? 0) < 0
            || ($method->free_over_amount !== null && (float) $method->free_over_amount < 0)
            || ($method->min_order_amount !== null && (float) $method->min_order_amount < 0)
            || ($method->max_order_amount !== null && (float) $method->max_order_amount < 0)) {
            return false;
        }

        if ($method->rate_type === ShippingMethod::RATE_CARRIER_CALCULATED_LATER && $method->carrier_account_id === null) {
            return false;
        }

        if ($method->carrier_account_id !== null) {
            $account = $carrierAccounts->firstWhere('id', $method->carrier_account_id);
            if ($account === null
                || (int) $account->store_id !== (int) $method->store_id
                || ! $this->carrierAccountIsConfigurationReady($account)) {
                return false;
            }
        }

        return true;
    }

    private function carrierAccountIsConfigurationReady(CarrierAccount $account): bool
    {
        if ($account->status !== CarrierAccount::STATUS_ENABLED) {
            return false;
        }

        if (! $account->enabled_for_checkout) {
            return false;
        }

        if ($account->isManualProvider()) {
            return true;
        }

        return $account->isConnected() || $account->isSandboxPlatformFallback();
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
