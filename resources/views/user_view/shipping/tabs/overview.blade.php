@php
    $setup = $deliverySetup ?? [];
    $healthItems = collect($setup['health_items'] ?? []);
    $isReady = (bool) ($setup['is_ready'] ?? false);
    $locationsList = collect($locations ?? []);
    $originReadiness = $originReadinessByLocationId ?? [];

    $stepDefs = [
        [
            'key' => 'ship_from',
            'title' => 'Source location',
            'question' => 'Where do you ship from?',
            'hint' => 'This sets your default ship-from origin for rates and labels.',
            'cta' => 'Continue to delivery areas',
            'href' => route('settings.delivery.setup.ship-from'),
        ],
        [
            'key' => 'delivery_areas',
            'title' => 'Delivery areas',
            'question' => 'Where do you deliver?',
            'hint' => 'Choose countries and regions customers can ship to.',
            'cta' => 'Continue to delivery options',
            'href' => route('settings.delivery.setup.deliver-to'),
        ],
        [
            'key' => 'delivery_options',
            'title' => 'Checkout options',
            'question' => 'What do customers see at checkout?',
            'hint' => 'Add the delivery choices shoppers pick during checkout.',
            'cta' => 'Continue to delivery providers',
            'href' => route('settings.delivery.setup.delivery-option'),
        ],
        [
            'key' => 'delivery_providers',
            'title' => 'Delivery providers',
            'question' => 'Connect a delivery provider',
            'hint' => 'Optional — connect FedEx or USPS when you are ready for labels.',
            'cta' => 'Connect delivery provider',
            'href' => route('shipping.carriers.connect.index'),
        ],
    ];

    $stepComplete = static function (array $summary): bool {
        return in_array(($summary['status'] ?? ''), ['complete', 'added', 'included', 'optional', 'off'], true);
    };

    $currentIndex = 0;
    foreach ($stepDefs as $i => $def) {
        $summary = $setup[$def['key']] ?? [];
        if (! $stepComplete($summary)) {
            $currentIndex = $i;
            break;
        }
        $currentIndex = min($i + 1, count($stepDefs) - 1);
    }
    if ($isReady) {
        $currentIndex = count($stepDefs) - 1;
    }

    $currentStep = $stepDefs[$currentIndex];
    $currentSummary = $setup[$currentStep['key']] ?? [];

    $primaryHref = $isReady
        ? route('shippingAutomation', ['tab' => 'areas'])
        : $currentStep['href'];
    $primaryLabel = $isReady ? 'Review delivery areas' : $currentStep['cta'];

    $fedExAccount = ($fedExAccounts ?? collect())->first();
    $uspsAccount = ($uspsMerchantAccounts ?? collect())->first();

    $fedExStatus = 'setup';
    $fedExLabel = 'Setup required';
    $fedExHref = ($fedExConfig->modelAEnabled() ?? false)
        ? route('settings.shipping.fedex-integrator.start')
        : route('shipping.carriers.connect.show', 'fedex');
    if ($fedExAccount) {
        $fedExHref = route('shippingAutomation', ['tab' => 'advanced']);
        if (in_array($fedExAccount->connection_status, ['connected', 'sandbox_platform_fallback'], true)) {
            $fedExStatus = 'connected';
            $fedExLabel = 'Connected';
        } elseif (in_array($fedExAccount->connection_status, ['failed', 'blocked_by_fedex'], true)) {
            $fedExStatus = 'attention';
            $fedExLabel = 'Needs attention';
        }
    }

    $uspsStatus = 'setup';
    $uspsLabel = 'Setup required';
    $uspsHref = ($uspsMerchantConnectionEnabled ?? false)
        ? route('settings.shipping.usps-merchant.start')
        : route('shipping.carriers.connect.index');
    if ($uspsAccount) {
        $uspsHref = route('settings.shipping.usps-merchant.manage', $uspsAccount);
        if ($uspsAccount->usps_authorization_status === \App\Models\CarrierAccount::USPS_AUTH_CONNECTED
            || $uspsAccount->connection_status === 'connected') {
            $uspsStatus = 'connected';
            $uspsLabel = 'Connected';
        }
    }
@endphp

