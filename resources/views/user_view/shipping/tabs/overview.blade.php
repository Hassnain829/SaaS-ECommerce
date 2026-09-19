@php
    $setup = $deliverySetup ?? [];
    $healthItems = collect($setup['health_items'] ?? []);
    $isReady = (bool) ($setup['is_ready'] ?? false);
    $storeCandidate = $selectedStore ?? $currentStore ?? null;
    $storeRef = $storeCandidate instanceof \App\Models\Store ? $storeCandidate : null;
    $hasCompletedSetup = $storeRef?->delivery_setup_completed_at !== null;
    $hasErrors = $healthItems->contains(fn ($i) => ($i['severity'] ?? '') === 'error');
    $statusBadgeLabel = $isReady
        ? 'Ready'
        : ($hasCompletedSetup ? 'Needs attention' : 'Setup in progress');
    $showAttentionHealth = $hasCompletedSetup && $healthItems->isNotEmpty() && (! $isReady || $hasErrors);
    $blockerItems = $healthItems->filter(fn ($i) => ($i['severity'] ?? '') === 'error')->values();
    $showCheckoutBlocker = $hasCompletedSetup && ! $isReady && $blockerItems->isNotEmpty();

    $locationsList = collect($locations ?? []);
    $allZones = collect($shippingZones ?? [])->values();
    $methods = collect($shippingMethods ?? []);
    $zonePresenter = app(\App\Services\Delivery\DeliveryAreaInputNormalizer::class);
    $lifecycle = app(\App\Services\Delivery\DeliverySetupLifecycleService::class);
    $currency = $storeRef?->currency ?? 'USD';
    $canManage = (bool) ($canManageShipping ?? false);
    $canDelete = (bool) ($canDeleteDelivery ?? false);

    $errorIds = $healthItems
        ->filter(fn ($i) => ($i['severity'] ?? '') === 'error')
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->values();
    $shipFromDone = ! $errorIds->contains(fn ($id) => str_starts_with($id, 'ship_from_'));
    $deliverToDone = $shipFromDone && ! $errorIds->contains(fn ($id) => str_starts_with($id, 'delivery_area_'));
    $checkoutDone = $deliverToDone && ! $errorIds->contains(fn ($id) => str_starts_with($id, 'delivery_option_'));
    if ($storeRef instanceof \App\Models\Store) {
        $continueSetupRoute = route($lifecycle->nextIncompleteSetupRouteName($storeRef));
    } else {
        $continueSetupRoute = route('settings.delivery.setup');
    }

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

    $fedExPlatformEnabled = (bool) ($fedExEnabled ?? false)
        && (bool) ($fedExConfig?->modelAEnabled() ?? false);
    $fedExStatus = 'setup';
    $fedExUiStatus = $fedExPlatformEnabled ? 'not_connected' : 'disabled';
    $fedExLabel = $fedExPlatformEnabled ? 'Not connected' : 'Unavailable';
    $fedExDetail = 'Connect your FedEx account when you want live carrier rates, labels, and tracking. Fixed/free delivery works without FedEx.';
    $fedExHref = $fedExPlatformEnabled
        ? route('settings.shipping.fedex-integrator.start')
        : route('shipping.carriers.connect.show', 'fedex');
    $fedExManageHref = $fedExHref;
    $fedExCaps = ['checkout' => false, 'labels' => false, 'tracking' => false];
    $fedExConnected = false;
    $fedExAccountTitle = 'FedEx';

    if ($fedExAccount) {
        $fedExHref = $fedExAccount->usesFedExIntegratorProvider()
            ? route('settings.shipping.fedex-integrator.manage', $fedExAccount)
            : route('shippingAutomation');
        $fedExManageHref = $fedExHref;
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
        $connectionStatus = (string) $fedExAccount->connection_status;

        if ($fedExConnected) {
            $fedExUiStatus = 'connected';
            $fedExStatus = $fedExCaps['checkout'] || $hasFixedCheckout ? 'connected' : 'attention';
            $fedExLabel = 'Connected';
        } elseif (in_array($connectionStatus, ['pending_validation', 'setup_required'], true)) {
            $fedExUiStatus = 'pending_validation';
            $fedExStatus = 'setup';
            $fedExLabel = 'Connecting';
            $fedExHref = route('settings.shipping.fedex-integrator.start');
        } elseif (in_array($connectionStatus, ['failed', 'blocked_by_fedex'], true)) {
            $fedExUiStatus = 'failed';
            $fedExStatus = 'attention';
            $fedExLabel = 'Needs attention';
        } elseif ($connectionStatus === 'disabled') {
            $fedExUiStatus = 'disabled';
            $fedExLabel = 'Unavailable';
        } else {
            $fedExUiStatus = $fedExPlatformEnabled ? 'not_connected' : 'disabled';
            $fedExLabel = $fedExPlatformEnabled ? 'Not connected' : 'Unavailable';
            $fedExHref = $fedExPlatformEnabled
                ? route('settings.shipping.fedex-integrator.start')
                : $fedExHref;
        }
    }

    $showPackages = $fedExLiveMethods->contains(fn ($m) => $m->is_active)
        || ($fedExConnected && ($fedExCaps['labels'] || $fedExCaps['checkout']));

    $defaultLocation = $locationsList->first(function ($location) use ($originReadinessByLocationId) {
            return (bool) $location->is_default
                && (bool) (($originReadinessByLocationId[$location->id] ?? null)?->ready);
        })
        ?? $locationsList->firstWhere('is_default', true)
        ?? $locationsList->firstWhere('is_active', true)
        ?? $locationsList->first();
    $originReadiness = $defaultLocation
        ? (($originReadinessByLocationId[$defaultLocation->id] ?? null))
        : null;
    $originCarrierReady = (bool) ($originReadiness?->ready ?? false);
    $originComplete = $defaultLocation
        && filled($defaultLocation->address_line1)
        && filled($defaultLocation->city)
        && filled($defaultLocation->country_code);
    $originAddress = $originReadiness?->displayAddress
        ?: ($defaultLocation
            ? collect([
                $defaultLocation->address_line1,
                $defaultLocation->city,
                $defaultLocation->state,
                $defaultLocation->postal_code,
                $defaultLocation->country_code,
            ])->filter()->implode(', ')
            : null);

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

        return $method->delivery_speed_label ?: 'No estimate';
    };

    $methodDescLabel = static function ($method): string {
        if ($method->rate_type === \App\Models\ShippingMethod::RATE_FREE) {
            return 'Free delivery';
        }
        if ((float) ($method->free_over_amount ?? 0) > 0) {
            return 'Conditional free delivery';
        }

        return 'Fixed price';
    };

    $packagePresetsList = collect($packagePresets ?? []);
    $defaultPreset = $packagePresetsList->firstWhere('is_default', true) ?? $packagePresetsList->first();
    $weightFallbackItem = $healthItems->firstWhere('id', 'products_using_shipping_weight_fallback');
    $fedExServicesCatalog = \App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog::services();
    $activeZones = $allZones->where('is_active', true)->values();
    $visibleCheckoutCount = $methods->filter(function ($method) use ($activeZones) {
        return $method->is_active
            && $method->enabled_for_checkout
            && $activeZones->contains(fn ($zone) => (int) $zone->id === (int) $method->shipping_zone_id);
    })->count();
    $routeFedExValue = match ($fedExUiStatus) {
        'connected' => 'FedEx',
        'pending_validation' => 'FedEx',
        'failed' => 'FedEx',
        'disabled' => 'FedEx',
        default => 'Optional',
    };
    $routeFedExState = match ($fedExUiStatus) {
        'connected' => 'Connected',
        'pending_validation' => 'Connecting',
        'failed' => 'Needs attention',
        'disabled' => 'Unavailable',
        default => 'Not connected',
    };
    $routeFedExPill = match ($fedExUiStatus) {
        'connected' => 'do-pill-ready',
        'pending_validation' => 'do-pill-blue',
        'failed' => 'do-pill-danger',
        'disabled' => 'do-pill-muted',
        default => 'do-pill-muted',
    };
    $locationsUrl = route('settings.locations.index');
    $packagesUrl = route('settings.shipping.packages');
    $previewUrl = route('settings.delivery.test-address');
    $productsFallbackUrl = route('products', ['shipping_weight' => 'uses_fallback']);

    $primaryActiveZone = $activeZones->first();
    $originStageStatus = $originCarrierReady ? 'ready' : (($originComplete ?? false) ? 'warning' : 'blocked');
    $originStageDetail = $defaultLocation
        ? (($originAddress ?: 'This ship-from location still needs a complete address.').($originCarrierReady ? '' : ' Carrier rates and labels need a complete origin address.'))
        : 'Add an active ship-from location so orders have a place to ship from.';
    $coverageStageDetail = $primaryActiveZone
        ? 'Customers in this area can see matching checkout options.'
        : 'At least one valid delivery area must be active before shipping options can appear at checkout.';
    $checkoutStageDetail = $visibleCheckoutCount > 0
        ? $visibleCheckoutCount.' checkout '.($visibleCheckoutCount === 1 ? 'option is' : 'options are').' available to matching customers.'
        : 'Checkout options cannot be shown until coverage and at least one delivery option are ready.';
    $carrierStageStatus = match ($fedExUiStatus) {
        'connected' => 'ready',
        'pending_validation' => 'warning',
        'failed' => 'warning',
        'disabled' => 'muted',
        default => 'muted',
    };
    $carrierStageValue = match ($fedExUiStatus) {
        'connected', 'pending_validation', 'failed' => 'FedEx',
        'disabled' => 'Unavailable',
        default => 'Not connected',
    };
    $carrierStageDetail = $fedExUiStatus === 'connected'
        ? trim($fedExAccountTitle.($fedExDetail ? ' · '.$fedExDetail : ''))
        : (string) $fedExDetail;
    $routeStages = [
        [
            'id' => 'origin',
            'label' => 'Ship from',
            'value' => $defaultLocation?->name ?? 'Not configured',
            'status' => $originStageStatus,
            'statusLabel' => $originCarrierReady ? 'Ready' : ($defaultLocation ? 'Needs attention' : 'Missing'),
            'icon' => 'store',
            'optional' => false,
            'detail' => $originStageDetail,
            'actionLabel' => $defaultLocation ? 'Manage locations' : 'Add location',
            'action' => 'flow-origin',
        ],
        [
            'id' => 'coverage',
            'label' => 'Deliver to',
            'value' => $primaryActiveZone?->name ?? 'No active area',
            'status' => $activeZones->isNotEmpty() ? 'ready' : 'blocked',
            'statusLabel' => $activeZones->isNotEmpty() ? 'Active' : 'Missing',
            'icon' => 'pin',
            'optional' => false,
            'detail' => $coverageStageDetail,
            'actionLabel' => $primaryActiveZone ? 'Manage area' : 'Add delivery area',
            'action' => 'flow-area',
        ],
        [
            'id' => 'checkout',
            'label' => 'Checkout',
            'value' => $visibleCheckoutCount > 0
                ? $visibleCheckoutCount.' delivery '.($visibleCheckoutCount === 1 ? 'option' : 'options')
                : 'Unavailable',
            'status' => $visibleCheckoutCount > 0 ? 'ready' : 'blocked',
            'statusLabel' => $visibleCheckoutCount > 0 ? 'Live' : 'Missing',
            'icon' => 'cart',
            'optional' => false,
            'detail' => $checkoutStageDetail,
            'actionLabel' => 'Review options',
            'action' => 'flow-checkout',
        ],
        [
            'id' => 'carrier',
            'label' => 'Carrier',
            'value' => $carrierStageValue,
            'status' => $carrierStageStatus,
            'statusLabel' => $routeFedExState,
            'icon' => 'truck',
            'optional' => true,
            'detail' => $carrierStageDetail,
            'actionLabel' => $fedExUiStatus === 'connected' ? 'Manage FedEx' : 'Connect FedEx',
            'action' => 'flow-fedex',
        ],
    ];
    $routeHealthHint = $isReady
        ? 'Required checkout path complete'
        : ($showCheckoutBlocker
            ? 'A required step is blocking delivery'
            : 'Finish the remaining delivery steps so customers can place shippable orders.');

    $setupOriginCurrent = ! $shipFromDone;
    $setupCoverageCurrent = $shipFromDone && ! $deliverToDone;
    $setupCheckoutCurrent = $deliverToDone && ! $checkoutDone;
    $setupOriginHref = route('settings.delivery.setup.ship-from');
    $setupCoverageHref = $shipFromDone
        ? route('settings.delivery.setup.deliver-to')
        : $continueSetupRoute;
    $setupCheckoutHref = $deliverToDone
        ? route('settings.delivery.setup.delivery-option')
        : $continueSetupRoute;
    $setupCarrierHref = $fedExUiStatus === 'connected'
        ? $fedExManageHref
        : ($fedExPlatformEnabled ? $fedExHref : $continueSetupRoute);
    $setupRouteStages = [
        [
            'id' => 'origin',
            'label' => 'Ship from',
            'value' => $defaultLocation?->name ?? 'Add a location',
            'status' => $shipFromDone ? 'ready' : 'warning',
            'statusLabel' => $shipFromDone ? 'Ready' : 'Start here',
            'icon' => 'store',
            'optional' => false,
            'current' => $setupOriginCurrent,
            'detail' => $defaultLocation
                ? (($originAddress ?: 'This ship-from location still needs a complete address.').($originCarrierReady ? '' : ' Carrier rates and labels need a complete origin address.'))
                : 'Add an active ship-from location so orders have a place to ship from.',
            'actionLabel' => $defaultLocation ? 'Review ship-from' : 'Add location',
            'action' => 'continue-setup',
            'href' => $setupOriginHref,
        ],
        [
            'id' => 'coverage',
            'label' => 'Deliver to',
            'value' => $primaryActiveZone?->name ?? 'Choose your coverage',
            'status' => $deliverToDone ? 'ready' : ($setupCoverageCurrent ? 'warning' : 'muted'),
            'statusLabel' => $deliverToDone ? 'Active' : ($setupCoverageCurrent ? 'Next' : 'Waiting'),
            'icon' => 'pin',
            'optional' => false,
            'current' => $setupCoverageCurrent,
            'detail' => $primaryActiveZone
                ? 'Customers in this area can see matching checkout options.'
                : 'Choose the countries or regions where customers can receive orders.',
            'actionLabel' => $primaryActiveZone ? 'Review coverage' : 'Add delivery area',
            'action' => 'continue-setup',
            'href' => $setupCoverageHref,
        ],
        [
            'id' => 'checkout',
            'label' => 'Checkout',
            'value' => $visibleCheckoutCount > 0
                ? $visibleCheckoutCount.' delivery '.($visibleCheckoutCount === 1 ? 'option' : 'options')
                : 'Fixed, free, or FedEx',
            'status' => $checkoutDone ? 'ready' : ($setupCheckoutCurrent ? 'warning' : 'muted'),
            'statusLabel' => $checkoutDone ? 'Live' : ($setupCheckoutCurrent ? 'Next' : 'Waiting'),
            'icon' => 'cart',
            'optional' => false,
            'current' => $setupCheckoutCurrent,
            'detail' => $visibleCheckoutCount > 0
                ? $visibleCheckoutCount.' checkout '.($visibleCheckoutCount === 1 ? 'option is' : 'options are').' available to matching customers.'
                : 'Add a fixed, free, or FedEx live-rate option so customers can choose delivery at checkout.',
            'actionLabel' => $visibleCheckoutCount > 0 ? 'Review options' : 'Add checkout option',
            'action' => 'continue-setup',
            'href' => $setupCheckoutHref,
        ],
        [
            'id' => 'carrier',
            'label' => 'Carrier',
            'value' => $carrierStageValue,
            'status' => $carrierStageStatus,
            'statusLabel' => $routeFedExState,
            'icon' => 'truck',
            'optional' => true,
            'current' => false,
            'detail' => $carrierStageDetail,
            'actionLabel' => $fedExUiStatus === 'connected' ? 'Manage FedEx' : 'Connect FedEx',
            'action' => 'flow-fedex',
            'href' => $setupCarrierHref,
        ],
    ];
    $setupHealthHint = $isReady
        ? 'Review and finish to open the Delivery workspace.'
        : ($setupOriginCurrent
            ? 'Start with the ship-from location, then coverage and checkout.'
            : ($setupCoverageCurrent
                ? 'Next: choose where customers can receive orders.'
                : ($setupCheckoutCurrent
                    ? 'Next: add a checkout option. FedEx can wait.'
                    : 'Review and finish to open the Delivery workspace.')));
