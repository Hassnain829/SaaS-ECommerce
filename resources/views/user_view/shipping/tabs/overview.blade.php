@php
    $setup = $deliverySetup ?? [];
    $healthItems = collect($setup['health_items'] ?? []);
    $isReady = (bool) ($setup['is_ready'] ?? false);
    $storeRef = $selectedStore ?? $currentStore ?? null;
    $hasCompletedSetup = $storeRef?->delivery_setup_completed_at !== null;
    $hasErrors = $healthItems->contains(fn ($i) => ($i['severity'] ?? '') === 'error');
    $statusBadgeLabel = $isReady
        ? 'Ready'
        : ($hasCompletedSetup ? 'Needs attention' : 'Setup in progress');
    $showCompactRecommendations = $isReady && ! $hasErrors && $healthItems->isNotEmpty();
    $showAttentionHealth = $healthItems->isNotEmpty() && (! $isReady || $hasErrors);

    $locationsList = collect($locations ?? []);
    $allZones = collect($shippingZones ?? [])->values();
    $methods = collect($shippingMethods ?? []);
    $zonePresenter = app(\App\Services\Delivery\DeliveryAreaInputNormalizer::class);
    $currency = $storeRef->currency ?? 'USD';

    $fedExLiveMethods = $methods->filter(
        fn ($m) => method_exists($m, 'isFedExLiveRateMethod')
            && $m->isFedExLiveRateMethod()
            && ($m->is_active || $m->enabled_for_checkout)
    );
    $orphanMethods = $methods->filter(function ($m) {
        if (! ($m->is_active || $m->enabled_for_checkout)) {
            return false;
        }

        return method_exists($m, 'isOrphanedFromArea')
            ? $m->isOrphanedFromArea()
            : ($m->shipping_zone_id === null || ! $m->shippingZone);
    })->values();

    $fedExAccount = ($fedExAccounts ?? collect())->first(
        fn ($account) => $account->usesFedExIntegratorProvider()
            && $account->disconnected_at === null
            && $account->replaced_at === null
    ) ?? ($fedExAccounts ?? collect())->first();

    $fedExStatus = 'setup';
    $fedExLabel = 'Not connected';
    $fedExDetail = 'Connect your FedEx account to offer live rates and create labels with your own account.';
    $fedExHref = ($fedExConfig->modelAEnabled() ?? false)
        ? route('settings.shipping.fedex-integrator.start')
        : route('shipping.carriers.connect.show', 'fedex');
    $fedExCaps = ['checkout' => false, 'labels' => false, 'tracking' => false];
    $fedExConnected = false;

    if ($fedExAccount) {
        $fedExHref = $fedExAccount->usesFedExIntegratorProvider()
            ? route('settings.shipping.fedex-integrator.manage', $fedExAccount)
            : route('shippingAutomation');
        $fedExDetail = 'Account '.$fedExAccount->maskedAccountNumber();
        $caps = is_array($fedExAccount->capabilities) ? $fedExAccount->capabilities : [];
        $checkoutPlatformOn = (bool) config('carriers.fedex.checkout_rates_enabled', false);
        $fedExCaps['checkout'] = $checkoutPlatformOn
            && (bool) $fedExAccount->enabled_for_checkout
            && (bool) ($caps['checkout_rates'] ?? false)
            && $fedExLiveMethods->isNotEmpty();
        $fedExCaps['labels'] = (bool) ($caps['labels'] ?? false);
        $fedExCaps['tracking'] = (bool) ($caps['tracking'] ?? true);
        $fedExConnected = in_array($fedExAccount->connection_status, ['connected', 'sandbox_platform_fallback'], true);

        if ($fedExConnected) {
            $fedExStatus = $fedExCaps['checkout'] ? 'connected' : 'attention';
            $fedExLabel = 'Connected';
        } elseif (in_array($fedExAccount->connection_status, ['failed', 'blocked_by_fedex'], true)) {
            $fedExStatus = 'attention';
            $fedExLabel = 'Needs attention';
        }
    }

    $showPackages = $fedExLiveMethods->isNotEmpty()
        || ($fedExConnected && ($fedExCaps['labels'] || $fedExCaps['checkout']));

    $defaultLocation = $locationsList->firstWhere('is_default', true)
        ?? $locationsList->firstWhere('is_active', true)
        ?? $locationsList->first();
    $originComplete = $defaultLocation
        && filled($defaultLocation->address_line1)
        && filled($defaultLocation->city)
        && filled($defaultLocation->country_code);
    $originAddress = $defaultLocation
        ? collect([
            $defaultLocation->address_line1,
            $defaultLocation->city,
            $defaultLocation->state,
            $defaultLocation->postal_code,
            $defaultLocation->country_code,
        ])->filter()->implode(', ')
        : null;

    $methodPriceLabel = static function ($method) use ($currency): string {
        if (method_exists($method, 'isFedExLiveRateMethod') && $method->isFedExLiveRateMethod()) {
            return 'Live rates';
        }
        if ($method->rate_type === \App\Models\ShippingMethod::RATE_FREE) {
            return 'Free';
        }
        if ((float) ($method->free_over_amount ?? 0) > 0) {
            return 'Free > '.$currency.' '.number_format((float) $method->free_over_amount, 2);
        }
        if ((float) ($method->flat_rate ?? 0) > 0) {
            return $currency.' '.number_format((float) $method->flat_rate, 2);
        }

        return 'Fixed';
    };

    $methodDaysLabel = static function ($method): string {
        if ($method->estimated_min_days !== null && $method->estimated_max_days !== null) {
            return $method->estimated_min_days.'–'.$method->estimated_max_days.' days';
        }

        return $method->delivery_speed_label ?: '—';
    };

    $packagePresetsList = collect($packagePresets ?? []);
    $defaultPreset = $packagePresetsList->firstWhere('is_default', true) ?? $packagePresetsList->first();
    $primaryRecommendation = $healthItems->first();
