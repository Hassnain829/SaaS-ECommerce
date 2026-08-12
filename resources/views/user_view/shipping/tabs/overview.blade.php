@php
    $setup = $deliverySetup ?? [];
    $healthItems = collect($setup['health_items'] ?? []);
    $isReady = (bool) ($setup['is_ready'] ?? false);
    $locationsList = collect($locations ?? []);
    $originReadiness = $originReadinessByLocationId ?? [];
    $zones = collect($shippingZones ?? [])->where('is_active', true)->values();
    $methods = collect($shippingMethods ?? []);
    $checkoutMethods = $methods->where('is_active', true)->where('enabled_for_checkout', true);
    $fedExLiveMethods = $methods->filter(fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod() && ($m->is_active || $m->enabled_for_checkout));

    $statusTone = static function (array $summary): string {
        return match ($summary['status'] ?? 'missing') {
            'complete', 'added', 'included' => 'ready',
            'optional', 'off' => 'optional',
            'needs_attention' => 'attention',
            default => 'missing',
        };
    };
    $statusLabel = static fn (string $tone): string => match ($tone) {
        'ready' => 'Ready',
        'optional' => 'Optional',
        'attention' => 'Needs attention',
        default => 'Not set',
    };

    $checkoutSummaryLines = [];
    foreach ($zones as $zone) {
        $zoneFedEx = $fedExLiveMethods->where('shipping_zone_id', $zone->id);
        $zoneFallback = $checkoutMethods->first(fn ($m) => (int) $m->shipping_zone_id === (int) $zone->id && ! (method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()));
        if ($zoneFedEx->isEmpty() && ! $zoneFallback) {
            continue;
        }
        $services = $zoneFedEx->pluck('carrier_service_name')->filter()->implode(', ');
        $line = $zone->name.': ';
        if ($zoneFedEx->isNotEmpty()) {
            $line .= 'FedEx live — '.($services !== '' ? $services : 'services configured');
        }
        if ($zoneFallback) {
            $price = ((float) ($zoneFallback->flat_rate ?? 0) > 0)
                ? ' $'.number_format((float) $zoneFallback->flat_rate, 2)
                : '';
            $line .= ($zoneFedEx->isNotEmpty() ? ' · Fallback: ' : 'Fixed/free — ').$zoneFallback->name.$price;
        }
        $checkoutSummaryLines[] = $line;
    }

    $fedExAccount = ($fedExAccounts ?? collect())->first(
        fn ($account) => $account->usesFedExIntegratorProvider()
            && $account->disconnected_at === null
            && $account->replaced_at === null
    ) ?? ($fedExAccounts ?? collect())->first();
    $uspsAccount = ($uspsMerchantAccounts ?? collect())->first();

    $fedExStatus = 'setup';
    $fedExLabel = 'Setup required';
    $fedExDetail = 'Connect your FedEx account to offer live rates and labels.';
    $fedExHref = ($fedExConfig->modelAEnabled() ?? false)
        ? route('settings.shipping.fedex-integrator.start')
        : route('shipping.carriers.connect.show', 'fedex');
    if ($fedExAccount) {
        $fedExHref = $fedExAccount->usesFedExIntegratorProvider()
            ? route('settings.shipping.fedex-integrator.manage', $fedExAccount)
            : route('shippingAutomation', ['tab' => 'providers']);
        $fedExDetail = 'Account '.$fedExAccount->maskedAccountNumber();
        if (in_array($fedExAccount->connection_status, ['connected', 'sandbox_platform_fallback'], true)) {
            $checkoutPlatformOn = (bool) config('carriers.fedex.checkout_rates_enabled', false);
            $caps = is_array($fedExAccount->capabilities) ? $fedExAccount->capabilities : [];
            $checkoutActive = $checkoutPlatformOn
                && (bool) $fedExAccount->enabled_for_checkout
                && (bool) ($caps['checkout_rates'] ?? false)
                && $fedExLiveMethods->isNotEmpty();
            if ($checkoutActive) {
                $fedExStatus = 'connected';
                $fedExLabel = 'Connected';
                $fedExDetail .= ' · Checkout active';
            } else {
                $fedExStatus = 'attention';
                $fedExLabel = 'Needs setup';
                $fedExDetail .= ' · Checkout rates need setup';
            }
        } elseif (in_array($fedExAccount->connection_status, ['failed', 'blocked_by_fedex'], true)) {
            $fedExStatus = 'attention';
            $fedExLabel = 'Needs attention';
        }
    }

    $uspsStatus = 'setup';
    $uspsLabel = 'Coming later';
    $uspsDetail = 'USPS merchant label purchasing stays deferred until platform approval is complete.';
    $uspsHref = ($uspsMerchantConnectionEnabled ?? false)
        ? route('settings.shipping.usps-merchant.start')
        : route('shippingAutomation', ['tab' => 'providers']);
    if ($uspsAccount) {
        $uspsHref = route('settings.shipping.usps-merchant.manage', $uspsAccount);
        $uspsDetail = $uspsAccount->display_name ?? 'USPS merchant account';
        if ($uspsAccount->usps_authorization_status === \App\Models\CarrierAccount::USPS_AUTH_CONNECTED
            || $uspsAccount->connection_status === 'connected') {
            $uspsStatus = 'connected';
            $uspsLabel = 'Connected';
        } else {
            $uspsLabel = 'Pending approval';
            $uspsDetail .= ' · Platform approval still required for production labels';
        }
    }

    $hubCards = [
        [
            'title' => 'Where do you ship from?',
            'summary' => $setup['ship_from'] ?? [],
            'empty' => 'Add the warehouse or store that packs and ships orders.',
            'href' => route('settings.locations.index'),
            'cta' => 'Manage locations',
            'setup_href' => route('settings.delivery.setup.ship-from'),
        ],
        [
            'title' => 'Where do you deliver?',
            'summary' => $setup['delivery_areas'] ?? [],
            'empty' => 'Choose countries and regions customers can receive orders in.',
            'href' => route('shippingAutomation', ['tab' => 'areas']),
            'cta' => 'Manage areas',
            'setup_href' => route('settings.delivery.setup.deliver-to'),
        ],
        [
            'title' => 'What do customers see at checkout?',
            'summary' => $setup['delivery_options'] ?? [],
            'empty' => 'Offer FedEx live rates, fixed prices, free shipping, or a mix.',
            'href' => route('settings.delivery.setup.delivery-option'),
            'cta' => 'Manage checkout shipping',
            'setup_href' => route('settings.delivery.setup.delivery-option'),
            'extra_lines' => $checkoutSummaryLines,
        ],
    ];
