<?php

namespace App\Http\Controllers\Carrier\Connection;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\Location;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\FedEx\Connection\FedExEulaService;
use App\Services\Carriers\FedEx\Connection\FedExIntegratorRegistrationOrchestrator;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FedExIntegratorConnectionController extends Controller
{
    public function start(Request $request, FedExConfig $config): View|RedirectResponse
    {
        $store = $this->resolveStore($request);
        abort_unless($config->modelAEnabled(), 404);

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

        $environment = $validated['environment'] ?? CarrierAccount::ENVIRONMENT_SANDBOX;
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

        return view('user_view.fedex_integrator.eula', [
            'selectedStore' => $session->store,
            'session' => $session,
            'eulaHtml' => $eulaService->isAvailable() ? $eulaService->html() : null,
            'eulaAvailable' => $eulaService->isAvailable(),
            'eulaVersion' => $eulaService->version(),
            'canManageShipping' => $request->user()?->canManageSettings($session->store) ?? false,
        ]);
    }

    public function acceptEula(
        Request $request,
        CarrierAccountRegistrationSession $session,
        FedExIntegratorRegistrationOrchestrator $orchestrator,
    ): RedirectResponse {
        $this->resolveSessionForStore($request, $session);
        $this->authorizeManage($request, $session->store);

        $request->validate([
            'accept_eula' => ['accepted'],
        ]);

        $orchestrator->acceptEula($session, $request->user());

        return redirect()->route('settings.shipping.fedex-integrator.account', $session);
    }

    public function showAccount(Request $request, CarrierAccountRegistrationSession $session, FedExConfig $config, FedExTestCaseFixtureService $fixtures): View
    {
        $this->resolveSessionForStore($request, $session);

        return view('user_view.fedex_integrator.account', [
            'selectedStore' => $session->store,
            'session' => $session,
            'validationPrefill' => $config->validationModeEnabled() ? $fixtures->usValidationAccount() : null,
            'validationModeEnabled' => $config->validationModeEnabled(),
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
            return redirect()->route('settings.shipping.fedex-integrator.success', $session);
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.account', $session)
            ->withInput($request->except('_token'))
            ->withErrors(['registration' => $session->last_error_message ?? 'FedEx registration failed.'])
            ->with('error_title', 'FedEx registration');
    }

    public function showMfa(Request $request, CarrierAccountRegistrationSession $session, FedExConfig $config): View|RedirectResponse
    {
        $this->resolveSessionForStore($request, $session);

        if ($session->status === CarrierAccountRegistrationSession::STATUS_REGISTERED) {
            return redirect()->route('settings.shipping.fedex-integrator.success', $session);
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
            return redirect()->route('settings.shipping.fedex-integrator.success', $session);
        }

        if ($redirect = $this->mfaBlockedRedirect($session)) {
            return $redirect;
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.mfa', $session)
            ->withErrors(['pin' => $session->last_error_message ?? 'PIN verification failed.']);
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
            return redirect()->route('settings.shipping.fedex-integrator.success', $session);
        }

        if ($redirect = $this->mfaBlockedRedirect($session)) {
            return $redirect;
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.mfa', $session)
            ->withErrors(['invoice_number' => $session->last_error_message ?? 'Invoice verification failed.']);
    }

    public function success(Request $request, CarrierAccountRegistrationSession $session): View
    {
        $this->resolveSessionForStore($request, $session);
        $account = $session->carrierAccount;

        return view('user_view.fedex_integrator.success', [
            'selectedStore' => $session->store,
            'session' => $session,
            'account' => $account,
            'canManageShipping' => $request->user()?->canManageSettings($session->store) ?? false,
        ]);
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

    public function exportValidation(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExValidationEvidenceExporter $exporter,
        FedExConfig $config,
    ): BinaryFileResponse {
        $store = $this->resolveStore($request);
        abort_unless((int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);
        abort_unless($config->validationModeEnabled(), 403);

        $zipPath = $exporter->export(
            store: $store,
            account: $carrierAccount,
            session: $carrierAccount->latestRegistrationSession,
            region: (string) $request->query('region', 'US'),
            environment: $carrierAccount->environment,
        );

        return response()->download($zipPath, basename($zipPath));
    }

    private function resolveStore(Request $request): \App\Models\Store
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return $store;
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
            CarrierAccountRegistrationSession::STATUS_LOCKED,
        ], true)) {
            return null;
        }

        return redirect()
            ->route('settings.shipping.fedex-integrator.account', $session)
            ->withErrors(['registration' => $session->last_error_message ?? 'FedEx verification could not continue. Start a new connection from Shipping & Delivery.'])
            ->with('error_title', 'FedEx verification');
    }
}