<section class="delivery-console" aria-labelledby="delivery-console-title">
    <div class="delivery-console-header">
        <div>
            <p class="delivery-console-crumb">Settings · Delivery</p>
            <h2 id="delivery-console-title" class="delivery-console-title">Delivery setup</h2>
            <p class="delivery-console-lede">
                Manage ship-from locations, delivery areas, checkout delivery options, and optional delivery providers.
            </p>
        </div>
        @if ($canManageShipping ?? false)
            <div class="flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('settings.delivery.test-address') }}" class="ui-btn ui-btn-secondary">
                    Test a customer address
                </a>
                @if (! $isReady)
                    <x-ui.button :href="$primaryHref">
                        Set up delivery
                    </x-ui.button>
                @else
                    <x-ui.button :href="route('shippingAutomation', ['tab' => 'areas'])">
                        + Add delivery area
                    </x-ui.button>
                @endif
            </div>
        @endif
    </div>

    @foreach ($healthItems as $item)
        @php $isError = ($item['severity'] ?? 'warning') === 'error'; @endphp
        <div @class(['settings-alert', 'settings-alert-error' => $isError])>
            <strong>{{ $item['label'] ?? 'Setup item' }}:</strong> {{ $item['message'] ?? '' }}
            @if ($canManageShipping ?? false)
                @if (! empty($item['action_href']))
                    <a href="{{ $item['action_href'] }}" class="ml-2 font-semibold underline">{{ $item['action_label'] ?? 'Fix' }}</a>
                @elseif (! empty($item['action_tab']))
                    <button type="button" data-shipping-tab="{{ $item['action_tab'] }}" class="ml-2 font-semibold underline">{{ $item['action_label'] ?? 'Fix' }}</button>
                @endif
            @endif
        </div>
    @endforeach

    {{-- Setup progress card --}}
    <article class="delivery-setup-card">
        <div class="delivery-setup-top">
            <div class="delivery-setup-identity">
                <span @class([
                    'delivery-setup-badge',
                    'delivery-setup-badge-ready' => $isReady,
                ]) aria-hidden="true">{{ $isReady ? '✓' : ($currentIndex + 1) }}</span>
                <div>
                    <p class="delivery-setup-kicker">
                        @if ($isReady)
                            Delivery ready
                        @else
                            Finish delivery setup
                        @endif
                    </p>
                    <p class="delivery-setup-sub">
                        @if ($isReady)
                            All required delivery steps are complete.
                        @else
                            Step {{ $currentIndex + 1 }} of {{ count($stepDefs) }}: {{ $currentStep['title'] }}
                        @endif
                    </p>
                </div>
            </div>
            <div class="delivery-progress" role="img" aria-label="Delivery setup progress {{ min($currentIndex + 1, count($stepDefs)) }} of {{ count($stepDefs) }}">
                @foreach ($stepDefs as $i => $def)
                    <span @class([
                        'delivery-progress-seg',
                        'is-done' => $i < $currentIndex || ($isReady && $i <= $currentIndex),
                        'is-on' => ! $isReady && $i === $currentIndex,
                    ])></span>
                @endforeach
            </div>
        </div>

        <label class="delivery-field-label" for="delivery-setup-focus">
            {{ $isReady ? 'Where do you ship from?' : $currentStep['question'] }}
        </label>

        @if (($currentStep['key'] === 'ship_from' || $isReady) && $locationsList->isNotEmpty())
            <div class="delivery-select-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 22s7-5.33 7-12a7 7 0 10-14 0c0 6.67 7 12 7 12z" stroke="currentColor" stroke-width="1.8"/>
                    <circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                </svg>
                <select id="delivery-setup-focus" class="delivery-select" disabled>
                    @foreach ($locationsList as $location)
                        <option value="{{ $location->id }}" @selected((bool) $location->is_default)>
                            {{ $location->name }}@if ($location->city) — {{ $location->city }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <p class="mt-2 text-xs text-[color:var(--color-ink-muted)]">
                Preview of your ship-from locations.
                <a href="{{ route('settings.locations.index') }}" class="font-semibold text-[color:var(--color-brand)] hover:underline">Manage locations</a>
                · continue below to finish Delivery setup.
            </p>
        @else
            <div class="delivery-empty-field">
                {{ $currentSummary['detail'] ?? 'Not configured yet.' }}
            </div>
        @endif

        <div class="delivery-setup-footer">
            <p class="delivery-setup-hint">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="shrink-0 text-[color:var(--color-brand)]">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M12 11v5M12 8h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                <span>{{ $currentStep['hint'] }}</span>
            </p>
            @if ($canManageShipping ?? false)
                <x-ui.button :href="$primaryHref">
                    {{ $primaryLabel }}
                    <span aria-hidden="true">→</span>
                </x-ui.button>
            @endif
        </div>

        <div class="delivery-mini-checks">
            <div>
                <strong>Where do you deliver?</strong>
                <span>{{ data_get($setup, 'delivery_areas.detail', 'Not configured yet.') }}</span>
            </div>
            <div>
                <strong>Connect delivery provider</strong>
                <span>{{ data_get($setup, 'delivery_providers.detail', 'Optional') }}</span>
            </div>
        </div>
    </article>

    @if (! $isReady && ($canManageShipping ?? false))
        <x-ui.empty-state
            title="Finish delivery setup"
            lead="Answer a few setup questions so customers can see delivery options at checkout."
            action-label="Start delivery setup"
            :action-href="route('settings.delivery.setup.ship-from')"
        />
    @endif

    {{-- Shipping Carriers --}}
    <section aria-labelledby="delivery-carriers-heading">
        <div class="delivery-section-head">
            <h3 id="delivery-carriers-heading" class="delivery-section-title">Shipping Carriers</h3>
            @if ($canManageShipping ?? false)
                <a href="{{ route('shipping.carriers.connect.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-[color:var(--color-brand)] hover:underline">
                    Explore more carriers <span aria-hidden="true">↗</span>
                </a>
            @endif
        </div>

        <div class="delivery-carrier-grid">
            <a
                href="{{ ($canManageShipping ?? false) ? $fedExHref : '#' }}"
                @class(['delivery-carrier-card', 'is-connected' => $fedExStatus === 'connected', 'is-setup' => $fedExStatus !== 'connected'])
            >
                <span class="delivery-carrier-logo">
                    @if (file_exists(public_path('assets/carriers/fedex/fedex-unified-logo.svg')))
                        <img src="{{ asset('assets/carriers/fedex/fedex-unified-logo.svg') }}" alt="">
                    @else
                        <span class="delivery-carrier-fallback">FX</span>
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-[color:var(--color-ink)]">FedEx</span>
                    <span class="mt-0.5 block text-xs text-[color:var(--color-ink-muted)]">
                        {{ $fedExAccount?->display_name ?? 'Connect your merchant-owned FedEx account' }}
                    </span>
                </span>
                <span @class(['delivery-status-pill', 'is-connected' => $fedExStatus === 'connected', 'is-setup' => $fedExStatus === 'setup', 'is-attention' => $fedExStatus === 'attention'])>
                    <span class="delivery-status-dot" aria-hidden="true"></span>
                    {{ strtoupper($fedExLabel) }}
                </span>
            </a>

            <a
                href="{{ ($canManageShipping ?? false) ? $uspsHref : '#' }}"
                @class(['delivery-carrier-card', 'is-connected' => $uspsStatus === 'connected', 'is-setup' => $uspsStatus !== 'connected'])
            >
                <span class="delivery-carrier-logo">
                    <span class="delivery-carrier-fallback">USPS</span>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-[color:var(--color-ink)]">USPS</span>
                    <span class="mt-0.5 block text-xs text-[color:var(--color-ink-muted)]">
                        {{ $uspsAccount?->display_name ?? 'Authorize your USPS postage account' }}
                    </span>
                </span>
                <span @class(['delivery-status-pill', 'is-connected' => $uspsStatus === 'connected', 'is-setup' => $uspsStatus !== 'connected'])>
                    <span class="delivery-status-dot" aria-hidden="true"></span>
                    {{ strtoupper($uspsLabel) }}
                </span>
            </a>
        </div>
    </section>

    {{-- Ship-from locations (always visible on Delivery home) --}}
    <section aria-labelledby="delivery-locations-heading">
        <div class="delivery-section-head">
            <div>
                <h3 id="delivery-locations-heading" class="delivery-section-title">Ship-from Locations</h3>
                <p class="mt-0.5 text-sm text-[color:var(--color-ink-muted)]">Fulfillment locations</p>
            </div>
            <a href="{{ route('settings.locations.index') }}" class="ui-btn ui-btn-secondary">Manage locations</a>
        </div>

        <div class="space-y-3">
            @forelse ($locationsList as $location)
                @php($readiness = $originReadiness[$location->id] ?? null)
                <article class="delivery-location-card">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-[color:var(--color-ink)]">{{ $location->name }}</p>
                                @if ($location->is_default)
                                    <span class="delivery-tag-default">Default origin</span>
                                @endif
                            </div>
                            <p class="mt-1.5 text-sm text-[color:var(--color-ink-muted)]">
                                {{ $readiness?->displayAddress ?: collect([$location->address_line1, $location->city, $location->state, $location->postal_code, $location->country_code])->filter()->implode(', ') }}
                            </p>
                            @if ($readiness && ! $readiness->ready)
                                <p class="mt-1 text-xs text-[color:var(--color-warning)]">{{ $readiness->merchantMessage }}</p>
                            @endif
                        </div>
                        @if ($readiness)
                            <span @class(['delivery-ready-chip', 'is-ready' => $readiness->ready, 'is-blocked' => ! $readiness->ready])>
                                {{ $readiness->badgeLabel }}
                            </span>
                        @endif
                    </div>
                </article>
            @empty
                <div class="delivery-empty-field text-center">No fulfillment locations yet.</div>
            @endforelse
        </div>
    </section>

    {{-- Advanced toggle (matches mock progressive disclosure) --}}
    <div class="delivery-advanced-row">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-[#f1f5f9] text-[color:var(--color-ink-muted)]" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h10M4 17h13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </span>
            <div>
                <p class="font-semibold text-[color:var(--color-ink)]">Advanced Settings</p>
                <p class="text-xs text-[color:var(--color-ink-muted)]">Delivery areas, checkout options, and carrier tools</p>
            </div>
        </div>
        <button
            type="button"
            class="delivery-toggle"
            x-bind:class="advancedOpen && 'is-on'"
            x-bind:aria-pressed="advancedOpen.toString()"
            @click="advancedOpen = !advancedOpen; persist();"
            aria-controls="delivery-advanced-panel"
            aria-label="Toggle advanced delivery settings"
        ></button>
    </div>

    <p class="text-sm text-[color:var(--color-ink-muted)]">
        Tax is configured separately in
        <a href="{{ route('settings.taxes.index') }}" class="font-semibold text-[color:var(--color-brand)] hover:underline">Checkout &amp; tax</a>.
    </p>
</section>
