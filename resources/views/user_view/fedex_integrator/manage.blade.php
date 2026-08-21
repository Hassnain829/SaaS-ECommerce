@extends('layouts.user.user-sidebar')

@section('title', 'FedEx — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="FedEx" lead="Connection status, checkout rates, and label availability for your FedEx account.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation') }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back to Delivery</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    @php
        $statusKey = match ($account->connection_status) {
            'connected' => 'connected',
            'setup_required', 'pending_validation', 'not_connected' => 'setup_required',
            'failed', 'blocked_by_fedex' => 'needs_attention',
            'disabled' => 'disabled',
            default => 'needs_attention',
        };
        $checkoutBadgeTone = match ($checkoutRatesStatus ?? 'needs_setup') {
            'working', 'active' => 'success',
            'disabled' => 'warning',
            default => 'warning',
        };
        $next = $primaryNextAction ?? null;
        $labelsTone = in_array(($labelsBadge ?? ''), ['Working', 'Enabled — not tested', 'Available'], true) ? 'success' : 'warning';
        $trackingTone = in_array(($trackingBadge ?? ''), ['Working', 'Enabled — not tested', 'Available'], true) ? 'success' : 'warning';
    @endphp
    <div class="mx-auto max-w-[920px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Account</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[#0F172A]">{{ $account->display_name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">
                        Account {{ $account->maskedAccountNumber() }} · {{ $presenter->billingLabel() }}
                        @if ($account->defaultOriginLocation)
                            · Ship from {{ $account->defaultOriginLocation->name }}
                        @endif
                    </p>
                    @if ($account->last_verified_at)
                        <p class="mt-1 text-xs text-[#94A3B8]">Last verified {{ $account->last_verified_at->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($account->environment === \App\Models\CarrierAccount::ENVIRONMENT_LIVE)
                        <x-ui.badge tone="info">Live account</x-ui.badge>
                    @else
                        <x-ui.badge tone="warning">Test account</x-ui.badge>
                    @endif
                    <x-ui.status-pill :status="$statusKey">
                        {{ str($account->connection_status)->replace('_', ' ')->title() }}
                    </x-ui.status-pill>
                </div>
            </div>

            @if ($account->last_error_message)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                    {{ $account->last_error_message }}
                </p>
            @endif

            @if ($canManageShipping ?? false)
                <div class="mt-4 flex flex-wrap gap-2">
                    @if (($next['form'] ?? null) === 'verify')
                        <form method="POST" action="{{ route('settings.shipping.fedex-integrator.verify', $account) }}">
                            @csrf
                            <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-brand px-5 text-sm font-bold text-white">{{ $next['label'] ?? 'Verify connection' }}</button>
                        </form>
                    @elseif (! empty($next['href']))
                        <a href="{{ $next['href'] }}" class="inline-flex h-10 items-center rounded-lg bg-brand px-5 text-sm font-bold text-white">{{ $next['label'] }}</a>
                    @endif
                    <a href="{{ route('settings.delivery.checkout-options') }}" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Manage checkout shipping</a>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Checkout rates</p>
                    <p class="mt-2"><x-ui.badge :tone="$checkoutBadgeTone">{{ $checkoutRatesBadge ?? 'Needs setup' }}</x-ui.badge></p>
                    <p class="mt-2 text-xs text-[#64748B]">{{ $checkoutRatesDetail ?? '' }}</p>
                </div>
                <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Labels</p>
                    <p class="mt-2"><x-ui.badge :tone="$labelsTone">{{ $labelsBadge ?? (($labelsEnabled ?? false) ? 'Available' : 'Needs attention') }}</x-ui.badge></p>
                </div>
                <div class="rounded-xl border border-[#F1F5F9] bg-[#F8FAFC] px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Tracking</p>
                    <p class="mt-2"><x-ui.badge :tone="$trackingTone">{{ $trackingBadge ?? (($trackingEnabled ?? false) ? 'Available' : 'Needs attention') }}</x-ui.badge></p>
                </div>
            </div>

            @if (($fedExCheckoutMethods ?? collect())->isNotEmpty())
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-[#64748B]">Enabled checkout services</p>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($fedExCheckoutMethods as $method)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[#F1F5F9] bg-[#F8FAFC] px-3 py-2">
                            <span class="font-medium text-[#0F172A]">{{ $method->carrier_service_name ?: $method->name }}</span>
                            <span class="text-xs text-[#64748B]">{{ $method->shippingZone?->name ?? 'Unassigned area' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                @if ($canManageShipping ?? false)
                    <a href="{{ route('settings.delivery.checkout-options') }}" class="inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]">Manage checkout shipping</a>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('shipments.index'))
                    <a href="{{ route('shipments.index', ['provider' => 'fedex']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-xs font-semibold text-[#475569]">View shipments</a>
                @endif
                @if (($readyToShipCount ?? 0) > 0)
                    <a href="{{ route('orders') }}" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-xs font-semibold text-[#475569]">{{ $readyToShipCount }} order(s) ready to ship</a>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Connection &amp; account</h3>
            <p class="mt-1 text-sm text-[#64748B]">Verify, reconnect, or disconnect this FedEx account.</p>

            @if ($canManageShipping ?? false)
                <div class="mt-4 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('settings.shipping.fedex-integrator.verify', $account) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Verify connection</button>
                    </form>
                    @if (! empty($resumableSession))
                        <form method="POST" action="{{ route('settings.shipping.fedex-integrator.resume', $resumableSession) }}">
                            @csrf
                            <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-4 text-sm font-semibold text-[#92400E]">Resume verification</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('settings.shipping.fedex-integrator.reconnect', $account) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Reconnect FedEx</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('settings.shipping.fedex-integrator.disconnect', $account) }}" class="mt-6" onsubmit="return confirm('Disconnect this FedEx account? Your account number ending will be kept for records, but shipping credentials will be removed.');">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700">Disconnect FedEx account</button>
                </form>
            @endif
        </section>
    </div>
@endsection
