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
    @include('user_view.partials.order_workspace_styles')
@endpush

@section('content')
    @php
        use App\Support\MoneyDisplay;
        use App\Support\OrderLifecycle;
        use App\Models\Shipment;

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
            if ($status === $order->status || OrderLifecycle::canTransitionOrderStatus($order->status, $status)) {
                $availableOrderStatuses->push($status);
            }
        }
        $canManageOrders = auth()->user()?->canManageOrders($selectedStore) ?? false;
        $canRecordManualPayment = (auth()->user()?->hasStorePermission($selectedStore, 'orders.payments') ?? false)
            && app(\App\Services\ManualOrderPaymentService::class)->canRecord($order);
        $canFulfillOrders = auth()->user()?->hasStorePermission($selectedStore, 'fulfillment.fulfill') ?? false;
        $canUpdateTracking = auth()->user()?->hasStorePermission($selectedStore, 'fulfillment.tracking') ?? false;
        $canPurchaseLabels = auth()->user()?->hasStorePermission($selectedStore, 'fulfillment.labels.purchase') ?? false;
        $canCancelLabels = auth()->user()?->hasStorePermission($selectedStore, 'fulfillment.labels.cancel') ?? false;
        $canManageReturns = auth()->user()?->hasStorePermission($selectedStore, 'customers.returns') ?? false;
        $canManageExchanges = auth()->user()?->hasStorePermission($selectedStore, 'customers.exchanges') ?? false;
        $noteEvents = $order->events->where('event_type', OrderLifecycle::EVENT_ORDER_NOTE_ADDED);
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
        $orderStatusLabel = OrderLifecycle::orderStatusLabel($order->status);
        $paymentStatusLabel = OrderLifecycle::paymentStatusLabel($order->payment_status);
        $fulfillmentStatusLabel = $isOrderExternallyManaged
            ? 'Externally managed'
            : OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status);
        $paymentPaid = in_array($order->payment_status, [
            OrderLifecycle::PAYMENT_PAID,
            OrderLifecycle::PAYMENT_AUTHORIZED,
        ], true);
        $canIssueRefund = (auth()->user()?->hasStorePermission($selectedStore, 'customers.refunds') ?? false)
            && bccomp((string) $remainingRefundableAmount, '0', 4) > 0;
        $canCancelOrder = (auth()->user()?->hasStorePermission($selectedStore, 'orders.cancel') ?? false)
            && $order->status !== OrderLifecycle::ORDER_CANCELLED
            && $availableOrderStatuses->contains(OrderLifecycle::ORDER_CANCELLED);
        $isCancelled = $order->status === OrderLifecycle::ORDER_CANCELLED;
        $isFulfilled = $order->fulfillment_status === OrderLifecycle::FULFILLMENT_FULFILLED;
        $hasShipments = $order->shipments->isNotEmpty();
        $activeShipments = $order->shipments->reject(fn ($shipment) => in_array($shipment->status, [
            Shipment::STATUS_CANCELLED,
            Shipment::STATUS_FAILED,
        ], true));
        $anyShipped = $activeShipments->contains(fn ($shipment) => in_array($shipment->status, [
            Shipment::STATUS_SHIPPED,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_DELIVERED,
        ], true));
        $allDelivered = $activeShipments->isNotEmpty()
            && $activeShipments->every(fn ($shipment) => $shipment->status === Shipment::STATUS_DELIVERED);
        $placedAt = $order->placed_at ?? $order->created_at;
        $paymentEvent = $order->events->first(fn ($event) => in_array($event->event_type, [
            OrderLifecycle::EVENT_PAYMENT_SUCCEEDED,
            OrderLifecycle::EVENT_PAYMENT_STATUS_RECORDED,
            OrderLifecycle::EVENT_PAYMENT_STATUS_CHANGED,
        ], true));
        $firstShippedAt = $order->shipments->pluck('shipped_at')->filter()->sort()->first();
        $firstDeliveredAt = $order->shipments->pluck('delivered_at')->filter()->sort()->first();
        $life = [
            'placed' => 'complete',
            'payment' => '',
            'ready' => '',
            'shipped' => '',
            'delivered' => '',
        ];
        if ($paymentPaid) {
            $life['payment'] = 'complete';
        } elseif (! $isCancelled) {
            $life['payment'] = 'current';
        }
        if ($life['payment'] === 'complete' && ! $isCancelled) {
            if ($isFulfilled || $remainingTotal === 0) {
                $life['ready'] = 'complete';
            } else {
                $life['ready'] = 'current';
            }
        }
        if ($life['ready'] === 'complete' && ! $isCancelled) {
            if ($allDelivered) {
                $life['shipped'] = 'complete';
                $life['delivered'] = 'complete';
            } elseif ($anyShipped) {
                $life['shipped'] = 'complete';
                $life['delivered'] = 'current';
            } elseif ($hasShipments || $isFulfilled) {
                $life['shipped'] = 'current';
            }
        }
        $qtyTotal = (int) ($order->total_quantity ?: $order->items->sum('quantity'));
        $itemCountLabel = $qtyTotal === 1 ? '1 item' : $qtyTotal.' items';
        $originName = $routedOriginLocationId
            ? ($fulfillmentLocations->firstWhere('id', $routedOriginLocationId)?->name
                ?? data_get($fulfillmentRouting, 'origin_name', 'the selected inventory location'))
            : 'the selected inventory location';
        $destinationLabel = collect([$shipping?->city, $shipping?->state])->filter()->implode(', ');
        if ($isOrderExternallyManaged) {
            $readyStripTitle = 'Fulfillment is managed externally';
            $readyStripHint = 'Shipment updates from the connected storefront appear in the fulfillment tab.';
            $readyStripIcon = '▣';
        } elseif ($remainingTotal > 0) {
            $readyStripTitle = $remainingTotal === 1 ? '1 item ready to ship' : $remainingTotal.' items ready to ship';
            $readyStripHint = 'All remaining items can be fulfilled from this order.';
            $readyStripIcon = '▣';
        } else {
            $readyStripTitle = 'Fulfillment complete';
            $readyStripHint = 'All items on this order already have shipments.';
            $readyStripIcon = '✓';
        }
        $packageStatusLabel = $isFulfilled || ($remainingTotal === 0 && $hasShipments)
            ? 'Fulfilled'
            : ($hasShipments ? 'Shipment created' : 'Not packed');
        $paymentBadgeClass = $paymentPaid ? 'badge-green' : (
            $order->payment_status === OrderLifecycle::PAYMENT_FAILED ? 'badge-red' : 'badge-amber'
        );
        $fulfillmentBadgeClass = $isOrderExternallyManaged ? 'badge-blue' : match ($order->fulfillment_status) {
            OrderLifecycle::FULFILLMENT_FULFILLED => 'badge-green',
            OrderLifecycle::FULFILLMENT_PARTIAL => 'badge-amber',
            OrderLifecycle::FULFILLMENT_RETURNED => 'badge-gray',
            default => 'badge-amber',
        };
        $paymentMethodLabel = $order->payment_method
            ? str($order->payment_method)->replace('_', ' ')->title()
            : ($gatewayLabel ?: 'Not recorded');
        $activityCategory = static function (?string $type): string {
            $type = (string) $type;
            if (str_starts_with($type, 'payment.') || $type === OrderLifecycle::EVENT_CHECKOUT_COMPLETED) {
                return 'payment';
            }
            if (str_starts_with($type, 'shipment.')
                || str_starts_with($type, 'fulfillment.')
                || str_starts_with($type, 'external_')) {
                return 'fulfillment';
            }
            if (str_starts_with($type, 'inventory.')) {
                return 'inventory';
            }
            if (str_starts_with($type, 'return.') || str_starts_with($type, 'refund.') || str_starts_with($type, 'exchange.')) {
                return 'after-sales';
            }
            if ($type === OrderLifecycle::EVENT_ORDER_NOTE_ADDED) {
                return 'note';
            }

            return 'order';
        };
        $recentEvents = $order->events->sortByDesc('id')->take(4)->values();
        $timelineEvents = $order->events->sortByDesc('id')->values();
        $initialTab = 'overview';
        $initialService = 'return';
        $openStatusDialog = $errors->has('status');
        $errorKeys = collect($errors->keys());
        if ($errorKeys->contains(fn ($key) => str_contains((string) $key, 'return')
            || str_contains((string) $key, 'refund')
            || str_contains((string) $key, 'exchange')
            || in_array($key, ['customer_notes', 'merchant_notes', 'amount', 'replacement_variant_id', 'return_reason_id'], true))
            || old('customer_notes')
            || old('replacement_variant_id')
            || old('amount')
        ) {
            $initialTab = 'after-sales';
            if (old('amount') || $errorKeys->contains(fn ($key) => str_contains((string) $key, 'refund'))) {
                $initialService = 'refund';
            } elseif (old('replacement_variant_id') || $errorKeys->contains(fn ($key) => str_contains((string) $key, 'exchange'))) {
                $initialService = 'exchange';
            }
        } elseif ($errorKeys->contains(fn ($key) => str_contains((string) $key, 'shipment')
            || in_array($key, ['origin_location_id', 'tracking_number', 'carrier_account_id', 'shipping_method_id', 'package_source', 'packed_weight'], true))
            || old('origin_location_id')
            || old('package_source')
            || old('packed_weight')
        ) {
            $initialTab = 'fulfillment';
        }
        $shipmentCount = $order->shipments->count();
    @endphp

    <div class="order-page order-workspace w-full pb-10"
         data-order-workspace
         data-initial-tab="{{ $initialTab }}"
         data-initial-service="{{ $initialService }}"
         data-open-status="{{ $openStatusDialog ? '1' : '0' }}">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="page-head">
            <div>
                <div class="breadcrumb">
                    <a href="{{ route('orders') }}">Orders</a>
                    / #{{ strtoupper($order->order_number) }}
                </div>
                <div class="order-title-row">
                    <h2>Order #{{ strtoupper($order->order_number) }}</h2>
                    <span class="badge {{ $paymentBadgeClass }}">{{ $paymentStatusLabel }}</span>
                    <span class="badge {{ $fulfillmentBadgeClass }}">{{ $fulfillmentStatusLabel }}</span>
                </div>
                <p class="order-meta">
                    {{ $placedAt?->format('M j, Y \a\t g:i A') ?: 'Date not recorded' }}
                    · {{ $sourceLabelLong }}
                </p>
            </div>
            <div class="head-actions">
                <button type="button" class="btn" data-action="print">Print order</button>
                @if ($canManageOrders || $canIssueRefund || ($canManageReturns && $canRecordReturn) || ($canManageExchanges && $canCreateExchange) || $canCancelOrder)
                    <button type="button" class="btn" id="moreButton" aria-expanded="false" aria-haspopup="true">•••</button>
                    <div class="menu" id="moreMenu" hidden>
                    @if ($canManageOrders)
                        <button type="button" data-action="status">Update order status</button>
                    @endif
                    @if ($canIssueRefund)
                        <button type="button" data-action="refund">Issue refund</button>
                    @endif
                    @if ($canManageReturns && $canRecordReturn)
                        <button type="button" data-action="return">Record return</button>
                    @endif
                    @if ($canManageExchanges && $canCreateExchange)
                        <button type="button" data-action="exchange">Create exchange</button>
                    @endif
                    @if ($canCancelOrder)
                        <button type="button" class="danger" data-action="cancel-order">Cancel order</button>
                    @endif
                    </div>
                @endif
                @if ($canFulfillOrders && $remainingTotal > 0 && ! $isOrderExternallyManaged)
                    <button type="button" class="btn btn-primary" data-action="fulfill">Fulfill order</button>
                @endif
            </div>
        </section>

        <section class="lifecycle" aria-label="Order lifecycle">
            <div class="life-step {{ $life['placed'] }}">
                <span class="life-dot">{{ $life['placed'] === 'complete' ? '✓' : '' }}</span>
                <strong>Order placed</strong>
                @if ($placedAt)
                    <small>{{ $placedAt->format('M j, g:i A') }}</small>
                @endif
            </div>
            <div class="life-step {{ $life['payment'] }}">
                <span class="life-dot">{{ $life['payment'] === 'complete' ? '✓' : '' }}</span>
                <strong>Payment confirmed</strong>
                @if ($paymentPaid && $paymentEvent?->created_at)
                    <small>{{ $paymentEvent->created_at->format('M j, g:i A') }}</small>
                @endif
            </div>
            <div class="life-step {{ $life['ready'] }}">
                <span class="life-dot">{{ $life['ready'] === 'complete' ? '✓' : '' }}</span>
                <strong>Ready to fulfill</strong>
            </div>
            <div class="life-step {{ $life['shipped'] }}">
                <span class="life-dot">{{ $life['shipped'] === 'complete' ? '✓' : '' }}</span>
                <strong>Shipped</strong>
                @if ($firstShippedAt)
                    <small>{{ $firstShippedAt->format('M j, g:i A') }}</small>
                @endif
            </div>
            <div class="life-step {{ $life['delivered'] }}">
                <span class="life-dot">{{ $life['delivered'] === 'complete' ? '✓' : '' }}</span>
                <strong>Delivered</strong>
                @if ($firstDeliveredAt)
                    <small>{{ $firstDeliveredAt->format('M j, g:i A') }}</small>
                @endif
            </div>
        </section>

        <section class="workspace">
            <nav class="tabs" aria-label="Order sections">
                <button type="button" class="tab {{ $initialTab === 'overview' ? 'active' : '' }}" data-tab="overview">Overview</button>
                <button type="button" class="tab {{ $initialTab === 'fulfillment' ? 'active' : '' }}" data-tab="fulfillment">
                    Fulfillment
                    @if ($shipmentCount > 0)
                        <span class="tab-count">{{ $shipmentCount }}</span>
                    @endif
                </button>
                <button type="button" class="tab {{ $initialTab === 'after-sales' ? 'active' : '' }}" data-tab="after-sales">Returns & refunds</button>
                <button type="button" class="tab {{ $initialTab === 'activity' ? 'active' : '' }}" data-tab="activity">Activity</button>
            </nav>

            {{-- This order will ship from the selected inventory location. --}}
            @include('user_view.partials.order_overview_panel')

            <section class="panel" id="panel-fulfillment" @if ($initialTab !== 'fulfillment') hidden @endif>
                @include('user_view.partials.order_fulfillment_legacy')
            </section>

            <section class="panel" id="panel-after-sales" @if ($initialTab !== 'after-sales') hidden @endif>
                <div class="summary-cards">
                    <div class="summary-card">
                        <span>Return records</span>
                        <strong>{{ $order->returns->count() }}</strong>
                    </div>
                    <div class="summary-card">
                        <span>Refund records</span>
                        <strong>{{ $order->refunds->count() }}</strong>
                    </div>
                    <div class="summary-card">
                        <span>Exchanges</span>
                        <strong>{{ $order->exchanges->count() }}</strong>
                    </div>
                </div>
                @include('user_view.partials.order_after_sales_legacy')
            </section>

            <section class="panel" id="panel-activity" @if ($initialTab !== 'activity') hidden @endif>
                <article class="card">
                    <header class="card-head">
                        <div>
                            <h3>Order activity</h3>
                            <p>Complete payment, fulfillment, inventory, and after-sales history.</p>
                        </div>
                        <select id="activityFilter" style="width:180px">
                            <option value="all">All activity</option>
                            <option value="order">Order</option>
                            <option value="payment">Payment</option>
                            <option value="fulfillment">Fulfillment</option>
                            <option value="inventory">Inventory</option>
                            <option value="after-sales">After-sales</option>
                            <option value="note">Notes</option>
                        </select>
                    </header>
                    <div class="card-body">
                        @if ($timelineEvents->isEmpty())
                            <div class="empty">No order activity has been recorded yet. Future status changes and important actions will appear here.</div>
                        @else
                            <ul class="timeline">
                                @foreach ($timelineEvents as $event)
                                    @include('user_view.partials.order_activity_event', [
                                        'event' => $event,
                                        'activityType' => $activityCategory($event->event_type),
                                    ])
                                @endforeach
                            </ul>
                            <div id="activityFilterEmpty" class="empty" hidden>No matching activity.</div>
                        @endif
                    </div>
                </article>
            </section>
        </section>

        @if ($canManageOrders)
        <dialog id="statusDialog">
            <div id="status-manager" class="scroll-mt-24">
                <header class="dialog-head">
                    <h3>Update order status</h3>
                    <button class="close-button" type="button" data-close-dialog aria-label="Close">×</button>
                </header>
                <div class="dialog-body">
                    <div class="mb-4">
                        <p class="text-sm font-semibold text-slate-700">Current state</p>
                        <p class="mt-1 text-sm">{{ $orderStatusLabel }}</p>
                    </div>
                    @if ($availableOrderStatuses->count() > 1)
                        <form action="{{ route('orders.updateStatus', $order->id) }}" method="POST" class="ow-form space-y-4">
                            @csrf
                            @method('PATCH')
                            <label class="span-2">
                                Order status
                                <select name="status" id="status">
                                    @foreach ($availableOrderStatuses as $status)
                                        <option value="{{ $status }}" @selected($order->status === $status)>
                                            {{ OrderLifecycle::orderStatusLabel($status) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="dialog-actions" style="margin: 16px -20px -20px; padding: 14px 20px;">
                                <button class="btn" type="button" data-close-dialog>Cancel</button>
                                <button class="btn btn-primary" type="submit">Save status</button>
                            </div>
                        </form>
                    @else
                        <p class="text-sm text-slate-600">No further status changes are available for this order.</p>
                    @endif
                </div>
            </div>
        </dialog>
        @endif

        @if ($canCancelOrder)
            <form id="cancel-order-form" action="{{ route('orders.updateStatus', $order->id) }}" method="POST" hidden>
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ OrderLifecycle::ORDER_CANCELLED }}">
            </form>
            <dialog id="confirmDialog">
                <div class="dialog-head">
                    <h3>Cancel this order?</h3>
                    <button class="close-button" type="button" data-close-dialog aria-label="Close">×</button>
                </div>
                <div class="dialog-body">
                    <p>Cancelling the order does not automatically refund the payment.</p>
                </div>
                <div class="dialog-actions">
                    <button class="btn" type="button" data-close-dialog>Keep order</button>
                    <button class="btn btn-danger" type="button" id="confirmCancelOrder">Cancel order</button>
                </div>
            </dialog>
        @endif

        <div class="toast" id="orderWorkspaceToast" role="status" aria-live="polite"></div>
    </div>

    @include('user_view.partials.order_print_document')
@endsection

@push('scripts')
    @include('user_view.partials.order_workspace_scripts')
@endpush
