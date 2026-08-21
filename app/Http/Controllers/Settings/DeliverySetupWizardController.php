<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\Carriers\FedEx\Operations\FedExOperationGuard;
use App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog;
use App\Services\Delivery\DeliveryAddressDiagnosticService;
use App\Services\Delivery\DeliveryAreaInputNormalizer;
use App\Services\Delivery\DeliverySetupLifecycleService;
use App\Services\Delivery\DeliverySetupStatusService;
use App\Services\Delivery\DeliveryWizardPersistenceService;
use App\Services\Tax\TaxConfigurationService;
use App\Support\StorePermission;
use App\Support\Tax\TaxCountryCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliverySetupWizardController extends Controller
{
    private const SESSION_LOCATION = 'delivery_wizard.location_id';

    private const SESSION_ZONE = 'delivery_wizard.zone_id';

    private const SESSION_METHOD = 'delivery_wizard.method_id';

    public function index(Request $request, DeliverySetupLifecycleService $lifecycle): RedirectResponse
    {
        $store = $this->store($request);

        if ($lifecycle->hasCompletedSetup($store)) {
            return redirect()->route('shippingAutomation');
        }

        $lifecycle->clearWizardSession($request);

        return redirect()->route($lifecycle->nextIncompleteSetupRouteName($store));
    }

    public function shipFrom(Request $request, TaxConfigurationService $taxConfiguration, DeliverySetupLifecycleService $lifecycle): View|RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        if ($redirect = $this->redirectCompletedSetupToHub($store, $lifecycle)) {
            return $redirect;
        }

        if ($request->isMethod('post')) {
            $location = app(DeliveryWizardPersistenceService::class)->saveShipFrom($request, $store, $request->user());
            $request->session()->put(self::SESSION_LOCATION, $location->id);

            return redirect()->route('settings.delivery.setup.deliver-to');
        }

        return view('user_view.delivery.setup.ship-from', $this->wizardContext($request, $store, $taxConfiguration, [
            'step' => 1,
            'locations' => $store->locations()->orderByDesc('is_default')->orderBy('name')->get(),
            'selectedLocation' => $this->selectedLocation($store, $request),
            'locationTypes' => Location::TYPES,
        ]));
    }

    public function deliverTo(Request $request, TaxConfigurationService $taxConfiguration, DeliveryAreaInputNormalizer $areaNormalizer, DeliverySetupLifecycleService $lifecycle): View|RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        if ($redirect = $this->redirectCompletedSetupToHub($store, $lifecycle)) {
            return $redirect;
        }

        if ($request->isMethod('post')) {
            $zone = app(DeliveryWizardPersistenceService::class)->saveDeliveryArea($request, $store);
            $request->session()->put(self::SESSION_ZONE, $zone->id);

            return redirect()->route('settings.delivery.setup.delivery-option');
        }

        $zones = $store->shippingZones()
            ->withCount('shippingMethods')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        $selectedZone = $this->selectedZone($store, $request);
        $zonePayload = $selectedZone ? $areaNormalizer->presentationFromZone($selectedZone) : null;
        $zoneCatalog = $zones->mapWithKeys(fn (ShippingZone $zone): array => [
            $zone->id => $areaNormalizer->presentationFromZone($zone),
        ])->all();

        return view('user_view.delivery.setup.deliver-to', $this->wizardContext($request, $store, $taxConfiguration, [
            'step' => 2,
            'shippingZones' => $zones,
            'selectedZone' => $selectedZone,
            'zonePayload' => $zonePayload,
            'zoneCatalog' => $zoneCatalog,
            'legacyZones' => $zones->filter(fn (ShippingZone $zone): bool => app(DeliveryWizardPersistenceService::class)->isLegacyZone($zone)),
        ]));
    }

    public function deliveryOption(Request $request, TaxConfigurationService $taxConfiguration, DeliverySetupLifecycleService $lifecycle): View|RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        if ($lifecycle->hasCompletedSetup($store)) {
            return redirect()->route('settings.delivery.checkout-options', $request->query());
        }

        if ($request->isMethod('post')) {
            $method = app(DeliveryWizardPersistenceService::class)->saveDeliveryOption($request, $store, $request->user());
            $request->session()->put(self::SESSION_METHOD, $method->id);

            return redirect()->route('settings.delivery.setup.review');
        }

        return $this->renderCheckoutShippingEditor($request, $store, $taxConfiguration, manageMode: false);
    }

    /**
     * Ongoing checkout-shipping editor for stores that already finished setup (no wizard chrome).
     */
    public function checkoutOptions(Request $request, TaxConfigurationService $taxConfiguration, DeliverySetupLifecycleService $lifecycle): View|RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        if (! $lifecycle->hasCompletedSetup($store)) {
            return redirect()->route('settings.delivery.setup.delivery-option', $request->query());
        }

        if ($request->isMethod('post')) {
            app(DeliveryWizardPersistenceService::class)->saveDeliveryOption($request, $store, $request->user());
            $lifecycle->clearWizardSession($request);

            return redirect()
                ->route('shippingAutomation')
                ->with('success', 'Checkout shipping updated.')
                ->with('success_title', 'Delivery');
        }

        return $this->renderCheckoutShippingEditor($request, $store, $taxConfiguration, manageMode: true);
    }

    private function renderCheckoutShippingEditor(
        Request $request,
        $store,
        TaxConfigurationService $taxConfiguration,
        bool $manageMode,
    ): View {
        $selectedZone = $this->selectedZone($store, $request);
        $selectedMethod = $this->selectedMethod($store, $request, $selectedZone);
        $priceMode = 'fixed';
        if ($selectedMethod !== null) {
            $priceMode = $selectedMethod->rate_type === ShippingMethod::RATE_FREE
                ? 'free'
                : ((float) ($selectedMethod->free_over_amount ?? 0) > 0 ? 'free_over' : 'fixed');
        }

        $methods = $store->shippingMethods()->with(['shippingZone', 'carrierAccount'])->orderBy('name')->get();
        $methodCatalog = $methods->mapWithKeys(function (ShippingMethod $method): array {
            $priceMode = $method->rate_type === ShippingMethod::RATE_FREE
                ? 'free'
                : ((float) ($method->free_over_amount ?? 0) > 0 ? 'free_over' : 'fixed');

            return [$method->id => [
                'name' => $method->name,
                'delivery_speed_label' => $method->delivery_speed_label,
                'shipping_zone_id' => $method->shipping_zone_id,
                'delivery_price_mode' => $priceMode,
                'flat_rate' => $method->flat_rate,
                'free_over_amount' => $method->free_over_amount,
                'estimated_min_days' => $method->estimated_min_days,
                'estimated_max_days' => $method->estimated_max_days,
                'available_to_customers' => (bool) $method->enabled_for_checkout,
            ]];
        })->all();

        $fedExAccount = app(FedExOperationGuard::class)->resolveActiveModelAAccount($store);
        $fedExConnected = $fedExAccount instanceof CarrierAccount;
        $zoneId = $selectedZone?->id;
        $fedExMethodsInZone = $methods->filter(function (ShippingMethod $method) use ($zoneId, $fedExAccount): bool {
            if (! $method->isFedExLiveRateMethod()) {
                return false;
            }
            if ($zoneId && (int) $method->shipping_zone_id !== (int) $zoneId) {
                return false;
            }
            if ($fedExAccount && (int) $method->carrier_account_id !== (int) $fedExAccount->id) {
                return false;
            }

            return true;
        });
        $manualInZone = $methods->first(function (ShippingMethod $method) use ($zoneId): bool {
            if ($method->isFedExLiveRateMethod()) {
                return false;
            }
            if ($zoneId && (int) $method->shipping_zone_id !== (int) $zoneId) {
                return false;
            }

            return in_array($method->rate_type, [
                ShippingMethod::RATE_FLAT,
                ShippingMethod::RATE_FREE,
                ShippingMethod::RATE_MANUAL,
            ], true);
        });

        $checkoutShippingMode = 'fixed';
        if ($fedExMethodsInZone->isNotEmpty() && $manualInZone) {
            $checkoutShippingMode = 'both';
        } elseif ($fedExMethodsInZone->isNotEmpty()) {
            $checkoutShippingMode = 'fedex_live';
        }

        $selectedFedExServices = $fedExMethodsInZone
            ->filter(fn (ShippingMethod $m): bool => $m->is_active || $m->enabled_for_checkout)
            ->pluck('carrier_service_code')
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->unique()
            ->values()
            ->all();
        if ($selectedFedExServices === []) {
            $selectedFedExServices = ['FEDEX_GROUND', 'GROUND_HOME_DELIVERY'];
        }

        // Prefer explicit method selection; otherwise use the zone's fixed/free option for the form.
        if ($manualInZone
            && $selectedMethod === null
            && ! $request->filled('shipping_method_id')
            && ! $request->session()->has(self::SESSION_METHOD)) {
            $selectedMethod = $manualInZone;
            $priceMode = $manualInZone->rate_type === ShippingMethod::RATE_FREE
                ? 'free'
                : ((float) ($manualInZone->free_over_amount ?? 0) > 0 ? 'free_over' : 'fixed');
        }

        return view('user_view.delivery.setup.delivery-option', $this->wizardContext($request, $store, $taxConfiguration, [
            'step' => $manageMode ? null : 3,
            'manageMode' => $manageMode,
            'shippingZones' => $store->shippingZones()->where('is_active', true)->orderBy('name')->get(),
            'shippingMethods' => $methods,
            'methodCatalog' => $methodCatalog,
            'selectedZone' => $selectedZone,
            'selectedMethod' => $selectedMethod,
            'priceMode' => $priceMode,
            'flagMismatch' => $selectedMethod !== null && $selectedMethod->is_active !== $selectedMethod->enabled_for_checkout,
            'fedExConnected' => $fedExConnected,
            'fedExAccount' => $fedExAccount,
            'fedExCheckoutRatesPlatformEnabled' => (bool) config('carriers.fedex.checkout_rates_enabled', false),
            'fedExServices' => FedExCheckoutServiceCatalog::services(),
            'checkoutShippingMode' => $checkoutShippingMode,
            'selectedFedExServices' => $selectedFedExServices,
        ]));
    }

    public function review(Request $request, DeliverySetupStatusService $deliverySetupStatus, TaxConfigurationService $taxConfiguration, DeliverySetupLifecycleService $lifecycle): View|RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        if ($redirect = $this->redirectCompletedSetupToHub($store, $lifecycle)) {
            return $redirect;
        }

        $locations = $store->locations()->orderByDesc('is_default')->orderBy('name')->get();
        $zones = $store->shippingZones()->orderByDesc('is_active')->orderBy('name')->get();
        $methods = $store->shippingMethods()->with('shippingZone')->orderBy('name')->get();
        $carrierAccounts = $store->carrierAccounts()->orderBy('display_name')->get();
        $taxSetting = $taxConfiguration->settingsForStore($store);

        $deliverySetup = $deliverySetupStatus->assess($store, $locations, $zones, $methods, $carrierAccounts, $taxSetting);

        $selectedLocation = $this->selectedLocation($store, $request)?->fresh();
        $selectedZone = $this->selectedZone($store, $request)?->fresh();
        $selectedMethod = $this->selectedMethod($store, $request, $this->selectedZone($store, $request))?->fresh(['shippingZone']);

        if ($selectedLocation !== null) {
            $deliverySetup['ship_from'] = $deliverySetupStatus->summarizeShipFromLocation($selectedLocation);
        }
        if ($selectedZone !== null) {
            $deliverySetup['delivery_areas'] = $deliverySetupStatus->summarizeDeliveryArea($selectedZone);
        }
        if ($selectedMethod !== null) {
            $deliverySetup['delivery_options'] = $deliverySetupStatus->summarizeDeliveryOption($selectedMethod);
        }

        $fedExAccount = app(FedExOperationGuard::class)->resolveActiveModelAAccount($store);
        $zoneMethods = $selectedZone
            ? $methods->where('shipping_zone_id', $selectedZone->id)
            : collect();
        $fedExZoneMethods = $zoneMethods->filter(fn (ShippingMethod $m): bool => $m->isFedExLiveRateMethod() && ($m->is_active || $m->enabled_for_checkout));
        $manualZoneMethod = $zoneMethods->first(fn (ShippingMethod $m): bool => ! $m->isFedExLiveRateMethod() && ($m->is_active || $m->enabled_for_checkout));

        $checkoutModeLabel = 'Fixed or free shipping';
        $checkoutModeDetail = $selectedMethod?->name ?? ($manualZoneMethod?->name ?? 'Not configured');
        if ($fedExZoneMethods->isNotEmpty() && $manualZoneMethod) {
            $checkoutModeLabel = 'FedEx live rates + fallback';
            $services = $fedExZoneMethods->pluck('carrier_service_name')->filter()->implode(', ');
            $checkoutModeDetail = ($services !== '' ? $services : 'FedEx services')
                .' · Fallback: '.$manualZoneMethod->name;
        } elseif ($fedExZoneMethods->isNotEmpty()) {
            $checkoutModeLabel = 'FedEx live rates';
            $checkoutModeDetail = $fedExZoneMethods->pluck('carrier_service_name')->filter()->implode(', ') ?: 'FedEx services configured';
        }

        $maskedAccount = null;
        if ($fedExAccount instanceof CarrierAccount) {
            $masked = $fedExAccount->maskedAccountNumber();
            $maskedAccount = $masked !== '' ? $masked : (string) ($fedExAccount->display_name ?? 'FedEx account');
        }

        return view('user_view.delivery.setup.review', $this->wizardContext($request, $store, $taxConfiguration, [
            'step' => 4,
            'deliverySetup' => $deliverySetup,
            'selectedLocation' => $selectedLocation,
            'selectedZone' => $selectedZone,
            'selectedMethod' => $selectedMethod,
            'checkoutModeLabel' => $checkoutModeLabel,
            'checkoutModeDetail' => $checkoutModeDetail,
            'fedExAccountMasked' => $maskedAccount,
            'fedExCheckoutRatesPlatformEnabled' => (bool) config('carriers.fedex.checkout_rates_enabled', false),
        ]));
    }

    public function finish(Request $request, DeliverySetupStatusService $deliverySetupStatus, TaxConfigurationService $taxConfiguration, DeliverySetupLifecycleService $lifecycle): RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        if ($lifecycle->hasCompletedSetup($store)) {
            return redirect()->route('shippingAutomation');
        }

        $deliverySetup = $deliverySetupStatus->assess(
            $store,
            $store->locations()->orderByDesc('is_default')->orderBy('name')->get(),
            $store->shippingZones()->orderByDesc('is_active')->orderBy('name')->get(),
            $store->shippingMethods()->with('shippingZone')->orderBy('name')->get(),
            $store->carrierAccounts()->orderBy('display_name')->get(),
            $taxConfiguration->settingsForStore($store),
        );

        if (! ($deliverySetup['is_ready'] ?? false)) {
            $detail = collect($deliverySetup['blocking_items'] ?? $deliverySetup['health_items'] ?? [])
                ->filter(fn (array $item): bool => ($item['severity'] ?? '') === 'error')
                ->pluck('message')
                ->filter()
                ->unique()
                ->take(3)
                ->implode(' ');

            return redirect()
                ->route('settings.delivery.setup.review')
                ->withErrors([
                    'delivery_setup' => $detail !== ''
                        ? $detail
                        : 'Finish delivery setup after ship-from, delivery area, and checkout-visible options are ready.',
                ]);
        }

        $lifecycle->markCompleted($store);

        $request->session()->forget([
            self::SESSION_LOCATION,
            self::SESSION_ZONE,
            self::SESSION_METHOD,
        ]);

        return redirect()
            ->route('shippingAutomation')
            ->with('success', 'Delivery setup is ready for platform checkout. Review your delivery hub for optional improvements.')
            ->with('success_title', 'Delivery setup');
    }

    public function testAddress(Request $request, DeliveryAddressDiagnosticService $diagnostic, TaxConfigurationService $taxConfiguration): View
    {
        $store = $this->store($request);
        abort_unless($request->user()?->hasStorePermission($store, StorePermission::SETTINGS_VIEW) ?? false, 403);

        $result = null;
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'country_code' => ['required', 'string', 'max:8'],
                'region_code' => ['nullable', 'string', 'max:32'],
                'postal_code' => ['nullable', 'string', 'max:40'],
                'order_subtotal' => ['nullable', 'numeric', 'min:0'],
                'package_preset_id' => ['nullable', 'integer'],
                'package_weight' => ['nullable', 'numeric', 'min:0.01'],
                'package_weight_unit' => ['nullable', 'string', 'max:8'],
                'package_length' => ['nullable', 'numeric', 'min:0.01'],
                'package_width' => ['nullable', 'numeric', 'min:0.01'],
                'package_height' => ['nullable', 'numeric', 'min:0.01'],
                'package_dimension_unit' => ['nullable', 'string', 'max:8'],
            ]);

            if (! empty($validated['package_preset_id'])) {
                $presetExists = $store->shippingPackagePresets()
                    ->whereKey((int) $validated['package_preset_id'])
                    ->exists();
                abort_unless($presetExists, 404);
            }

            $result = $diagnostic->diagnose(
                $store,
                $validated['country_code'],
                $validated['region_code'] ?? null,
                $validated['postal_code'] ?? null,
                (float) ($validated['order_subtotal'] ?? 0),
                [
                    'package_preset_id' => isset($validated['package_preset_id']) ? (int) $validated['package_preset_id'] : null,
                    'weight' => isset($validated['package_weight']) ? (float) $validated['package_weight'] : null,
                    'weight_unit' => $validated['package_weight_unit'] ?? null,
                    'length' => isset($validated['package_length']) ? (float) $validated['package_length'] : null,
                    'width' => isset($validated['package_width']) ? (float) $validated['package_width'] : null,
                    'height' => isset($validated['package_height']) ? (float) $validated['package_height'] : null,
                    'dimension_unit' => $validated['package_dimension_unit'] ?? null,
                ],
            );
        }

        $packagePresets = $store->shippingPackagePresets()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('user_view.delivery.test-address', [
            'selectedStore' => $store,
            'countries' => TaxCountryCatalog::all(),
            'result' => $result,
            'packagePresets' => $packagePresets,
            'input' => $request->old() ?: $request->only([
                'country_code',
                'region_code',
                'postal_code',
                'order_subtotal',
                'package_preset_id',
                'package_weight',
                'package_weight_unit',
                'package_length',
                'package_width',
                'package_height',
                'package_dimension_unit',
            ]),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function wizardContext(Request $request, $store, TaxConfigurationService $taxConfiguration, array $extra): array
    {
        return array_merge([
            'selectedStore' => $store,
            'countries' => TaxCountryCatalog::all(),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
        ], $extra);
    }

    private function store(Request $request)
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return $store;
    }

    private function authorizeManage(Request $request, $store): void
    {
        abort_unless($request->user()?->canManageSettings($store) ?? false, 403);
    }

    private function redirectCompletedSetupToHub($store, DeliverySetupLifecycleService $lifecycle): ?RedirectResponse
    {
        if (! $lifecycle->hasCompletedSetup($store)) {
            return null;
        }

        return redirect()->route('shippingAutomation');
    }

    private function selectedLocation($store, Request $request): ?Location
    {
        $sessionId = $request->session()->get(self::SESSION_LOCATION);
        $requestId = $request->integer('location_id');

        $id = $requestId > 0 ? $requestId : $sessionId;

        if ($id) {
            $location = $store->locations()->whereKey($id)->first();
            if ($location !== null) {
                return $location;
            }
        }

        return $store->locations()->where('is_default', true)->first()
            ?? $store->locations()->where('is_active', true)->first();
    }

    private function selectedZone($store, Request $request): ?ShippingZone
    {
        $sessionId = $request->session()->get(self::SESSION_ZONE);
        $requestId = $request->integer('shipping_zone_id');

        $id = $requestId > 0 ? $requestId : $sessionId;

        if ($id) {
            return $store->shippingZones()->whereKey($id)->first();
        }

        return $store->shippingZones()->where('is_active', true)->orderBy('sort_order')->first();
    }

    private function selectedMethod($store, Request $request, ?ShippingZone $zone = null): ?ShippingMethod
    {
        $sessionId = $request->session()->get(self::SESSION_METHOD);
        $requestId = $request->integer('shipping_method_id');

        $id = $requestId > 0 ? $requestId : $sessionId;

        if ($id) {
            $method = $store->shippingMethods()->whereKey($id)->first();
            if ($method !== null) {
                return $method;
            }
        }

        $query = $store->shippingMethods()->where('is_active', true)->orderBy('sort_order');
        if ($zone !== null) {
            $query->where('shipping_zone_id', $zone->id);
        }

        return $query->first();
    }
}
