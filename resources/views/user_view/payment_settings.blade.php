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

    <section id="stripe-provider-card" class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Payment provider</p>
                <h3 class="mt-1 text-xl font-poppins font-semibold text-[#0F172A]">Stripe</h3>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">
                    Connect Stripe to accept payments through platform checkout. Stripe handles the secure payment setup. You do not need to paste secret keys.
                </p>
            </div>
            <span class="w-fit rounded-full {{ $stripeStatusClass }} px-3 py-1 text-xs font-bold uppercase tracking-[.6px]">{{ $stripeStatusLabel }}</span>
        </div>

        @if($connectAccount)
            <div class="mt-5 grid gap-3 text-sm md:grid-cols-3">
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Charges</p>
                    <p class="mt-1 font-semibold text-[#0F172A]">{{ $connectAccount->charges_enabled ? 'Enabled' : 'Not ready' }}</p>
                </div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Payouts</p>
                    <p class="mt-1 font-semibold text-[#0F172A]">{{ $connectAccount->payouts_enabled ? 'Enabled' : 'Not ready' }}</p>
                </div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Last checked</p>
                    <p class="mt-1 font-semibold text-[#0F172A]">{{ $connectAccount->last_verified_at?->diffForHumans() ?? 'Not checked yet' }}</p>
                </div>
            </div>

            @if($connectNeedsAction)
                <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                    Stripe needs more account details before platform checkout can be enabled.
                </div>
            @endif
        @else
            <div class="mt-5 rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
                No Stripe account is connected for this store yet.
            </div>
        @endif

        @if($canManagePayments)
            <div class="mt-5 flex flex-wrap gap-2">
                @if(! $connectAccount || $connectDisabled)
                    <form method="POST" action="{{ route('settings.payments.stripe.connect') }}">
                        @csrf
                        <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">
                            {{ $connectDisabled ? 'Reconnect Stripe' : 'Connect Stripe' }}
                        </button>
                    </form>
                @elseif($connectReady)
                    <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                        @csrf
                        <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">Refresh status</button>
                    </form>
                    <form method="POST" action="{{ route('settings.payments.stripe.disable') }}" onsubmit="return confirm('Disable Stripe platform checkout for this store? Existing orders stay unchanged.');">
                        @csrf
                        <button class="h-10 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 text-sm font-semibold text-[#991B1B] hover:bg-[#FEE2E2]">Disable</button>
                    </form>
                @else
                    <form method="GET" action="{{ route('settings.payments.stripe.refresh') }}">
                        <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">Continue setup</button>
                    </form>
                    <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                        @csrf
                        <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">Refresh status</button>
                    </form>
                @endif
            </div>
        @else
            <p class="mt-5 text-sm text-[#64748B]">You can view payment setup, but your store role cannot change it.</p>
        @endif
    </section>

    @if($canManagePayments)
        <details id="developer-diagnostics" class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
            <summary class="cursor-pointer text-lg font-poppins font-semibold text-[#0F172A]">Developer diagnostics</summary>
            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Stripe mode</p>
                    <p class="mt-1 text-[#64748B]">{{ (string) str($stripeConfig['mode'])->lower() === 'live' ? 'Live' : 'Test' }}</p>
                </div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Platform webhook</p>
                    <p class="mt-1 {{ $stripeConfig['platform_webhook_secret'] ? 'text-[#059669]' : 'text-[#B91C1C]' }}">{{ $stripeConfig['platform_webhook_secret'] ? 'Configured' : 'Missing' }}</p>
                </div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Connect webhook</p>
                    <p class="mt-1 {{ $stripeConfig['connect_webhook_secret'] ? 'text-[#059669]' : 'text-[#B91C1C]' }}">{{ $stripeConfig['connect_webhook_secret'] ? 'Configured' : 'Missing' }}</p>
                </div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Platform publishable key</p>
                    <p class="mt-1 {{ $stripeConfig['publishable_key'] ? 'text-[#059669]' : 'text-[#B91C1C]' }}">{{ $stripeConfig['publishable_key'] ? 'Configured' : 'Missing' }}</p>
                </div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Platform secret key</p>
                    <p class="mt-1 {{ $stripeConfig['secret_key'] ? 'text-[#059669]' : 'text-[#B91C1C]' }}">{{ $stripeConfig['secret_key'] ? 'Configured' : 'Missing' }}</p>
                </div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Platform sandbox fallback</p>
                    <p class="mt-1 text-[#64748B]">Local/testing only</p>
                </div>
            </div>
            @if(! $stripeConfig['connect_webhook_secret'])
                <p class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                    Connect webhook is missing. Connected account events may not update automatically.
                </p>
            @endif
        </details>
    @endif
</div>
@endsection
