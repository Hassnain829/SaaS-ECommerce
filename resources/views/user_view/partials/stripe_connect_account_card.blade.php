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
    $statusClass = 'bg-[#F1F5F9] text-[#475569]';
    if ($accountReady) {
        $statusLabel = 'Connected';
        $statusClass = 'bg-[#DCFCE7] text-[#166534]';
    } elseif ($needsAction) {
        $statusLabel = 'Action required';
        $statusClass = 'bg-[#FEF3C7] text-[#92400E]';
    } elseif ($account && ! $accountDisabled) {
        $statusLabel = 'Setup in progress';
        $statusClass = 'bg-[#FEF3C7] text-[#92400E]';
    } elseif ($accountDisabled) {
        $statusLabel = 'Disabled';
        $statusClass = 'bg-[#FEE2E2] text-[#991B1B]';
    }

    $isLive = ($mode ?? 'test') === 'live';
    $unavailableMessage = $isLive
        ? 'Live Stripe connection is not available on this platform environment yet. Use test mode for now or contact the platform admin.'
        : 'Stripe test connection is not available on this platform environment yet. Contact the platform admin.';
    $connectButtonLabel = $isLive ? 'Connect Stripe live account' : 'Connect Stripe test account';
    $continueButtonLabel = $isLive ? 'Continue live onboarding' : 'Continue test onboarding';
    $refreshButtonLabel = $isLive ? 'Refresh live status' : 'Refresh test status';
    $reconnectButtonLabel = $isLive ? 'Reconnect Stripe live account' : 'Reconnect Stripe test account';
@endphp

<article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h4 class="text-lg font-poppins font-semibold text-[#0F172A]">{{ $title }}</h4>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">{{ $description }}</p>
        </div>
        <span class="shrink-0 rounded-full {{ $statusClass }} px-3 py-1 text-xs font-bold uppercase tracking-[.6px]">{{ $statusLabel }}</span>
    </div>

    <ul class="mt-3 space-y-1 text-xs text-[#475569]">
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
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Account ID</p>
                <p class="mt-1 font-semibold text-[#0F172A]">{{ $account->maskedProviderAccountId() ?? 'Pending' }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Charges</p>
                <p class="mt-1 font-semibold text-[#0F172A]">{{ $account->charges_enabled ? 'Enabled' : 'Not ready' }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Payouts</p>
                <p class="mt-1 font-semibold text-[#0F172A]">{{ $account->payouts_enabled ? 'Enabled' : 'Not ready' }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#94A3B8]">Last checked</p>
                <p class="mt-1 font-semibold text-[#0F172A]">{{ $account->last_verified_at?->diffForHumans() ?? 'Not checked yet' }}</p>
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
                        <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">
                            {{ $accountDisabled ? $reconnectButtonLabel : $connectButtonLabel }}
                        </button>
                    </form>
                @endif
            @elseif($accountReady)
                <form method="POST" action="{{ route('settings.payments.stripe.connect.status', $account) }}">
                    @csrf
                    <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">{{ $refreshButtonLabel }}</button>
                </form>
                <form method="POST" action="{{ route('settings.payments.stripe.connect.disconnect', $account) }}" onsubmit="return confirm('Disable this Stripe {{ $isLive ? 'live' : 'test' }} account for this store? Existing orders stay unchanged.');">
                    @csrf
                    <button class="h-10 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 text-sm font-semibold text-[#991B1B] hover:bg-[#FEE2E2]">Disconnect</button>
                </form>
            @else
                <form method="POST" action="{{ route('settings.payments.stripe.connect.refresh', $account) }}">
                    @csrf
                    <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">{{ $continueButtonLabel }}</button>
                </form>
                <form method="POST" action="{{ route('settings.payments.stripe.connect.status', $account) }}">
                    @csrf
                    <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">{{ $refreshButtonLabel }}</button>
                </form>
            @endif
        </div>
    @endif
</article>
