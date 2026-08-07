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
        $capLabel = fn (bool $ready): string => $ready ? 'ready' : 'not enabled';
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
            </section>

            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Capabilities</h3>
                <p class="mt-2 text-sm text-[#64748B]">Ready means both the platform flag and this account setting are on. Secrets and full account numbers are never shown.</p>
                <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 {{ ($opsCapabilities['address_validation'] ?? false) ? 'text-emerald-800' : 'text-[#64748B]' }}">
                        Address validation: {{ $capLabel((bool) ($opsCapabilities['address_validation'] ?? false)) }}
                    </li>
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 {{ ($opsCapabilities['service_availability'] ?? false) ? 'text-emerald-800' : 'text-[#64748B]' }}">
                        Service availability: {{ $capLabel((bool) ($opsCapabilities['service_availability'] ?? false)) }}
                    </li>
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 {{ ($opsCapabilities['negotiated_rates'] ?? false) ? 'text-emerald-800' : 'text-[#64748B]' }}">
                        Negotiated rates: {{ $capLabel((bool) ($opsCapabilities['negotiated_rates'] ?? false)) }}
                    </li>
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 {{ ($opsCapabilities['checkout_rates'] ?? false) ? 'text-emerald-800' : 'text-[#64748B]' }}">
                        Checkout rates: {{ $capLabel((bool) ($opsCapabilities['checkout_rates'] ?? false)) }}
                    </li>
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 {{ ($opsCapabilities['ship_labels'] ?? false) ? 'text-emerald-800' : 'text-[#64748B]' }}">
                        Labels: {{ $capLabel((bool) ($opsCapabilities['ship_labels'] ?? false)) }}
                    </li>
                    <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 {{ ($opsCapabilities['tracking'] ?? false) ? 'text-emerald-800' : 'text-[#64748B]' }}">
                        Tracking: {{ $capLabel((bool) ($opsCapabilities['tracking'] ?? false)) }}
                    </li>
                </ul>

                @if ($canManageShipping ?? false)
                    <form method="POST" action="{{ route('settings.shipping.fedex-integrator.capabilities', $account) }}" class="mt-4 space-y-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        @csrf
                        <p class="text-sm font-semibold text-[#0F172A]">Account switches</p>
                        <p class="text-xs text-[#64748B]">Platform must also enable checkout/labels/tracking before these switches take effect.</p>
                        <label class="flex items-center gap-2 text-sm text-[#334155]">
                            <input type="hidden" name="enabled_for_checkout" value="0">
                            <input type="checkbox" name="enabled_for_checkout" value="1" @checked(old('enabled_for_checkout', $accountCapabilityToggles['enabled_for_checkout'] ?? false))>
                            Allow checkout use
                        </label>
                        <label class="flex items-center gap-2 text-sm text-[#334155]">
                            <input type="hidden" name="checkout_rates" value="0">
                            <input type="checkbox" name="checkout_rates" value="1" @checked(old('checkout_rates', $accountCapabilityToggles['checkout_rates'] ?? false)) @disabled(!($globalFlags['checkout_rates'] ?? false))>
                            Checkout rates
                            @unless ($globalFlags['checkout_rates'] ?? false)
                                <span class="text-xs text-[#94A3B8]">(platform off)</span>
                            @endunless
                        </label>
                        <label class="flex items-center gap-2 text-sm text-[#334155]">
                            <input type="hidden" name="labels" value="0">
                            <input type="checkbox" name="labels" value="1" @checked(old('labels', $accountCapabilityToggles['labels'] ?? false)) @disabled(!($globalFlags['ship_labels'] ?? false))>
                            Label purchase
                            @unless ($globalFlags['ship_labels'] ?? false)
                                <span class="text-xs text-[#94A3B8]">(platform off)</span>
                            @endunless
                        </label>
                        <label class="flex items-center gap-2 text-sm text-[#334155]">
                            <input type="hidden" name="tracking" value="0">
                            <input type="checkbox" name="tracking" value="1" @checked(old('tracking', $accountCapabilityToggles['tracking'] ?? false)) @disabled(!($globalFlags['tracking'] ?? false))>
                            Tracking
                            @unless ($globalFlags['tracking'] ?? false)
                                <span class="text-xs text-[#94A3B8]">(platform off)</span>
                            @endunless
                        </label>
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]">Save capability switches</button>
                    </form>
                @endif
            </section>
        </div>

        @if (! empty($recentShipments) && count($recentShipments))
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Recent shipments</h3>
                <p class="mt-1 text-sm text-[#64748B]">Tracking numbers are masked. Open the order to download labels or refresh tracking.</p>
                <ul class="mt-3 divide-y divide-[#F1F5F9] text-sm">
                    @foreach ($recentShipments as $shipment)
                        <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-[#0F172A]">{{ $shipment->shipment_number }}</p>
                                <p class="text-xs text-[#64748B]">
                                    ···{{ $shipment->tracking_number ? substr($shipment->tracking_number, -4) : '----' }}
                                    · {{ str($shipment->status)->replace('_', ' ')->title() }}
                                    · {{ optional($shipment->created_at)->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}
                                </p>
                            </div>
                            @if (filled(data_get($shipment->metadata, 'fedex.tracking.status_text')))
                                <span class="text-xs text-[#64748B]">{{ data_get($shipment->metadata, 'fedex.tracking.status_text') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($recentApiEvents) && count($recentApiEvents))
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Recent FedEx activity</h3>
                <p class="mt-1 text-sm text-[#64748B]">Safe summaries only — credentials and full account numbers are never shown.</p>
                <ul class="mt-3 divide-y divide-[#F1F5F9] text-sm">
                    @foreach ($recentApiEvents as $event)
                        <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-[#0F172A]">{{ str($event->action)->replace('_', ' ')->title() }}</p>
                                <p class="text-xs text-[#64748B]">
                                    {{ optional($event->created_at)->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}
                                    @if (filled(data_get($event->response_summary, 'fedex_transaction_id')))
                                        · Ref {{ data_get($event->response_summary, 'fedex_transaction_id') }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-xs font-semibold uppercase tracking-wide {{ $event->status === 'succeeded' ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ str($event->status)->replace('_', ' ')->title() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

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
