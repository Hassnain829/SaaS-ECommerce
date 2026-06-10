<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\ShipmentPackage;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\Carriers\CarrierProviderManager;
use App\Services\Carriers\FedEx\FedExAccountRegistrationService;
use App\Services\Carriers\FedEx\FedExConfig;
use App\Services\Carriers\USPS\USPSConfig;
use App\Services\Carriers\USPS\USPSDomesticRateQuoteService;
use App\Services\Carriers\USPS\USPSOAuthTokenService;
use App\Services\Channels\ChannelOwnershipService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShippingSettingsController extends Controller
{
    public function index(Request $request, ChannelOwnershipService $channelOwnership, FedExConfig $fedExConfig, USPSConfig $uspsConfig): View|RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $store = $channelOwnership->ensureChannelsStructure($store);

        return view('user_view.shippingAutomation', [
            'selectedStore' => $store,
            'isExternalManaged' => $channelOwnership->isExternalManaged($store),
            'isPlatformManaged' => $channelOwnership->isPlatformManaged($store),
            'carriers' => Carrier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'carrierAccounts' => $store->carrierAccounts()
                ->with(['carrier', 'shippingMethods'])
                ->orderByDesc('status')
                ->orderBy('display_name')
                ->get(),
            'fedExCarrier' => Carrier::query()->where('code', 'fedex')->first(),
            'fedExAccounts' => $store->carrierAccounts()
                ->where('provider', CarrierAccount::PROVIDER_FEDEX)
                ->with('carrier')
                ->orderByDesc('updated_at')
                ->get(),
            'fedExApiEvents' => $store->carrierApiEvents()
                ->where('provider', CarrierAccount::PROVIDER_FEDEX)
                ->latest('id')
                ->limit(8)
                ->get(),
            'fedExPlatformConfigured' => $fedExConfig->isConfigured(),
            'fedExEnabled' => $fedExConfig->isEnabled(),
            'fedExRegistrationPath' => $fedExConfig->accountRegistrationPath(CarrierAccount::ENVIRONMENT_SANDBOX),
            'fedExRegistrationResidentialMode' => $fedExConfig->accountRegistrationResidentialMode(),
            'fedExStepDiagnostics' => $this->fedExLatestStepDiagnostics($store),
            'fedExRegistrationRequestDiagnostics' => $this->fedExRegistrationRequestDiagnostics($store),
            'fedExSandboxPlatformFallbackAllowed' => $fedExConfig->allowsSandboxPlatformFallback(),
            'uspsCarrier' => Carrier::query()->where('code', 'usps')->first(),
            'uspsAccounts' => $store->carrierAccounts()
                ->where('provider', CarrierAccount::PROVIDER_USPS)
                ->with('carrier')
                ->orderByDesc('updated_at')
                ->get(),
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
            'shippingZones' => $store->shippingZones()
                ->with('shippingMethods.carrierAccount.carrier')
                ->orderByDesc('is_active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'shippingMethods' => $store->shippingMethods()
                ->with(['shippingZone', 'carrierAccount.carrier'])
                ->orderByDesc('is_active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'locations' => $store->locations()
                ->orderByDesc('is_default')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
            'connectionTypes' => CarrierAccount::CONNECTION_TYPES,
            'carrierAccountStatuses' => CarrierAccount::STATUSES,
            'rateTypes' => ShippingMethod::RATE_TYPES,
        ]);
    }

    public function storeCarrierAccount(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $request->validate([
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'display_name' => ['required', 'string', 'max:120'],
            'connection_type' => ['required', Rule::in(CarrierAccount::CONNECTION_TYPES)],
            'status' => ['required', Rule::in(CarrierAccount::STATUSES)],
            'supported_countries' => ['nullable'],
            'enabled_for_checkout' => ['nullable', 'boolean'],
        ]);

        $account = $store->carrierAccounts()->create([
            ...$validated,
            'provider' => CarrierAccount::PROVIDER_MANUAL,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'connection_status' => $validated['status'] === CarrierAccount::STATUS_ENABLED
                ? CarrierAccount::CONNECTION_CONNECTED
                : CarrierAccount::CONNECTION_NOT_CONNECTED,
            'supported_countries' => $this->listFromInput($validated['supported_countries'] ?? null),
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
            'created_by' => $request->user()?->id,
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_account_created',
            store: $store,
            metadata: ['carrier_account_id' => $account->id, 'display_name' => $account->display_name]
        );

        return back()
            ->with('success', 'Carrier account added.')
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

    public function storeFedExCarrierAccount(Request $request, FedExConfig $fedExConfig, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        if (! $fedExConfig->isConfigured()) {
            return back()
                ->withErrors(['fedex' => 'FedEx sandbox connection is not available on this platform environment yet. Contact the platform admin.'])
                ->with('error_title', 'Shipping & delivery');
        }

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'environment' => ['required', Rule::in(['sandbox'])],
            'provider_account_number' => ['required', 'string', 'max:32'],
            'company_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'address_line1' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:160'],
            'residential' => ['nullable', 'boolean'],
            'default_origin_location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
        ]);

        $fedExCarrier = Carrier::query()->where('code', 'fedex')->where('is_active', true)->firstOrFail();
        $displayName = filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : 'FedEx sandbox account';

        $settings = [
            'registration' => [
                'company_name' => $validated['company_name'],
                'contact_name' => $validated['contact_name'],
                'address_line1' => $validated['address_line1'],
                'city' => $validated['city'],
                'state' => $validated['state'] ?? null,
                'postal_code' => $validated['postal_code'],
                'country_code' => strtoupper($validated['country_code']),
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'provider_account_number' => $validated['provider_account_number'],
                'residential' => $request->boolean('residential'),
            ],
        ];

        if (filled($validated['default_origin_location_id'] ?? null)) {
            $settings['default_origin_location_id'] = (int) $validated['default_origin_location_id'];
        }

        $account = $store->carrierAccounts()->create([
            'carrier_id' => $fedExCarrier->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => $displayName,
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'provider_account_number' => $validated['provider_account_number'],
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'settings' => $settings,
            'enabled_for_checkout' => false,
            'created_by' => $request->user()?->id,
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_carrier_account_created',
            store: $store,
            metadata: ['carrier_account_id' => $account->id, 'display_name' => $account->display_name]
        );

        return back()
            ->with('success', 'FedEx sandbox account saved. Run Test connection to verify registration.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateFedExRegistrationSettings(
        Request $request,
        CarrierAccount $carrierAccount,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);

        $request->validate([
            'residential' => ['nullable', 'boolean'],
        ]);

        $settings = $carrierAccount->settings ?? [];
        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];
        $registration['residential'] = $request->boolean('residential');
        $settings['registration'] = $registration;

        $carrierAccount->forceFill(['settings' => $settings])->save();

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_registration_settings_updated',
            store: $store,
            metadata: [
                'carrier_account_id' => $carrierAccount->id,
                'residential' => $registration['residential'],
            ]
        );

        return back()
            ->with('success', 'FedEx registration settings updated. Run Test connection again.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function exportFedExDebugPayload(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExAccountRegistrationService $registrationService,
    ): JsonResponse {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);

        return response()->json(
            $registrationService->redactedRegistrationPayload($carrierAccount),
            200,
            [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }

    public function enableFedExSandboxPlatformFallback(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $fedExConfig,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        abort_unless(app()->environment(['local', 'testing']), 404);
        abort_unless($fedExConfig->allowsSandboxPlatformFallback(), 403);

        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);

        $settings = $carrierAccount->settings ?? [];
        $settings['sandbox_platform_fallback'] = true;
        $carrierAccount->forceFill(['settings' => $settings])->save();

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_sandbox_platform_fallback_enabled',
            store: $store,
            metadata: ['carrier_account_id' => $carrierAccount->id],
        );

        return back()
            ->with('success', 'Sandbox platform fallback enabled for this FedEx account. Run Test connection to verify platform OAuth only.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function testFedExCarrierAccount(
        Request $request,
        CarrierAccount $carrierAccount,
        CarrierProviderManager $providerManager,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);

        try {
            $result = $providerManager->provider(CarrierAccount::PROVIDER_FEDEX)->testConnection(
                $carrierAccount->load('store')
            );
        } catch (\Throwable) {
            $carrierAccount->markFailed('FedEx connection test failed. Please try again.');

            return back()
                ->withErrors(['fedex' => 'FedEx connection test failed. Please try again.'])
                ->with('error_title', 'Shipping & delivery');
        }

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_carrier_account_tested',
            store: $store,
            metadata: [
                'carrier_account_id' => $carrierAccount->id,
                'success' => $result->success,
            ]
        );

        if (! $result->success) {
            return back()
                ->withErrors(['fedex' => $result->detailMessage ?? $result->message])
                ->with('error_title', 'Shipping & delivery')
                ->with('fedex_connection_message', $result->message)
                ->with('fedex_connection_steps', $result->steps)
                ->with('fedex_connection_status', $result->connectionStatus);
        }

        return back()
            ->with('success', $result->message)
            ->with('success_title', 'Shipping & delivery')
            ->with('fedex_connection_steps', $result->steps)
            ->with('fedex_connection_status', $result->connectionStatus);
    }

    public function storeUspsCarrierAccount(Request $request, USPSConfig $uspsConfig, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        if (! $uspsConfig->isConfigured()) {
            return back()
                ->withErrors(['usps' => 'USPS public API connection is not available on this platform environment yet. Contact the platform admin.'])
                ->with('error_title', 'Shipping & delivery');
        }

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'environment' => ['required', Rule::in(['testing'])],
            'default_origin_location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'enabled_for_checkout' => ['nullable', 'boolean'],
        ]);

        $uspsCarrier = Carrier::query()->where('code', 'usps')->where('is_active', true)->firstOrFail();
        $displayName = filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : 'USPS testing account';

        $settings = [];
        if (filled($validated['default_origin_location_id'] ?? null)) {
            $settings['default_origin_location_id'] = (int) $validated['default_origin_location_id'];
        }

        $account = $store->carrierAccounts()->create([
            'carrier_id' => $uspsCarrier->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => $displayName,
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_PLATFORM,
            'billing_owner' => CarrierAccount::BILLING_OWNER_PLATFORM,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'settings' => $settings,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
            'created_by' => $request->user()?->id,
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.usps_carrier_account_created',
            store: $store,
            metadata: ['carrier_account_id' => $account->id, 'display_name' => $account->display_name]
        );

        return back()
            ->with('success', 'USPS testing account saved. Run Test connection to verify OAuth.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function testUspsCarrierAccount(
        Request $request,
        CarrierAccount $carrierAccount,
        CarrierProviderManager $providerManager,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isUsps(), 404);

        try {
            $result = $providerManager->provider(CarrierAccount::PROVIDER_USPS)->testConnection(
                $carrierAccount->load('store')
            );
        } catch (\Throwable) {
            $carrierAccount->markFailed('USPS connection test failed. Please try again.');

            return back()
                ->withErrors(['usps' => 'USPS connection test failed. Please try again.'])
                ->with('error_title', 'Shipping & delivery');
        }

        $securityLogRecorder->record(
            $request,
            'shipping.usps_carrier_account_tested',
            store: $store,
            metadata: [
                'carrier_account_id' => $carrierAccount->id,
                'success' => $result->success,
            ]
        );

        if (! $result->success) {
            return back()
                ->withErrors(['usps' => $result->detailMessage ?? $result->message])
                ->with('error_title', 'Shipping & delivery')
                ->with('usps_connection_message', $result->message)
                ->with('usps_connection_steps', $result->steps);
        }

        return back()
            ->with('success', $result->message)
            ->with('success_title', 'Shipping & delivery')
            ->with('usps_connection_steps', $result->steps);
    }

    public function storeUspsTestPackage(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'destination_postal_code' => ['required', 'string', 'max:16'],
            'weight_value' => ['required', 'numeric', 'gt:0'],
            'length' => ['required', 'numeric', 'gt:0'],
            'width' => ['required', 'numeric', 'gt:0'],
            'height' => ['required', 'numeric', 'gt:0'],
            'mail_class' => ['nullable', 'string', 'max:64'],
            'carrier_account_id' => [
                'required',
                'integer',
                Rule::exists('carrier_accounts', 'id')->where('store_id', $store->id),
            ],
        ]);

        $package = $store->shipmentPackages()->create([
            'origin_location_id' => $validated['origin_location_id'] ?? null,
            'name' => 'USPS test package',
            'weight_value' => $validated['weight_value'],
            'weight_unit' => 'lb',
            'length' => $validated['length'],
            'width' => $validated['width'],
            'height' => $validated['height'],
            'dimension_unit' => 'in',
            'package_type' => 'parcel',
            'metadata' => [
                'destination_postal_code' => $validated['destination_postal_code'],
                'mail_class' => $validated['mail_class'] ?? null,
            ],
            'created_by' => $request->user()?->id,
        ]);

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->where('provider', CarrierAccount::PROVIDER_USPS)
            ->whereKey((int) $validated['carrier_account_id'])
            ->firstOrFail();

        $oauth = app(USPSOAuthTokenService::class)->accessToken();
        if ($oauth === null) {
            return back()
                ->withErrors(['usps' => 'USPS OAuth token is unavailable. Test the USPS connection first.'])
                ->with('error_title', 'Shipping & delivery');
        }

        ['result' => $quoteResult] = app(USPSDomesticRateQuoteService::class)->quotePackage(
            $store,
            $account,
            $package,
            $validated['destination_postal_code'],
            $oauth['access_token'],
            $request->user(),
            $validated['mail_class'] ?? null,
        );

        $securityLogRecorder->record(
            $request,
            'shipping.usps_test_rate_quote_requested',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'package_id' => $package->id,
                'success' => $quoteResult->success,
            ]
        );

        if (! $quoteResult->success) {
            return back()
                ->withErrors(['usps' => $quoteResult->errorMessage ?? 'USPS rate quote failed.'])
                ->with('error_title', 'Shipping & delivery');
        }

        return back()
            ->with('success', 'USPS test rate quote saved. This quote is informational only and does not change checkout totals.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function disableCarrierAccount(
        Request $request,
        CarrierAccount $carrierAccount,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);

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

    public function storeZone(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $this->validateZone($request);

        $zone = $store->shippingZones()->create([
            ...$validated,
            'countries' => $this->listFromInput($validated['countries'] ?? null, true),
            'regions' => $this->listFromInput($validated['regions'] ?? null),
            'postal_patterns' => $this->listFromInput($validated['postal_patterns'] ?? null),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.zone_created',
            store: $store,
            metadata: ['shipping_zone_id' => $zone->id, 'name' => $zone->name]
        );

        return back()
            ->with('success', 'Shipping zone added.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateZone(Request $request, ShippingZone $shippingZone, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingZone->store_id === (int) $store->id, 404);

        $validated = $this->validateZone($request);

        $shippingZone->update([
            ...$validated,
            'countries' => $this->listFromInput($validated['countries'] ?? null, true),
            'regions' => $this->listFromInput($validated['regions'] ?? null),
            'postal_patterns' => $this->listFromInput($validated['postal_patterns'] ?? null),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.zone_updated',
            store: $store,
            metadata: ['shipping_zone_id' => $shippingZone->id, 'name' => $shippingZone->name]
        );

        return back()
            ->with('success', 'Shipping zone updated.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function destroyZone(Request $request, ShippingZone $shippingZone, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingZone->store_id === (int) $store->id, 404);

        $shippingZone->delete();

        $securityLogRecorder->record(
            $request,
            'shipping.zone_deleted',
            store: $store,
            metadata: ['shipping_zone_id' => $shippingZone->id, 'name' => $shippingZone->name]
        );

        return back()
            ->with('success', 'Shipping zone removed.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function storeMethod(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $this->validateMethod($request, $store->id);

        $method = $store->shippingMethods()->create([
            ...$validated,
            'code' => $this->uniqueMethodCode($store->id, $validated['name']),
            'carrier_account_id' => $validated['carrier_account_id'] ?? null,
            'flat_rate' => $validated['flat_rate'] ?? 0,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.method_created',
            store: $store,
            metadata: ['shipping_method_id' => $method->id, 'name' => $method->name]
        );

        return back()
            ->with('success', 'Delivery method added.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateMethod(Request $request, ShippingMethod $shippingMethod, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shippingMethod->store_id === (int) $store->id, 404);

        $validated = $this->validateMethod($request, $store->id);

        $shippingMethod->update([
            ...$validated,
            'carrier_account_id' => $validated['carrier_account_id'] ?? null,
            'flat_rate' => $validated['flat_rate'] ?? 0,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.method_updated',
            store: $store,
            metadata: ['shipping_method_id' => $shippingMethod->id, 'name' => $shippingMethod->name]
        );

        return back()
            ->with('success', 'Delivery method updated.')
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
            ->with('success', 'Delivery method removed.')
            ->with('success_title', 'Shipping & delivery');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateZone(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
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
            'rate_type' => ['required', Rule::in(ShippingMethod::RATE_TYPES)],
            'flat_rate' => ['nullable', 'numeric', 'min:0'],
            'free_over_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['nullable', 'numeric', 'min:0'],
            'estimated_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'estimated_max_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'enabled_for_checkout' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
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

    private function uniqueMethodCode(int $storeId, string $name): string
    {
        $base = Str::slug($name) ?: 'delivery-method';
        $code = $base;
        $counter = 2;

        while (ShippingMethod::query()->where('store_id', $storeId)->where('code', $code)->exists()) {
            $code = $base.'-'.$counter;
            $counter++;
        }

        return $code;
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

    private function fedExLatestStepDiagnostics(\App\Models\Store $store): array
    {
        $actions = [
            CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
            CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
        ];

        $accountIds = $store->carrierAccounts()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
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

    /**
     * @return array<int, array{request: array<string, mixed>, response: array<string, mixed>, error_message: ?string}>
     */
    private function fedExRegistrationRequestDiagnostics(\App\Models\Store $store): array
    {
        $accountIds = $store->carrierAccounts()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->pluck('id');

        $diagnostics = [];

        foreach ($accountIds as $accountId) {
            $event = CarrierApiEvent::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $accountId)
                ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
                ->latest('id')
                ->first();

            if ($event === null) {
                continue;
            }

            $diagnostics[$accountId] = [
                'request' => is_array($event->request_summary) ? $event->request_summary : [],
                'response' => is_array($event->response_summary) ? $event->response_summary : [],
                'error_message' => $event->error_message,
            ];
        }

        return $diagnostics;
    }
}
