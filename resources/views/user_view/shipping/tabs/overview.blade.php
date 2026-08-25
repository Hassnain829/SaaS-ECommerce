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
    $showCompactRecommendations = $hasCompletedSetup && $isReady && ! $hasErrors && $healthItems->isNotEmpty();
    $showAttentionHealth = $hasCompletedSetup && $healthItems->isNotEmpty() && (! $isReady || $hasErrors);
    $blockerItems = $healthItems->filter(fn ($i) => ($i['severity'] ?? '') === 'error')->values();
    $showCheckoutBlocker = $hasCompletedSetup && ! $isReady && $blockerItems->isNotEmpty();

    $locationsList = collect($locations ?? []);
    $allZones = collect($shippingZones ?? [])->values();
    $methods = collect($shippingMethods ?? []);
    $zonePresenter = app(\App\Services\Delivery\DeliveryAreaInputNormalizer::class);
    $lifecycle = app(\App\Services\Delivery\DeliverySetupLifecycleService::class);
    $currency = $storeRef->currency ?? 'USD';
    $canManage = (bool) ($canManageShipping ?? false);

    $errorIds = $healthItems
        ->filter(fn ($i) => ($i['severity'] ?? '') === 'error')
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->values();
    $shipFromDone = ! $errorIds->contains(fn ($id) => str_starts_with($id, 'ship_from_'));
    $deliverToDone = $shipFromDone && ! $errorIds->contains(fn ($id) => str_starts_with($id, 'delivery_area_'));
    $checkoutDone = $deliverToDone && ! $errorIds->contains(fn ($id) => str_starts_with($id, 'delivery_option_'));
    $continueSetupRoute = $storeRef
        ? route($lifecycle->nextIncompleteSetupRouteName($storeRef))
        : route('settings.delivery.setup');

    $setupSteps = [
        [
            'title' => 'Ship from',
            'meta' => $shipFromDone ? 'Main location saved' : 'Where orders ship from',
            'state' => $shipFromDone ? 'done' : 'current',
        ],
        [
            'title' => 'Deliver to',
            'meta' => $deliverToDone ? 'Coverage saved' : 'Choose your coverage',
            'state' => ! $shipFromDone ? 'todo' : ($deliverToDone ? 'done' : 'current'),
        ],
        [
            'title' => 'Checkout options',
            'meta' => $checkoutDone ? 'Options saved' : 'Fixed, free, or FedEx',
            'state' => ! $deliverToDone ? 'todo' : ($checkoutDone ? 'done' : 'current'),
        ],
        [
            'title' => 'Review',
            'meta' => $isReady ? 'Ready to finish' : 'Confirm and finish',
            'state' => ! $checkoutDone ? 'todo' : ($isReady ? 'done' : 'current'),
        ],
    ];

    $fedExLiveMethods = $methods->filter(
        fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()
    );
    $orphanMethods = $methods->filter(function ($m) {
        return method_exists($m, 'isOrphanedFromArea')
            ? $m->isOrphanedFromArea()
            : ($m->shipping_zone_id === null || ! $m->shippingZone);
    })->values();

    $hasFixedCheckout = $methods->contains(function ($m) {
        if (method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()) {
            return false;
        }
        if (method_exists($m, 'isOrphanedFromArea') && $m->isOrphanedFromArea()) {
            return false;
        }

        return $m->is_active && $m->enabled_for_checkout;
    });

    $fedExAccount = ($fedExAccounts ?? collect())->first(
        fn ($account) => $account->usesFedExIntegratorProvider()
            && $account->disconnected_at === null
            && $account->replaced_at === null
    ) ?? ($fedExAccounts ?? collect())->first();

    $fedExStatus = 'setup';
    $fedExLabel = 'Not connected';
    $fedExDetail = 'Connect your FedEx account when you want live carrier rates, labels, and tracking. Fixed/free delivery works without FedEx.';
    $fedExHref = ($fedExConfig->modelAEnabled() ?? false)
        ? route('settings.shipping.fedex-integrator.start')
        : route('shipping.carriers.connect.show', 'fedex');
    $fedExCaps = ['checkout' => false, 'labels' => false, 'tracking' => false];
    $fedExConnected = false;
    $fedExAccountTitle = 'FedEx';

    if ($fedExAccount) {
        $fedExHref = $fedExAccount->usesFedExIntegratorProvider()
            ? route('settings.shipping.fedex-integrator.manage', $fedExAccount)
            : route('shippingAutomation');
        $fedExDetail = 'Account '.$fedExAccount->maskedAccountNumber();
        $fedExAccountTitle = $fedExAccount->display_name ?: 'FedEx';
        $caps = is_array($fedExAccount->capabilities) ? $fedExAccount->capabilities : [];
        $checkoutPlatformOn = (bool) config('carriers.fedex.checkout_rates_enabled', false);
        $fedExCaps['checkout'] = $checkoutPlatformOn
            && (bool) $fedExAccount->enabled_for_checkout
            && (bool) ($caps['checkout_rates'] ?? false)
            && $fedExLiveMethods->contains(fn ($m) => $m->is_active && $m->enabled_for_checkout);
        $fedExCaps['labels'] = (bool) ($caps['labels'] ?? false);
        $fedExCaps['tracking'] = (bool) ($caps['tracking'] ?? true);
        $fedExConnected = in_array($fedExAccount->connection_status, ['connected', 'sandbox_platform_fallback'], true);

        if ($fedExConnected) {
            $fedExStatus = $fedExCaps['checkout'] || $hasFixedCheckout ? 'connected' : 'attention';
            $fedExLabel = 'Connected';
        } elseif (in_array($fedExAccount->connection_status, ['failed', 'blocked_by_fedex'], true)) {
            $fedExStatus = 'attention';
            $fedExLabel = 'Needs attention';
        }
    }

    $showPackages = $fedExLiveMethods->contains(fn ($m) => $m->is_active)
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
            return 'Live rate';
        }
        if ($method->rate_type === \App\Models\ShippingMethod::RATE_FREE) {
            return 'Free';
        }
        if ((float) ($method->free_over_amount ?? 0) > 0) {
            return 'Free over '.$currency.' '.number_format((float) $method->free_over_amount, 2);
        }
        if ((float) ($method->flat_rate ?? 0) > 0) {
            return $currency.' '.number_format((float) $method->flat_rate, 2);
        }

        return 'Fixed';
    };

    $methodDaysLabel = static function ($method): string {
        if ($method->estimated_min_days !== null && $method->estimated_max_days !== null) {
            return $method->estimated_min_days.'–'.$method->estimated_max_days.' business days';
        }

        return $method->delivery_speed_label ?: '—';
    };

    $methodDescLabel = static function ($method): string {
        if ($method->rate_type === \App\Models\ShippingMethod::RATE_FREE) {
            return 'Free';
        }
        if ((float) ($method->free_over_amount ?? 0) > 0) {
            return 'Free on qualifying orders';
        }

        return 'Fixed price';
    };

    $packagePresetsList = collect($packagePresets ?? []);
    $defaultPreset = $packagePresetsList->firstWhere('is_default', true) ?? $packagePresetsList->first();
    $primaryRecommendation = $healthItems->first();
    $fedExServicesCatalog = \App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog::services();
