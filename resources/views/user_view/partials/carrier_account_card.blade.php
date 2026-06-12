@php($presenter = \App\Support\CarrierAccountStatusPresenter::for($account))
<article class="rounded-xl border border-[#E2E8F0] bg-white p-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-semibold text-[#0F172A]">{{ $account->display_name }}</p>
            <p class="mt-1 text-sm text-[#64748B]">
                @if ($account->isManualProvider())
                    Manual / local delivery
                @elseif ($account->isUsps())
                    USPS sandbox testing
                @else
                    {{ $account->carrier?->name ?? strtoupper($account->provider) }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <span class="rounded-full {{ $presenter->badgeClass() }} px-2.5 py-1 text-xs font-bold">{{ $presenter->ownershipLabel() }}</span>
            <span class="rounded-full {{ $presenter->badgeClass() }} px-2.5 py-1 text-xs font-bold">{{ $presenter->connectionStatusLabel() }}</span>
        </div>
    </div>

    @if ($account->isFedEx() && $presenter->maskedAccountNumberLabel())
        <p class="mt-3 text-xs text-[#64748B]">{{ $presenter->maskedAccountNumberLabel() }}@if ($presenter->maskedClientIdLabel()) · API key {{ $presenter->maskedClientIdLabel() }}@endif</p>
    @endif

    @if ($account->defaultOriginLocation)
        <p class="mt-2 text-xs text-[#64748B]"><span class="font-semibold text-[#0F172A]">Ship-from:</span> {{ $account->defaultOriginLocation->name }}</p>
    @endif

    @if ($account->isMerchantOwned() && $account->isFedEx())
        <p class="mt-2 text-xs text-[#64748B]">{{ $presenter->billingLabel() }}</p>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach (['Rates not enabled', 'Labels not enabled', 'Tracking not enabled', 'Pickup not enabled'] as $chip)
                <span class="rounded-full bg-[#F1F5F9] px-2 py-0.5 text-[10px] font-semibold text-[#64748B]">{{ $chip }}</span>
            @endforeach
        </div>
    @elseif ($account->isManualProvider())
        <p class="mt-2 text-xs text-[#64748B]">Internal delivery option for manual tracking and local fulfillment.</p>
    @elseif ($account->isUsps())
        <p class="mt-2 text-xs text-[#64748B]">Platform testing connection — not a merchant-owned USPS account.</p>
    @endif

    @if ($canManageShipping ?? false)
        <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-[#F1F5F9] pt-3">
            @if (! $account->isManualProvider() && ! $account->isBlockedByFedEx())
                @if ($account->isUsps())
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.usps.test', $account) }}" class="shipping-submit-form">@csrf<button type="submit" class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8] shipping-submit-btn">Test connection</button></form>
                @elseif ($account->isFedEx())
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test', $account) }}" class="shipping-submit-form">@csrf<button type="submit" class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8] shipping-submit-btn">Run connection check</button></form>
                @endif
            @endif
            @if ($account->connection_status !== 'disabled')
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.disable', $account) }}" onsubmit="return confirm('Disable this account?')">@csrf<button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Disable</button></form>
            @endif
            @if ($account->isManualProvider())
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.destroy', $account) }}" onsubmit="return confirm('Remove this manual delivery account?')">@csrf @method('DELETE')<button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Remove</button></form>
            @endif
        </div>
    @endif
</article>
