@php
    use App\Support\CheckoutMode;
@endphp

<section id="checkout-mode-cards" class="payments-section">
    <header class="payments-section-head">
        <div>
            <h2 class="payments-section-title">How does this store accept payments?</h2>
            <p class="payments-section-lede">Choose one checkout mode for this store. You can switch later.</p>
        </div>
    </header>

    <div class="payments-mode-grid">
        <article @class([
            'payments-mode-card',
            'is-selected' => $checkoutMode === CheckoutMode::EXTERNAL,
        ])>
            <div class="payments-mode-card-top">
                <div class="payments-mode-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                </div>
                <span class="payments-pill payments-pill-success">Available</span>
            </div>
            <h3 class="payments-mode-card-title">External checkout</h3>
            <p class="payments-mode-card-copy">Payments happen on your existing website. Completed orders sync into this dashboard.</p>
            <div class="payments-mode-card-foot">
                <a href="{{ route('developer-storefront.settings') }}" class="payments-btn payments-btn-secondary">Integration instructions</a>
                @if($canManagePayments)
                    @if($checkoutMode === CheckoutMode::EXTERNAL)
                        <span class="payments-btn payments-btn-current">Current mode</span>
                    @else
                        <form method="POST" action="{{ route('settings.payments.mode') }}">
                            @csrf
                            <input type="hidden" name="checkout_mode" value="{{ CheckoutMode::EXTERNAL }}">
                            <button type="submit" class="payments-btn payments-btn-secondary">Use external checkout</button>
                        </form>
                    @endif
                @endif
            </div>
        </article>

        <article @class([
            'payments-mode-card',
            'is-selected' => $checkoutMode === CheckoutMode::PLATFORM,
        ])>
            <div class="payments-mode-card-top">
                <div class="payments-mode-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <span @class([
                    'payments-pill',
                    'payments-pill-active' => $checkoutMode === CheckoutMode::PLATFORM && $connectReady,
                    'payments-pill-success' => $connectReady && $checkoutMode !== CheckoutMode::PLATFORM,
                    'payments-pill-warning' => $connectNeedsAction || $connectInProgress,
                    'payments-pill-muted' => ! $connectReady && ! $connectNeedsAction && ! $connectInProgress,
                ])>
                    @if($checkoutMode === CheckoutMode::PLATFORM)
                        <span class="payments-pill-dot" aria-hidden="true"></span>
                        Active
                    @else
                        {{ $platformStatusLabel }}
                    @endif
                </span>
            </div>
            <h3 class="payments-mode-card-title">Platform checkout</h3>
            <p class="payments-mode-card-copy">Customers pay through this platform. Orders are created automatically after payment succeeds.</p>
            <p class="text-sm text-[#64748B]">{{ $platformStatusDetail }}@if($checkoutMode === CheckoutMode::PLATFORM). This is the active checkout mode.@elseif($connectReady) Stripe is ready — switch when you are.@endif</p>
            @if($canManagePayments)
                <div class="payments-mode-card-foot">
                    @if($connectReady)
                        @if($checkoutMode === CheckoutMode::PLATFORM)
                            <span class="payments-btn payments-btn-current">Current mode</span>
                        @else
                            <form method="POST" action="{{ route('settings.payments.mode') }}">
                                @csrf
                                <input type="hidden" name="checkout_mode" value="{{ CheckoutMode::PLATFORM }}">
                                <button type="submit" class="payments-btn payments-btn-primary">Use platform checkout</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                            @csrf
                            <button type="submit" class="payments-btn payments-btn-secondary">Refresh status</button>
                        </form>
                    @elseif($hasConnectAccount && ! $connectDisabled)
                        <form method="GET" action="{{ route('settings.payments.stripe.refresh') }}">
                            <button type="submit" class="payments-btn payments-btn-primary">Continue Stripe setup</button>
                        </form>
                        <form method="POST" action="{{ route('settings.payments.stripe.status') }}">
                            @csrf
                            <button type="submit" class="payments-btn payments-btn-secondary">Refresh status</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('settings.payments.stripe.connect') }}">
                            @csrf
                            <button type="submit" class="payments-btn payments-btn-primary">{{ $connectDisabled ? 'Reconnect Stripe' : 'Connect Stripe' }}</button>
                        </form>
                    @endif
                </div>
            @endif
        </article>
    </div>
</section>
