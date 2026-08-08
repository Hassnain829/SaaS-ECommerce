@extends('layouts.user.user-sidebar')

@section('title', 'Shipments — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Shipments" :lead="'Track labels and deliveries for '.($selectedStore?->name ?? 'this store').'.'">
        <x-slot:search>
            <form method="GET" action="{{ route('shipments.index') }}" class="flex w-full items-center gap-2">
                <input type="hidden" name="status" value="{{ $currentStatus }}">
                <input type="hidden" name="provider" value="{{ $currentProvider }}">
                <input name="q" value="{{ $search }}" class="h-9 min-w-0 flex-1 rounded-md border border-border bg-surface px-3 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="Search shipment #, tracking, or order…">
                <button class="inline-flex h-9 shrink-0 items-center rounded-md border border-border bg-surface px-3 text-xs font-semibold text-ink-secondary hover:bg-surface-muted">Search</button>
            </form>
        </x-slot:search>
        <x-slot:actions>
            <a href="{{ route('orders') }}" class="hidden h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted xl:inline-flex">View orders</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    $providerLabels = [
        'all' => 'All providers',
        'fedex' => 'FedEx',
        'manual' => 'Manual',
        'usps' => 'USPS',
    ];
@endphp
<div class="w-full space-y-4">
    @include('user_view.partials.flash_success')

    <section class="merchant-card space-y-4 p-4">
        @if ($search !== '')
            <div class="flex items-center justify-between rounded-md bg-surface-muted px-3 py-2 text-sm text-ink-secondary">
                <span>Search: <span class="font-semibold text-ink">{{ $search }}</span></span>
                <a href="{{ route('shipments.index', ['status' => $currentStatus, 'provider' => $currentProvider]) }}" class="font-semibold text-brand hover:text-brand-hover">Clear</a>
            </div>
        @endif

        <form method="GET" action="{{ route('shipments.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="q" value="{{ $search }}">
            <label class="space-y-1">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Status</span>
                <select name="status" class="h-9 rounded-md border border-border bg-surface px-3 text-sm text-ink">
                    <option value="all" @selected($currentStatus === 'all')>All statuses</option>
                    @foreach ($shipmentStatuses as $status)
                        <option value="{{ $status }}" @selected($currentStatus === $status)>{{ \App\Support\OrderLifecycle::shipmentStatusLabel($status) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-ink-muted">Provider</span>
                <select name="provider" class="h-9 rounded-md border border-border bg-surface px-3 text-sm text-ink">
                    @foreach ($providerLabels as $value => $label)
                        <option value="{{ $value }}" @selected($currentProvider === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="ui-btn ui-btn-secondary h-9">Apply filters</button>
        </form>
    </section>

    <section class="merchant-card overflow-hidden">
        <div class="border-b border-border px-4 py-3.5 md:px-5">
            <h2 class="text-sm font-semibold text-ink">Shipment list</h2>
            <p class="text-xs text-ink-muted">Labels, tracking, and fulfillment status across your store.</p>
        </div>

        @if ($shipments->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-sm">
                    <thead class="border-b border-border bg-surface-muted/70 text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Shipment</th>
                            <th class="px-4 py-2.5 text-left">Order</th>
                            <th class="px-4 py-2.5 text-left">Recipient</th>
                            <th class="px-4 py-2.5 text-left">Carrier</th>
                            <th class="px-4 py-2.5 text-left">Status</th>
                            <th class="px-4 py-2.5 text-left">Tracking</th>
                            <th class="px-4 py-2.5 text-left">Cost</th>
                            <th class="px-4 py-2.5 text-left">Created</th>
                            <th class="px-5 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shipments as $shipment)
                            @php
                                $isFedExManaged = $shipment->isFedExManagedShipment();
                                $providerLabel = $isFedExManaged
                                    ? 'FedEx'
                                    : ($shipment->carrierAccount?->isUsps()
                                        ? 'USPS'
                                        : ($shipment->carrierAccount?->display_name ?: 'Manual'));
                                $labels = array_values((array) data_get($shipment->metadata, 'fedex.labels', []));
                                $hasDownloadableLabel = collect($labels)->contains(fn ($label) => is_array($label) && filled($label['path'] ?? null));
                                $customerTrackingUrl = $isFedExManaged && filled($shipment->tracking_number)
                                    ? $shipment->publicFedExTrackingUrl($selectedStore->slug ?? null)
                                    : null;
                                $recipient = $shipment->order?->addresses?->firstWhere('type', 'shipping')
                                    ?? $shipment->order?->addresses?->first();
                                $canVoid = $isFedExManaged
                                    && $canManageOrders
                                    && ! in_array($shipment->status, [
                                        \App\Models\Shipment::STATUS_CANCELLED,
                                        \App\Models\Shipment::STATUS_DELIVERED,
                                    ], true)
                                    && \Illuminate\Support\Facades\Route::has('shipments.fedex.cancel');
                                $canRefresh = $isFedExManaged
                                    && $canManageOrders
                                    && filled($shipment->tracking_number)
                                    && \Illuminate\Support\Facades\Route::has('shipments.fedex.tracking.refresh');
                            @endphp
                            <tr class="border-b border-border/80 hover:bg-surface-muted/40">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-ink">{{ $shipment->shipment_number }}</p>
                                    @if ($shipment->isReturn())
                                        <p class="text-xs text-ink-muted">Return</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($shipment->order)
                                        <a href="{{ route('orderViewDetails', $shipment->order) }}" class="font-semibold text-brand hover:text-brand-hover">{{ $shipment->order->order_number }}</a>
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-secondary">
                                    {{ $recipient?->name ?: '—' }}
                                    @if ($recipient?->city)
                                        <p class="text-xs text-ink-muted">{{ $recipient->city }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-secondary">
                                    <span class="font-medium text-ink">{{ $providerLabel }}</span>
                                    @if ($shipment->carrier_service)
                                        <p class="text-xs text-ink-muted">{{ $shipment->carrier_service }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ \App\Support\OrderLifecycle::shipmentStatusBadgeClass($shipment->status) }}">
                                        {{ \App\Support\OrderLifecycle::shipmentStatusLabel($shipment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($shipment->tracking_number)
                                        <p class="font-medium tabular-nums text-ink">{{ $shipment->tracking_number }}</p>
                                    @else
                                        <span class="text-ink-muted">No tracking yet</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-secondary">
                                    @if ($shipment->shipping_cost !== null)
                                        {{ number_format((float) $shipment->shipping_cost, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-secondary">{{ $shipment->created_at?->format('M j, Y') }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        @if ($shipment->order)
                                            <a href="{{ route('orderViewDetails', $shipment->order) }}" class="rounded-md border border-border px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">View order</a>
                                        @endif
                                        @if ($shipment->isReturn() && $shipment->order_return_id && $shipment->order)
                                            <a href="{{ route('orderViewDetails', $shipment->order) }}#returns-refunds" class="rounded-md border border-border px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">View return</a>
                                        @endif
                                        @if ($isFedExManaged)
                                            @if ($hasDownloadableLabel && $canManageOrders)
                                                <a href="{{ route('shipments.fedex.label.download', $shipment) }}" class="rounded-md border border-[#BFDBFE] bg-[#EFF6FF] px-2.5 py-1.5 text-xs font-semibold text-[#1D4ED8]">Download label</a>
                                            @endif
                                            @if ($canRefresh)
                                                <form method="POST" action="{{ route('shipments.fedex.tracking.refresh', $shipment) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-md border border-border px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Refresh tracking</button>
                                                </form>
                                            @endif
                                            @if ($customerTrackingUrl)
                                                <a href="{{ $customerTrackingUrl }}" target="_blank" rel="noopener" class="rounded-md border border-border px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Track</a>
                                            @elseif ($shipment->tracking_url)
                                                <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="rounded-md border border-border px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Track</a>
                                            @endif
                                            @if ($canVoid)
                                                <form method="POST" action="{{ route('shipments.fedex.cancel', $shipment) }}" onsubmit="return confirm('Void this FedEx shipment?')">
                                                    @csrf
                                                    <button type="submit" class="rounded-md border border-[#FECACA] bg-[#FEF2F2] px-2.5 py-1.5 text-xs font-semibold text-[#991B1B]">Void</button>
                                                </form>
                                            @endif
                                        @else
                                            @if ($shipment->tracking_url)
                                                <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="rounded-md border border-border px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Track</a>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-4 py-3">
                {{ $shipments->links() }}
            </div>
        @else
            <div class="px-5 py-14 text-center">
                <p class="text-base font-semibold text-ink">No shipments yet</p>
                <p class="mt-2 text-sm text-ink-muted">
                    @if ($search !== '' || $currentStatus !== 'all' || $currentProvider !== 'all')
                        No shipments match these filters. Try clearing search or switching status/provider.
                    @else
                        When you create a shipment from an order, it will appear here.
                    @endif
                </p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    @if ($search !== '' || $currentStatus !== 'all' || $currentProvider !== 'all')
                        <a href="{{ route('shipments.index') }}" class="ui-btn ui-btn-secondary">Clear filters</a>
                    @endif
                    <a href="{{ route('orders') }}" class="ui-btn ui-btn-primary">Go to orders</a>
                </div>
            </div>
        @endif
    </section>
</div>
@endsection
