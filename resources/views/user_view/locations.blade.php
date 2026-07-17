@extends('layouts.user.user-sidebar')

@section('title', 'Locations | BaaS Core')

@php
    use App\Support\Tax\TaxCountryCatalog;

    $typeLabels = [
        'warehouse' => 'Warehouse',
        'store' => 'Store / shop',
        'third_party' => 'Third-party storage',
        'other' => 'Other',
    ];
    $countries = $countries ?? TaxCountryCatalog::all();
    $addCountry = strtoupper((string) old('country_code', 'US'));
    $locationRegionCatalog = [];
    foreach (array_keys($countries) as $catalogCountryCode) {
        $locationRegionCatalog[$catalogCountryCode] = TaxCountryCatalog::regionsFor($catalogCountryCode);
    }
@endphp

@section('topbar')
    <x-ui.merchant-topbar title="Locations" lead="Places where your store keeps inventory and fulfills orders.">
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="settings-workspace space-y-6">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="settings-guide">
            <p class="settings-guide-title">Locations guide</p>
            <ul class="settings-guide-list">
                <li><span class="settings-guide-step">1</span><span>Create each physical fulfillment location (warehouse, shop, or 3PL) with complete address fields.</span></li>
                <li><span class="settings-guide-step">2</span><span>Set one default location for fallback stock and shipping origin behavior.</span></li>
                <li><span class="settings-guide-step">3</span><span>Use service countries/regions/postal rules only when you need routing control beyond standard fulfillment.</span></li>
            </ul>
        </section>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Inventory settings</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[#0F172A] font-[Poppins]">Inventory locations</h2>
                    <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Locations are places where you store or fulfill inventory, such as a warehouse, shop, stock room, restaurant branch, or third-party storage.</p>
                    <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Locations control where stock is stored. Markets and currencies control where and how you sell. Market-specific selling settings will be added later.</p>
                    <p class="mt-2 text-sm leading-relaxed text-[#64748B]">Use service areas to control which destinations this location can fulfill. This routing is based on configured service areas, stock availability, and your priority settings.</p>

                    <div class="mt-5 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-4">
                        <p class="text-sm font-semibold text-[#0F172A]">Fulfillment origins</p>
                        <p class="mt-2 text-sm leading-relaxed text-[#475569]">Carrier rates use fulfillment locations as the ship-from address. Your store business address can be different and is used for store profile, invoices, and admin context.</p>
                        <p class="mt-2 text-sm leading-relaxed text-[#475569]">USPS and FedEx testing use the default origin location selected on Shipping &amp; Delivery — not the store business address.</p>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">What locations are used for</h3>
                            <ul class="mt-3 space-y-2 text-sm text-[#64748B]">
                                <li>Inventory levels</li>
                                <li>Reservations</li>
                                <li>Stock movements</li>
                                <li>Future fulfillment origin</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                            <h3 class="text-sm font-semibold text-[#0F172A]">What locations do not control</h3>
                            <ul class="mt-3 space-y-2 text-sm text-[#64748B]">
                                <li>Customer markets</li>
                                <li>Selling currencies</li>
                                <li>Language</li>
                                <li>Regional pricing</li>
                                <li>Storefront availability</li>
                            </ul>
                        </div>
                    </div>
                </div>
                @if ($canManageLocations)
                    <form method="POST" action="{{ route('settings.locations.store') }}" class="w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 lg:max-w-xl">
                        @csrf
                        <p class="text-sm font-semibold text-[#0F172A]">Add location</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Name</span>
                                <input name="name" value="{{ old('name') }}" placeholder="Main warehouse" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Type</span>
                                <select name="type" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                    @foreach ($locationTypes as $type)
                                        <option value="{{ $type }}">{{ $typeLabels[$type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $type)) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 sm:col-span-2">
                                <span class="text-xs font-semibold text-[#64748B]">Address line 1</span>
                                <input name="address_line1" value="{{ old('address_line1') }}" placeholder="738 Fawn Valley Dr" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">City</span>
                                <input name="city" value="{{ old('city') }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                            </label>
                            <div class="space-y-1" data-location-address-fields data-form-prefix="add">
                                <x-geo.country-select name="country_code" id="location-add-country" :selected="$addCountry" :countries="$countries" required />
                                <x-geo.region-select name="state" id="location-add-state" :country-code="$addCountry" :selected="strtoupper((string) old('state', ''))" />
                            </div>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Postal/ZIP code</span>
                                <input name="postal_code" value="{{ old('postal_code') }}" placeholder="75002" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                            </label>
                            <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#334155] sm:col-span-2">
                                <input type="hidden" name="fulfills_online_orders" value="0">
                                <input type="checkbox" name="fulfills_online_orders" value="1" @checked(old('fulfills_online_orders', '1'))>
                                Fulfill online orders
                            </label>
                            <details class="sm:col-span-2 rounded-xl border border-[#E2E8F0] bg-white p-3">
                                <summary class="cursor-pointer text-sm font-semibold text-[#475569]">Advanced routing (optional)</summary>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#334155]">
                                        <input type="hidden" name="pickup_enabled" value="0">
                                        <input type="checkbox" name="pickup_enabled" value="1" @checked(old('pickup_enabled'))>
                                        Offer pickup
                                    </label>
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold text-[#64748B]">Routing priority</span>
                                        <input name="routing_priority" type="number" min="1" max="9999" value="{{ old('routing_priority', 100) }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                    </label>
                                    <label class="space-y-1 sm:col-span-2">
                                        <span class="text-xs font-semibold text-[#64748B]">Service countries</span>
                                        <input name="service_countries" value="{{ old('service_countries') }}" placeholder="US, CA" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase">
                                    </label>
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold text-[#64748B]">Service regions</span>
                                        <input name="service_regions" value="{{ old('service_regions') }}" placeholder="CA, TX" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase">
                                    </label>
                                    <label class="space-y-1">
                                        <span class="text-xs font-semibold text-[#64748B]">Service postal patterns</span>
                                        <input name="service_postal_patterns" value="{{ old('service_postal_patterns') }}" placeholder="60601, 606*" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase">
                                        <span class="text-[11px] text-[#94A3B8]">Use exact postal codes or prefix patterns such as 60601 or 606*.</span>
                                    </label>
                                </div>
                            </details>
                        </div>
                        <button type="submit" class="mt-4 inline-flex rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Add location</button>
                    </form>
                @else
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
                        You can view locations. Store owners manage location changes.
                    </div>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
            <div class="border-b border-[#F1F5F9] px-5 py-4">
                <h2 class="text-lg font-semibold text-[#0F172A] font-[Poppins]">Current locations</h2>
                <p class="mt-1 text-sm text-[#64748B]">One active default location is used when imports, quick add, product edits, and storefront orders need a stock location.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-[#F8FAFC] text-xs font-semibold uppercase tracking-wide text-[#64748B]">
                        <tr>
                            <th class="px-5 py-3">Location</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Address</th>
                            <th class="px-5 py-3">Routing</th>
                            <th class="px-5 py-3">Ship-from readiness</th>
                            <th class="px-5 py-3">Default</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F1F5F9]">
                        @foreach ($locations as $location)
                            @php
                                $readiness = $originReadinessByLocationId[$location->id] ?? null;
                            @endphp
                            <tr>
                                <td class="px-5 py-4 align-top">
                                    <p class="font-semibold text-[#0F172A]">{{ $location->name }}</p>
                                    <p class="mt-1 text-xs text-[#94A3B8]">{{ $location->inventory_levels_count }} inventory row(s)</p>
                                    @if ($location->is_default)
                                        <p class="mt-1 text-xs font-semibold text-[#1D4ED8]">Default fulfillment origin</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top text-[#334155]">{{ $typeLabels[$location->type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $location->type)) }}</td>
                                <td class="px-5 py-4 align-top text-[#64748B]">
                                    {{ collect([$location->address_line1, $location->city, $location->state, $location->postal_code, $location->country_code])->filter()->implode(', ') ?: 'No address saved' }}
                                </td>
                                <td class="px-5 py-4 align-top text-[#64748B]">
                                    <div class="space-y-1">
                                        <p class="text-xs font-semibold text-[#334155]">Priority {{ $location->routing_priority ?? 100 }}</p>
                                        <p class="text-xs">{{ $location->fulfills_online_orders ? 'Online fulfillment' : 'Not used for online fulfillment' }}</p>
                                        <p class="text-xs">{{ $location->pickup_enabled ? 'Pickup offered' : 'Pickup off' }}</p>
                                        <p class="text-xs">Countries: {{ collect($location->service_countries)->filter()->implode(', ') ?: ($location->country_code ?: 'Store default') }}</p>
                                        @if (collect($location->service_regions)->filter()->isNotEmpty())
                                            <p class="text-xs">Regions: {{ collect($location->service_regions)->filter()->implode(', ') }}</p>
                                        @endif
                                        @if (collect($location->service_postal_patterns)->filter()->isNotEmpty())
                                            <p class="text-xs">Postal: {{ collect($location->service_postal_patterns)->filter()->implode(', ') }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    @if ($readiness)
                                        <span class="rounded-full {{ $readiness->ready ? 'bg-[#ECFDF5] text-[#047857]' : ($readiness->status === 'unsupported_country' ? 'bg-[#FFF7ED] text-[#C2410C]' : 'bg-[#FEF2F2] text-[#991B1B]') }} px-2.5 py-1 text-xs font-bold">{{ $readiness->badgeLabel }}</span>
                                        @if (! $readiness->ready && $readiness->missingFields !== [])
                                            <p class="mt-2 text-xs text-[#64748B]">Missing: {{ implode(', ', $readiness->missingFields) }}</p>
                                        @elseif (! $readiness->ready)
                                            <p class="mt-2 text-xs text-[#64748B]">{{ $readiness->merchantMessage }}</p>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top">
                                    @if ($location->is_default)
                                        <span class="rounded-full bg-[#ECFDF5] px-2.5 py-1 text-xs font-bold text-[#047857]">Default</span>
                                    @else
                                        <span class="text-xs text-[#94A3B8]">-</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <span class="rounded-full {{ $location->is_active ? 'bg-[#EFF6FF] text-[#1D4ED8]' : 'bg-[#F1F5F9] text-[#64748B]' }} px-2.5 py-1 text-xs font-bold">{{ $location->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    @if ($canManageLocations)
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @if (! $location->is_default)
                                                <form method="POST" action="{{ route('settings.locations.make-default', $location) }}">
                                                    @csrf
                                                    <button class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 py-1.5 text-xs font-semibold text-[#1D4ED8]">Make default</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('settings.locations.deactivate', $location) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569]">{{ $location->is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                            <details class="basis-full text-right">
                                                <summary class="inline-flex cursor-pointer rounded-lg border border-[#E2E8F0] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569]">Edit</summary>
                                                <form method="POST" action="{{ route('settings.locations.update', $location) }}" class="mt-3 grid gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 text-left sm:grid-cols-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="space-y-1">
                                                        <span class="text-xs font-semibold text-[#64748B]">Name</span>
                                                        <input name="name" value="{{ old('name', $location->name) }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                    </label>
                                                    <label class="space-y-1">
                                                        <span class="text-xs font-semibold text-[#64748B]">Type</span>
                                                        <select name="type" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                            @foreach ($locationTypes as $type)
                                                                <option value="{{ $type }}" @selected(old('type', $location->type) === $type)>{{ $typeLabels[$type] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $type)) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="space-y-1 sm:col-span-2">
                                                        <span class="text-xs font-semibold text-[#64748B]">Address line 1</span>
                                                        <input name="address_line1" value="{{ old('address_line1', $location->address_line1) }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                    </label>
                                                    <label class="space-y-1">
                                                        <span class="text-xs font-semibold text-[#64748B]">City</span>
                                                        <input name="city" value="{{ old('name') === $location->name ? old('city') : $location->city }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                    </label>
                                                    @php
                                                        $editCountry = strtoupper((string) (old('name') === $location->name ? old('country_code', $location->country_code) : $location->country_code));
                                                        $editState = strtoupper((string) (old('name') === $location->name ? old('state', $location->state) : $location->state));
                                                        $editFulfillsOnline = old('name') === $location->name ? old('fulfills_online_orders', $location->fulfills_online_orders) : $location->fulfills_online_orders;
                                                        $editPickupEnabled = old('name') === $location->name ? old('pickup_enabled', $location->pickup_enabled) : $location->pickup_enabled;
                                                    @endphp
                                                    <div class="space-y-1 sm:col-span-2" data-location-address-fields data-location-id="{{ $location->id }}">
                                                        <x-geo.country-select name="country_code" :id="'location-edit-country-'.$location->id" :selected="$editCountry" :countries="$countries" required />
                                                        <x-geo.region-select name="state" :id="'location-edit-state-'.$location->id" :country-code="$editCountry" :selected="$editState" />
                                                    </div>
                                                    <label class="space-y-1">
                                                        <span class="text-xs font-semibold text-[#64748B]">Postal/ZIP code</span>
                                                        <input name="postal_code" value="{{ old('name') === $location->name ? old('postal_code', $location->postal_code) : $location->postal_code }}" placeholder="75002" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                    </label>
                                                    <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-[#334155] sm:col-span-2">
                                                        <input type="hidden" name="fulfills_online_orders" value="0">
                                                        <input type="checkbox" name="fulfills_online_orders" value="1" @checked($editFulfillsOnline)>
                                                        Fulfill online orders
                                                    </label>
                                                    <details class="sm:col-span-2 rounded-xl border border-[#E2E8F0] bg-white p-3">
                                                        <summary class="cursor-pointer text-sm font-semibold text-[#475569]">Advanced routing (optional)</summary>
                                                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                                            <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#334155]">
                                                                <input type="hidden" name="pickup_enabled" value="0">
                                                                <input type="checkbox" name="pickup_enabled" value="1" @checked($editPickupEnabled)>
                                                                Offer pickup
                                                            </label>
                                                            <label class="space-y-1">
                                                                <span class="text-xs font-semibold text-[#64748B]">Routing priority</span>
                                                                <input name="routing_priority" type="number" min="1" max="9999" value="{{ old('name') === $location->name ? old('routing_priority', $location->routing_priority ?? 100) : ($location->routing_priority ?? 100) }}" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm">
                                                            </label>
                                                            <label class="space-y-1 sm:col-span-2">
                                                                <span class="text-xs font-semibold text-[#64748B]">Service countries</span>
                                                                <input name="service_countries" value="{{ old('name') === $location->name ? old('service_countries', collect($location->service_countries)->filter()->implode(', ')) : collect($location->service_countries)->filter()->implode(', ') }}" placeholder="US, CA" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase">
                                                            </label>
                                                            <label class="space-y-1">
                                                                <span class="text-xs font-semibold text-[#64748B]">Service regions</span>
                                                                <input name="service_regions" value="{{ old('name') === $location->name ? old('service_regions', collect($location->service_regions)->filter()->implode(', ')) : collect($location->service_regions)->filter()->implode(', ') }}" placeholder="CA, TX" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase">
                                                            </label>
                                                            <label class="space-y-1">
                                                                <span class="text-xs font-semibold text-[#64748B]">Service postal patterns</span>
                                                                <input name="service_postal_patterns" value="{{ old('name') === $location->name ? old('service_postal_patterns', collect($location->service_postal_patterns)->filter()->implode(', ')) : collect($location->service_postal_patterns)->filter()->implode(', ') }}" placeholder="60601, 606*" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase">
                                                            </label>
                                                        </div>
                                                    </details>
                                                    <div class="sm:col-span-2">
                                                        <button class="rounded-lg bg-[#0052CC] px-3 py-2 text-xs font-bold text-white">Save location</button>
                                                    </div>
                                                </form>
                                            </details>
                                        </div>
                                    @else
                                        <span class="block text-right text-xs text-[#94A3B8]">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script type="application/json" id="location-region-catalog">@json($locationRegionCatalog)</script>
    <script>
    (function () {
        var regionCatalog = {};
        try {
            var catalogEl = document.getElementById('location-region-catalog');
            if (catalogEl) regionCatalog = JSON.parse(catalogEl.textContent || '{}');
        } catch (e) {}

        function renderLocationRegionSelect(wrapper, countryCode, selected) {
            if (!wrapper) return;
            var stateHost = wrapper.querySelector('[data-role="geo-region-single-wrapper"]');
            if (!stateHost) return;
            var regions = regionCatalog[countryCode] || {};
            var keys = Object.keys(regions);
            selected = (selected || '').toUpperCase();
            if (!keys.length) {
                stateHost.innerHTML = '<span class="text-xs font-semibold text-[#64748B]">State / province</span><input type="text" name="state" value="' + selected + '" placeholder="Region" data-role="geo-region-text" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm uppercase mt-1">';
                return;
            }
            var html = '<span class="text-xs font-semibold text-[#64748B]">State / province</span><select name="state" data-role="geo-region-single-select" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm mt-1"><option value="">Select a state / province</option>';
            keys.forEach(function (code) {
                html += '<option value="' + code + '"' + (selected === code ? ' selected' : '') + '>' + regions[code] + ' (' + code + ')</option>';
            });
            if (selected && keys.indexOf(selected) === -1) {
                html += '<option value="' + selected + '" selected>' + selected + ' (legacy)</option>';
            }
            html += '</select>';
            stateHost.innerHTML = html;
        }

        document.querySelectorAll('[data-location-address-fields]').forEach(function (wrapper) {
            var countrySelect = wrapper.querySelector('[data-role="geo-country-select"]');
            if (!countrySelect) return;
            var stateSelect = wrapper.querySelector('[data-role="geo-region-single-select"], [data-role="geo-region-text"]');
            var selected = stateSelect ? stateSelect.value : '';
            countrySelect.addEventListener('change', function () {
                renderLocationRegionSelect(wrapper, countrySelect.value || '', '');
            });
        });
    })();
    </script>
@endsection
