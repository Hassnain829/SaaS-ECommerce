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

<div class="mx-auto max-w-6xl space-y-5 py-2 md:py-4">
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Store payment setup</p>
                <h2 class="mt-1 text-2xl font-poppins font-semibold text-[#0F172A]">Payments & Channels</h2>
                <p class="mt-2 text-sm leading-6 text-[#475569]">
                    Choose how this store accepts payments and sends orders into your dashboard.
                </p>
            </div>
            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Current mode</p>
                <p class="mt-1 font-semibold text-[#0F172A]">{{ $currentModeLabel }}</p>
            </div>
        </div>
    </section>

    <section id="checkout-mode-cards" class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <div class="flex flex-col gap-1">
            <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">How does this store accept payments?</h3>
            <p class="text-sm text-[#64748B]">Choose one active checkout mode for this store.</p>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border {{ $checkoutMode === CheckoutMode::EXTERNAL ? 'border-[#0052CC] bg-[#F8FBFF]' : 'border-[#CBD5E1] bg-white' }} p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 class="text-lg font-poppins font-semibold text-[#0F172A]">External checkout</h4>
                        <p class="mt-2 text-sm leading-6 text-[#64748B]">
                            Use this if your existing website already accepts payments through Shopify, WooCommerce, WordPress, PayPal, bank transfer, cash on delivery, or another checkout.
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full bg-[#DCFCE7] px-3 py-1 text-xs font-bold uppercase tracking-[.6px] text-[#166534]">Available</span>
                </div>

                <ul class="mt-4 space-y-2 text-sm text-[#475569]">
                    <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>Payment happens on your existing website.</span></li>
                    <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>Completed orders sync into this dashboard.</span></li>
                    <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>No payment provider setup is required here.</span></li>
                </ul>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <a href="{{ route('developer-storefront.settings') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">
                        View integration instructions
                    </a>
                    @if($canManagePayments)
                        @if($checkoutMode === CheckoutMode::EXTERNAL)
                            <span class="inline-flex h-10 items-center justify-center rounded-lg bg-[#E0F2FE] px-4 text-sm font-semibold text-[#075985]">Current mode</span>
                        @else
                            <form method="POST" action="{{ route('settings.payments.mode') }}">
                                @csrf
                                <input type="hidden" name="checkout_mode" value="{{ CheckoutMode::EXTERNAL }}">
                                <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">Use external checkout</button>
                            </form>
                        @endif
                    @endif
                </div>
            </article>

            <article class="rounded-2xl border {{ $checkoutMode === CheckoutMode::PLATFORM ? 'border-[#0052CC] bg-[#F8FBFF]' : 'border-[#CBD5E1] bg-white' }} p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 class="text-lg font-poppins font-semibold text-[#0F172A]">Platform checkout</h4>
                        <p class="mt-2 text-sm leading-6 text-[#64748B]">
                            Use this platform's checkout flow to collect customer payments and create orders automatically.
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full {{ $platformStatusClass }} px-3 py-1 text-xs font-bold uppercase tracking-[.6px]">{{ $platformStatusLabel }}</span>
                </div>

                <ul class="mt-4 space-y-2 text-sm text-[#475569]">
                    <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>Customers pay through the platform checkout.</span></li>
                    <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>Orders are created after payment succeeds.</span></li>
                    <li class="flex gap-2"><span class="mt-2 h-1.5 w-1.5 rounded-full bg-[#0052CC]"></span><span>A connected payment provider is required.</span></li>
                </ul>

                <div class="mt-4 rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm">
                    <p class="font-semibold text-[#0F172A]">{{ $platformStatusDetail }}</p>
                    @if($checkoutMode === CheckoutMode::PLATFORM)
                        <p class="mt-1 text-[#64748B]">This is the active checkout mode for the store.</p>
                    @elseif($connectReady)
                        <p class="mt-1 text-[#64748B]">Stripe is ready. You can switch this store to platform checkout.</p>
                    @endif
                </div>

                @if($canManagePayments)
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        @if($connectReady)
                            @if($checkoutMode === CheckoutMode::PLATFORM)
                                <span class="inline-flex h-10 items-center justify-center rounded-lg bg-[#E0F2FE] px-4 text-sm font-semibold text-[#075985]">Current mode</span>
                            @else
                                <form method="POST" action="{{ route('settings.payments.mode') }}">
                                    @csrf
                                    <input type="hidden" name="checkout_mode" value="{{ CheckoutMode::PLATFORM }}">
                                    <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">Use platform checkout</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                                @csrf
                                <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">Refresh status</button>
                            </form>
                        @elseif($hasConnectAccount && ! $connectDisabled)
                            <form method="GET" action="{{ route('settings.payments.stripe.refresh') }}">
                                <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">Continue Stripe setup</button>
                            </form>
                            <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                                @csrf
                                <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">Refresh status</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('settings.payments.stripe.connect') }}">
                                @csrf
                                <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">
                                    {{ $connectDisabled ? 'Reconnect Stripe' : 'Connect Stripe' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </article>
        </div>
    </section>

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <div class="flex flex-col gap-1">
            <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Checkout and fulfillment mode</h3>
            <p class="text-sm text-[#64748B]">Ownership shows who manages checkout, payment, shipping, and fulfillment for this store.</p>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border {{ ($isExternalManaged ?? false) ? 'border-[#0052CC] bg-[#F8FBFF]' : 'border-[#CBD5E1] bg-white' }} p-5">
                <div class="flex items-start justify-between gap-3">
                    <h4 class="text-lg font-poppins font-semibold text-[#0F172A]">External managed</h4>
                    @if($isExternalManaged ?? false)
                        <span class="shrink-0 rounded-full bg-[#DCFCE7] px-3 py-1 text-xs font-bold uppercase tracking-[.6px] text-[#166534]">Active</span>
                    @endif
                </div>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">
                    External storefront manages checkout, payment, shipping, and fulfillment. This dashboard records orders, customers, payment references, shipping details, shipment updates, and delivery history.
                </p>
                <ul class="mt-4 space-y-1 text-xs text-[#475569]">
                    <li>Checkout: {{ ucfirst($externalChannelConfig['checkout_owner'] ?? 'external') }}</li>
                    <li>Payment: {{ ucfirst($externalChannelConfig['payment_owner'] ?? 'external') }}</li>
                    <li>Shipping: {{ ucfirst($externalChannelConfig['shipping_owner'] ?? 'external') }}</li>
                    <li>Fulfillment: {{ ucfirst($externalChannelConfig['fulfillment_owner'] ?? 'external') }}</li>
                    <li>
                        Inventory:
                        {{ ($usesPlatformInventoryForExternal ?? true) ? 'Platform managed' : 'External managed' }}
                    </li>
                </ul>

                <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">Inventory source for external orders</p>
                    <p class="mt-1 text-sm leading-6 text-[#64748B]">
                        Choose where stock is managed for external checkout orders.
                    </p>
                    @if($usesPlatformInventoryForExternal ?? true)
                        <p class="mt-2 text-sm leading-6 text-[#475569]">
                            External orders reduce dashboard stock when they sync.
                        </p>
                    @else
                        <p class="mt-2 text-sm leading-6 text-[#475569]">
                            External orders are recorded here, but dashboard stock is not changed.
                        </p>
                    @endif

                    @if($canManagePayments ?? false)
                        <form method="POST" action="{{ route('settings.payments.external-inventory') }}" class="mt-4 space-y-3">
                            @csrf
                            <label class="flex items-start gap-2 text-sm text-[#334155]">
                                <input
                                    type="radio"
                                    name="inventory_owner"
                                    value="platform"
                                    @checked(($externalInventoryOwner ?? 'platform') === 'platform')
                                    class="mt-1"
                                >
                                <span>
                                    <span class="font-semibold text-[#0F172A]">Use dashboard inventory</span>
                                    <span class="mt-0.5 block text-xs text-[#64748B]">Best when your external storefront reads products and stock from this platform.</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-2 text-sm text-[#334155]">
                                <input
                                    type="radio"
                                    name="inventory_owner"
                                    value="external"
                                    @checked(($externalInventoryOwner ?? 'platform') === 'external')
                                    class="mt-1"
                                >
                                <span>
                                    <span class="font-semibold text-[#0F172A]">Inventory managed by external storefront</span>
                                    <span class="mt-0.5 block text-xs text-[#64748B]">Best when Shopify, WooCommerce, or another system is the stock source of truth.</span>
                                </span>
                            </label>
                            <button type="submit" class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">
                                Save inventory source
                            </button>
                        </form>
                    @endif
                </div>
            </article>

            <article class="rounded-2xl border {{ ($isPlatformManaged ?? false) ? 'border-[#0052CC] bg-[#F8FBFF]' : 'border-[#CBD5E1] bg-white' }} p-5">
                <div class="flex items-start justify-between gap-3">
                    <h4 class="text-lg font-poppins font-semibold text-[#0F172A]">Platform managed</h4>
                    @if($isPlatformManaged ?? false)
                        <span class="shrink-0 rounded-full bg-[#DCFCE7] px-3 py-1 text-xs font-bold uppercase tracking-[.6px] text-[#166534]">Active</span>
                    @endif
                </div>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">
                    Platform checkout manages checkout, payment, delivery options, and fulfillment from this dashboard.
                </p>
                <ul class="mt-4 space-y-1 text-xs text-[#475569]">
                    <li>Checkout: {{ ucfirst($platformChannelConfig['checkout_owner'] ?? 'platform') }}</li>
                    <li>Payment: {{ ucfirst($platformChannelConfig['payment_owner'] ?? 'platform') }}</li>
                    <li>Shipping: {{ ucfirst($platformChannelConfig['shipping_owner'] ?? 'platform') }}</li>
                    <li>Fulfillment: {{ ucfirst($platformChannelConfig['fulfillment_owner'] ?? 'platform') }}</li>
                </ul>
            </article>
        </div>
    </section>

    <section id="stripe-provider-card" class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6 space-y-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Payment provider</p>
            <h3 class="mt-1 text-xl font-poppins font-semibold text-[#0F172A]">Stripe</h3>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">
                Connect separate Stripe test and live accounts for platform checkout through secure Stripe hosted onboarding. No Stripe secret keys are entered or stored in this dashboard.
            </p>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
            <p class="text-sm font-semibold text-[#0F172A]">Platform checkout payment mode</p>
            <p class="mt-1 text-sm leading-6 text-[#64748B]">Test mode is for safe sandbox payments. Live mode charges real customers.</p>
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
                    <button type="submit" class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">Save payment mode</button>
                </form>
            @else
                <p class="mt-3 text-sm text-[#475569]">Current mode: {{ ($platformPaymentMode ?? 'test') === 'live' ? 'Live mode' : 'Test mode' }}</p>
            @endif
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
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
@endsection
