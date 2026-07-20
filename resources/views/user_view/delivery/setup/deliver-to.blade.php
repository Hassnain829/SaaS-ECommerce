@extends('user_view.delivery.wizard-layout')

@section('wizard-content')
    @php
        $wizardCountryCode = strtoupper((string) old('country_code', $zonePayload['country_code'] ?? 'US'));
        $wizardRegionCodes = old('region_codes', $zonePayload['region_codes'] ?? []);
        $wizardZoneName = old('name', $zonePayload['name'] ?? $selectedZone?->name ?? 'United States');
        $wizardSelectedZoneId = old('shipping_zone_id', $selectedZone?->id);
        $wizardZoneIsActive = (bool) old('is_active', $zonePayload['is_active'] ?? true);
        $legacyZoneIds = $legacyZones->pluck('id')->all();

        $wizardPostalRules = [];
        if (old('postal_rules_json')) {
            $wizardPostalRules = json_decode((string) old('postal_rules_json'), true) ?: [];
        } else {
            $wizardPostalRules = $zonePayload['postal_rules'] ?? [];
        }

        $wizardRegionCatalog = [];
        foreach (array_keys($countries) as $catalogCountryCode) {
            $wizardRegionCatalog[$catalogCountryCode] = \App\Support\Tax\TaxCountryCatalog::regionsFor($catalogCountryCode);
        }
    @endphp

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm md:p-6">
        <h2 class="text-2xl font-semibold text-[#0F172A]">Where do you deliver?</h2>
        <p class="mt-2 text-sm text-[#64748B]">Each delivery area covers one country. Add states or ZIP/postal rules when you need tighter coverage.</p>

        @if ($legacyZones->isNotEmpty())
            <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                Some delivery areas use advanced multi-country settings.
                <a href="{{ route('shippingAutomation', ['tab' => 'advanced']) }}" class="font-semibold underline">Open advanced delivery settings</a> to edit them.
            </div>
        @endif

        <form method="POST" action="{{ route('settings.delivery.setup.deliver-to') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="zone_editor_mode" value="simple">

            @if ($shippingZones->isNotEmpty())
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Delivery area</span>
                    <select name="shipping_zone_id" id="wizard-zone-select" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                        <option value="">Create a new delivery area</option>
                        @foreach ($shippingZones as $zone)
                            @php($zoneIsLegacy = in_array($zone->id, $legacyZoneIds, true))
                            <option value="{{ $zone->id }}" @selected($wizardSelectedZoneId == $zone->id) @disabled($zoneIsLegacy)>
                                {{ $zone->name }}@if ($zoneIsLegacy) (advanced only)@endif
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="block space-y-1">
                <span class="text-xs font-semibold text-[#64748B]">Delivery area name</span>
                <input name="name" required value="{{ $wizardZoneName }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
            </label>

            <x-geo.country-select id="wizard-zone-country" name="country_code" :selected="$wizardCountryCode" :countries="$countries" required />

            <div id="wizard-zone-region-host">
                <x-geo.region-multi-select id="wizard-zone-regions" :country-code="$wizardCountryCode" :selected="$wizardRegionCodes" />
            </div>

            <x-geo.postal-rule-builder input-id="wizard-zone-postal-json" container-id="wizard-zone-postal-builder" :rules="$wizardPostalRules" />

            <label class="flex items-center gap-2 text-sm text-[#334155]">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked($wizardZoneIsActive) class="rounded border-[#CBD5E1]">
                Active delivery area
            </label>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#F1F5F9] pt-4">
                <a href="{{ route('settings.delivery.setup.ship-from') }}" class="text-sm font-semibold text-[#64748B]">Back</a>
                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-brand px-5 text-sm font-bold text-white">Save and continue</button>
            </div>
        </form>
    </section>

    <script type="application/json" id="wizard-region-catalog">@json($wizardRegionCatalog)</script>
    <script type="application/json" id="wizard-zone-catalog">@json($zoneCatalog ?? [])</script>
    @include('user_view.delivery.partials.wizard-geo-script')
    <script>
    (function () {
        var catalog = {};
        try {
            var el = document.getElementById('wizard-zone-catalog');
            if (el) catalog = JSON.parse(el.textContent || '{}');
        } catch (e) {}

        var select = document.getElementById('wizard-zone-select');
        var form = select ? select.closest('form') : null;
        if (!select || !form) return;

        select.addEventListener('change', function () {
            var data = catalog[select.value];
            if (!data || data.editor_mode === 'legacy') return;

            var nameField = form.querySelector('[name="name"]');
            if (nameField) nameField.value = data.name || '';

            var country = document.getElementById('wizard-zone-country');
            if (country) {
                country.value = data.country_code || '';
                if (window.wizardRenderRegionMulti) {
                    window.wizardRenderRegionMulti('wizard-zone-region-host', country.value, data.region_codes || []);
                }
            }

            var activeField = form.querySelector('[name="is_active"][type="checkbox"]');
            if (activeField) activeField.checked = !!data.is_active;

            if (window.wizardHydratePostalRules) {
                window.wizardHydratePostalRules(document.getElementById('wizard-zone-postal-builder'), data.postal_rules || []);
            }
        });
    })();
    </script>
@endsection