@endphp

<section class="dh" aria-label="Delivery settings">
@unless ($hasCompletedSetup)
    <section class="dh-setup-hero" aria-label="Set up delivery">
        <h1 class="dh-setup-title">Set up delivery</h1>
        <p class="dh-setup-lead">Complete these steps once. After you finish, Delivery becomes your day-to-day management workspace.</p>
        <div class="dh-step-grid">
            @foreach ($setupSteps as $index => $step)
                <div @class(['dh-step', 'is-done' => $step['state'] === 'done', 'is-current' => $step['state'] === 'current'])>
                    <div class="dh-step-num">{{ $step['state'] === 'done' ? '✓' : ($index + 1) }}</div>
                    <div class="dh-step-title">{{ $step['title'] }}</div>
                    <div class="dh-step-meta">{{ $step['meta'] }}</div>
                </div>
            @endforeach
        </div>
        <div class="dh-setup-foot">
            <p class="dh-setup-note">FedEx is optional. You can finish setup with a simple fixed or free delivery option.</p>
            @if ($canManage)
                <a href="{{ $continueSetupRoute }}" class="dh-btn dh-btn-primary">Continue setup</a>
            @endif
        </div>
    </section>
@else
    <header class="dh-status-bar">
        <span @class(['dh-badge', 'is-ready' => $isReady, 'is-pending' => ! $isReady])>
            <span class="dh-badge-dot" aria-hidden="true"></span>
            {{ $statusBadgeLabel }}
        </span>
        @if ($canManage)
            <button type="button" data-open-drawer="method-add" class="dh-btn dh-btn-primary" @if ($allZones->isEmpty()) disabled @endif>
                Add delivery option
            </button>
        @endif
    </header>

    @if ($showCheckoutBlocker)
        <aside class="dh-alert dh-alert-blocker" aria-label="Checkout blockers">
            <div class="dh-alert-copy">
                <p class="dh-alert-title">{{ $blockerItems->count() }} {{ $blockerItems->count() === 1 ? 'issue is' : 'issues are' }} blocking some checkouts</p>
                <p class="dh-alert-item-msg">{{ $blockerItems->first()['message'] ?? '' }}</p>
            </div>
            @if ($canManage)
                <a href="{{ $continueSetupRoute }}" class="dh-btn dh-btn-primary">Continue setup</a>
            @endif
        </aside>
    @elseif ($showAttentionHealth)
        <aside class="dh-alert" aria-label="Delivery needs attention">
            <div class="dh-alert-copy">
                <p class="dh-alert-title">Needs attention</p>
                <ul class="dh-alert-list">
                    @foreach ($healthItems->take(3) as $item)
                        <li class="dh-alert-item">
                            <div>
                                <p class="dh-alert-item-title">{{ $item['label'] ?? 'Setup item' }}</p>
                                <p class="dh-alert-item-msg">{{ $item['message'] ?? '' }}</p>
                            </div>
                            @if ($canManage && ! empty($item['action_href']))
                                <a href="{{ $item['action_href'] }}" class="dh-link">{{ $item['action_label'] ?? 'Fix' }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            @if ($canManage)
                <a href="{{ $continueSetupRoute }}" class="dh-btn dh-btn-primary">Continue setup</a>
            @endif
        </aside>
    @elseif ($showCompactRecommendations && $primaryRecommendation)
        <aside class="dh-reco" aria-label="Delivery recommendations">
            <div class="dh-reco-copy">
                <p class="dh-reco-count">
                    {{ $healthItems->count() }} {{ $healthItems->count() === 1 ? 'recommendation' : 'recommendations' }}
                </p>
                <p class="dh-reco-msg">{{ $primaryRecommendation['message'] ?? ($primaryRecommendation['label'] ?? '') }}</p>
            </div>
            @if ($canManage && ! empty($primaryRecommendation['action_href']))
                <a href="{{ $primaryRecommendation['action_href'] }}" class="dh-link">
                    {{ $primaryRecommendation['action_label'] ?? 'Review' }} →
                </a>
            @endif
        </aside>
    @endif

    @if ($orphanMethods->isNotEmpty() && $canManage)
        <div class="dh-orphan">
            <p>{{ $orphanMethods->count() }} unused delivery {{ $orphanMethods->count() === 1 ? 'option is' : 'options are' }} not linked to a delivery area.</p>
            <form method="POST" action="{{ route('settings.shipping.methods.cleanup-orphans') }}" onsubmit="return confirm('Remove all unused delivery options that are not linked to a delivery area?')">
                @csrf
                <button type="submit" class="dh-btn dh-btn-ghost">Remove unused options</button>
            </form>
        </div>
    @endif

    <section id="delivery-fedex" class="dh-block">
        <div class="dh-block-head">
            <div>
                <h3 class="dh-block-title">FedEx</h3>
                <p class="dh-block-sub">Optional live rates, labels, and tracking using your FedEx account.</p>
            </div>
        </div>
        @if ($fedExConnected)
            <article class="dh-fedex is-connected">
                <div class="dh-fedex-top">
                    <div class="dh-fedex-brand">
                        <span class="dh-fedex-logo" aria-hidden="true">
                            @if (file_exists(public_path('assets/carriers/fedex/fedex-unified-logo.svg')))
                                <img src="{{ asset('assets/carriers/fedex/fedex-unified-logo.svg') }}" alt="">
                            @else
                                FedEx
                            @endif
                        </span>
                        <div>
                            <p class="dh-panel-title">{{ $fedExAccountTitle }}</p>
                            <p class="dh-panel-meta">{{ $fedExDetail }}</p>
                            <div class="dh-chips"><span class="dh-chip is-ready">Connected</span></div>
                        </div>
                    </div>
                    @if ($canManage)
                        <a href="{{ $fedExHref }}" class="dh-link">Manage FedEx →</a>
                    @endif
                </div>
                <div class="dh-cap-grid">
                    <div class="dh-cap">
                        <p class="dh-cap-label">Checkout rates</p>
                        <p class="dh-cap-value">{{ $fedExCaps['checkout'] ? 'Available' : 'Needs attention' }}</p>
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
            </article>
        @else
            <article class="dh-panel">
                <div class="dh-panel-row">
                    <div class="dh-row-main">
                        <span class="dh-fedex-logo" aria-hidden="true">FedEx</span>
                        <div>
                            <p class="dh-panel-title">FedEx</p>
                            <p class="dh-panel-meta">{{ $fedExDetail }}</p>
                            <div class="dh-chips">
                                <span class="dh-chip is-muted">Optional</span>
                                <span @class(['dh-chip', 'is-warn' => true])>
                                    {{ $fedExLabel }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @if ($canManage)
                        <a href="{{ $fedExHref }}" class="dh-btn dh-btn-primary">Connect FedEx</a>
                    @endif
                </div>
            </article>
        @endif
    </section>

    <section id="shipping-origin" class="dh-block">
        <div class="dh-block-head">
            <div>
                <h3 class="dh-block-title">Shipping origin</h3>
                <p class="dh-block-sub">Where orders ship from.</p>
            </div>
            <a href="{{ route('settings.locations.index') }}" class="dh-btn dh-btn-ghost">Manage locations</a>
        </div>
        @if ($defaultLocation)
            <article class="dh-panel">
                <div class="dh-panel-row">
                    <div class="dh-row-main">
                        <div class="dh-iconbox" aria-hidden="true">⌖</div>
                        <div class="min-w-0">
                            <p class="dh-panel-title">{{ $defaultLocation->name }}</p>
                            <p class="dh-panel-meta">{{ $originAddress ?: 'Address incomplete' }}</p>
                            <div class="dh-chips">
                                @if ($defaultLocation->is_default)
                                    <span class="dh-chip is-muted">Default</span>
                                @endif
                                <span @class(['dh-chip', 'is-ready' => $originComplete, 'is-blocked' => ! $originComplete])>
                                    {{ $originComplete ? 'Ready' : 'Needs attention' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('settings.locations.index') }}" class="dh-link">Edit location</a>
                </div>
            </article>
        @else
            <div class="dh-empty">Add a ship-from location to continue.</div>
        @endif
    </section>

    <section id="delivery-areas" class="dh-block">
        <div class="dh-block-head">
            <div>
                <h3 class="dh-block-title">Delivery areas &amp; options</h3>
                <p class="dh-block-sub">Where customers can order and what they see at checkout.</p>
            </div>
            @if ($canManage)
                <button type="button" data-open-drawer="zone-add" class="dh-btn dh-btn-ghost">Add delivery area</button>
            @endif
        </div>

        <div class="dh-stack">
            @forelse ($allZones as $zone)
                @php
                    $zoneMethods = $methods
                        ->where('shipping_zone_id', $zone->id)
                        ->values();
                    $zoneFedExMethods = $zoneMethods->filter(
                        fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()
                    )->values();
                    $zoneFedExSelected = $zoneFedExMethods->filter(fn ($m) => $m->is_active)->values();
                    $zoneFixedMethods = $zoneMethods->reject(
                        fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()
                    )->values();
                    $regionCount = collect($zone->regions)->filter()->count();
                    $countries = collect($zone->countries)->filter()->values();
                    $coverage = $regionCount > 0
                        ? $regionCount.' '.($regionCount === 1 ? 'state' : 'states')
                        : ($countries->implode(', ') ?: 'No country set');
                    if ($regionCount === 0 && $countries->count() === 1) {
                        $coverage = 'Entire country · '.$countries->first();
                    }
                    $fedExCheckoutOn = $zone->is_active
                        && $zoneFedExSelected->contains(fn ($m) => $m->enabled_for_checkout);
                    $selectedFedExCodes = $zoneFedExSelected->pluck('carrier_service_code')->filter()->map(fn ($c) => strtoupper((string) $c))->all();
                @endphp
                <article class="dh-panel dh-area">
                    <div class="dh-area-top">
                        <div class="min-w-0">
                            <p class="dh-panel-title">{{ $zone->name }}</p>
                            <p class="dh-panel-meta">{{ $coverage }}</p>
                        </div>
                        @if ($canManage)
                            <div class="dh-actions">
                                <div class="dh-switchwrap">
                                    <span class="dh-switchlabel">Area active</span>
                                    <button
                                        type="button"
                                        class="dh-switch {{ $zone->is_active ? 'is-on' : '' }}"
                                        data-availability-toggle
                                        data-toggle-kind="zone"
                                        data-toggle-url="{{ route('settings.shipping.zones.availability', $zone) }}"
                                        data-available="{{ $zone->is_active ? '1' : '0' }}"
                                        aria-pressed="{{ $zone->is_active ? 'true' : 'false' }}"
                                        aria-label="Area active for {{ $zone->name }}"
                                    ></button>
                                </div>
                                <button type="button" class="zone-edit-btn dh-btn dh-btn-ghost"
                                    data-action="{{ route('settings.shipping.zones.update', $zone) }}"
                                    data-zone-form="{{ e(json_encode($zonePresenter->presentationFromZone($zone))) }}">Edit</button>
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
                        @else
                            <span @class(['dh-chip', 'is-ready' => $zone->is_active, 'is-muted' => ! $zone->is_active])>
                                {{ $zone->is_active ? 'Active' : 'Inactive' }}
                            </span>
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
                                    $optionOn = $method->is_active && $method->enabled_for_checkout;
                                @endphp
                                <div @class(['dh-option-row', 'is-off' => ! $optionOn])>
                                    <div>
                                        <div class="dh-option-name">{{ $method->name }}</div>
                                        <div class="dh-option-desc">{{ $methodDescLabel($method) }}</div>
                                    </div>
                                    <div class="dh-metric">
                                        <div class="dh-olabel">Customer pays</div>
                                        <div class="dh-ovalue">{{ $methodPriceLabel($method) }}</div>
                                    </div>
                                    <div class="dh-metric">
                                        <div class="dh-olabel">Estimate</div>
                                        <div class="dh-ovalue">{{ $methodDaysLabel($method) }}</div>
                                    </div>
                                    <div class="dh-actions">
                                        @if ($canManage)
                                            <div class="dh-switchwrap">
                                                <span class="dh-switchlabel">At checkout</span>
                                                <button
                                                    type="button"
                                                    class="dh-switch {{ $optionOn ? 'is-on' : '' }}"
                                                    data-availability-toggle
                                                    data-toggle-kind="method"
                                                    data-toggle-url="{{ route('settings.shipping.methods.availability', $method) }}"
                                                    data-available="{{ $optionOn ? '1' : '0' }}"
                                                    aria-pressed="{{ $optionOn ? 'true' : 'false' }}"
                                                    aria-label="At checkout for {{ $method->name }}"
                                                ></button>
                                            </div>
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
                                </div>
                            @endforeach

                            @if ($zoneFedExMethods->isNotEmpty())
                                @php
                                    $serviceCount = $zoneFedExSelected->count();
                                    $serviceLabel = $serviceCount.' '.($serviceCount === 1 ? 'service' : 'services').' enabled';
                                @endphp
                                <div @class(['dh-option-row', 'is-off' => ! $fedExCheckoutOn])>
                                    <div>
                                        <div class="dh-option-name">FedEx live rates</div>
                                        <div class="dh-option-desc">{{ $serviceLabel }} · rates calculated by FedEx</div>
                                    </div>
                                    <div class="dh-metric">
                                        <div class="dh-olabel">Customer pays</div>
                                        <div class="dh-ovalue">Live rate</div>
                                    </div>
                                    <div class="dh-metric">
                                        <div class="dh-olabel">Services</div>
                                        <div class="dh-ovalue">{{ $serviceCount > 0 ? ($zoneFedExSelected->first()->carrier_service_name ?: 'FedEx').($serviceCount > 1 ? ' + '.($serviceCount - 1) : '') : 'None' }}</div>
                                    </div>
                                    <div class="dh-actions">
                                        @if ($canManage)
                                            <div class="dh-switchwrap">
                                                <span class="dh-switchlabel">At checkout</span>
                                                <button
                                                    type="button"
                                                    class="dh-switch {{ $fedExCheckoutOn ? 'is-on' : '' }}"
                                                    data-availability-toggle
                                                    data-toggle-kind="fedex-group"
                                                    data-toggle-url="{{ route('settings.shipping.zones.fedex-live-rates.availability', $zone) }}"
                                                    data-available="{{ $fedExCheckoutOn ? '1' : '0' }}"
                                                    aria-pressed="{{ $fedExCheckoutOn ? 'true' : 'false' }}"
                                                    aria-label="FedEx live rates at checkout for {{ $zone->name }}"
                                                ></button>
                                            </div>
                                            <button
                                                type="button"
                                                class="dh-btn dh-btn-ghost"
                                                data-open-drawer="fedex-services"
                                                data-zone-id="{{ $zone->id }}"
                                                data-zone-name="{{ $zone->name }}"
                                                data-action="{{ route('settings.shipping.zones.fedex-live-rates.update', $zone) }}"
                                                data-available="{{ $fedExCheckoutOn ? '1' : '0' }}"
                                                data-services="{{ e(json_encode($selectedFedExCodes)) }}"
                                            >Manage</button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endif

                        @if ($canManage)
                            <button type="button" data-open-drawer="method-add" data-zone-id="{{ $zone->id }}" class="dh-add-option">+ Add delivery option</button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="dh-empty">
                    <p>No delivery areas yet. Add an area to start offering checkout delivery.</p>
                    @if ($canManage)
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                            <button type="button" data-open-drawer="zone-add" class="dh-btn dh-btn-primary">Add delivery area</button>
                            <a href="{{ $continueSetupRoute }}" class="dh-btn dh-btn-ghost">Continue setup</a>
                        </div>
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    @if ($showPackages)
        <section id="packages" class="dh-block">
            <div class="dh-block-head">
                <div>
                    <h3 class="dh-block-title">Packages</h3>
                    <p class="dh-block-sub">Used only when carrier rates or labels need parcel dimensions.</p>
                </div>
            </div>
            <article class="dh-panel">
                <div class="dh-panel-row">
                    @if ($defaultPreset)
                        <div>
                            <p class="dh-panel-title">{{ $defaultPreset->name }}</p>
                            <p class="dh-panel-meta">
                                {{ number_format((float) $defaultPreset->length, 0) }}
                                × {{ number_format((float) $defaultPreset->width, 0) }}
                                × {{ number_format((float) $defaultPreset->height, 0) }}
                                {{ strtolower($defaultPreset->dimension_unit ?: 'in') }}
                                @if ($defaultPreset->is_default)
                                    · Default
                                @endif
                            </p>
                        </div>
                    @else
                        <p class="dh-panel-meta">Add a default package so live rates can use real dimensions.</p>
                    @endif
                    <a href="{{ route('settings.shipping.packages') }}#checkout-weight-fallback" class="dh-link">Manage packages →</a>
                </div>
                @php
                    $fallbackWeight = $shippingPreferences['fallback_item_weight'] ?? null;
                    $weightUnitLabel = $shippingPreferences['weight_unit'] ?? 'LB';
                @endphp
                @if ($fallbackWeight)
                    <p class="mt-3 text-sm text-[#475569]">
                        Checkout weight fallback: <span class="font-semibold text-[#0F172A]">{{ number_format((float) $fallbackWeight, 2) }} {{ $weightUnitLabel }}</span>
                        · used only when a product has no shipping weight.
                    </p>
                @else
                    <p class="mt-3 text-sm text-[#64748B]">
                        No checkout weight fallback set.
                        <a href="{{ route('settings.shipping.packages') }}#checkout-weight-fallback" class="font-semibold text-brand hover:underline">Add a fallback item weight</a>
                        so FedEx can estimate rates for products without their own weight.
                    </p>
                @endif
            </article>
        </section>
    @endif

    <section id="delivery-troubleshooting" class="dh-block">
        <details class="dh-trouble">
            <summary class="dh-trouble-summary">
                <span>
                    <span class="dh-trouble-title">Troubleshooting</span>
                    <span class="dh-trouble-hint">Preview checkout delivery.</span>
                </span>
                <span aria-hidden="true">⌄</span>
            </summary>
            <div class="dh-trouble-body">
                <div class="dh-tr-row">
                    <div>
                        <p class="dh-panel-title" style="font-size:0.92rem">Preview checkout delivery</p>
                        <p class="dh-panel-meta">See exactly what a customer will be offered for an address and subtotal.</p>
                    </div>
                    <a href="{{ route('settings.delivery.test-address') }}" class="dh-btn dh-btn-ghost">Preview</a>
                </div>
            </div>
        </details>
    </section>
@endunless
</section>

@if ($hasCompletedSetup && $canManage)
<script type="application/json" id="fedex-services-catalog">@json($fedExServicesCatalog)</script>
@endif
