@extends('layouts.user.user-sidebar')

@section('title', 'Payments & Channels | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
    </button>
    <div class="min-w-0">
        <h1 class="truncate text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Payments & Channels</h1>
        <p class="hidden md:block text-xs text-[#64748B]">Choose how this store accepts payments and sends orders into your dashboard.</p>
    </div>
</header>
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
    $stripeStatusClass = 'bg-[#F1F5F9] text-[#475569]';
    if ($connectReady) {
        $stripeStatusLabel = 'Connected';
        $stripeStatusClass = 'bg-[#DCFCE7] text-[#166534]';
    } elseif ($connectNeedsAction) {
        $stripeStatusLabel = 'Action required';
        $stripeStatusClass = 'bg-[#FEF3C7] text-[#92400E]';
    } elseif ($connectInProgress) {
        $stripeStatusLabel = 'Setup in progress';
        $stripeStatusClass = 'bg-[#FEF3C7] text-[#92400E]';
    } elseif ($connectDisabled) {
        $stripeStatusLabel = 'Disabled';
        $stripeStatusClass = 'bg-[#FEE2E2] text-[#991B1B]';
    }

    $platformStatusLabel = $connectReady
        ? ($checkoutMode === CheckoutMode::PLATFORM ? 'Enabled' : 'Stripe connected')
        : ($hasConnectAccount && ! $connectDisabled ? 'Setup required' : 'Not enabled');
    $platformStatusDetail = $connectReady
        ? 'Stripe connected'
        : ($hasConnectAccount && ! $connectDisabled ? 'Continue Stripe setup' : 'Connect Stripe to use platform checkout');
    $platformStatusClass = $connectReady
        ? 'bg-[#DCFCE7] text-[#166534]'
        : ($hasConnectAccount && ! $connectDisabled ? 'bg-[#FEF3C7] text-[#92400E]' : 'bg-[#F1F5F9] text-[#475569]');
@endphp

<div class="settings-workspace-fluid settings-page">
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

    <div class="settings-page-stack">
        <section id="checkout-mode-cards" class="settings-panel">
            <header class="settings-panel-header">
                <h2 class="settings-panel-title">How does this store accept payments?</h2>
                <p class="settings-panel-lead">Choose one checkout mode for this store. You can switch later.</p>
            </header>

            <div class="settings-choice-grid">
                <article @class([
                    'settings-choice-card',
                    'settings-choice-card-selected' => $checkoutMode === CheckoutMode::EXTERNAL,
                ])>
                    <div class="settings-choice-card-top">
                        <div>
                            <h3 class="settings-choice-card-title">External checkout</h3>
                            <p class="settings-choice-card-copy">Payments happen on your existing website. Completed orders sync into this dashboard.</p>
                        </div>
                        <span class="settings-pill settings-pill-success">Available</span>
                    </div>
                    <div class="settings-choice-card-foot">
                        <a href="{{ route('developer-storefront.settings') }}" class="settings-btn settings-btn-secondary">Integration instructions</a>
                        @if($canManagePayments)
                            @if($checkoutMode === CheckoutMode::EXTERNAL)
                                <span class="settings-btn settings-btn-current">Current mode</span>
                            @else
                                <form method="POST" action="{{ route('settings.payments.mode') }}">
                                    @csrf
                                    <input type="hidden" name="checkout_mode" value="{{ CheckoutMode::EXTERNAL }}">
                                    <button class="settings-btn settings-btn-primary">Use external checkout</button>
                                </form>
                            @endif
                        @endif
                    </div>
                </article>

                <article @class([
                    'settings-choice-card',
                    'settings-choice-card-selected' => $checkoutMode === CheckoutMode::PLATFORM,
                ])>
                    <div class="settings-choice-card-top">
                        <div>
                            <h3 class="settings-choice-card-title">Platform checkout</h3>
                            <p class="settings-choice-card-copy">Customers pay through this platform. Orders are created automatically after payment succeeds.</p>
                        </div>
                        <span @class([
                            'settings-pill',
                            'settings-pill-success' => $connectReady,
                            'settings-pill-warning' => $connectNeedsAction || $connectInProgress,
                            'settings-pill-muted' => ! $connectReady && ! $connectNeedsAction && ! $connectInProgress,
                        ])>{{ $platformStatusLabel }}</span>
                    </div>
                    <p class="text-sm text-[#64748B]">{{ $platformStatusDetail }}@if($checkoutMode === CheckoutMode::PLATFORM). This is the active checkout mode.@elseif($connectReady) Stripe is ready — switch when you are.@endif</p>
                    @if($canManagePayments)
                        <div class="settings-choice-card-foot">
                            @if($connectReady)
                                @if($checkoutMode === CheckoutMode::PLATFORM)
                                    <span class="settings-btn settings-btn-current">Current mode</span>
                                @else
                                    <form method="POST" action="{{ route('settings.payments.mode') }}">
                                        @csrf
                                        <input type="hidden" name="checkout_mode" value="{{ CheckoutMode::PLATFORM }}">
                                        <button class="settings-btn settings-btn-primary">Use platform checkout</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                                    @csrf
                                    <button class="settings-btn settings-btn-secondary">Refresh status</button>
                                </form>
                            @elseif($hasConnectAccount && ! $connectDisabled)
                                <form method="GET" action="{{ route('settings.payments.stripe.refresh') }}">
                                    <button class="settings-btn settings-btn-primary">Continue Stripe setup</button>
                                </form>
                                <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                                    @csrf
                                    <button class="settings-btn settings-btn-secondary">Refresh status</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('settings.payments.stripe.connect') }}">
                                    @csrf
                                    <button class="settings-btn settings-btn-primary">{{ $connectDisabled ? 'Reconnect Stripe' : 'Connect Stripe' }}</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            </div>
        </section>

        <section id="stripe-provider-card" class="settings-panel">
            <header class="settings-panel-header">
                <h2 class="settings-panel-title">Stripe</h2>
                <p class="settings-panel-lead">Connect separate Stripe test and live accounts for platform checkout through secure Stripe hosted onboarding.</p>
            </header>

            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <p class="text-sm font-semibold text-[#0F172A]">Platform checkout payment mode</p>
                <p class="mt-1 text-sm text-[#64748B]">Test mode is for sandbox payments. Live mode charges real customers.</p>
                @if($canManagePayments ?? false)
                    <form method="POST" action="{{ route('settings.payments.platform-payment-mode') }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="flex items-start gap-2 text-sm text-[#334155]">
                            <input type="radio" name="platform_payment_mode" value="test" @checked(($platformPaymentMode ?? 'test') === 'test') class="mt-1">
                            <span><span class="font-semibold text-[#0F172A]">Test mode</span><span class="mt-0.5 block text-xs text-[#64748B]">Uses the connected Stripe test account or local sandbox fallback.</span></span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-[#334155]">
                            <input type="radio" name="platform_payment_mode" value="live" @checked(($platformPaymentMode ?? 'test') === 'live') class="mt-1" @disabled(! ($liveConnectReady ?? false))>
                            <span><span class="font-semibold text-[#0F172A]">Live mode</span><span class="mt-0.5 block text-xs text-[#64748B]">Requires an active Stripe live connected account.</span></span>
                        </label>
                        <button type="submit" class="settings-btn settings-btn-primary">Save payment mode</button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-[#475569]">Current mode: {{ ($platformPaymentMode ?? 'test') === 'live' ? 'Live mode' : 'Test mode' }}</p>
                @endif
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @include('user_view.partials.stripe_connect_account_card', [
                    'title' => 'Stripe test account',
                    'description' => 'Use this to test platform checkout safely with Stripe sandbox payments. No real money is charged.',
                    'mode' => 'test',
                    'account' => $testConnectAccount ?? null,
                    'ready' => $testConnectReady ?? false,
                    'modeConfig' => $stripeConfig['test'] ?? [],
                    'connectRoute' => route('settings.payments.stripe.connect.test'),
                    'canManagePayments' => $canManagePayments ?? false,
                ])
                @include('user_view.partials.stripe_connect_account_card', [
                    'title' => 'Stripe live account',
                    'description' => 'Use this only when you are ready to accept real customer payments.',
                    'mode' => 'live',
                    'account' => $liveConnectAccount ?? null,
                    'ready' => $liveConnectReady ?? false,
                    'modeConfig' => $stripeConfig['live'] ?? [],
                    'connectRoute' => route('settings.payments.stripe.connect.live'),
                    'canManagePayments' => $canManagePayments ?? false,
                ])
            </div>
        </section>

        <details class="settings-hub-details">
            <summary>Store ownership &amp; inventory source</summary>
            <div class="settings-hub-details-body">
                <p class="mb-4 text-sm text-[#64748B]">Shows how checkout, payment, shipping, and inventory are owned for each channel mode.</p>
                <div class="settings-choice-grid">
                    <article @class([
                        'settings-choice-card',
                        'settings-choice-card-selected' => $isExternalManaged ?? false,
                    ])>
                        <div class="settings-choice-card-top">
                            <h3 class="settings-choice-card-title">External managed</h3>
                            @if($isExternalManaged ?? false)
                                <span class="settings-pill settings-pill-success">Active</span>
                            @endif
                        </div>
                        <p class="settings-choice-card-copy">External storefront manages checkout, payment, shipping, and fulfillment. This dashboard records orders and activity.</p>
                        <ul class="space-y-1 text-xs text-[#475569]">
                            <li>Checkout: {{ ucfirst($externalChannelConfig['checkout_owner'] ?? 'external') }}</li>
                            <li>Payment: {{ ucfirst($externalChannelConfig['payment_owner'] ?? 'external') }}</li>
                            <li>Shipping: {{ ucfirst($externalChannelConfig['shipping_owner'] ?? 'external') }}</li>
                            <li>Fulfillment: {{ ucfirst($externalChannelConfig['fulfillment_owner'] ?? 'external') }}</li>
                            <li>Inventory: {{ ($usesPlatformInventoryForExternal ?? true) ? 'Platform managed' : 'External managed' }}</li>
                        </ul>

                        @if($canManagePayments ?? false)
                            <form method="POST" action="{{ route('settings.payments.external-inventory') }}" class="mt-3 space-y-3 border-t border-[#E2E8F0] pt-3">
                                @csrf
                                <p class="text-sm font-semibold text-[#0F172A]">Inventory source for external orders</p>
                                <label class="flex items-start gap-2 text-sm text-[#334155]">
                                    <input type="radio" name="inventory_owner" value="platform" @checked(($externalInventoryOwner ?? 'platform') === 'platform') class="mt-1">
                                    <span><span class="font-semibold text-[#0F172A]">Use dashboard inventory</span><span class="mt-0.5 block text-xs text-[#64748B]">External orders reduce dashboard stock when they sync.</span></span>
                                </label>
                                <label class="flex items-start gap-2 text-sm text-[#334155]">
                                    <input type="radio" name="inventory_owner" value="external" @checked(($externalInventoryOwner ?? 'platform') === 'external') class="mt-1">
                                    <span><span class="font-semibold text-[#0F172A]">External storefront manages inventory</span><span class="mt-0.5 block text-xs text-[#64748B]">Orders are recorded here without changing dashboard stock.</span></span>
                                </label>
                                <button type="submit" class="settings-btn settings-btn-primary">Save inventory source</button>
                            </form>
                        @endif
                    </article>

                    <article @class([
                        'settings-choice-card',
                        'settings-choice-card-selected' => $isPlatformManaged ?? false,
                    ])>
                        <div class="settings-choice-card-top">
                            <h3 class="settings-choice-card-title">Platform managed</h3>
                            @if($isPlatformManaged ?? false)
                                <span class="settings-pill settings-pill-success">Active</span>
                            @endif
                        </div>
                        <p class="settings-choice-card-copy">Platform checkout manages checkout, payment, delivery, and fulfillment from this dashboard.</p>
                        <ul class="space-y-1 text-xs text-[#475569]">
                            <li>Checkout: {{ ucfirst($platformChannelConfig['checkout_owner'] ?? 'platform') }}</li>
                            <li>Payment: {{ ucfirst($platformChannelConfig['payment_owner'] ?? 'platform') }}</li>
                            <li>Shipping: {{ ucfirst($platformChannelConfig['shipping_owner'] ?? 'platform') }}</li>
                            <li>Fulfillment: {{ ucfirst($platformChannelConfig['fulfillment_owner'] ?? 'platform') }}</li>
                        </ul>
                    </article>
                </div>
            </div>
        </details>

        @if($showDeveloperDiagnostics ?? false)
        <details id="developer-diagnostics" class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
            <summary class="cursor-pointer text-lg font-poppins font-semibold text-[#0F172A]">Developer diagnostics</summary>
            <p class="mt-2 text-xs text-[#64748B]">Platform/server Stripe configuration only. Store owners connect accounts through Stripe hosted onboarding — they never paste keys here.</p>
            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach($stripeConfig['diagnostics'] ?? [] as $label => $configured)
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <p class="font-semibold text-[#0F172A]">{{ $label }} configured</p>
                        <p class="mt-1 {{ $configured ? 'text-[#059669]' : 'text-[#B91C1C]' }}">{{ $configured ? 'Yes' : 'No' }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Platform sandbox fallback</p>
                    <p class="mt-1 text-[#64748B]">{{ ($stripeConfig['sandbox_fallback'] ?? false) ? 'Enabled for local/testing' : 'Disabled' }}</p>
                </div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Live Stripe config</p>
                    <p class="mt-1 text-[#64748B]">{{ $stripeConfig['live_config_source_label'] ?? 'Missing' }}</p>
                </div>
                @if($stripeConfig['live_mirrors_test_keys'] ?? false)
                    <div class="rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 sm:col-span-2 lg:col-span-3">
                        <p class="font-semibold text-[#92400E]">Local dev: live Stripe uses test platform keys</p>
                        <p class="mt-1 text-xs text-[#92400E]">Add real STRIPE_LIVE_KEY and STRIPE_LIVE_SECRET to server config for production live Connect. Values are never shown here.</p>
                    </div>
                @elseif(($stripeConfig['live_config_source'] ?? 'missing') === 'real')
                    <div class="rounded-xl border border-[#BBF7D0] bg-[#F0FDF4] px-4 py-3 sm:col-span-2 lg:col-span-3">
                        <p class="font-semibold text-[#166534]">Real live Stripe platform config is active on this server.</p>
                    </div>
                @endif
            </div>
        </details>
    @endif
    </div>
</div>
@endsection
