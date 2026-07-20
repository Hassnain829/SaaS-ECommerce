<section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
    <h2 class="text-xl font-semibold text-[#0F172A]">Authorize {{ $labelProviderName }} in USPS</h2>
    <p class="mt-2 text-sm leading-6 text-[#64748B]">
        BmyBrand needs official USPS Label Provider authorization before it can create labels on your behalf.
        Postage stays on your Enterprise Payment Account — we never ask for your USPS password or API secrets here.
    </p>

    @if ($merchantOAuthAvailable ?? false)
        <div class="mt-4 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-4">
            <h3 class="text-sm font-semibold text-[#1D4ED8]">Authorize with USPS</h3>
            <p class="mt-2 text-sm text-[#1E40AF]">
                Use the secure USPS authorization flow below. This is the only way to complete Label Provider authorization in BmyBrand.
            </p>
            @if ($canManageShipping ?? false)
                <a href="{{ route('settings.shipping.usps-merchant.oauth.start', $account) }}" class="mt-4 inline-flex h-10 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">
                    Authorize with USPS
                </a>
            @endif
        </div>
    @else
        <div class="mt-4 rounded-xl border border-[#FEF3C7] bg-[#FFFBEB] px-4 py-4">
            <h3 class="text-sm font-semibold text-[#92400E]">Awaiting official USPS authorization</h3>
            <p class="mt-2 text-sm leading-6 text-[#92400E]">
                BmyBrand platform Label Provider authorization is not available yet. Your USPS account details are saved,
                but this connection stays in <strong>awaiting authorization</strong> until USPS provides the official merchant authorization process.
            </p>
            <p class="mt-2 text-sm leading-6 text-[#92400E]">
                Signing in to the USPS Business Portal or visiting My Apps does not complete BmyBrand authorization on its own.
            </p>
        </div>
    @endif

    <div class="mt-4">
        <a href="{{ $businessPortalUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Manage USPS Business Account</a>
    </div>
    <p class="mt-2 text-xs leading-5 text-[#64748B]">
        Use this link only to manage your USPS business account — for example EPA payment setup, Ship enrollment, or account details.
        It does not authorize BmyBrand as your Label Provider.
    </p>

    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
        <p class="font-semibold">If USPS shows an internal system error after login</p>
        <p class="mt-2 leading-6">
            That message comes from USPS, not BmyBrand. Your sign-in worked, but the USPS portal failed to load your dashboard.
            Try again later, use a private/incognito window, or contact USPS Business Customer Gateway support.
        </p>
    </div>
</section>

<section class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-5 py-4 text-sm text-[#64748B]">
    @if ($merchantOAuthAvailable ?? false)
        After USPS authorization, BmyBrand verifies Label Provider access automatically. Ship enrollment and postage verification continue in the next setup phase.
    @else
        Automated Label Provider authorization will be enabled once BmyBrand receives the official USPS merchant authorization URL from USPS. Your setup progress is saved until then.
    @endif
</section>
