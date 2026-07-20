<section id="stripe-provider-card" class="payments-section">
    <div class="payments-stripe-head">
        <img
            class="payments-stripe-logo"
            src="https://cdn.jsdelivr.net/gh/simple-icons/simple-icons@v9/icons/stripe.svg"
            alt=""
            width="44"
            height="44"
        >
        <div>
            <h2 class="payments-section-title">Stripe</h2>
            <p class="payments-section-lede">Connect separate Stripe test and live accounts for platform checkout through secure Stripe hosted onboarding.</p>
            <p class="mt-1 text-xs text-[#64748B]">Test mode is for safe sandbox payments. Live mode charges real customers.</p>
        </div>

        <div class="payments-stripe-toggle" role="tablist" aria-label="Stripe account mode">
            <button
                type="button"
                role="tab"
                class="payments-stripe-toggle-btn"
                :class="{ 'is-active': stripePanel === 'test' }"
                :aria-selected="stripePanel === 'test'"
                @click="setStripePanel('test')"
            >Test Mode</button>
            <button
                type="button"
                role="tab"
                class="payments-stripe-toggle-btn"
                :class="{ 'is-active': stripePanel === 'live' }"
                :aria-selected="stripePanel === 'live'"
                @click="setStripePanel('live')"
            >Live Mode</button>
        </div>
    </div>

    @if($canManagePayments ?? false)
        <form
            x-ref="paymentModeForm"
            method="POST"
            action="{{ route('settings.payments.platform-payment-mode') }}"
            class="sr-only"
            aria-hidden="true"
        >
            @csrf
            <input
                type="radio"
                name="platform_payment_mode"
                value="test"
                x-ref="modeTest"
                @checked(($platformPaymentMode ?? 'test') === 'test')
            >
            <input
                type="radio"
                name="platform_payment_mode"
                value="live"
                x-ref="modeLive"
                @checked(($platformPaymentMode ?? 'test') === 'live')
                @disabled(! ($liveConnectReady ?? false))
            >
            <button type="submit">Save payment mode</button>
        </form>
    @else
        <p class="text-sm text-[#475569]">Current mode: {{ ($platformPaymentMode ?? 'test') === 'live' ? 'Live mode' : 'Test mode' }}</p>
    @endif

    <div class="payments-stripe-layout">
        <div class="payments-stripe-accounts">
            <div
                :class="{
 'payments-stripe-card': true,
 'is-focused': stripePanel === 'test',
 'is-dimmed': stripePanel === 'live'
 }"
            >
                @include('user_view.partials.stripe_connect_account_card', [
                    'title' => 'Stripe test account',
                    'description' => 'Use this to test platform checkout safely with Stripe sandbox payments. No real money is charged.',
                    'mode' => 'test',
                    'account' => $testConnectAccount ?? null,
                    'ready' => $testConnectReady ?? false,
                    'modeConfig' => $stripeConfig['test'] ?? [],
                    'connectRoute' => route('settings.payments.stripe.connect.test'),
                    'canManagePayments' => $canManagePayments ?? false,
                    'consoleStyle' => true,
                ])
            </div>

            <div
                :class="{
 'payments-stripe-card': true,
 'is-focused': stripePanel === 'live',
 'is-dimmed': stripePanel === 'test'
 }"
            >
                @include('user_view.partials.stripe_connect_account_card', [
                    'title' => 'Stripe live account',
                    'description' => 'Use this only when you are ready to accept real customer payments.',
                    'mode' => 'live',
                    'account' => $liveConnectAccount ?? null,
                    'ready' => $liveConnectReady ?? false,
                    'modeConfig' => $stripeConfig['live'] ?? [],
                    'connectRoute' => route('settings.payments.stripe.connect.live'),
                    'canManagePayments' => $canManagePayments ?? false,
                    'consoleStyle' => true,
                ])
            </div>
        </div>

        <aside class="payments-security-card">
            <h3 class="payments-security-title">Security First</h3>
            <p class="payments-security-copy">Your Stripe keys are never stored on our servers in raw format. All transactions are PCI-DSS Level 1 compliant via Stripe's encrypted handshaking protocol.</p>
            <ul class="payments-security-list">
                <li class="payments-security-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24389c" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
                    PCI-DSS Compliant
                </li>
                <li class="payments-security-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24389c" stroke-width="2" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    AES-256 Encryption
                </li>
                <li class="payments-security-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24389c" stroke-width="2" aria-hidden="true"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
                    Full Audit Logging
                </li>
            </ul>
        </aside>
    </div>
</section>
