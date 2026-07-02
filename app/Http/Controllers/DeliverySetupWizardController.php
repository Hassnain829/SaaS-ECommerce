<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\Delivery\DeliveryAddressDiagnosticService;
use App\Services\Delivery\DeliveryAreaInputNormalizer;
use App\Services\Delivery\DeliverySetupStatusService;
use App\Services\Delivery\DeliveryWizardPersistenceService;
use App\Services\Inventory\DefaultLocationService;
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

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('settings.delivery.setup.ship-from');
    }

    public function shipFrom(Request $request, TaxConfigurationService $taxConfiguration): View|RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

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

    public function deliverTo(Request $request, TaxConfigurationService $taxConfiguration, DeliveryAreaInputNormalizer $areaNormalizer): View|RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        if ($request->isMethod('post')) {
            $zone = app(DeliveryWizardPersistenceService::class)->saveDeliveryArea($request, $store);
            $request->session()->put(self::SESSION_ZONE, $zone->id);

            return redirect()->route('settings.delivery.setup.delivery-option');
        }

        $zones = $store->shippingZones()->orderByDesc('is_active')->orderBy('name')->get();
        $selectedZone = $this->selectedZone($store, $request);
        $zonePayload = $selectedZone ? $areaNormalizer->presentationFromZone($selectedZone) : null;

        return view('user_view.delivery.setup.deliver-to', $this->wizardContext($request, $store, $taxConfiguration, [
            'step' => 2,
            'shippingZones' => $zones,
            'selectedZone' => $selectedZone,
            'zonePayload' => $zonePayload,
            'legacyZones' => $zones->filter(fn (ShippingZone $zone): bool => app(DeliveryWizardPersistenceService::class)->isLegacyZone($zone)),
        ]));
    }

    public function deliveryOption(Request $request, TaxConfigurationService $taxConfiguration): View|RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        if ($request->isMethod('post')) {
            $method = app(DeliveryWizardPersistenceService::class)->saveDeliveryOption($request, $store, $request->user());
            $request->session()->put(self::SESSION_METHOD, $method->id);

            return redirect()->route('settings.delivery.setup.review');
        }

        $selectedZone = $this->selectedZone($store, $request);
        $selectedMethod = $this->selectedMethod($store, $request);
        $priceMode = 'fixed';
        if ($selectedMethod !== null) {
            $priceMode = $selectedMethod->rate_type === ShippingMethod::RATE_FREE
                ? 'free'
                : ((float) ($selectedMethod->free_over_amount ?? 0) > 0 ? 'free_over' : 'fixed');
        }

        return view('user_view.delivery.setup.delivery-option', $this->wizardContext($request, $store, $taxConfiguration, [
            'step' => 3,
            'shippingZones' => $store->shippingZones()->where('is_active', true)->orderBy('name')->get(),
            'shippingMethods' => $store->shippingMethods()->orderBy('name')->get(),
            'selectedZone' => $selectedZone,
            'selectedMethod' => $selectedMethod,
            'priceMode' => $priceMode,
            'flagMismatch' => $selectedMethod !== null && $selectedMethod->is_active !== $selectedMethod->enabled_for_checkout,
        ]));
    }

    public function review(Request $request, DeliverySetupStatusService $deliverySetupStatus, TaxConfigurationService $taxConfiguration): View
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        $locations = $store->locations()->orderByDesc('is_default')->orderBy('name')->get();
        $zones = $store->shippingZones()->orderByDesc('is_active')->orderBy('name')->get();
        $methods = $store->shippingMethods()->with('shippingZone')->orderBy('name')->get();
        $carrierAccounts = $store->carrierAccounts()->orderBy('display_name')->get();
        $taxSetting = $taxConfiguration->ensureSettingsForStore($store);

        $deliverySetup = $deliverySetupStatus->assess($store, $locations, $zones, $methods, $carrierAccounts, $taxSetting);

        $selectedLocation = $this->selectedLocation($store, $request);
        $selectedZone = $this->selectedZone($store, $request);
        $selectedMethod = $this->selectedMethod($store, $request);

        if ($selectedLocation !== null) {
            $deliverySetup['ship_from'] = $deliverySetupStatus->summarizeShipFromLocation($selectedLocation);
        }
        if ($selectedZone !== null) {
            $deliverySetup['delivery_areas'] = $deliverySetupStatus->summarizeDeliveryArea($selectedZone);
        }
        if ($selectedMethod !== null) {
            $deliverySetup['delivery_options'] = $deliverySetupStatus->summarizeDeliveryOption($selectedMethod);
        }

        return view('user_view.delivery.setup.review', $this->wizardContext($request, $store, $taxConfiguration, [
            'step' => 4,
            'deliverySetup' => $deliverySetup,
            'selectedLocation' => $selectedLocation,
            'selectedZone' => $selectedZone,
            'selectedMethod' => $selectedMethod,
        ]));
    }

    public function finish(Request $request): RedirectResponse
    {
        $store = $this->store($request);
        $this->authorizeManage($request, $store);

        $request->session()->forget([
            self::SESSION_LOCATION,
            self::SESSION_ZONE,
            self::SESSION_METHOD,
        ]);

        return redirect()
            ->route('shippingAutomation')
            ->with('success', 'Delivery setup saved. Review your delivery hub for any remaining items.')
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
            ]);

            $result = $diagnostic->diagnose(
                $store,
                $validated['country_code'],
                $validated['region_code'] ?? null,
                $validated['postal_code'] ?? null,
                (float) ($validated['order_subtotal'] ?? 0),
            );
        }

        return view('user_view.delivery.test-address', [
            'selectedStore' => $store,
            'countries' => TaxCountryCatalog::all(),
            'result' => $result,
            'input' => $request->old() ?: $request->only(['country_code', 'region_code', 'postal_code', 'order_subtotal']),
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

        app(DefaultLocationService::class)->ensureFromStoreDefaults($store, $request->user());

        return $store;
    }

    private function authorizeManage(Request $request, $store): void
    {
        abort_unless($request->user()?->canManageSettings($store) ?? false, 403);
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

    private function selectedMethod($store, Request $request): ?ShippingMethod
    {
        $sessionId = $request->session()->get(self::SESSION_METHOD);
        $requestId = $request->integer('shipping_method_id');

        $id = $requestId > 0 ? $requestId : $sessionId;

        if ($id) {
            return $store->shippingMethods()->whereKey($id)->first();
        }

        return $store->shippingMethods()->where('is_active', true)->orderBy('sort_order')->first();
    }
}
