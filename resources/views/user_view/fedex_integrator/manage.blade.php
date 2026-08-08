@extends('layouts.user.user-sidebar')

@section('title', 'FedEx Center — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="FedEx Center" lead="Manage checkout rates, shipping, tracking, and your connected FedEx account.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation', ['tab' => 'carriers']) }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back to carriers</a>
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
        $checkoutBadgeTone = match ($checkoutRatesStatus ?? 'needs_setup') {
            'working', 'active' => 'success',
            'disabled' => 'warning',
            default => 'warning',
        };
        $next = $primaryNextAction ?? null;
        $labelsTone = in_array(($labelsBadge ?? ''), ['Working', 'Enabled — not tested'], true) ? 'success' : 'warning';
        $trackingTone = in_array(($trackingBadge ?? ''), ['Working', 'Enabled — not tested'], true) ? 'success' : 'warning';
    @endphp
    <div class="mx-auto max-w-[920px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        {{-- Account health --}}
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Account health</p>
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
                    <x-ui.badge tone="info">{{ $envLabel }}</x-ui.badge>
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
                    <a href="{{ route('settings.delivery.setup.delivery-option') }}" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Manage checkout shipping</a>
                </div>
            @endif
        </section>

        {{-- Checkout rates --}}
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-[#0F172A]">Checkout rates</h3>
                    <p class="mt-1 text-sm text-[#64748B]">{{ $checkoutRatesDetail ?? '' }}</p>
                </div>
                <x-ui.badge :tone="$checkoutBadgeTone">{{ $checkoutRatesBadge ?? 'Needs setup' }}</x-ui.badge>
            </div>

            @if (($fedExCheckoutMethods ?? collect())->isNotEmpty())
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($fedExCheckoutMethods as $method)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[#F1F5F9] bg-[#F8FAFC] px-3 py-2">
                            <span class="font-medium text-[#0F172A]">{{ $method->carrier_service_name ?: $method->name }}</span>
                            <span class="text-xs text-[#64748B]">{{ $method->shippingZone?->name ?? 'Unassigned area' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canManageShipping ?? false)
                <div class="mt-4">
                    <a href="{{ route('settings.delivery.setup.delivery-option') }}" class="inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]">
                        Manage checkout shipping
                    </a>
                </div>
            @endif
        </section>

        {{-- Shipping & labels --}}
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-[#0F172A]">Shipping &amp; labels</h3>
                    <p class="mt-1 text-sm text-[#64748B]">{{ $labelsDetail ?? '' }}</p>
                </div>
                <x-ui.badge :tone="$labelsTone">{{ $labelsBadge ?? (($labelsEnabled ?? false) ? 'Enabled' : 'Not ready') }}</x-ui.badge>
            </div>
            <p class="mt-3 text-sm text-[#334155]">
                <span class="font-semibold tabular-nums">{{ $readyToShipCount ?? 0 }}</span> order(s) ready to ship
            </p>

            @if (! empty($recentShipments) && count($recentShipments))
                <ul class="mt-4 divide-y divide-[#F1F5F9] text-sm">
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
                            @if ($shipment->order)
                                <a href="{{ route('orderViewDetails', $shipment->order) }}" class="text-xs font-semibold text-[#1D4ED8]">Order {{ $shipment->order->order_number }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-[#94A3B8]">No FedEx shipments yet for this connection.</p>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                @if (($readyToShipCount ?? 0) > 0)
                    <a href="{{ route('orders') }}" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-xs font-semibold text-[#475569]">View orders ready to ship</a>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('shipments.index'))
                    <a href="{{ route('shipments.index', ['provider' => 'fedex']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]">View shipments</a>
                @endif
            </div>
        </section>

        {{-- Tracking --}}
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-[#0F172A]">Tracking</h3>
                    <p class="mt-1 text-sm text-[#64748B]">{{ $trackingDetail ?? '' }}</p>
                </div>
                <x-ui.badge :tone="$trackingTone">{{ $trackingBadge ?? (($trackingEnabled ?? false) ? 'Enabled' : 'Not ready') }}</x-ui.badge>
            </div>
            <dl class="mt-4 grid gap-3 sm:grid-cols-3 text-sm">
                <div class="rounded-lg border border-[#F1F5F9] bg-[#F8FAFC] px-3 py-2">
                    <dt class="text-xs text-[#64748B]">In transit / open</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-[#0F172A]">{{ $inTransitCount ?? 0 }}</dd>
                </div>
                <div class="rounded-lg border border-[#F1F5F9] bg-[#F8FAFC] px-3 py-2">
                    <dt class="text-xs text-[#64748B]">Exceptions</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-[#0F172A]">{{ $exceptionCount ?? 0 }}</dd>
                </div>
                <div class="rounded-lg border border-[#F1F5F9] bg-[#F8FAFC] px-3 py-2">
                    <dt class="text-xs text-[#64748B]">Delivered (30d)</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums text-[#0F172A]">{{ $deliveredCount ?? 0 }}</dd>
                </div>
            </dl>
            @if (\Illuminate\Support\Facades\Route::has('shipments.index'))
                <div class="mt-4">
                    <a href="{{ route('shipments.index', ['provider' => 'fedex']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]">View shipments</a>
                </div>
            @endif
        </section>

        {{-- Returns --}}
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-[#0F172A]">Returns</h3>
                    <p class="mt-1 text-sm text-[#64748B]">
                        @if ($labelsEnabled ?? false)
                            Return labels use this FedEx connection when you create them from an approved return.
                        @else
                            Enable label purchase before creating FedEx return labels.
                        @endif
                    </p>
                </div>
                <x-ui.badge :tone="($labelsEnabled ?? false) ? 'success' : 'warning'">
                    {{ ($labelsEnabled ?? false) ? 'Available' : 'Not ready' }}
                </x-ui.badge>
            </div>
            <p class="mt-3 text-sm text-[#334155]">
                <span class="font-semibold tabular-nums">{{ $openReturnsNeedingLabels ?? 0 }}</span> approved return(s) open
            </p>

            @if (! empty($recentReturnShipments) && count($recentReturnShipments))
                <ul class="mt-4 divide-y divide-[#F1F5F9] text-sm">
                    @foreach ($recentReturnShipments as $shipment)
                        <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-[#0F172A]">{{ $shipment->shipment_number }}</p>
                                <p class="text-xs text-[#64748B]">
                                    ···{{ $shipment->tracking_number ? substr($shipment->tracking_number, -4) : '----' }}
                                    · {{ str($shipment->status)->replace('_', ' ')->title() }}
                                </p>
                            </div>
                            @if ($shipment->order)
                                <a href="{{ route('orderViewDetails', $shipment->order) }}" class="text-xs font-semibold text-[#1D4ED8]">Order {{ $shipment->order->order_number }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-[#94A3B8]">No FedEx return shipments yet for this connection.</p>
            @endif

            <div class="mt-4">
                <a href="{{ route('orders') }}" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-xs font-semibold text-[#475569]">View returns</a>
            </div>
        </section>

        {{-- Connection & account (lower) --}}
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Connection &amp; account</h3>
            <p class="mt-1 text-sm text-[#64748B]">Verify, reconnect, or disconnect. Advanced switches stay collapsed for operators.</p>

            @if (($lineageAccounts ?? collect())->isNotEmpty())
                <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">Previous connections</p>
                    <p class="mt-1 text-xs text-[#64748B]">Shipment history from earlier reconnects stays linked to this FedEx Center.</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($lineageAccounts as $prior)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-white bg-white px-3 py-2">
                                <span class="font-medium text-[#0F172A]">{{ $prior->display_name }}</span>
                                <span class="text-xs text-[#64748B]">
                                    ···{{ $prior->maskedAccountNumber() }}
                                    @if ($prior->replaced_at)
                                        · Replaced {{ $prior->replaced_at->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y') }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

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

                <details class="mt-5 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Advanced capability switches</summary>
                    <form method="POST" action="{{ route('settings.shipping.fedex-integrator.capabilities', $account) }}" class="mt-3 space-y-3">
                        @csrf
                        <p class="text-xs text-[#64748B]">Prefer Checkout Shipping and shipping preferences for day-to-day control. Platform flags still apply.</p>
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
                </details>

                @if (! empty($recentApiEvents) && count($recentApiEvents))
                    <details class="mt-4 rounded-xl border border-[#E2E8F0] bg-white p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Recent connection activity</summary>
                        <ul class="mt-3 divide-y divide-[#F1F5F9] text-sm">
                            @foreach ($recentApiEvents as $event)
                                <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="font-medium text-[#0F172A]">{{ str($event->action)->replace('_', ' ')->title() }}</p>
                                        <p class="text-xs text-[#64748B]">
                                            {{ optional($event->created_at)->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}
                                        </p>
                                    </div>
                                    <span class="text-xs font-semibold uppercase tracking-wide {{ $event->status === 'succeeded' ? 'text-emerald-700' : 'text-amber-700' }}">
                                        {{ str($event->status)->replace('_', ' ')->title() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                <form method="POST" action="{{ route('settings.shipping.fedex-integrator.disconnect', $account) }}" class="mt-6" onsubmit="return confirm('Disconnect this FedEx account? Your account number ending will be kept for records, but shipping credentials will be removed.');">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700">Disconnect FedEx account</button>
                </form>
            @endif
        </section>
    </div>
@endsection
