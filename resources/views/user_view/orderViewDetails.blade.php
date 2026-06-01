@extends('layouts.user.user-sidebar')

@section('title', 'Order ' . strtoupper($order->order_number) . ' | BaaS Core')
@section('sidebar_brand_title', 'BaaS Admin')
@section('sidebar_brand_subtitle', optional($selectedStore)->name ?? 'E-commerce Portal')

@section('topbar')
    <header class="sticky top-0 z-30 flex h-[4.25rem] shrink-0 items-center justify-between gap-4 border-b border-slate-200/80 bg-white/95 px-4 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-white/80 md:px-8">
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <button type="button" id="sidebarToggle" onclick="openSidebar()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm md:hidden" aria-label="Open sidebar">
                <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
                </svg>
            </button>
            <div class="min-w-0">
                <h1 class="truncate font-[Poppins] text-lg font-semibold tracking-tight text-slate-900 md:text-xl">Order {{ strtoupper($order->order_number) }}</h1>
                <p class="hidden text-xs text-slate-500 sm:block">Order detail, status history, and customer snapshot.</p>
            </div>
        </div>
        <a href="{{ route('orders') }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50/60 hover:text-indigo-900">
            <span aria-hidden="true">←</span> Back to orders
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
        $noteEvents = $order->events->where('event_type', \App\Support\OrderLifecycle::EVENT_ORDER_NOTE_ADDED);
        $sourceLabels = [
            'external_checkout' => 'External checkout',
            'platform_checkout' => 'Platform checkout',
            'developer_storefront' => 'Developer Storefront',
            'manual' => 'Manual order',
        ];
        $sourceLabel = $sourceLabels[$order->order_source] ?? ($order->order_source ? str($order->order_source)->replace('_', ' ')->title() : 'Manual order');
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

        $card = 'rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.03]';
        $cardHeader = 'border-b border-slate-100 px-5 py-4 md:px-6';
        $metaTile = 'rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3';
    @endphp

    <div class="mx-auto max-w-[1480px] space-y-6 pb-10 pt-2 md:space-y-8 md:pt-4">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}" class="font-medium transition hover:text-indigo-700">Dashboard</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <a href="{{ route('orders') }}" class="font-medium transition hover:text-indigo-700">Orders</a>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="font-semibold text-slate-800">{{ strtoupper($order->order_number) }}</span>
        </nav>

        {{-- Hero snapshot --}}
        <section class="{{ $card }} overflow-hidden p-5 md:p-7">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1 space-y-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Order snapshot</p>
                    <h2 class="font-[Poppins] text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">Order {{ strtoupper($order->order_number) }}</h2>
                    <p class="max-w-2xl text-sm leading-relaxed text-slate-600">
                        Placed {{ $order->placed_at ? $order->placed_at->format('F j, Y \a\t g:i A') : 'date not recorded' }}
                        <span class="text-slate-400">·</span>
                        <span class="font-medium text-slate-800">{{ $customerName }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 lg:max-w-md lg:justify-end">
                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide {{ \App\Support\OrderLifecycle::orderStatusBadgeClass($order->status) }}">
                        Order: {{ \App\Support\OrderLifecycle::orderStatusLabel($order->status) }}
                    </span>
                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide {{ \App\Support\OrderLifecycle::paymentStatusBadgeClass($order->payment_status) }}">
                        Payment: {{ \App\Support\OrderLifecycle::paymentStatusLabel($order->payment_status) }}
                    </span>
                    <span class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide {{ $isOrderExternallyManaged ? 'bg-sky-100 text-sky-800' : \App\Support\OrderLifecycle::fulfillmentStatusBadgeClass($order->fulfillment_status) }}">
                        Fulfillment: {{ $isOrderExternallyManaged ? 'Externally managed' : \App\Support\OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status) }}
                    </span>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                <div class="{{ $metaTile }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Items</p>
                    <p class="mt-1.5 text-xl font-semibold tabular-nums text-slate-900">{{ $order->item_count ?: $order->items->count() }}</p>
                </div>
                <div class="{{ $metaTile }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quantity</p>
                    <p class="mt-1.5 text-xl font-semibold tabular-nums text-slate-900">{{ $order->total_quantity ?: $order->items->sum('quantity') }}</p>
                </div>
                <div class="{{ $metaTile }} min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Source</p>
                    <p class="mt-1.5 truncate text-lg font-semibold text-slate-900" title="{{ $sourceLabel }}">{{ $sourceLabel }}</p>
                </div>
                <div class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/90 to-white px-4 py-3 ring-1 ring-indigo-100/80">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-800/80">Total</p>
                    <p class="mt-1.5 text-xl font-bold tabular-nums text-indigo-700 md:text-2xl">{{ $currency }}{{ number_format($displayTotal, 2) }}</p>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start xl:gap-8">
            <div class="min-w-0 space-y-6">
                {{-- Line items --}}
                <article class="{{ $card }} overflow-hidden">
                    <div class="{{ $cardHeader }}">
                        <h3 class="font-[Poppins] text-lg font-semibold text-slate-900">Order items</h3>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600">What the customer bought—names, options, and prices are frozen from checkout.</p>
                    </div>
                    <div class="space-y-3 p-4 md:p-5">
                        @forelse ($order->items as $item)
                            @php
                                $imagePath = $item->product_image_snapshot ?: $item->product?->images?->first()?->image_path;
                            @endphp
                            <div class="flex flex-col gap-4 rounded-xl border border-slate-100 bg-slate-50/40 p-4 transition hover:border-slate-200 hover:bg-white hover:shadow-sm sm:flex-row sm:items-start sm:justify-between sm:p-5">
                                <div class="flex min-w-0 flex-1 items-start gap-4">
                                    @if ($imagePath)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($imagePath) }}" class="h-20 w-20 shrink-0 rounded-xl border border-slate-200/80 object-cover shadow-sm" alt="{{ $item->product_name }}">
                                    @else
                                        <div class="grid h-20 w-20 shrink-0 place-items-center rounded-xl border border-dashed border-slate-200 bg-white text-slate-400" aria-hidden="true">
                                            <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" aria-hidden="true">
                                                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 20h16a2 2 0 002-2V8a2 2 0 00-2-2h-3.17a2 2 0 01-1.41-.59l-1.83-1.83A2 2 0 0010.17 4H4a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-base font-semibold leading-snug text-slate-900 md:text-lg">{{ $item->product_name }}</h4>
                                        <p class="mt-1 text-sm text-slate-600">{{ $item->variant_label ?: 'Default option' }}</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($item->sku_snapshot)
                                                <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">SKU {{ $item->sku_snapshot }}</span>
                                            @endif
                                            @if ($item->brand_name_snapshot)
                                                <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">{{ $item->brand_name_snapshot }}</span>
                                            @endif
                                            @if ($item->product_type_snapshot)
                                                <span class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">{{ ucfirst($item->product_type_snapshot) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="shrink-0 border-t border-slate-100 pt-3 text-left sm:border-0 sm:pt-0 sm:text-right">
                                    <p class="text-sm text-slate-500">Qty {{ $item->quantity }} × {{ $currency }}{{ number_format((float) $item->unit_price, 2) }}</p>
                                    <p class="mt-1 text-lg font-bold tabular-nums text-slate-900 md:text-xl">{{ $currency }}{{ number_format((float) $item->total, 2) }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-6 py-12 text-center">
                                <p class="text-sm font-medium text-slate-700">No line items on this order</p>
                                <p class="mt-1 text-sm text-slate-500">If something looks wrong, check the original channel or contact support.</p>
                            </div>
                        @endforelse
                    </div>
                </article>

                {{-- Payment summary --}}
                <article class="{{ $card }} p-5 md:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-[Poppins] text-lg font-semibold text-slate-900">Payment summary</h3>
                            <p class="mt-1 text-sm text-slate-600">
                                @if ($gatewayLabel)
                                    {{ $gatewayLabel }}{{ $order->payment_method ? ' · ' . str($order->payment_method)->replace('_', ' ')->title() : '' }}
                                @else
                                    {{ $order->payment_method ? str($order->payment_method)->replace('_', ' ')->title() : 'Payment method not recorded' }}
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide {{ \App\Support\OrderLifecycle::paymentStatusBadgeClass($order->payment_status) }}">
                            {{ \App\Support\OrderLifecycle::paymentStatusLabel($order->payment_status) }}
                        </span>
                    </div>

                    <div class="mt-6 divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/50">
                        <div class="flex justify-between gap-4 px-4 py-3 text-sm text-slate-700">
                            <span>Subtotal</span>
                            <span class="font-semibold tabular-nums">{{ $currency }}{{ number_format((float) $order->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between gap-4 px-4 py-3 text-sm text-slate-700">
                            <span>Shipping</span>
                            <span class="font-semibold tabular-nums">{{ $currency }}{{ number_format((float) $order->shipping, 2) }}</span>
                        </div>
                        <div class="flex justify-between gap-4 px-4 py-3 text-sm text-slate-700">
                            <span>Tax</span>
                            <span class="font-semibold tabular-nums">{{ $currency }}{{ number_format((float) $order->tax, 2) }}</span>
                        </div>
                        @if ((float) $order->discount > 0)
                            <div class="flex justify-between gap-4 px-4 py-3 text-sm text-emerald-800">
                                <span>Discount</span>
                                <span class="font-semibold tabular-nums">−{{ $currency }}{{ number_format((float) $order->discount, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 flex flex-wrap items-end justify-between gap-4 rounded-xl bg-indigo-50/70 px-4 py-4 ring-1 ring-indigo-100/80 md:px-5">
                        <span class="text-base font-semibold text-slate-900">Total</span>
                        <span class="text-2xl font-bold tabular-nums text-indigo-800 md:text-3xl">{{ $currency }}{{ number_format($displayTotal, 2) }}</span>
                    </div>
                </article>

                {{-- Payment & source --}}
                <article class="{{ $card }} p-5 md:p-6">
                    <h3 class="font-[Poppins] text-lg font-semibold text-slate-900">Payment and source</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">
                        @if ($order->order_source === 'external_checkout')
                            Payment status recorded from external checkout.
                        @elseif ($order->order_source === 'platform_checkout')
                            Payment was confirmed through platform checkout.
                        @else
                            Source and payment details captured for this order.
                        @endif
                    </p>

                    <div class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="{{ $metaTile }}">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Source</p>
                            <p class="mt-1.5 font-semibold text-slate-900">{{ $sourceLabel }}</p>
                        </div>
                        <div class="{{ $metaTile }}">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Channel</p>
                            <p class="mt-1.5 font-semibold text-slate-900">{{ $order->channel ? str($order->channel)->replace('_', ' ')->title() : 'Dashboard' }}</p>
                        </div>
                        @if ($order->external_order_number)
                            <div class="{{ $metaTile }}">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">External order</p>
                                <p class="mt-1.5 font-semibold text-slate-900">{{ $order->external_order_number }}</p>
                            </div>
                        @endif
                        @if ($order->external_checkout_reference)
                            <div class="{{ $metaTile }}">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Checkout reference</p>
                                <p class="mt-1.5 break-all font-semibold text-slate-900">{{ $order->external_checkout_reference }}</p>
                            </div>
                        @endif
                        @if ($platformCheckoutNumber)
                            <div class="{{ $metaTile }}">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Checkout</p>
                                <p class="mt-1.5 break-all font-semibold text-slate-900">{{ $platformCheckoutNumber }}</p>
                            </div>
                        @endif
                        @if ($paymentConnectionLabel)
                            <div class="{{ $metaTile }}">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Stripe connection</p>
                                <p class="mt-1.5 font-semibold text-slate-900">{{ $paymentConnectionLabel }}</p>
                            </div>
                        @endif
                        @if ($gatewayLabel)
                            <div class="{{ $metaTile }}">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Gateway</p>
                                <p class="mt-1.5 font-semibold text-slate-900">{{ $gatewayLabel }}</p>
                            </div>
                        @endif
                        @if ($order->payment_method)
                            <div class="{{ $metaTile }}">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Method</p>
                                <p class="mt-1.5 font-semibold text-slate-900">{{ str($order->payment_method)->replace('_', ' ')->title() }}</p>
                            </div>
                        @endif
                        @if ($order->payment_reference)
                            <div class="{{ $metaTile }} sm:col-span-2">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Payment reference</p>
                                <p class="mt-1.5 break-all font-semibold text-slate-900">{{ $order->payment_reference }}</p>
                            </div>
                        @endif
                        @if ($connectedAccountId)
                            <div class="{{ $metaTile }} sm:col-span-2">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Connected account</p>
                                <p class="mt-1.5 break-all font-semibold text-slate-900">{{ $connectedAccountId }}</p>
                            </div>
                        @endif
                        @if ($selectedDeliveryMethod)
                            <div class="{{ $metaTile }} sm:col-span-2">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Delivery method</p>
                                <p class="mt-1.5 font-semibold text-slate-900">{{ $selectedDeliveryMethod }}</p>
                                @if ($selectedDeliverySpeed || $selectedCarrierName || $estimatedMinDays !== null || $estimatedMaxDays !== null)
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ collect([
                                            $selectedDeliverySpeed,
                                            $selectedCarrierName,
                                            $estimatedMinDays !== null && $estimatedMaxDays !== null
                                                ? $estimatedMinDays . '-' . $estimatedMaxDays . ' days'
                                                : null,
                                        ])->filter()->implode(' | ') }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>

                {{-- Activity --}}
                <article class="{{ $card }} p-5 md:p-6">
                    <h3 class="font-[Poppins] text-lg font-semibold text-slate-900">Order activity</h3>
                    <p class="mt-1 text-sm text-slate-600">A clear history of what changed and when—helpful for support and audits.</p>

                    <div class="relative mt-6">
                        @if ($order->events->count() > 1)
                            <div class="absolute bottom-8 left-[15px] top-8 w-px bg-slate-200" aria-hidden="true"></div>
                        @endif

                        <div class="space-y-6">
                            @forelse ($order->events as $event)
                                <div class="relative flex gap-4 pl-1">
                                    <span class="relative z-10 mt-1.5 h-3 w-3 shrink-0 rounded-full border-[3px] border-white bg-indigo-600 shadow-[0_0_0_1px_rgba(148,163,184,0.5)]" aria-hidden="true"></span>
                                    <div class="min-w-0 flex-1 pb-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-slate-900">{{ $event->title }}</p>
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                                {{ \App\Support\OrderLifecycle::eventTypeLabel($event->event_type) }}
                                            </span>
                                        </div>
                                        @if ($event->description)
                                            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ $event->description }}</p>
                                        @endif
                                        <p class="mt-2 text-xs text-slate-400">
                                            {{ $event->actor?->name ?? 'System' }} · {{ $event->created_at?->format('M j, Y g:i A') ?? 'Time not recorded' }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-10 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-100" aria-hidden="true">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-slate-600">No order activity has been recorded yet. Future status changes and important actions will appear here.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </article>
            </div>

            <aside class="space-y-6 xl:sticky xl:top-6">
                <article class="{{ $card }} p-5 md:p-6">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Operations</p>
                    <h3 class="mt-1 font-[Poppins] text-lg font-semibold text-slate-900">Order status</h3>
                    <p class="mt-1 text-sm text-slate-600">Move this order only through allowed steps for your store.</p>

                    @if ($canManageOrders && $availableOrderStatuses->count() > 1)
                        <form action="{{ route('orders.updateStatus', $order->id) }}" method="POST" class="mt-5 space-y-4">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="status" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Next state</label>
                                <select name="status" id="status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                                    @foreach ($availableOrderStatuses as $status)
                                        <option value="{{ $status }}" @selected($order->status === $status)>
                                            {{ \App\Support\OrderLifecycle::orderStatusLabel($status) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700">
                                Save status
                            </button>
                        </form>
                    @elseif ($canManageOrders)
                        <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            No further status changes are available for this order.
                        </div>
                    @else
                        <div class="mt-5 rounded-xl border border-amber-100 bg-amber-50/80 px-4 py-3 text-sm text-amber-950">
                            You can view this order, but your store role cannot change its status.
                        </div>
                    @endif
                </article>

                <article class="{{ $card }} p-5 md:p-6">
                    <h3 class="font-[Poppins] text-lg font-semibold text-slate-900">Customer</h3>
                    <div class="mt-4 flex items-center gap-3">
                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-800 ring-2 ring-white shadow-sm">
                            {{ strtoupper($customerInitials) ?: 'C' }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">{{ $customerName }}</p>
                            <p class="truncate text-sm text-slate-600">{{ $order->customer_email ?? 'Email not recorded' }}</p>
                        </div>
                    </div>

                    <dl class="mt-6 space-y-5 text-sm">
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Phone</dt>
                            <dd class="mt-1.5 text-slate-800">{{ $order->customer_phone ?? $shipping?->phone ?? 'Not recorded' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Shipping address</dt>
                            <dd class="mt-1.5 text-slate-800">
                                @if ($shipping)
                                    {{ $shipping->address_line1 }}@if ($shipping->address_line2), {{ $shipping->address_line2 }}@endif<br>
                                    {{ $shipping->city }}, {{ $shipping->state }} {{ $shipping->postal_code }}<br>
                                    {{ $shipping->country }}
                                @else
                                    <span class="text-slate-500">Not recorded</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Billing address</dt>
                            <dd class="mt-1.5 text-slate-800">
                                @if ($billing)
                                    {{ $billing->address_line1 }}@if ($billing->address_line2), {{ $billing->address_line2 }}@endif<br>
                                    {{ $billing->city }}, {{ $billing->state }} {{ $billing->postal_code }}<br>
                                    {{ $billing->country }}
                                @elseif ($order->billing_same_as_shipping && $shipping)
                                    <span class="text-slate-600">Same as shipping address</span>
                                @else
                                    <span class="text-slate-500">Not recorded</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($order->customer_id)
                        <a href="{{ route('customersProfile', $order->customer_id) }}" class="mt-6 flex h-11 w-full items-center justify-center rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-800 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50/60 hover:text-indigo-900">
                            View customer
                        </a>
                    @endif
                </article>

                <article class="{{ $card }} p-5 md:p-6">
                    @php
                        $remainingFulfillmentQuantities = $remainingFulfillmentQuantities ?? [];
                        $remainingTotal = collect($remainingFulfillmentQuantities)->sum();
                    @endphp
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-[Poppins] text-lg font-semibold text-slate-900">Fulfillment</h3>
                            @if ($isOrderExternallyManaged)
                                <p class="mt-1 text-sm text-slate-600">Fulfillment managed externally. Updates appear here when the external storefront sends shipment snapshots.</p>
                            @else
                                <p class="mt-1 text-sm text-slate-600">Create shipments, add tracking, and keep fulfillment status accurate.</p>
                            @endif
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide {{ $isOrderExternallyManaged ? 'bg-sky-100 text-sky-800' : \App\Support\OrderLifecycle::fulfillmentStatusBadgeClass($order->fulfillment_status) }}">
                            {{ $isOrderExternallyManaged ? 'Externally managed' : \App\Support\OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status) }}
                        </span>
                    </div>

                    @if ($isOrderExternallyManaged)
                        <div class="mt-5 rounded-xl border border-sky-100 bg-sky-50/80 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-sky-800/80">Fulfillment managed externally</p>
                            @if ($hasExternalFulfillmentDetails)
                                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                    @if ($externalCarrierName)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Carrier</dt>
                                            <dd class="mt-1 font-semibold text-slate-900">{{ $externalCarrierName }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalFulfillmentStatus)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">External status</dt>
                                            <dd class="mt-1 font-semibold text-slate-900">{{ str($externalFulfillmentStatus)->replace('_', ' ')->title() }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalTrackingNumber)
                                        <div class="sm:col-span-2">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking number</dt>
                                            <dd class="mt-1 break-all font-semibold text-slate-900">{{ $externalTrackingNumber }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalTrackingUrl)
                                        <div class="sm:col-span-2">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking link</dt>
                                            <dd class="mt-1"><a href="{{ $externalTrackingUrl }}" target="_blank" rel="noopener" class="font-semibold text-indigo-700 hover:underline">Open tracking</a></dd>
                                        </div>
                                    @elseif (! $externalTrackingNumber)
                                        <div class="sm:col-span-2">
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking</dt>
                                            <dd class="mt-1 text-sm text-slate-600">No tracking update received yet.</dd>
                                        </div>
                                    @endif
                                    @if ($externalShippedAt)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Shipped at</dt>
                                            <dd class="mt-1 font-semibold text-slate-900">{{ $externalShippedAt }}</dd>
                                        </div>
                                    @endif
                                    @if ($externalDeliveredAt)
                                        <div>
                                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Delivered at</dt>
                                            <dd class="mt-1 font-semibold text-slate-900">{{ $externalDeliveredAt }}</dd>
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
                        <details class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
                            <summary class="cursor-pointer text-xs font-bold uppercase tracking-wide text-slate-500">Internal fulfillment quantities (advanced)</summary>
                            <div class="mt-3 space-y-2">
                                @foreach ($order->items as $item)
                                    @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate text-slate-700">{{ $item->product_name }}</span>
                                        <span class="font-semibold tabular-nums text-slate-900">{{ $remaining }} / {{ $item->quantity }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">These counts reflect dashboard-managed shipments only. External storefront fulfillment is shown above.</p>
                        </details>
                    @else
                        <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Remaining to fulfill</p>
                            <div class="mt-3 space-y-2">
                                @foreach ($order->items as $item)
                                    @php $remaining = (int) ($remainingFulfillmentQuantities[$item->id] ?? 0); @endphp
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate text-slate-700">{{ $item->product_name }}</span>
                                        <span class="font-semibold tabular-nums text-slate-900">{{ $remaining }} / {{ $item->quantity }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($canManageOrders && $remainingTotal > 0 && ! $isOrderExternallyManaged)
                        <form method="POST" action="{{ route('orders.shipments.store', $order) }}" class="mt-5 space-y-4 rounded-xl border border-slate-200 bg-white p-4">
                            @csrf
                            <p class="font-semibold text-slate-900">Create shipment</p>
                            @if ($routedOriginLocationId || $pickupLocationName)
                                <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs leading-relaxed text-indigo-900">
                                    Fulfillment origin selected by service area routing{{ $routedOriginLocationId ? ': '.($fulfillmentLocations->firstWhere('id', $routedOriginLocationId)?->name ?? data_get($fulfillmentRouting, 'origin_name', 'Selected location')) : '' }}.
                                    @if ($pickupLocationName)
                                        Pickup location selected: {{ $pickupLocationName }}.
                                    @endif
                                    You can override the ship-from location before creating the shipment.
                                </div>
                            @endif
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
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Carrier account</span>
                                    <select name="carrier_account_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <option value="">No carrier selected</option>
                                        @foreach ($carrierAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->display_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Delivery method</span>
                                    <select name="shipping_method_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <option value="">No delivery method selected</option>
                                        @foreach ($shippingMethods as $method)
                                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
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

                            <div class="grid gap-3">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking number</span>
                                    <input name="tracking_number" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tracking link</span>
                                    <input name="tracking_url" type="url" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="https://">
                                </label>
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
                            </div>
                            <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700">Create shipment</button>
                        </form>
                    @elseif ($canManageOrders && $remainingTotal === 0 && ! $isOrderExternallyManaged)
                        <div class="mt-5 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            All items on this order are fulfilled.
                        </div>
                    @elseif ($isOrderExternallyManaged && $canManageOrders && $remainingTotal > 0)
                        <details class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
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
                                            <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-indigo-700 hover:underline">Open tracking</a>
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
                                            <form method="POST" action="{{ route('shipments.mark-shipped', $shipment) }}">@csrf<button class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white">Mark shipped</button></form>
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
                            <div class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-10 text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-100" aria-hidden="true">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <p class="mx-auto mt-4 max-w-sm text-sm leading-relaxed text-slate-600">No shipments have been created yet.</p>
                            </div>
                        @endforelse
                    </div>
                </article>

                <article class="{{ $card }} p-5 md:p-6">
                    <h3 class="font-[Poppins] text-lg font-semibold text-slate-900">Order notes</h3>
                    <p class="mt-1 text-sm text-slate-600">Internal notes stay in your team—customers do not see them.</p>

                    @if ($canManageOrders)
                        <form action="{{ route('orders.notes.store', $order) }}" method="POST" class="mt-5 space-y-3">
                            @csrf
                            <label for="order-note-body" class="sr-only">Note for your team</label>
                            <textarea id="order-note-body" name="body" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20" placeholder="e.g. Customer asked for gift receipt"></textarea>
                            <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700">Add note</button>
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
                            @else
                                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center text-sm text-slate-500">No notes yet.</div>
                            @endif
                        @endforelse
                    </div>
                </article>

                <article class="{{ $card }} p-5 md:p-6">
                    <h3 class="font-[Poppins] text-lg font-semibold text-slate-900">Returns and refunds</h3>
                    <p class="mt-1 text-sm text-slate-600">RMAs and refund history will live here when returns are enabled for your store.</p>
                    <div class="mt-5 flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-10 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-100" aria-hidden="true">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <p class="mx-auto mt-4 max-w-sm text-sm leading-relaxed text-slate-600">No returns or refunds are recorded yet. Returns and refunds will be added in a later commerce phase.</p>
                    </div>
                </article>
            </aside>
        </div>
    </div>
@endsection