@endphp

<section class="dh w-full max-w-none" aria-label="Delivery settings">
    {{-- Status strip (topbar already says Delivery) --}}
    <header class="dh-status-bar">
        <span @class(['dh-badge', 'is-ready' => $isReady, 'is-pending' => ! $isReady])>
            {{ $statusBadgeLabel }}
        </span>
        @if ($canManageShipping ?? false)
            @unless ($hasCompletedSetup)
                <a href="{{ route('settings.delivery.setup') }}" class="dh-btn dh-btn-primary">Set up delivery</a>
            @else
                <button type="button" data-open-drawer="method-add" class="dh-btn dh-btn-primary" @if ($allZones->isEmpty()) disabled @endif>
                    Add delivery option
                </button>
            @endunless
        @endif
    </header>

    @if ($showCompactRecommendations && $primaryRecommendation)
        <aside class="dh-reco" aria-label="Delivery recommendations">
            <div class="dh-reco-copy">
                <p class="dh-reco-count">
                    {{ $healthItems->count() }} {{ $healthItems->count() === 1 ? 'recommendation' : 'recommendations' }}
                </p>
                <p class="dh-reco-msg">{{ $primaryRecommendation['message'] ?? ($primaryRecommendation['label'] ?? '') }}</p>
            </div>
            @if (($canManageShipping ?? false) && ! empty($primaryRecommendation['action_href']))
                <a href="{{ $primaryRecommendation['action_href'] }}" class="dh-link">
                    {{ $primaryRecommendation['action_label'] ?? 'Review' }} →
                </a>
            @endif
        </aside>
    @elseif ($showAttentionHealth)
        <aside class="dh-alert" aria-label="Delivery needs attention">
            <p class="dh-alert-title">{{ $isReady ? 'Recommended improvements' : ($hasCompletedSetup ? 'Needs attention' : 'Action needed') }}</p>
            <ul class="dh-alert-list">
                @foreach ($healthItems->take(3) as $item)
                    <li class="dh-alert-item">
                        <div>
                            <p class="dh-alert-item-title">{{ $item['label'] ?? 'Setup item' }}</p>
                            <p class="dh-alert-item-msg">{{ $item['message'] ?? '' }}</p>
                        </div>
                        @if (($canManageShipping ?? false) && ! empty($item['action_href']))
                            <a href="{{ $item['action_href'] }}" class="dh-link">{{ $item['action_label'] ?? 'Fix' }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </aside>
    @endif

    @if ($orphanMethods->isNotEmpty() && ($canManageShipping ?? false))
        <div class="dh-orphan">
            <p>{{ $orphanMethods->count() }} unused delivery {{ $orphanMethods->count() === 1 ? 'option is' : 'options are' }} not linked to a delivery area.</p>
            <form method="POST" action="{{ route('settings.shipping.methods.cleanup-orphans') }}" onsubmit="return confirm('Remove all unused delivery options that are not linked to a delivery area?')">
                @csrf
                <button type="submit" class="dh-btn dh-btn-ghost">Remove unused options</button>
            </form>
        </div>
    @endif

    @if (! $isReady && ! $hasCompletedSetup && ($canManageShipping ?? false))
        <x-ui.empty-state
            title="Delivery isn’t set up yet"
            lead="Set where you ship from, where you deliver, and what customers see at checkout."
            action-label="Set up delivery"
            :action-href="route('settings.delivery.setup')"
        />
    @endif

    {{-- Shipping origin --}}
    <section id="shipping-origin" class="dh-block">
        <h3 class="dh-block-title">Shipping origin</h3>
        @if ($defaultLocation)
            <article class="dh-panel">
                <div class="dh-panel-row">
                    <div class="min-w-0">
                        <div class="dh-panel-title-row">
                            <p class="dh-panel-title">{{ $defaultLocation->name }}</p>
                            <span @class(['dh-chip', 'is-ready' => $originComplete, 'is-blocked' => ! $originComplete])>
                                {{ $originComplete ? 'Ready' : 'Needs attention' }}
                            </span>
                        </div>
                        <p class="dh-panel-meta">{{ $originAddress ?: 'Address incomplete' }}</p>
                    </div>
                    <a href="{{ route('settings.locations.index') }}" class="dh-link">Manage locations</a>
                </div>
            </article>
        @else
            <div class="dh-empty">Add a ship-from location to continue.</div>
        @endif
    </section>

    {{-- Delivery areas & options --}}
    <section id="delivery-areas" class="dh-block">
        <div class="dh-block-head">
            <h3 class="dh-block-title">Delivery areas &amp; options</h3>
            @if ($canManageShipping ?? false)
                @if ($hasCompletedSetup)
                    <button type="button" data-open-drawer="zone-add" class="dh-btn dh-btn-ghost">Add delivery area</button>
                @else
                    <a href="{{ route('settings.delivery.setup.deliver-to') }}" class="dh-btn dh-btn-ghost">Add delivery area</a>
                @endif
            @endif
        </div>

        <div class="dh-stack">
            @forelse ($allZones as $zone)
                @php
                    $zoneMethods = $methods
                        ->where('shipping_zone_id', $zone->id)
                        ->filter(fn ($m) => $m->is_active || $m->enabled_for_checkout)
                        ->values();
                    $zoneFedExMethods = $zoneMethods->filter(
                        fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()
                    )->values();
                    $zoneFixedMethods = $zoneMethods->reject(
                        fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()
                    )->values();
                    $regionCount = collect($zone->regions)->filter()->count();
                    $coverage = $regionCount > 0
                        ? $regionCount.' '.($regionCount === 1 ? 'state' : 'states')
                        : (collect($zone->countries)->filter()->implode(', ') ?: 'No country set');
                    $fedExManageHref = $hasCompletedSetup
                        ? route('settings.delivery.checkout-options', ['shipping_zone_id' => $zone->id])
                        : route('settings.delivery.setup.delivery-option', ['shipping_zone_id' => $zone->id]);
                    $fedExCheckoutAvailable = $zone->is_active
                        && $zoneFedExMethods->contains(fn ($m) => $m->is_active && $m->enabled_for_checkout);
                @endphp
                <article class="dh-panel">
                    <div class="dh-panel-row">
                        <div class="min-w-0">
                            <div class="dh-panel-title-row">
                                <p class="dh-panel-title">{{ $zone->name }}</p>
                                <span @class(['dh-chip', 'is-ready' => $zone->is_active, 'is-muted' => ! $zone->is_active])>
                                    {{ $zone->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <p class="dh-panel-meta">{{ $coverage }}</p>
                        </div>
                        @if ($canManageShipping ?? false)
                            <div class="dh-actions">
                                @if ($hasCompletedSetup)
                                    <button type="button" class="zone-edit-btn dh-btn dh-btn-ghost"
                                        data-action="{{ route('settings.shipping.zones.update', $zone) }}"
                                        data-zone-form="{{ e(json_encode($zonePresenter->presentationFromZone($zone))) }}">Edit</button>
                                @else
                                    <a href="{{ route('settings.delivery.setup.deliver-to', ['shipping_zone_id' => $zone->id]) }}" class="dh-btn dh-btn-ghost">Edit</a>
                                @endif
                                <details class="dh-menu">
                                    <summary class="dh-menu-trigger" aria-label="More actions for {{ $zone->name }}">⋯</summary>
                                    <div class="dh-menu-panel">
                                        <form method="POST" action="{{ route('settings.shipping.zones.destroy', $zone) }}" onsubmit="return confirm('Remove “{{ $zone->name }}” and its checkout options? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dh-menu-danger">Remove</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        @endif
                    </div>

                    <div class="dh-options">
                        @if ($zoneFedExMethods->isEmpty() && $zoneFixedMethods->isEmpty())
                            <p class="dh-empty-inline">No checkout options in this area yet.</p>
                        @else
                            @foreach ($zoneFixedMethods as $method)
                                @php
                                    $priceMode = $method->rate_type === 'free'
                                        ? 'free'
                                        : ((float) ($method->free_over_amount ?? 0) > 0 ? 'free_over' : 'fixed');
                                    $flagMismatch = $method->is_active !== $method->enabled_for_checkout;
                                @endphp
                                <div class="dh-option-row">
                                    <div class="dh-option-main">
                                        <span class="dh-option-name">{{ $method->name }}</span>
                                        <span class="dh-option-meta">
                                            <span>{{ $methodPriceLabel($method) }}</span>
                                            <span class="dh-dot" aria-hidden="true">·</span>
                                            <span>{{ $methodDaysLabel($method) }}</span>
                                        </span>
                                    </div>
                                    @if ($canManageShipping ?? false)
                                        <details class="dh-menu">
                                            <summary class="dh-menu-trigger" aria-label="More actions for {{ $method->name }}">⋯</summary>
                                            <div class="dh-menu-panel">
                                                <button type="button" class="method-edit-btn dh-menu-item"
                                                    data-action="{{ route('settings.shipping.methods.update', $method) }}"
                                                    data-name="{{ $method->name }}"
                                                    data-zone="{{ $method->shipping_zone_id }}"
                                                    data-carrier="{{ $method->carrier_account_id }}"
                                                    data-rate-type="{{ $method->rate_type }}"
                                                    data-price-mode="{{ $priceMode }}"
                                                    data-label="{{ $method->delivery_speed_label }}"
                                                    data-flat="{{ $method->flat_rate }}"
                                                    data-free-over="{{ $method->free_over_amount }}"
                                                    data-min-order="{{ $method->min_order_amount }}"
                                                    data-max-order="{{ $method->max_order_amount }}"
                                                    data-min-days="{{ $method->estimated_min_days }}"
                                                    data-max-days="{{ $method->estimated_max_days }}"
                                                    data-description="{{ $method->description }}"
                                                    data-sort="{{ $method->sort_order }}"
                                                    data-checkout="{{ $method->enabled_for_checkout ? '1' : '0' }}"
                                                    data-active="{{ $method->is_active ? '1' : '0' }}"
                                                    data-flag-mismatch="{{ $flagMismatch ? '1' : '0' }}">Edit</button>
                                                <form method="POST" action="{{ route('settings.shipping.methods.destroy', $method) }}" onsubmit="return confirm('Remove “{{ $method->name }}”? Customers will no longer see this option at checkout.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dh-menu-danger">Remove</button>
                                                </form>
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            @endforeach

                            @if ($zoneFedExMethods->isNotEmpty())
                                <div class="dh-option-row">
                                    <div class="dh-option-main">
                                        <span class="dh-option-name">FedEx live rates</span>
                                        <span class="dh-option-meta">
                                            <span>{{ $zoneFedExMethods->count() }} {{ $zoneFedExMethods->count() === 1 ? 'service' : 'services' }}</span>
                                            <span class="dh-dot" aria-hidden="true">·</span>
                                            <span>
                                                @if (! $zone->is_active)
                                                    Unavailable
                                                @elseif ($fedExCheckoutAvailable)
                                                    Live rates
                                                @else
                                                    Hidden
                                                @endif
                                            </span>
                                        </span>
                                    </div>
                                    @if ($canManageShipping ?? false)
                                        <a href="{{ $fedExManageHref }}" class="dh-link">Manage</a>
                                    @endif
                                </div>
                            @endif
                        @endif

                        @if (($canManageShipping ?? false) && $hasCompletedSetup)
                            <button type="button" data-open-drawer="method-add" data-zone-id="{{ $zone->id }}" class="dh-add-option">+ Add delivery option</button>
                        @elseif (($canManageShipping ?? false) && ! $hasCompletedSetup)
                            <a href="{{ route('settings.delivery.setup.delivery-option', ['shipping_zone_id' => $zone->id]) }}" class="dh-add-option">+ Add delivery option</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="dh-empty">No delivery areas yet.</div>
            @endforelse
        </div>
    </section>

    {{-- FedEx --}}
    <section id="delivery-fedex" class="dh-block">
        <div class="dh-block-head">
            <h3 class="dh-block-title">FedEx</h3>
        </div>
        <article @class(['dh-fedex', 'is-connected' => $fedExStatus === 'connected', 'is-setup' => $fedExStatus === 'setup', 'is-attention' => $fedExStatus === 'attention'])>
            <div class="dh-fedex-top">
                <div class="dh-fedex-brand">
                    <span class="dh-fedex-logo" aria-hidden="true">
                        @if (file_exists(public_path('assets/carriers/fedex/fedex-unified-logo.svg')))
                            <img src="{{ asset('assets/carriers/fedex/fedex-unified-logo.svg') }}" alt="">
                        @else
                            FX
                        @endif
                    </span>
                    <div>
                        <p class="dh-panel-title">FedEx</p>
                        <p class="dh-panel-meta">{{ $fedExDetail }}</p>
                    </div>
                </div>
                <span @class(['dh-chip', 'is-ready' => $fedExStatus === 'connected', 'is-warn' => $fedExStatus === 'setup', 'is-blocked' => $fedExStatus === 'attention'])>
                    {{ $fedExLabel }}
                </span>
            </div>

            @if ($fedExAccount && in_array($fedExStatus, ['connected', 'attention'], true))
                <div class="dh-cap-grid">
                    <div class="dh-cap">
                        <p class="dh-cap-label">Checkout rates</p>
                        <p class="dh-cap-value">{{ $fedExCaps['checkout'] ? 'Available' : 'Not active' }}</p>
                    </div>
                    <div class="dh-cap">
                        <p class="dh-cap-label">Labels</p>
                        <p class="dh-cap-value">{{ $fedExCaps['labels'] ? 'Available' : 'Needs attention' }}</p>
                    </div>
                    <div class="dh-cap">
                        <p class="dh-cap-label">Tracking</p>
                        <p class="dh-cap-value">{{ $fedExCaps['tracking'] ? 'Available' : 'Needs attention' }}</p>
                    </div>
                </div>
            @endif

            @if ($canManageShipping ?? false)
                <div class="dh-fedex-foot">
                    <a href="{{ $fedExHref }}" class="dh-link">{{ $fedExConnected ? 'Manage FedEx' : 'Connect FedEx' }} →</a>
                </div>
            @endif
        </article>
    </section>

    {{-- Packages --}}
    @if ($showPackages)
        <section id="packages" class="dh-block">
            <h3 class="dh-block-title">Packages</h3>
            <article class="dh-panel">
                <div class="dh-panel-row">
                    @if ($defaultPreset)
                        <p class="dh-packages-summary">
                            <span class="dh-option-name">{{ $defaultPreset->name }}</span>
                            <span class="dh-dot" aria-hidden="true">·</span>
                            {{ number_format((float) $defaultPreset->length, 0) }}
                            × {{ number_format((float) $defaultPreset->width, 0) }}
                            × {{ number_format((float) $defaultPreset->height, 0) }}
                            {{ strtolower($defaultPreset->dimension_unit ?: 'in') }}
                            @if ($defaultPreset->is_default)
                                <span class="dh-dot" aria-hidden="true">·</span>
                                Default
                            @endif
                        </p>
                    @else
                        <p class="dh-panel-meta">Add a default package so live rates can use real dimensions.</p>
                    @endif
                    <a href="{{ route('settings.shipping.packages') }}" class="dh-link">Manage packages</a>
                </div>
            </article>
        </section>
    @endif

    {{-- Troubleshooting --}}
    <section id="delivery-troubleshooting" class="dh-block">
        <details class="dh-trouble">
            <summary class="dh-trouble-summary">
                <span>Troubleshooting</span>
                <span class="dh-trouble-hint">Preview checkout delivery</span>
            </summary>
            <div class="dh-trouble-body">
                <a href="{{ route('settings.delivery.test-address') }}" class="dh-link">Preview checkout delivery →</a>
                <p class="dh-panel-meta mt-2">
                    See which delivery options a customer would get for an address — without changing orders or settings.
                </p>
                <p class="dh-panel-meta mt-2">
                    Tax is managed under
                    <a href="{{ route('settings.taxes.index') }}" class="dh-link">Checkout &amp; tax</a>.
                </p>
            </div>
        </details>
    </section>
</section>
