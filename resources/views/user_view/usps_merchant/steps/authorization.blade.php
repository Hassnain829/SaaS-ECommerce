<section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
    <h2 class="text-xl font-semibold text-[#0F172A]">Authorize {{ $labelProviderName }} in USPS</h2>
    <p class="mt-2 text-sm leading-6 text-[#64748B]">
        Sign in to the USPS Business Portal and authorize {{ $labelProviderName }} as your Label Provider.
        This allows BmyBrand to create labels on your behalf while postage stays on your USPS payment account.
    </p>

    @if ($merchantOAuthAvailable ?? false)
        <div class="mt-4 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-4">
            <h3 class="text-sm font-semibold text-[#1D4ED8]">Recommended: Authorize with USPS</h3>
            <p class="mt-2 text-sm text-[#1E40AF]">
                First-time authorization uses the official USPS Business Portal link. BmyBrand never asks for your USPS password or API secrets.
            </p>
            @if ($canManageShipping ?? false)
                <a href="{{ route('settings.shipping.usps-merchant.oauth.start', $account) }}" class="mt-4 inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">
                    Authorize with USPS
                </a>
            @endif
        </div>
    @endif

    <ol class="mt-4 space-y-2 text-sm text-[#475569]">
        <li>1. Open the USPS Business Portal and sign in with your business account.</li>
        <li>2. Go to Label Provider authorization (or Manage Label Providers).</li>
        <li>3. Authorize <strong>{{ $labelProviderName }}</strong>.</li>
        <li>4. Return here and confirm authorization below@if ($merchantOAuthAvailable ?? false) if you used the portal manually@endif.</li>
    </ol>

    <div class="mt-4">
        <a href="{{ $businessPortalUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Open USPS Business Portal</a>
    </div>

    @if ($canManageShipping ?? false)
        <form method="POST" action="{{ route('settings.shipping.usps-merchant.authorization', $account) }}" class="mt-6 space-y-4 border-t border-[#F1F5F9] pt-5">
            @csrf
            <p class="text-sm font-semibold text-[#0F172A]">
                @if ($merchantOAuthAvailable ?? false)
                    Manual confirmation (fallback)
                @else
                    Confirm authorization
                @endif
            </p>
            <label class="flex items-start gap-3 text-sm text-[#475569]">
                <input type="checkbox" name="requirements_confirmed" value="1" @checked(old('requirements_confirmed')) class="mt-1" required>
                <span>My USPS business account is active, my EPA has a valid payment method, and postage should be charged to my EPA.</span>
            </label>
            <label class="flex items-start gap-3 text-sm text-[#475569]">
                <input type="checkbox" name="portal_authorization_confirmed" value="1" @checked(old('portal_authorization_confirmed')) class="mt-1" required>
                <span>I authorized <strong>{{ $labelProviderName }}</strong> as my Label Provider in the USPS Business Portal.</span>
            </label>
            <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">
                @if ($merchantOAuthAvailable ?? false)
                    I've authorized BmyBrand manually
                @else
                    I've authorized BmyBrand in USPS
                @endif
            </button>
        </form>
    @endif
</section>

<section class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] px-5 py-4 text-sm text-[#64748B]">
    @if ($merchantOAuthAvailable ?? false)
        After USPS authorization, BmyBrand verifies Label Provider access automatically. Ship enrollment and postage verification continue in the next setup phase.
    @else
        Automated USPS authorization verification will be enabled once BmyBrand platform Label Provider OAuth is approved. Until then, your setup is saved and ready.
    @endif
</section>