@endphp

<section class="delivery-hub" aria-labelledby="delivery-console-title">
    <header class="delivery-hub-hero">
        <div class="delivery-hub-hero-copy">
            <p class="delivery-console-crumb">Settings · Delivery</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 id="delivery-console-title" class="delivery-console-title">Delivery setup</h2>
                <span @class(['delivery-hub-status', 'is-ready' => $isReady, 'is-pending' => ! $isReady])>
                    {{ $isReady ? 'Ready for checkout' : 'Setup in progress' }}
                </span>
            </div>
            <p class="delivery-console-lede">
                Manage ship-from locations, delivery areas, checkout delivery options, and optional delivery providers.
            </p>
        </div>
        @if ($canManageShipping ?? false)
            <div class="delivery-hub-hero-actions">
                <a href="{{ route('settings.delivery.test-address') }}" class="ui-btn ui-btn-secondary">Test checkout shipping</a>
                <x-ui.button :href="$isReady ? route('settings.delivery.setup.delivery-option') : route('settings.delivery.setup.ship-from')">
                    {{ $isReady ? 'Edit delivery setup' : 'Set up delivery' }}
                </x-ui.button>
            </div>
        @endif
    </header>

    @if ($healthItems->isNotEmpty())
        <aside class="delivery-hub-health" aria-label="Delivery health">
            <p class="delivery-hub-health-title">{{ $healthItems->contains(fn ($i) => ($i['severity'] ?? '') === 'error') ? 'Action needed' : 'Suggestions' }}</p>
            <ul class="delivery-hub-health-list">
                @foreach ($healthItems->take(3) as $item)
                    <li @class(['delivery-hub-health-item', 'is-error' => ($item['severity'] ?? '') === 'error'])>
                        <div>
                            <p class="font-semibold text-[color:var(--color-ink)]">{{ $item['label'] ?? 'Setup item' }}</p>
                            <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">{{ $item['message'] ?? '' }}</p>
                        </div>
                        @if ($canManageShipping ?? false)
                            @if (! empty($item['action_href']))
                                <a href="{{ $item['action_href'] }}" class="delivery-hub-link">{{ $item['action_label'] ?? 'Fix' }}</a>
                            @elseif (! empty($item['action_tab']))
                                <button type="button" data-shipping-tab="{{ $item['action_tab'] }}" class="delivery-hub-link">{{ $item['action_label'] ?? 'Fix' }}</button>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </aside>
    @endif

    <div class="delivery-hub-grid delivery-hub-grid-3">
        @foreach ($hubCards as $card)
            @php
                $summary = $card['summary'];
                $tone = $statusTone($summary);
            @endphp
            <article class="delivery-hub-card" data-tone="{{ $tone }}">
                <div class="delivery-hub-card-top">
                    <p class="delivery-hub-card-eyebrow">Configuration</p>
                    <span class="delivery-hub-card-badge tone-{{ $tone }}">{{ $statusLabel($tone) }}</span>
                </div>
                <h3 class="delivery-hub-card-title">{{ $card['title'] }}</h3>
                @if (! empty($summary['title']) && ($summary['title'] ?? '') !== 'Not configured')
                    <p class="delivery-hub-card-value">{{ $summary['title'] }}</p>
                @endif
                <p class="delivery-hub-card-detail">{{ $summary['detail'] ?? $card['empty'] }}</p>
                @foreach (($card['extra_lines'] ?? []) as $extra)
                    <p class="mt-1 text-xs text-[color:var(--color-ink-muted)]">{{ $extra }}</p>
                @endforeach
                @if ($canManageShipping ?? false)
                    <div class="delivery-hub-card-actions">
                        <a href="{{ $tone === 'missing' ? $card['setup_href'] : $card['href'] }}" class="delivery-hub-primary-link">{{ $card['cta'] }}</a>
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    @if (! $isReady && ($canManageShipping ?? false))
        <x-ui.empty-state
            title="Finish delivery setup"
            lead="Answer a few setup questions so customers can see delivery options at checkout."
            action-label="Start delivery setup"
            :action-href="route('settings.delivery.setup.ship-from')"
        />
    @endif

    <section class="delivery-hub-section" aria-labelledby="delivery-carriers-heading">
        <div class="delivery-section-head">
            <div>
                <h3 id="delivery-carriers-heading" class="delivery-section-title">Delivery providers</h3>
                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">External carriers for live rates and labels. Manual fixed/free shipping is configured under checkout shipping — not listed here as a connected carrier.</p>
            </div>
        </div>
        <div class="delivery-carrier-grid">
            <a href="{{ ($canManageShipping ?? false) ? $fedExHref : '#' }}" @class(['delivery-carrier-card', 'is-connected' => $fedExStatus === 'connected', 'is-setup' => $fedExStatus === 'setup', 'is-attention' => $fedExStatus === 'attention'])>
                <span class="delivery-carrier-logo">
                    @if (file_exists(public_path('assets/carriers/fedex/fedex-unified-logo.svg')))
                        <img src="{{ asset('assets/carriers/fedex/fedex-unified-logo.svg') }}" alt="">
                    @else
                        <span class="delivery-carrier-fallback">FX</span>
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-[color:var(--color-ink)]">FedEx</span>
                    <span class="mt-0.5 block text-xs text-[color:var(--color-ink-muted)]">{{ $fedExDetail }}</span>
                </span>
                <span @class(['delivery-status-pill', 'is-connected' => $fedExStatus === 'connected', 'is-setup' => $fedExStatus === 'setup', 'is-attention' => $fedExStatus === 'attention'])>
                    <span class="delivery-status-dot" aria-hidden="true"></span>{{ strtoupper($fedExLabel) }}
                </span>
            </a>
            <a href="{{ ($canManageShipping ?? false) ? $uspsHref : '#' }}" @class(['delivery-carrier-card', 'is-connected' => $uspsStatus === 'connected', 'is-setup' => $uspsStatus !== 'connected'])>
                <span class="delivery-carrier-logo"><span class="delivery-carrier-fallback">USPS</span></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-[color:var(--color-ink)]">USPS</span>
                    <span class="mt-0.5 block text-xs text-[color:var(--color-ink-muted)]">{{ $uspsDetail }}</span>
                </span>
                <span @class(['delivery-status-pill', 'is-connected' => $uspsStatus === 'connected', 'is-setup' => $uspsStatus !== 'connected'])>
                    <span class="delivery-status-dot" aria-hidden="true"></span>{{ strtoupper($uspsLabel) }}
                </span>
            </a>
            <div class="delivery-carrier-card is-setup" aria-disabled="true">
                <span class="delivery-carrier-logo"><span class="delivery-carrier-fallback">DHL</span></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-[color:var(--color-ink)]">DHL</span>
                    <span class="mt-0.5 block text-xs text-[color:var(--color-ink-muted)]">Coming later — production integration is not available yet.</span>
                </span>
                <span class="delivery-status-pill is-setup">
                    <span class="delivery-status-dot" aria-hidden="true"></span>COMING LATER
                </span>
            </div>
        </div>
    </section>

    <section class="delivery-hub-section" aria-labelledby="delivery-locations-heading">
        <div class="delivery-section-head">
            <div>
                <h3 id="delivery-locations-heading" class="delivery-section-title">Ship-from locations</h3>
                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Fulfillment locations</p>
            </div>
            <a href="{{ route('settings.locations.index') }}" class="ui-btn ui-btn-secondary">Manage locations</a>
        </div>
        <div class="space-y-3">
            @forelse ($locationsList->take(3) as $location)
                @php
                    $readiness = $originReadiness[$location->id] ?? null;
                @endphp
                <article class="delivery-location-card">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-[color:var(--color-ink)]">{{ $location->name }}</p>
                                @if ($location->is_default)<span class="delivery-tag-default">Default origin</span>@endif
                            </div>
                            <p class="mt-1.5 text-sm text-[color:var(--color-ink-muted)]">
                                {{ $readiness?->displayAddress ?: collect([$location->address_line1, $location->city, $location->state, $location->postal_code, $location->country_code])->filter()->implode(', ') }}
                            </p>
                        </div>
                        @if ($readiness)
                            <span @class(['delivery-ready-chip', 'is-ready' => $readiness->ready, 'is-blocked' => ! $readiness->ready])>{{ $readiness->badgeLabel }}</span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="delivery-empty-field text-center">No fulfillment locations yet.</div>
            @endforelse
        </div>
    </section>

    <section class="delivery-hub-section" aria-labelledby="delivery-packages-heading">
        <div class="delivery-section-head">
            <div>
                <h3 id="delivery-packages-heading" class="delivery-section-title">Package sizes</h3>
                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Default boxes for carrier rates when products do not list dimensions</p>
            </div>
            <a href="{{ route('shippingAutomation', ['tab' => 'packages']) }}" class="ui-btn ui-btn-secondary">Manage packages</a>
        </div>
        @php
            $packagePresetsList = collect($packagePresets ?? []);
            $defaultPreset = $packagePresetsList->firstWhere('is_default', true) ?? $packagePresetsList->first();
        @endphp
        @if ($defaultPreset)
            <article class="delivery-location-card">
                <p class="font-semibold text-[color:var(--color-ink)]">{{ $defaultPreset->name }}</p>
                <p class="mt-1.5 text-sm text-[color:var(--color-ink-muted)]">
                    {{ number_format((float) $defaultPreset->length, 1) }}
                    × {{ number_format((float) $defaultPreset->width, 1) }}
                    × {{ number_format((float) $defaultPreset->height, 1) }}
                    {{ $defaultPreset->dimension_unit ?: 'IN' }}
                    @if ($defaultPreset->is_default) · Default @endif
                </p>
            </article>
        @else
            <div class="delivery-empty-field text-center">No default package size yet. Add one so FedEx live rates can use real dimensions.</div>
        @endif
    </section>

    <div class="delivery-advanced-row">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#f1f5f9] text-[color:var(--color-ink-muted)]" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h10M4 17h13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </span>
            <div>
                <p class="font-semibold text-[color:var(--color-ink)]">Advanced settings</p>
                <p class="text-xs text-[color:var(--color-ink-muted)]">Legacy tables and edge configuration — not for normal FedEx checkout setup</p>
            </div>
        </div>
        <button type="button" class="delivery-toggle" x-bind:class="advancedOpen && 'is-on'" x-bind:aria-pressed="advancedOpen.toString()" x-on:click="advancedOpen = !advancedOpen; persist();" aria-controls="delivery-advanced-panel" aria-label="Toggle advanced delivery settings"></button>
    </div>

    <p class="text-sm text-[color:var(--color-ink-muted)]">
        Tax is configured separately in
        <a href="{{ route('settings.taxes.index') }}" class="font-semibold text-[color:var(--color-brand)] hover:underline">Checkout &amp; tax</a>.
    </p>
</section>
