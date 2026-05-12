<?php

namespace App\Http\Controllers;

use App\Models\PaymentProviderAccount;
use App\Models\SecurityLog;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\StripeConnectService;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
