<?php

namespace App\Http\Controllers;

use App\Models\PaymentProviderAccount;
use App\Models\SecurityLog;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\StripeConnectService;
use App\Services\SecurityLogRecorder;
use App\Support\CheckoutMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    public function index(Request $request, PaymentProviderManager $paymentProviderManager): View|RedirectResponse
    {
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

        $connectAccount = $accounts->firstWhere('connection_type', 'connect');
        $platformAccount = $accounts->firstWhere('connection_type', 'platform');

        return view('user_view.payment_settings', [
            'selectedStore' => $store,
            'accounts' => $accounts,
            'connectAccount' => $connectAccount,
            'platformAccount' => $platformAccount,
            'activeConnectAccount' => $paymentProviderManager->activeConnectedAccountForStore($store),
            'checkoutMode' => CheckoutMode::forStore($store),
            'stripeConfig' => [
                'mode' => (string) config('payments.stripe.mode', 'test'),
                'publishable_key' => filled(config('payments.stripe.key')),
                'secret_key' => filled(config('payments.stripe.secret')),
                'platform_webhook_secret' => filled(config('payments.stripe.webhook_secret')),
                'connect_webhook_secret' => filled(config('payments.stripe.connect_webhook_secret')),
                'sandbox_fallback' => $paymentProviderManager->canUsePlatformSandboxFallback(),
            ],
            'canManagePayments' => $request->user()?->canManageSettings($store) ?? false,
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

        if ($targetMode === CheckoutMode::PLATFORM && ! $paymentProviderManager->activeConnectedAccountForStore($store)) {
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

    public function connect(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && $request->user(), 404);

        try {
            $account = $connectService->createOrRetrieveConnectedAccount($store, $request->user());
            $url = $connectService->createAccountOnboardingLink($account);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => $exception->getMessage()]);
        }

        $securityLogRecorder->record(
            $request,
            'stripe_connect_started',
            store: $store,
            metadata: [
                'payment_provider_account_id' => $account->id,
                'provider_account_id' => $account->provider_account_id,
                'mode' => $account->mode,
            ]
        );

        return redirect()->away($url);
    }

    public function stripeReturn(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $account = $this->currentConnectAccount($store->id);
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
                ]
            );
        }

        return redirect()
            ->route('settings.payments.index')
            ->with('success', $account?->status === 'active'
                ? 'Stripe is connected and ready for platform checkout.'
                : 'Stripe onboarding was saved. Continue onboarding if Stripe still needs more details.');
    }

    public function refreshOnboarding(Request $request, StripeConnectService $connectService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $account = $this->currentConnectAccount($store->id);
        if (! $account) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => 'Connect Stripe before continuing onboarding.']);
        }

        return redirect()->away($connectService->createAccountOnboardingLink($account));
    }

    public function status(
        Request $request,
        StripeConnectService $connectService,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $account = $this->currentConnectAccount($store->id);
        if (! $account) {
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
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $account = $this->currentConnectAccount($store->id);
        if (! $account) {
            return redirect()
                ->route('settings.payments.index')
                ->withErrors(['stripe' => 'No connected Stripe account was found for this store.']);
        }

        $account = $connectService->disableLocally($account);
        if (CheckoutMode::forStore($store) === CheckoutMode::PLATFORM) {
            $store = CheckoutMode::setForStore($store, CheckoutMode::EXTERNAL);
        }

        $securityLogRecorder->record(
            $request,
            'stripe_provider_disabled',
            SecurityLog::SEVERITY_WARNING,
            store: $store,
            metadata: [
                'payment_provider_account_id' => $account->id,
                'provider_account_id' => $account->provider_account_id,
            ]
        );

        return redirect()
            ->route('settings.payments.index')
            ->with('success', 'Stripe platform checkout was disabled for this store. You can reconnect anytime.');
    }

    private function currentConnectAccount(int $storeId): ?PaymentProviderAccount
    {
        return PaymentProviderAccount::query()
            ->where('store_id', $storeId)
            ->where('provider', 'stripe')
            ->where('mode', (string) config('payments.stripe.mode', 'test'))
            ->where('connection_type', 'connect')
            ->latest('id')
            ->first();
    }
}
