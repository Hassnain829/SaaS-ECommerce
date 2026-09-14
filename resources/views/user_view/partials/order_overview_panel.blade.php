@php
    use App\Support\MoneyDisplay;
@endphp
<section class="panel" id="panel-overview" @if ($initialTab !== 'overview') hidden @endif>
    <div class="overview-grid">
        <div class="stack">
            <article class="card">
                <header class="card-head">
                    <div>
                        <h3>Order items</h3>
                        <p>{{ $itemCountLabel }} from {{ strtolower($sourceLabelLong) }}</p>
                    </div>
                </header>
                <div class="card-body">
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($order->items as $item)
                                @php
                                    $imagePath = $item->product_image_snapshot ?: $item->product?->images?->first()?->image_path;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="product-cell">
                                            <div class="product-thumb">
                                                @if ($imagePath)
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($imagePath) }}" alt="{{ $item->product_name }}">
                                                @else
                                                    {{ strtoupper(\Illuminate\Support\Str::substr($item->product_name, 0, 2)) }}
                                                @endif
                                            </div>
                                            <div class="product-name">
                                                <strong>{{ $item->product_name }}</strong>
                                                <span>{{ $item->variant_label ?: 'Default option' }}</span>
                                                @if ($item->sku_snapshot)
                                                    <span>SKU {{ $item->sku_snapshot }}</span>
                                                @endif
                                            </div>
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

                    <div class="totals">
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span>{{ MoneyDisplay::formatWithCode($order->subtotal, $currency) }}</span>
                        </div>
                        @if ((float) $order->discount > 0)
                            <div class="total-row">
                                <span>
                                    Discount
                                    @php $orderCouponCode = data_get($order->meta, 'coupon_snapshot.code'); @endphp
                                    @if (filled($orderCouponCode))
                                        ({{ $orderCouponCode }})
                                    @endif
                                </span>
                                <span>{{ MoneyDisplay::formatDiscountWithCode($order->discount, $currency) }}</span>
                            </div>
                        @endif
                        <div class="total-row">
                            <span>Shipping</span>
                            <span>{{ MoneyDisplay::formatWithCode($order->shipping, $currency) }}</span>
                        </div>
                        <div class="total-row">
                            <span>
                                Tax
                                @isset($taxDisplay)
                                    @if ($taxDisplay['compact_summary'] ?? null)
                                        · {{ $taxDisplay['compact_summary'] }}
                                    @endif
                                @endisset
                            </span>
                            <span>{{ MoneyDisplay::formatWithCode($order->tax, $currency) }}</span>
                        </div>
                        <div class="total-row grand">
                            <span>Total</span>
                            <span>{{ MoneyDisplay::formatWithCode($displayTotal, $currency) }}</span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="card">
                <header class="card-head no-border">
                    <div>
                        <h3>Fulfillment</h3>
                    </div>
                    <span class="badge {{ $fulfillmentBadgeClass }}">{{ $fulfillmentStatusLabel }}</span>
                </header>
                <div class="card-body" style="padding-top:0">
                    <div class="ready-strip">
                        <span style="font-size:20px" aria-hidden="true">{{ $readyStripIcon }}</span>
                        <div>
                            <strong>{{ $readyStripTitle }}</strong>
                            <span>{{ $readyStripHint }}</span>
                        </div>
                    </div>

                    <div class="fulfillment-facts">
                        <div class="fact">
                            <span aria-hidden="true">⌖</span>
                            <div>
                                <strong>Fulfillment route</strong>
                                <span>This order will ship from {{ $originName }}{{ $destinationLabel ? ' → '.$destinationLabel : '' }}.</span>
                            </div>
                        </div>
                        <div class="fact">
                            <span aria-hidden="true">◇</span>
                            <div>
                                <strong>Delivery method</strong>
                                <span>
                                    {{ $selectedDeliveryMethod ?: 'Not selected' }}
                                    @if ($selectedDeliverySpeed || ($estimatedMinDays !== null && $estimatedMaxDays !== null))
                                        · {{ collect([$selectedDeliverySpeed, $estimatedMinDays !== null && $estimatedMaxDays !== null ? $estimatedMinDays.'–'.$estimatedMaxDays.' days' : null])->filter()->implode(' · ') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="fact">
                            <span aria-hidden="true">□</span>
                            <div>
                                <strong>Package status</strong>
                                <span>{{ $packageStatusLabel }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="fulfillment-actions">
                        @if ($canManageOrders && $remainingTotal > 0 && ! $isOrderExternallyManaged)
                            <button type="button" class="btn btn-primary" data-action="fulfill">Create shipment</button>
                        @else
                            <span></span>
                        @endif
                        <button type="button" class="btn btn-link" data-action="fulfillment-details">View fulfillment details →</button>
                    </div>
                </div>
            </article>

            <article class="card">
                <header class="card-head">
                    <div>
                        <h3>Recent activity</h3>
                        <p>Latest updates for this order</p>
                    </div>
                    <button type="button" class="btn btn-link" data-tab="activity">View all →</button>
                </header>
                <div class="card-body">
                    @if ($recentEvents->isEmpty())
                        <div class="empty">No order activity has been recorded yet. Future status changes and important actions will appear here.</div>
                    @else
                        <ul class="timeline">
                            @foreach ($recentEvents as $event)
                                @include('user_view.partials.order_activity_event', [
                                    'event' => $event,
                                    'activityType' => $activityCategory($event->event_type),
                                ])
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>
        </div>

        <aside class="stack">
            <article class="card">
                <header class="card-head">
                    <h3>Customer & delivery</h3>
                </header>
                <div class="card-body">
                    <div class="customer-row">
                        <div class="customer-avatar">{{ strtoupper($customerInitials) ?: 'C' }}</div>
                        <div>
                            <strong>{{ $customerName }}</strong>
                            <span>{{ $order->customer_email ?? 'Email not recorded' }}</span>
                            <span>{{ $order->customer_phone ?? $shipping?->phone ?? 'Phone not recorded' }}</span>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="address">
                        <span aria-hidden="true">⌖</span>
                        <div>
                            <strong>Shipping address</strong>
                            @if ($shipping)
                                <p>
                                    {{ $shipping->address_line1 }}@if ($shipping->address_line2)<br>{{ $shipping->address_line2 }}@endif<br>
                                    {{ collect([$shipping->city, $shipping->state, $shipping->postal_code])->filter()->implode(' ') }}<br>
                                    {{ $shipping->country }}
                                </p>
                            @else
                                <p>Not recorded</p>
                            @endif
                        </div>
                    </div>

                    @if ($billing || $order->billing_same_as_shipping)
                        <div class="address" style="margin-top:12px">
                            <span aria-hidden="true">▣</span>
                            <div>
                                <strong>Billing address</strong>
                                @if ($billing)
                                    <p>
                                        {{ $billing->address_line1 }}@if ($billing->address_line2)<br>{{ $billing->address_line2 }}@endif<br>
                                        {{ collect([$billing->city, $billing->state, $billing->postal_code])->filter()->implode(' ') }}<br>
                                        {{ $billing->country }}
                                    </p>
                                @elseif ($order->billing_same_as_shipping)
                                    <p>Same as shipping address</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="button-pair">
                        @if ($order->customer_email)
                            <a class="btn" href="mailto:{{ $order->customer_email }}">Contact</a>
                        @else
                            <button type="button" class="btn" disabled>Contact</button>
                        @endif
                        @if ($order->customer_id)
                            <a class="btn" href="{{ route('customersProfile', $order->customer_id) }}">View customer</a>
                        @else
                            <button type="button" class="btn" disabled>View customer</button>
                        @endif
                    </div>
                </div>
            </article>

            <article class="card">
                <header class="card-head">
                    <h3>Payment</h3>
                    <span class="badge {{ $paymentBadgeClass }}">{{ $paymentStatusLabel }}</span>
                </header>
                <div class="card-body">
                    <dl class="detail-list">
                        <div class="detail-row">
                            <dt>Payment method</dt>
                            <dd>{{ $paymentMethodLabel }}</dd>
                        </div>
                        @if ($order->payment_reference)
                            <div class="detail-row">
                                <dt>Reference</dt>
                                <dd>{{ $order->payment_reference }}</dd>
                            </div>
                        @endif
                        <div class="detail-row total">
                            <dt>Total</dt>
                            <dd>{{ MoneyDisplay::formatWithCode($displayTotal, $currency) }}</dd>
                        </div>
                    </dl>

                    @if ($canRecordManualPayment)
                        <form
                            id="record-manual-payment-form"
                            action="{{ route('orders.payments.record', $order) }}"
                            method="POST"
                            class="relative z-20 mt-4 rounded-xl border border-amber-200 bg-amber-50/80 p-4"
                            data-turbo="false"
                        >
                            @csrf
                            <p class="text-sm font-semibold text-amber-950">Payment is still outstanding</p>
                            <p class="mt-1 text-xs leading-5 text-amber-900">
                                Manual orders are created unpaid. Record cash, bank, or in-person card payment here after you collect it. This does not charge a card.
                            </p>
                            @error('payment')
                                <p class="mt-2 text-xs text-[#B91C1C]">{{ $message }}</p>
                            @enderror
                            <label class="mt-3 block">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-amber-900">How was it paid?</span>
                                <select name="payment_method" form="record-manual-payment-form" class="mt-1 h-10 w-full rounded-lg border border-amber-200 bg-white px-3 text-sm" required>
                                    @foreach (\App\Services\ManualOrderPaymentService::methods() as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method')
                                    <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                                @enderror
                            </label>
                            <label class="mt-3 block">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-amber-900">Reference <span class="font-normal normal-case tracking-normal">(optional)</span></span>
                                <input name="payment_reference" form="record-manual-payment-form" value="{{ old('payment_reference') }}" maxlength="120" class="mt-1 h-10 w-full rounded-lg border border-amber-200 bg-white px-3 text-sm" placeholder="Receipt number or note">
                                @error('payment_reference')
                                    <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                                @enderror
                            </label>
                            <button
                                type="submit"
                                form="record-manual-payment-form"
                                class="relative z-20 mt-4 inline-flex h-10 w-full cursor-pointer items-center justify-center rounded-lg bg-brand px-4 text-sm font-semibold text-white pointer-events-auto"
                            >
                                Record payment
                            </button>
                        </form>
                    @elseif ($order->order_source === 'manual' && $order->payment_status === \App\Support\OrderLifecycle::PAYMENT_PENDING && ! $canManageOrders)
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-xs text-amber-900">Payment is still outstanding. Ask a store owner or manager to record it.</p>
                    @endif

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
                        <details>
                            <summary>View payment and source details</summary>
                            <div class="technical-details">
                                <div>Source: <code>{{ $sourceLabelLong }}</code></div>
                                <div>Channel: <code>{{ $order->channel ? str($order->channel)->replace('_', ' ')->title() : 'Dashboard' }}</code></div>
                                @if ($order->external_order_number)
                                    <div>External order: <code>{{ $order->external_order_number }}</code></div>
                                @endif
                                @if ($order->external_checkout_reference)
                                    <div>Checkout reference: <code>{{ $order->external_checkout_reference }}</code></div>
                                @endif
                                @if ($platformCheckoutNumber)
                                    <div>Checkout: <code>{{ $platformCheckoutNumber }}</code></div>
                                @endif
                                @if ($paymentConnectionLabel)
                                    <div>Stripe connection: <code>{{ $paymentConnectionLabel }}</code></div>
                                @endif
                                @if ($gatewayLabel)
                                    <div>Gateway: <code>{{ $gatewayLabel }}</code></div>
                                @endif
                                @if ($order->payment_method)
                                    <div>Method: <code>{{ str($order->payment_method)->replace('_', ' ')->title() }}</code></div>
                                @endif
                                @if ($order->payment_reference)
                                    <div>Payment reference: <code>{{ $order->payment_reference }}</code></div>
                                @endif
                                @if ($connectedAccountId)
                                    <div>Connected account: <code>{{ $connectedAccountId }}</code></div>
                                @endif
                                @if ($selectedDeliveryMethod)
                                    <div>Delivery method: <code>{{ $selectedDeliveryMethod }}</code></div>
                                @endif
                            </div>
                        </details>
                    @endif
                </div>
            </article>

            <article class="card">
                <header class="card-head">
                    <div>
                        <h3>Internal notes</h3>
                        <p>Visible only to your team</p>
                    </div>
                </header>
                <div class="card-body">
                    @if ($canManageOrders)
                        <form action="{{ route('orders.notes.store', $order) }}" method="POST" class="ow-form">
                            @csrf
                            <label for="order-note-body" class="sr-only">Note for your team</label>
                            <textarea id="order-note-body" name="body" placeholder="Add a note for the team...">{{ old('body') }}</textarea>
                            <div style="display:flex;justify-content:flex-end;margin-top:9px">
                                <button type="submit" class="btn">Add Note</button>
                            </div>
                        </form>
                    @endif

                    <div style="margin-top:12px">
                        @forelse ($noteEvents as $note)
                            <div style="padding:10px 0;border-bottom:1px solid var(--line)">
                                <strong>{{ $note->description }}</strong>
                                <div style="color:var(--muted);font-size:11px;margin-top:3px">
                                    {{ $note->actor?->name ?? 'System' }} · {{ $note->created_at?->format('M j, Y g:i A') }}
                                </div>
                            </div>
                        @empty
                            @if ($order->notes)
                                <p class="whitespace-pre-line text-sm leading-relaxed">{{ $order->notes }}</p>
                            @elseif (! $canManageOrders)
                                <div class="empty">No notes yet.</div>
                            @endif
                        @endforelse
                    </div>
                </div>
            </article>

            <div class="after-sales-banner">
                <div>
                    <strong>Need to refund, return, or exchange an item?</strong>
                    <span>Open the dedicated after-sales tools.</span>
                </div>
                <button type="button" class="btn" data-action="after-sales">Open tools</button>
            </div>
        </aside>
    </div>
</section>
