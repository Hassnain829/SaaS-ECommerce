@extends('layouts.user.user-sidebar')

@section('title', 'Payments & Channels | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Payments &amp; Channels" lead="Configure transaction processing and external sales channels.">
        <x-slot:actions>
        <a href="{{ route('settings.taxes.index') }}" class="payments-btn payments-btn-primary shrink-0">Checkout &amp; tax</a>
    </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    use App\Support\CheckoutMode;

    $checkoutMode = $checkoutMode ?? CheckoutMode::forStore($selectedStore);
    $currentModeLabel = CheckoutMode::label($checkoutMode);
    $connectStatus = $connectAccount?->status;
    $requirementsDue = $connectAccount?->requirements_currently_due ?? [];
    $connectReady = $activeConnectAccount !== null;
    $hasConnectAccount = $connectAccount !== null;
    $connectDisabled = $connectStatus === 'disabled';
    $connectNeedsAction = $hasConnectAccount
        && ! $connectDisabled
        && (
            $connectStatus === 'restricted'
            || $connectAccount?->requirements_disabled_reason
            || ! empty($requirementsDue)
        );
    $connectInProgress = $hasConnectAccount && ! $connectReady && ! $connectNeedsAction && ! $connectDisabled;

    $stripeStatusLabel = 'Not connected';
    if ($connectReady) {
        $stripeStatusLabel = 'Connected';
    } elseif ($connectNeedsAction) {
        $stripeStatusLabel = 'Action required';
    } elseif ($connectInProgress) {
        $stripeStatusLabel = 'Setup in progress';
    } elseif ($connectDisabled) {
        $stripeStatusLabel = 'Disabled';
    }

    $platformStatusLabel = $connectReady
        ? ($checkoutMode === CheckoutMode::PLATFORM ? 'Enabled' : 'Stripe connected')
        : ($hasConnectAccount && ! $connectDisabled ? 'Setup required' : 'Not enabled');
    $platformStatusDetail = $connectReady
        ? 'Stripe connected'
        : ($hasConnectAccount && ! $connectDisabled ? 'Continue Stripe setup' : 'Connect Stripe to use platform checkout');

    $initialStripePanel = ($platformPaymentMode ?? 'test') === 'live' ? 'live' : 'test';
@endphp

<div
    class="settings-workspace-fluid settings-page payments-console"
    x-data="paymentsConsole(
        @js($initialStripePanel),
        @js($selectedStore->id),
        @js($canManagePayments ?? false),
        @js($liveConnectReady ?? false)
    )"
>
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="settings-status-strip">
        <span class="settings-status-pill">Checkout mode <strong>{{ $currentModeLabel }}</strong></span>
        <span @class([
            'settings-status-pill',
            'settings-status-pill-ready' => $connectReady,
            'settings-status-pill-pending' => $connectNeedsAction || $connectInProgress,
        ])>Stripe <strong>{{ $stripeStatusLabel }}</strong></span>
        <span @class([
            'settings-status-pill',
            'settings-status-pill-ready' => $connectReady && $checkoutMode === CheckoutMode::PLATFORM,
            'settings-status-pill-pending' => $connectReady && $checkoutMode !== CheckoutMode::PLATFORM,
        ])>Platform checkout <strong>{{ $platformStatusLabel }}</strong></span>
    </div>

    @include('user_view.payments.partials.checkout_modes', [
        'checkoutMode' => $checkoutMode,
        'canManagePayments' => $canManagePayments ?? false,
        'connectReady' => $connectReady,
        'connectNeedsAction' => $connectNeedsAction,
        'connectInProgress' => $connectInProgress,
        'hasConnectAccount' => $hasConnectAccount,
        'connectDisabled' => $connectDisabled,
        'platformStatusLabel' => $platformStatusLabel,
        'platformStatusDetail' => $platformStatusDetail,
    ])

    @include('user_view.payments.partials.stripe_integration', [
        'canManagePayments' => $canManagePayments ?? false,
        'platformPaymentMode' => $platformPaymentMode ?? 'test',
        'liveConnectReady' => $liveConnectReady ?? false,
        'testConnectAccount' => $testConnectAccount ?? null,
        'liveConnectAccount' => $liveConnectAccount ?? null,
        'testConnectReady' => $testConnectReady ?? false,
        'stripeConfig' => $stripeConfig ?? [],
    ])

    @include('user_view.payments.partials.ownership_inventory', [
        'canManagePayments' => $canManagePayments ?? false,
        'isExternalManaged' => $isExternalManaged ?? false,
        'isPlatformManaged' => $isPlatformManaged ?? false,
        'externalChannelConfig' => $externalChannelConfig ?? [],
        'platformChannelConfig' => $platformChannelConfig ?? [],
        'usesPlatformInventoryForExternal' => $usesPlatformInventoryForExternal ?? true,
        'externalInventoryOwner' => $externalInventoryOwner ?? 'platform',
    ])

    @if($showDeveloperDiagnostics ?? false)
        @include('user_view.payments.partials.developer_diagnostics', [
            'stripeConfig' => $stripeConfig ?? [],
        ])
    @endif
</div>
@endsection
