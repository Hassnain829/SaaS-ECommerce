<?php

namespace App\Services\Delivery;

use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Inventory\DefaultLocationService;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeliveryWizardPersistenceService
{
    public function __construct(
        private readonly CarrierOriginReadinessService $originReadiness,
        private readonly DeliveryAreaInputNormalizer $areaNormalizer,
        private readonly DeliveryOptionInputNormalizer $optionNormalizer,
        private readonly ManualDeliveryProviderResolver $manualProviderResolver,
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
        $methodId = $request->integer('shipping_method_id');
        $existing = $methodId > 0
            ? $store->shippingMethods()->whereKey($methodId)->first()
            : null;

        abort_unless($methodId <= 0 || $existing !== null, 404);

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

        $catalog = TaxCountryCatalog::regionsFor($countryCode);
        if (isset($catalog[$token])) {
            return $token;
        }

        foreach ($catalog as $code => $label) {
            if (strtoupper($label) === $token) {
                return $code;
            }
        }

        return $token;
    }
}
