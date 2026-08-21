<?php

namespace App\Http\Controllers\Carrier\Connection;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\Location;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\FedEx\Connection\FedExConnectionFailurePresenter;
use App\Services\Carriers\FedEx\Connection\FedExEulaService;
use App\Services\Carriers\FedEx\Connection\FedExIntegratorRegistrationOrchestrator;
use App\Services\Carriers\FedEx\Connection\FedExMerchantConnectionLifecycleService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FedExIntegratorConnectionController extends Controller
{
    public function __construct(
        private readonly FedExConnectionFailurePresenter $failurePresenter,
    ) {}

    public function start(Request $request, FedExConfig $config): View|RedirectResponse
    {
        $store = $this->resolveStore($request);
        abort_unless($config->modelAEnabled(), 404);

        $intentKey = (string) $request->query('return_intent', '');
        $capability = app(\App\Services\Carriers\FedEx\Support\FedExCheckoutCapabilityService::class);
        if ($capability->resolveReturnIntent($intentKey) !== null) {
            $request->session()->put(
                \App\Services\Carriers\FedEx\Support\FedExCheckoutCapabilityService::SESSION_RETURN_INTENT,
                $intentKey
            );
        }

        $locations = $store->locations()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Location $location) => [
                'location' => $location,
                'readiness' => app(CarrierOriginReadinessService::class)->assessForFulfillmentOrigin(
                    $location,
                    CarrierOriginReadinessService::CARRIER_GENERIC,
                ),
            ]);

        return view('user_view.fedex_integrator.start', [
            'selectedStore' => $store,
            'locations' => $locations,
            'productionEnabled' => $config->productionEnabled(),
            'defaultEnvironment' => $config->merchantMayChooseEnvironment()
                ? CarrierAccount::ENVIRONMENT_SANDBOX
                : $config->merchantDefaultEnvironment(),
            'allowEnvironmentChoice' => $config->merchantMayChooseEnvironment(),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
        ]);
    }

    public function storeOrigin(
        Request $request,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
        SecurityLogRecorder $securityLogRecorder,
        FedExConfig $config,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'environment' => ['nullable', Rule::in(['sandbox', 'live'])],
        ]);

        $environment = $config->merchantMayChooseEnvironment()
            ? strtolower((string) ($validated['environment'] ?? CarrierAccount::ENVIRONMENT_SANDBOX))
            : $config->merchantDefaultEnvironment();
        abort_unless($config->allowsIntegratorEnvironment($environment), 422);

        $session = $orchestrator->start(
            $store,
            $request->user(),
            (int) $validated['origin_location_id'],
            $environment,
        );

        $securityLogRecorder->record($request, 'shipping.fedex_integrator.origin_selected', store: $store, metadata: [
            'registration_session_id' => $session->id,
            'environment' => $environment,
        ]);

        return redirect()->route('settings.shipping.fedex-integrator.eula', $session);
    }

    public function showEula(Request $request, CarrierAccountRegistrationSession $session, FedExEulaService $eulaService): View
    {
        $this->resolveSessionForStore($request, $session);

        $documentValid = $eulaService->isValid();
        $documentHash = $documentValid ? $eulaService->hash() : null;

        return view('user_view.fedex_integrator.eula', [
            'selectedStore' => $session->store,
            'session' => $session,
            'eulaAvailable' => $eulaService->isAvailable(),
            'eulaValid' => $documentValid,
            'eulaVersion' => $eulaService->version(),
            'eulaFormNumber' => $eulaService->formNumber(),
            'eulaExpectedPages' => $eulaService->expectedPages(),
            'eulaDocumentHash' => $documentHash,
            'eulaMetadata' => $eulaService->metadata(),
            'scrollCompleted' => $session->eula_scrolled_at !== null
                && filled($session->eula_document_hash)
                && $documentHash !== null
                && hash_equals($documentHash, (string) $session->eula_document_hash),
            'canManageShipping' => $request->user()?->canManageSettings($session->store) ?? false,
        ]);
    }

    public function showEulaDocument(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExEulaService $eulaService,
    ): BinaryFileResponse {
        $this->resolveSessionForStore($request, $session);
        $eulaService->assertValid();

        $path = $eulaService->documentPath();
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $eulaService->mimeType(),
            'Content-Disposition' => 'inline; filename="FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function markEulaScrollComplete(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
    ): JsonResponse {
        $this->resolveSessionForStore($request, $session);
        $this->authorizeManage($request, $session->store);

        $validated = $request->validate([
            'document_hash' => ['required', 'string', 'size:64'],
            'rendered_page_count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $session = $orchestrator->markEulaScrollComplete(
            $session,
            strtolower((string) $validated['document_hash']),
            (int) $validated['rendered_page_count'],
        );

        return response()->json([
            'ok' => true,
            'scrolled_at' => $session->eula_scrolled_at?->toIso8601String(),
            'document_hash' => $session->eula_document_hash,
            'rendered_page_count' => $session->eula_rendered_page_count,
        ]);
    }

    public function acceptEula(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
        FedExEulaService $eulaService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $this->resolveSessionForStore($request, $session);
        $this->authorizeManage($request, $session->store);

        $validated = $request->validate([
            'read_and_accept_eula' => ['accepted'],
            'document_hash' => ['required', 'string', 'size:64'],
        ]);

        $session = $orchestrator->acceptEula(
            $session,
            $request->user(),
            strtolower((string) $validated['document_hash']),
        );

        $securityLogRecorder->record($request, 'shipping.fedex_eula_accepted', store: $session->store, metadata: [
            'registration_session_id' => $session->id,
            'carrier_account_id' => $session->carrier_account_id,
            'purpose' => $session->purpose,
            'eula_version' => $session->eula_version,
            'eula_document_hash' => $session->eula_document_hash,
            'expected_pages' => $eulaService->expectedPages(),
            'accepted_by_user_id' => $request->user()?->id,
        ]);

        return redirect()->route('settings.shipping.fedex-integrator.account', $session);
    }

    public function showAccount(Request $request, CarrierAccountRegistrationSession $session): View
    {
        $this->resolveSessionForStore($request, $session);

        return view('user_view.fedex_integrator.account', [
            'selectedStore' => $session->store,
            'session' => $session,
            'countryOptions' => \App\Support\CarrierCountryOptions::fedExOptionsForContext($session->environment),
            'defaultCountry' => \App\Support\CarrierCountryOptions::defaultFedExCountry(
                $session->originLocation?->country_code
                    ?? data_get($session->registrationAddress(), 'country_code')
            ),
            'canManageShipping' => $request->user()?->canManageSettings($session->store) ?? false,
        ]);
    }

    public function submitAccount(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
    ): RedirectResponse {
        $this->resolveSessionForStore($request, $session);
        $this->authorizeManage($request, $session->store);

        $validated = $request->validate([
            'provider_account_number' => ['required', 'string', 'max:32'],
            'company_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['required', 'string', 'max:120'],
            'address_line2' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'max:32'],
            'postal_code' => ['required', 'string', 'max:16'],
            'country_code' => ['required', 'string', 'size:2'],
            'residential' => ['nullable', 'boolean'],
        ]);

        $session = $orchestrator->submitAccountDetails($session, $validated);

        if ($session->status === CarrierAccountRegistrationSession::STATUS_MFA_METHOD_REQUIRED) {
            return redirect()->route('settings.shipping.fedex-integrator.mfa', $session);
        }

        if ($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            return $this->successRedirectOrRecovery($session);
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.account', $session)
            ->withInput($request->except('_token'))
            ->withErrors(['registration' => $this->customerFacingFailureMessage($session)])
            ->with('error_title', 'FedEx registration');
    }

    public function showMfa(Request $request, CarrierAccountRegistrationSession $session, FedExConfig $config): View|RedirectResponse
    {
        $this->resolveSessionForStore($request, $session);

        if ($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            return $this->successRedirectOrRecovery($session);
        }

        if ($redirect = $this->mfaBlockedRedirect($session)) {
            return $redirect;
        }

        return view('user_view.fedex_integrator.mfa', [
            'selectedStore' => $session->store,
            'session' => $session,
            'mfaOptions' => is_array($session->mfa_options_json) ? $session->mfa_options_json : [],
            'pinEndpointConfigured' => $config->mfaPinValidationPath() !== null,
            'invoiceEndpointConfigured' => $config->mfaInvoiceValidationPath() !== null,
            'canManageShipping' => $request->user()?->canManageSettings($session->store) ?? false,
        ]);
    }

    public function selectMfaMethod(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
    ): RedirectResponse {
        $this->resolveSessionForStore($request, $session);
        $this->authorizeManage($request, $session->store);

        $validated = $request->validate([
            'mfa_method' => ['required', Rule::in(['email', 'sms', 'call', 'invoice'])],
        ]);

        $session = $orchestrator->selectMfaMethod($session, $validated['mfa_method']);

        if ($redirect = $this->mfaBlockedRedirect($session)) {
            return $redirect;
        }

        return redirect()->route('settings.shipping.fedex-integrator.mfa', $session);
    }

    public function verifyPin(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
    ): RedirectResponse {
        $this->resolveSessionForStore($request, $session);
        $this->authorizeManage($request, $session->store);

        $validated = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        $session = $orchestrator->verifyPin($session, $validated['pin']);

        if ($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            return $this->successRedirectOrRecovery($session);
        }

        if ($redirect = $this->mfaBlockedRedirect($session)) {
            return $redirect;
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.mfa', $session)
            ->withErrors(['pin' => $this->customerFacingFailureMessage($session, 'PIN verification failed.')]);
    }

    public function verifyInvoice(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
    ): RedirectResponse {
        $this->resolveSessionForStore($request, $session);
        $this->authorizeManage($request, $session->store);

        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:64'],
            'invoice_date' => ['required', 'date'],
            'invoice_currency' => ['nullable', 'string', 'size:3'],
            'invoice_amount' => ['required', 'string', 'max:32'],
        ]);

        $session = $orchestrator->verifyInvoice($session, $validated);

        if ($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            return $this->successRedirectOrRecovery($session);
        }

        if ($redirect = $this->mfaBlockedRedirect($session)) {
            return $redirect;
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.mfa', $session)
            ->withErrors(['invoice_number' => $this->customerFacingFailureMessage($session, 'Invoice verification failed.')]);
    }

    public function success(Request $request, CarrierAccountRegistrationSession $session): View|RedirectResponse
    {
        $this->resolveSessionForStore($request, $session);
        $account = $session->carrierAccount;

        if (! $this->hasVerifiedSuccessfulConnection($session)) {
            return $this->recoveryRedirect($session);
        }

        $directChildAuthorization =
            $session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED
            && blank($session->mfa_method)
            && data_get($session->response_summary_json, 'credential_key_detected') === true
            && data_get($session->response_summary_json, 'credential_secret_detected') === true
            && data_get($session->response_summary_json, 'mfa_detected') === false;

        return view('user_view.fedex_integrator.success', [
            'selectedStore' => $session->store,
            'session' => $session,
            'account' => $account,
            'directChildAuthorization' => $directChildAuthorization,
            'canManageShipping' => $request->user()?->canManageSettings($session->store) ?? false,
            'returnIntent' => $this->consumeReturnIntent($request),
        ]);
    }

    public function manage(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExMerchantConnectionLifecycleService $lifecycle,
        FedExConfig $config,
    ): View|RedirectResponse {
        $store = $this->resolveStore($request);
        abort_unless($config->modelAEnabled(), 404);
        $account = $this->resolveIntegratorAccount($store, $carrierAccount);

        if ($account->disconnected_at !== null || $account->replaced_at !== null) {
            return redirect()
                ->route('settings.shipping.fedex-integrator.start')
                ->withErrors(['registration' => 'This FedEx connection is no longer active. Start a new connection when you are ready.']);
        }

        $lifecycle->assertActiveManageableAccount($account);
        $account->loadMissing([
            'defaultOriginLocation',
            'carrier',
            'apiEvents' => fn ($query) => $query->latest('id')->limit(8),
        ]);

        $resumableSession = $lifecycle->findResumableSessionForAccount($account);

        $guard = app(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::class);
        $caps = is_array($account->capabilities) ? $account->capabilities : [];

        $lineageAccountIds = $this->fedExAccountLineageIds($store->id, $account);

        $recentShipments = \App\Models\Shipment::query()
            ->where('store_id', $store->id)
            ->whereIn('carrier_account_id', $lineageAccountIds)
            ->where(function ($query): void {
                $query->whereNull('direction')
                    ->orWhere('direction', '!=', \App\Models\Shipment::DIRECTION_RETURN);
            })
            ->with(['order:id,order_number,store_id'])
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'order_id', 'shipment_number', 'tracking_number', 'status', 'label_url', 'carrier_account_id', 'created_at', 'metadata', 'direction']);

        $recentReturnShipments = \App\Models\Shipment::query()
            ->where('store_id', $store->id)
            ->whereIn('carrier_account_id', $lineageAccountIds)
            ->where(function ($query): void {
                $query->where('direction', \App\Models\Shipment::DIRECTION_RETURN)
                    ->orWhere('metadata->fedex->return_shipment', true);
            })
            ->with(['order:id,order_number,store_id'])
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'order_id', 'shipment_number', 'tracking_number', 'status', 'carrier_account_id', 'created_at', 'metadata', 'direction']);

        $openReturnsNeedingLabels = \App\Models\OrderReturn::query()
            ->where('store_id', $store->id)
            ->where('status', \App\Support\ReturnLifecycle::STATUS_APPROVED)
            ->count();

        $fedExCheckoutMethods = \App\Models\ShippingMethod::query()
            ->where('store_id', $store->id)
            ->where('rate_type', \App\Models\ShippingMethod::RATE_CARRIER_CALCULATED_LATER)
            ->whereIn('carrier_account_id', $lineageAccountIds)
            ->where(function ($query): void {
                $query->where('is_active', true)->orWhere('enabled_for_checkout', true);
            })
            ->with('shippingZone:id,name,store_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $checkoutPlatformOn = $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_CHECKOUT_RATES);
        $checkoutAccountOn = (bool) $account->enabled_for_checkout && (bool) ($caps['checkout_rates'] ?? false);
        $checkoutRatesEffective = $checkoutPlatformOn && $checkoutAccountOn && $fedExCheckoutMethods->isNotEmpty();

        $recentApiEvents = $account->apiEvents;
        $lastCheckoutRateSuccess = $this->latestSuccessfulEvent($recentApiEvents, [
            \App\Models\CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
        ]);
        $lastLabelSuccess = $this->latestSuccessfulEvent($recentApiEvents, [
            \App\Models\CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
        ]);
        $lastTrackingSuccess = $this->latestSuccessfulEvent($recentApiEvents, [
            \App\Models\CarrierApiEvent::ACTION_FEDEX_BASIC_INTEGRATED_VISIBILITY,
        ]);

        $checkoutRatesStatus = 'needs_setup';
        $checkoutRatesDetail = 'Connect checkout options so customers can see FedEx prices.';
        $checkoutRatesBadge = 'Needs setup';
        if (! $checkoutPlatformOn) {
            $checkoutRatesStatus = 'disabled';
            $checkoutRatesDetail = 'Platform checkout rates are currently off.';
            $checkoutRatesBadge = 'Disabled';
        } elseif ($fedExCheckoutMethods->isEmpty()) {
            $checkoutRatesStatus = 'needs_setup';
            $checkoutRatesDetail = 'FedEx is connected, but no live-rate options are linked to a delivery area yet.';
            $checkoutRatesBadge = 'Needs setup';
        } elseif ($checkoutRatesEffective && $lastCheckoutRateSuccess) {
            $checkoutRatesStatus = 'working';
            $checkoutRatesDetail = 'Live FedEx rates are configured. Last successful quote '.$lastCheckoutRateSuccess->created_at?->diffForHumans().'.';
            $checkoutRatesBadge = 'Working';
        } elseif ($checkoutRatesEffective) {
            $checkoutRatesStatus = 'active';
            $checkoutRatesDetail = 'Live FedEx rates are configured for checkout.';
            $checkoutRatesBadge = 'Enabled — not tested';
        } elseif (! $checkoutAccountOn) {
            $checkoutRatesStatus = 'needs_setup';
            $checkoutRatesDetail = 'Checkout rate options exist, but checkout use is not enabled on this account.';
            $checkoutRatesBadge = 'Needs setup';
        }

        $labelsPlatformOn = $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_SHIP_LABELS);
        $labelsAccountOn = (bool) ($caps['labels'] ?? $caps['ship_labels'] ?? false);
        $trackingPlatformOn = $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_TRACKING);
        $trackingAccountOn = (bool) ($caps['tracking'] ?? false);

        $labelsEnabled = $labelsPlatformOn && $labelsAccountOn;
        $trackingEnabled = $trackingPlatformOn && $trackingAccountOn;

        $labelsBadge = match (true) {
            ! $labelsPlatformOn => 'Disabled',
            ! $labelsAccountOn => 'Not enabled',
            $lastLabelSuccess !== null => 'Working',
            default => 'Enabled — not tested',
        };
        $labelsDetail = match (true) {
            ! $labelsPlatformOn => 'Label purchase is off at the platform.',
            ! $labelsAccountOn => 'Platform labels are on, but this FedEx account still needs labels turned on.',
            $lastLabelSuccess !== null => 'Label purchase is enabled. Last successful label '.$lastLabelSuccess->created_at?->diffForHumans().'.',
            default => 'Label purchase is enabled for this account.',
        };

        $trackingBadge = match (true) {
            ! $trackingPlatformOn => 'Disabled',
            ! $trackingAccountOn => 'Not enabled',
            $lastTrackingSuccess !== null => 'Working',
            default => 'Enabled — not tested',
        };
        $trackingDetail = match (true) {
            ! $trackingPlatformOn => 'Tracking is off at the platform.',
            ! $trackingAccountOn => 'Platform tracking is on, but this FedEx account still needs tracking turned on.',
            $lastTrackingSuccess !== null => 'Tracking updates are enabled. Last successful sync '.$lastTrackingSuccess->created_at?->diffForHumans().'.',
            default => 'Tracking updates are enabled for this account.',
        };

        $readyToShipCount = \App\Models\Order::query()
            ->where('store_id', $store->id)
            ->whereIn('fulfillment_status', [
                \App\Support\OrderLifecycle::FULFILLMENT_UNFULFILLED,
                \App\Support\OrderLifecycle::FULFILLMENT_PARTIAL,
            ])
            ->whereNotIn('status', [
                \App\Models\Order::STATUS_CANCELLED,
                \App\Models\Order::STATUS_REFUNDED,
            ])
            ->count();

        $inTransitCount = \App\Models\Shipment::query()
            ->where('store_id', $store->id)
            ->whereIn('carrier_account_id', $lineageAccountIds)
            ->whereIn('status', ['in_transit', 'shipped', 'label_created', 'pending'])
            ->count();

        $exceptionCount = \App\Models\Shipment::query()
            ->where('store_id', $store->id)
            ->whereIn('carrier_account_id', $lineageAccountIds)
            ->whereIn('status', ['exception', 'failed', 'delivery_exception'])
            ->count();

        $deliveredCount = \App\Models\Shipment::query()
            ->where('store_id', $store->id)
            ->whereIn('carrier_account_id', $lineageAccountIds)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $lineageAccounts = \App\Models\CarrierAccount::query()
            ->where('store_id', $store->id)
            ->whereIn('id', $lineageAccountIds)
            ->where('id', '!=', $account->id)
            ->orderByDesc('replaced_at')
            ->orderByDesc('id')
            ->get();

        $primaryNextAction = match (true) {
            filled($account->last_error_message) && in_array($account->connection_status, ['failed', 'blocked_by_fedex'], true) => [
                'label' => 'Verify connection',
                'href' => null,
                'form' => 'verify',
            ],
            in_array($checkoutRatesStatus, ['needs_setup'], true) => [
                'label' => 'Manage checkout shipping',
                'href' => route('settings.delivery.checkout-options'),
                'form' => null,
            ],
            $labelsEnabled && $readyToShipCount > 0 => [
                'label' => 'Ship an order',
                'href' => route('orders'),
                'form' => null,
            ],
            default => [
                'label' => 'Verify connection',
                'href' => null,
                'form' => 'verify',
            ],
        };

        return view('user_view.fedex_integrator.manage', [
            'selectedStore' => $store,
            'account' => $account,
            'presenter' => \App\Support\CarrierAccountStatusPresenter::for($account),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
            'productionEnabled' => $config->productionEnabled(),
            'resumableSession' => $resumableSession,
            'opsCapabilities' => [
                'address_validation' => $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_ADDRESS_VALIDATION),
                'service_availability' => $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_SERVICE_AVAILABILITY),
                'negotiated_rates' => $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_NEGOTIATED_RATES),
                'checkout_rates' => $checkoutRatesEffective,
                'ship_labels' => $labelsEnabled,
                'tracking' => $trackingEnabled,
            ],
            'accountCapabilityToggles' => [
                'checkout_rates' => (bool) ($caps['checkout_rates'] ?? false),
                'labels' => (bool) ($caps['labels'] ?? $caps['ship_labels'] ?? false),
                'tracking' => (bool) ($caps['tracking'] ?? false),
                'enabled_for_checkout' => (bool) $account->enabled_for_checkout,
            ],
            'globalFlags' => [
                'checkout_rates' => $checkoutPlatformOn,
                'ship_labels' => $labelsPlatformOn,
                'tracking' => $trackingPlatformOn,
            ],
            'recentApiEvents' => $recentApiEvents,
            'recentShipments' => $recentShipments,
            'recentReturnShipments' => $recentReturnShipments,
            'openReturnsNeedingLabels' => $openReturnsNeedingLabels,
            'checkoutRatesStatus' => $checkoutRatesStatus,
            'checkoutRatesDetail' => $checkoutRatesDetail,
            'checkoutRatesBadge' => $checkoutRatesBadge,
            'fedExCheckoutMethods' => $fedExCheckoutMethods,
            'readyToShipCount' => $readyToShipCount,
            'inTransitCount' => $inTransitCount,
            'exceptionCount' => $exceptionCount,
            'deliveredCount' => $deliveredCount,
            'primaryNextAction' => $primaryNextAction,
            'labelsEnabled' => $labelsEnabled,
            'trackingEnabled' => $trackingEnabled,
            'labelsBadge' => $labelsBadge,
            'labelsDetail' => $labelsDetail,
            'trackingBadge' => $trackingBadge,
            'trackingDetail' => $trackingDetail,
            'lineageAccounts' => $lineageAccounts,
        ]);
    }

    /**
     * @param  iterable<int, \App\Models\CarrierApiEvent>  $events
     * @param  list<string>  $actions
     */
    private function latestSuccessfulEvent(iterable $events, array $actions): ?\App\Models\CarrierApiEvent
    {
        foreach ($events as $event) {
            if (($event->status ?? null) !== 'succeeded') {
                continue;
            }
            if (in_array((string) $event->action, $actions, true)) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function fedExAccountLineageIds(int $storeId, CarrierAccount $account): array
    {
        $ids = collect([(int) $account->id]);

        $changed = true;
        while ($changed) {
            $changed = false;
            $predecessors = CarrierAccount::query()
                ->where('store_id', $storeId)
                ->whereIn('replaced_by_carrier_account_id', $ids->all())
                ->pluck('id');
            foreach ($predecessors as $id) {
                if (! $ids->contains((int) $id)) {
                    $ids->push((int) $id);
                    $changed = true;
                }
            }
        }

        $cursor = $account->replaced_by_carrier_account_id;
        $guard = 0;
        while ($cursor && $guard < 20) {
            $ids->push((int) $cursor);
            $next = CarrierAccount::query()->where('store_id', $storeId)->whereKey($cursor)->first();
            $cursor = $next?->replaced_by_carrier_account_id;
            $guard++;
        }

        return $ids->unique()->values()->all();
    }

    public function updateCapabilities(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        abort_unless($config->modelAEnabled(), 404);
        $account = $this->resolveIntegratorAccount($store, $carrierAccount);

        $validated = $request->validate([
            'enabled_for_checkout' => ['nullable', 'boolean'],
            'checkout_rates' => ['nullable', 'boolean'],
            'labels' => ['nullable', 'boolean'],
            'tracking' => ['nullable', 'boolean'],
        ]);

        $lifecycle = app(\App\Services\Carriers\FedEx\Connection\FedExMerchantConnectionLifecycleService::class);
        $lifecycle->assertActiveManageableAccount($account);

        $guard = app(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::class);
        $globalCheckout = $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_CHECKOUT_RATES);
        $globalLabels = $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_SHIP_LABELS);
        $globalTracking = $guard->capabilityEnabled(\App\Services\Carriers\FedEx\Operations\FedExOperationGuard::CAPABILITY_TRACKING);

        $caps = is_array($account->capabilities) ? $account->capabilities : [];
        $requestedCheckoutRates = (bool) ($validated['checkout_rates'] ?? false);
        $requestedLabels = (bool) ($validated['labels'] ?? false);
        $requestedTracking = (bool) ($validated['tracking'] ?? false);
        $requestedEnabledForCheckout = (bool) ($validated['enabled_for_checkout'] ?? false);

        if ($requestedCheckoutRates && ! $globalCheckout) {
            return back()->withErrors([
                'checkout_rates' => 'Checkout rates are disabled by the platform and cannot be enabled on this account yet.',
            ]);
        }
        if ($requestedLabels && ! $globalLabels) {
            return back()->withErrors([
                'labels' => 'Label purchase is disabled by the platform and cannot be enabled on this account yet.',
            ]);
        }
        if ($requestedTracking && ! $globalTracking) {
            return back()->withErrors([
                'tracking' => 'Tracking is disabled by the platform and cannot be enabled on this account yet.',
            ]);
        }

        $caps['checkout_rates'] = $globalCheckout ? $requestedCheckoutRates : false;
        $caps['labels'] = $globalLabels ? $requestedLabels : false;
        $caps['tracking'] = $globalTracking ? $requestedTracking : false;

        // Checkout enable requires both the global checkout flag and the account checkout_rates capability.
        $enabledForCheckout = $globalCheckout && $caps['checkout_rates'] && $requestedEnabledForCheckout;

        $account->forceFill([
            'enabled_for_checkout' => $enabledForCheckout,
            'capabilities' => $caps,
        ])->save();

        $securityLogRecorder->record($request, 'shipping.fedex_integrator.capabilities_updated', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'capabilities' => [
                'checkout_rates' => $caps['checkout_rates'],
                'labels' => $caps['labels'],
                'tracking' => $caps['tracking'],
                'enabled_for_checkout' => (bool) $account->enabled_for_checkout,
            ],
        ]);

        return redirect()
            ->route('settings.shipping.fedex-integrator.manage', $account)
            ->with('success', 'FedEx account capabilities updated.');
    }

    public function verify(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExMerchantConnectionLifecycleService $lifecycle,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveIntegratorAccount($store, $carrierAccount);

        $result = $lifecycle->verify($account);

        $securityLogRecorder->record($request, 'shipping.fedex_integrator.verify_requested', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'success' => $result->success,
            'error_code' => $result->errorCode,
        ]);

        if ($result->success) {
            return redirect()
                ->route('settings.shipping.fedex-integrator.manage', $account)
                ->with('success', 'FedEx connection verified successfully.');
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.manage', $account)
            ->withErrors(['registration' => $result->errorMessage ?? 'FedEx could not verify this connection.']);
    }

    public function resume(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExMerchantConnectionLifecycleService $lifecycle,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $session = $this->resolveSessionForStore($request, $session);

        $session = $lifecycle->resumeChildOAuthVerification($store, $session);

        $securityLogRecorder->record($request, 'shipping.fedex_integrator.resume_verification', store: $store, metadata: [
            'registration_session_id' => $session->id,
            'carrier_account_id' => $session->carrier_account_id,
            'status' => $session->status,
        ]);

        if ($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            return $this->successRedirectOrRecovery($session);
        }

        $accountId = (int) $session->carrier_account_id;
        if ($accountId > 0) {
            return redirect()
                ->route('settings.shipping.fedex-integrator.manage', $accountId)
                ->withErrors(['registration' => $session->last_error_message ?? 'FedEx verification could not be completed. You can try Resume verification again.']);
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.account', $session)
            ->withErrors(['registration' => $session->last_error_message ?? 'FedEx verification could not be completed.']);
    }

    public function reconnect(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExMerchantConnectionLifecycleService $lifecycle,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveIntegratorAccount($store, $carrierAccount);
        $lifecycle->assertActiveManageableAccount($account);

        $originLocationId = (int) (
            $account->default_origin_location_id
            ?? data_get($account->settings, 'default_origin_location_id')
        );
        abort_unless($originLocationId > 0, 422);

        $session = $lifecycle->beginReconnect(
            $store,
            $request->user(),
            $account,
            $originLocationId,
        );

        $securityLogRecorder->record($request, 'shipping.fedex_integrator.reconnect_started', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'registration_session_id' => $session->id,
            'environment' => $session->environment,
        ]);

        return redirect()->route('settings.shipping.fedex-integrator.eula', $session);
    }

    public function disconnect(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExMerchantConnectionLifecycleService $lifecycle,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveIntegratorAccount($store, $carrierAccount);

        $result = $lifecycle->disconnect($account);

        $securityLogRecorder->record($request, 'shipping.fedex_integrator.disconnected', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'environment' => $account->environment,
            'account_last4' => $account->fresh()?->provider_account_last4,
            'idempotent' => $result['idempotent'],
        ]);

        return redirect()
            ->route('shippingAutomation', ['tab' => 'carriers'])
            ->with('success', $result['idempotent']
                ? 'FedEx account was already disconnected.'
                : 'FedEx account disconnected. You can connect again anytime from Shipping & Delivery.');
    }

    public function cancel(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
    ): RedirectResponse {
        $this->resolveSessionForStore($request, $session);
        $this->authorizeManage($request, $session->store);

        $orchestrator->cancel($session);

        return redirect()
            ->route('shippingAutomation', ['tab' => 'carriers'])
            ->with('success', 'FedEx connection setup was cancelled.');
    }

    private function resolveStore(Request $request): \App\Models\Store
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return $store;
    }

    private function resolveIntegratorAccount(
        \App\Models\Store $store,
        CarrierAccount $carrierAccount,
    ): CarrierAccount {
        abort_unless((int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->usesFedExIntegratorProvider(), 404);

        return $carrierAccount;
    }

    private function resolveSessionForStore(Request $request, CarrierAccountRegistrationSession $session): CarrierAccountRegistrationSession
    {
        $store = $this->resolveStore($request);
        abort_unless((int) $session->store_id === (int) $store->id, 404);

        return $session->loadMissing(['store', 'originLocation', 'carrierAccount']);
    }

    private function authorizeManage(Request $request, \App\Models\Store $store): void
    {
        abort_unless($request->user()?->canManageSettings($store), 403);
    }

    private function mfaBlockedRedirect(CarrierAccountRegistrationSession $session): ?RedirectResponse
    {
        if (! in_array($session->status, [
            CarrierAccountRegistrationSession::STATUS_FAILED,
            CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
            CarrierAccountRegistrationSession::STATUS_LOCKED,
        ], true)) {
            return null;
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.account', $session)
            ->withErrors(['registration' => $this->customerFacingFailureMessage($session)])
            ->with('error_title', 'FedEx verification');
    }

    private function customerFacingFailureMessage(
        CarrierAccountRegistrationSession $session,
        string $fallback = 'FedEx verification could not continue. Start a new connection from Shipping & Delivery.',
    ): string {
        return $session->last_error_message
            ?: $this->failurePresenter->message(
                $session,
                $session->last_error_code,
                $fallback,
            );
    }

    private function successRedirectOrRecovery(
        CarrierAccountRegistrationSession $session,
    ): RedirectResponse {
        if ($this->hasVerifiedSuccessfulConnection($session)) {
            return redirect()->route('settings.shipping.fedex-integrator.success', $session);
        }

        return $this->recoveryRedirect($session);
    }

    private function recoveryRedirect(
        CarrierAccountRegistrationSession $session,
    ): RedirectResponse {
        return redirect()
            ->route('settings.shipping.fedex-integrator.account', $session)
            ->withErrors([
                'registration' => $this->customerFacingFailureMessage(
                    $session,
                    'FedEx account verification is not complete. Review the connection details and try again.',
                ),
            ])
            ->with('error_title', 'FedEx verification');
    }

    private function hasVerifiedSuccessfulConnection(
        CarrierAccountRegistrationSession $session,
    ): bool {
        $account = $session->carrierAccount;

        return $session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED
            && $account !== null
            && $account->isConnected()
            && $account->hasLegacyFedExChildCredentials();
    }

    /**
     * @return array{key: string, label: string, route: string, params?: array<string, mixed>}|null
     */
    private function consumeReturnIntent(Request $request): ?array
    {
        $capability = app(\App\Services\Carriers\FedEx\Support\FedExCheckoutCapabilityService::class);
        $key = $request->session()->pull(
            \App\Services\Carriers\FedEx\Support\FedExCheckoutCapabilityService::SESSION_RETURN_INTENT
        );

        return $capability->resolveReturnIntent(is_string($key) ? $key : null);
    }
}
