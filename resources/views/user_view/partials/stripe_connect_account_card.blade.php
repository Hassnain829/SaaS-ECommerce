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
@endphp

<article class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h4 class="text-lg font-poppins font-semibold text-[#0F172A]">{{ $title }}</h4>
            <p class="mt-2 text-sm leading-6 text-[#64748B]">{{ $description }}</p>
        </div>
        <span class="shrink-0 rounded-full {{ $statusClass }} px-3 py-1 text-xs font-bold uppercase tracking-[.6px]">{{ $statusLabel }}</span>
    </div>

    @if(! ($modeConfig['connect_configured'] ?? false))
        <div class="mt-4 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">
            Stripe {{ $mode }} mode is not configured on this environment yet. Add the {{ $mode }} Stripe keys before connecting.
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
                Stripe needs more account details before this {{ $mode }} account can accept platform checkout payments.
            </div>
        @endif

        @if(! empty($requirementsDue))
            <p class="mt-3 text-xs text-[#64748B]">Requirements due: {{ implode(', ', $requirementsDue) }}</p>
        @endif
    @else
        <div class="mt-4 rounded-xl bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
            No Stripe {{ $mode }} account is connected for this store yet.
        </div>
    @endif

    @if($canManagePayments ?? false)
        <div class="mt-4 flex flex-wrap gap-2">
            @if(! $account || $accountDisabled)
                @if($modeConfig['connect_configured'] ?? false)
                    <form method="POST" action="{{ $connectRoute }}">
                        @csrf
                        <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">
                            {{ $accountDisabled ? 'Reconnect '.$mode.' account' : 'Connect '.$mode.' account' }}
                        </button>
                    </form>
                @endif
            @elseif($accountReady)
                <form method="POST" action="{{ route('settings.payments.stripe.connect.status', $account) }}">
                    @csrf
                    <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">Refresh status</button>
                </form>
                <form method="POST" action="{{ route('settings.payments.stripe.connect.disconnect', $account) }}" onsubmit="return confirm('Disable this Stripe {{ $mode }} account for this store? Existing orders stay unchanged.');">
                    @csrf
                    <button class="h-10 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 text-sm font-semibold text-[#991B1B] hover:bg-[#FEE2E2]">Disconnect</button>
                </form>
            @else
                <form method="POST" action="{{ route('settings.payments.stripe.connect.refresh', $account) }}">
                    @csrf
                    <button class="h-10 rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]">Continue onboarding</button>
                </form>
                <form method="POST" action="{{ route('settings.payments.stripe.connect.status', $account) }}">
                    @csrf
                    <button class="h-10 rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]">Refresh status</button>
                </form>
            @endif
        </div>
    @endif
</article>
