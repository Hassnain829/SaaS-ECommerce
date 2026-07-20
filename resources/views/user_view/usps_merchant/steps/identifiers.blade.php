<section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
    <h2 class="text-xl font-semibold text-[#0F172A]">USPS account details</h2>
    <p class="mt-2 text-sm leading-6 text-[#64748B]">
        Enter the account numbers from your USPS Business Portal. These identify your business account for verification later.
        This is not your USPS login password, API key, or platform secret.
    </p>

    @if ($canManageShipping ?? false)
        <form method="POST" action="{{ route('settings.shipping.usps-merchant.identifiers', $account) }}" class="mt-5 space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Customer Registration ID (CRID)</span>
                    <input type="text" name="merchant_crid" inputmode="numeric" pattern="\d{5,12}" maxlength="12" required value="{{ old('merchant_crid') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="e.g. 49188300">
                </label>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Mailer ID (MID)</span>
                    <input type="text" name="merchant_mid" inputmode="numeric" pattern="(\d{6}|\d{9})" maxlength="9" required value="{{ old('merchant_mid') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Enterprise Payment Account (EPA)</span>
                    <input type="text" name="merchant_epa" inputmode="numeric" pattern="\d{5,12}" maxlength="12" required value="{{ old('merchant_epa') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="Postage account number">
                </label>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Manifest MID (optional)</span>
                    <input type="text" name="merchant_manifest_mid" inputmode="numeric" pattern="\d{5,12}" maxlength="12" value="{{ old('merchant_manifest_mid') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
            </div>
            <p class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">
                Only account numbers are stored here, encrypted at rest, and shown masked in the UI. Postage is always charged to your EPA — never to BmyBrand.
            </p>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white">Continue to Label Provider authorization</button>
        </form>
    @endif
</section>
