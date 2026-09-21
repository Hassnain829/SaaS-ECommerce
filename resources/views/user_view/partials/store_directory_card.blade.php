@php
    $isLive = (bool) $store->onboarding_completed;
    $isActive = $activeStoreId === (int) $store->id;
    $logoUrl = $store->logoPublicUrl();
    $metrics = $storeMetrics[$store->id] ?? [
        'revenue_7d' => 0.0,
        'orders_7d' => 0,
        'orders_change_pct' => null,
        'health' => 'setup',
        'health_label' => 'Setup needed',
        'setup_complete' => false,
    ];
    $canViewProducts = (bool) ($metrics['can_view_products'] ?? false);
    $canViewOrders = (bool) ($metrics['can_view_orders'] ?? false);
    $productsDisplay = $canViewProducts ? number_format((int) ($store->products_count ?? 0)) : '—';
    $ordersDisplay = $canViewOrders ? number_format((int) ($metrics['orders_7d'] ?? 0)) : '—';
    $revenueDisplay = $canViewOrders
        ? \App\Support\MoneyDisplay::format($metrics['revenue_7d'] ?? 0, $store->currency ?: 'USD')
        : '—';
    $needsSetup = ! (bool) ($metrics['setup_complete'] ?? false) || ! $isLive;
    $healthLabel = $metrics['health_label'] ?? 'Setup needed';
    [$avatarBg, $avatarColor] = $avatarPalettes[$store->id % count($avatarPalettes)];
    $memberRole = (string) ($store->pivot->role ?? '');
    $permissions = \App\Support\StorePermissionResolver::permissionsFor(auth()->user(), $store);
    $canManageSettings = in_array(\App\Support\StorePermission::SETTINGS_MANAGE, $permissions, true)
        || auth()->user()?->hasStorePermission($store, 'settings.locations')
        || auth()->user()?->hasStorePermission($store, 'settings.taxes');
    $canManageCatalog = auth()->user()?->hasStorePermission($store, 'products.edit') ?? false;
    $canClose = $memberRole === \App\Models\Store::ROLE_OWNER;
    $storeActionPayload = [
        'id' => $store->id,
        'name' => $store->name,
        'contact_email' => $store->settings['contact_email'] ?? '',
        'primary_market' => $store->settings['primary_market'] ?? 'Global Market',
        'currency' => $store->currency,
        'timezone' => $store->timezone,
        'address' => $store->address,
        'category' => $store->category,
        'custom_category' => $store->settings['custom_category'] ?? '',
        'business_models' => $store->settings['business_models'] ?? [],
        'logo_url' => $logoUrl,
        'update_url' => route('store.update', ['storeId' => $store->id]),
        'delete_url' => route('store.destroy', ['storeId' => $store->id]),
        'redirect_to' => 'store-management',
        'allow_delete' => $canClose,
        'hide_primary_market' => false,
        'requires_catalog_conversion' => (bool) (($storesNeedingCurrencyConversion[$store->id] ?? false)),
    ];
    $primaryLabel = $needsSetup ? 'Continue setup →' : 'Open workspace →';
    $listPrimaryLabel = $needsSetup ? 'Continue →' : 'Open →';
    $layout = $layout ?? 'grid';
@endphp

