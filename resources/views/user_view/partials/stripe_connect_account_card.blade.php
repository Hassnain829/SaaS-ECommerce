@php
    $accountReady = $ready ?? false;
    $accountDisabled = ($account->status ?? null) === 'disabled';
    $requirementsDue = $account?->requirements_currently_due ?? [];
    $needsAction = $account
        && ! $accountDisabled
        && (
            ($account->status ?? null) === 'restricted'
            || $account->requirements_disabled_reason
            || ! empty($requirementsDue)
        );
    $statusLabel = 'Not connected';
    $statusClass = ($consoleStyle ?? false) ? 'payments-pill payments-pill-muted' : 'shrink-0 rounded-full bg-[#F1F5F9] text-[#475569] px-3 py-1 text-xs font-bold uppercase tracking-[.6px]';
    if ($accountReady) {
        $statusLabel = 'Connected';
        $statusClass = ($consoleStyle ?? false) ? 'payments-pill payments-pill-success' : 'shrink-0 rounded-full bg-[#DCFCE7] text-[#166534] px-3 py-1 text-xs font-bold uppercase tracking-[.6px]';
    } elseif ($needsAction) {
        $statusLabel = 'Action required';
        $statusClass = ($consoleStyle ?? false) ? 'payments-pill payments-pill-warning' : 'shrink-0 rounded-full bg-[#FEF3C7] text-[#92400E] px-3 py-1 text-xs font-bold uppercase tracking-[.6px]';
    } elseif ($account && ! $accountDisabled) {
        $statusLabel = 'Setup in progress';
        $statusClass = ($consoleStyle ?? false) ? 'payments-pill payments-pill-warning' : 'shrink-0 rounded-full bg-[#FEF3C7] text-[#92400E] px-3 py-1 text-xs font-bold uppercase tracking-[.6px]';
    } elseif ($accountDisabled) {
        $statusLabel = 'Disabled';
        $statusClass = ($consoleStyle ?? false) ? 'payments-pill payments-pill-muted' : 'shrink-0 rounded-full bg-[#FEE2E2] text-[#991B1B] px-3 py-1 text-xs font-bold uppercase tracking-[.6px]';
    }

    $isLive = ($mode ?? 'test') === 'live';
    $unavailableMessage = $isLive
        ? 'Live Stripe connection is not available on this platform environment yet. Use test mode for now or contact the platform admin.'
        : 'Stripe test connection is not available on this platform environment yet. Contact the platform admin.';
    $connectButtonLabel = $isLive ? 'Connect Stripe live account' : 'Connect Stripe test account';
    $continueButtonLabel = $isLive ? 'Continue live onboarding' : 'Continue test onboarding';
    $refreshButtonLabel = $isLive ? 'Refresh live status' : 'Refresh test status';
    $reconnectButtonLabel = $isLive ? 'Reconnect Stripe live account' : 'Reconnect Stripe test account';
    $useConsole = $consoleStyle ?? false;
@endphp

