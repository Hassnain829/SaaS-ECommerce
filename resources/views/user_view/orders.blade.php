@extends('layouts.user.user-sidebar')

@section('title', 'Orders — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Orders" :lead="'Track customer orders for '.($selectedStore?->name ?? 'this store').'.'">
        <x-slot:search>
            <form method="GET" action="{{ route('orders') }}" class="flex w-full items-center gap-2">
                <input type="hidden" name="status" value="{{ $currentStatus }}">
                <input name="q" value="{{ $search }}" class="h-9 min-w-0 flex-1 rounded-md border border-border bg-surface px-3 text-sm text-ink placeholder:text-ink-muted focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="Search orders…">
                <button class="inline-flex h-9 shrink-0 items-center rounded-md border border-border bg-surface px-3 text-xs font-semibold text-ink-secondary hover:bg-surface-muted">Search</button>
            </form>
        </x-slot:search>
        @if($canManageOrders)
            <x-slot:actions>
                <a href="{{ route('orders.create') }}" class="hidden h-9 items-center rounded-md bg-brand px-3.5 text-sm font-semibold text-white transition hover:bg-brand-hover xl:inline-flex">Create order</a>
            </x-slot:actions>
        @endif
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    $sourceLabels = [
        'external_checkout' => 'External checkout',
        'platform_checkout' => 'Platform checkout',
        'developer_storefront' => 'Developer Storefront',
        'manual' => 'Manual order',
    ];
@endphp
<div class="w-full space-y-4">
    @include('user_view.partials.flash_success')

    <section class="merchant-card space-y-4 p-4">
        @if($search !== '')
            <div class="flex items-center justify-between rounded-md bg-surface-muted px-3 py-2 text-sm text-ink-secondary">
                <span>Search: <span class="font-semibold text-ink">{{ $search }}</span></span>
                <a href="{{ route('orders', ['status' => $currentStatus]) }}" class="font-semibold text-brand hover:text-brand-hover">Clear</a>
            </div>
        @endif

        <div class="flex flex-wrap gap-2 text-sm font-semibold">
            <a href="{{ route('orders', ['status' => 'all']) }}" @class([
                'inline-flex h-8 items-center rounded-md px-3',
                'bg-brand text-white' => $currentStatus === 'all',
                'bg-surface-muted text-ink-secondary hover:bg-border/60' => $currentStatus !== 'all',
            ])>
                All ({{ $statusCounts['all'] ?? 0 }})
            </a>
            @foreach($orderStatuses as $status)
                <a href="{{ route('orders', ['status' => $status]) }}" @class([
                    'inline-flex h-8 items-center rounded-md px-3',
                    'bg-brand text-white' => $currentStatus === $status,
                    'bg-surface-muted text-ink-secondary hover:bg-border/60' => $currentStatus !== $status,
                ])>
                    {{ \App\Support\OrderLifecycle::orderStatusLabel($status) }} ({{ $statusCounts[$status] ?? 0 }})
                </a>
            @endforeach
        </div>
    </section>

    <section class="merchant-card overflow-hidden">
        <div class="border-b border-border px-4 py-3.5 md:px-5">
            <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Draft orders</h2>
                    <p class="text-xs text-ink-muted">Manual orders that have not become confirmed orders yet.</p>
                </div>
                @if($canManageOrders)
                    <a href="{{ route('orders.create') }}" class="text-sm font-semibold text-brand hover:text-brand-hover">New draft</a>
                @endif
            </div>
        </div>

        @if(($draftOrders ?? collect())->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead class="border-b border-border bg-surface-muted/70 text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th class="px-5 py-2.5 text-left">Draft</th>
                            <th class="px-4 py-2.5 text-left">Customer</th>
                            <th class="px-4 py-2.5 text-left">Created</th>
                            <th class="px-4 py-2.5 text-right">Total</th>
                            <th class="px-4 py-2.5 text-left">Status</th>
                            <th class="px-4 py-2.5 text-left">Items</th>
                            <th class="px-5 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($draftOrders as $draft)
                            <tr class="border-b border-border/80 hover:bg-surface-muted/40">
                                <td class="px-5 py-3">
                                    <a href="{{ route('draft-orders.show', $draft) }}" class="font-semibold text-brand hover:text-brand-hover">{{ $draft->draft_number }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-ink">{{ $draft->customer?->full_name ?? $draft->customer?->email ?? 'No customer selected' }}</p>
                                    <p class="text-xs text-ink-muted">{{ $draft->customer?->email }}</p>
                                </td>
                                <td class="px-4 py-3 text-ink-secondary">{{ $draft->created_at?->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-ink">{{ $draft->currency ?: ($selectedStore->currency ?? 'USD') }} {{ number_format((float) $draft->total, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                        'bg-info-soft text-info' => $draft->status === \App\Models\DraftOrder::STATUS_DRAFT,
                                        'bg-surface-muted text-ink-secondary' => $draft->status !== \App\Models\DraftOrder::STATUS_DRAFT,
                                    ])>
                                        {{ str($draft->status)->title() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-ink-secondary">{{ $draft->items_count }} {{ str('item')->plural($draft->items_count) }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                                        <a href="{{ route('draft-orders.show', $draft) }}" class="rounded-md border border-border px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">View/Edit</a>
                                        @if($draft->status === \App\Models\DraftOrder::STATUS_DRAFT)
                                            <form action="{{ route('draft-orders.convert', $draft) }}" method="POST">
                                                @csrf
                                                <button class="rounded-md bg-brand px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-brand-hover">Create order</button>
                                            </form>
                                            <form action="{{ route('draft-orders.cancel', $draft) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button class="rounded-md border border-danger/30 px-2.5 py-1.5 text-xs font-semibold text-danger hover:bg-danger-soft">Cancel</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('draft-orders.destroy', $draft) }}" method="POST" onsubmit="return confirm('Delete this draft order? This will remove it from your active draft list. Converted orders cannot be deleted.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-md border border-danger/30 px-2.5 py-1.5 text-xs font-semibold text-danger hover:bg-danger-soft">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-5 py-8 text-center text-sm text-ink-muted">
                No draft orders found. Drafts you save from the manual order workspace will appear here.
            </div>
        @endif
    </section>

    <section class="merchant-card overflow-hidden">
        <div class="border-b border-border px-4 py-3.5 md:px-5">
            <h2 class="text-sm font-semibold text-ink">Final orders</h2>
            <p class="text-xs text-ink-muted">Confirmed customer orders stay separate from drafts.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-sm">
                <thead class="border-b border-border bg-surface-muted/70 text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-5 py-2.5 text-left">Order</th>
                        <th class="px-4 py-2.5 text-left">Date</th>
                        <th class="px-4 py-2.5 text-left">Customer</th>
                        <th class="px-4 py-2.5 text-left">Source</th>
                        <th class="px-4 py-2.5 text-right">Total</th>
                        <th class="px-4 py-2.5 text-left">Order state</th>
                        <th class="px-4 py-2.5 text-left">Payment</th>
                        <th class="px-4 py-2.5 text-left">Fulfillment</th>
                        <th class="px-5 py-2.5 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr class="border-b border-border/80 hover:bg-surface-muted/40">
                        <td class="px-5 py-3 font-semibold text-brand">{{ strtoupper($order->order_number) }}</td>
                        <td class="px-4 py-3 text-ink-secondary">{{ $order->placed_at ? $order->placed_at->format('M d, Y') : '-' }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-ink">{{ $order->customer->full_name ?? $order->customer_email }}</p>
                            <p class="text-xs text-ink-muted">{{ $order->customer_email }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-ink">{{ $sourceLabels[$order->order_source] ?? ($order->order_source ? str($order->order_source)->replace('_', ' ')->title() : 'Manual order') }}</p>
                            <p class="text-xs text-ink-muted">{{ $order->channel ? str($order->channel)->replace('_', ' ')->title() : 'Dashboard' }}</p>
                            @if($order->external_order_number)
                                <p class="text-xs text-ink-muted">External {{ $order->external_order_number }}</p>
                            @endif
                            @if(data_get($order->meta, 'platform_checkout.checkout_number'))
                                <p class="text-xs text-ink-muted">Checkout {{ data_get($order->meta, 'platform_checkout.checkout_number') }}</p>
                            @endif
                            @if(data_get($order->meta, 'platform_checkout.connection_label'))
                                <p class="text-xs text-ink-muted">{{ data_get($order->meta, 'platform_checkout.connection_label') }}</p>
                            @endif
                            @if($order->payment_gateway)
                                <p class="text-xs text-ink-muted">{{ str($order->payment_gateway)->replace('_', ' ')->title() }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-ink">{{ $selectedStore->currency ?? '$' }}{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ \App\Support\OrderLifecycle::orderStatusBadgeClass($order->status) }}">
                                {{ \App\Support\OrderLifecycle::orderStatusLabel($order->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ \App\Support\OrderLifecycle::paymentStatusBadgeClass($order->payment_status) }}">
                                {{ \App\Support\OrderLifecycle::paymentStatusLabel($order->payment_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ \App\Support\OrderLifecycle::fulfillmentStatusBadgeClass($order->fulfillment_status) }}">
                                {{ \App\Support\OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('orderViewDetails', $order->id) }}" class="text-sm font-semibold text-brand hover:text-brand-hover">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-5 py-8 text-center text-ink-muted">
                            No orders found. Manual orders you create and storefront orders will appear here.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
        <div class="flex items-center justify-between border-t border-border px-4 py-3 text-xs text-ink-muted md:px-5">
            <p>Showing <span class="font-semibold text-ink">{{ $orders->firstItem() }}</span> to <span class="font-semibold text-ink">{{ $orders->lastItem() }}</span> of <span class="font-semibold text-ink">{{ $orders->total() }}</span> orders</p>
            <div class="flex items-center gap-2">
                {{ $orders->links('pagination::tailwind') }}
            </div>
        </div>
        @else
        <div class="flex h-12 items-center border-t border-border px-4 text-xs text-ink-muted md:px-5">
            <p>Showing <span class="font-semibold text-ink">{{ $orders->count() }}</span> orders</p>
        </div>
        @endif
    </section>
</div>
@endsection
