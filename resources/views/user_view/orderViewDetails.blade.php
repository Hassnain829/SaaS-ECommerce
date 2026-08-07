@extends('layouts.user.user-sidebar')

@section('title', 'Order ' . strtoupper($order->order_number) . ' — '.config('app.name'))
@section('sidebar_brand_title', config('app.name'))
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'Orders')

@section('topbar')
    <x-ui.merchant-topbar title="Order details" :lead="strtoupper($order->order_number)">
        <x-slot:actions>
            <a href="{{ route('orders') }}" class="inline-flex h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">Back to orders</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@push('styles')
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap" rel="stylesheet">
    <style>
        .order-page .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            font-size: 1.25rem;
            line-height: 1;
        }
        .order-status-pill {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-flex;
            align-items: center;
        }
    </style>
@endpush

@section('content')
    @php
        use App\Support\MoneyDisplay;

        $currency = strtoupper(trim((string) ($order->currency_code ?: $selectedStore->currency ?: 'USD')));
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
        $noteEvents = $order->events->where('event_type', \App\Support\OrderLifecycle::EVENT_ORDER_NOTE_ADDED);
        $sourceLabels = [
            'external_checkout' => 'External',
            'platform_checkout' => 'Platform',
            'developer_storefront' => 'Storefront',
            'manual' => 'Manual',
        ];
        $sourceLabel = $sourceLabels[$order->order_source] ?? ($order->order_source ? str($order->order_source)->replace('_', ' ')->title() : 'Manual');
        $sourceLabelLong = [
            'external_checkout' => 'External checkout',
            'platform_checkout' => 'Platform checkout',
            'developer_storefront' => 'Developer Storefront',
            'manual' => 'Manual order',
        ][$order->order_source] ?? $sourceLabel;
        $gatewayLabel = $order->payment_gateway ? str($order->payment_gateway)->replace('_', ' ')->title() : null;
        $platformCheckoutNumber = data_get($order->meta, 'platform_checkout.checkout_number');
        $paymentConnectionLabel = data_get($order->meta, 'platform_checkout.connection_label');
        $connectedAccountId = data_get($order->meta, 'platform_checkout.provider_account_id');
        $shippingSnapshot = data_get($order->meta, 'shipping', []);
        $selectedDeliveryMethod = data_get($shippingSnapshot, 'method_name');
        $selectedCarrierName = data_get($shippingSnapshot, 'carrier_name');
        $selectedDeliverySpeed = data_get($shippingSnapshot, 'delivery_speed_label');
        $estimatedMinDays = data_get($shippingSnapshot, 'estimated_min_days');
        $estimatedMaxDays = data_get($shippingSnapshot, 'estimated_max_days');
        $fulfillmentRouting = data_get($order->meta, 'fulfillment_routing', []);
        $routedOriginLocationId = (int) data_get($fulfillmentRouting, 'origin_location_id');
        $pickupLocationName = data_get($fulfillmentRouting, 'pickup_name');
        $isOrderExternallyManaged = $isOrderExternallyManaged ?? false;
        $externalFulfillmentSnapshot = is_array($externalFulfillmentSnapshot ?? null) ? $externalFulfillmentSnapshot : [];
        $externalShipmentsMeta = is_array($externalShipmentsMeta ?? null) ? $externalShipmentsMeta : [];
        $externalCarrierName = $externalFulfillmentSnapshot['carrier_name'] ?? $selectedCarrierName;
        $externalTrackingNumber = $externalFulfillmentSnapshot['tracking_number'] ?? null;
        $externalTrackingUrl = $externalFulfillmentSnapshot['tracking_url'] ?? null;
        $externalFulfillmentStatus = $externalFulfillmentSnapshot['status'] ?? null;
        $externalShippedAt = $externalFulfillmentSnapshot['shipped_at'] ?? null;
        $externalDeliveredAt = $externalFulfillmentSnapshot['delivered_at'] ?? null;
        $hasExternalFulfillmentDetails = filled($externalCarrierName)
            || filled($externalTrackingNumber)
            || filled($externalTrackingUrl)
            || filled($externalFulfillmentStatus)
            || filled($externalShippedAt)
            || filled($externalDeliveredAt)
            || $externalShipmentsMeta !== [];
        $remainingFulfillmentQuantities = $remainingFulfillmentQuantities ?? [];
        $remainingTotal = collect($remainingFulfillmentQuantities)->sum();
        $orderStatusLabel = \App\Support\OrderLifecycle::orderStatusLabel($order->status);
        $paymentStatusLabel = \App\Support\OrderLifecycle::paymentStatusLabel($order->payment_status);
        $fulfillmentStatusLabel = $isOrderExternallyManaged
            ? 'Externally managed'
            : \App\Support\OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status);
        $paymentPaid = in_array($order->payment_status, [
            \App\Support\OrderLifecycle::PAYMENT_PAID,
            \App\Support\OrderLifecycle::PAYMENT_AUTHORIZED,
        ], true);
    @endphp

    <div class="order-page mx-auto w-full max-w-[1440px] space-y-8 pb-10">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Page header --}}
        <section class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <nav class="mb-2 text-sm text-slate-500" aria-label="Breadcrumb">
                    <a href="{{ route('orders') }}" class="hover:text-brand">Orders</a>
                    <span> / Order History</span>
                </nav>
                <div class="flex flex-wrap items-center gap-3 md:gap-4">
                    <h2 class="font-heading text-2xl font-semibold tracking-tight text-stone-900 md:text-[32px] md:leading-tight">
                        Order #{{ strtoupper($order->order_number) }}
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        <span class="order-status-pill bg-brand-soft text-brand-ink">{{ $orderStatusLabel }}</span>
                        <span @class([
                            'order-status-pill',
                            'bg-green-100 text-green-700' => $paymentPaid,
                            'bg-amber-100 text-amber-800' => ! $paymentPaid,
                        ])>{{ $paymentStatusLabel }}</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="button" onclick="window.print()" class="inline-flex h-10 items-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:bg-stone-50">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    Print
                </button>
                <a href="#returns-refunds" class="inline-flex h-10 items-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:bg-stone-50">
                    <span class="material-symbols-outlined text-[18px]">history</span>
                    Refund
                </a>
                <a href="#status-manager" class="inline-flex h-10 items-center rounded-xl bg-brand px-6 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-hover">
                    Manage
                </a>
            </div>
        </section>

        {{-- Metrics --}}
        <section class="grid grid-cols-1 gap-6 md:grid-cols-4">
            <div class="merchant-card flex flex-col p-6">
                <span class="mb-1 text-sm text-slate-500">Items</span>
                <span class="font-heading text-xl font-bold">{{ $order->item_count ?: $order->items->count() }}</span>
            </div>
            <div class="merchant-card flex flex-col p-6">
                <span class="mb-1 text-sm text-slate-500">Total</span>
                <span class="font-heading text-xl font-bold tabular-nums">{{ MoneyDisplay::formatWithCode($displayTotal, $currency) }}</span>
            </div>
            <div class="merchant-card flex flex-col p-6">
                <span class="mb-1 text-sm text-slate-500">Source</span>
                <span class="font-heading text-xl font-bold" title="{{ $sourceLabelLong }}">{{ $sourceLabel }}</span>
            </div>
            <div class="merchant-card flex flex-col p-6">
                <span class="mb-1 text-sm text-slate-500">Fulfillment Status</span>
                <span @class([
                    'order-status-pill mt-2 w-fit',
                    'bg-sky-100 text-sky-800' => $isOrderExternallyManaged,
                    'bg-danger-soft text-danger' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_UNFULFILLED,
                    'bg-amber-100 text-amber-800' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_PARTIAL,
                    'bg-green-100 text-green-700' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_FULFILLED,
                    'bg-slate-100 text-slate-700' => ! $isOrderExternallyManaged
                        && ! in_array($order->fulfillment_status, [
                            \App\Support\OrderLifecycle::FULFILLMENT_UNFULFILLED,
                            \App\Support\OrderLifecycle::FULFILLMENT_PARTIAL,
                            \App\Support\OrderLifecycle::FULFILLMENT_FULFILLED,
                        ], true),
                ])>{{ $fulfillmentStatusLabel }}</span>
            </div>
        </section>

        <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
            {{-- Left column --}}
            <div class="space-y-8 lg:col-span-8">
                {{-- Order items --}}
                <div class="merchant-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-stone-200 px-6 py-4">
                        <h3 class="font-heading text-xl font-semibold">Order Items</h3>
                    </div>
                    <div class="space-y-6 p-6">
                        @forelse ($order->items as $item)
                            @php
                                $imagePath = $item->product_image_snapshot ?: $item->product?->images?->first()?->image_path;
                            @endphp
                            <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                                @if ($imagePath)
                                    <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-lg bg-stone-100">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($imagePath) }}" class="h-full w-full object-cover" alt="{{ $item->product_name }}">
                                    </div>
                                @else
                                    <div class="flex h-24 w-24 flex-shrink-0 items-center justify-center rounded-lg bg-stone-100 text-slate-400">
                                        <span class="material-symbols-outlined text-3xl">inventory_2</span>
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-lg font-semibold text-stone-900">{{ $item->product_name }}</h4>
                                    <p class="text-sm text-slate-500">Variant: {{ $item->variant_label ?: 'Default option' }}</p>
                                    @if ($item->sku_snapshot)
                                        <p class="text-sm text-slate-500">SKU {{ $item->sku_snapshot }}</p>
                                    @endif
                                </div>
                                <div class="text-left sm:text-right">
                                    <p class="text-lg font-bold">{{ MoneyDisplay::formatWithCode($item->unit_price, $currency) }} x {{ $item->quantity }}</p>
                                    <p class="text-brand text-sm font-semibold">{{ MoneyDisplay::formatWithCode($item->total, $currency) }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-6 py-12 text-center text-sm text-slate-600">
                                No line items on this order
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Payment summary --}}
                <div class="merchant-card">
                    <div class="flex items-center justify-between border-b border-stone-200 px-6 py-4">
                        <h3 class="font-heading text-xl font-semibold">Payment Summary</h3>
                        @isset($taxDisplay)
                            <a href="#order-tax-breakdown" class="text-brand text-sm font-semibold hover:underline">View Breakdown</a>
                        @endisset
                    </div>
                    <div class="space-y-3 p-6">
                        <div class="flex justify-between text-base">
                            <span class="text-slate-600">Subtotal</span>
                            <span class="tabular-nums">{{ MoneyDisplay::formatWithCode($order->subtotal, $currency) }}</span>
                        </div>
                        @if ((float) $order->discount > 0)
                            <div class="flex justify-between text-base text-emerald-800">
                                <span>
                                    Discount
                                    @php $orderCouponCode = data_get($order->meta, 'coupon_snapshot.code'); @endphp
                                    @if (filled($orderCouponCode))
                                        <span class="mt-0.5 block text-xs font-normal text-emerald-700/80">{{ $orderCouponCode }}</span>
                                    @endif
                                </span>
                                <span class="tabular-nums font-semibold">{{ MoneyDisplay::formatDiscountWithCode($order->discount, $currency) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base">
                            <span class="text-slate-600">Shipping</span>
                            <span class="tabular-nums">{{ MoneyDisplay::formatWithCode($order->shipping, $currency) }}</span>
                        </div>
                        <div class="flex justify-between text-base">
                            <span class="text-slate-600">
                                Tax
                                @isset($taxDisplay)
                                    @if ($taxDisplay['compact_summary'] ?? null)
                                        <span class="mt-0.5 block text-xs font-normal text-slate-500">{{ $taxDisplay['compact_summary'] }}</span>
                                    @endif
                                @endisset
                            </span>
                            <span class="tabular-nums">{{ MoneyDisplay::formatWithCode($order->tax, $currency) }}</span>
                        </div>
                        <div class="my-2 h-px bg-stone-200"></div>
                        <div class="flex justify-between font-heading text-xl font-bold">
                            <span>Total</span>
                            <span class="text-brand tabular-nums">{{ MoneyDisplay::formatWithCode($displayTotal, $currency) }}</span>
                        </div>

                        @isset($taxDisplay)
                            <div class="pt-4">
                                @include('user_view.partials.tax_detail_disclosure', [
                                    'taxDisplay' => $taxDisplay,
                                    'currency' => $currency,
                                    'disclosureId' => 'order-tax-breakdown',
                                    'title' => 'Tax details',
                                ])
                            </div>
                        @endisset

                        @if ($gatewayLabel || $order->payment_method || $order->payment_reference || $platformCheckoutNumber || $paymentConnectionLabel || $connectedAccountId || $order->external_order_number || $order->external_checkout_reference || $selectedDeliveryMethod)
                            <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Payment and source details</summary>
                                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Source</p>
                                        <p class="mt-1 font-semibold">{{ $sourceLabelLong }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Channel</p>
                                        <p class="mt-1 font-semibold">{{ $order->channel ? str($order->channel)->replace('_', ' ')->title() : 'Dashboard' }}</p>
                                    </div>
                                    @if ($order->external_order_number)
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">External order</p>
                                            <p class="mt-1 font-semibold">{{ $order->external_order_number }}</p>
                                        </div>
                                    @endif
                                    @if ($order->external_checkout_reference)
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Checkout reference</p>
                                            <p class="mt-1 break-all font-semibold">{{ $order->external_checkout_reference }}</p>
                                        </div>
                                    @endif
                                    @if ($platformCheckoutNumber)
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Checkout</p>
                                            <p class="mt-1 break-all font-semibold">{{ $platformCheckoutNumber }}</p>
                                        </div>
                                    @endif
                                    @if ($paymentConnectionLabel)
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Stripe connection</p>
                                            <p class="mt-1 font-semibold">{{ $paymentConnectionLabel }}</p>
                                        </div>
                                    @endif
                                    @if ($gatewayLabel)
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Gateway</p>
                                            <p class="mt-1 font-semibold">{{ $gatewayLabel }}</p>
                                        </div>
                                    @endif
                                    @if ($order->payment_method)
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Method</p>
                                            <p class="mt-1 font-semibold">{{ str($order->payment_method)->replace('_', ' ')->title() }}</p>
                                        </div>
                                    @endif
                                    @if ($order->payment_reference)
                                        <div class="sm:col-span-2">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Payment reference</p>
                                            <p class="mt-1 break-all font-semibold">{{ $order->payment_reference }}</p>
                                        </div>
                                    @endif
                                    @if ($connectedAccountId)
                                        <div class="sm:col-span-2">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Connected account</p>
                                            <p class="mt-1 break-all font-semibold">{{ $connectedAccountId }}</p>
                                        </div>
                                    @endif
                                    @if ($selectedDeliveryMethod)
                                        <div class="sm:col-span-2">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Delivery method</p>
                                            <p class="mt-1 font-semibold">{{ $selectedDeliveryMethod }}</p>
                                            @if ($selectedDeliverySpeed || $selectedCarrierName || $estimatedMinDays !== null || $estimatedMaxDays !== null)
                                                <p class="mt-1 text-xs text-slate-500">
                                                    {{ collect([
                                                        $selectedDeliverySpeed,
                                                        $selectedCarrierName,
                                                        $estimatedMinDays !== null && $estimatedMaxDays !== null
                                                            ? $estimatedMinDays.'-'.$estimatedMaxDays.' days'
                                                            : null,
                                                    ])->filter()->implode(' | ') }}
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                </div>

                {{-- Activity --}}
                <div class="merchant-card">
                    <div class="border-b border-stone-200 px-6 py-4">
                        <h3 class="font-heading text-xl font-semibold">Order Activity</h3>
                    </div>
                    <div class="relative p-6">
                        @if ($order->events->count() > 1)
                            <div class="absolute bottom-10 left-9 top-10 w-px bg-slate-200" aria-hidden="true"></div>
                        @endif
                        <div class="space-y-8">
                            @forelse ($order->events as $event)
                                <div class="relative flex gap-6">
                                    <div class="z-10 flex h-6 w-6 items-center justify-center rounded-full bg-brand ring-4 ring-white">
                                        <span class="material-symbols-outlined text-[14px] text-white">done</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <p class="font-semibold text-stone-900">{{ $event->title }}</p>
                                            <span class="text-sm text-slate-500">{{ $event->created_at?->format('M j, g:i A') ?? 'Time not recorded' }}</span>
                                        </div>
                                        @if ($event->description)
                                            <p class="mt-1 text-sm text-slate-500">{{ $event->description }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-slate-400">
                                            {{ \App\Support\OrderLifecycle::eventTypeLabel($event->event_type) }}
                                            · {{ $event->actor?->name ?? 'System' }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-10 text-center text-sm text-slate-600">
                                    No order activity has been recorded yet. Future status changes and important actions will appear here.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right column --}}
            <div class="space-y-8 lg:col-span-4">
                {{-- Status manager --}}
                <div id="status-manager" class="merchant-card scroll-mt-24 p-6">
                    <h3 class="mb-4 font-heading text-xl font-semibold">Status Manager</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Current State</label>
                            <div class="flex items-center gap-2 rounded-lg border border-stone-200 bg-brand-soft p-3">
                                <span class="material-symbols-outlined text-brand">check_circle</span>
                                <span class="font-semibold">{{ $orderStatusLabel }}</span>
                            </div>
                        </div>

                        @if ($canManageOrders && $availableOrderStatuses->count() > 1)
                            <span class="material-symbols-outlined flex justify-center text-slate-400">arrow_downward</span>
                            <form action="{{ route('orders.updateStatus', $order->id) }}" method="POST" class="space-y-4">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label for="status" class="mb-2 block text-sm font-semibold text-slate-700">Move to Next State</label>
                                    <select name="status" id="status" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                        @foreach ($availableOrderStatuses as $status)
                                            <option value="{{ $status }}" @selected($order->status === $status)>
                                                {{ \App\Support\OrderLifecycle::orderStatusLabel($status) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="w-full rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-brand-hover">
                                    Save status
                                </button>
                            </form>
                        @elseif ($canManageOrders)
                            <div class="rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                No further status changes are available for this order.
                            </div>
                        @else
                            <div class="rounded-lg border border-amber-100 bg-amber-50/80 px-4 py-3 text-sm text-amber-950">
                                You can view this order, but your store role cannot change its status.
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Customer --}}
                <div class="merchant-card overflow-hidden">
                    <div class="p-6">
                        <h3 class="mb-6 font-heading text-xl font-semibold">Customer Profile</h3>
                        <div class="mb-6 flex items-center gap-4">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-soft font-heading text-xl font-bold text-brand">
                                {{ strtoupper($customerInitials) ?: 'C' }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="truncate text-lg font-bold">{{ $customerName }}</h4>
                                <p class="truncate text-sm text-slate-500">{{ $order->customer_email ?? 'Email not recorded' }}</p>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <div>
                                <h5 class="mb-3 text-[12px] font-bold uppercase tracking-[0.05em] text-slate-400">Shipping Address</h5>
                                @if ($shipping)
                                    <p class="text-base leading-relaxed">
                                        {{ $shipping->address_line1 }}@if ($shipping->address_line2)<br>{{ $shipping->address_line2 }}@endif<br>
                                        {{ $shipping->city }}, {{ $shipping->state }} {{ $shipping->postal_code }}<br>
                                        {{ $shipping->country }}
                                    </p>
                                @else
                                    <p class="text-sm italic text-slate-500">Not recorded</p>
                                @endif
                            </div>
                            <div>
                                <h5 class="mb-3 text-[12px] font-bold uppercase tracking-[0.05em] text-slate-400">Billing Address</h5>
                                @if ($billing)
                                    <p class="text-base leading-relaxed">
                                        {{ $billing->address_line1 }}@if ($billing->address_line2)<br>{{ $billing->address_line2 }}@endif<br>
                                        {{ $billing->city }}, {{ $billing->state }} {{ $billing->postal_code }}<br>
                                        {{ $billing->country }}
                                    </p>
                                @elseif ($order->billing_same_as_shipping && $shipping)
                                    <p class="text-sm italic text-slate-500">Same as shipping address</p>
                                @else
                                    <p class="text-sm italic text-slate-500">Not recorded</p>
                                @endif
                            </div>
                            <div>
                                <h5 class="mb-3 text-[12px] font-bold uppercase tracking-[0.05em] text-slate-400">Phone</h5>
                                <p class="text-sm text-slate-700">{{ $order->customer_phone ?? $shipping?->phone ?? 'Not recorded' }}</p>
                            </div>
                        </div>
                        @if ($order->customer_id)
                            <a href="{{ route('customersProfile', $order->customer_id) }}" class="text-brand mt-6 inline-flex text-sm font-bold hover:underline">
                                View customer
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Fulfillment --}}
                <div class="merchant-card p-6">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <h3 class="font-heading text-xl font-semibold">Fulfillment</h3>
                        <span @class([
                            'order-status-pill',
                            'bg-sky-100 text-sky-800' => $isOrderExternallyManaged,
                            'bg-danger-soft text-danger' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_UNFULFILLED,
                            'bg-amber-100 text-amber-800' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_PARTIAL,
                            'bg-green-100 text-green-700' => ! $isOrderExternallyManaged && $order->fulfillment_status === \App\Support\OrderLifecycle::FULFILLMENT_FULFILLED,
                            'bg-slate-100 text-slate-700' => ! $isOrderExternallyManaged
                                && ! in_array($order->fulfillment_status, [
                                    \App\Support\OrderLifecycle::FULFILLMENT_UNFULFILLED,
                                    \App\Support\OrderLifecycle::FULFILLMENT_PARTIAL,
                                    \App\Support\OrderLifecycle::FULFILLMENT_FULFILLED,
                                ], true),
                        ])>{{ $fulfillmentStatusLabel }}</span>
                    </div>

                    @if ($isOrderExternallyManaged)
                        <div class="mb-4 rounded-xl border border-sky-100 bg-sky-50/80 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-sky-800/80">Fulfillment managed externally</p>
                            @if ($hasExternalFulfillmentDetails)
                                <dl class="mt-3 grid gap-3 text-sm">
                                    @if ($externalCarrierName)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Carrier</dt>
                                            <dd class="mt-1 font-semibold">{{ $externalCarrierName }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalFulfillmentStatus)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">External status</dt>
                                            <dd class="mt-1 font-semibold">{{ str($externalFulfillmentStatus)->replace('_', ' ')->title() }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalTrackingNumber)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking number</dt>
                                            <dd class="mt-1 break-all font-semibold">{{ $externalTrackingNumber }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalTrackingUrl)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking link</dt>
                                            <dd class="mt-1"><a href="{{ $externalTrackingUrl }}" target="_blank" rel="noopener" class="text-brand font-semibold hover:underline">Open tracking</a></dd>
                                        </div>
                                    @elseif (! $externalTrackingNumber)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking</dt>
                                            <dd class="mt-1 text-sm text-slate-600">No tracking update received yet.</dd>
                                        </div>
                                    @endif
                                    @if ($externalShippedAt)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Shipped at</dt>
                                            <dd class="mt-1 font-semibold">{{ $externalShippedAt }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalDeliveredAt)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Delivered at</dt>
                                            <dd class="mt-1 font-semibold">{{ $externalDeliveredAt }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            @else
                                <p class="mt-3 text-sm leading-relaxed text-slate-700">
                                    Fulfillment is managed by the external storefront. No shipment update has been received yet.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($isOrderExternallyManaged)
                        <details class="mb-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
                            <summary class="cursor-pointer text-xs font-bold uppercase tracking-wide text-slate-500">Internal fulfillment quantities (advanced)</summary>
                            <div class="mt-3 space-y-2">
                                @foreach ($order->items as $item)
                                    @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate text-slate-700">{{ $item->product_name }}</span>
                                        <span class="font-semibold tabular-nums">{{ $remaining }} / {{ $item->quantity }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">These counts reflect dashboard-managed shipments only. External storefront fulfillment is shown above.</p>
                        </details>
                    @else
                        <div class="mb-4 rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Remaining to fulfill</p>
                            <div class="mt-3 space-y-2">
                                @foreach ($order->items as $item)
                                    @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate text-slate-700">{{ $item->product_name }}</span>
                                        <span class="font-semibold tabular-nums">{{ $remaining }} / {{ $item->quantity }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @include('user_view.orders.partials.fedex_shipping_ops')

                    @if ($canManageOrders && $remainingTotal > 0 && ! $isOrderExternallyManaged)
                        <form method="POST" action="{{ route('orders.shipments.store', $order) }}" class="space-y-4">
                            @csrf
                            @if ($routedOriginLocationId || $pickupLocationName)
                                <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs leading-relaxed text-indigo-900">
                                    Fulfillment origin selected by service area routing{{ $routedOriginLocationId ? ': '.($fulfillmentLocations->firstWhere('id', $routedOriginLocationId)?->name ?? data_get($fulfillmentRouting, 'origin_name', 'Selected location')) : '' }}.
                                    @if ($pickupLocationName)
                                        Pickup location selected: {{ $pickupLocationName }}.
                                    @endif
                                    You can override the ship-from location before creating the shipment.
                                </div>
                            @endif
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Ship from</label>
                                <select name="origin_location_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">No location selected</option>
                                    @foreach ($fulfillmentLocations as $location)
                                        <option value="{{ $location->id }}" @selected((string) old('origin_location_id', $routedOriginLocationId ?: '') === (string) $location->id)>{{ $location->name }}{{ $location->is_default ? ' (default)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Carrier</label>
                                <select name="carrier_account_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">No carrier selected</option>
                                    @foreach ($carrierAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Delivery method</label>
                                <select name="shipping_method_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
                                    <option value="">No delivery method selected</option>
                                    @foreach ($shippingMethods as $method)
                                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Items</p>
                                @foreach ($order->items as $item)
                                    @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                    @if ($remaining > 0)
                                        <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-slate-800">{{ $item->product_name }}</span>
                                                <span class="text-xs text-slate-500">{{ $remaining }} remaining</span>
                                            </span>
                                            <input name="items[{{ $item->id }}]" type="number" min="0" max="{{ $remaining }}" value="{{ $remaining }}" class="h-9 w-20 rounded-lg border border-slate-200 bg-white px-2 text-right text-sm">
                                        </label>
                                    @endif
                                @endforeach
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tracking Number</label>
                                <input name="tracking_number" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="Enter tracking #">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tracking link</label>
                                <input name="tracking_url" type="url" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-800 shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="https://">
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Packages</span>
                                    <input name="package_count" type="number" min="1" value="1" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Weight</span>
                                    <input name="package_weight" type="number" min="0" step="0.001" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cost</span>
                                    <input name="shipping_cost" type="number" min="0" step="0.01" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                </label>
                            </div>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Internal note</span>
                                <textarea name="note" rows="2" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional"></textarea>
                            </label>
                            <button type="submit" class="w-full rounded-xl border-2 border-brand py-3 text-sm font-bold text-brand transition hover:bg-brand-soft">
                                Create shipment
                            </button>
                        </form>
                    @elseif ($canManageOrders && $remainingTotal === 0 && ! $isOrderExternallyManaged)
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            All items on this order are fulfilled.
                        </div>
                    @elseif ($isOrderExternallyManaged && $canManageOrders && $remainingTotal > 0)
                        <details class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700">Advanced: create internal shipment override</summary>
                            <p class="mt-2 text-xs leading-relaxed text-slate-500">Only use this if you need to record fulfillment inside the dashboard in addition to external updates.</p>
                            <form method="POST" action="{{ route('orders.shipments.store', $order) }}" class="mt-4 space-y-4 rounded-xl border border-slate-200 bg-white p-4">
                                @csrf
                                <p class="font-semibold text-slate-900">Create shipment</p>
                                <div class="grid gap-3">
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ship from</span>
                                        <select name="origin_location_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                            <option value="">No location selected</option>
                                            @foreach ($fulfillmentLocations as $location)
                                                <option value="{{ $location->id }}" @selected((string) old('origin_location_id', $routedOriginLocationId ?: '') === (string) $location->id)>{{ $location->name }}{{ $location->is_default ? ' (default)' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <div class="space-y-2">
                                    @foreach ($order->items as $item)
                                        @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                        @if ($remaining > 0)
                                            <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                                                <span class="min-w-0 truncate font-medium text-slate-800">{{ $item->product_name }}</span>
                                                <input name="items[{{ $item->id }}]" type="number" min="0" max="{{ $remaining }}" value="{{ $remaining }}" class="h-9 w-20 rounded-lg border border-slate-200 bg-white px-2 text-right text-sm">
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                                <button class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-800">Create internal shipment</button>
                            </form>
                        </details>
                    @endif

                    <div class="mt-5 space-y-4">
                        @forelse ($order->shipments as $shipment)
                            @php
                                $isExternalShipment = data_get($shipment->metadata, 'source') === 'external';
                                $externalShipmentCarrier = data_get($shipment->metadata, 'carrier_name');
                            @endphp
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $shipment->shipment_number }}</p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            @if ($isExternalShipment)
                                                {{ $externalShipmentCarrier ?: 'External carrier' }} · Synced from external storefront
                                            @else
                                                {{ $shipment->carrierAccount?->display_name ?? 'No carrier account' }}{{ $shipment->shippingMethod ? ' | '.$shipment->shippingMethod->name : '' }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ \App\Support\OrderLifecycle::shipmentStatusBadgeClass($shipment->status) }}">
                                        {{ \App\Support\OrderLifecycle::shipmentStatusLabel($shipment->status) }}
                                    </span>
                                </div>
                                <div class="mt-3 space-y-1 text-sm text-slate-600">
                                    @foreach ($shipment->items as $shipmentItem)
                                        <p>{{ $shipmentItem->quantity }} x {{ $shipmentItem->orderItem?->product_name ?? 'Order item' }}</p>
                                    @endforeach
                                    <p>Created {{ $shipment->created_at?->format('M j, Y g:i A') }}</p>
                                    @if ($shipment->shipped_at)<p>Shipped {{ $shipment->shipped_at->format('M j, Y g:i A') }}</p>@endif
                                    @if ($shipment->delivered_at)<p>Delivered {{ $shipment->delivered_at->format('M j, Y g:i A') }}</p>@endif
                                </div>
                                @if ($shipment->tracking_number || $shipment->tracking_url)
                                    <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                        <p class="font-semibold text-slate-900">{{ $shipment->tracking_number ?: 'Tracking link' }}</p>
                                        @if ($shipment->tracking_url)
                                            <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="text-brand mt-1 inline-flex hover:underline">Open tracking</a>
                                        @endif
                                    </div>
                                @endif

                                @if ($canManageOrders && ! $isExternalShipment)
                                    <form method="POST" action="{{ route('shipments.tracking.update', $shipment) }}" class="mt-4 grid gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input name="tracking_number" value="{{ $shipment->tracking_number }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Tracking number">
                                        <input name="tracking_url" value="{{ $shipment->tracking_url }}" type="url" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Tracking link">
                                        <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Update tracking</button>
                                    </form>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if (in_array($shipment->status, [\App\Models\Shipment::STATUS_PENDING, \App\Models\Shipment::STATUS_LABEL_CREATED], true))
                                            <form method="POST" action="{{ route('shipments.mark-shipped', $shipment) }}">@csrf<button class="rounded-lg bg-brand px-3 py-2 text-xs font-bold text-white transition hover:bg-brand-hover">Mark shipped</button></form>
                                            <form method="POST" action="{{ route('shipments.cancel', $shipment) }}">@csrf<button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Cancel</button></form>
                                        @endif
                                        @if (in_array($shipment->status, [\App\Models\Shipment::STATUS_SHIPPED, \App\Models\Shipment::STATUS_IN_TRANSIT], true))
                                            <form method="POST" action="{{ route('shipments.mark-delivered', $shipment) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Mark delivered</button></form>
                                        @endif
                                        @if (! in_array($shipment->status, [\App\Models\Shipment::STATUS_DELIVERED, \App\Models\Shipment::STATUS_FAILED, \App\Models\Shipment::STATUS_CANCELLED], true))
                                            <form method="POST" action="{{ route('shipments.mark-failed', $shipment) }}">@csrf<button class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">Mark failed</button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-600">
                                No shipments have been created yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Internal notes --}}
                <div class="merchant-card p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="font-heading text-xl font-semibold">Internal Notes</h3>
                        <span class="material-symbols-outlined text-slate-400">lock</span>
                    </div>

                    @if ($canManageOrders)
                        <form action="{{ route('orders.notes.store', $order) }}" method="POST">
                            @csrf
                            <label for="order-note-body" class="sr-only">Note for your team</label>
                            <textarea id="order-note-body" name="body" class="h-32 w-full resize-none rounded-xl border border-stone-200 bg-white p-4 text-sm text-stone-800 shadow-sm placeholder:text-stone-400 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20" placeholder="Add a note for the team..."></textarea>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="text-brand rounded-lg px-4 py-2 text-sm font-bold transition hover:bg-brand-soft">
                                    Add Note
                                </button>
                            </div>
                        </form>
                    @endif

                    <div class="mt-5 space-y-3">
                        @forelse ($noteEvents as $note)
                            <div class="rounded-xl border border-slate-100 bg-slate-50/90 p-4 text-sm shadow-sm">
                                <p class="whitespace-pre-line leading-relaxed text-slate-800">{{ $note->description }}</p>
                                <p class="mt-3 text-xs text-slate-400">{{ $note->actor?->name ?? 'System' }} · {{ $note->created_at?->format('M j, Y g:i A') }}</p>
                            </div>
                        @empty
                            @if ($order->notes)
                                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $order->notes }}</p>
                            @elseif (! $canManageOrders)
                                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center text-sm text-slate-500">No notes yet.</div>
                            @endif
                        @endforelse
                    </div>
                </div>

                {{-- After-sales: returns, refunds, exchanges --}}
                <div id="returns-refunds" class="merchant-card scroll-mt-24 p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-heading text-xl font-semibold">After-sales service</h3>
                            <p class="mt-1 text-sm text-slate-600">Record and manage return, refund or exchange decisions after a customer contacts your store.</p>
                            <p class="mt-2 text-sm text-slate-500">Customer requests are received through your store’s normal support channels. Use this workspace to record and process the agreed action.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($canManageOrders && $canRecordReturn)
                                <button type="button" onclick="document.getElementById('start-return-form')?.classList.toggle('hidden')" class="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-white transition hover:opacity-90">Record return</button>
                            @endif
                            @if ($canManageOrders && bccomp((string) $remainingRefundableAmount, '0', 4) > 0)
                                <button type="button" onclick="document.getElementById('issue-refund-form')?.classList.toggle('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 hover:bg-stone-50">Issue refund</button>
                            @endif
                            @if ($canManageOrders && $canCreateExchange)
                                <button type="button" onclick="document.getElementById('start-exchange-form')?.classList.toggle('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 hover:bg-stone-50">Create exchange</button>
                            @endif
                        </div>
                    </div>

                    @if ($canManageOrders && ! $canRecordReturn && $returnEligibilityMessage)
                        <p class="mt-4 text-sm text-slate-500">{{ $returnEligibilityMessage }}</p>
                    @endif

                    @if ($canManageOrders && ! $canCreateExchange && $exchangeEligibilityMessage)
                        <p class="mt-4 text-sm text-slate-500">{{ $exchangeEligibilityMessage }}</p>
                    @endif

                    @if ($canManageOrders && $canRecordReturn)
                        <form id="start-return-form" method="POST" action="{{ route('orders.returns.store', $order) }}" class="mt-5 hidden space-y-4 rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Return reason</span>
                                    <select name="return_reason_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                        <option value="">Select a reason</option>
                                        @foreach ($returnReasons as $reason)
                                            <option value="{{ $reason->id }}" @selected((string) old('return_reason_id') === (string) $reason->id)>{{ $reason->label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Tracking reference</span>
                                    <input type="text" name="tracking_reference" value="{{ old('tracking_reference') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Optional carrier tracking">
                                </label>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm font-medium text-slate-700">Items to return</p>
                                @foreach ($returnableItems as $item)
                                    @php $remainingQty = (int) ($remainingReturnableQuantities[$item->id] ?? 0); @endphp
                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white px-3 py-3 text-sm">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $item->product_name }}</p>
                                            <p class="text-xs text-slate-500">{{ $item->variant_label ?: 'Standard' }} · {{ $remainingQty }} returnable</p>
                                        </div>
                                        <label class="flex items-center gap-2">
                                            <span class="text-xs text-slate-500">Qty</span>
                                            @php
                                                $oldReturnQty = old('items.'.$item->id, 0);
                                                if (is_array($oldReturnQty)) {
                                                    $oldReturnQty = $oldReturnQty['quantity'] ?? 0;
                                                }
                                            @endphp
                                            <input type="number" min="0" max="{{ $remainingQty }}" name="items[{{ $item->id }}]" value="{{ $oldReturnQty }}" class="w-20 rounded-lg border border-stone-200 px-2 py-1.5 text-sm">
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-700">Customer’s message</span>
                                <textarea name="customer_notes" rows="2" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Record what the customer reported by email, phone or chat.">{{ old('customer_notes') }}</textarea>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-700">Return instructions</span>
                                <textarea name="manual_instructions" rows="2" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Tell the customer how to send the items back">{{ old('manual_instructions') }}</textarea>
                            </label>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-700">Internal team notes</span>
                                <textarea name="merchant_notes" rows="2" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Visible to your team">{{ old('merchant_notes') }}</textarea>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-white">Create return record</button>
                                <button type="button" onclick="document.getElementById('start-return-form')?.classList.add('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700">Cancel</button>
                            </div>
                        </form>
                    @endif

                    @if ($canManageOrders && bccomp((string) $remainingRefundableAmount, '0', 4) > 0)
                        <form id="issue-refund-form" method="POST" action="{{ route('orders.refunds.store', $order) }}" class="mt-5 hidden space-y-4 rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                            <p class="text-sm text-slate-600">Remaining refundable: <span class="font-semibold text-slate-900">{{ MoneyDisplay::format($remainingRefundableAmount, $currency) }}</span>. Leave amount blank to refund the full remaining balance.</p>
                            <p class="text-sm text-slate-600">Issuing a refund returns money only. It does not return or restock products. If goods are coming back, record and receive the return separately.</p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Refund amount</span>
                                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" placeholder="Full remaining">
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Shipping refund</span>
                                    <input type="number" step="0.01" min="0" name="shipping_amount" value="{{ old('shipping_amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Shipping tax refund</span>
                                    <input type="number" step="0.01" min="0" name="shipping_tax_amount" value="{{ old('shipping_tax_amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Tax refund</span>
                                    <input type="number" step="0.01" min="0" name="tax_amount" value="{{ old('tax_amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                </label>
                                <label class="block text-sm sm:col-span-2">
                                    <span class="mb-1 block font-medium text-slate-700">Other adjustment</span>
                                    <input type="number" step="0.01" min="0" name="other_amount" value="{{ old('other_amount') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                                </label>
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm font-medium text-slate-700">Item quantities (optional)</p>
                                @foreach ($order->items as $item)
                                    @php $refundableQty = max(0, (int) $item->quantity - (int) $item->refunded_quantity); @endphp
                                    @if ($refundableQty > 0)
                                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white px-3 py-3 text-sm">
                                            <div>
                                                <p class="font-medium text-slate-900">{{ $item->product_name }}</p>
                                                <p class="text-xs text-slate-500">{{ $refundableQty }} refundable</p>
                                            </div>
                                            @php
                                                $oldRefundQty = old('items.'.$item->id, 0);
                                                if (is_array($oldRefundQty)) {
                                                    $oldRefundQty = $oldRefundQty['quantity'] ?? 0;
                                                }
                                            @endphp
                                            <input type="number" min="0" max="{{ $refundableQty }}" name="items[{{ $item->id }}]" value="{{ $oldRefundQty }}" class="w-20 rounded-lg border border-stone-200 px-2 py-1.5 text-sm">
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium text-slate-700">Reason</span>
                                <input type="text" name="reason" value="{{ old('reason') }}" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                            </label>
                            @if ($isOrderExternallyManaged)
                                <input type="hidden" name="processed_externally" value="1">
                                <p class="text-xs text-slate-500">Record this refund only after it has been processed through the original selling channel or payment provider.</p>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-white">Process refund</button>
                                <button type="button" onclick="document.getElementById('issue-refund-form')?.classList.add('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700">Cancel</button>
                            </div>
                        </form>
                    @endif

                    @if ($canManageOrders && $canCreateExchange)
                        <form id="start-exchange-form" method="POST" action="{{ route('orders.exchanges.store', $order) }}" class="mt-5 hidden space-y-4 rounded-xl border border-stone-200 bg-stone-50/70 p-4">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Original item</span>
                                    <select name="order_item_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" required>
                                        @foreach ($exchangeableItems as $item)
                                            @php $exchangeQty = (int) ($remainingExchangeableQuantities[$item->id] ?? 0); @endphp
                                            <option value="{{ $item->id }}">{{ $item->product_name }} ({{ $item->variant_label ?: 'Standard' }}) · {{ $exchangeQty }} left</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Quantity</span>
                                    <input type="number" min="1" name="quantity" value="1" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" required>
                                </label>
                                <label class="block text-sm">
                                    <span class="mb-1 block font-medium text-slate-700">Replacement variant</span>
                                    <select name="replacement_variant_id" class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" required>
                                        @foreach ($exchangeVariants as $variant)
                                            <option value="{{ $variant->id }}">{{ $variant->product?->name }} · {{ $variant->sku }} · {{ MoneyDisplay::format($variant->price, $currency) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-brand px-4 text-sm font-semibold text-white">Create exchange</button>
                                <button type="button" onclick="document.getElementById('start-exchange-form')?.classList.add('hidden')" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700">Cancel</button>
                            </div>
                        </form>
                    @endif

                    <div class="mt-6 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Returns</h4>
                            <p class="mt-1 text-xs text-slate-500">Goods coming back to your store</p>
                        </div>
                        @forelse ($order->returns as $return)
                            <div class="rounded-xl border border-stone-200 bg-white p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $return->return_number }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ \App\Support\ReturnLifecycle::statusLabel($return->status) }}
                                            @if ($return->reason)
                                                · {{ $return->reason->label }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="order-status-pill bg-stone-100 text-stone-700">{{ \App\Support\ReturnLifecycle::statusLabel($return->status) }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @foreach ($return->items as $returnItem)
                                        <div class="rounded-lg bg-stone-50 px-3 py-2 text-sm">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="font-medium text-slate-800">{{ $returnItem->product_name_snapshot }}</p>
                                                <p class="text-xs text-slate-600">
                                                    Req {{ $returnItem->requested_quantity }}
                                                    @if ((int) $returnItem->received_quantity > 0)
                                                        · Recv {{ $returnItem->received_quantity }}
                                                    @endif
                                                    @if ((int) $returnItem->restocked_quantity > 0)
                                                        · Restocked {{ $returnItem->restocked_quantity }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if ($canManageOrders)
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @if (\App\Support\ReturnLifecycle::canTransition($return->status, \App\Support\ReturnLifecycle::STATUS_APPROVED))
                                            <form method="POST" action="{{ route('returns.approve', $return) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">Approve</button></form>
                                            <form method="POST" action="{{ route('returns.reject', $return) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Reject</button></form>
                                        @endif
                                        @if (\App\Support\ReturnLifecycle::canTransition($return->status, \App\Support\ReturnLifecycle::STATUS_RECEIVED))
                                            <form method="POST" action="{{ route('returns.receive', $return) }}" class="w-full space-y-3 rounded-xl border border-stone-200 bg-stone-50 p-3">
                                                @csrf
                                                @foreach ($return->items as $returnItem)
                                                    @php $maxRecv = (int) $returnItem->approved_quantity; @endphp
                                                    <div class="grid gap-2 sm:grid-cols-4">
                                                        <input type="hidden" name="items[{{ $returnItem->order_item_id }}][received_quantity]" value="{{ $maxRecv }}">
                                                        <p class="sm:col-span-4 text-xs font-medium text-slate-700">{{ $returnItem->product_name_snapshot }}</p>
                                                        <label class="text-xs">Condition
                                                            <select name="items[{{ $returnItem->order_item_id }}][condition]" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5">
                                                                <option value="sellable">Sellable</option>
                                                                <option value="damaged">Damaged</option>
                                                                <option value="defective">Defective</option>
                                                                <option value="non_sellable">Non-sellable</option>
                                                            </select>
                                                        </label>
                                                        <label class="text-xs">Restock?
                                                            <select name="items[{{ $returnItem->order_item_id }}][restock]" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5">
                                                                <option value="0">No</option>
                                                                <option value="1">Yes</option>
                                                            </select>
                                                        </label>
                                                        <label class="text-xs sm:col-span-2">Restock location
                                                            <select name="items[{{ $returnItem->order_item_id }}][restock_location_id]" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5">
                                                                <option value="">Select location</option>
                                                                @foreach ($fulfillmentLocations as $location)
                                                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                    </div>
                                                @endforeach
                                                <button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">Mark received</button>
                                            </form>
                                        @endif
                                        @if (\App\Support\ReturnLifecycle::canTransition($return->status, \App\Support\ReturnLifecycle::STATUS_COMPLETED))
                                            <form method="POST" action="{{ route('returns.complete', $return) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">Complete return</button></form>
                                        @endif
                                        @if (\App\Support\ReturnLifecycle::canTransition($return->status, \App\Support\ReturnLifecycle::STATUS_CANCELLED))
                                            <form method="POST" action="{{ route('returns.cancel', $return) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Cancel return</button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-600">
                                @if (! $canRecordReturn)
                                    {{ $returnEligibilityMessage ?: 'No returnable items are available on this order.' }}
                                @else
                                    No returns are recorded yet.
                                @endif
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-6 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Refunds</h4>
                            <p class="mt-1 text-xs text-slate-500">Money returned to the customer</p>
                        </div>
                        @forelse ($order->refunds as $refund)
                            <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold text-slate-900">{{ $refund->refund_number }} · {{ MoneyDisplay::format($refund->amount, $refund->currency_code) }}</p>
                                    <span class="order-status-pill bg-stone-100 text-stone-700">{{ \App\Support\RefundLifecycle::statusLabel($refund->status) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ ucfirst($refund->method) }}
                                    @if ($refund->reason)
                                        · {{ $refund->reason }}
                                    @endif
                                </p>
                                @if (
                                    $canManageOrders
                                    && $refund->method === \App\Support\RefundLifecycle::METHOD_PROVIDER
                                    && in_array($refund->status, [
                                        \App\Support\RefundLifecycle::STATUS_PENDING,
                                        \App\Support\RefundLifecycle::STATUS_PROCESSING,
                                        \App\Support\RefundLifecycle::STATUS_FAILED,
                                    ], true)
                                )
                                    <form method="POST" action="{{ route('orders.refunds.recheck', [$order, $refund]) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700 transition hover:border-stone-300 hover:bg-stone-50">
                                            {{ $refund->status === \App\Support\RefundLifecycle::STATUS_FAILED ? 'Retry refund' : 'Recheck refund' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-600">No refunds recorded yet.</div>
                        @endforelse
                    </div>

                    <div class="mt-6 space-y-4">
                        <div>
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Exchanges</h4>
                            <p class="mt-1 text-xs text-slate-500">Replacement product for the customer</p>
                        </div>
                        @forelse ($order->exchanges as $exchange)
                            <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-semibold text-slate-900">{{ $exchange->exchange_number }}</p>
                                    <span class="order-status-pill bg-stone-100 text-stone-700">{{ \App\Support\ExchangeLifecycle::statusLabel($exchange->status) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Price difference: {{ MoneyDisplay::format($exchange->price_difference, $exchange->currency_code) }}</p>
                                @if ($canManageOrders && in_array($exchange->status, ['requested', 'reserved'], true) && bccomp((string) $exchange->balance_due, '0', 4) > 0)
                                    @php
                                        $remainingBalance = \App\Support\Money\CurrencyPrecision::roundMajor(
                                            bcsub((string) $exchange->balance_due, (string) $exchange->collected_amount, 8),
                                            (string) $exchange->currency_code
                                        );
                                    @endphp
                                    @if (bccomp($remainingBalance, '0', 4) > 0)
                                        <form method="POST" action="{{ route('exchanges.collect', $exchange) }}" class="mt-3 space-y-2 rounded-lg border border-amber-200 bg-amber-50/60 p-3">
                                            @csrf
                                            <p class="text-xs text-amber-900">Remaining balance due: <span class="font-semibold">{{ MoneyDisplay::format($remainingBalance, $exchange->currency_code) }}</span></p>
                                            <input type="hidden" name="collected_amount" value="{{ $remainingBalance }}">
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <label class="block text-xs">
                                                    <span class="mb-1 block font-medium text-slate-700">Collection method</span>
                                                    <select name="collection_method" class="w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm" required>
                                                        <option value="manual">Manual</option>
                                                        <option value="external">External</option>
                                                    </select>
                                                </label>
                                                <label class="block text-xs">
                                                    <span class="mb-1 block font-medium text-slate-700">Collection reference</span>
                                                    <input type="text" name="collection_reference" class="w-full rounded-lg border border-stone-200 bg-white px-2 py-1.5 text-sm" required>
                                                </label>
                                            </div>
                                            <button type="submit" class="inline-flex h-9 items-center rounded-xl border border-amber-300 bg-white px-3 text-xs font-semibold text-amber-900">Record collection</button>
                                        </form>
                                    @endif
                                @endif
                                @if ($canManageOrders && in_array($exchange->status, ['requested', 'reserved', 'processing'], true))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('exchanges.complete', $exchange) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex h-9 items-center rounded-xl bg-brand px-3 text-xs font-semibold text-white">
                                                {{ $exchange->status === 'processing' ? 'Resume completion' : 'Complete exchange' }}
                                            </button>
                                        </form>
                                        @if ($exchange->status !== 'processing')
                                            <form method="POST" action="{{ route('exchanges.cancel', $exchange) }}">@csrf<button type="submit" class="inline-flex h-9 items-center rounded-xl border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Cancel</button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-600">No exchanges recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
