@extends('layouts.user.user-sidebar')

@section('title', 'Manage FedEx — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Manage FedEx connection" lead="Review your connected FedEx account and keep billing between you and FedEx.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation', ['tab' => 'carriers']) }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    @php
        $envLabel = $account->environment === \App\Models\CarrierAccount::ENVIRONMENT_LIVE ? 'Live' : 'Sandbox';
        $statusKey = match ($account->connection_status) {
            'connected' => 'connected',
            'setup_required', 'pending_validation', 'not_connected' => 'setup_required',
            'failed', 'blocked_by_fedex' => 'needs_attention',
            'disabled' => 'disabled',
            default => 'needs_attention',
        };
    @endphp
    <div class="mx-auto max-w-[920px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">FedEx account</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[#0F172A]">{{ $account->display_name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">
                        Your FedEx account is connected for this store. FedEx charges the connected merchant account — not the platform.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.badge tone="info">{{ $envLabel }}</x-ui.badge>
                    <x-ui.status-pill :status="$statusKey">
                        {{ str($account->connection_status)->replace('_', ' ')->title() }}
                    </x-ui.status-pill>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Connection overview</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">Account</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $account->maskedAccountNumber() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">Billing</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $presenter->billingLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">Environment</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $envLabel }}</dd>
                    </div>
                    @if ($account->defaultOriginLocation)
                        <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                            <dt class="text-[#64748B]">Ship-from</dt>
                            <dd class="font-medium text-[#0F172A]">{{ $account->defaultOriginLocation->name }}</dd>
                        </div>
                    @endif
                    @if ($account->last_verified_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-[#64748B]">Last verified</dt>
                            <dd class="font-medium text-[#0F172A]">{{ $account->last_verified_at->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($account->last_error_message)
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                        {{ $account->last_error_message }}
                    </p>
                @endif

                @if ($account->connection_status === 'failed' && filled($account->fedex_active_store_key))
                    <p class="mt-3 text-sm text-[#64748B]">
                        Your previous active FedEx connection rules still apply. Use Reconnect to replace this account safely, or Disconnect to clear credentials.
                    </p>
                @endif
            </section>

            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Capabilities</h3>
                <p class="mt-2 text-sm text-[#64748B]">Checkout rates, labels, and tracking stay off until later production phases.</p>
                <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-[#64748B]">Rates: not enabled yet</li>
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-[#64748B]">Labels: not enabled yet</li>
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-[#64748B]">Tracking: not enabled yet</li>
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-[#64748B]">Pickup: not enabled yet</li>
                </ul>
            </section>
        </div>

        @if ($canManageShipping ?? false)
            <section class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5">
                <h3 class="text-sm font-semibold text-[#0F172A]">Connection actions</h3>
                <div class="mt-3 flex flex-wrap gap-3">
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
            </section>

            <form method="POST" action="{{ route('settings.shipping.fedex-integrator.disconnect', $account) }}" onsubmit="return confirm('Disconnect this FedEx account? Your account number ending will be kept for records, but shipping credentials will be removed.');">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700">Disconnect FedEx account</button>
            </form>
        @endif
    </div>
@endsection
