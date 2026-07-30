@php
    $manualAccounts = $carrierAccounts->filter(fn ($a) => $a->isManualProvider());
    $uspsPlatformTestingAccounts = $uspsPlatformTestingAccounts ?? collect();
    $uspsMerchantAccounts = $uspsMerchantAccounts ?? collect();
@endphp
<section class="space-y-8">
    @if ($canManageShipping ?? false)
        <div class="rounded-2xl border border-[#BFDBFE] bg-[#EFF6FF] px-5 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-[#0F172A]">Connect a delivery provider</h2>
                    <p class="mt-1 text-sm text-[#475569]">Choose FedEx, USPS, manual/local delivery, or see planned carriers like DHL and UPS.</p>
                </div>
                <a href="{{ route('shipping.carriers.connect.index') }}" class="ui-btn ui-btn-primary shrink-0">Connect delivery provider</a>
            </div>
        </div>
    @endif

    {{-- FedEx Merchant Account --}}
    <div>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-[#0F172A]">FedEx Merchant Account</h2>
                <p class="mt-1 text-sm text-[#64748B]">Connect your merchant-owned FedEx account through the platform integrator registration flow.</p>
            </div>
            @if (($fedExEnabled ?? false) && ($canManageShipping ?? false) && ($fedExAccounts ?? collect())->isEmpty())
                <a href="{{ route(($fedExConfig->modelAEnabled() ?? false) ? 'settings.shipping.fedex-integrator.start' : 'shipping.carriers.connect.show', ($fedExConfig->modelAEnabled() ?? false) ? [] : 'fedex') }}" class="inline-flex h-10 shrink-0 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Connect FedEx account</a>
            @endif
        </div>

        @if (! ($fedExEnabled ?? false) && ! app()->environment(['local', 'testing']))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">FedEx account setup is not available on this platform environment yet.</div>
        @elseif (($fedExAccounts ?? collect())->isEmpty())
                    <div class="rounded-2xl border border-dashed border-[color:var(--color-border-strong)] bg-[color:var(--color-surface-muted)] px-6 py-10 text-center">
                <p class="font-semibold text-[color:var(--color-ink)]">Connect your FedEx merchant account</p>
                <p class="mx-auto mt-2 max-w-lg text-sm text-[color:var(--color-ink-muted)]">Use your FedEx account number and billing address on file with FedEx. FedEx billing stays between you and FedEx.</p>
                @if (($fedExEnabled ?? false) && ($canManageShipping ?? false))
                    <a href="{{ route(($fedExConfig->modelAEnabled() ?? false) ? 'settings.shipping.fedex-integrator.start' : 'shipping.carriers.connect.show', ($fedExConfig->modelAEnabled() ?? false) ? [] : 'fedex') }}" class="ui-btn ui-btn-primary mt-4">Connect FedEx account</a>
                @endif
            </div>
        @else
            <div class="space-y-4">
                @foreach ($fedExAccounts as $account)
                    @include('user_view.shipping.partials.fedex_merchant_card', ['account' => $account, 'fedExConfig' => $fedExConfig])
                @endforeach
            </div>
        @endif
    </div>

    {{-- USPS Merchant Account --}}
    <div>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-[#0F172A]">USPS Merchant Account</h2>
                <p class="mt-1 text-sm text-[#64748B]">Connect your merchant-owned USPS business account. Postage stays on your USPS payment account — BmyBrand does not pay for your labels.</p>
            </div>
            @if (($uspsMerchantConnectionEnabled ?? false) && ($canManageShipping ?? false) && $uspsMerchantAccounts->isEmpty())
                <a href="{{ route('settings.shipping.usps-merchant.start') }}" class="inline-flex h-10 shrink-0 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Connect USPS account</a>
            @endif
        </div>

        @if (! ($uspsMerchantConnectionEnabled ?? false))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">USPS merchant connection is not enabled on this platform environment yet.</div>
        @elseif (! ($hasCarrierReadyOrigin ?? false))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Set up a carrier-ready US fulfillment origin before connecting USPS.
                <a href="{{ route('settings.locations.index') }}" class="ml-1 font-semibold underline">Manage locations</a>
            </div>
        @elseif ($uspsMerchantAccounts->isEmpty())
            <div class="rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-6 py-10 text-center">
                <p class="font-semibold text-[#0F172A]">Connect your USPS business account</p>
                <p class="mx-auto mt-2 max-w-lg text-sm text-[#64748B]">Authorize BmyBrand as your Label Provider in the USPS Business Portal. You never paste API keys or passwords here.</p>
                @if ($canManageShipping ?? false)
                    <a href="{{ route('settings.shipping.usps-merchant.start') }}" class="mt-4 inline-flex h-10 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Connect USPS account</a>
                @endif
            </div>
        @else
            <div class="space-y-4">
                @foreach ($uspsMerchantAccounts as $account)
                    @include('user_view.shipping.partials.usps_merchant_card', ['account' => $account, 'canManageShipping' => $canManageShipping ?? false])
                @endforeach
            </div>
        @endif
    </div>

    {{-- USPS Sandbox Diagnostics --}}
    @if (($uspsEnabled ?? false) && ($uspsPlatformConfigured ?? false))
        <details class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5 shadow-sm">
            <summary class="cursor-pointer text-xl font-semibold text-[#0F172A]">USPS sandbox diagnostics</summary>
            <p class="mt-2 text-sm text-[#64748B]">Internal platform testing only. Does not buy labels, charge postage, or change checkout totals.</p>

            @if ($canManageShipping ?? false)
                <div class="mt-4">
                    <a href="{{ route('shipping.carriers.connect.show', 'usps') }}" class="inline-flex h-10 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Connect USPS for testing</a>
                </div>
            @endif

            <div class="mt-4 space-y-3">
                @forelse ($uspsPlatformTestingAccounts as $account)
                    @include('user_view.partials.carrier_account_card', ['account' => $account, 'canManageShipping' => $canManageShipping ?? false])
                @empty
                    <p class="text-sm text-[#64748B]">No USPS testing accounts yet.</p>
                @endforelse
            </div>

            @if ($uspsPlatformTestingAccounts->isNotEmpty() && ($canManageShipping ?? false))
                <div class="mt-5 rounded-xl border border-[#E2E8F0] bg-white p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">USPS package quote tester</p>
                    <p class="mt-1 text-xs text-[#64748B]">Informational domestic quote only.</p>
                    @unless ($hasCarrierReadyOrigin ?? false)
                        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                            Set up a carrier-ready US fulfillment origin before USPS testing.
                        </div>
                    @endunless
                    @php($primaryUspsAccount = $uspsPlatformTestingAccounts->first())
                    <form method="POST" action="{{ route('settings.shipping.usps.test-package-quote') }}" class="shipping-submit-form mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="carrier_account_id" value="{{ $primaryUspsAccount->id }}">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">Origin location</span>
                                <select name="origin_location_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" @disabled(! ($hasCarrierReadyOrigin ?? false)) required>
                                    <option value="">Select origin</option>
                                    @foreach ($locations as $location)
                                        @php($readiness = $originReadinessByLocationId[$location->id] ?? null)
                                        <option value="{{ $location->id }}" @disabled(! ($readiness?->ready ?? false))>{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">Destination ZIP</span><input name="destination_postal_code" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-4">
                            <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">Weight (lb)</span><input name="weight_value" type="number" step="0.01" min="0.01" value="1" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                            <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">L (in)</span><input name="length" type="number" step="0.01" min="0.01" value="9" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                            <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">W (in)</span><input name="width" type="number" step="0.01" min="0.01" value="6" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                            <label class="space-y-1 block"><span class="text-xs font-semibold text-[#64748B]">H (in)</span><input name="height" type="number" step="0.01" min="0.01" value="2" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                        </div>
                        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white shipping-submit-btn" @disabled(! ($hasCarrierReadyOrigin ?? false))>Get USPS test quote</button>
                    </form>
                </div>
            @endif
        </details>
    @endif

    {{-- Manual / Local --}}
    <div class="rounded-2xl border border-[#E2E8F0] bg-[#FFFBEB]/30 p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-[#0F172A]">Manual / Local Delivery</h2>
                <p class="mt-1 text-sm text-[#64748B]">Use your own courier, local driver, store pickup, or manual tracking workflow — not a live carrier API.</p>
            </div>
            @if ($canManageShipping ?? false)
                <a href="{{ route('shipping.carriers.connect.show', 'manual') }}" class="inline-flex h-10 shrink-0 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Add manual/local delivery</a>
            @endif
        </div>
        <div class="mt-4 space-y-3">
            @forelse ($manualAccounts as $account)
                @include('user_view.partials.carrier_account_card', ['account' => $account, 'canManageShipping' => $canManageShipping ?? false])
            @empty
                <p class="text-sm text-[#64748B]">No manual/local delivery accounts yet.</p>
            @endforelse
        </div>
    </div>
</section>
