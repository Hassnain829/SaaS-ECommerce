@php
    $defaultLocation = $locations->firstWhere('is_default', true) ?? $locations->first();
    $defaultReadiness = $defaultLocation ? ($originReadinessByLocationId[$defaultLocation->id] ?? null) : null;
    $connectedCarriers = $carrierAccounts->filter(fn ($a) => $a->isConnected() || ($a->isManualProvider() && $a->status === 'enabled'))->count();
    $activeZones = $shippingZones->where('is_active', true)->count();
    $activeMethods = $shippingMethods->where('is_active', true)->count();
    $checkoutMethods = $shippingMethods->where('is_active', true)->where('enabled_for_checkout', true)->count();
    $fedExConnected = ($fedExAccounts ?? collect())->contains(fn ($a) => $a->isConnected());
    $uspsConnected = ($uspsAccounts ?? collect())->contains(fn ($a) => $a->isConnected());
    $hasCarrierOrManual = $carrierAccounts->isNotEmpty();
    $hasOriginReady = ($hasCarrierReadyOrigin ?? false) && $defaultLocation !== null;

    $checklist = [
        ['label' => 'Fulfillment origin configured', 'done' => $hasOriginReady, 'warn' => $defaultLocation && ! ($defaultReadiness?->ready ?? false)],
        ['label' => 'Carrier or manual delivery account', 'done' => $hasCarrierOrManual],
        ['label' => 'Active delivery zone', 'done' => $activeZones > 0],
        ['label' => 'Active delivery method', 'done' => $activeMethods > 0],
        ['label' => 'FedEx connected', 'done' => ! ($fedExAccounts ?? collect())->count() ? null : $fedExConnected, 'optional' => true],
        ['label' => 'USPS testing connected', 'done' => ! ($uspsAccounts ?? collect())->count() ? null : $uspsConnected, 'optional' => true],
        ['label' => 'Checkout delivery method', 'done' => $checkoutMethods > 0],
    ];

    if (! $hasOriginReady) {
        $nextAction = ['title' => 'Add fulfillment origin', 'body' => 'Complete a ship-from address on a fulfillment location before carrier testing.', 'href' => route('settings.locations.index'), 'label' => 'Manage locations'];
    } elseif (! $hasCarrierOrManual) {
        $nextAction = ['title' => 'Connect a carrier or manual delivery', 'body' => 'Add FedEx credentials, USPS sandbox testing, or manual/local delivery.', 'tab' => 'carriers', 'label' => 'Manage carriers'];
    } elseif ($activeZones === 0) {
        $nextAction = ['title' => 'Create a delivery zone', 'body' => 'Define where this store delivers before adding methods.', 'tab' => 'zones', 'label' => 'Manage zones'];
    } elseif ($activeMethods === 0) {
        $nextAction = ['title' => 'Add a delivery method', 'body' => 'Create Standard delivery, Local delivery, or Store pickup.', 'tab' => 'methods', 'label' => 'Add delivery method'];
    } else {
        $nextAction = ['title' => 'Shipping setup is ready', 'body' => 'Core shipping configuration is in place for platform checkout and manual fulfillment.', 'tab' => null, 'label' => null];
    }
@endphp

<section class="space-y-6">
    <div class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
        <h2 class="text-lg font-poppins font-semibold text-[#0F172A]">Setup checklist</h2>
        <ul class="mt-4 space-y-2">
            @foreach ($checklist as $item)
                @if ($item['done'] === null)
                    @continue
                @endif
                <li class="flex items-start gap-3 text-sm">
                    @if ($item['done'])
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#ECFDF5] text-[#047857]">✓</span>
                        <span class="text-[#0F172A]">{{ $item['label'] }}</span>
                    @elseif ($item['warn'] ?? false)
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#FEF3C7] text-[#92400E]">!</span>
                        <span class="text-[#92400E]">{{ $item['label'] }} — needs attention</span>
                    @else
                        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#F1F5F9] text-[#64748B]">○</span>
                        <span class="text-[#64748B]">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Fulfillment origin</p>
            <p class="mt-2 font-semibold text-[#0F172A]">{{ $defaultLocation?->name ?? 'Not set' }}</p>
            @if ($defaultReadiness)
                <span class="mt-2 inline-flex rounded-full {{ $defaultReadiness->ready ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#FEF3C7] text-[#92400E]' }} px-2 py-0.5 text-xs font-bold">{{ $defaultReadiness->badgeLabel }}</span>
            @endif
            <button type="button" data-shipping-tab="locations" class="mt-3 text-sm font-semibold text-[#1D4ED8]">Manage locations</button>
        </article>
        <article class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Carrier accounts</p>
            <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $connectedCarriers }} <span class="text-sm font-normal text-[#64748B]">connected</span></p>
            <p class="mt-1 text-xs text-[#64748B]">
                @if ($fedExConnected) FedEx connected @else FedEx not connected @endif
                · @if (($uspsAccounts ?? collect())->isNotEmpty() && $uspsConnected) USPS testing @else USPS optional @endif
            </p>
            <button type="button" data-shipping-tab="carriers" class="mt-3 text-sm font-semibold text-[#1D4ED8]">Manage carriers</button>
        </article>
        <article class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Delivery zones</p>
            <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $activeZones }} <span class="text-sm font-normal text-[#64748B]">active</span></p>
            <p class="mt-1 truncate text-xs text-[#64748B]">{{ $shippingZones->first()?->name ?? 'No zones yet' }}</p>
            <button type="button" data-shipping-tab="zones" class="mt-3 text-sm font-semibold text-[#1D4ED8]">Manage zones</button>
        </article>
        <article class="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Delivery methods</p>
            <p class="mt-2 text-2xl font-semibold text-[#0F172A]">{{ $activeMethods }} <span class="text-sm font-normal text-[#64748B]">active</span></p>
            <p class="mt-1 text-xs text-[#64748B]">{{ $checkoutMethods }} at checkout · {{ $shippingMethods->first()?->name ?? 'No methods yet' }}</p>
            <button type="button" data-shipping-tab="methods" class="mt-3 text-sm font-semibold text-[#1D4ED8]">Manage methods</button>
        </article>
    </div>

    <div class="rounded-2xl border border-[#BFDBFE] bg-[#EFF6FF] p-5">
        <p class="text-xs font-bold uppercase tracking-[1px] text-[#1D4ED8]">Recommended next step</p>
        <h3 class="mt-1 text-lg font-semibold text-[#0F172A]">{{ $nextAction['title'] }}</h3>
        <p class="mt-2 text-sm text-[#475569]">{{ $nextAction['body'] }}</p>
        @if ($nextAction['href'] ?? null)
            <a href="{{ $nextAction['href'] }}" class="mt-4 inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">{{ $nextAction['label'] }}</a>
        @elseif ($nextAction['tab'] ?? null)
            <button type="button" data-shipping-tab="{{ $nextAction['tab'] }}" class="mt-4 inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">{{ $nextAction['label'] }}</button>
        @endif
    </div>
</section>
