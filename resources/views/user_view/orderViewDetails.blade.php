@extends('layouts.user.user-sidebar')

@section('title', 'Order ' . strtoupper($order->order_number) . ' | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div class="min-w-0">
        <h1 class="truncate text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Order {{ strtoupper($order->order_number) }}</h1>
        <p class="hidden md:block text-xs text-[#64748B]">Order detail, status history, and customer snapshot.</p>
    </div>

    <a href="{{ route('orders') }}" class="h-10 px-4 rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A] inline-flex items-center justify-center shrink-0 hover:bg-[#F8FAFC]">
        Back to orders
    </a>
</header>
@endsection

@section('content')
@php
    $currency = $selectedStore->currency ?? '$';
    $shipping = $order->addresses->firstWhere('type', 'shipping');
    $billing = $order->addresses->firstWhere('type', 'billing');
    $customerName = $order->customer?->full_name ?? $order->customer_email ?? 'Guest customer';
    $customerInitials = collect(explode(' ', $customerName))
        ->filter()
        ->map(fn ($part) => substr($part, 0, 1))
        ->take(2)
        ->join('');
    $displayTotal = (float) ($order->grand_total ?: $order->total);
    $availableOrderStatuses = collect();
    foreach ($orderStatuses as $status) {
        if ($status === $order->status || \App\Support\OrderLifecycle::canTransitionOrderStatus($order->status, $status)) {
            $availableOrderStatuses->push($status);
        }
    }
    $canManageOrders = auth()->user()?->canManageOrders($selectedStore) ?? false;
@endphp

<div class="w-full py-2 md:py-4 space-y-4">
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            {{ $errors->first() }}
        </div>
    @endif

    <nav class="flex flex-wrap items-center gap-2 text-sm text-[#64748B]">
        <a href="{{ route('dashboard') }}" class="hover:text-[#0F172A]">Dashboard</a>
        <span>&gt;</span>
        <a href="{{ route('orders') }}" class="hover:text-[#0F172A]">Orders</a>
        <span>&gt;</span>
        <span class="font-medium text-[#0F172A]">{{ strtoupper($order->order_number) }}</span>
    </nav>

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Order snapshot</p>
                <h2 class="mt-1 text-2xl md:text-3xl font-poppins font-semibold text-[#0F172A]">Order {{ strtoupper($order->order_number) }}</h2>
                <p class="mt-1 text-sm text-[#64748B]">
                    Placed {{ $order->placed_at ? $order->placed_at->format('F d, Y \a\t h:i A') : 'date not recorded' }}
                    by {{ $customerName }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[.6px] {{ \App\Support\OrderLifecycle::orderStatusBadgeClass($order->status) }}">
                    Order: {{ \App\Support\OrderLifecycle::orderStatusLabel($order->status) }}
                </span>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[.6px] {{ \App\Support\OrderLifecycle::paymentStatusBadgeClass($order->payment_status) }}">
                    Payment: {{ \App\Support\OrderLifecycle::paymentStatusLabel($order->payment_status) }}
                </span>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[.6px] {{ \App\Support\OrderLifecycle::fulfillmentStatusBadgeClass($order->fulfillment_status) }}">
                    Fulfillment: {{ \App\Support\OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status) }}
                </span>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-semibold text-[#64748B]">Items</p>
                <p class="mt-1 text-lg font-bold text-[#0F172A]">{{ $order->item_count ?: $order->items->count() }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-semibold text-[#64748B]">Quantity</p>
                <p class="mt-1 text-lg font-bold text-[#0F172A]">{{ $order->total_quantity ?: $order->items->sum('quantity') }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-semibold text-[#64748B]">Channel</p>
                <p class="mt-1 truncate text-lg font-bold text-[#0F172A]">{{ $order->channel ? ucfirst($order->channel) : 'Storefront' }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-semibold text-[#64748B]">Total</p>
                <p class="mt-1 text-lg font-bold text-[#0052CC]">{{ $currency }}{{ number_format($displayTotal, 2) }}</p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
        <div class="space-y-4">
            <article class="overflow-hidden rounded-2xl border border-[#CBD5E1] bg-white">
                <div class="border-b border-[#E2E8F0] px-5 md:px-6 py-4">
                    <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Order items</h3>
                    <p class="text-sm text-[#64748B]">Product and variant snapshots captured when the order was placed.</p>
                </div>

                <div class="divide-y divide-[#F1F5F9]">
                    @forelse($order->items as $item)
                        @php
                            $imagePath = $item->product_image_snapshot ?: $item->product?->images?->first()?->image_path;
                        @endphp
                        <div class="p-5 md:p-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-4 min-w-0">
                                @if($imagePath)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($imagePath) }}" class="h-16 w-16 rounded-xl object-cover border border-[#E2E8F0]" alt="{{ $item->product_name }}">
                                @else
                                    <div class="h-16 w-16 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] grid place-items-center text-xs font-semibold text-[#94A3B8]">IMG</div>
                                @endif

                                <div class="min-w-0">
                                    <h4 class="truncate text-base md:text-lg font-semibold text-[#0F172A]">{{ $item->product_name }}</h4>
                                    <p class="mt-1 text-sm text-[#64748B]">{{ $item->variant_label ?: 'Default option' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-[#64748B]">
                                        @if($item->sku_snapshot)
                                            <span class="rounded-full bg-[#F8FAFC] px-2 py-1">SKU {{ $item->sku_snapshot }}</span>
                                        @endif
                                        @if($item->brand_name_snapshot)
                                            <span class="rounded-full bg-[#F8FAFC] px-2 py-1">{{ $item->brand_name_snapshot }}</span>
                                        @endif
                                        @if($item->product_type_snapshot)
                                            <span class="rounded-full bg-[#F8FAFC] px-2 py-1">{{ ucfirst($item->product_type_snapshot) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="sm:text-right shrink-0">
                                <p class="text-sm text-[#64748B]">Qty {{ $item->quantity }} x {{ $currency }}{{ number_format((float) $item->unit_price, 2) }}</p>
                                <p class="mt-1 text-xl font-bold text-[#0F172A]">{{ $currency }}{{ number_format((float) $item->total, 2) }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-[#64748B]">No line items are attached to this order.</div>
                    @endforelse
                </div>
            </article>

            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Payment summary</h3>
                        <p class="text-sm text-[#64748B]">{{ $order->payment_method ? ucfirst($order->payment_method) : 'Payment method not recorded' }}</p>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[.6px] {{ \App\Support\OrderLifecycle::paymentStatusBadgeClass($order->payment_status) }}">
                        {{ \App\Support\OrderLifecycle::paymentStatusLabel($order->payment_status) }}
                    </span>
                </div>

                <div class="mt-5 space-y-3 text-sm text-[#334155]">
                    <div class="flex justify-between gap-4">
                        <span>Subtotal</span>
                        <span class="font-semibold">{{ $currency }}{{ number_format((float) $order->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span>Shipping</span>
                        <span class="font-semibold">{{ $currency }}{{ number_format((float) $order->shipping, 2) }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span>Tax</span>
                        <span class="font-semibold">{{ $currency }}{{ number_format((float) $order->tax, 2) }}</span>
                    </div>
                    @if((float) $order->discount > 0)
                        <div class="flex justify-between gap-4 text-[#15803D]">
                            <span>Discount</span>
                            <span class="font-semibold">-{{ $currency }}{{ number_format((float) $order->discount, 2) }}</span>
                        </div>
                    @endif
                </div>

                <div class="mt-5 border-t border-[#E2E8F0] pt-5 flex items-end justify-between gap-4">
                    <span class="text-base font-semibold text-[#0F172A]">Total</span>
                    <span class="text-2xl md:text-3xl font-bold text-[#0052CC]">{{ $currency }}{{ number_format($displayTotal, 2) }}</span>
                </div>
            </article>

            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Order activity</h3>
                <p class="text-sm text-[#64748B]">Recorded events for this order.</p>

                <div class="relative mt-5">
                    @if($order->events->count() > 1)
                        <div class="absolute left-[11px] top-7 bottom-7 w-px bg-[#CBD5E1]" aria-hidden="true"></div>
                    @endif

                    <div class="space-y-5">
                        @forelse($order->events as $event)
                            <div class="relative flex items-start gap-3">
                                <span class="mt-1 h-6 w-6 shrink-0 rounded-full border-4 border-white bg-[#0052CC] shadow-[0_0_0_1px_#CBD5E1]" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-[#0F172A]">{{ $event->title }}</p>
                                        <span class="rounded-full bg-[#F8FAFC] px-2 py-0.5 text-[10px] font-bold uppercase tracking-[.6px] text-[#64748B]">
                                            {{ \App\Support\OrderLifecycle::eventTypeLabel($event->event_type) }}
                                        </span>
                                    </div>
                                    @if($event->description)
                                        <p class="mt-1 text-sm text-[#64748B]">{{ $event->description }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-[#94A3B8]">
                                        {{ $event->actor?->name ?? 'System' }} - {{ $event->created_at?->format('M d, Y h:i A') ?? 'Time not recorded' }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">
                                No order activity has been recorded yet. Future status changes and important actions will appear here.
                            </div>
                        @endforelse
                    </div>
                </div>
            </article>
        </div>

        <aside class="space-y-4">
            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Order status</h3>
                <p class="mt-1 text-sm text-[#64748B]">Move the order through the approved lifecycle.</p>

                @if($canManageOrders && $availableOrderStatuses->count() > 1)
                    <form action="{{ route('orders.updateStatus', $order->id) }}" method="POST" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        <label for="status" class="block text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Next state</label>
                        <select name="status" id="status" class="w-full rounded-lg border border-[#CBD5E1] bg-white p-2.5 text-sm text-[#334155] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                            @foreach($availableOrderStatuses as $status)
                                <option value="{{ $status }}" {{ $order->status === $status ? 'selected' : '' }}>
                                    {{ \App\Support\OrderLifecycle::orderStatusLabel($status) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full h-10 rounded-lg bg-[#0052CC] text-white font-semibold text-sm hover:bg-[#0047B3]">
                            Save status
                        </button>
                    </form>
                @elseif($canManageOrders)
                    <p class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
                        No further status changes are available for this order.
                    </p>
                @else
                    <p class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
                        You can view this order, but your store role cannot change its status.
                    </p>
                @endif
            </article>

            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Customer</h3>
                <div class="mt-4 flex items-center gap-3">
                    <div class="h-11 w-11 rounded-full bg-[#EFF6FF] text-[#1D4ED8] grid place-items-center font-bold shrink-0">
                        {{ strtoupper($customerInitials) ?: 'C' }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-bold text-[#0F172A]">{{ $customerName }}</p>
                        <p class="truncate text-sm text-[#64748B]">{{ $order->customer_email ?? 'Email not recorded' }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-[1px] text-[#94A3B8] font-bold">Phone</p>
                        <p class="mt-1 text-[#334155]">{{ $order->customer_phone ?? $shipping?->phone ?? 'Not recorded' }}</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-[1px] text-[#94A3B8] font-bold">Shipping address</p>
                        @if($shipping)
                            <p class="mt-1 text-[#334155]">
                                {{ $shipping->address_line1 }}@if($shipping->address_line2), {{ $shipping->address_line2 }}@endif<br>
                                {{ $shipping->city }}, {{ $shipping->state }} {{ $shipping->postal_code }}<br>
                                {{ $shipping->country }}
                            </p>
                        @else
                            <p class="mt-1 text-[#64748B]">Not recorded</p>
                        @endif
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-[1px] text-[#94A3B8] font-bold">Billing address</p>
                        @if($billing)
                            <p class="mt-1 text-[#334155]">
                                {{ $billing->address_line1 }}@if($billing->address_line2), {{ $billing->address_line2 }}@endif<br>
                                {{ $billing->city }}, {{ $billing->state }} {{ $billing->postal_code }}<br>
                                {{ $billing->country }}
                            </p>
                        @elseif($order->billing_same_as_shipping && $shipping)
                            <p class="mt-1 text-[#64748B]">Same as shipping address</p>
                        @else
                            <p class="mt-1 text-[#64748B]">Not recorded</p>
                        @endif
                    </div>
                </div>

                @if($order->customer_id)
                    <a href="{{ route('customersProfile', $order->customer_id) }}" class="mt-5 inline-flex w-full h-10 items-center justify-center rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">
                        View customer
                    </a>
                @endif
            </article>

            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Shipments</h3>
                <p class="mt-4 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">
                    No shipments have been created yet. Fulfillment tracking will appear here after shipping is implemented.
                </p>
            </article>

            <article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Order notes</h3>
                @if($order->notes)
                    <p class="mt-4 whitespace-pre-line text-sm text-[#334155]">{{ $order->notes }}</p>
                @else
                    <p class="mt-4 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">
                        No internal notes are recorded for this order.
                    </p>
                @endif
            </article>
        </aside>
    </section>
</div>
@endsection