<article @class([
    'rounded-2xl border border-[#CBD5E1] bg-white p-5' => ! $useConsole,
])>
    <div class="payments-stripe-card-head">
        <div>
            <h4 @class([
                'payments-stripe-card-title' => $useConsole,
                'text-lg font-semibold text-[#0F172A]' => ! $useConsole,
            ])>{{ $title }}</h4>
            @unless($useConsole)
                <p class="mt-2 text-sm leading-6 text-[#64748B]">{{ $description }}</p>
            @endunless
        </div>
        <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
    </div>

    @if($useConsole)
        <p class="mb-3 text-sm leading-6 text-[#64748B]">{{ $description }}</p>
    @endif

    <ul class="mt-1 space-y-1 text-xs text-[#475569]">
        <li>You will connect through Stripe hosted onboarding.</li>
        <li>No Stripe secret keys are entered here.</li>
    </ul>

    @if($isLive && ($modeConfig['uses_local_mirror'] ?? false))
        <div class="mt-3 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
            Local simulation: live Stripe setup is using test platform keys.
        </div>
    @endif

    @if(! ($modeConfig['connect_configured'] ?? false))
        <div class="mt-4 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            {{ $unavailableMessage }}
        </div>
    @elseif($account)
        <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
            <div @class(['payments-stripe-stat' => $useConsole, 'rounded-xl bg-[#F8FAFC] px-4 py-3' => ! $useConsole])>
                <p @class(['payments-stripe-stat-label' => $useConsole, 'text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]' => ! $useConsole])>Account ID</p>
                <p @class(['payments-stripe-stat-value' => $useConsole, 'mt-1 font-semibold text-[#0F172A]' => ! $useConsole])>{{ $account->maskedProviderAccountId() ?? 'Pending' }}</p>
            </div>
            <div @class(['payments-stripe-stat' => $useConsole, 'rounded-xl bg-[#F8FAFC] px-4 py-3' => ! $useConsole])>
                <p @class(['payments-stripe-stat-label' => $useConsole, 'text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]' => ! $useConsole])>Charges</p>
                <p @class(['payments-stripe-stat-value' => $useConsole, 'mt-1 font-semibold text-[#0F172A]' => ! $useConsole])>{{ $account->charges_enabled ? 'Enabled' : 'Not ready' }}</p>
            </div>
            <div @class(['payments-stripe-stat' => $useConsole, 'rounded-xl bg-[#F8FAFC] px-4 py-3' => ! $useConsole])>
                <p @class(['payments-stripe-stat-label' => $useConsole, 'text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]' => ! $useConsole])>Payouts</p>
                <p @class(['payments-stripe-stat-value' => $useConsole, 'mt-1 font-semibold text-[#0F172A]' => ! $useConsole])>{{ $account->payouts_enabled ? 'Enabled' : 'Not ready' }}</p>
            </div>
            <div @class(['payments-stripe-stat' => $useConsole, 'rounded-xl bg-[#F8FAFC] px-4 py-3' => ! $useConsole])>
                <p @class(['payments-stripe-stat-label' => $useConsole, 'text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]' => ! $useConsole])>Last checked</p>
                <p @class(['payments-stripe-stat-value' => $useConsole, 'mt-1 font-semibold text-[#0F172A]' => ! $useConsole])>{{ $account->last_verified_at?->diffForHumans() ?? 'Not checked yet' }}</p>
            </div>
        </div>

        @if($needsAction)
            <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                Stripe needs more account details before this {{ $isLive ? 'live' : 'test' }} account can accept platform checkout payments.
            </div>
        @endif

        @if(! empty($requirementsDue))
            <p class="mt-3 text-xs text-[#64748B]">Requirements due: {{ implode(', ', $requirementsDue) }}</p>
        @endif
    @else
        <div class="mt-4 rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
            No Stripe {{ $isLive ? 'live' : 'test' }} account is connected for this store yet.
            You will be redirected to Stripe to connect your account securely.
        </div>
    @endif

    @if($canManagePayments ?? false)
        <div class="mt-4 flex flex-wrap gap-2">
            @if(! $account || $accountDisabled)
                @if($modeConfig['connect_configured'] ?? false)
                    <form method="POST" action="{{ $connectRoute }}">
                        @csrf
                        <button @class([
                            'payments-btn payments-btn-primary' => $useConsole,
                            'h-10 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover' => ! $useConsole,
                        ])>
                            {{ $accountDisabled ? $reconnectButtonLabel : $connectButtonLabel }}
                        </button>
                    </form>
                @endif
            @elseif($accountReady)
                <form method="POST" action="{{ route('settings.payments.stripe.connect.status', $account) }}">
                    @csrf
                    <button @class([
                        'payments-btn payments-btn-secondary' => $useConsole,
                        'h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]' => ! $useConsole,
                    ])>{{ $refreshButtonLabel }}</button>
                </form>
                <form method="POST" action="{{ route('settings.payments.stripe.connect.disconnect', $account) }}" onsubmit="return confirm('Disable this Stripe {{ $isLive ? 'live' : 'test' }} account for this store? Existing orders stay unchanged.');">
                    @csrf
                    <button @class([
                        'payments-btn payments-btn-danger' => $useConsole,
                        'h-10 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 text-sm font-semibold text-[#991B1B] hover:bg-[#FEE2E2]' => ! $useConsole,
                    ])>Disconnect</button>
                </form>
            @else
                <form method="POST" action="{{ route('settings.payments.stripe.connect.refresh', $account) }}">
                    @csrf
                    <button @class([
                        'payments-btn payments-btn-primary' => $useConsole,
                        'h-10 rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover' => ! $useConsole,
                    ])>{{ $continueButtonLabel }}</button>
                </form>
                <form method="POST" action="{{ route('settings.payments.stripe.connect.status', $account) }}">
                    @csrf
                    <button @class([
                        'payments-btn payments-btn-secondary' => $useConsole,
                        'h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]' => ! $useConsole,
                    ])>{{ $refreshButtonLabel }}</button>
                </form>
            @endif
        </div>
    @endif
</article>
