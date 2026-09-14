@php
    use App\Support\MoneyDisplay;

    $printStoreName = $selectedStore->name ?? config('app.name');
    $printStoreAddress = trim((string) ($selectedStore->address ?? ''));
    $printCouponCode = data_get($order->meta, 'coupon_snapshot.code');
    $printShipments = $order->shipments->filter(fn ($shipment) => filled($shipment->tracking_number) || filled($shipment->tracking_url));
@endphp

<style>
    .order-print-document { display: none; }

    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm 12mm 14mm;
        }

        html, body {
            height: auto !important;
            min-height: 0 !important;
            overflow: visible !important;
            background: #fff !important;
            color: #111 !important;
        }

        body.merchant-shell {
            display: block !important;
            flex-direction: column !important;
            overflow: visible !important;
        }

        #sidebar,
        #sidebarOverlay,
        .merchant-topbar,
        .ui-modal-shell,
        dialog,
        .toast,
        noscript {
            display: none !important;
        }

        main {
            display: block !important;
            width: 100% !important;
            overflow: visible !important;
            background: #fff !important;
        }

        .merchant-app {
            display: block !important;
            padding: 0 !important;
            overflow: visible !important;
            height: auto !important;
        }

        .merchant-app > *:not(.order-print-document) {
            display: none !important;
        }

        .order-print-document {
            display: block !important;
            width: 100%;
            max-width: none;
            color: #111827;
            font: 12px/1.45 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        .order-print-document * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .op-head {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #111827;
            margin-bottom: 18px;
        }

        .op-brand {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
        }

        .op-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
        }

        .op-brand h1 {
            margin: 0;
            font-size: 20px;
            letter-spacing: -.02em;
        }

        .op-brand p {
            margin: 4px 0 0;
            color: #4b5563;
            font-size: 11px;
            white-space: pre-line;
        }

        .op-meta {
            text-align: right;
        }

        .op-meta strong {
            display: block;
            font-size: 22px;
            letter-spacing: -.03em;
        }

        .op-meta span,
        .op-meta p {
            display: block;
            margin: 3px 0 0;
            color: #4b5563;
            font-size: 11px;
        }

        .op-status {
            display: inline-block;
            margin-top: 8px;
            padding: 2px 8px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .op-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px;
            margin-bottom: 20px;
        }

        .op-block h2 {
            margin: 0 0 6px;
            color: #6b7280;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .op-block p,
        .op-block strong {
            margin: 0;
        }

        .op-block strong {
            display: block;
            font-size: 13px;
        }

        .op-block p {
            margin-top: 3px;
            color: #374151;
            font-size: 12px;
        }

        .op-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .op-table th {
            padding: 8px 6px;
            border-bottom: 1px solid #111827;
            color: #6b7280;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .06em;
            text-align: left;
            text-transform: uppercase;
        }

        .op-table td {
            padding: 10px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .op-table th:nth-child(2),
        .op-table td:nth-child(2),
        .op-table th:nth-child(3),
        .op-table td:nth-child(3),
        .op-table th:nth-child(4),
        .op-table td:nth-child(4) {
            text-align: right;
            white-space: nowrap;
        }

        .op-item strong,
        .op-item span {
            display: block;
        }

        .op-item span {
            margin-top: 2px;
            color: #6b7280;
            font-size: 11px;
        }

        .op-totals {
            width: 280px;
            margin-left: auto;
        }

        .op-totals div {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 4px 0;
            color: #4b5563;
        }

        .op-totals .op-grand {
            margin-top: 6px;
            padding-top: 8px;
            border-top: 2px solid #111827;
            color: #111827;
            font-size: 14px;
            font-weight: 800;
        }

        .op-foot {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 22px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
        }

        .op-foot h2 {
            margin: 0 0 6px;
            color: #6b7280;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .op-foot p {
            margin: 0;
            color: #374151;
            font-size: 12px;
        }

        .op-thanks {
            margin-top: 28px;
            color: #6b7280;
            font-size: 11px;
            text-align: center;
        }
    }
</style>

<section class="order-print-document" aria-hidden="true">
    <header class="op-head">
        <div class="op-brand">
            @if ($selectedStore->logo)
                <img class="op-logo" src="{{ asset('storage/'.$selectedStore->logo) }}" alt="">
            @endif
            <div>
                <h1>{{ $printStoreName }}</h1>
                @if ($printStoreAddress !== '')
                    <p>{{ $printStoreAddress }}</p>
                @endif
            </div>
        </div>
        <div class="op-meta">
            <strong>Order #{{ strtoupper($order->order_number) }}</strong>
            <span>{{ $placedAt?->format('M j, Y g:i A') ?: 'Date not recorded' }}</span>
            <span>{{ $sourceLabelLong }}</span>
            <span class="op-status">{{ $paymentStatusLabel }} · {{ $fulfillmentStatusLabel }}</span>
        </div>
    </header>

    <div class="op-grid">
        <div class="op-block">
            <h2>Customer</h2>
            <strong>{{ $customerName }}</strong>
            @if ($order->customer_email)
                <p>{{ $order->customer_email }}</p>
            @endif
            @if ($order->customer_phone ?? $shipping?->phone)
                <p>{{ $order->customer_phone ?? $shipping?->phone }}</p>
            @endif
        </div>
        <div class="op-block">
            <h2>Ship to</h2>
            @if ($shipping)
                <strong>{{ $shipping->name ?: $customerName }}</strong>
                <p>
                    {{ $shipping->address_line1 }}@if ($shipping->address_line2), {{ $shipping->address_line2 }}@endif<br>
                    {{ collect([$shipping->city, $shipping->state, $shipping->postal_code])->filter()->implode(', ') }}<br>
                    {{ $shipping->country }}
                </p>
            @else
                <p>Not recorded</p>
            @endif
        </div>
        <div class="op-block">
            <h2>Bill to</h2>
            @if ($billing)
                <strong>{{ $billing->name ?: $customerName }}</strong>
                <p>
                    {{ $billing->address_line1 }}@if ($billing->address_line2), {{ $billing->address_line2 }}@endif<br>
                    {{ collect([$billing->city, $billing->state, $billing->postal_code])->filter()->implode(', ') }}<br>
                    {{ $billing->country }}
                </p>
            @elseif ($order->billing_same_as_shipping && $shipping)
                <p>Same as shipping address</p>
            @else
                <p>Not recorded</p>
            @endif
        </div>
    </div>

    <table class="op-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $item)
                <tr>
                    <td>
                        <div class="op-item">
                            <strong>{{ $item->product_name }}</strong>
                            @if ($item->variant_label)
                                <span>{{ $item->variant_label }}</span>
                            @endif
                            @if ($item->sku_snapshot)
                                <span>SKU {{ $item->sku_snapshot }}</span>
                            @endif
                        </div>
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ MoneyDisplay::formatWithCode($item->unit_price, $currency) }}</td>
                    <td>{{ MoneyDisplay::formatWithCode($item->total, $currency) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No line items on this order</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="op-totals">
        <div><span>Subtotal</span><span>{{ MoneyDisplay::formatWithCode($order->subtotal, $currency) }}</span></div>
        @if ((float) $order->discount > 0)
            <div>
                <span>Discount{!! filled($printCouponCode) ? ' ('.e($printCouponCode).')' : '' !!}</span>
                <span>{{ MoneyDisplay::formatDiscountWithCode($order->discount, $currency) }}</span>
            </div>
        @endif
        <div><span>Shipping</span><span>{{ MoneyDisplay::formatWithCode($order->shipping, $currency) }}</span></div>
        <div><span>Tax</span><span>{{ MoneyDisplay::formatWithCode($order->tax, $currency) }}</span></div>
        <div class="op-grand"><span>Total</span><span>{{ MoneyDisplay::formatWithCode($displayTotal, $currency) }}</span></div>
    </div>

    <div class="op-foot">
        <div>
            <h2>Payment</h2>
            <p>{{ $paymentStatusLabel }}{{ $paymentMethodLabel && $paymentMethodLabel !== 'Not recorded' ? ' · '.$paymentMethodLabel : '' }}</p>
            @if ($order->payment_reference)
                <p>Reference: {{ $order->payment_reference }}</p>
            @endif
        </div>
        <div>
            <h2>Delivery</h2>
            <p>{{ $fulfillmentStatusLabel }}</p>
            @if ($selectedDeliveryMethod)
                <p>{{ $selectedDeliveryMethod }}</p>
            @endif
            @if ($printShipments->isNotEmpty())
                @foreach ($printShipments as $shipment)
                    <p>
                        {{ $shipment->shipment_number }}
                        @if ($shipment->tracking_number)
                            · Tracking {{ $shipment->tracking_number }}
                        @endif
                    </p>
                @endforeach
            @endif
        </div>
    </div>

    <p class="op-thanks">Thank you for your order.</p>
</section>
