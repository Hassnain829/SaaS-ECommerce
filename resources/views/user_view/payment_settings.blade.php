@extends('layouts.user.user-sidebar')

@section('title', 'Payments & Channels | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
    </button>
    <div class="min-w-0">
        <h1 class="truncate text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Payments & Channels</h1>
        <p class="hidden md:block text-xs text-[#64748B]">Choose how this store accepts and syncs payments.</p>
    </div>
</header>
@endsection

@section('content')
@php
    $statusClasses = [
        'active' => 'bg-[#DCFCE7] text-[#166534]',
        'pending' => 'bg-[#FEF3C7] text-[#92400E]',
        'restricted' => 'bg-[#FEE2E2] text-[#991B1B]',
        'disabled' => 'bg-[#F1F5F9] text-[#475569]',
        'not_configured' => 'bg-[#F1F5F9] text-[#475569]',
    ];
    $connectStatus = $connectAccount?->status ?? 'not_configured';
    $connectStatusClass = $statusClasses[$connectStatus] ?? 'bg-[#F1F5F9] text-[#475569]';
    $connectReady = $connectAccount?->status === 'active' && $connectAccount?->charges_enabled;
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
                <h2 class="mt-1 text-2xl font-poppins font-semibold text-[#0F172A]">{{ $selectedStore->name }}</h2>
                <p class="mt-2 text-sm leading-6 text-[#475569]">
                    External checkout sync is always available for websites that already collect payment. Platform checkout becomes available when Stripe is connected for this store, with local sandbox fallback only for development.
                </p>
            </div>
            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm">
                <p class="font-semibold text-[#0F172A]">Stripe mode</p>
                <p class="mt-1 text-[#64748B]">{{ strtoupper($stripeConfig['mode']) }}</p>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">External checkout sync</h3>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">Use when Shopify, WooCommerce, PayPal, bank transfer, cash on delivery, or a custom site already handled payment.</p>
                </div>
                <span class="rounded-full bg-[#DCFCE7] px-3 py-1 text-xs font-bold uppercase tracking-[.6px] text-[#166534]">Ready</span>
            </div>
            <div class="mt-5 rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                Orders arrive through <code class="text-[#0F172A]">/api/v1/external/orders</code> with a payment reference and real order snapshots.
            </div>
        </article>

        <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Platform checkout</h3>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">Let this SaaS create the Stripe payment while the storefront collects card details with Stripe.js.</p>
                </div>
                <span class="rounded-full {{ $connectReady || $stripeConfig['sandbox_fallback'] ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-[#FEF2F2] text-[#991B1B]' }} px-3 py-1 text-xs font-bold uppercase tracking-[.6px]">
                    {{ $connectReady ? 'Connected' : ($stripeConfig['sandbox_fallback'] ? 'Sandbox' : 'Off') }}
                </span>
            </div>
            <div class="mt-5 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                @foreach([
                    'publishable_key' => 'Publishable key',
                    'secret_key' => 'Platform secret',
                    'platform_webhook_secret' => 'Platform webhook',
                    'connect_webhook_secret' => 'Connect webhook',
                ] as $key => $label)
                    <div class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2">
                        <p class="font-semibold text-[#0F172A]">{{ $label }}</p>
                        <p class="{{ ($stripeConfig[$key] ?? false) ? 'text-[#059669]' : 'text-[#B91C1C]' }}">
                            {{ ($stripeConfig[$key] ?? false) ? 'Configured' : 'Missing' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Connected Stripe account</h3>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">Best for production stores. The merchant owns their Stripe account; this SaaS only stores the Stripe account ID and status.</p>
                </div>
                <span class="rounded-full {{ $connectStatusClass }} px-3 py-1 text-xs font-bold uppercase tracking-[.6px]">
                    {{ str($connectStatus)->replace('_', ' ')->title() }}
                </span>
            </div>

            @if($connectAccount)
                <div class="mt-5 space-y-2 rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                    <p><span class="font-semibold text-[#0F172A]">Account:</span> {{ $connectAccount->provider_account_id }}</p>
                    <p><span class="font-semibold text-[#0F172A]">Charges:</span> {{ $connectAccount->charges_enabled ? 'Enabled' : 'Not ready' }}</p>
                    <p><span class="font-semibold text-[#0F172A]">Payouts:</span> {{ $connectAccount->payouts_enabled ? 'Enabled' : 'Not ready' }}</p>
                    @if($connectAccount->last_verified_at)
                        <p><span class="font-semibold text-[#0F172A]">Checked:</span> {{ $connectAccount->last_verified_at->diffForHumans() }}</p>
                    @endif
                    @if(! empty($connectAccount->requirements_currently_due))
                        <p class="text-[#92400E]">Stripe still needs more business details before checkout is fully ready.</p>
                    @endif
                    @if($connectAccount->requirements_disabled_reason)
                        <p class="text-[#991B1B]">{{ str($connectAccount->requirements_disabled_reason)->replace('_', ' ')->title() }}</p>
                    @endif
                </div>
            @else
                <div class="mt-5 rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
                    No Stripe account is connected for this store yet.
                </div>
            @endif

            <div class="mt-5 flex flex-wrap gap-2">
                @if($canManagePayments)
                    <form method="POST" action="{{ route('settings.payments.stripe.connect') }}">
                        @csrf
                        <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">
                            {{ $connectAccount ? 'Continue Stripe onboarding' : 'Connect Stripe' }}
                        </button>
                    </form>

                    @if($connectAccount)
                        <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                            @csrf
                            <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">
                                Refresh status
                            </button>
                        </form>

                        @if($connectAccount->status !== 'disabled')
                            <form method="POST" action="{{ route('settings.payments.stripe.disable') }}" onsubmit="return confirm('Disable Stripe platform checkout for this store? Existing orders stay unchanged.');">
                                @csrf
                                <button class="h-10 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 text-sm font-semibold text-[#991B1B] hover:bg-[#FEE2E2]">
                                    Disable
                                </button>
                            </form>
                        @endif
                    @endif
                @else
                    <p class="text-sm text-[#64748B]">You can view payment setup, but only store owners can change it.</p>
                @endif
            </div>
        </article>
    </section>

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">How checkout chooses a payment account</h3>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                <p class="font-semibold text-[#0F172A]">1. Connected Stripe</p>
                <p class="mt-1">If this store has an active default connected account, platform checkout uses it.</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                <p class="font-semibold text-[#0F172A]">2. Local sandbox</p>
                <p class="mt-1">In local/testing only, configured platform Stripe sandbox keys can be used for simulator testing.</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                <p class="font-semibold text-[#0F172A]">3. External sync</p>
                <p class="mt-1">If platform checkout is off, storefronts can still sync already-paid or pending external orders.</p>
            </div>
        </div>
    </section>
</div>
@endsection
