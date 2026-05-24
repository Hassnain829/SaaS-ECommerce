    <section id="stripe-provider-card" class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6 space-y-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Payment provider</p>
            <h3 class="mt-1 text-xl font-poppins font-semibold text-[#0F172A]">Stripe</h3>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">
                Connect separate Stripe test and live accounts for platform checkout. Stripe handles secure onboarding. You do not need to paste secret keys.
            </p>
        </div>

        <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
            <p class="text-sm font-semibold text-[#0F172A]">Platform checkout payment mode</p>
            <p class="mt-1 text-sm leading-6 text-[#64748B]">Test mode is for safe sandbox payments. Live mode charges real customers.</p>
            @if($canManagePayments ?? false)
                <form method="POST" action="{{ route('settings.payments.platform-payment-mode') }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                        <input type="radio" name="platform_payment_mode" value="test" @checked(($platformPaymentMode ?? 'test') === 'test') class="mt-1">
                        <span><span class="font-semibold text-[#0F172A]">Test mode</span><span class="mt-0.5 block text-xs text-[#64748B]">Uses the connected Stripe test account or local sandbox fallback.</span></span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-[#334155]">
                        <input type="radio" name="platform_payment_mode" value="live" @checked(($platformPaymentMode ?? 'test') === 'live') class="mt-1" @disabled(! ($liveConnectReady ?? false))>
                        <span><span class="font-semibold text-[#0F172A]">Live mode</span><span class="mt-0.5 block text-xs text-[#64748B]">Requires an active Stripe live connected account.</span></span>
                    </label>
                    <button type="submit" class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">Save payment mode</button>
                </form>
            @else
                <p class="mt-3 text-sm text-[#475569]">Current mode: {{ ($platformPaymentMode ?? 'test') === 'live' ? 'Live mode' : 'Test mode' }}</p>
            @endif
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @include('user_view.partials.stripe_connect_account_card', [
                'title' => 'Stripe test account',
                'description' => 'Use this to test platform checkout safely with Stripe sandbox payments. No real money is charged.',
                'mode' => 'test',
                'account' => $testConnectAccount ?? null,
                'ready' => $testConnectReady ?? false,
                'modeConfig' => $stripeConfig['test'] ?? [],
                'connectRoute' => route('settings.payments.stripe.connect.test'),
                'canManagePayments' => $canManagePayments ?? false,
            ])
            @include('user_view.partials.stripe_connect_account_card', [
                'title' => 'Stripe live account',
                'description' => 'Use this only when you are ready to accept real customer payments.',
                'mode' => 'live',
                'account' => $liveConnectAccount ?? null,
                'ready' => $liveConnectReady ?? false,
                'modeConfig' => $stripeConfig['live'] ?? [],
                'connectRoute' => route('settings.payments.stripe.connect.live'),
                'canManagePayments' => $canManagePayments ?? false,
            ])
        </div>
    </section>

    @if($canManagePayments)
        <details id="developer-diagnostics" class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
            <summary class="cursor-pointer text-lg font-poppins font-semibold text-[#0F172A]">Developer diagnostics</summary>
            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach(['test' => 'Test', 'live' => 'Live'] as $modeKey => $modeLabel)
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <p class="font-semibold text-[#0F172A]">{{ $modeLabel }} publishable key</p>
                        <p class="mt-1 {{ ($stripeConfig[$modeKey]['publishable_key'] ?? false) ? 'text-[#059669]' : 'text-[#B91C1C]' }}">{{ ($stripeConfig[$modeKey]['publishable_key'] ?? false) ? 'Configured' : 'Missing' }}</p>
                    </div>
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <p class="font-semibold text-[#0F172A]">{{ $modeLabel }} webhook</p>
                        <p class="mt-1 {{ ($stripeConfig[$modeKey]['webhook_secret'] ?? false) ? 'text-[#059669]' : 'text-[#B91C1C]' }}">{{ ($stripeConfig[$modeKey]['webhook_secret'] ?? false) ? 'Configured' : 'Missing' }}</p>
                    </div>
                    <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                        <p class="font-semibold text-[#0F172A]">{{ $modeLabel }} connect webhook</p>
                        <p class="mt-1 {{ ($stripeConfig[$modeKey]['connect_webhook_secret'] ?? false) ? 'text-[#059669]' : 'text-[#B91C1C]' }}">{{ ($stripeConfig[$modeKey]['connect_webhook_secret'] ?? false) ? 'Configured' : 'Missing' }}</p>
                    </div>
                @endforeach
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Platform sandbox fallback</p>
                    <p class="mt-1 text-[#64748B]">{{ ($stripeConfig['sandbox_fallback'] ?? false) ? 'Enabled for local/testing' : 'Disabled' }}</p>
                </div>
            </div>
        </details>
    @endif