@if ($layout === 'grid')
    <article
        class="js-store-card sd-card {{ $isActive ? 'is-current' : '' }}"
        style="--sd-avatar-bg: {{ $avatarBg }}; --sd-avatar-color: {{ $avatarColor }}"
        data-store-id="{{ $store->id }}"
        data-store-status="{{ $isLive ? 'live' : 'draft' }}"
        data-store-name="{{ $store->name }}"
        data-store-type="{{ $typeFilterValue($store) }}"
        data-needs-setup="{{ $needsSetup ? '1' : '0' }}"
        data-revenue="{{ $canViewOrders ? (float) ($metrics['revenue_7d'] ?? 0) : 0 }}"
        data-orders="{{ $canViewOrders ? (int) ($metrics['orders_7d'] ?? 0) : 0 }}"
        data-products="{{ $canViewProducts ? (int) ($store->products_count ?? 0) : 0 }}"
        x-show="onPage($el)"
        x-cloak
    >
        <div class="sd-card-top">
            <div class="sd-avatar">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $store->name }} logo">
                @else
                    {{ $storeInitials($store->name) }}
                @endif
            </div>
            <div class="sd-title">
                <div class="sd-name-line">
                    <span class="sd-name">{{ $store->name }}</span>
                    @if ($isActive)
                        <span class="sd-current" title="This is the store currently selected in your sidebar">Current</span>
                    @endif
                </div>
                <div class="sd-meta">{{ $categoryLabel($store) }} · {{ strtoupper($store->currency ?: 'USD') }}</div>
            </div>
            <div class="relative">
                <button
                    type="button"
                    class="sd-more"
                    @click.stop="openMenu = openMenu === {{ $store->id }} ? null : {{ $store->id }}"
                    :aria-expanded="openMenu === {{ $store->id }}"
                    aria-label="More actions for {{ $store->name }}"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                </button>
                <div class="sd-menu" x-show="openMenu === {{ $store->id }}" x-cloak role="menu">
                    @include('user_view.partials.store_directory_menu')
                </div>
            </div>
        </div>
        <div class="sd-status-line">
            @if ($needsSetup)
                <span class="sd-status is-attention"><span class="sd-dot"></span>{{ $healthLabel }}</span>
            @else
                <span class="sd-status"><span class="sd-dot"></span>{{ $healthLabel }}</span>
            @endif
        </div>
        <div class="sd-card-metrics">
            <div class="sd-card-metric">
                <div class="sd-card-metric-label">Products</div>
                <div class="sd-card-metric-value">{{ $productsDisplay }}</div>
            </div>
            <div class="sd-card-metric">
                <div class="sd-card-metric-label">Orders · 7d</div>
                <div class="sd-card-metric-value">{{ $ordersDisplay }}</div>
            </div>
            <div class="sd-card-metric">
                <div class="sd-card-metric-label">Revenue · 7d</div>
                <div class="sd-card-metric-value">{{ $revenueDisplay }}</div>
            </div>
        </div>
        <div class="sd-card-actions">
            @include('user_view.partials.store_switch_action', [
                'label' => $primaryLabel,
                'class' => 'sd-action',
                'isActive' => $isActive,
                'redirectTo' => 'dashboard',
                'href' => route('dashboard'),
            ])
            <a href="{{ route('store.products', ['storeId' => $store->id]) }}" class="sd-secondary">Catalog</a>
        </div>
    </article>
@else
    <article
        class="js-store-card sd-list-row {{ $isActive ? 'is-current' : '' }}"
        style="--sd-avatar-bg: {{ $avatarBg }}; --sd-avatar-color: {{ $avatarColor }}"
        data-store-id="{{ $store->id }}"
        data-store-status="{{ $isLive ? 'live' : 'draft' }}"
        data-store-name="{{ $store->name }}"
        data-store-type="{{ $typeFilterValue($store) }}"
        data-needs-setup="{{ $needsSetup ? '1' : '0' }}"
        data-revenue="{{ $canViewOrders ? (float) ($metrics['revenue_7d'] ?? 0) : 0 }}"
        data-orders="{{ $canViewOrders ? (int) ($metrics['orders_7d'] ?? 0) : 0 }}"
        data-products="{{ $canViewProducts ? (int) ($store->products_count ?? 0) : 0 }}"
        x-show="onPage($el)"
        x-cloak
    >
        <div class="sd-list-store">
            <div class="sd-avatar">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $store->name }} logo">
                @else
                    {{ $storeInitials($store->name) }}
                @endif
            </div>
            <div class="sd-title">
                <div class="sd-name-line">
                    <span class="sd-name">{{ $store->name }}</span>
                    @if ($isActive)
                        <span class="sd-current">Current</span>
                    @endif
                </div>
                <div class="sd-meta">{{ $categoryLabel($store) }} · {{ strtoupper($store->currency ?: 'USD') }}</div>
            </div>
        </div>
        <div>
            @if ($needsSetup)
                <span class="sd-status is-attention"><span class="sd-dot"></span>{{ $healthLabel }}</span>
            @else
                <span class="sd-status"><span class="sd-dot"></span>{{ $healthLabel }}</span>
            @endif
        </div>
        <div class="sd-numeric font-semibold">{{ $productsDisplay }}</div>
        <div class="sd-numeric font-semibold">{{ $ordersDisplay }}</div>
        <div class="sd-numeric font-semibold">{{ $revenueDisplay }}</div>
        <div class="sd-list-action">
            @include('user_view.partials.store_switch_action', [
                'label' => $listPrimaryLabel,
                'class' => 'sd-action',
                'isActive' => $isActive,
                'redirectTo' => 'dashboard',
                'href' => route('dashboard'),
            ])
            <div class="relative">
                <button
                    type="button"
                    class="sd-more"
                    @click.stop="openMenu = openMenu === {{ $store->id }} ? null : {{ $store->id }}"
                    aria-label="More actions for {{ $store->name }}"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                </button>
                <div class="sd-menu" x-show="openMenu === {{ $store->id }}" x-cloak role="menu">
                    @include('user_view.partials.store_directory_menu')
                </div>
            </div>
        </div>
    </article>
@endif