@endphp

@include('user_view.partials.delivery_workspace_icons')

<section
    class="dh delivery-ops"
    aria-label="Delivery settings"
    data-fedex-status="{{ $fedExUiStatus }}"
    data-fedex-href="{{ $fedExHref }}"
    data-fedex-manage-url="{{ $fedExManageHref }}"
    data-locations-url="{{ $locationsUrl }}"
    data-packages-url="{{ $packagesUrl }}"
    data-preview-url="{{ $previewUrl }}"
    data-continue-setup-url="{{ $continueSetupRoute }}"
    data-setup-mode="{{ $hasCompletedSetup ? '0' : '1' }}"
>
@unless ($hasCompletedSetup)
    @if ($orphanMethods->isNotEmpty() && $canManage)
        <div class="do-orphan">
            <div>
                <p>{{ $orphanMethods->count() }} unused delivery {{ $orphanMethods->count() === 1 ? 'option is' : 'options are' }} not linked to a delivery area. Manage areas and checkout options.</p>
                <p>{{ $orphanMethods->pluck('name')->filter()->implode(', ') }}</p>
            </div>
            @if ($canDelete)
            <form method="POST" action="{{ route('settings.shipping.methods.cleanup-orphans') }}" data-ui-confirm="Remove unused delivery options that are not linked to a delivery area?" data-ui-confirm-title="Remove unused delivery options?" data-ui-confirm-action="Remove unused options">
                @csrf
                <button type="submit" class="do-btn">Remove unused options</button>
            </form>
            @endif
        </div>
    @endif

    <section class="dh-setup-hero" aria-label="Set up delivery">
        <header class="dh-setup-intro">
            <div>
                <h1 class="dh-setup-title">Set up delivery</h1>
                <p class="dh-setup-lead">Complete these steps once. After you finish, Delivery becomes your day-to-day management workspace for Delivery areas, Delivery options, Fulfillment locations, and FedEx. Ship-from uses fulfillment locations, not your store business address.</p>
            </div>
            <div class="header-actions">
                <div class="route-health is-progress">
                    <div class="health-copy">
                        <strong>Setup in progress</strong>
                        <span>{{ $setupHealthHint }}</span>
                    </div>
                </div>
                @if ($canManage)
                    <a href="{{ $continueSetupRoute }}" class="do-btn do-btn-primary">Continue setup</a>
                @endif
            </div>
        </header>
        @include('user_view.shipping.partials.delivery_route_flow', ['stages' => $setupRouteStages])
        @if ($defaultLocation)
            <div class="do-setup-origin">
                <p>Fulfillment locations</p>
                <strong>{{ $defaultLocation->name }}</strong>
                <div class="chips">
                    @if ($defaultLocation->is_default)
                        <span class="do-pill do-pill-muted no-dot">Default origin</span>
                    @endif
                    @if ($originCarrierReady)
                        <span class="do-pill do-pill-ready no-dot">Carrier-ready</span>
                    @endif
                </div>
                <p>This ship-from location is used for delivery, not your store business address.</p>
            </div>
        @endif
    </section>
