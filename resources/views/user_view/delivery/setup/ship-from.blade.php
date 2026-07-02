@extends('user_view.delivery.wizard-layout')

@section('wizard-content')
    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm md:p-6">
        <h2 class="text-2xl font-poppins font-semibold text-[#0F172A]">Where do you ship from?</h2>
        <p class="mt-2 text-sm text-[#64748B]">Choose an existing ship-from location or add the address customers' orders will ship from.</p>

        <form method="POST" action="{{ route('settings.delivery.setup.ship-from') }}" class="mt-6 space-y-5">
            @csrf

            @if ($locations->isNotEmpty())
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Use an existing location</span>
                    <select name="location_id" id="wizard-location-select" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                        <option value="">Create a new ship-from location</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id', $selectedLocation?->id) == $location->id)>
                                {{ $location->name }}@if ($location->is_default) (default)@endif
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block space-y-1 sm:col-span-2">
                    <span class="text-xs font-semibold text-[#64748B]">Location name</span>
                    <input name="name" required value="{{ old('name', $selectedLocation?->name) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Type</span>
                    <select name="type" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                        @foreach ($locationTypes as $type)
                            <option value="{{ $type }}" @selected(old('type', $selectedLocation?->type ?? 'warehouse') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block space-y-1 sm:col-span-2">
                    <span class="text-xs font-semibold text-[#64748B]">Street address</span>
                    <input name="address_line1" required value="{{ old('address_line1', $selectedLocation?->address_line1) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">City</span>
                    <input name="city" required value="{{ old('city', $selectedLocation?->city) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
                @php
                    $shipCountry = strtoupper((string) old('country_code', $selectedLocation?->country_code ?? 'US'));
                    $shipState = strtoupper((string) old('state', $selectedLocation?->state ?? ''));
                @endphp
                <div class="space-y-3 sm:col-span-2">
                    <x-geo.country-select id="wizard-ship-country" :selected="$shipCountry" :countries="$countries" required />
                    <x-geo.region-select id="wizard-ship-state" name="state" :country-code="$shipCountry" :selected="$shipState" />
                </div>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">ZIP / postal code</span>
                    <input name="postal_code" value="{{ old('postal_code', $selectedLocation?->postal_code) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
                <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#334155] sm:col-span-2">
                    <input type="hidden" name="fulfills_online_orders" value="0">
                    <input type="checkbox" name="fulfills_online_orders" value="1" @checked(old('fulfills_online_orders', $selectedLocation?->fulfills_online_orders ?? true))>
                    Fulfill online orders from this location
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#F1F5F9] pt-4">
                <a href="{{ route('settings.locations.index') }}" class="text-sm font-semibold text-[#64748B] hover:text-[#1D4ED8]">Open advanced location settings</a>
                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-5 text-sm font-bold text-white">Save and continue</button>
            </div>
        </form>
    </section>

    @php
        $wizardLocationCatalog = $locations->mapWithKeys(fn ($location) => [
            $location->id => [
                'name' => $location->name,
                'type' => $location->type,
                'address_line1' => $location->address_line1,
                'city' => $location->city,
                'state' => $location->state,
                'postal_code' => $location->postal_code,
                'country_code' => $location->country_code,
                'fulfills_online_orders' => (bool) $location->fulfills_online_orders,
            ],
        ])->all();
    @endphp
    <script type="application/json" id="wizard-location-catalog">@json($wizardLocationCatalog)</script>
    <script>
    (function () {
        var catalog = {};
        try {
            var el = document.getElementById('wizard-location-catalog');
            if (el) catalog = JSON.parse(el.textContent || '{}');
        } catch (e) {}

        var select = document.getElementById('wizard-location-select');
        var form = select ? select.closest('form') : null;
        if (!select || !form) return;

        function setValue(name, value) {
            var field = form.querySelector('[name="' + name + '"]');
            if (!field) return;
            if (field.type === 'checkbox') {
                field.checked = !!value;
            } else {
                field.value = value || '';
            }
        }

        select.addEventListener('change', function () {
            var data = catalog[select.value];
            if (!data) return;
            setValue('name', data.name);
            setValue('type', data.type);
            setValue('address_line1', data.address_line1);
            setValue('city', data.city);
            setValue('state', data.state);
            setValue('postal_code', data.postal_code);
            var country = document.getElementById('wizard-ship-country');
            if (country) {
                country.value = data.country_code || '';
                country.dispatchEvent(new Event('change'));
            }
            setValue('fulfills_online_orders', data.fulfills_online_orders);
        });
    })();
    </script>
@endsection
