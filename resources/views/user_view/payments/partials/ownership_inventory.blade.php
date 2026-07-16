<section class="payments-section">
    <header class="payments-section-head">
        <div>
            <h2 class="payments-section-title">Checkout and fulfillment mode</h2>
            <p class="payments-section-lede">Ownership &amp; Inventory Source — how checkout, payment, shipping, and inventory are owned for each channel mode.</p>
        </div>
        <a href="{{ route('developer-storefront.settings') }}" class="text-sm font-semibold text-[#24389c] hover:underline">Learn more about routing</a>
    </header>

    <div class="payments-ownership-grid">
        <div class="payments-ownership-stack">
            <article @class([
                'payments-ownership-card',
                'is-active' => $isExternalManaged ?? false,
            ])>
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h3 class="text-base font-bold text-[#0F172A]">External managed</h3>
                    @if($isExternalManaged ?? false)
                        <span class="payments-pill payments-pill-success">Active</span>
                    @endif
                </div>
                <p class="mb-4 text-sm text-[#64748B]">External storefront manages checkout, payment, shipping, and fulfillment. This dashboard records orders and activity.</p>
                <ul class="space-y-3">
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Checkout</span>
                        <span class="payments-routing-value">{{ ucfirst($externalChannelConfig['checkout_owner'] ?? 'external') }}</span>
                    </li>
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Payment</span>
                        <span class="payments-routing-value">{{ ucfirst($externalChannelConfig['payment_owner'] ?? 'external') }}</span>
                    </li>
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Shipping</span>
                        <span class="payments-routing-value">{{ ucfirst($externalChannelConfig['shipping_owner'] ?? 'external') }}</span>
                    </li>
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Fulfillment</span>
                        <span class="payments-routing-value">{{ ucfirst($externalChannelConfig['fulfillment_owner'] ?? 'external') }}</span>
                    </li>
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Inventory</span>
                        <span class="payments-routing-value">{{ ($usesPlatformInventoryForExternal ?? true) ? 'Platform managed' : 'External managed' }}</span>
                    </li>
                </ul>

                @if($canManagePayments ?? false)
                    <form method="POST" action="{{ route('settings.payments.external-inventory') }}" class="payments-inventory-panel space-y-3">
                        @csrf
                        <p class="text-sm font-semibold text-[#0F172A]">Inventory source for external orders</p>
                        <label class="flex items-start gap-2 text-sm text-[#334155]">
                            <input type="radio" name="inventory_owner" value="platform" @checked(($externalInventoryOwner ?? 'platform') === 'platform') class="mt-1">
                            <span>
                                <span class="font-semibold text-[#0F172A]">Use dashboard inventory</span>
                                <span class="mt-0.5 block text-xs text-[#64748B]">External orders reduce dashboard stock when they sync.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-[#334155]">
                            <input type="radio" name="inventory_owner" value="external" @checked(($externalInventoryOwner ?? 'platform') === 'external') class="mt-1">
                            <span>
                                <span class="font-semibold text-[#0F172A]">External storefront manages inventory</span>
                                <span class="mt-0.5 block text-xs text-[#64748B]">External orders are recorded here, but dashboard stock is not changed.</span>
                            </span>
                        </label>
                        <button type="submit" class="payments-btn payments-btn-primary">Save inventory source</button>
                    </form>
                @endif
            </article>

            <article @class([
                'payments-ownership-card',
                'is-active' => $isPlatformManaged ?? false,
            ])>
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h3 class="text-base font-bold text-[#0F172A]">Platform managed</h3>
                    @if($isPlatformManaged ?? false)
                        <span class="payments-pill payments-pill-success">Active</span>
                    @endif
                </div>
                <p class="mb-4 text-sm text-[#64748B]">Platform checkout manages checkout, payment, delivery, and fulfillment from this dashboard.</p>
                <ul class="space-y-3">
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Checkout</span>
                        <span class="payments-routing-value">{{ ucfirst($platformChannelConfig['checkout_owner'] ?? 'platform') }}</span>
                    </li>
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Payment</span>
                        <span class="payments-routing-value">{{ ucfirst($platformChannelConfig['payment_owner'] ?? 'platform') }}</span>
                    </li>
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Shipping</span>
                        <span class="payments-routing-value">{{ ucfirst($platformChannelConfig['shipping_owner'] ?? 'platform') }}</span>
                    </li>
                    <li class="payments-routing-row">
                        <span class="payments-routing-label">Fulfillment</span>
                        <span class="payments-routing-value">{{ ucfirst($platformChannelConfig['fulfillment_owner'] ?? 'platform') }}</span>
                    </li>
                </ul>
            </article>
        </div>

        <aside class="payments-hero-card">
            <div class="payments-hero-body">
                <h3 class="payments-hero-title">Master Distribution</h3>
                <p class="payments-hero-copy">
                    @if($isPlatformManaged ?? false)
                        Centralize your SKU management. When platform managed is active, this dashboard becomes the source of truth for stock levels across every channel.
                    @else
                        Keep external checkout flexible while this dashboard tracks orders, fulfillment, and inventory routing for your connected storefront.
                    @endif
                </p>
                <div class="payments-hero-actions">
                    <a href="{{ route('products') }}" class="payments-hero-btn payments-hero-btn-primary">Manage Inventory</a>
                    <a href="{{ route('analytics') }}" class="payments-hero-btn payments-hero-btn-secondary">View Analytics</a>
                </div>
            </div>
        </aside>
    </div>
</section>
