@if($isPlatformMode)
    @if($connectReady)
        <div class="pay-switch-banner is-active">
            <div class="pay-switch-banner-copy">
                <strong>Platform checkout is active.</strong>
                <span>Customers pay through Stripe on this platform. Orders appear here after payment succeeds.</span>
            </div>
            <span class="pay-badge pay-badge-active">Active</span>
        </div>
    @else
        <div class="pay-switch-banner">
            <div class="pay-switch-banner-copy">
                <strong>Checkout is blocked until Stripe is connected.</strong>
                <span>Platform checkout is the only payment path. Connect Stripe below before customers can pay.</span>
            </div>
        </div>
    @endif
@endif

<div class="pay-stripe-grid">
    <div class="pay-stripe-card-wrap">
        @include('user_view.partials.stripe_connect_account_card', [
            'title' => 'Stripe test account',
            'description' => 'Safe sandbox payments for testing platform checkout. No real money is charged.',
            'mode' => 'test',
            'account' => $testConnectAccount ?? null,
            'ready' => $testConnectReady ?? false,
            'modeConfig' => $stripeConfig['test'] ?? [],
            'connectRoute' => route('settings.payments.stripe.connect.test'),
            'canManagePayments' => $canManagePayments,
            'consoleStyle' => true,
            'studioStyle' => true,
        ])
    </div>

    <div @class([
        'pay-stripe-card-wrap',
        'is-locked' => ! ($stripeConfig['live']['connect_configured'] ?? false),
    ])>
        @if(! ($stripeConfig['live']['connect_configured'] ?? false))
            <div class="pay-lock-overlay">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <h4>Live payments not available yet</h4>
                <p>Live Stripe connection is not configured on this platform environment. Use test mode for now.</p>
            </div>
        @endif
        @include('user_view.partials.stripe_connect_account_card', [
            'title' => 'Stripe live account',
            'description' => 'Real customer payments once your live Stripe account is ready.',
            'mode' => 'live',
            'account' => $liveConnectAccount ?? null,
            'ready' => $liveConnectReady ?? false,
            'modeConfig' => $stripeConfig['live'] ?? [],
            'connectRoute' => route('settings.payments.stripe.connect.live'),
            'canManagePayments' => $canManagePayments && ($stripeConfig['live']['connect_configured'] ?? false),
            'consoleStyle' => true,
            'studioStyle' => true,
        ])
    </div>
</div>

@if($canManagePayments)
    <form
        x-ref="paymentModeForm"
        method="POST"
        action="{{ route('settings.payments.platform-payment-mode') }}"
        class="sr-only"
        aria-hidden="true"
    >
        @csrf
        <input type="radio" name="platform_payment_mode" value="test" x-ref="modeTest" @checked(($platformPaymentMode ?? 'test') === 'test')>
        <input type="radio" name="platform_payment_mode" value="live" x-ref="modeLive" @checked(($platformPaymentMode ?? 'test') === 'live') @disabled(! ($liveConnectReady ?? false))>
        <button type="submit">Save payment mode</button>
    </form>

    <div class="pay-mode-hint">
        <span>Active Stripe mode for platform checkout:</span>
        <strong>{{ ($platformPaymentMode ?? 'test') === 'live' ? 'Live' : 'Test' }}</strong>
        @if(($platformPaymentMode ?? 'test') !== 'live' && ($liveConnectReady ?? false))
            <button type="button" class="pay-link-btn" @click="setStripePanel('live')">Use live mode</button>
        @elseif(($platformPaymentMode ?? 'test') === 'live')
            <button type="button" class="pay-link-btn" @click="setStripePanel('test')">Use test mode</button>
        @endif
    </div>
@endif

<div class="pay-info-grid">
    <article class="pay-info-card">
        <h4 class="pay-info-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            How platform checkout works
        </h4>
        <ol class="pay-steps">
            <li>This platform handles checkout and Stripe payment.</li>
            <li>Paid orders are created here automatically.</li>
            <li>Inventory and fulfillment stay in this dashboard.</li>
        </ol>
    </article>

    <article class="pay-info-card">
        <div class="pay-info-brand">
            <x-brand.stripe-logo variant="wordmark" :size="88" />
            <h4 class="pay-info-title">Connect security</h4>
        </div>
        <p class="pay-info-copy">You connect through Stripe’s hosted onboarding. Secret keys are never pasted into this dashboard. A Stripe account already used in WooCommerce cannot be reused here — connect from this portal.</p>
        <div class="pay-chip-row">
            <span class="pay-chip">Stripe Connect</span>
            <span class="pay-chip">Hosted onboarding</span>
            <span class="pay-chip">No keys in the UI</span>
        </div>
    </article>
</div>
