<?php

namespace App\Http\Controllers;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Services\Carriers\CarrierConnectionWizardService;
use App\Services\Carriers\CarrierOriginReadinessService;
use App\Services\Carriers\CarrierProviderManager;
use App\Services\Carriers\FedEx\FedExConfig;
use App\Services\Carriers\USPS\USPSConfig;
use App\Services\SecurityLogRecorder;
use App\Support\CarrierAccountStatusPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CarrierConnectionWizardController extends Controller
{
    public function index(Request $request, CarrierConnectionWizardService $wizard, USPSConfig $uspsConfig, FedExConfig $fedExConfig): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $cards = collect($wizard->supportedCarriers())
            ->map(fn (string $carrier): array => $wizard->carrierCard($carrier, $store, $uspsConfig, $fedExConfig))
            ->all();

        return view('user_view.carrier_connection_wizard.index', [
            'selectedStore' => $store,
            'carrierCards' => $cards,
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
        ]);
    }

    public function show(Request $request, string $carrier, CarrierConnectionWizardService $wizard, USPSConfig $uspsConfig, FedExConfig $fedExConfig): View|RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $carrier = $wizard->normalizeCarrierCode($carrier);
        $card = $wizard->carrierCard($carrier, $store, $uspsConfig, $fedExConfig);

        if ($card['deferred'] ?? false) {
            return redirect()
                ->route('shipping.carriers.connect.index')
                ->withErrors(['carrier' => "{$card['name']} integration is planned for a later phase."]);
        }

        $step = (string) $request->query('step', 'origin');
        $accountId = (int) $request->query('account');
        $account = $accountId > 0
            ? $store->carrierAccounts()->whereKey($accountId)->first()
            : null;

        $carrierContext = $carrier === CarrierConnectionWizardService::CARRIER_USPS
            ? CarrierOriginReadinessService::CARRIER_USPS
            : CarrierOriginReadinessService::CARRIER_GENERIC;

        return view('user_view.carrier_connection_wizard.show', [
            'selectedStore' => $store,
            'carrier' => $carrier,
            'carrierCard' => $card,
            'step' => $step,
            'account' => $account,
            'presenter' => $account ? CarrierAccountStatusPresenter::for($account) : null,
            'originOptions' => $wizard->originOptions($store, $carrierContext),
            'ownershipOptions' => $wizard->ownershipOptions($carrier, $uspsConfig),
            'carriers' => Carrier::query()->where('is_active', true)->orderBy('name')->get(),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
        ]);
    }

    public function storeOrigin(
        Request $request,
        string $carrier,
        CarrierConnectionWizardService $wizard,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $carrier = $wizard->normalizeCarrierCode($carrier);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'carrier_account_id' => [
                'nullable',
                'integer',
                Rule::exists('carrier_accounts', 'id')->where('store_id', $store->id),
            ],
        ]);

        $location = Location::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['origin_location_id'])
            ->firstOrFail();

        $carrierContext = $carrier === CarrierConnectionWizardService::CARRIER_USPS
            ? CarrierOriginReadinessService::CARRIER_USPS
            : CarrierOriginReadinessService::CARRIER_GENERIC;

        $readiness = app(CarrierOriginReadinessService::class)->assessForFulfillmentOrigin($location, $carrierContext);

        if (! $readiness->ready) {
            return back()
                ->withErrors(['origin_location_id' => $readiness->merchantMessage])
                ->withInput();
        }

        $accountId = (int) ($validated['carrier_account_id'] ?? 0);

        return redirect()
            ->route('shipping.carriers.connect.show', [
                'carrier' => $carrier,
                'step' => 'ownership',
                'origin_location_id' => $location->id,
                'account' => $accountId > 0 ? $accountId : null,
            ])
            ->with('success', 'Ship-from location selected.');
    }

    public function storeOwnership(
        Request $request,
        string $carrier,
        CarrierConnectionWizardService $wizard,
        USPSConfig $uspsConfig,
        FedExConfig $fedExConfig,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $carrier = $wizard->normalizeCarrierCode($carrier);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'ownership_mode' => ['required', 'string', 'max:40'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'carrier_id' => ['nullable', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'supported_countries' => ['nullable'],
            'enabled_for_checkout' => ['nullable', 'boolean'],
            'carrier_account_id' => [
                'nullable',
                'integer',
                Rule::exists('carrier_accounts', 'id')->where('store_id', $store->id),
            ],
        ]);

        $location = Location::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['origin_location_id'])
            ->firstOrFail();

        if ($carrier === CarrierConnectionWizardService::CARRIER_USPS) {
            abort_unless($uspsConfig->isConfigured(), 404);
            abort_unless($validated['ownership_mode'] === CarrierAccount::OWNERSHIP_PLATFORM_TESTING, 422);

            $uspsCarrier = Carrier::query()->where('code', 'usps')->where('is_active', true)->firstOrFail();
            $account = $store->carrierAccounts()->create([
                'carrier_id' => $uspsCarrier->id,
                'provider' => CarrierAccount::PROVIDER_USPS,
                'environment' => CarrierAccount::ENVIRONMENT_TESTING,
                'display_name' => filled($validated['display_name'] ?? null) ? $validated['display_name'] : 'USPS platform testing',
                'connection_type' => CarrierAccount::CONNECTION_API,
                'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_PLATFORM,
                'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
                'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
                'supported_countries' => ['US'],
                'enabled_for_checkout' => false,
                'created_by' => $request->user()?->id,
                ...CarrierAccount::ownershipAttributesForUspsPlatformTesting(),
            ]);
            $wizard->applyOriginSelection($account, $location, CarrierOriginReadinessService::CARRIER_USPS);
        } elseif ($carrier === CarrierConnectionWizardService::CARRIER_MANUAL) {
            $manualCarrier = Carrier::query()->whereKey((int) ($validated['carrier_id'] ?? 0))->where('is_active', true)->firstOrFail();
            $account = $store->carrierAccounts()->create([
                'carrier_id' => $manualCarrier->id,
                'provider' => CarrierAccount::PROVIDER_MANUAL,
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'display_name' => filled($validated['display_name'] ?? null) ? $validated['display_name'] : $manualCarrier->name,
                'connection_type' => CarrierAccount::CONNECTION_MANUAL,
                'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
                'status' => CarrierAccount::STATUS_ENABLED,
                'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
                'supported_countries' => $this->listFromInput($validated['supported_countries'] ?? null, true),
                'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
                'created_by' => $request->user()?->id,
                ...CarrierAccount::ownershipAttributesForManual(),
            ]);
            $wizard->applyOriginSelection($account, $location, CarrierOriginReadinessService::CARRIER_GENERIC);
        } elseif ($carrier === CarrierConnectionWizardService::CARRIER_FEDEX) {
            abort_unless($fedExConfig->isConfigured(), 404);

            return redirect()
                ->route('shipping.carriers.connect.show', [
                    'carrier' => $carrier,
                    'step' => 'fedex_details',
                    'origin_location_id' => $location->id,
                ]);
        } else {
            abort(404);
        }

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_wizard_ownership_saved',
            store: $store,
            metadata: ['carrier_account_id' => $account->id, 'carrier' => $carrier],
        );

        return redirect()
            ->route('shipping.carriers.connect.show', [
                'carrier' => $carrier,
                'step' => 'test',
                'account' => $account->id,
            ])
            ->with('success', 'Carrier account saved. Run the connection test next.');
    }

    public function storeFedExDetails(
        Request $request,
        CarrierConnectionWizardService $wizard,
        FedExConfig $fedExConfig,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);
        abort_unless($fedExConfig->isConfigured(), 404);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'display_name' => ['nullable', 'string', 'max:120'],
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
        ]);

        $location = Location::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['origin_location_id'])
            ->firstOrFail();

        $fedExCarrier = Carrier::query()->where('code', 'fedex')->where('is_active', true)->firstOrFail();

        $account = $store->carrierAccounts()->create([
            'carrier_id' => $fedExCarrier->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => filled($validated['display_name'] ?? null) ? $validated['display_name'] : 'FedEx sandbox account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'provider_account_number' => $validated['provider_account_number'],
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'enabled_for_checkout' => false,
            'settings' => [
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
            ],
            'created_by' => $request->user()?->id,
            ...CarrierAccount::ownershipAttributesForFedExMerchantOwned(),
        ]);

        $wizard->applyOriginSelection($account, $location, CarrierOriginReadinessService::CARRIER_GENERIC);

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_wizard_fedex_saved',
            store: $store,
            metadata: ['carrier_account_id' => $account->id],
        );

        return redirect()
            ->route('shipping.carriers.connect.show', [
                'carrier' => CarrierConnectionWizardService::CARRIER_FEDEX,
                'step' => 'test',
                'account' => $account->id,
            ])
            ->with('success', 'FedEx sandbox account saved. Run the connection test next.');
    }

    public function test(
        Request $request,
        string $carrier,
        CarrierConnectionWizardService $wizard,
        CarrierProviderManager $providerManager,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $carrier = $wizard->normalizeCarrierCode($carrier);

        $validated = $request->validate([
            'carrier_account_id' => [
                'required',
                'integer',
                Rule::exists('carrier_accounts', 'id')->where('store_id', $store->id),
            ],
        ]);

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['carrier_account_id'])
            ->firstOrFail();

        if ($carrier === CarrierConnectionWizardService::CARRIER_MANUAL) {
            return redirect()
                ->route('shippingAutomation')
                ->with('success', 'Manual/local delivery account is ready to use.');
        }

        $result = $wizard->testConnection($account);

        $securityLogRecorder->record(
            $request,
            'shipping.carrier_wizard_tested',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'success' => $result->success,
            ]
        );

        if (! $result->success) {
            return redirect()
                ->route('shipping.carriers.connect.show', [
                    'carrier' => $carrier,
                    'step' => 'test',
                    'account' => $account->id,
                ])
                ->withErrors(['connection' => $result->detailMessage ?? $result->message]);
        }

        return redirect()
            ->route('shippingAutomation')
            ->with('success', $result->message)
            ->with('success_title', 'Carrier connection');
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
}
