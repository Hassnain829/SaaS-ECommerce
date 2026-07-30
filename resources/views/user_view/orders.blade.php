@extends('layouts.user.user-sidebar')

@section('title', 'Orders | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="All orders" :lead="'Track customer orders for '.($selectedStore?->name ?? 'this store').'.'">
        <x-slot:search>
            <form method="GET" action="{{ route('orders') }}" class="flex w-full items-center gap-2">
                <input type="hidden" name="status" value="{{ $currentStatus }}">
                <input name="q" value="{{ $search }}" class="h-9 min-w-0 flex-1 rounded-lg border border-stone-200 bg-stone-50 px-3 text-sm" placeholder="Search orders…">
                <button class="inline-flex h-9 shrink-0 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700 hover:bg-stone-50">Search</button>
            </form>
        </x-slot:search>
        @if($canManageOrders)
            <x-slot:actions>
                <a href="{{ route('orders.create') }}" class="hidden h-9 items-center rounded-lg bg-brand px-3 text-xs font-semibold text-white hover:bg-brand-hover xl:inline-flex">Create order</a>
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
<div class="w-full py-2 md:py-4 space-y-4">
    @include('user_view.partials.flash_success')

    <section class="bg-white border border-[#CBD5E1] rounded-2xl p-4 md:p-5 space-y-4">
        @if($search !== '')
            <div class="flex items-center justify-between rounded-lg bg-[#F8FAFC] px-3 py-2 text-sm text-[#475569]">
                <span>Search: <span class="font-semibold text-[#0F172A]">{{ $search }}</span></span>
                <a href="{{ route('orders', ['status' => $currentStatus]) }}" class="font-semibold text-[#0052CC]">Clear</a>
            </div>
        @endif

        <div class="flex flex-wrap gap-2 text-sm font-semibold">
            <a href="{{ route('orders', ['status' => 'all']) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === 'all' ? 'bg-brand text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">
                All ({{ $statusCounts['all'] ?? 0 }})
            </a>
            @foreach($orderStatuses as $status)
                <a href="{{ route('orders', ['status' => $status]) }}" class="h-9 px-4 rounded-full flex items-center justify-center {{ $currentStatus === $status ? 'bg-brand text-white' : 'bg-[#F1F5F9] text-[#475569]' }}">
                    {{ \App\Support\OrderLifecycle::orderStatusLabel($status) }} ({{ $statusCounts[$status] ?? 0 }})
                </a>
            @endforeach
        </div>
    </section>

    <section class="bg-white border border-[#CBD5E1] rounded-2xl overflow-hidden">
        <div class="border-b border-[#E2E8F0] bg-[#F8FAFC] px-4 py-4 md:px-6">
            <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-[#0F172A]">Draft orders</h2>
                    <p class="text-sm text-[#64748B]">Manual orders that have not become confirmed orders yet.</p>
                </div>
                @if($canManageOrders)
                    <a href="{{ route('orders.create') }}" class="text-sm font-semibold text-[#0052CC]">New draft</a>
                @endif
            </div>
        </div>

        @if(($draftOrders ?? collect())->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px]">
                    <thead class="bg-white border-b border-[#E2E8F0] text-[#64748B] text-xs uppercase tracking-[1px]">
                        <tr>
                            <th class="text-left px-6 py-4">Draft</th>
                            <th class="text-left px-4 py-4">Customer</th>
                            <th class="text-left px-4 py-4">Created</th>
                            <th class="text-right px-4 py-4">Total</th>
                            <th class="text-left px-4 py-4">Status</th>
                            <th class="text-left px-4 py-4">Items</th>
                            <th class="text-right px-6 py-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($draftOrders as $draft)
                            <tr class="border-b border-[#F1F5F9]">
                                <td class="px-6 py-4">
                                    <a href="{{ route('draft-orders.show', $draft) }}" class="font-bold text-[#0052CC]">{{ $draft->draft_number }}</a>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-[#0F172A]">{{ $draft->customer?->full_name ?? $draft->customer?->email ?? 'No customer selected' }}</p>
                                    <p class="text-xs text-[#64748B]">{{ $draft->customer?->email }}</p>
                                </td>
                                <td class="px-4 py-4 text-[#475569]">{{ $draft->created_at?->format('M d, Y') }}</td>
                                <td class="px-4 py-4 text-right font-bold">{{ $draft->currency ?: ($selectedStore->currency ?? 'USD') }} {{ number_format((float) $draft->total, 2) }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] {{ $draft->status === \App\Models\DraftOrder::STATUS_DRAFT ? 'bg-[#DBEAFE] text-[#1D4ED8]' : 'bg-[#F1F5F9] text-[#475569]' }}">
                                        {{ str($draft->status)->title() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-[#475569]">{{ $draft->items_count }} {{ str('item')->plural($draft->items_count) }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <a href="{{ route('draft-orders.show', $draft) }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2 text-xs font-semibold text-[#0F172A]">View/Edit</a>
                                        @if($draft->status === \App\Models\DraftOrder::STATUS_DRAFT)
                                            <form action="{{ route('draft-orders.convert', $draft) }}" method="POST">
                                                @csrf
                                                <button class="rounded-lg bg-[#059669] px-3 py-2 text-xs font-semibold text-white">Create order</button>
                                            </form>
                                            <form action="{{ route('draft-orders.cancel', $draft) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button class="rounded-lg border border-[#FECACA] px-3 py-2 text-xs font-semibold text-[#991B1B]">Cancel</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('draft-orders.destroy', $draft) }}" method="POST" onsubmit="return confirm('Delete this draft order? This will remove it from your active draft list. Converted orders cannot be deleted.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg border border-[#FECACA] px-3 py-2 text-xs font-semibold text-[#991B1B]">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-8 text-center text-sm text-[#64748B]">
                No draft orders found. Drafts you save from the manual order workspace will appear here.
            </div>
        @endif
    </section>

    <section class="bg-white border border-[#CBD5E1] rounded-2xl overflow-hidden">
        <div class="border-b border-[#E2E8F0] bg-[#F8FAFC] px-4 py-4 md:px-6">
            <h2 class="text-lg font-semibold text-[#0F172A]">Final orders</h2>
            <p class="text-sm text-[#64748B]">Confirmed customer orders stay separate from drafts.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px]">
                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-[#64748B] text-xs uppercase tracking-[1px]">
                    <tr>
                        <th class="text-left px-6 py-4">Order</th>
                        <th class="text-left px-4 py-4">Date</th>
                        <th class="text-left px-4 py-4">Customer</th>
                        <th class="text-left px-4 py-4">Source</th>
                        <th class="text-right px-4 py-4">Total</th>
                        <th class="text-left px-4 py-4">Order state</th>
                        <th class="text-left px-4 py-4">Payment</th>
                        <th class="text-left px-4 py-4">Fulfillment</th>
                        <th class="text-right px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($orders as $order)
                    <tr class="border-b border-[#F1F5F9]">
                        <td class="px-6 py-4 text-[#0052CC] font-bold">{{ strtoupper($order->order_number) }}</td>
                        <td class="px-4 py-4 text-[#475569]">{{ $order->placed_at ? $order->placed_at->format('M d, Y') : '-' }}</td>
                        <td class="px-4 py-4">
                            <p class="font-semibold">{{ $order->customer->full_name ?? $order->customer_email }}</p>
                            <p class="text-xs text-[#64748B]">{{ $order->customer_email }}</p>
                        </td>
                        <td class="px-4 py-4">
                            <p class="font-semibold text-[#0F172A]">{{ $sourceLabels[$order->order_source] ?? ($order->order_source ? str($order->order_source)->replace('_', ' ')->title() : 'Manual order') }}</p>
                            <p class="text-xs text-[#64748B]">{{ $order->channel ? str($order->channel)->replace('_', ' ')->title() : 'Dashboard' }}</p>
                            @if($order->external_order_number)
                                <p class="text-xs text-[#64748B]">External {{ $order->external_order_number }}</p>
                            @endif
                            @if(data_get($order->meta, 'platform_checkout.checkout_number'))
                                <p class="text-xs text-[#64748B]">Checkout {{ data_get($order->meta, 'platform_checkout.checkout_number') }}</p>
                            @endif
                            @if(data_get($order->meta, 'platform_checkout.connection_label'))
                                <p class="text-xs text-[#64748B]">{{ data_get($order->meta, 'platform_checkout.connection_label') }}</p>
                            @endif
                            @if($order->payment_gateway)
                                <p class="text-xs text-[#64748B]">{{ str($order->payment_gateway)->replace('_', ' ')->title() }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right font-bold">{{ $selectedStore->currency ?? '$' }}{{ number_format((float) $order->total, 2) }}</td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] {{ \App\Support\OrderLifecycle::orderStatusBadgeClass($order->status) }}">
                                {{ \App\Support\OrderLifecycle::orderStatusLabel($order->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] {{ \App\Support\OrderLifecycle::paymentStatusBadgeClass($order->payment_status) }}">
                                {{ \App\Support\OrderLifecycle::paymentStatusLabel($order->payment_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[1px] {{ \App\Support\OrderLifecycle::fulfillmentStatusBadgeClass($order->fulfillment_status) }}">
                                {{ \App\Support\OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('orderViewDetails', $order->id) }}" class="text-[#0052CC] font-bold">View details</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-[#64748B]">
                            No orders found. Manual orders you create and storefront orders will appear here.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
        <div class="border-t border-[#F1F5F9] px-4 md:px-6 py-4 flex items-center justify-between text-xs text-[#64748B]">
            <p>Showing <span class="font-bold text-[#0F172A]">{{ $orders->firstItem() }}</span> to <span class="font-bold text-[#0F172A]">{{ $orders->lastItem() }}</span> of <span class="font-bold text-[#0F172A]">{{ $orders->total() }}</span> orders</p>
            <div class="flex items-center gap-2">
                {{ $orders->links('pagination::tailwind') }}
            </div>
        </div>
        @else
        <div class="border-t border-[#F1F5F9] px-4 md:px-6 h-14 flex items-center justify-between text-xs text-[#64748B]">
            <p>Showing <span class="font-bold text-[#0F172A]">{{ $orders->count() }}</span> orders</p>
        </div>
        @endif
    </section>
</div>
@endsection
