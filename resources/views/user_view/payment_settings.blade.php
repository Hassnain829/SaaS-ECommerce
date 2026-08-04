@extends('layouts.user.user-sidebar')

@section('title', 'Payments — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Payments" lead="Choose how this store takes payments.">
        <x-slot:actions>
            <a href="{{ route('settings.taxes.index') }}" class="inline-flex h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">Checkout &amp; tax</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    use App\Support\CheckoutMode;

    $checkoutMode = $checkoutMode ?? CheckoutMode::forStore($selectedStore);
    $isExternalMode = $checkoutMode === CheckoutMode::EXTERNAL;
    $isPlatformMode = $checkoutMode === CheckoutMode::PLATFORM;

    $testAccount = $testConnectAccount ?? null;
    $liveAccount = $liveConnectAccount ?? null;
    $testReady = (bool) ($testConnectReady ?? false);
    $liveReady = (bool) ($liveConnectReady ?? false);

    $testRequirementsDue = $testAccount?->requirements_currently_due ?? [];
    $testDisabled = ($testAccount?->status ?? null) === 'disabled';
    $testNeedsAction = $testAccount
        && ! $testDisabled
        && (
            ($testAccount->status ?? null) === 'restricted'
            || $testAccount->requirements_disabled_reason
            || ! empty($testRequirementsDue)
        );

    $connectReady = $activeConnectAccount !== null;
    $canManagePayments = (bool) ($canManagePayments ?? false);
    $initialView = $isPlatformMode ? 'platform' : 'external';

    $externalConfig = $externalChannelConfig ?? [];
    $platformConfig = $platformChannelConfig ?? [];
    $inventoryOwner = $externalInventoryOwner ?? 'platform';
    $inventoryIsDashboard = $inventoryOwner !== 'external';

    $ownerLabel = static function (?string $owner): string {
        return ($owner ?? '') === 'platform' ? 'Dashboard' : 'Your website';
    };
@endphp

<div
    class="settings-workspace-fluid settings-page payments-studio"
    x-data="paymentsConsole({
        initialView: @js($initialView),
        checkoutMode: @js($checkoutMode),
        storeId: @js($selectedStore->id),
        canManage: @js($canManagePayments),
        liveReady: @js($liveReady),
    })"
>
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="pay-alert pay-alert-error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Mode switcher --}}
    <header class="pay-hero">
        <h2 class="pay-hero-title">How does this store accept payments?</h2>
        <p class="pay-hero-lede">Pick one checkout mode. You can switch later — this only changes where customers pay.</p>

        <div class="pay-mode-switch" role="tablist" aria-label="Checkout mode">
            <button
                type="button"
                role="tab"
                id="tab-external"
                class="pay-mode-tab"
                :class="{ 'is-active': viewMode === 'external' }"
                :aria-selected="viewMode === 'external'"
                @click="switchTab('external')"
            >
                External checkout
                <span class="pay-mode-tab-hint" x-show="isExternalCurrent" x-cloak>(current)</span>
            </button>
            <button
                type="button"
                role="tab"
                id="tab-platform"
                class="pay-mode-tab pay-mode-tab-stripe"
                :class="{ 'is-active': viewMode === 'platform' }"
                :aria-selected="viewMode === 'platform'"
                @click="switchTab('platform')"
            >
                <x-brand.stripe-logo variant="badge" :size="22" />
                Platform checkout
                <span class="pay-mode-tab-hint" x-show="isPlatformCurrent" x-cloak>(current)</span>
            </button>
        </div>
        <p class="pay-hero-note" x-show="viewingOtherMode" x-cloak>
            You are previewing another mode. Use the switch button below to make it active for this store.
        </p>
    </header>

    <div
        id="content-external"
        class="pay-panel space-y-5"
        x-show="viewMode === 'external'"
        @unless($isExternalMode) x-cloak @endunless
        role="tabpanel"
        aria-labelledby="tab-external"
    >
        @include('user_view.payments.partials.external_panel', [
            'checkoutMode' => $checkoutMode,
            'isExternalMode' => $isExternalMode,
            'canManagePayments' => $canManagePayments,
            'externalConfig' => $externalConfig,
            'ownerLabel' => $ownerLabel,
            'inventoryIsDashboard' => $inventoryIsDashboard,
            'inventoryOwner' => $inventoryOwner,
            'usesPlatformInventoryForExternal' => $usesPlatformInventoryForExternal ?? true,
        ])
    </div>

    <div
        id="content-platform"
        class="pay-panel space-y-5"
        x-show="viewMode === 'platform'"
        @unless($isPlatformMode) x-cloak @endunless
        role="tabpanel"
        aria-labelledby="tab-platform"
    >
        @include('user_view.payments.partials.platform_panel', [
            'checkoutMode' => $checkoutMode,
            'isPlatformMode' => $isPlatformMode,
            'canManagePayments' => $canManagePayments,
            'connectReady' => $connectReady,
            'testConnectAccount' => $testAccount,
            'liveConnectAccount' => $liveAccount,
            'testConnectReady' => $testReady,
            'liveConnectReady' => $liveReady,
            'testNeedsAction' => $testNeedsAction,
            'stripeConfig' => $stripeConfig ?? [],
            'platformConfig' => $platformConfig,
            'ownerLabel' => $ownerLabel,
            'platformPaymentMode' => $platformPaymentMode ?? 'test',
        ])
    </div>

    {{-- Shared CTA --}}
    <section class="pay-cta">
        <div class="pay-cta-copy">
            <h3 class="pay-cta-title">Keep selling from one place</h3>
            <p class="pay-cta-lede" x-show="viewMode === 'external'" @unless($isExternalMode) x-cloak @endunless>
                Your website takes payment. This dashboard still tracks orders, stock, and fulfillment for the store.
            </p>
            <p class="pay-cta-lede" x-show="viewMode === 'platform'" @unless($isPlatformMode) x-cloak @endunless>
                Platform checkout creates paid orders here automatically after Stripe confirms payment.
            </p>
            <div class="pay-cta-actions">
                <a href="{{ route('products') }}" class="pay-btn pay-btn-on-dark">Manage inventory</a>
                <a href="{{ route('analytics') }}" class="pay-btn pay-btn-ghost-on-dark">View analytics</a>
            </div>
        </div>
        <div class="pay-cta-art" aria-hidden="true">
            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                <path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/>
            </svg>
        </div>
    </section>

    @if($showDeveloperDiagnostics ?? false)
        @include('user_view.payments.partials.developer_diagnostics', [
            'stripeConfig' => $stripeConfig ?? [],
        ])
    @endif
</div>
@endsection
