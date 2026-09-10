<?php

namespace App\Support\Payments;

use App\Models\PaymentProviderAccount;
use App\Models\Store;
use App\Support\PlatformPaymentMode;
use Carbon\CarbonInterface;

final class PaymentWorkspacePresenter
{
    /**
     * @param  array<string, mixed>  $stripeConfig
     * @return array<string, mixed>
     */
    public static function forPage(
        Store $store,
        ?PaymentProviderAccount $testConnectAccount,
        ?PaymentProviderAccount $liveConnectAccount,
        bool $testConnectReady,
        bool $liveConnectReady,
        ?PaymentProviderAccount $activeConnectAccount,
        string $platformPaymentMode,
        string $selectedPaymentMode,
        array $stripeConfig,
    ): array {
        $activeMode = $platformPaymentMode === PlatformPaymentMode::LIVE
            ? PlatformPaymentMode::LIVE
            : PlatformPaymentMode::TEST;
        $selectedMode = $selectedPaymentMode === PlatformPaymentMode::LIVE
            ? PlatformPaymentMode::LIVE
            : PlatformPaymentMode::TEST;
        $otherMode = $activeMode === PlatformPaymentMode::LIVE
            ? PlatformPaymentMode::TEST
            : PlatformPaymentMode::LIVE;

        $paymentModes = [
            PlatformPaymentMode::TEST => self::modeState(
                PlatformPaymentMode::TEST,
                $testConnectAccount,
                $testConnectReady,
                $stripeConfig,
            ),
            PlatformPaymentMode::LIVE => self::modeState(
                PlatformPaymentMode::LIVE,
                $liveConnectAccount,
                $liveConnectReady,
                $stripeConfig,
            ),
        ];

        $activeState = $paymentModes[$activeMode];
        $otherState = $paymentModes[$otherMode];

        return [
            'storeName' => (string) ($store->name ?: 'this store'),
            'activeMode' => $activeMode,
            'selectedMode' => $selectedMode,
            'otherMode' => $otherMode,
            'testReady' => $testConnectReady,
            'liveReady' => $liveConnectReady,
            'testConfigured' => (bool) ($stripeConfig[PlatformPaymentMode::TEST]['connect_configured'] ?? false),
            'liveConfigured' => (bool) ($stripeConfig[PlatformPaymentMode::LIVE]['connect_configured'] ?? false),
            'paymentModes' => $paymentModes,
            'activeState' => $activeState,
            'otherState' => $otherState,
            'activeReady' => (bool) ($activeState['configured'] && $activeState['ready']),
            'otherReady' => (bool) ($otherState['configured'] && $otherState['ready']),
            'activeVerified' => self::formatVerified($activeConnectAccount ?? $activeState['account']),
        ];
    }

    /**
     * @param  array<string, mixed>  $stripeConfig
     * @return array<string, mixed>
     */
    public static function modeState(
        string $mode,
        ?PaymentProviderAccount $account,
        bool $ready,
        array $stripeConfig,
    ): array {
        $isLive = $mode === PlatformPaymentMode::LIVE;
        $modeConfig = is_array($stripeConfig[$mode] ?? null) ? $stripeConfig[$mode] : [];
        $configured = (bool) ($modeConfig['connect_configured'] ?? false);
        $status = $account?->status;
        $disabled = $status === 'disabled';
        $requirementsDue = $account?->requirements_currently_due ?? [];
        $needsAction = $account !== null && ! $disabled && (
            $status === 'restricted'
            || filled($account->requirements_disabled_reason)
            || $requirementsDue !== []
        );
        $connected = $account !== null && ! $disabled;
        $inProgress = $connected && ! $ready;

        $pillKey = 'not-connected';
        $pillLabel = 'Not connected';
        if (! $configured) {
            $pillKey = 'unavailable';
            $pillLabel = 'Unavailable';
        } elseif ($ready) {
            $pillKey = 'ready';
            $pillLabel = 'Connected';
        } elseif ($needsAction) {
            $pillKey = 'action';
            $pillLabel = 'Action required';
        } elseif ($inProgress) {
            $pillKey = 'action';
            $pillLabel = 'Setup in progress';
        }

        $lastVerified = self::formatVerified($account);
        $accountId = $account?->maskedProviderAccountId();

        return [
            'key' => $mode,
            'label' => $isLive ? 'Live payments' : 'Test payments',
            'accountTitle' => $isLive ? 'Stripe live account' : 'Stripe test account',
            'helper' => $isLive
                ? 'Real customer payments'
                : 'Safe sandbox payments for testing platform checkout. No real money is charged.',
            'account' => $account,
            'ready' => $ready,
            'configured' => $configured,
            'connected' => $connected,
            'needsAction' => $needsAction,
            'inProgress' => $inProgress,
            'pillKey' => $pillKey,
            'pillLabel' => $pillLabel,
            'accountIdText' => $connected
                ? ($accountId ?: 'Pending')
                : ($configured ? 'Connect an account' : 'Platform setup required'),
            'rowHelper' => ! $configured
                ? ($isLive ? 'Live connection unavailable' : 'Test connection unavailable')
                : ($connected ? ($isLive ? 'Real customer payments' : 'Safe checkout testing') : 'Stripe hosted onboarding'),
            'unavailableMessage' => $isLive
                ? 'Live Stripe connection is not available on this platform environment yet. Use test mode for now or contact the platform admin.'
                : 'Stripe test connection is not available on this platform environment yet. Contact the platform admin.',
            'connectLabel' => $disabled
                ? ($isLive ? 'Reconnect Stripe live account' : 'Reconnect Stripe test account')
                : ($isLive ? 'Connect Stripe live account' : 'Connect Stripe test account'),
            'continueLabel' => $isLive ? 'Continue live onboarding' : 'Continue test onboarding',
            'lastVerified' => $lastVerified,
            'lastVerifiedLower' => strtolower($lastVerified),
            'usesLocalMirror' => $isLive && (bool) ($stripeConfig['live_mirrors_test_keys'] ?? ($modeConfig['uses_local_mirror'] ?? false)),
            'chargesEnabled' => (bool) ($account?->charges_enabled),
            'payoutsEnabled' => (bool) ($account?->payouts_enabled),
            'onboardingComplete' => $account?->onboarding_completed_at !== null,
        ];
    }

    public static function formatVerified(?PaymentProviderAccount $account): string
    {
        $at = $account?->last_verified_at;
        if (! $at instanceof CarbonInterface) {
            return 'Not checked yet';
        }

        if ($at->greaterThanOrEqualTo(now()->subMinute())) {
            return 'Just now';
        }

        return $at->diffForHumans();
    }
}
