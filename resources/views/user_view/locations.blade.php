@extends('layouts.user.user-sidebar')

@section('title', 'Locations — '.config('app.name'))

@php
    use App\Support\Tax\TaxCountryCatalog;

    $typeLabels = [
        'warehouse' => 'Warehouse',
        'store' => 'Store / shop',
        'third_party' => 'Third-party storage',
        'other' => 'Other',
    ];
    $countries = $countries ?? TaxCountryCatalog::all();
    $fedExConnectPhone = trim((string) ($fedExConnectPhone ?? ''));
    $shipFromPhoneBoundFromFedEx = $fedExConnectPhone !== '';
    $locationMetrics = $locationMetrics ?? ['total' => 0, 'active' => 0, 'ship_from_ready' => 0, 'pickup' => 0];
    $selectedLocationId = (int) ($selectedLocationId ?? 0);
    $activeLocationCount = (int) $locations->where('is_active', true)->count();
    $openLocationModal = $errors->any() && ! $errors->has('location');
    $locationFormMode = old('location_form', 'add') === 'edit' ? 'edit' : 'add';
    $locationRegionCatalog = [];
    foreach (array_keys($countries) as $catalogCountryCode) {
        $locationRegionCatalog[$catalogCountryCode] = TaxCountryCatalog::regionsFor($catalogCountryCode);
    }
    $locationEditorPayload = $locations->mapWithKeys(function ($location) use ($typeLabels) {
        return [$location->id => [
            'id' => $location->id,
            'name' => $location->name,
            'type' => $location->type,
            'address_line1' => $location->address_line1,
            'address_line2' => $location->address_line2,
            'city' => $location->city,
            'state' => $location->state,
            'postal_code' => $location->postal_code,
            'country_code' => $location->country_code,
            'phone' => $location->phone,
            'fulfills_online_orders' => (bool) $location->fulfills_online_orders,
            'pickup_enabled' => (bool) $location->pickup_enabled,
            'routing_priority' => (int) ($location->routing_priority ?? 100),
            'update_url' => route('settings.locations.update', $location),
        ]];
    });
@endphp

