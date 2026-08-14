@php
    use App\Support\CheckoutMode;
@endphp

<div class="pay-grid-main">
    <article class="pay-card pay-card-accent">
        <div class="pay-card-head">
            <div class="pay-card-identity">
                <span class="pay-icon-tile pay-icon-tile-mint" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </span>
                <div>
                    <h3 class="pay-card-title">External checkout</h3>
                    <p class="pay-card-copy">Customers pay on your existing website. Completed orders sync into this dashboard.</p>
                </div>
            </div>
            @if($isExternalMode)
                <span class="pay-badge pay-badge-active">Active mode</span>
            @endif
        </div>

        <div class="pay-inline-bar">
            <div class="pay-inline-bar-copy">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                <span>Orders sync into this dashboard after checkout.</span>
            </div>
            <a href="{{ route('developer-storefront.settings') }}" class="pay-btn pay-btn-outline">Website</a>
        </div>

        @if($canManagePayments && ! $isExternalMode)
            <form method="POST" action="{{ route('settings.payments.mode') }}" class="mt-4" data-turbo="false">
                @csrf
                <input type="hidden" name="checkout_mode" value="{{ CheckoutMode::EXTERNAL }}">
                <button type="submit" class="pay-btn pay-btn-primary">Switch to external checkout</button>
            </form>
        @endif
    </article>

    <aside class="pay-trust-card">
        <h3 class="pay-trust-title">Built for store security</h3>
        <p class="pay-trust-copy">Card details stay with your website and payment provider. This dashboard receives order results — not raw card numbers.</p>
        <ul class="pay-trust-list">
            <li>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Order sync over HTTPS
            </li>
            <li>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Store-scoped access only
            </li>
        </ul>
    </aside>
</div>

<section class="pay-matrix">
    <header class="pay-matrix-head">
        <div>
            <h4 class="pay-matrix-title">What each system handles</h4>
            <p class="pay-matrix-lede">For external checkout, your website runs payment. This dashboard still helps with inventory and order ops.</p>
        </div>
    </header>
    <div class="pay-matrix-grid">
        <div class="pay-matrix-cell">
            <span class="pay-matrix-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/></svg>
            </span>
            <p class="pay-matrix-label">Checkout</p>
            <p class="pay-matrix-value">{{ $ownerLabel($externalConfig['checkout_owner'] ?? 'external') }}</p>
        </div>
        <div class="pay-matrix-cell">
            <span class="pay-matrix-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
            </span>
            <p class="pay-matrix-label">Payment</p>
            <p class="pay-matrix-value">{{ $ownerLabel($externalConfig['payment_owner'] ?? 'external') }}</p>
        </div>
        <div class="pay-matrix-cell">
            <span class="pay-matrix-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/></svg>
            </span>
            <p class="pay-matrix-label">Shipping</p>
            <p class="pay-matrix-value">{{ $ownerLabel($externalConfig['shipping_owner'] ?? 'external') }}</p>
        </div>
        <div class="pay-matrix-cell">
            <span class="pay-matrix-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>
            </span>
            <p class="pay-matrix-label">Inventory</p>
            <p class="pay-matrix-value">{{ $inventoryIsDashboard ? 'Dashboard' : 'Your website' }}</p>
        </div>
        <div class="pay-matrix-cell">
            <span class="pay-matrix-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
            </span>
            <p class="pay-matrix-label">Order sync</p>
            <p class="pay-matrix-value">Ready</p>
        </div>
    </div>

    @if($canManagePayments)
        <div
            class="pay-inventory-panel"
            x-data="{ editing: {{ ($errors->has('inventory_owner') || old('_inventory_editing')) ? 'true' : 'false' }} }"
        >
            <div class="pay-inventory-head">
                <div>
                    <p class="pay-inventory-title">Inventory for external orders</p>
                    <p class="pay-inventory-copy" x-show="!editing" x-cloak>
                        Current:
                        <strong>{{ $inventoryIsDashboard ? 'Reduce dashboard stock when orders sync' : 'Website manages stock — dashboard stock stays unchanged' }}</strong>
                    </p>
                </div>
                <button type="button" class="pay-btn pay-btn-outline" x-show="!editing" @click="editing = true">Edit</button>
            </div>

            <form
                method="POST"
                action="{{ route('settings.payments.external-inventory') }}"
                class="pay-inventory-form"
                x-show="editing"
                x-cloak
            >
                @csrf
                <input type="hidden" name="_inventory_editing" value="1">
                <label class="pay-choice">
                    <input type="radio" name="inventory_owner" value="platform" @checked(($inventoryOwner ?? 'platform') === 'platform')>
                    <span>
                        <strong>Use dashboard inventory</strong>
                        <em>External orders reduce stock here when they sync.</em>
                    </span>
                </label>
                <label class="pay-choice">
                    <input type="radio" name="inventory_owner" value="external" @checked(($inventoryOwner ?? 'platform') === 'external')>
                    <span>
                        <strong>Website manages inventory</strong>
                        <em>Orders are recorded here without changing dashboard stock.</em>
                    </span>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="pay-btn pay-btn-primary">Save inventory source</button>
                    <button type="button" class="pay-btn pay-btn-outline" @click="editing = false">Cancel</button>
                </div>
            </form>
        </div>
    @endif
</section>
