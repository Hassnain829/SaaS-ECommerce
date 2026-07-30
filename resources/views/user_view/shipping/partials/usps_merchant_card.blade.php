@php
    $presenter = \App\Services\Carriers\USPS\Presenters\USPSMerchantStatusPresenter::for($account);
    $context = \App\Services\Carriers\USPS\Support\USPSMerchantConnectionContext::for($account);
    $originName = $account->defaultOriginLocation?->name;
@endphp
<article class="overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
    <div class="border-b border-[#F1F5F9] px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">USPS Merchant Account</p>
                <h3 class="mt-1 text-lg font-semibold text-[#0F172A]">{{ $account->display_name }}</h3>
                <p class="mt-1 text-sm text-[#64748B]">{{ $presenter->merchantSummary() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full {{ $presenter->badgeClass() }} px-2.5 py-1 text-xs font-bold">{{ $presenter->authorizationStatusLabel() }}</span>
                <span class="rounded-full bg-[#F0FDF4] px-2.5 py-1 text-xs font-bold text-[#047857]">Merchant-owned</span>
            </div>
        </div>
    </div>

    <div class="space-y-4 px-5 py-4">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs font-semibold text-[#64748B]">Billing</dt><dd class="mt-0.5 text-[#0F172A]">Your USPS payment account</dd></div>
            <div><dt class="text-xs font-semibold text-[#64748B]">Enrollment</dt><dd class="mt-0.5 text-[#0F172A]">{{ $presenter->enrollmentStatusLabel() }}</dd></div>
            @if ($originName)
                <div><dt class="text-xs font-semibold text-[#64748B]">Ship-from</dt><dd class="mt-0.5 text-[#0F172A]">{{ $originName }}</dd></div>
            @endif
            <div><dt class="text-xs font-semibold text-[#64748B]">CRID / MID / EPA</dt><dd class="mt-0.5 text-[#0F172A]">{{ $context->merchantCridMasked() ?? '—' }} / {{ $context->merchantMidMasked() ?? '—' }} / {{ $context->merchantEpaMasked() ?? '—' }}</dd></div>
        </dl>

        @if ($account->last_error_message)
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $account->last_error_message }}</p>
        @endif

        <div class="flex flex-wrap gap-2">
            @if ($canManageShipping ?? false)
                @if ($account->usps_authorization_status === \App\Models\CarrierAccount::USPS_AUTH_VERIFYING)
                    <a href="{{ route('settings.shipping.usps-merchant.manage', $account) }}" class="inline-flex h-9 items-center rounded-lg bg-brand px-3 text-sm font-bold text-white">Manage connection</a>
                @elseif ($account->usps_authorization_status === \App\Models\CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION)
                    <a href="{{ route('settings.shipping.usps-merchant.wizard', ['carrierAccount' => $account, 'step' => 'authorization']) }}" class="inline-flex h-9 items-center rounded-lg bg-brand px-3 text-sm font-bold text-white">Continue setup</a>
                @else
                    <a href="{{ route('settings.shipping.usps-merchant.manage', $account) }}" class="inline-flex h-9 items-center rounded-lg bg-brand px-3 text-sm font-bold text-white">Manage connection</a>
                @endif
            @endif
        </div>
    </div>
</article>
