<?php

namespace App\Http\Controllers\Carrier\Connection;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\USPS\Auth\USPSMerchantOAuthService;
use App\Services\Carriers\USPS\Connection\USPSMerchantAuthorizationVerificationService;
use App\Services\Carriers\USPS\Connection\USPSMerchantConnectionService;
use App\Services\Carriers\USPS\Connection\USPSMerchantShipSuiteVerificationService;
use App\Services\Carriers\USPS\Presenters\USPSMerchantStatusPresenter;
use App\Services\Carriers\USPS\Support\USPSMerchantConnectionContext;
use App\Services\Carriers\USPS\Support\USPSMerchantOAuthException;
use App\Services\Carriers\USPS\Support\USPSMerchantWizard;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class USPSMerchantConnectionController extends Controller
{
    public function start(Request $request, USPSMerchantConnectionService $connectionService): View|RedirectResponse
    {
        $store = $this->resolveStore($request);
        abort_unless($connectionService->merchantConnectionAvailable(), 404);

        $existing = $connectionService->findActiveMerchantAccount($store);

        if ($existing !== null) {
            $step = $connectionService->wizard()->resolveStep($existing);

            if ($connectionService->wizard()->isWizardComplete($existing)) {
                return redirect()->route('settings.shipping.usps-merchant.manage', $existing);
            }

            return redirect()->route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $existing,
                'step' => $step,
            ]);
        }

        return view('user_view.usps_merchant.start', [
            'selectedStore' => $store,
            'locations' => $store->locations()
                ->orderByDesc('is_default')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (Location $location): array => [
                    'location' => $location,
                    'readiness' => app(CarrierOriginReadinessService::class)->assessForFulfillmentOrigin(
                        $location,
                        CarrierOriginReadinessService::CARRIER_USPS,
                    ),
                ]),
            'requirements' => $connectionService->merchantRequirements(),
            'businessPortalUrl' => $connectionService->uspsBusinessPortalUrl(),
            'labelProviderName' => $connectionService->platformLabelProviderName(),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
            'merchantOAuthAvailable' => $connectionService->merchantOAuthAvailable(),
        ]);
    }

    public function showWizard(
        Request $request,
        CarrierAccount $carrierAccount,
        string $step,
        USPSMerchantConnectionService $connectionService,
    ): View|RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        if ($account->usps_authorization_status === CarrierAccount::USPS_AUTH_DISABLED) {
            return redirect()
                ->route('settings.shipping.usps-merchant.start')
                ->withErrors(['usps' => 'This USPS connection was disconnected. Start a new connection when you are ready.']);
        }

        if ($connectionService->wizard()->isWizardComplete($account)) {
            return redirect()->route('settings.shipping.usps-merchant.manage', $account);
        }

        abort_unless(USPSMerchantWizard::isValidStep($step), 404);

        $resolvedStep = $connectionService->wizard()->resolveStep($account);
        if ($step !== $resolvedStep && $step !== USPSMerchantWizard::STEP_ORIGIN) {
            return redirect()->route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account,
                'step' => $resolvedStep,
            ]);
        }

        $account->loadMissing('defaultOriginLocation');

        $locations = $store->locations()
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Location $location): array => [
                'location' => $location,
                'readiness' => app(CarrierOriginReadinessService::class)->assessForFulfillmentOrigin(
                    $location,
                    CarrierOriginReadinessService::CARRIER_USPS,
                ),
            ]);

        return view('user_view.usps_merchant.wizard', [
            'selectedStore' => $store,
            'account' => $account,
            'step' => $step,
            'wizard' => $connectionService->wizard(),
            'progress' => $connectionService->wizard()->progress($account),
            'presenter' => USPSMerchantStatusPresenter::for($account),
            'context' => USPSMerchantConnectionContext::for($account),
            'locations' => $locations,
            'requirements' => $connectionService->merchantRequirements(),
            'businessPortalUrl' => $connectionService->uspsBusinessPortalUrl(),
            'labelProviderName' => $connectionService->platformLabelProviderName(),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
            'merchantOAuthAvailable' => $connectionService->merchantOAuthAvailable(),
        ]);
    }

    public function storeOrigin(
        Request $request,
        USPSMerchantConnectionService $connectionService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        abort_unless($connectionService->merchantConnectionAvailable(), 404);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'display_name' => ['nullable', 'string', 'max:120'],
        ]);

        $location = Location::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['origin_location_id'])
            ->firstOrFail();

        $account = $connectionService->startOrResume($store, $request->user(), $location);

        if (filled($validated['display_name'] ?? null)) {
            $account->forceFill(['display_name' => trim((string) $validated['display_name'])])->save();
        }

        $securityLogRecorder->record($request, 'shipping.usps_merchant.origin_selected', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'origin_location_id' => $location->id,
        ]);

        return redirect()
            ->route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account,
                'step' => USPSMerchantWizard::STEP_IDENTIFIERS,
            ])
            ->with('success', 'Ship-from location saved. Next, confirm your USPS account details.');
    }

    public function updateOrigin(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
        ]);

        $location = Location::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['origin_location_id'])
            ->firstOrFail();

        $connectionService->applyOrigin($account, $location);

        $securityLogRecorder->record($request, 'shipping.usps_merchant.origin_updated', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'origin_location_id' => $location->id,
        ]);

        return back()->with('success', 'Ship-from location updated.');
    }

    public function storeIdentifiers(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        $validated = $request->validate([
            'merchant_crid' => ['required', 'string', 'regex:/^\d{5,12}$/'],
            'merchant_mid' => ['required', 'string', 'regex:/^(?:\d{6}|\d{9})$/'],
            'merchant_epa' => ['required', 'string', 'regex:/^\d{5,12}$/'],
            'merchant_manifest_mid' => ['nullable', 'string', 'regex:/^(?:\d{6}|\d{9})$/'],
        ], [
            'merchant_crid.regex' => 'Enter a valid USPS Customer Registration ID (CRID).',
            'merchant_mid.regex' => 'Enter a valid USPS Mailer ID (MID). USPS MIDs are 6 or 9 digits.',
            'merchant_epa.regex' => 'Enter a valid USPS Enterprise Payment Account (EPA) number.',
            'merchant_manifest_mid.regex' => 'Enter a valid manifest Mailer ID (6 or 9 digits).',
        ]);

        $connectionService->saveMerchantIdentifiers($account, $validated);

        $securityLogRecorder->record($request, 'shipping.usps_merchant.identifiers_saved', store: $store, metadata: [
            'carrier_account_id' => $account->id,
        ]);

        return redirect()
            ->route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account->fresh(),
                'step' => USPSMerchantWizard::STEP_AUTHORIZATION,
            ])
            ->with('success', 'USPS account details saved. Complete Label Provider authorization when the official USPS authorization flow is available.');
    }

    public function storeAuthorizationAcknowledgement(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        abort(404);
    }

    public function reauthorize(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        $connectionService->resetAuthorization($account);

        $securityLogRecorder->record($request, 'shipping.usps_merchant.authorization_reset', store: $store, metadata: [
            'carrier_account_id' => $account->id,
        ]);

        return redirect()
            ->route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account,
                'step' => USPSMerchantWizard::STEP_AUTHORIZATION,
            ])
            ->with('success', 'Authorization reset. Use Authorize with USPS when you are ready to authorize again.');
    }

    public function manage(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
    ): View|RedirectResponse {
        $store = $this->resolveStore($request);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        if ($account->usps_authorization_status === CarrierAccount::USPS_AUTH_DISABLED) {
            return redirect()
                ->route('settings.shipping.usps-merchant.start')
                ->withErrors(['usps' => 'This USPS connection was disconnected. Start a new connection when you are ready.']);
        }

        if (! $connectionService->wizard()->isWizardComplete($account)) {
            return redirect()->route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account,
                'step' => $connectionService->wizard()->resolveStep($account),
            ]);
        }

        $account->loadMissing(['defaultOriginLocation', 'carrier', 'apiEvents' => fn ($query) => $query->latest('id')->limit(8)]);

        return view('user_view.usps_merchant.manage', [
            'selectedStore' => $store,
            'account' => $account,
            'presenter' => USPSMerchantStatusPresenter::for($account),
            'context' => USPSMerchantConnectionContext::for($account),
            'progress' => $connectionService->wizard()->progress($account),
            'businessPortalUrl' => $connectionService->uspsBusinessPortalUrl(),
            'labelProviderName' => $connectionService->platformLabelProviderName(),
            'canManageShipping' => $request->user()?->canManageSettings($store) ?? false,
            'merchantOAuthAvailable' => $connectionService->merchantOAuthAvailable(),
            'merchantShipSuiteVerifyAvailable' => $connectionService->merchantShipSuiteVerifyAvailable(),
        ]);
    }

    public function verifyShipSuite(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
        USPSMerchantShipSuiteVerificationService $shipSuiteVerificationService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        $verification = $shipSuiteVerificationService->verify($store, $account);

        $securityLogRecorder->record($request, 'shipping.usps_merchant.ship_suite_verify_requested', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'verification_code' => $verification['code'],
            'verification_success' => $verification['success'],
        ]);

        if ($verification['success']) {
            return back()->with('success', $verification['message']);
        }

        return back()->withErrors(['usps' => $verification['message']]);
    }

    public function startOAuth(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
        USPSMerchantOAuthService $oauthService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);
        abort_unless($connectionService->merchantOAuthAvailable(), 404);
        abort_unless($account->hasUspsMerchantIdentifiers(), 422);

        $user = $request->user();
        abort_unless($user !== null, 403);

        try {
            $authorizeUrl = $oauthService->resolveAuthorizeRedirectUrl($account, (int) $user->id);
        } catch (USPSMerchantOAuthException $exception) {
            return back()->withErrors(['usps' => $exception->getMessage()]);
        }

        $securityLogRecorder->record($request, 'shipping.usps_merchant.oauth_started', store: $store, metadata: [
            'carrier_account_id' => $account->id,
        ]);

        return redirect()->away($authorizeUrl);
    }

    public function oauthCallback(
        Request $request,
        USPSMerchantConnectionService $connectionService,
        USPSMerchantOAuthService $oauthService,
        USPSMerchantAuthorizationVerificationService $verificationService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        abort_unless($connectionService->merchantOAuthAvailable(), 404);

        if ($request->filled('error')) {
            return redirect()
                ->route('shippingAutomation', ['tab' => 'advanced'])
                ->withErrors(['usps' => 'USPS authorization was not completed. Try again from your USPS connection page.']);
        }

        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        $statePayload = $oauthService->resolveOAuthState($state);

        if ($statePayload === null || $code === '') {
            return redirect()
                ->route('shippingAutomation', ['tab' => 'advanced'])
                ->withErrors(['usps' => 'USPS authorization response was invalid or expired. Start authorization again.']);
        }

        abort_unless((int) $statePayload['store_id'] === (int) $store->id, 403);
        abort_unless((int) $statePayload['user_id'] === (int) ($request->user()?->id ?? 0), 403);

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $statePayload['carrier_account_id'])
            ->firstOrFail();

        abort_unless($account->isUspsMerchantLabelProvider(), 404);

        $oauthService->forgetOAuthState($state);

        $tokenResult = $oauthService->exchangeAuthorizationCode($store, $account, $code);

        if (! $tokenResult->success) {
            return redirect()
                ->route('settings.shipping.usps-merchant.manage', $account)
                ->withErrors(['usps' => $tokenResult->errorMessage ?? 'USPS could not complete authorization. Try again.']);
        }

        $account = $account->fresh();
        $connectionService->completeOAuthAuthorization($account);

        $verification = $verificationService->verify($store, $account->fresh());

        $securityLogRecorder->record($request, 'shipping.usps_merchant.oauth_callback', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'verification_code' => $verification['code'],
            'verification_success' => $verification['success'],
        ]);

        if ($verification['success']) {
            return redirect()
                ->route('settings.shipping.usps-merchant.manage', $account->fresh())
                ->with('success', $verification['message']);
        }

        return redirect()
            ->route('settings.shipping.usps-merchant.manage', $account->fresh())
            ->with('success', 'USPS authorization received. Verification could not finish yet — use Verify with USPS on the manage page when ready.')
            ->withErrors(['usps' => $verification['message']]);
    }

    public function verifyConnection(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
        USPSMerchantAuthorizationVerificationService $verificationService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        $verification = $verificationService->verify($store, $account);

        $securityLogRecorder->record($request, 'shipping.usps_merchant.verify_requested', store: $store, metadata: [
            'carrier_account_id' => $account->id,
            'verification_code' => $verification['code'],
            'verification_success' => $verification['success'],
        ]);

        if ($verification['success']) {
            return back()->with('success', $verification['message']);
        }

        return back()->withErrors(['usps' => $verification['message']]);
    }

    public function disconnect(
        Request $request,
        CarrierAccount $carrierAccount,
        USPSMerchantConnectionService $connectionService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $this->resolveStore($request);
        $this->authorizeManage($request, $store);
        $account = $this->resolveMerchantAccount($store, $carrierAccount);

        $connectionService->disconnect($account);

        $securityLogRecorder->record($request, 'shipping.usps_merchant.disconnected', store: $store, metadata: [
            'carrier_account_id' => $account->id,
        ]);

        return redirect()
            ->route('shippingAutomation', ['tab' => 'advanced'])
            ->with('success', 'USPS merchant connection disconnected. Your historical activity remains in the audit log.');
    }

    private function resolveStore(Request $request)
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        return $store;
    }

    private function authorizeManage(Request $request, $store): void
    {
        abort_unless($request->user()?->canManageSettings($store) ?? false, 403);
    }

    private function resolveMerchantAccount($store, CarrierAccount $carrierAccount): CarrierAccount
    {
        abort_unless((int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isUspsMerchantLabelProvider(), 404);

        return $carrierAccount;
    }
}
