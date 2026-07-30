<section id="developer-diagnostics" class="payments-diagnostics">
    <button
        type="button"
        class="payments-diagnostics-toggle"
        @click="toggleDiagnostics()"
        :aria-expanded="diagnosticsOpen"
    >
        <span class="payments-diagnostics-title">Developer diagnostics</span>
        <svg
            class="payments-diagnostics-chevron"
            :class="{ 'is-open': diagnosticsOpen }"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        ><path d="m6 9 6 6 6-6"/></svg>
    </button>

    <p class="mt-2 text-xs text-[#64748B]">Platform/server Stripe configuration only. Store owners connect accounts through Stripe hosted onboarding — they never paste keys here.</p>

    <div
        class="payments-diag-grid"
        x-show="diagnosticsOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
    >
        @foreach($stripeConfig['diagnostics'] ?? [] as $label => $configured)
            <div class="payments-diag-item">
                <span class="payments-diag-label">{{ $label }} configured</span>
                <span @class([
                    'payments-diag-badge',
                    'is-yes' => $configured,
                    'is-no' => ! $configured,
                ])>{{ $configured ? 'Configured' : 'Missing' }}</span>
            </div>
        @endforeach

        <div class="payments-diag-item">
            <span class="payments-diag-label">Platform sandbox fallback</span>
            <span @class([
                'payments-diag-badge',
                'is-yes' => ($stripeConfig['sandbox_fallback'] ?? false),
                'is-muted' => ! ($stripeConfig['sandbox_fallback'] ?? false),
            ])>{{ ($stripeConfig['sandbox_fallback'] ?? false) ? 'Enabled for local/testing' : 'Disabled' }}</span>
        </div>

        <div class="payments-diag-item">
            <span class="payments-diag-label">Live Stripe config</span>
            <span class="payments-diag-badge is-muted">{{ $stripeConfig['live_config_source_label'] ?? 'Missing' }}</span>
        </div>

        @if($stripeConfig['live_mirrors_test_keys'] ?? false)
            <div class="rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 sm:col-span-2 lg:col-span-3">
                <p class="font-semibold text-[#92400E]">Local dev: live Stripe uses test platform keys</p>
                <p class="mt-1 text-xs text-[#92400E]">Add real STRIPE_LIVE_KEY and STRIPE_LIVE_SECRET to server config for production live Connect. Values are never shown here.</p>
            </div>
        @elseif(($stripeConfig['live_config_source'] ?? 'missing') === 'real')
            <div class="rounded-xl border border-[#BBF7D0] bg-[#F0FDF4] px-4 py-3 sm:col-span-2 lg:col-span-3">
                <p class="font-semibold text-[#166534]">Real live Stripe platform config is active on this server.</p>
            </div>
        @endif
    </div>
</section>