@section('topbar')
    <x-ui.merchant-topbar title="Locations" lead="Places where your store keeps inventory and fulfills orders.">
        @if ($canManageLocations)
            <x-slot:actions>
                <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-hover" data-lc-open-add>
                    <span aria-hidden="true">+</span>
                    <span class="hidden sm:inline">Add location</span>
                </button>
            </x-slot:actions>
        @endif
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div
        class="locations-console settings-workspace-fluid"
        x-data="{
            selectedId: {{ $selectedLocationId }},
            query: '',
            status: 'all',
            menuOpen: false,
            checklistOpen: false,
            matches(el) {
                const q = this.query.trim().toLowerCase();
                const hay = (el.getAttribute('data-search') || '').toLowerCase();
                const st = el.getAttribute('data-status') || '';
                return (!q || hay.includes(q)) && (this.status === 'all' || st === this.status);
            },
            visibleCount() {
                return [...this.$el.querySelectorAll('[data-location-item]')].filter((el) => this.matches(el)).length;
            },
            select(id) {
                this.selectedId = Number(id);
                this.menuOpen = false;
                this.checklistOpen = false;
                const url = new URL(window.location.href);
                url.searchParams.set('location', String(id));
                history.replaceState({}, '', url);
            }
        }"
        @click="if (!$event.target.closest('[data-lc-menu]')) menuOpen = false"
    >
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        @unless ($canManageLocations)
            <div class="mb-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
                You can view locations. Store owners manage location changes.
            </div>
        @endunless

        <section>
            <div class="lc-intro">
                <h2>Location overview</h2>
                <p>Manage inventory sites, fulfillment routing, and ship-from readiness.</p>
            </div>
            <div class="lc-health">
                <div class="lc-health-head">
                    <div class="lc-eyebrow">Location health</div>
                    <div class="lc-scope">{{ $selectedStore->name }}</div>
                </div>
                <div class="lc-metrics">
                    <div class="lc-metric">
                        <div class="lc-metric-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 21V7l7-4 7 4v14M3 21h18M9 9h2M13 9h2M9 13h2M13 13h2M10 21v-4h4v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                        </div>
                        <div>
                            <div class="lc-metric-label">Locations</div>
                            <div class="lc-metric-value">{{ $locationMetrics['total'] }}</div>
                            <div class="lc-metric-note">Total locations</div>
                        </div>
                    </div>
                    <div class="lc-metric">
                        <div class="lc-metric-icon is-green" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.9"/><path d="m8 12 2.7 2.7L16.5 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <div class="lc-metric-label">Active</div>
                            <div class="lc-metric-value">{{ $locationMetrics['active'] }}</div>
                            <div class="lc-metric-note">Available for inventory</div>
                        </div>
                    </div>
                    <div class="lc-metric">
                        <div class="lc-metric-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7zM7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM18 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        </div>
                        <div>
                            <div class="lc-metric-label">Ship-from ready</div>
                            <div class="lc-metric-value">{{ $locationMetrics['ship_from_ready'] }}</div>
                            <div class="lc-metric-note">Carrier-ready address</div>
                        </div>
                    </div>
                    <div class="lc-metric">
                        <div class="lc-metric-icon is-amber" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 10.5V20h16v-9.5M3 9l2-5h14l2 5M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0M9 20v-5h6v5" stroke="currentColor" stroke-width="1.8"/></svg>
                        </div>
                        <div>
                            <div class="lc-metric-label">Customer pickup</div>
                            <div class="lc-metric-value">{{ $locationMetrics['pickup'] }}</div>
                            <div class="lc-metric-note">{{ $locationMetrics['pickup'] > 0 ? 'Pickup locations enabled' : 'No pickup locations' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="lc-boundary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v6M12 7h.01" stroke="currentColor" stroke-width="2"/></svg>
            <p>Locations hold inventory and provide ship-from addresses. They do not control customer markets, currencies, or storefront availability. Customer delivery coverage is managed in Delivery.</p>
            <a href="{{ route('shippingAutomation') }}">Open Delivery →</a>
        </div>

        <section class="lc-workspace" aria-label="Location management">
            <aside class="lc-directory">
                <div class="lc-directory-head">
                    <h2>Your locations</h2>
                    <span class="lc-count">{{ $locations->count() }}</span>
                </div>
                <div class="lc-tools">
                    <div class="lc-search">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="2"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="2"/></svg>
                        <label class="sr-only" for="location-search">Search locations</label>
                        <input id="location-search" type="search" placeholder="Search locations..." autocomplete="off" x-model="query">
                    </div>
                    <label class="sr-only" for="location-status-filter">Status</label>
                    <select id="location-status-filter" x-model="status">
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="lc-list" aria-live="polite">
                    @forelse ($locations as $location)
                        @php
                            $searchHaystack = strtolower(trim(implode(' ', array_filter([
                                $location->name,
                                $typeLabels[$location->type] ?? $location->type,
                                $location->address_line1,
                                $location->city,
                                $location->state,
                                $location->postal_code,
                                $location->country_code,
                            ]))));
                            $addressLine1 = filled($location->address_line1) ? $location->address_line1 : 'No address saved';
                            $addressLine2 = collect([$location->city, $location->state, $location->postal_code, $location->country_code])->filter()->implode(', ');
                            $readiness = $originReadinessByLocationId[$location->id] ?? null;
                        @endphp
                        <button
                            type="button"
                            class="lc-item"
                            data-location-item
                            data-search="{{ $searchHaystack }}"
                            data-status="{{ $location->is_active ? 'active' : 'inactive' }}"
                            :class="selectedId === {{ $location->id }} ? 'is-selected' : ''"
                            x-show="matches($el)"
                            @click="select({{ $location->id }})"
                        >
                            <span class="lc-item-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 21V7l7-4 7 4v14M3 21h18M9 9h2M13 9h2M9 13h2M13 13h2M10 21v-4h4v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            </span>
                            <span class="lc-item-main">
                                <span class="lc-item-name">{{ $location->name }}</span>
                                <span class="lc-item-type">{{ $typeLabels[$location->type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $location->type)) }}</span>
                                <span class="lc-item-address">{{ $addressLine1 }}@if ($addressLine2)<br>{{ $addressLine2 }}@endif</span>
                                <span class="lc-badges">
                                    @if ($location->is_default)
                                        <span class="lc-badge is-green">Default</span>
                                    @endif
                                    <span class="lc-badge {{ $location->is_active ? 'is-green' : '' }}">{{ $location->is_active ? 'Active' : 'Inactive' }}</span>
                                    @if ($location->is_active && $readiness && ! $readiness->ready)
                                        <span class="lc-badge is-amber">{{ $readiness->badgeLabel }}</span>
                                    @endif
                                </span>
                                <span class="lc-item-stock">{{ $location->inventory_levels_count }} inventory {{ \Illuminate\Support\Str::plural('item', $location->inventory_levels_count) }}</span>
                            </span>
                            <span class="lc-item-chevron" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m9 18 6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </span>
                        </button>
                    @empty
                        <div class="lc-empty">
                            <strong>No locations yet</strong>
                            Add a warehouse, shop, or storage site to hold inventory.
                        </div>
                    @endforelse
                    <div class="lc-empty" x-show="visibleCount() === 0 && {{ $locations->count() }} > 0" x-cloak>
                        <strong>No matching locations</strong>
                        Try another search or status.
                    </div>
                </div>
                <div class="lc-directory-footer">
                    Showing <span x-text="visibleCount()">{{ $locations->count() }}</span> {{ $locations->count() === 1 ? 'location' : 'locations' }}
                </div>
            </aside>

            <div class="lc-detail">
                @forelse ($locations as $location)
                    @php
                        $readiness = $originReadinessByLocationId[$location->id] ?? null;
                        $carrierReady = (bool) ($readiness?->ready ?? false);
                        $fullAddress = collect([
                            $location->address_line1,
                            $location->address_line2,
                            $location->city,
                            $location->state,
                            $location->postal_code,
                            $location->country_code,
                        ])->filter()->implode(', ');
                        $countryCode = strtoupper((string) $location->country_code);
                        $checklist = [
                            ['Street address', filled($location->address_line1)],
                            ['City', filled($location->city)],
                            ['State / province', $countryCode !== 'US' || filled($location->state)],
                            ['Postal / ZIP', filled($location->postal_code)],
                        ];
                        $canDeactivate = $location->is_active && ! $location->is_default && $activeLocationCount > 1;
                        $deactivateReason = $location->is_default
                            ? 'Choose another default location first.'
                            : ($activeLocationCount <= 1 ? 'Keep at least one active location.' : '');
                        $phoneReady = $shipFromPhoneBoundFromFedEx || filled($location->phone);
                        $phoneCopy = $shipFromPhoneBoundFromFedEx
                            ? 'Phone supplied by your FedEx connection'
                            : (filled($location->phone) ? $location->phone : 'No carrier phone supplied');
                    @endphp
                    <div x-show="selectedId === {{ $location->id }}" x-cloak>
                        <div class="lc-detail-top">
                            <div>
                                <div class="lc-eyebrow">Selected location</div>
                                <div class="lc-detail-title">{{ $location->name }}</div>
                                <div class="lc-detail-address">{{ $fullAddress !== '' ? $fullAddress : 'No address saved' }}</div>
                                <div class="lc-badges">
                                    @if ($location->is_default)
                                        <span class="lc-badge is-green">Default</span>
                                    @endif
                                    <span class="lc-badge {{ $location->is_active ? 'is-green' : '' }}">{{ $location->is_active ? 'Active' : 'Inactive' }}</span>
                                    @if ($location->is_active && $readiness && ! $readiness->ready)
                                        <span class="lc-badge is-amber">{{ $readiness->badgeLabel }}</span>
                                    @endif
                                </div>
                            </div>
                            @if ($canManageLocations)
                                <div class="lc-detail-actions">
                                    <button type="button" class="inline-flex h-8 items-center rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm font-semibold text-[#344054] hover:bg-[#F8F9FA]" data-lc-edit="{{ $location->id }}">Edit location</button>
                                    <div class="lc-menu-wrap" data-lc-menu>
                                        <button type="button" class="lc-icon-btn" @click.stop="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-label="More actions">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                                        </button>
                                        <div class="lc-menu" x-show="menuOpen" x-cloak @click.stop>
                                            @if (! $location->is_default && $location->is_active)
                                                <form method="POST" action="{{ route('settings.locations.make-default', $location) }}">
                                                    @csrf
                                                    <button type="submit">Make default</button>
                                                </form>
                                            @endif
                                            <form
                                                method="POST"
                                                action="{{ route('settings.locations.deactivate', $location) }}"
                                                @if ($location->is_active && $canDeactivate)
                                                    data-ui-confirm="This location will stop being used for inventory and fulfillment."
                                                    data-ui-confirm-title="Deactivate this location?"
                                                    data-ui-confirm-action="Deactivate"
                                                    data-ui-confirm-cancel="Keep active"
                                                @endif
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" @disabled($location->is_active && ! $canDeactivate)>{{ $location->is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                            @if ($location->is_active && ! $canDeactivate)
                                                <div class="lc-menu-hint">{{ $deactivateReason }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="lc-readiness {{ $carrierReady ? '' : 'is-attention' }}">
                            <span class="lc-readiness-icon" aria-hidden="true">
                                @if ($carrierReady)
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="m8 12 2.7 2.7L16.5 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                @else
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v6M12 17h.01" stroke="currentColor" stroke-width="2"/></svg>
                                @endif
                            </span>
                            <span class="lc-readiness-copy">
                                <span class="lc-readiness-title">{{ $carrierReady ? 'Ready for carrier labels' : 'Ship-from details incomplete' }}</span>
                                <span class="lc-readiness-text">{{ $carrierReady ? 'This address has the required ship-from details.' : ($readiness->merchantMessage ?? 'Complete the address before using this location for carrier labels.') }}</span>
                            </span>
                            <button type="button" class="lc-readiness-btn" @click="checklistOpen = !checklistOpen">
                                <span x-show="!checklistOpen">{{ $carrierReady ? 'View details' : 'Review fields' }} →</span>
                                <span x-show="checklistOpen" x-cloak>Hide details ↑</span>
                            </button>
                        </div>
                        <div class="lc-checklist" x-show="checklistOpen" x-cloak>
                            @foreach ($checklist as $check)
                                <span class="lc-check {{ $check[1] ? 'is-ok' : 'is-missing' }}">
                                    @if ($check[1])
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="m8 12 2.7 2.7L16.5 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    @else
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v6M12 17h.01" stroke="currentColor" stroke-width="2"/></svg>
                                    @endif
                                    {{ $check[0] }}
                                </span>
                            @endforeach
                        </div>

                        <div class="lc-cards">
                            <section class="lc-card">
                                <h3>Fulfillment</h3>
                                <div class="lc-row">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8"/></svg>
                                    <span>Online orders</span>
                                    <span class="lc-row-value">{{ $location->fulfills_online_orders ? 'Enabled' : 'Disabled' }}</span>
                                </div>
                                <div class="lc-row">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z" stroke="currentColor" stroke-width="1.7"/></svg>
                                    <span>Routing priority</span>
                                    <span class="lc-row-value">{{ $location->routing_priority ?? 100 }}</span>
                                </div>
                                <div class="lc-row">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10.5V20h16v-9.5M3 9l2-5h14l2 5M9 20v-5h6v5" stroke="currentColor" stroke-width="1.8"/></svg>
                                    <span>Store pickup</span>
                                    <span class="lc-row-value">{{ $location->pickup_enabled ? 'On' : 'Off' }}</span>
                                </div>
                            </section>
                            <section class="lc-card">
                                <h3>Inventory</h3>
                                <div class="lc-row">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 7 8-4 8 4v10l-8 4-8-4zM4 7l8 4 8-4M12 11v10" stroke="currentColor" stroke-width="1.8"/></svg>
                                    <span>Inventory records</span>
                                    <span class="lc-row-value">{{ $location->inventory_levels_count }}</span>
                                </div>
                                <div class="lc-row">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><ellipse cx="12" cy="5" rx="8" ry="3" stroke="currentColor" stroke-width="1.8"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" stroke="currentColor" stroke-width="1.8"/></svg>
                                    <span>Default stock location</span>
                                    <span class="lc-row-value">{{ $location->is_default ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="lc-row">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 21V7l7-4 7 4v14M3 21h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    <span>Location type</span>
                                    <span class="lc-row-value">{{ $typeLabels[$location->type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $location->type)) }}</span>
                                </div>
                            </section>
                        </div>

                        <section class="lc-pickup">
                            <div class="lc-pickup-head">
                                <h3>Pickup services</h3>
                                <span class="lc-badge is-blue">Location setting</span>
                            </div>
                            <div class="lc-pickup-row">
                                <span class="lc-pickup-icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 10.5V20h16v-9.5M3 9l2-5h14l2 5M9 20v-5h6v5" stroke="currentColor" stroke-width="1.8"/></svg>
                                </span>
                                <span>
                                    <span class="lc-pickup-name">Customer pickup</span>
                                    <span class="lc-pickup-copy">Let customers collect orders from this location.</span>
                                </span>
                                @if ($canManageLocations)
                                    <form method="POST" action="{{ route('settings.locations.pickup', $location) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="lc-toggle {{ $location->pickup_enabled ? 'is-on' : '' }}"
                                            role="switch"
                                            aria-checked="{{ $location->pickup_enabled ? 'true' : 'false' }}"
                                            aria-label="Customer pickup"
                                        ></button>
                                    </form>
                                @else
                                    <span class="lc-row-value">{{ $location->pickup_enabled ? 'On' : 'Off' }}</span>
                                @endif
                            </div>
                            <div class="lc-pickup-row">
                                <span class="lc-pickup-icon is-muted" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z" stroke="currentColor" stroke-width="1.7"/></svg>
                                </span>
                                <span>
                                    <span class="lc-pickup-name">FedEx carrier pickup</span>
                                    <span class="lc-pickup-copy">Schedule a FedEx driver collection from this location.</span>
                                </span>
                                <span class="lc-soon">Coming soon</span>
                            </div>
                        </section>

                        <div class="lc-contact">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3 4 5c0 8 7 15 15 15l2-3-5-3-2 2c-3-1-5-3-6-6l2-2z" stroke="currentColor" stroke-width="1.8"/></svg>
                            <span>
                                <span class="lc-pickup-name">Ship-from contact</span>
                                <span class="lc-pickup-copy">{{ $phoneCopy }}</span>
                            </span>
                            <span class="lc-available {{ $phoneReady ? '' : 'is-missing' }}">
                                @if ($phoneReady)
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="m8 12 2.7 2.7L16.5 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                @else
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v6M12 17h.01" stroke="currentColor" stroke-width="2"/></svg>
                                @endif
                                {{ $phoneReady ? 'Available' : 'Missing' }}
                            </span>
                        </div>

                        <div class="lc-footer">
                            <span class="lc-updated">Last updated {{ $location->updated_at?->diffForHumans() ?? 'recently' }}</span>
                            @if ($canManageLocations)
                                <span class="lc-footer-actions">
                                    <form
                                        method="POST"
                                        action="{{ route('settings.locations.deactivate', $location) }}"
                                        @if ($location->is_active && $canDeactivate)
                                            data-ui-confirm="This location will stop being used for inventory and fulfillment."
                                            data-ui-confirm-title="Deactivate this location?"
                                            data-ui-confirm-action="Deactivate"
                                            data-ui-confirm-cancel="Keep active"
                                        @endif
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm font-semibold text-[#344054] hover:bg-[#F8F9FA] disabled:cursor-not-allowed disabled:opacity-50"
                                            @disabled($location->is_active && ! $canDeactivate)
                                            title="{{ $deactivateReason }}"
                                        >
                                            @if ($location->is_active)
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="1.8"/></svg>
                                            @endif
                                            {{ $location->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <button type="button" class="inline-flex h-8 items-center rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-hover" data-lc-edit="{{ $location->id }}">Edit location</button>
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="lc-empty">
                        <strong>No location selected</strong>
                        Add a location to begin.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection

@if ($canManageLocations)
    @push('overlays')
        <div id="locationEditorModal" class="ui-modal-shell hidden locations-console-modal" role="dialog" aria-modal="true" aria-labelledby="locationModalTitle">
            <div class="ui-modal-panel ui-modal-panel--xl">
                <div class="flex items-start justify-between gap-4 border-b border-[#E2E8F0] px-5 py-4">
                    <div>
                        <h2 id="locationModalTitle" class="text-section font-semibold text-[#0F172A]">{{ $locationFormMode === 'edit' ? 'Edit location' : 'Add location' }}</h2>
                        <p id="locationModalCopy" class="mt-1 text-sm text-[#64748B]">{{ $locationFormMode === 'edit' ? 'Update inventory and fulfillment settings.' : 'Create an inventory and fulfillment location.' }}</p>
                    </div>
                    <button type="button" class="lc-icon-btn" data-lc-close-modal aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </button>
                </div>
                <form id="locationEditorForm" method="POST" action="{{ route('settings.locations.store') }}" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <input type="hidden" name="_method" id="locationEditorMethod" value="PATCH" @disabled($locationFormMode !== 'edit')>
                    <input type="hidden" name="location_form" id="locationEditorMode" value="{{ $locationFormMode }}">
                    <input type="hidden" name="location_id" id="locationEditorId" value="{{ old('location_id') }}">
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                        <section class="form-section">
                            <h3 class="text-sm font-semibold text-[#0F172A]">Location details</h3>
                            <div class="lc-form-grid mt-3">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-[#64748B]">Name</span>
                                    <input id="locationName" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="Main warehouse" class="h-10 w-full rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-[#64748B]">Type</span>
                                    <select id="locationType" name="type" class="h-10 w-full rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm">
                                        @foreach ($locationTypes as $type)
                                            <option value="{{ $type }}" @selected(old('type', 'warehouse') === $type)>{{ $typeLabels[$type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $type)) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </section>
                        <section class="form-section mt-4 border-t border-[#E2E8F0] pt-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">Address</h3>
                            <p class="mt-1 text-xs text-[#64748B]">Required when this location fulfills online orders.</p>
                            <div class="lc-form-grid mt-3" data-location-address-fields>
                                <label class="space-y-1 full">
                                    <span class="text-xs font-semibold text-[#64748B]">Address line 1</span>
                                    <input id="locationAddress1" name="address_line1" value="{{ old('address_line1') }}" placeholder="738 Fawn Valley Dr" class="h-10 w-full rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm">
                                </label>
                                <label class="space-y-1 full">
                                    <span class="text-xs font-semibold text-[#64748B]">Address line 2 <span class="font-normal text-[#98A2B3]">(optional)</span></span>
                                    <input id="locationAddress2" name="address_line2" value="{{ old('address_line2') }}" class="h-10 w-full rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-[#64748B]">City</span>
                                    <input id="locationCity" name="city" value="{{ old('city') }}" class="h-10 w-full rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm">
                                </label>
                                <x-geo.country-select name="country_code" id="location-editor-country" :selected="old('country_code', 'US')" :countries="$countries" />
                                <x-geo.region-select name="state" id="location-editor-state" :country-code="old('country_code', 'US')" :selected="old('state', '')" />
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-[#64748B]">Postal / ZIP code</span>
                                    <input id="locationPostal" name="postal_code" value="{{ old('postal_code') }}" placeholder="75002" class="h-10 w-full rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm">
                                </label>
                            </div>
                        </section>
                        <section class="form-section mt-4 border-t border-[#E2E8F0] pt-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">Fulfillment</h3>
                            <p class="mt-1 text-xs text-[#64748B]">These settings control how this location participates in orders.</p>
                            <div class="lc-form-grid mt-3">
                                <label class="lc-check-row full">
                                    <input type="hidden" name="fulfills_online_orders" value="0">
                                    <input id="locationOnline" type="checkbox" name="fulfills_online_orders" value="1" @checked(old('fulfills_online_orders', '1'))>
                                    <span>
                                        <strong>Fulfill online orders</strong>
                                        <span>Use inventory at this location for eligible online orders.</span>
                                    </span>
                                </label>
                                <label class="lc-check-row full">
                                    <input type="hidden" name="pickup_enabled" value="0">
                                    <input id="locationPickup" type="checkbox" name="pickup_enabled" value="1" @checked(old('pickup_enabled'))>
                                    <span>
                                        <strong>Offer customer pickup</strong>
                                        <span>Let customers collect their orders from this location. This does not require FedEx pickup scheduling.</span>
                                    </span>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold text-[#64748B]">Routing priority</span>
                                    <input id="locationPriority" name="routing_priority" type="number" min="1" max="9999" value="{{ old('routing_priority', 100) }}" class="h-10 w-full rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm">
                                </label>
                                <div class="space-y-1">
                                    <span class="text-xs font-semibold text-[#64748B]">Ship-from phone</span>
                                    @if ($shipFromPhoneBoundFromFedEx)
                                        <div class="lc-phone-note">Supplied by FedEx connection</div>
                                        <input type="hidden" name="phone" value="">
                                    @else
                                        <input id="locationPhone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="Ship-from phone for this location" class="h-10 w-full rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm">
                                    @endif
                                </div>
                            </div>
                        </section>
                        <section class="form-section mt-4 border-t border-[#E2E8F0] pt-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">Carrier pickup</h3>
                            <div class="lc-disabled-service mt-3">
                                <div>
                                    <strong>FedEx pickup scheduling</strong>
                                    <p>Schedule a FedEx driver collection from this location.</p>
                                </div>
                                <span class="lc-soon">Coming soon</span>
                            </div>
                        </section>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-[#E2E8F0] px-5 py-4">
                        <button type="button" class="inline-flex h-10 items-center rounded-lg border border-[#CFD5DC] bg-white px-4 text-sm font-semibold text-[#344054] hover:bg-[#F8F9FA]" data-lc-close-modal>Cancel</button>
                        <button type="submit" id="locationEditorSubmit" class="inline-flex h-10 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white hover:bg-brand-hover">{{ $locationFormMode === 'edit' ? 'Save changes' : 'Add location' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush
@endif

@push('scripts')
    <script type="application/json" id="location-region-catalog">@json($locationRegionCatalog)</script>
    <script type="application/json" id="location-editor-payload">@json($locationEditorPayload)</script>
    <script>
    (function () {
        var regionCatalog = {};
        var payload = {};
        try {
            var catalogEl = document.getElementById('location-region-catalog');
            if (catalogEl) regionCatalog = JSON.parse(catalogEl.textContent || '{}');
        } catch (e) {}
        try {
            var payloadEl = document.getElementById('location-editor-payload');
            if (payloadEl) payload = JSON.parse(payloadEl.textContent || '{}');
        } catch (e) {}

        var modal = document.getElementById('locationEditorModal');
        var form = document.getElementById('locationEditorForm');
        if (! modal || ! form) {
            return;
        }

        var storeUrl = @json(route('settings.locations.store'));
        var methodInput = document.getElementById('locationEditorMethod');
        var modeInput = document.getElementById('locationEditorMode');
        var idInput = document.getElementById('locationEditorId');
        var titleEl = document.getElementById('locationModalTitle');
        var copyEl = document.getElementById('locationModalCopy');
        var submitEl = document.getElementById('locationEditorSubmit');
        var wrapper = form.querySelector('[data-location-address-fields]');

        function renderLocationRegionSelect(countryCode, selected) {
            if (! wrapper) return;
            var stateHost = wrapper.querySelector('[data-role="geo-region-single-wrapper"]');
            if (! stateHost) return;
            var regions = regionCatalog[countryCode] || {};
            var keys = Object.keys(regions);
            selected = (selected || '').toUpperCase();
            if (! keys.length) {
                stateHost.innerHTML = '<span class="text-xs font-semibold text-[#64748B]">State / province</span><input type="text" name="state" id="location-editor-state" value="' + selected + '" placeholder="Region" data-role="geo-region-text" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase mt-1">';
                return;
            }
            var html = '<span class="text-xs font-semibold text-[#64748B]">State / province</span><select name="state" id="location-editor-state" data-role="geo-region-single-select" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm mt-1"><option value="">Select a state / province</option>';
            keys.forEach(function (code) {
                html += '<option value="' + code + '"' + (selected === code ? ' selected' : '') + '>' + regions[code] + ' (' + code + ')</option>';
            });
            if (selected && keys.indexOf(selected) === -1) {
                html += '<option value="' + selected + '" selected>' + selected + ' (legacy)</option>';
            }
            html += '</select>';
            stateHost.innerHTML = html;
        }

        function setValue(id, value) {
            var el = document.getElementById(id);
            if (el) el.value = value == null ? '' : value;
        }

        function setChecked(id, checked) {
            var el = document.getElementById(id);
            if (el) el.checked = Boolean(checked);
        }

        function openModal() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var name = document.getElementById('locationName');
            if (name) name.focus();
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function openAdd() {
            form.action = storeUrl;
            if (methodInput) methodInput.disabled = true;
            if (modeInput) modeInput.value = 'add';
            if (idInput) idInput.value = '';
            if (titleEl) titleEl.textContent = 'Add location';
            if (copyEl) copyEl.textContent = 'Create an inventory and fulfillment location.';
            if (submitEl) submitEl.textContent = 'Add location';
            setValue('locationName', '');
            setValue('locationType', 'warehouse');
            setValue('locationAddress1', '');
            setValue('locationAddress2', '');
            setValue('locationCity', '');
            setValue('locationPostal', '');
            setValue('locationPriority', '100');
            var phone = document.getElementById('locationPhone');
            if (phone) phone.value = '';
            setChecked('locationOnline', true);
            setChecked('locationPickup', false);
            var country = document.getElementById('location-editor-country');
            if (country) country.value = 'US';
            renderLocationRegionSelect('US', '');
            openModal();
        }

        function openEdit(id) {
            var data = payload[String(id)];
            if (! data) return;
            form.action = data.update_url;
            if (methodInput) {
                methodInput.disabled = false;
                methodInput.value = 'PATCH';
            }
            if (modeInput) modeInput.value = 'edit';
            if (idInput) idInput.value = String(data.id);
            if (titleEl) titleEl.textContent = 'Edit location';
            if (copyEl) copyEl.textContent = 'Update inventory and fulfillment settings.';
            if (submitEl) submitEl.textContent = 'Save changes';
            setValue('locationName', data.name);
            setValue('locationType', data.type || 'warehouse');
            setValue('locationAddress1', data.address_line1);
            setValue('locationAddress2', data.address_line2);
            setValue('locationCity', data.city);
            setValue('locationPostal', data.postal_code);
            setValue('locationPriority', data.routing_priority || 100);
            var phone = document.getElementById('locationPhone');
            if (phone) phone.value = data.phone || '';
            setChecked('locationOnline', data.fulfills_online_orders);
            setChecked('locationPickup', data.pickup_enabled);
            var country = document.getElementById('location-editor-country');
            var countryCode = (data.country_code || 'US').toUpperCase();
            if (country) country.value = countryCode;
            renderLocationRegionSelect(countryCode, data.state || '');
            openModal();
        }

        if (wrapper) {
            wrapper.addEventListener('change', function (event) {
                if (event.target && event.target.getAttribute('data-role') === 'geo-country-select') {
                    renderLocationRegionSelect(event.target.value || '', '');
                }
            });
        }

        document.querySelectorAll('[data-lc-open-add]').forEach(function (btn) {
            btn.addEventListener('click', openAdd);
        });
        document.querySelectorAll('[data-lc-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openEdit(btn.getAttribute('data-lc-edit'));
            });
        });
        document.querySelectorAll('[data-lc-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', closeModal);
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && ! modal.classList.contains('hidden')) {
                closeModal();
            }
        });

        @if ($openLocationModal)
            @if ($locationFormMode === 'edit' && old('location_id'))
                (function () {
                    var oldId = @json((string) old('location_id'));
                    var data = payload[String(oldId)] || {};
                    form.action = data.update_url || form.action;
                    if (methodInput) {
                        methodInput.disabled = false;
                        methodInput.value = 'PATCH';
                    }
                    if (modeInput) modeInput.value = 'edit';
                    if (idInput) idInput.value = oldId;
                    if (titleEl) titleEl.textContent = 'Edit location';
                    if (copyEl) copyEl.textContent = 'Update inventory and fulfillment settings.';
                    if (submitEl) submitEl.textContent = 'Save changes';
                    var country = document.getElementById('location-editor-country');
                    var stateEl = document.getElementById('location-editor-state');
                    renderLocationRegionSelect(
                        country ? country.value : 'US',
                        stateEl ? stateEl.value : ''
                    );
                    openModal();
                })();
            @else
                if (methodInput) methodInput.disabled = true;
                form.action = storeUrl;
                if (modeInput) modeInput.value = 'add';
                if (titleEl) titleEl.textContent = 'Add location';
                if (copyEl) copyEl.textContent = 'Create an inventory and fulfillment location.';
                if (submitEl) submitEl.textContent = 'Add location';
                var country = document.getElementById('location-editor-country');
                var stateEl = document.getElementById('location-editor-state');
                renderLocationRegionSelect(
                    country ? country.value : 'US',
                    stateEl ? stateEl.value : ''
                );
                openModal();
            @endif
        @endif
    })();
    </script>
@endpush
