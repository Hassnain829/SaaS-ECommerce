<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderAccount;
use App\Models\SecurityLog;
use App\Services\Channels\ChannelOwnershipService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\StripeConfig;
use App\Services\Payments\StripeConnectService;
use App\Services\SecurityLogRecorder;
use App\Support\CheckoutMode;
use App\Support\PlatformPaymentMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    public function index(
        Request $request,
        PaymentProviderManager $paymentProviderManager,
        ChannelOwnershipService $channelOwnership,
        StripeConfig $stripeConfig,
    ): View|RedirectResponse {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $accounts = PaymentProviderAccount::query()
            ->where('store_id', $store->id)
            ->where('provider', 'stripe')
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        $store = $channelOwnership->ensureChannelsStructure($store);
        $platformPaymentMode = PlatformPaymentMode::forStore($store);
        $testConnectAccount = $paymentProviderManager->connectAccountForStore($store, PlatformPaymentMode::TEST);
        $liveConnectAccount = $paymentProviderManager->connectAccountForStore($store, PlatformPaymentMode::LIVE);
        $testConnectReady = $paymentProviderManager->activeConnectedAccountForStore($store, PlatformPaymentMode::TEST) !== null;
        $liveConnectReady = $paymentProviderManager->activeConnectedAccountForStore($store, PlatformPaymentMode::LIVE) !== null;
        $activeConnectAccount = $paymentProviderManager->activeConnectedAccountForStore($store, $platformPaymentMode);

        $canManagePayments = $request->user()?->canManageSettings($store) ?? false;

        return view('user_view.payment_settings', [
            'selectedStore' => $store,
            'accounts' => $accounts,
            'connectAccount' => $testConnectAccount,
            'platformAccount' => $accounts->firstWhere('connection_type', 'platform'),
            'testConnectAccount' => $testConnectAccount,
            'liveConnectAccount' => $liveConnectAccount,
            'testConnectReady' => $testConnectReady,
            'liveConnectReady' => $liveConnectReady,
            'activeConnectAccount' => $activeConnectAccount,
            'platformPaymentMode' => $platformPaymentMode,
            'checkoutMode' => CheckoutMode::forStore($store),
            'externalChannelConfig' => $channelOwnership->externalCheckoutConfig($store),
            'platformChannelConfig' => $channelOwnership->platformCheckoutConfig($store),
            'isExternalManaged' => $channelOwnership->isExternalManaged($store),
            'isPlatformManaged' => $channelOwnership->isPlatformManaged($store),
            'externalInventoryOwner' => $channelOwnership->inventoryOwner($store, ChannelOwnershipService::CHANNEL_EXTERNAL),
            'usesPlatformInventoryForExternal' => $channelOwnership->usesPlatformInventory($store, ChannelOwnershipService::CHANNEL_EXTERNAL),
            'stripeConfig' => [
                'test' => [
                    'configured' => $stripeConfig->isModeConfigured(PlatformPaymentMode::TEST),
                    'connect_configured' => $stripeConfig->isConnectModeConfigured(PlatformPaymentMode::TEST),
                    'publishable_key' => filled($stripeConfig->stripePublicKey(PlatformPaymentMode::TEST)),
                    'webhook_secret' => filled($stripeConfig->stripeWebhookSecret(PlatformPaymentMode::TEST)),
                    'connect_webhook_secret' => filled($stripeConfig->stripeConnectWebhookSecret(PlatformPaymentMode::TEST)),
                ],
                'live' => [
                    'configured' => $stripeConfig->isModeConfigured(PlatformPaymentMode::LIVE),
                    'connect_configured' => $stripeConfig->isConnectModeConfigured(PlatformPaymentMode::LIVE),
                    'publishable_key' => filled($stripeConfig->stripePublicKey(PlatformPaymentMode::LIVE)),
                    'webhook_secret' => filled($stripeConfig->stripeWebhookSecret(PlatformPaymentMode::LIVE)),
                    'connect_webhook_secret' => filled($stripeConfig->stripeConnectWebhookSecret(PlatformPaymentMode::LIVE)),
                    'uses_local_mirror' => $stripeConfig->liveKeysMirroredFromTest(),
                    'has_real_keys' => $stripeConfig->hasDedicatedLiveKeys(),
                    'config_source' => $stripeConfig->liveConfigSource(),
                ],
                'sandbox_fallback' => $paymentProviderManager->canUsePlatformSandboxFallback(PlatformPaymentMode::TEST),
                'live_mirrors_test_keys' => $stripeConfig->liveKeysMirroredFromTest(),
                'live_config_source' => $stripeConfig->liveConfigSource(),
                'live_config_source_label' => $stripeConfig->liveConfigSourceLabel(),
                'diagnostics' => [
                    'STRIPE_TEST_KEY' => filled($stripeConfig->stripePublicKey(PlatformPaymentMode::TEST)),
                    'STRIPE_TEST_SECRET' => filled($stripeConfig->stripeSecretKey(PlatformPaymentMode::TEST)),
                    'STRIPE_CONNECT_TEST_CLIENT_ID' => filled($stripeConfig->stripeConnectClientId(PlatformPaymentMode::TEST)),
                    'STRIPE_LIVE_KEY' => $stripeConfig->hasDedicatedLiveKeys(),
                    'STRIPE_LIVE_SECRET' => $stripeConfig->hasDedicatedLiveKeys(),
                    'STRIPE_CONNECT_LIVE_CLIENT_ID' => filled($stripeConfig->stripeConnectClientId(PlatformPaymentMode::LIVE))
                        && $stripeConfig->hasDedicatedLiveKeys(),
                ],
            ],
            'canManagePayments' => $canManagePayments,
            'showDeveloperDiagnostics' => $canManagePayments && app()->environment(['local', 'testing']),
        ]);
    }

    public function updateMode(
        Request $request,
        PaymentProviderManager $paymentProviderManager,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && $request->user(), 404);

        $validated = $request->validate([
            'checkout_mode' => ['required', Rule::in(CheckoutMode::ALL)],
        ]);

        $targetMode = $validated['checkout_mode'];
        $previousMode = CheckoutMode::forStore($store);

        if ($targetMode === CheckoutMode::PLATFORM && ! $paymentProviderManager->isCheckoutReady($store)) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['checkout_mode' => 'Connect Stripe before enabling platform checkout.']);
        }

        if ($previousMode !== $targetMode) {
            $store = CheckoutMode::setForStore($store, $targetMode);

            $securityLogRecorder->record(
                $request,
                'payment.checkout_mode_changed',
                store: $store,
                metadata: [
                    'previous_mode' => $previousMode,
                    'new_mode' => $targetMode,
                ]
            );
        }

        return redirect()
            ->route('settings.payments.index')
            ->with('success', 'Checkout mode updated to '.CheckoutMode::label($targetMode).'.');
    }

    public function updateExternalInventory(
        Request $request,
        ChannelOwnershipService $channelOwnership,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && $request->user(), 404);

        $validated = $request->validate([
            'inventory_owner' => ['required', Rule::in([
                ChannelOwnershipService::OWNER_PLATFORM,
                ChannelOwnershipService::OWNER_EXTERNAL,
            ])],
        ]);

        $previousOwner = $channelOwnership->inventoryOwner($store, ChannelOwnershipService::CHANNEL_EXTERNAL);
        $targetOwner = $validated['inventory_owner'];

        if ($previousOwner !== $targetOwner) {
            $store = $channelOwnership->setExternalCheckoutInventoryOwner($store, $targetOwner);

            $securityLogRecorder->record(
                $request,
                'payment.external_inventory_owner_changed',
                store: $store,
                metadata: [
                    'previous_inventory_owner' => $previousOwner,
                    'new_inventory_owner' => $targetOwner,
                ]
            );
        }

        return redirect()
            ->route('settings.payments.index')
            ->with('success', $targetOwner === ChannelOwnershipService::OWNER_PLATFORM
                ? 'External orders will now reduce dashboard stock when they sync.'
                : 'External orders will be recorded without changing dashboard stock.');
    }

    public function updatePlatformPaymentMode(
        Request $request,
        PaymentProviderManager $paymentProviderManager,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && $request->user(), 404);

        $validated = $request->validate([
            'platform_payment_mode' => ['required', Rule::in(PlatformPaymentMode::ALL)],
        ]);

        $targetMode = $validated['platform_payment_mode'];
        $previousMode = PlatformPaymentMode::forStore($store);

        if ($targetMode === PlatformPaymentMode::LIVE && ! $paymentProviderManager->activeConnectedAccountForStore($store, PlatformPaymentMode::LIVE)) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['platform_payment_mode' => 'Connect an active Stripe live account before enabling live platform checkout payments.']);
        }

        if ($previousMode !== $targetMode) {
            $store = PlatformPaymentMode::setForStore($store, $targetMode);

            $securityLogRecorder->record(
                $request,
                'platform_payment_mode_changed',
                store: $store,
                metadata: [
                    'previous_mode' => $previousMode,
                    'new_mode' => $targetMode,
                ]
            );
        }

        return redirect()
            ->route('settings.payments.index')
            ->with('success', 'Platform checkout payment mode updated to '.PlatformPaymentMode::label($targetMode).'.');
    }

    public function connect(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->connectForMode($request, $connectService, $securityLogRecorder, PlatformPaymentMode::TEST);
    }

    public function connectTest(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->connectForMode($request, $connectService, $securityLogRecorder, PlatformPaymentMode::TEST);
    }

    public function connectLive(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->connectForMode($request, $connectService, $securityLogRecorder, PlatformPaymentMode::LIVE);
    }

    public function stripeReturn(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        return $this->connectReturn($request, $connectService, $securityLogRecorder, PlatformPaymentMode::TEST);
    }

    public function connectReturn(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
        string $mode,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $mode = strtolower($mode);
        $account = $connectService->handleReturn($store, $mode);

        if ($account) {
            try {
                $account = $connectService->refreshAccountStatus($account);
            } catch (\RuntimeException $exception) {
                return redirect()
                    ->route('settings.payments.index')
                    ->withErrors(['stripe' => $exception->getMessage()]);
            }

            $securityLogRecorder->record(
                $request,
                'stripe_connect_returned',
                store: $store,
                metadata: [
                    'payment_provider_account_id' => $account->id,
                    'provider_account_id' => $account->provider_account_id,
                    'status' => $account->status,
                    'charges_enabled' => $account->charges_enabled,
                    'mode' => $account->mode,
                ]
            );
        }

        return redirect()
            ->route('settings.payments.index')
            ->with('success', $account?->status === 'active'
                ? 'Stripe '.($mode === PlatformPaymentMode::LIVE ? 'live' : 'test').' account is connected and ready.'
                : 'Stripe onboarding was saved. Continue onboarding if Stripe still needs more details.');
    }

    public function refreshOnboarding(Request $request, StripeConnectService $connectService, PaymentProviderManager $paymentProviderManager, ?PaymentProviderAccount $account = null): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $account ??= $paymentProviderManager->connectAccountForStore($store, PlatformPaymentMode::TEST);
        if (! $account) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => 'Connect Stripe before continuing onboarding.']);
        }

        return $this->refreshConnectAccount($request, $connectService, $account);
    }

    public function refreshConnectAccount(Request $request, StripeConnectService $connectService, PaymentProviderAccount $account): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $account->store_id === (int) $store->id, 404);

        if (! $account->isConnect()) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => 'Only connected Stripe accounts can continue onboarding.']);
        }

        return redirect()->away($connectService->createAccountOnboardingLink($account));
    }

    public function status(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
        PaymentProviderManager $paymentProviderManager,
        ?PaymentProviderAccount $account = null,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $account ??= $paymentProviderManager->connectAccountForStore($store, PlatformPaymentMode::TEST);
        if (! $account) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => 'No connected Stripe account was found for this store.']);
        }

        return $this->refreshConnectAccountStatus($request, $connectService, $securityLogRecorder, $account);
    }

    public function refreshConnectAccountStatus(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
        PaymentProviderAccount $account,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $account->store_id === (int) $store->id, 404);

        if (! $account->isConnect()) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => 'No connected Stripe account was found for this store.']);
        }

        try {
            $account = $connectService->refreshAccountStatus($account);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => $exception->getMessage()]);
        }

        $securityLogRecorder->record(
            $request,
            'stripe_account_status_refreshed',
            store: $store,
            metadata: [
                'payment_provider_account_id' => $account->id,
                'provider_account_id' => $account->provider_account_id,
                'status' => $account->status,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'mode' => $account->mode,
            ]
        );

        return redirect()
            ->route('settings.payments.index')
            ->with('success', 'Stripe account status refreshed.');
    }

    public function disable(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
        PaymentProviderManager $paymentProviderManager,
        ?PaymentProviderAccount $account = null,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $account ??= $paymentProviderManager->connectAccountForStore($store, PlatformPaymentMode::TEST);
        if (! $account) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => 'No connected Stripe account was found for this store.']);
        }

        return $this->disconnectConnectAccount($request, $connectService, $securityLogRecorder, $account);
    }

    public function disconnectConnectAccount(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
        PaymentProviderAccount $account,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $account->store_id === (int) $store->id, 404);

        if (! $account->isConnect()) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => 'No connected Stripe account was found for this store.']);
        }

        $account = $connectService->disconnectAccount($account);

        if (
            CheckoutMode::forStore($store) === CheckoutMode::PLATFORM
            && PlatformPaymentMode::forStore($store) === $account->mode
        ) {
            $store = CheckoutMode::setForStore($store, CheckoutMode::EXTERNAL);
        }

        $securityLogRecorder->record(
            $request,
            'stripe_provider_disconnected',
            SecurityLog::SEVERITY_WARNING,
            store: $store,
            metadata: [
                'payment_provider_account_id' => $account->id,
                'provider_account_id' => $account->provider_account_id,
                'mode' => $account->mode,
            ]
        );

        return redirect()
            ->route('settings.payments.index')
            ->with('success', 'Stripe '.($account->mode === PlatformPaymentMode::LIVE ? 'live' : 'test').' account was disabled for this store.');
    }

    private function connectForMode(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
        string $mode,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && $request->user(), 404);

        try {
            $url = $connectService->startOnboarding($store, $request->user(), $mode);
            $account = $connectService->connectedAccountForStore($store, $mode);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => $exception->getMessage()]);
        }

        $securityLogRecorder->record(
            $request,
            $mode === PlatformPaymentMode::LIVE ? 'stripe_connect_live_started' : 'stripe_connect_test_started',
            store: $store,
            metadata: [
                'payment_provider_account_id' => $account?->id,
                'provider_account_id' => $account?->provider_account_id,
                'mode' => $mode,
            ]
        );

        return redirect()->away($url);
    }
}
