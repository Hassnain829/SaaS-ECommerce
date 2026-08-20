<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingPackagePreset;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Delivery\DeliveryAreaInputNormalizer;
use App\Services\Delivery\DeliveryOptionInputNormalizer;
use App\Services\Delivery\DeliverySetupStatusService;
use App\Services\Delivery\ManualDeliveryProviderResolver;
use App\Services\Delivery\StoreShippingPreferences;
use App\Services\SecurityLogRecorder;
use App\Services\Tax\TaxConfigurationService;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShippingSettingsController extends Controller
{
    public function index(
        Request $request,
        FedExConfig $fedExConfig,
        USPSConfig $uspsConfig,
        CarrierOriginReadinessService $originReadiness,
        DeliverySetupStatusService $deliverySetupStatus,
        TaxConfigurationService $taxConfiguration,
    ): View|RedirectResponse {
        $store = $request->attributes->get('currentStore');
        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $locations = $store->locations()
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $originReadinessByLocationId = $locations
            ->mapWithKeys(fn (Location $location): array => [
                $location->id => $originReadiness->assess($location, CarrierOriginReadinessService::CARRIER_USPS),
            ])
            ->all();

        $hasCarrierReadyOrigin = collect($originReadinessByLocationId)
            ->contains(fn ($readiness): bool => $readiness->ready);

        $carrierAccounts = $store->carrierAccounts()
            ->with(['carrier', 'shippingMethods', 'defaultOriginLocation'])
            ->orderByDesc('status')
            ->orderBy('display_name')
            ->get();

        $shippingZones = $store->shippingZones()
            ->with('shippingMethods.carrierAccount.carrier')
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $shippingMethods = $store->shippingMethods()
            ->with(['shippingZone', 'carrierAccount.carrier'])
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $packagePresets = $store->shippingPackagePresets()
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $shippingPreferences = app(StoreShippingPreferences::class)->get($store);

        $taxSetting = $taxConfiguration->settingsForStore($store);

        $deliverySetup = $deliverySetupStatus->assess(
            $store,
            $locations,
            $shippingZones,
            $shippingMethods,
            $carrierAccounts,
            $taxSetting,
        );

        $fedExAccounts = $store->carrierAccounts()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->with('carrier')
            ->orderByDesc('updated_at')
            ->get();

        return view('user_view.shippingAutomation', [
            'selectedStore' => $store,
            'carriers' => Carrier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'carrierAccounts' => $carrierAccounts,
            'deliverySetup' => $deliverySetup,
            'taxSetting' => $taxSetting,
            'fedExCarrier' => Carrier::query()->where('code', 'fedex')->first(),
            'fedExAccounts' => $fedExAccounts,
            'fedExPlatformConfigured' => $fedExConfig->isConfigured(),
            'fedExEnabled' => $fedExConfig->isEnabled(),
            'fedExConfig' => $fedExConfig,
            'fedExRegistrationPath' => $fedExConfig->accountRegistrationPath(CarrierAccount::ENVIRONMENT_SANDBOX),
            'fedExRegistrationResidentialMode' => $fedExConfig->accountRegistrationResidentialMode(),
            'fedExSandboxPlatformFallbackAllowed' => $fedExConfig->allowsSandboxPlatformFallback(),
            'uspsCarrier' => Carrier::query()->where('code', 'usps')->first(),
            'uspsMerchantAccounts' => $store->carrierAccounts()
                ->where('provider', CarrierAccount::PROVIDER_USPS)
                ->where('connection_mode', CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER)
                ->with(['carrier', 'defaultOriginLocation'])
                ->orderByDesc('updated_at')
                ->get()
                ->reject(fn (CarrierAccount $account): bool => $account->usps_authorization_status === CarrierAccount::USPS_AUTH_DISABLED),
            'uspsPlatformTestingAccounts' => $store->carrierAccounts()
                ->where('provider', CarrierAccount::PROVIDER_USPS)
                ->where('connection_mode', CarrierAccount::CONNECTION_MODE_USPS_PLATFORM)
                ->with('carrier')
                ->orderByDesc('updated_at')
                ->get(),
            'uspsMerchantConnectionEnabled' => $uspsConfig->merchantConnectionEnabled() && $uspsConfig->isEnabled(),
            'uspsApiEvents' => $store->carrierApiEvents()
                ->where('provider', CarrierAccount::PROVIDER_USPS)
                ->latest('id')
                ->limit(8)
                ->get(),
            'uspsPlatformConfigured' => $uspsConfig->isConfigured(),
            'uspsEnabled' => $uspsConfig->isEnabled(),
            'uspsBaseUrl' => $uspsConfig->baseUrl(),
            'uspsOAuthPath' => $uspsConfig->oauthPath(),
            'uspsLabelsEnabled' => $uspsConfig->labelsEnabled(),
            'uspsRecentQuotes' => $store->carrierRateQuotes()
                ->where('provider', CarrierAccount::PROVIDER_USPS)
                ->latest('id')
                ->limit(5)
                ->get(),
            'uspsStepDiagnostics' => $this->uspsLatestStepDiagnostics($store),
            'shippingZones' => $shippingZones,
            'shippingMethods' => $shippingMethods,
            'packagePresets' => $packagePresets,
            'shippingPreferences' => $shippingPreferences,
            'locations' => $locations,
            'originReadinessByLocationId' => $originReadinessByLocationId,
            'hasCarrierReadyOrigin' => $hasCarrierReadyOrigin,
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
            'connectionTypes' => CarrierAccount::CONNECTION_TYPES,
            'carrierAccountStatuses' => CarrierAccount::STATUSES,
            'rateTypes' => array_values(array_filter(
                ShippingMethod::RATE_TYPES,
                fn (string $type): bool => $type !== ShippingMethod::RATE_CARRIER_CALCULATED_LATER
            )),
            'countries' => TaxCountryCatalog::all(),
        ]);
    }

    /**
     * Legacy route — raw carrier account creation was removed from merchant UI in Phase 6C-1C-FIX.
     * Redirect merchants to the guided carrier connection wizard.
     */
    public function storeCarrierAccount(Request $request): RedirectResponse
    {
        abort_unless($request->attributes->get('currentStore'), 404);

        return redirect()
            ->route('shipping.carriers.connect.index')
            ->with('success', 'Carrier accounts are now managed through the guided setup.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateCarrierAccount(Request $request, CarrierAccount $carrierAccount, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);

        $validated = $request->validate([
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'display_name' => ['required', 'string', 'max:120'],
            'connection_type' => ['required', Rule::in(CarrierAccount::CONNECTION_TYPES)],
            'status' => ['required', Rule::in(CarrierAccount::STATUSES)],
            'supported_countries' => ['nullable'],
            'enabled_for_checkout' => ['nullable', 'boolean'],
        ]);

        $carrierAccount->update([
            ...$validated,
            'supported_countries' => $this->listFromInput($validated['supported_countries'] ?? null),
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_account_updated',
            store: $store,
            metadata: ['carrier_account_id' => $carrierAccount->id, 'display_name' => $carrierAccount->display_name]
        );

        return back()
            ->with('success', 'Carrier account updated.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function destroyCarrierAccount(Request $request, CarrierAccount $carrierAccount, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless(! $carrierAccount->usesFedExIntegratorProvider(), 422);

        $carrierAccount->delete();

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_account_deleted',
            store: $store,
            metadata: ['carrier_account_id' => $carrierAccount->id, 'display_name' => $carrierAccount->display_name]
        );

        return back()
            ->with('success', 'Carrier account removed.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function disableCarrierAccount(
        Request $request,
        CarrierAccount $carrierAccount,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless(! $carrierAccount->usesFedExIntegratorProvider(), 422);

        $carrierAccount->markDisabled();

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_account_disabled',
            store: $store,
            metadata: ['carrier_account_id' => $carrierAccount->id, 'display_name' => $carrierAccount->display_name]
        );

        return back()
            ->with('success', 'Carrier account disabled.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function storeZone(Request $request, SecurityLogRecorder $securityLogRecorder, DeliveryAreaInputNormalizer $areaNormalizer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $this->validateZone($request);
        $coverage = $areaNormalizer->normalizeFromRequest($request);

        $zone = $store->shippingZones()->create([
            'name' => $validated['name'],
            'countries' => $coverage['countries'],
            'regions' => $coverage['regions'],
            'postal_patterns' => $coverage['postal_patterns'],
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.zone_created',
            store: $store,
            metadata: ['shipping_zone_id' => $zone->id, 'name' => $zone->name]
        );

        return $this->zoneSavedRedirect('Delivery area added.', $areaNormalizer);
    }

    public function updateZone(Request $request, ShippingZone $shippingZone, SecurityLogRecorder $securityLogRecorder, DeliveryAreaInputNormalizer $areaNormalizer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingZone->store_id === (int) $store->id, 404);

        $validated = $this->validateZone($request);
        $coverage = $areaNormalizer->normalizeFromRequest($request);

        $shippingZone->update([
            'name' => $validated['name'],
            'countries' => $coverage['countries'],
            'regions' => $coverage['regions'],
            'postal_patterns' => $coverage['postal_patterns'],
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.zone_updated',
            store: $store,
            metadata: ['shipping_zone_id' => $shippingZone->id, 'name' => $shippingZone->name]
        );

        return $this->zoneSavedRedirect('Delivery area updated.', $areaNormalizer);
    }

    private function zoneSavedRedirect(string $message, DeliveryAreaInputNormalizer $areaNormalizer): RedirectResponse
    {
        $warnings = $areaNormalizer->consumeCountryWarnings();
        if ($warnings !== []) {
            $message .= ' '.implode(' ', $warnings);
        }

        return back()
            ->with('success', $message)
            ->with('success_title', 'Shipping & delivery');
    }

    public function destroyZone(Request $request, ShippingZone $shippingZone, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingZone->store_id === (int) $store->id, 404);

        $removedMethods = 0;

        DB::transaction(function () use ($shippingZone, &$removedMethods): void {
            $removedMethods = $shippingZone->shippingMethods()->count();
            $shippingZone->shippingMethods()->each(function (ShippingMethod $method): void {
                $method->delete();
            });
            $shippingZone->delete();
        });

        $securityLogRecorder->record(
            $request,
            'shipping.zone_deleted',
            store: $store,
            metadata: [
                'shipping_zone_id' => $shippingZone->id,
                'name' => $shippingZone->name,
                'removed_method_count' => $removedMethods,
            ]
        );

        $message = $removedMethods > 0
            ? 'Delivery area removed, including '.$removedMethods.' checkout '.($removedMethods === 1 ? 'option' : 'options').'.'
            : 'Delivery area removed.';

        return back()
            ->with('success', $message)
            ->with('success_title', 'Shipping & delivery');
    }

    public function storeMethod(Request $request, SecurityLogRecorder $securityLogRecorder, ManualDeliveryProviderResolver $manualProviderResolver, DeliveryOptionInputNormalizer $optionNormalizer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $this->validateMethod($request, $store->id);
        $this->assertAdvancedMethodNotFedExLive($store, $validated);

        $validated = $optionNormalizer->applyPricingMode($request->input('delivery_price_mode'), $validated);
        $optionNormalizer->assertValidPricingAndDays((string) $request->input('delivery_price_mode', 'fixed'), $validated);
        $validated = $optionNormalizer->applyAdvancedAvailability($request, $validated, isCreate: true);
        $validated['carrier_account_id'] = $this->resolveMethodCarrierAccountId(
            $store,
            $validated,
            $manualProviderResolver,
            $request->user(),
        );

        $method = $store->shippingMethods()->create([
            ...$validated,
            'code' => $optionNormalizer->uniqueMethodCode($store->id, $validated['name']),
            'carrier_account_id' => $validated['carrier_account_id'],
            'flat_rate' => $validated['flat_rate'] ?? 0,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'enabled_for_checkout' => (bool) ($validated['enabled_for_checkout'] ?? true),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.method_created',
            store: $store,
            metadata: ['shipping_method_id' => $method->id, 'name' => $method->name]
        );

        return back()
            ->with('success', 'Delivery option added.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateMethod(Request $request, ShippingMethod $shippingMethod, SecurityLogRecorder $securityLogRecorder, ManualDeliveryProviderResolver $manualProviderResolver, DeliveryOptionInputNormalizer $optionNormalizer): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingMethod->store_id === (int) $store->id, 404);

        if ($shippingMethod->isFedExLiveRateMethod()) {
            return redirect()
                ->route('settings.delivery.setup.delivery-option')
                ->withErrors([
                    'rate_type' => 'Manage FedEx live services from Checkout Shipping.',
                ]);
        }

        $validated = $this->validateMethod($request, $store->id);
        $this->assertAdvancedMethodNotFedExLive($store, $validated);

        $validated = $optionNormalizer->applyPricingMode($request->input('delivery_price_mode'), $validated);
        $optionNormalizer->assertValidPricingAndDays((string) $request->input('delivery_price_mode', 'fixed'), $validated);
        $validated = $optionNormalizer->applyAdvancedAvailability($request, $validated, isCreate: false, existing: $shippingMethod);
        $validated['carrier_account_id'] = $this->resolveMethodCarrierAccountId(
            $store,
            $validated,
            $manualProviderResolver,
            $request->user(),
            $shippingMethod->carrier_account_id,
        );

        $shippingMethod->update($optionNormalizer->mergePreservedMethodFields($shippingMethod, [
            ...$validated,
            'carrier_account_id' => $validated['carrier_account_id'],
            'flat_rate' => $validated['flat_rate'] ?? 0,
            'sort_order' => (int) ($validated['sort_order'] ?? $shippingMethod->sort_order ?? 0),
            'enabled_for_checkout' => (bool) ($validated['enabled_for_checkout'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]));

        $securityLogRecorder->record(
            $request,
            'shipping.method_updated',
            store: $store,
            metadata: ['shipping_method_id' => $shippingMethod->id, 'name' => $shippingMethod->name]
        );

        return back()
            ->with('success', 'Delivery option updated.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function destroyMethod(Request $request, ShippingMethod $shippingMethod, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingMethod->store_id === (int) $store->id, 404);

        $shippingMethod->delete();

        $securityLogRecorder->record(
            $request,
            'shipping.method_deleted',
            store: $store,
            metadata: ['shipping_method_id' => $shippingMethod->id, 'name' => $shippingMethod->name]
        );

        return back()
            ->with('success', 'Delivery option removed.')
            ->with('success_title', 'Shipping & delivery');
    }

    /**
     * Soft-delete checkout options that no longer have a usable delivery area.
     */
    public function cleanupOrphanMethods(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $orphans = $store->shippingMethods()
            ->with('shippingZone')
            ->get()
            ->filter(fn (ShippingMethod $method): bool => $method->isOrphanedFromArea());

        $removed = 0;
        foreach ($orphans as $method) {
            $method->delete();
            $removed++;
        }

        $securityLogRecorder->record(
            $request,
            'shipping.orphan_methods_cleaned',
            store: $store,
            metadata: ['removed_count' => $removed]
        );

        $message = $removed === 0
            ? 'No unused delivery options to remove.'
            : ($removed === 1
                ? 'Removed 1 unused delivery option.'
                : 'Removed '.$removed.' unused delivery options.');

        return back()
            ->with('success', $message)
            ->with('success_title', 'Shipping & delivery');
    }

    public function storePackagePreset(
        Request $request,
        SecurityLogRecorder $securityLogRecorder,
        StoreShippingPreferences $shippingPreferences,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $this->validatePackagePreset($request);
        $makeDefault = (bool) ($validated['is_default'] ?? false);

        $preset = DB::transaction(function () use ($store, $validated, $makeDefault, $shippingPreferences): ShippingPackagePreset {
            if ($makeDefault) {
                $store->shippingPackagePresets()->where('is_default', true)->update(['is_default' => false]);
            }

            $preset = $store->shippingPackagePresets()->create([
                ...$validated,
                'weight_unit' => strtoupper((string) ($validated['weight_unit'] ?? 'LB')),
                'dimension_unit' => strtoupper((string) ($validated['dimension_unit'] ?? 'IN')),
                'is_default' => $makeDefault,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            if ($makeDefault) {
                $shippingPreferences->update($store, ['default_package_preset_id' => $preset->id]);
            }

            return $preset;
        });

        $securityLogRecorder->record(
            $request,
            'shipping.package_preset_created',
            store: $store,
            metadata: ['shipping_package_preset_id' => $preset->id, 'name' => $preset->name]
        );

        return back()
            ->with('success', 'Package size saved.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updatePackagePreset(
        Request $request,
        ShippingPackagePreset $shippingPackagePreset,
        SecurityLogRecorder $securityLogRecorder,
        StoreShippingPreferences $shippingPreferences,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingPackagePreset->store_id === (int) $store->id, 404);

        $validated = $this->validatePackagePreset($request);
        $makeDefault = (bool) ($validated['is_default'] ?? false);

        DB::transaction(function () use ($store, $shippingPackagePreset, $validated, $makeDefault, $shippingPreferences): void {
            if ($makeDefault) {
                $store->shippingPackagePresets()
                    ->where('is_default', true)
                    ->where('id', '!=', $shippingPackagePreset->id)
                    ->update(['is_default' => false]);
            }

            $shippingPackagePreset->update([
                ...$validated,
                'weight_unit' => strtoupper((string) ($validated['weight_unit'] ?? 'LB')),
                'dimension_unit' => strtoupper((string) ($validated['dimension_unit'] ?? 'IN')),
                'is_default' => $makeDefault,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            if ($makeDefault) {
                $shippingPreferences->update($store, ['default_package_preset_id' => $shippingPackagePreset->id]);
            } elseif ((int) ($shippingPreferences->get($store)['default_package_preset_id'] ?? 0) === (int) $shippingPackagePreset->id) {
                $shippingPreferences->update($store, ['default_package_preset_id' => null]);
            }
        });

        $securityLogRecorder->record(
            $request,
            'shipping.package_preset_updated',
            store: $store,
            metadata: ['shipping_package_preset_id' => $shippingPackagePreset->id, 'name' => $shippingPackagePreset->name]
        );

        return back()
            ->with('success', 'Package size updated.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function destroyPackagePreset(
        Request $request,
        ShippingPackagePreset $shippingPackagePreset,
        SecurityLogRecorder $securityLogRecorder,
        StoreShippingPreferences $shippingPreferences,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingPackagePreset->store_id === (int) $store->id, 404);

        $presetId = (int) $shippingPackagePreset->id;
        $shippingPackagePreset->delete();

        if ((int) ($shippingPreferences->get($store)['default_package_preset_id'] ?? 0) === $presetId) {
            $shippingPreferences->update($store, ['default_package_preset_id' => null]);
        }

        $securityLogRecorder->record(
            $request,
            'shipping.package_preset_deleted',
            store: $store,
            metadata: ['shipping_package_preset_id' => $presetId, 'name' => $shippingPackagePreset->name]
        );

        return back()
            ->with('success', 'Package size removed.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function setDefaultPackagePreset(
        Request $request,
        ShippingPackagePreset $shippingPackagePreset,
        SecurityLogRecorder $securityLogRecorder,
        StoreShippingPreferences $shippingPreferences,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingPackagePreset->store_id === (int) $store->id, 404);

        DB::transaction(function () use ($store, $shippingPackagePreset, $shippingPreferences): void {
            $store->shippingPackagePresets()->where('is_default', true)->update(['is_default' => false]);
            $shippingPackagePreset->update([
                'is_default' => true,
                'is_active' => true,
            ]);
            $shippingPreferences->update($store, ['default_package_preset_id' => $shippingPackagePreset->id]);
        });

        $securityLogRecorder->record(
            $request,
            'shipping.package_preset_default_set',
            store: $store,
            metadata: ['shipping_package_preset_id' => $shippingPackagePreset->id, 'name' => $shippingPackagePreset->name]
        );

        return back()
            ->with('success', 'Default package size updated.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateShippingPreferences(
        Request $request,
        StoreShippingPreferences $shippingPreferences,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $request->validate([
            'default_label_format' => ['nullable', 'string', Rule::in(StoreShippingPreferences::LABEL_FORMATS)],
            'default_handoff_type' => ['nullable', 'string', Rule::in(StoreShippingPreferences::HANDOFF_TYPES)],
            'default_signature_option' => ['nullable', 'string', Rule::in(StoreShippingPreferences::SIGNATURE_OPTIONS)],
            'saturday_delivery_default' => ['nullable', 'boolean'],
        ]);

        $shippingPreferences->update($store, [
            'default_label_format' => $validated['default_label_format'] ?? 'PDF',
            'default_handoff_type' => $validated['default_handoff_type'] ?? StoreShippingPreferences::HANDOFF_USE_SCHEDULED_PICKUP,
            'default_signature_option' => $validated['default_signature_option'] ?? null,
            'saturday_delivery_default' => $request->boolean('saturday_delivery_default'),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.preferences_updated',
            store: $store,
            metadata: ['keys' => array_keys($validated)]
        );

        return back()
            ->with('success', 'Shipping defaults saved.')
            ->with('success_title', 'Shipping & delivery');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePackagePreset(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'weight_value' => ['nullable', 'numeric', 'min:0.01'],
            'weight_unit' => ['nullable', 'string', 'max:8'],
            'length' => ['required', 'numeric', 'min:0.01'],
            'width' => ['required', 'numeric', 'min:0.01'],
            'height' => ['required', 'numeric', 'min:0.01'],
            'dimension_unit' => ['nullable', 'string', 'max:8'],
            'package_type' => ['nullable', 'string', 'max:64'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateZone(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'zone_editor_mode' => ['nullable', Rule::in(['simple', 'legacy'])],
            'country_code' => ['nullable', 'string', 'max:8'],
            'region_codes' => ['nullable', 'array'],
            'region_codes.*' => ['nullable', 'string', 'max:32'],
            'postal_rules_json' => ['nullable', 'string'],
            'legacy_countries' => ['nullable'],
            'legacy_regions' => ['nullable'],
            'legacy_postal_patterns' => ['nullable'],
            'countries' => ['nullable'],
            'regions' => ['nullable'],
            'postal_patterns' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMethod(Request $request, int $storeId): array
    {
        return $request->validate([
            'shipping_zone_id' => [
                'required',
                'integer',
                Rule::exists('shipping_zones', 'id')->where('store_id', $storeId),
            ],
            'carrier_account_id' => [
                'nullable',
                'integer',
                Rule::exists('carrier_accounts', 'id')->where('store_id', $storeId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'delivery_speed_label' => ['nullable', 'string', 'max:120'],
            'delivery_price_mode' => ['nullable', Rule::in(['fixed', 'free', 'free_over'])],
            'rate_type' => ['nullable', Rule::in(ShippingMethod::RATE_TYPES)],
            'flat_rate' => ['nullable', 'numeric', 'min:0'],
            'free_over_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['nullable', 'numeric', 'min:0'],
            'estimated_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'estimated_max_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'available_to_customers' => ['nullable', 'boolean'],
            'enabled_for_checkout' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveMethodCarrierAccountId(
        Store $store,
        array $validated,
        ManualDeliveryProviderResolver $manualProviderResolver,
        ?\App\Models\User $actor,
        ?int $existingCarrierAccountId = null,
    ): ?int {
        if (! empty($validated['carrier_account_id'])) {
            $accountId = (int) $validated['carrier_account_id'];
            if (($validated['rate_type'] ?? null) === ShippingMethod::RATE_CARRIER_CALCULATED_LATER) {
                $account = \App\Models\CarrierAccount::query()
                    ->where('store_id', $store->id)
                    ->whereKey($accountId)
                    ->first();
                if (! $account || ! $account->isFedEx() || ! $account->usesFedExIntegratorProvider()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'carrier_account_id' => 'Live carrier pricing requires a connected FedEx account.',
                    ]);
                }
            }

            return $accountId;
        }

        if (($validated['rate_type'] ?? null) === ShippingMethod::RATE_CARRIER_CALCULATED_LATER) {
            if ($existingCarrierAccountId) {
                $existing = \App\Models\CarrierAccount::query()
                    ->where('store_id', $store->id)
                    ->whereKey($existingCarrierAccountId)
                    ->first();
                if ($existing && $existing->isFedEx() && $existing->usesFedExIntegratorProvider()) {
                    return $existingCarrierAccountId;
                }
            }

            throw \Illuminate\Validation\ValidationException::withMessages([
                'carrier_account_id' => 'Select a connected FedEx account for live carrier pricing.',
            ]);
        }

        return $manualProviderResolver->resolveForStore($store, $actor)->id;
    }

    /**
     * Advanced drawers may only manage flat/free/manual methods — not FedEx live rates.
     *
     * @param  array<string, mixed>  $validated
     */
    private function assertAdvancedMethodNotFedExLive(Store $store, array $validated): void
    {
        $rateType = (string) ($validated['rate_type'] ?? '');
        if ($rateType === ShippingMethod::RATE_CARRIER_CALCULATED_LATER) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'rate_type' => 'Manage FedEx live services from Checkout Shipping.',
            ]);
        }

        $accountId = (int) ($validated['carrier_account_id'] ?? 0);
        if ($accountId <= 0) {
            return;
        }

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->whereKey($accountId)
            ->first();

        if ($account && $account->isFedEx() && $account->usesFedExIntegratorProvider()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'carrier_account_id' => 'Manage FedEx live services from Checkout Shipping.',
            ]);
        }
    }

    /**
     * @return list<string>|null
     */
    private function listFromInput(mixed $value, bool $uppercase = false): ?array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        }

        $parts = collect($parts)
            ->map(fn ($part): string => trim((string) $part))
            ->filter()
            ->map(fn ($part): string => $uppercase ? strtoupper($part) : $part)
            ->unique()
            ->values()
            ->all();

        return $parts === [] ? null : $parts;
    }

    /**
     * @return array<int, array<string, array{status: string, endpoint: ?string, http_status: mixed, error_message: ?string}>>
     */
    private function uspsLatestStepDiagnostics(\App\Models\Store $store): array
    {
        $actions = [
            CarrierApiEvent::ACTION_OAUTH_TOKEN,
            CarrierApiEvent::ACTION_ADDRESS_VALIDATION,
            CarrierApiEvent::ACTION_DOMESTIC_RATE_QUOTE,
        ];

        $accountIds = $store->carrierAccounts()
            ->where('provider', CarrierAccount::PROVIDER_USPS)
            ->pluck('id');

        $diagnostics = [];

        foreach ($accountIds as $accountId) {
            foreach ($actions as $action) {
                $event = CarrierApiEvent::query()
                    ->where('store_id', $store->id)
                    ->where('carrier_account_id', $accountId)
                    ->where('action', $action)
                    ->latest('id')
                    ->first();

                if ($event === null) {
                    continue;
                }

                $diagnostics[$accountId][$action] = [
                    'status' => $event->status,
                    'endpoint' => data_get($event->request_summary, 'endpoint'),
                    'http_status' => data_get($event->response_summary, 'http_status'),
                    'error_message' => $event->error_message,
                ];
            }
        }

        return $diagnostics;
    }
}
