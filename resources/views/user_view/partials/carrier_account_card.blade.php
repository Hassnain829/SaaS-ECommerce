@php($presenter = \App\Support\CarrierAccountStatusPresenter::for($account))
<article class="rounded-xl border border-[#E2E8F0] bg-white p-4">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="font-semibold text-[#0F172A]">{{ $account->display_name }}</p>
            <p class="mt-1 text-sm text-[#64748B]">{{ $account->carrier?->name ?? strtoupper($account->provider) }}</p>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
            <span class="rounded-full {{ $presenter->badgeClass() }} px-2.5 py-1 text-xs font-bold">{{ $presenter->ownershipLabel() }}</span>
            <span class="rounded-full {{ $presenter->badgeClass() }} px-2.5 py-1 text-xs font-bold">{{ $presenter->connectionStatusLabel() }}</span>
        </div>
    </div>
    <p class="mt-2 text-xs text-[#64748B]">{{ $presenter->merchantStatusLabel() }}</p>
    @if ($presenter->maskedAccountNumberLabel())
        <p class="mt-2 text-xs text-[#64748B]">{{ $presenter->maskedAccountNumberLabel() }}</p>
    @endif
    @if ($account->isMerchantOwned() && $account->isFedEx())
        <p class="mt-2 text-xs text-[#64748B]">{{ $presenter->billingLabel() }}</p>
    @endif
    <ul class="mt-3 space-y-1 text-xs text-[#64748B]">
        @foreach ($presenter->merchantCapabilityLabels() as $capabilityLabel)
            <li>{{ $capabilityLabel }}</li>
        @endforeach
    </ul>
    @if ($account->defaultOriginLocation)
        <p class="mt-3 text-xs text-[#64748B]"><span class="font-semibold text-[#0F172A]">Ship-from location:</span> {{ $account->defaultOriginLocation->name }}</p>
    @endif
    @if ($canManageShipping ?? false)
        <div class="mt-3 flex flex-wrap justify-end gap-2">
            @if (! $account->isManualProvider() && ! $account->isBlockedByFedEx())
                @if ($account->isUsps())
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.usps.test', $account) }}">@csrf<button class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]">Test connection</button></form>
                @elseif ($account->isFedEx())
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test', $account) }}">@csrf<button class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-2 text-xs font-semibold text-[#1D4ED8]">Run connection check</button></form>
                @endif
            @endif
            @if ($account->connection_status !== 'disabled')
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.disable', $account) }}">@csrf<button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Disable</button></form>
            @endif
            @if ($account->isManualProvider())
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.destroy', $account) }}">@csrf @method('DELETE')<button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#991B1B]">Remove</button></form>
            @endif
        </div>
    @endif
</article>
