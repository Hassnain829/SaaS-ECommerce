@php
    $uspsMerchantVisible = (bool) ($uspsMerchantVisible ?? false);
    $uspsPlatformTestingAccounts = $uspsPlatformTestingAccounts ?? collect();
    $uspsMerchantAccounts = $uspsMerchantAccounts ?? collect();
@endphp
<section class="space-y-8">
    {{-- FedEx only in support tools --}}
    <div>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-[#0F172A]">FedEx</h2>
                <p class="mt-1 text-sm text-[#64748B]">Connect your own FedEx account for live rates and labels. Postage stays on your FedEx account.</p>
            </div>
            @if (($fedExEnabled ?? false) && ($canManageShipping ?? false) && ($fedExAccounts ?? collect())->isEmpty())
                <a href="{{ route(($fedExConfig->modelAEnabled() ?? false) ? 'settings.shipping.fedex-integrator.start' : 'shipping.carriers.connect.show', ($fedExConfig->modelAEnabled() ?? false) ? [] : 'fedex') }}" class="inline-flex h-10 shrink-0 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Connect FedEx</a>
            @endif
        </div>

        @if (! ($fedExEnabled ?? false) && ! app()->environment(['local', 'testing']))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">FedEx account setup is not available yet.</div>
        @elseif (($fedExAccounts ?? collect())->isEmpty())
            <div class="rounded-2xl border border-dashed border-[color:var(--color-border-strong)] bg-[color:var(--color-surface-muted)] px-6 py-10 text-center">
                <p class="font-semibold text-[color:var(--color-ink)]">Connect your FedEx account</p>
                <p class="mx-auto mt-2 max-w-lg text-sm text-[color:var(--color-ink-muted)]">Use your FedEx account number and billing address on file with FedEx.</p>
                @if (($fedExEnabled ?? false) && ($canManageShipping ?? false))
                    <a href="{{ route(($fedExConfig->modelAEnabled() ?? false) ? 'settings.shipping.fedex-integrator.start' : 'shipping.carriers.connect.show', ($fedExConfig->modelAEnabled() ?? false) ? [] : 'fedex') }}" class="ui-btn ui-btn-primary mt-4">Connect FedEx</a>
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

    @if ($uspsMerchantVisible)
        <div>
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-[#0F172A]">USPS</h2>
                <p class="mt-1 text-sm text-[#64748B]">Merchant USPS label purchasing (preview).</p>
            </div>

            @if (! ($uspsMerchantConnectionEnabled ?? false))
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">USPS merchant connection is not enabled yet.</div>
            @elseif ($uspsMerchantAccounts->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-6 py-10 text-center">
                    <p class="font-semibold text-[#0F172A]">Connect USPS</p>
                    <a href="{{ route('settings.shipping.usps-merchant.start') }}" class="ui-btn ui-btn-primary mt-4">Start USPS connection</a>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($uspsMerchantAccounts as $account)
                        @include('user_view.shipping.partials.usps_merchant_card', ['account' => $account, 'canManageShipping' => $canManageShipping ?? false])
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</section>
