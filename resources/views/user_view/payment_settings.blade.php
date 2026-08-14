@extends('layouts.user.user-sidebar')

@section('title', 'Payments — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Payments" lead="Customers pay through this portal with Stripe.">
        <x-slot:actions>
            <a href="{{ route('settings.taxes.index') }}" class="inline-flex h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">Checkout &amp; tax</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
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
@endphp

<div
    class="settings-workspace-fluid settings-page payments-studio"
    x-data="paymentsConsole({
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

    <header class="pay-hero">
        <h2 class="pay-hero-title">How this store accepts payments</h2>
        <p class="pay-hero-lede">Shoppers pay through this portal with Stripe. WordPress only shows the checkout. Connect Stripe before customers can complete an order.</p>
    </header>

    @include('user_view.payments.partials.platform_panel', [
        'isPlatformMode' => true,
        'canManagePayments' => $canManagePayments,
        'connectReady' => $connectReady,
        'testConnectAccount' => $testAccount,
        'liveConnectAccount' => $liveAccount,
        'testConnectReady' => $testReady,
        'liveConnectReady' => $liveReady,
        'testNeedsAction' => $testNeedsAction,
        'stripeConfig' => $stripeConfig ?? [],
        'platformPaymentMode' => $platformPaymentMode ?? 'test',
    ])

    <section class="pay-cta">
        <div class="pay-cta-copy">
            <h3 class="pay-cta-title">Keep selling from one place</h3>
            <p class="pay-cta-lede">
                Platform checkout creates paid orders here automatically after Stripe confirms payment.
            </p>
            <div class="pay-cta-actions">
                <a href="{{ route('products') }}" class="pay-btn pay-btn-on-dark">Manage inventory</a>
                <a href="{{ route('dashboard') }}" class="pay-btn pay-btn-ghost-on-dark">Go to dashboard</a>
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
