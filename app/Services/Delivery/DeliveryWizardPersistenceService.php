<?php

namespace App\Services\Delivery;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\FedEx\Operations\FedExOperationGuard;
use App\Services\Carriers\FedEx\Support\FedExCheckoutCapabilityService;
use App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog;
use App\Services\Inventory\DefaultLocationService;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryWizardPersistenceService
{
    public function __construct(
        private readonly CarrierOriginReadinessService $originReadiness,
        private readonly DeliveryAreaInputNormalizer $areaNormalizer,
        private readonly DeliveryOptionInputNormalizer $optionNormalizer,
        private readonly ManualDeliveryProviderResolver $manualProviderResolver,
        private readonly FedExOperationGuard $fedExGuard,
    ) {}

    public function saveShipFrom(Request $request, Store $store, ?User $actor = null): Location
    {
        $locationId = $request->integer('location_id');
        $existing = $locationId > 0
            ? $store->locations()->whereKey($locationId)->first()
            : null;

        abort_unless($locationId <= 0 || $existing !== null, 404);

        $validated = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(Location::TYPES)],
            'address_line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'country_code' => ['required', 'string', 'max:8'],
            'fulfills_online_orders' => ['nullable', 'boolean'],
        ]);

        $country = $this->originReadiness->normalizeCountryCode(trim((string) $validated['country_code']));
        if ($country === null || in_array($country, ['UN', 'XX', 'ZZ'], true)) {
            throw ValidationException::withMessages([
                'country_code' => 'Choose a valid country.',
            ]);
        }

        $state = filled($validated['state'] ?? null)
            ? $this->normalizeStateCode((string) $validated['state'], $country)
            : null;

        $payload = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'address_line1' => $validated['address_line1'],
            'city' => $validated['city'],
            'state' => $state,
            'postal_code' => $validated['postal_code'] ?? null,
            'country_code' => $country,
            'fulfills_online_orders' => $request->boolean('fulfills_online_orders', true),
            'updated_by' => $actor?->id,
        ];

        if ($existing !== null) {
            $existing->update($payload);
            $location = $existing->fresh();
        } else {
            $location = $store->locations()->create([
                ...$payload,
                'is_default' => false,
                'is_active' => true,
                'created_by' => $actor?->id,
            ]);

            if ($store->locations()->where('is_default', true)->doesntExist()) {
                app(DefaultLocationService::class)->makeDefault($location, $actor);
            }
        }

        if ($location->fulfills_online_orders
            && filled($location->address_line1)
            && filled($location->city)
            && filled($location->country_code)) {
            app(DefaultLocationService::class)->makeDefault($location, $actor);
        }

        return $location;
    }

    public function saveDeliveryArea(Request $request, Store $store): ShippingZone
    {
        $zoneId = $request->integer('shipping_zone_id');
        $existing = $zoneId > 0
            ? $store->shippingZones()->whereKey($zoneId)->first()
            : null;

        abort_unless($zoneId <= 0 || $existing !== null, 404);

        if ($existing !== null && $this->isLegacyZone($existing)) {
            throw ValidationException::withMessages([
                'shipping_zone_id' => 'This delivery area uses advanced multi-country settings. Open advanced delivery settings to edit it.',
            ]);
        }

        $validated = $request->validate([
            'shipping_zone_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'country_code' => ['required', 'string', 'max:8'],
            'region_codes' => ['nullable', 'array'],
            'region_codes.*' => ['nullable', 'string', 'max:32'],
            'postal_rules_json' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $request->merge(['zone_editor_mode' => 'simple']);
        $coverage = $this->areaNormalizer->normalizeFromRequest($request);

        $attributes = [
            'name' => $validated['name'],
            'countries' => $coverage['countries'],
            'regions' => $coverage['regions'],
            'postal_patterns' => $coverage['postal_patterns'],
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($existing !== null) {
            $existing->update([
                ...$attributes,
                'sort_order' => $existing->sort_order,
            ]);

            return $existing->fresh();
        }

        return $store->shippingZones()->create([
            ...$attributes,
            'sort_order' => 0,
        ]);
    }

    public function saveDeliveryOption(Request $request, Store $store, ?User $actor = null): ShippingMethod
    {
        $mode = (string) $request->input('checkout_shipping_mode', 'fixed');
        if (! in_array($mode, ['fedex_live', 'fixed', 'both'], true)) {
            $mode = 'fixed';
        }

        if (in_array($mode, ['fedex_live', 'both'], true)) {
            $platformOn = app(FedExCheckoutCapabilityService::class)
                ->platformCheckoutRatesEnabled();
            if (! $platformOn) {
                throw ValidationException::withMessages([
                    'checkout_shipping_mode' => 'FedEx checkout rates are not currently available on this platform. Use fixed or free shipping, or try again later.',
                ]);
            }

            // Validate fallback before mutating FedEx methods so Step 3 stays atomic.
            $this->validateCheckoutWeightFallback($request);

            return DB::transaction(function () use ($request, $store, $actor, $mode): ShippingMethod {
                $method = $this->saveFedExAwareCheckoutOptions($request, $store, $actor, $mode);
                $this->persistCheckoutWeightFallback($request, $store);

                return $method;
            });
        }

        return $this->saveFixedDeliveryOption($request, $store, $actor);
    }

    private function validateCheckoutWeightFallback(Request $request): void
    {
        if (! $request->exists('fallback_item_weight')) {
            return;
        }

        $raw = $request->input('fallback_item_weight');
        if ($raw === null || $raw === '') {
            return;
        }

        $request->validate([
            'fallback_item_weight' => ['nullable', 'numeric', 'gt:0', 'max:'.StoreShippingPreferences::MAX_ITEM_WEIGHT],
        ]);
    }

    private function persistCheckoutWeightFallback(Request $request, Store $store): void
    {
        if (! $request->exists('fallback_item_weight')) {
            return;
        }

        $raw = $request->input('fallback_item_weight');
        if ($raw === null || $raw === '') {
            return;
        }

        app(StoreShippingPreferences::class)->update($store, [
            'fallback_item_weight' => $raw,
        ]);
    }

    private function saveFixedDeliveryOption(Request $request, Store $store, ?User $actor): ShippingMethod
    {
        $methodId = $request->integer('shipping_method_id');
        $existing = $methodId > 0
            ? $store->shippingMethods()->whereKey($methodId)->first()
            : null;

        abort_unless($methodId <= 0 || $existing !== null, 404);

        if ($existing !== null && $existing->isFedExLiveRateMethod()) {
            throw ValidationException::withMessages([
                'checkout_shipping_mode' => 'This option uses FedEx live rates. Choose FedEx live rates (or FedEx + fallback) to edit it, or create a new fixed option.',
            ]);
        }

        $validated = $request->validate([
            'shipping_method_id' => ['nullable', 'integer'],
            'shipping_zone_id' => [
                'required',
                'integer',
                Rule::exists('shipping_zones', 'id')->where('store_id', $store->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'delivery_speed_label' => ['nullable', 'string', 'max:120'],
            'delivery_price_mode' => ['required', Rule::in(['fixed', 'free', 'free_over'])],
            'flat_rate' => ['nullable', 'numeric', 'min:0'],
            'free_over_amount' => ['nullable', 'numeric', 'min:0'],
            'estimated_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'estimated_max_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'available_to_customers' => ['nullable', 'boolean'],
            'resolve_flag_mismatch' => ['nullable', Rule::in(['available', 'keep'])],
        ]);

        $validated = $this->optionNormalizer->applyPricingMode($validated['delivery_price_mode'] ?? 'fixed', $validated);
        $this->optionNormalizer->assertValidPricingAndDays($validated['delivery_price_mode'] ?? 'fixed', $validated);
        $validated = $this->optionNormalizer->applySimpleAvailability($request, $validated, $existing);

        $carrierAccountId = $existing?->carrier_account_id;
        if ($carrierAccountId === null && empty($validated['carrier_account_id'])) {
            $carrierAccountId = $this->manualProviderResolver->resolveForStore($store, $actor)->id;
        }

        $attributes = [
            'shipping_zone_id' => (int) $validated['shipping_zone_id'],
            'name' => $validated['name'],
            'delivery_speed_label' => $validated['delivery_speed_label'] ?? null,
            'rate_type' => $validated['rate_type'],
            'flat_rate' => $validated['flat_rate'] ?? 0,
            'free_over_amount' => $validated['free_over_amount'] ?? null,
            'estimated_min_days' => $validated['estimated_min_days'] ?? null,
            'estimated_max_days' => $validated['estimated_max_days'] ?? null,
            'enabled_for_checkout' => (bool) ($validated['enabled_for_checkout'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'carrier_account_id' => $carrierAccountId,
            'carrier_service_code' => null,
            'carrier_service_name' => null,
        ];

        if ($existing !== null) {
            $existing->update($this->optionNormalizer->mergePreservedMethodFields($existing, $attributes));

            return $existing->fresh();
        }

        return $store->shippingMethods()->create([
            ...$attributes,
            'code' => $this->optionNormalizer->uniqueMethodCode($store->id, $validated['name']),
            'sort_order' => 0,
        ]);
    }

    private function saveFedExAwareCheckoutOptions(
        Request $request,
        Store $store,
        ?User $actor,
        string $mode,
    ): ShippingMethod {
        $account = $this->fedExGuard->resolveActiveModelAAccount($store);
        if (! $account instanceof CarrierAccount) {
            throw ValidationException::withMessages([
                'checkout_shipping_mode' => 'Connect FedEx before offering live FedEx rates at checkout.',
            ]);
        }

        $allowedCodes = FedExCheckoutServiceCatalog::codes();
        $validated = $request->validate([
            'shipping_zone_id' => [
                'required',
                'integer',
                Rule::exists('shipping_zones', 'id')->where('store_id', $store->id),
            ],
            'fedex_services' => ['required', 'array', 'min:1'],
            'fedex_services.*' => ['string', Rule::in($allowedCodes)],
            'available_to_customers' => ['nullable', 'boolean'],
            'fallback_name' => ['nullable', 'string', 'max:120'],
            'delivery_price_mode' => ['nullable', Rule::in(['fixed', 'free', 'free_over'])],
            'flat_rate' => ['nullable', 'numeric', 'min:0'],
            'free_over_amount' => ['nullable', 'numeric', 'min:0'],
            'estimated_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'estimated_max_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $zoneId = (int) $validated['shipping_zone_id'];
        $selectedServices = array_values(array_unique(array_map('strtoupper', $validated['fedex_services'])));
        $available = $request->boolean('available_to_customers', true);

        $primary = DB::transaction(function () use (
            $store,
            $actor,
            $account,
            $zoneId,
            $selectedServices,
            $available,
            $mode,
            $request,
            $validated,
        ): ShippingMethod {
            $existingFedEx = $store->shippingMethods()
                ->where('shipping_zone_id', $zoneId)
                ->where('rate_type', ShippingMethod::RATE_CARRIER_CALCULATED_LATER)
                ->where('carrier_account_id', $account->id)
                ->get();

            $keptIds = [];
            $primary = null;
            $sort = 0;

            foreach ($selectedServices as $code) {
                $name = FedExCheckoutServiceCatalog::nameFor($code);
                $match = $existingFedEx->first(fn (ShippingMethod $m): bool => strtoupper((string) $m->carrier_service_code) === $code);

                $payload = [
                    'shipping_zone_id' => $zoneId,
                    'carrier_account_id' => $account->id,
                    'carrier_service_code' => $code,
                    'carrier_service_name' => $name,
                    'name' => $name,
                    'delivery_speed_label' => $name,
                    'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
                    'flat_rate' => 0,
                    'free_over_amount' => null,
                    'enabled_for_checkout' => $available,
                    'is_active' => $available,
                    'sort_order' => $sort++,
                ];

                if ($match) {
                    $match->update($payload);
                    $method = $match->fresh();
                } else {
                    $method = $store->shippingMethods()->create([
                        ...$payload,
                        'code' => $this->optionNormalizer->uniqueMethodCode($store->id, $name),
                    ]);
                }

                $keptIds[] = $method->id;
                $primary ??= $method;
            }

            foreach ($existingFedEx as $stale) {
                if (! in_array($stale->id, $keptIds, true)) {
                    $stale->forceFill([
                        'is_active' => false,
                        'enabled_for_checkout' => false,
                    ])->save();
                }
            }

            if ($mode === 'both') {
                $this->upsertManualFallback($request, $store, $actor, $zoneId, $validated, $available);
            }

            $this->enableFedExCheckoutCapabilities($account);

            return $primary ?? $existingFedEx->first() ?? throw ValidationException::withMessages([
                'fedex_services' => 'Select at least one FedEx service.',
            ]);
        });

        return $primary;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function upsertManualFallback(
        Request $request,
        Store $store,
        ?User $actor,
        int $zoneId,
        array $validated,
        bool $available,
    ): ShippingMethod {
        $priceMode = (string) ($validated['delivery_price_mode'] ?? 'fixed');
        $pricing = $this->optionNormalizer->applyPricingMode($priceMode, [
            'flat_rate' => $validated['flat_rate'] ?? 5,
            'free_over_amount' => $validated['free_over_amount'] ?? null,
        ]);
        $this->optionNormalizer->assertValidPricingAndDays($priceMode, array_merge($validated, $pricing));

        $manualAccountId = $this->manualProviderResolver->resolveForStore($store, $actor)->id;
        $name = filled($validated['fallback_name'] ?? null)
            ? (string) $validated['fallback_name']
            : 'Standard delivery';

        $existing = $store->shippingMethods()
            ->where('shipping_zone_id', $zoneId)
            ->whereIn('rate_type', [ShippingMethod::RATE_FLAT, ShippingMethod::RATE_FREE, ShippingMethod::RATE_MANUAL])
            ->where(function ($query) use ($manualAccountId): void {
                $query->where('carrier_account_id', $manualAccountId)
                    ->orWhereNull('carrier_account_id');
            })
            ->whereNull('carrier_service_code')
            ->orderBy('id')
            ->first();

        $payload = [
            'shipping_zone_id' => $zoneId,
            'carrier_account_id' => $manualAccountId,
            'carrier_service_code' => null,
            'carrier_service_name' => null,
            'name' => $name,
            'delivery_speed_label' => $name,
            'rate_type' => $pricing['rate_type'],
            'flat_rate' => $pricing['flat_rate'] ?? 0,
            'free_over_amount' => $pricing['free_over_amount'] ?? null,
            'estimated_min_days' => $validated['estimated_min_days'] ?? null,
            'estimated_max_days' => $validated['estimated_max_days'] ?? null,
            'enabled_for_checkout' => $available,
            'is_active' => $available,
            'sort_order' => 100,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return $store->shippingMethods()->create([
            ...$payload,
            'code' => $this->optionNormalizer->uniqueMethodCode($store->id, $name),
        ]);
    }

    private function enableFedExCheckoutCapabilities(CarrierAccount $account): void
    {
        app(FedExCheckoutCapabilityService::class)
            ->enableAccountCheckoutRatesIfAllowed($account);
    }

    public function isLegacyZone(ShippingZone $zone): bool
    {
        $countries = collect($zone->countries)->filter()->values();

        return $countries->count() > 1;
    }

    private function normalizeStateCode(string $state, string $countryCode): string
    {
        $token = strtoupper(trim($state));
        if ($token === '') {
            return '';
        }

        $regions = TaxCountryCatalog::regionsFor($countryCode);
        foreach ($regions as $code => $label) {
            if ($token === strtoupper((string) $code) || $token === strtoupper((string) $label)) {
                return (string) $code;
            }
        }

        return $token;
    }
}