@else
    <div class="page-heading">
        <div>
            <h2>Delivery operations</h2>
            <p>Control where orders ship, what customers see at checkout, and how rates are calculated.</p>
        </div>
        <div class="page-actions">
            @if ($canManage)
                <button type="button" class="do-btn do-btn-primary" data-open-drawer="method-add" @if ($allZones->isEmpty()) disabled @endif>
                    <svg class="do-icon" aria-hidden="true"><use href="#do-i-plus"/></svg>
                    Add delivery option
                </button>
            @endif
        </div>
    </div>

    @if ($orphanMethods->isNotEmpty() && $canManage)
        <div class="do-orphan">
            <div>
                <p>{{ $orphanMethods->count() }} unused delivery {{ $orphanMethods->count() === 1 ? 'option is' : 'options are' }} not linked to a delivery area. Manage areas and checkout options.</p>
                <p>{{ $orphanMethods->pluck('name')->filter()->implode(', ') }}</p>
            </div>
            @if ($canDelete)
            <form method="POST" action="{{ route('settings.shipping.methods.cleanup-orphans') }}" data-ui-confirm="Remove unused delivery options that are not linked to a delivery area?" data-ui-confirm-title="Remove unused delivery options?" data-ui-confirm-action="Remove unused options">
                @csrf
                <button type="submit" class="do-btn">Remove unused options</button>
            </form>
            @endif
        </div>
    @endif

    <section class="do-surface route-strip" id="route-strip" aria-labelledby="route-title">
        <header class="route-header">
            <div class="title-row">
                <span class="title-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <use href="#do-i-route" xlink:href="#do-i-route"/>
                    </svg>
                </span>
                <div>
                    <h3 id="route-title">Delivery route</h3>
                    <p>From warehouse to customer — the configuration path used at checkout.</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="route-health {{ $isReady ? '' : 'is-blocked' }}">
                    <div class="health-copy">
                        <strong>{{ $statusBadgeLabel }}</strong>
                        <span>{{ $routeHealthHint }}</span>
                    </div>
                    <span class="health-icon" aria-hidden="true">
                        @if ($isReady)
                            <svg viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 3 2.5 20h19L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4m0 3h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        @endif
                    </span>
                </div>
                <button type="button" class="do-btn do-btn-primary" data-delivery-action="test-checkout">
                    <svg class="do-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 3h6m-5 0v6l-5 9a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3l-5-9V3M8 15h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Test checkout
                </button>
            </div>
        </header>
        @include('user_view.shipping.partials.delivery_route_flow', ['stages' => $routeStages])
    </section>

    @if ($showCheckoutBlocker || $showAttentionHealth)
    <section id="health-region">
        @if ($showCheckoutBlocker)
            <div class="health-banner is-danger">
                <div>
                    <strong>{{ $blockerItems->count() }} {{ $blockerItems->count() === 1 ? 'issue is' : 'issues are' }} blocking some checkouts</strong>
                    <span>{{ $blockerItems->first()['message'] ?? 'A ship-from location, active area, and at least one usable checkout option are required.' }}</span>
                </div>
                @if ($canManage)
                    <a href="{{ $continueSetupRoute }}" class="do-btn do-btn-danger">Continue setup</a>
                @endif
            </div>
        @else
            <div class="health-banner">
                <div>
                    <strong>Needs attention</strong>
                    <span>{{ $healthItems->first()['message'] ?? 'Finish the remaining delivery steps so customers can place shippable orders.' }}</span>
                </div>
                @if ($canManage)
                    <a href="{{ $continueSetupRoute }}" class="do-btn do-btn-primary">Continue setup</a>
                @endif
            </div>
        @endif
    </section>
    @endif

    <div class="management-grid">
        <section class="do-surface" id="delivery-areas" aria-label="Delivery areas">
            <div class="panel-head">
                <div>
                    <div class="panel-title-row">
                        <h3>Delivery areas <span class="do-sr">&amp; options</span></h3>
                        <span class="do-count">{{ $allZones->count() }}</span>
                    </div>
                    <p>Coverage and the options customers see at checkout. <span class="do-sr">Delivery options. Manage areas and checkout options.</span></p>
                </div>
                @if ($canManage)
                    <button type="button" class="do-text-action" data-open-drawer="zone-add">
                        + Add area
                        <span class="do-sr">Add delivery area</span>
                    </button>
                @endif
            </div>

            <div class="area-list">
                @forelse ($allZones as $zone)
                    @php
                        $zoneMethods = $methods->where('shipping_zone_id', $zone->id)->values();
                        $zoneFedExMethods = $zoneMethods->filter(
                            fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()
                        )->values();
                        $zoneFedExSelected = $zoneFedExMethods->filter(fn ($m) => $m->is_active)->values();
                        $zoneFixedMethods = $zoneMethods->reject(
                            fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()
                        )->values();
                        $regionCount = collect($zone->regions)->filter()->count();
                        $countries = collect($zone->countries)->filter()->values();
                        $primaryCountry = strtoupper((string) $countries->first());
                        $coverage = $regionCount > 0
                            ? $regionCount.' selected '.($regionCount === 1 ? 'state' : 'states')
                            : ($countries->isNotEmpty() ? 'Entire country' : 'No country set');
                        $fedExMethodOn = $zoneFedExSelected->contains(fn ($m) => $m->enabled_for_checkout);
                        $fedExCheckoutOn = $zone->is_active && $fedExMethodOn;
                        $selectedFedExCodes = $zoneFedExSelected->pluck('carrier_service_code')->filter()->map(fn ($c) => strtoupper((string) $c))->all();
                    @endphp
                    <article class="area {{ $zone->is_active ? '' : 'is-inactive' }}">
                        <div class="area-top">
                            <div class="area-main">
                                <span class="flag-box" aria-hidden="true">
                                    @if ($primaryCountry === 'US')
                                        <svg class="flag-svg" viewBox="0 0 19 10"><use href="#do-flag-us"/></svg>
                                    @elseif ($primaryCountry === 'CA')
                                        <svg class="flag-svg" viewBox="0 0 19 10"><use href="#do-flag-ca"/></svg>
                                    @else
                                        <span class="flag-letters">{{ $primaryCountry !== '' ? $primaryCountry : '—' }}</span>
                                    @endif
                                </span>
                                <div>
                                    <div class="area-title-row">
                                        <strong>{{ $zone->name }}</strong>
                                        <span class="do-pill {{ $zone->is_active ? 'do-pill-ready' : 'do-pill-muted' }} no-dot">{{ $zone->is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                    <small>{{ $coverage }}</small>
                                </div>
                            </div>
                            <div class="area-actions">
                                @if ($canManage)
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
                                    <button type="button" class="zone-edit-btn do-btn compact-btn"
                                        data-action="{{ route('settings.shipping.zones.update', $zone) }}"
                                        data-zone-form="{{ e(json_encode($zonePresenter->presentationFromZone($zone))) }}">Edit</button>
                                    @if ($canDelete)
                                    <details class="dh-menu">
                                        <summary class="dh-menu-trigger" aria-label="More actions for {{ $zone->name }}">
                                            <svg class="do-icon" aria-hidden="true"><use href="#do-i-more"/></svg>
                                        </summary>
                                        <div class="dh-menu-panel">
                                            <form method="POST" action="{{ route('settings.shipping.zones.destroy', $zone) }}" data-ui-confirm="Remove “{{ $zone->name }}” and its checkout options? This cannot be undone." data-ui-confirm-title="Remove this delivery area?" data-ui-confirm-action="Remove area">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dh-menu-danger">Remove area</button>
                                            </form>
                                        </div>
                                    </details>
                                    @endif
                                @else
                                    <span class="do-pill {{ $zone->is_active ? 'do-pill-ready' : 'do-pill-muted' }}">{{ $zone->is_active ? 'Active' : 'Inactive' }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="options-table">
                            <div class="options-head">
                                <span>Delivery option<span class="do-sr">s</span></span>
                                <span>Customer pays</span>
                                <span>Estimate / services</span>
                                <span>Availability</span>
                            </div>

                            @if ($zoneFedExMethods->isEmpty() && $zoneFixedMethods->isEmpty())
                                <div class="empty-state" style="padding:28px">
                                    <h4>No checkout options</h4>
                                    <p>Add a fixed, free, or FedEx live-rate option for this area.</p>
                                </div>
                            @else
                                @foreach ($zoneFixedMethods as $method)
                                    @php
                                        $priceMode = $method->rate_type === 'free'
                                            ? 'free'
                                            : ((float) ($method->free_over_amount ?? 0) > 0 ? 'free_over' : 'fixed');
                                        $flagMismatch = $method->is_active !== $method->enabled_for_checkout;
                                        $methodCheckoutOn = $method->is_active && $method->enabled_for_checkout;
                                        $optionOn = $zone->is_active && $methodCheckoutOn;
                                    @endphp
                                    <div class="dh-option-row">
                                    <div @class(['option-row', 'is-off' => ! $optionOn])>
                                        <div>
                                            <div class="option-name">{{ $method->name }}</div>
                                            <div class="option-sub">{{ $methodDescLabel($method) }}</div>
                                        </div>
                                        <div>
                                            <span class="metric-label">Customer pays</span>
                                            <span class="metric-value">{{ $methodPriceLabel($method) }}</span>
                                        </div>
                                        <div>
                                            <span class="metric-label">Estimate / services</span>
                                            <span class="metric-value">{{ $methodDaysLabel($method) }}</span>
                                        </div>
                                        <div class="option-availability">
                                            @if ($canManage)
                                                <span class="switch-label">
                                                    <button
                                                        type="button"
                                                        class="dh-switch {{ $methodCheckoutOn ? 'is-on' : '' }}"
                                                        data-availability-toggle
                                                        data-toggle-kind="method"
                                                        data-toggle-url="{{ route('settings.shipping.methods.availability', $method) }}"
                                                        data-available="{{ $methodCheckoutOn ? '1' : '0' }}"
                                                        aria-pressed="{{ $methodCheckoutOn ? 'true' : 'false' }}"
                                                        aria-label="Available at checkout for {{ $method->name }}"
                                                    ></button>
                                                    Available at checkout
                                                </span>
                                                <span class="option-actions">
                                                    <details class="dh-menu">
                                                        <summary class="dh-menu-trigger" aria-label="More actions for {{ $method->name }}">
                                                            <svg class="do-icon" aria-hidden="true"><use href="#do-i-more"/></svg>
                                                        </summary>
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
                                                                data-flag-mismatch="{{ $flagMismatch ? '1' : '0' }}">Edit option</button>
                                                            @if ($canDelete)
                                                            <form method="POST" action="{{ route('settings.shipping.methods.destroy', $method) }}" data-ui-confirm="Remove “{{ $method->name }}”? Customers will no longer see this option at checkout." data-ui-confirm-title="Remove this delivery option?" data-ui-confirm-action="Remove option">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dh-menu-danger">Remove option</button>
                                                            </form>
                                                            @endif
                                                        </div>
                                                    </details>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    </div>
                                @endforeach

                                @if ($zoneFedExMethods->isNotEmpty())
                                    @php
                                        $serviceCount = $zoneFedExSelected->count();
                                        $serviceLabel = $serviceCount.' '.($serviceCount === 1 ? 'service' : 'services').' enabled';
                                        $firstService = $zoneFedExSelected->first();
                                        $serviceDetail = $serviceCount > 0
                                            ? (($firstService->carrier_service_name ?: 'FedEx').($serviceCount > 1 ? ' + '.($serviceCount - 1) : ''))
                                            : 'No services';
                                    @endphp
                                    <div class="dh-option-row">
                                    <div @class(['option-row', 'is-off' => ! $fedExCheckoutOn])>
                                        <div>
                                            <div class="option-name">FedEx live rates</div>
                                            <div class="option-sub">{{ $serviceLabel }} · Rates calculated by FedEx</div>
                                        </div>
                                        <div>
                                            <span class="metric-label">Customer pays</span>
                                            <span class="metric-value">Live rate</span>
                                        </div>
                                        <div>
                                            <span class="metric-label">Estimate / services</span>
                                            <span class="metric-value">{{ $serviceDetail }}</span>
                                        </div>
                                        <div class="option-availability">
                                            @if ($canManage)
                                                <span class="switch-label">
                                                    <button
                                                        type="button"
                                                        class="dh-switch {{ $fedExMethodOn ? 'is-on' : '' }}"
                                                        data-availability-toggle
                                                        data-toggle-kind="fedex-group"
                                                        data-toggle-url="{{ route('settings.shipping.zones.fedex-live-rates.availability', $zone) }}"
                                                        data-available="{{ $fedExMethodOn ? '1' : '0' }}"
                                                        aria-pressed="{{ $fedExMethodOn ? 'true' : 'false' }}"
                                                        aria-label="Available at checkout for FedEx live rates in {{ $zone->name }}"
                                                    ></button>
                                                    Available at checkout
                                                </span>
                                                <span class="option-actions">
                                                    <button
                                                        type="button"
                                                        class="do-btn compact-btn"
                                                        data-open-drawer="fedex-services"
                                                        data-zone-id="{{ $zone->id }}"
                                                        data-zone-name="{{ $zone->name }}"
                                                        data-action="{{ route('settings.shipping.zones.fedex-live-rates.update', $zone) }}"
                                                        data-available="{{ $fedExMethodOn ? '1' : '0' }}"
                                                        data-services="{{ e(json_encode($selectedFedExCodes)) }}"
                                                    >Manage</button>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    </div>
                                @endif
                            @endif

                            @if ($canManage)
                                <button type="button" class="add-inline" data-open-drawer="method-add" data-zone-id="{{ $zone->id }}">+ Add delivery option</button>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty-state">
                        <span class="empty-icon"><svg class="do-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><use href="#do-i-pin" xlink:href="#do-i-pin"/></svg></span>
                        <h4>No delivery areas yet</h4>
                        <p>Add the countries, states, or postal coverage where customers can receive orders.</p>
                        @if ($canManage)
                            <button type="button" class="do-btn do-btn-primary" data-open-drawer="zone-add">Add delivery area</button>
                        @endif
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="do-surface config-panel" id="configuration-panel" aria-label="Delivery configuration">
            <div class="panel-head">
                <div>
                    <h3>Delivery configuration</h3>
                    <p>Key settings for shipping, rates, and fulfillment.</p>
                </div>
            </div>

            <section class="config-section" id="shipping-origin">
                <div class="config-heading">
                    <div class="config-id">
                        <span class="round-icon"><svg class="do-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><use href="#do-i-pin" xlink:href="#do-i-pin"/></svg></span>
                        <h4>Shipping origin</h4>
                    </div>
                    @if ($canManage)
                        <a class="do-btn compact-btn" href="{{ $locationsUrl }}">Edit</a>
                    @endif
                </div>
                <div class="config-main">
                    @if ($defaultLocation)
                        <strong>{{ $defaultLocation->name }}</strong>
                        <p>{{ $originAddress ?: 'Address incomplete' }}</p>
                        <div class="chips">
                            @if ($defaultLocation->is_default)
                                <span class="do-pill do-pill-muted no-dot">Default<span class="do-sr"> origin</span></span>
                            @endif
                            <span class="do-pill {{ $originCarrierReady ? 'do-pill-ready' : 'do-pill-danger' }} no-dot">{{ $originCarrierReady ? 'Ready' : 'Needs attention' }}<span class="do-sr">{{ $originCarrierReady ? 'Carrier-ready' : '' }}</span></span>
                        </div>
                        <p class="do-sr"><a class="do-text-action" href="{{ $locationsUrl }}">Manage fulfillment locations</a></p>
                    @else
                        <div class="provider-state">
                            <strong>No location configured</strong>
                            <p>Add an active ship-from location from Fulfillment locations.</p>
                            @if ($canManage)
                                <a class="do-btn do-btn-primary compact-btn" href="{{ $locationsUrl }}">Add location</a>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            <section class="config-section" id="delivery-fedex">
                <div class="config-heading">
                    <div class="config-id">
                        <span class="fedex-mark" aria-hidden="true">Fed<span>Ex</span></span>
                        <h4>FedEx account</h4>
                    </div>
                    @if ($canManage && $fedExUiStatus === 'connected')
                        <a class="do-btn compact-btn" href="{{ $fedExManageHref }}">Manage</a>
                    @endif
                </div>
                <div class="config-main">
                    @if ($fedExUiStatus === 'connected')
                        <strong>{{ $fedExAccountTitle }}</strong>
                        <p>{{ $fedExDetail }}</p>
                        <div class="chips"><span class="do-pill do-pill-ready no-dot">Connected</span></div>
                        @php
                            $availableCaps = collect([
                                $fedExCaps['checkout'] ? 'rates' : null,
                                $fedExCaps['labels'] ? 'labels' : null,
                                $fedExCaps['tracking'] ? 'tracking' : null,
                            ])->filter()->values();
                            if ($fedExCaps['checkout'] && $fedExCaps['labels'] && $fedExCaps['tracking']) {
                                $capLine = 'Rates, labels & tracking available';
                            } elseif ($availableCaps->isNotEmpty()) {
                                $capLine = \Illuminate\Support\Str::ucfirst($availableCaps->implode(', ')).' available';
                            } else {
                                $capLine = 'Connection needs configuration';
                            }
                        @endphp
                        <div class="cap-line">
                            <svg class="do-icon" aria-hidden="true"><use href="#do-i-check"/></svg>
                            {{ $capLine }}
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
                    @elseif ($fedExUiStatus === 'pending_validation')
                        <div class="provider-state">
                            <div class="panel-title-row">
                                <span class="do-spinner" aria-hidden="true"></span>
                                <strong>Connection in progress</strong>
                            </div>
                            <p>Finish verification to activate this FedEx account.</p>
                            @if ($canManage)
                                <button type="button" class="do-btn compact-btn" data-delivery-action="resume-fedex">Resume setup</button>
                            @endif
                        </div>
                    @elseif ($fedExUiStatus === 'failed')
                        <div class="provider-state is-danger">
                            <strong style="color:#b42318">FedEx needs attention</strong>
                            <p>The last account verification failed. Existing fixed-rate options remain available.</p>
                            @if ($canManage && $fedExAccount)
                                <form method="POST" action="{{ route('settings.shipping.fedex-integrator.reconnect', $fedExAccount) }}">
                                    @csrf
                                    <button type="submit" class="do-btn do-btn-danger compact-btn">Reconnect</button>
                                </form>
                            @endif
                        </div>
                    @elseif ($fedExUiStatus === 'disabled')
                        <div class="provider-state">
                            <strong>FedEx connections unavailable</strong>
                            <p>This environment is not configured for new FedEx connections.</p>
                            <span class="do-pill do-pill-muted no-dot">Unavailable</span>
                        </div>
                    @else
                        <div class="provider-state">
                            <strong>Connect your FedEx account</strong>
                            <p>Add live rates, labels, and tracking. Fixed and free delivery continue to work without FedEx.</p>
                            <div class="chips" style="margin:8px 0">
                                <span class="do-pill do-pill-muted no-dot">Optional</span>
                                <span class="do-pill do-pill-warn no-dot">Not connected</span>
                            </div>
                            @if ($canManage && $fedExPlatformEnabled)
                                <a class="do-btn do-btn-primary compact-btn" href="{{ $fedExHref }}" data-delivery-action="connect-fedex">Connect FedEx</a>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            @if ($showPackages)
                <section class="config-section" id="packages">
                    <div class="config-heading">
                        <div class="config-id">
                            <span class="round-icon"><svg class="do-icon" aria-hidden="true"><use href="#do-i-box"/></svg></span>
                            <h4>Packaging</h4>
                        </div>
                        @if ($canManage)
                            <a class="do-btn compact-btn" href="{{ $packagesUrl }}">Manage</a>
                        @endif
                    </div>
                    <div class="config-main">
                        @if ($defaultPreset)
                            <strong>{{ $defaultPreset->name }}</strong>
                            <p>
                                {{ number_format((float) $defaultPreset->length, 0) }}
                                × {{ number_format((float) $defaultPreset->width, 0) }}
                                × {{ number_format((float) $defaultPreset->height, 0) }}
                                {{ strtolower($defaultPreset->dimension_unit ?: 'in') }}
                                @if ($defaultPreset->is_default)
                                    · Default
                                @endif
                            </p>
                        @else
                            <strong>No default package</strong>
                            <p>Add dimensions for live rates and labels.</p>
                        @endif
                        @php
                            $fallbackWeight = $shippingPreferences['fallback_item_weight'] ?? null;
                            $weightUnitLabel = $shippingPreferences['weight_unit'] ?? 'LB';
                        @endphp
                        @if ($weightFallbackItem)
                            <div class="recommendation">
                                <svg class="do-icon" aria-hidden="true"><use href="#do-i-alert"/></svg>
                                <div>
                                    <p>{{ $weightFallbackItem['message'] ?? '' }}</p>
                                    <a class="do-text-action" href="{{ $weightFallbackItem['action_href'] ?? $productsFallbackUrl }}">{{ $weightFallbackItem['action_label'] ?? 'Review products' }} →</a>
                                </div>
                            </div>
                        @elseif ($fallbackWeight)
                            <p style="margin-top:8px">Fallback: {{ number_format((float) $fallbackWeight, 2) }} {{ $weightUnitLabel }}</p>
                        @else
                            <p style="margin-top:8px">No checkout weight fallback set. <a class="do-text-action" href="{{ $packagesUrl }}#checkout-weight-fallback">Add a fallback item weight</a></p>
                        @endif
                    </div>
                </section>
            @endif
        </aside>
    </div>

    <section class="do-surface preview-bar" id="delivery-troubleshooting">
        <div class="preview-copy">
            <div>
                <h3>Troubleshooting</h3>
                <p>Checks use the same live delivery settings as checkout.</p>
            </div>
        </div>
        <div class="preview-actions">
            <button type="button" class="do-text-action" data-delivery-action="troubleshooting">Open troubleshooting ›</button>
        </div>
    </section>
@endunless
</section>

@if ($hasCompletedSetup && $canManage)
<script type="application/json" id="fedex-services-catalog">@json($fedExServicesCatalog)</script>
@endif

@if ($hasCompletedSetup)
    @push('overlays')
        @include('user_view.shipping.partials.delivery_ops_drawers')
    @endpush
@endif
