@php
    use App\Support\Tax\TaxCountryCatalog;

    $errorBag = $errorBag ?? 'default';
    $bag = $errors->getBag($errorBag);
    $values = $values ?? [];
    $countries = $countries ?? TaxCountryCatalog::all();
    $showPriorityField = $showPriorityField ?? false;
    $formId = $formId ?? 'tax-rate-form';
    $selectedCountry = strtoupper((string) ($values['country_code'] ?? ''));
    $selectedRegion = strtoupper((string) ($values['region_code'] ?? ''));
    $knownRegions = $selectedCountry !== '' ? TaxCountryCatalog::regionsFor($selectedCountry) : [];
    $hasKnownRegions = $knownRegions !== [];
    $customRegion = $selectedRegion !== '' && ! array_key_exists($selectedRegion, $knownRegions);
    $legacyCountry = $selectedCountry !== '' && ! array_key_exists($selectedCountry, $countries);
@endphp

<div
    class="grid gap-4 sm:grid-cols-2"
    data-tax-rate-form-fields
    data-form-id="{{ $formId }}"
    data-initial-region="{{ $selectedRegion }}"
>
    <label class="block sm:col-span-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Rate name</span>
        <input
            type="text"
            name="name"
            value="{{ $values['name'] ?? '' }}"
            required
            maxlength="120"
            class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $bag->has('name') ? 'border-[#F87171]' : '' }}"
            @if ($bag->has('name')) aria-invalid="true" aria-describedby="{{ $formId }}-name-error" @endif
        >
        @if ($bag->has('name'))
            <p id="{{ $formId }}-name-error" class="mt-1 text-xs text-[#B91C1C]">{{ $bag->first('name') }}</p>
        @endif
    </label>

    <label class="block">
        <span class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Country</span>
        <select
            name="country_code"
            required
            data-tax-rate-country-select
            class="mt-1 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm {{ $bag->has('country_code') ? 'border-[#F87171]' : '' }}"
            @if ($bag->has('country_code')) aria-invalid="true" aria-describedby="{{ $formId }}-country-error" @endif
        >
            <option value="">Select a country</option>
            @foreach ($countries as $code => $label)
                <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $label }} ({{ $code }})</option>
            @endforeach
            @if ($legacyCountry)
                <option value="{{ $selectedCountry }}" selected>{{ $selectedCountry }} (update required)</option>
            @endif
        </select>
        @if ($bag->has('country_code'))
            <p id="{{ $formId }}-country-error" class="mt-1 text-xs text-[#B91C1C]">{{ $bag->first('country_code') }}</p>
        @endif
        @if ($legacyCountry)
            <p class="mt-1 text-xs text-[#92400E]">This rate uses a legacy country value. Choose a valid country from the list.</p>
        @endif
    </label>

    <div class="block" data-tax-rate-region-wrapper>
        <span class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Region / state (optional)</span>
        <select
            name="{{ $hasKnownRegions ? 'region_code' : '' }}"
            data-tax-rate-region-select
            class="mt-1 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm {{ $hasKnownRegions ? '' : 'hidden' }} {{ $bag->has('region_code') ? 'border-[#F87171]' : '' }}"
            @if (! $hasKnownRegions) disabled @endif
            @if ($bag->has('region_code')) aria-invalid="true" aria-describedby="{{ $formId }}-region-error" @endif
        >
            <option value="">Country-wide (all regions)</option>
            @foreach ($knownRegions as $code => $label)
                <option value="{{ $code }}" @selected($selectedRegion === $code && ! $customRegion)>{{ $label }} ({{ $code }})</option>
            @endforeach
            @if ($customRegion && $hasKnownRegions)
                <option value="{{ $selectedRegion }}" selected>{{ $selectedRegion }} — Custom or legacy code</option>
            @endif
        </select>
        <input
            type="text"
            @if (! $hasKnownRegions) name="region_code" @endif
            value="{{ ! $hasKnownRegions ? $selectedRegion : '' }}"
            maxlength="32"
            autocomplete="off"
            placeholder="Region code (optional)"
            data-tax-rate-region-text
            class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase {{ $hasKnownRegions ? 'hidden' : '' }} {{ $bag->has('region_code') ? 'border-[#F87171]' : '' }}"
            @if ($hasKnownRegions) disabled @endif
        >
        <p class="mt-1 text-xs text-[#64748B]">Leave blank to apply this rate across the entire country.</p>
        @if ($customRegion && $hasKnownRegions)
            <p class="mt-1 text-xs text-[#92400E]">This rate uses a region code not included in the suggested list. It will be preserved unless you select another region.</p>
        @endif
        @if ($bag->has('region_code'))
            <p id="{{ $formId }}-region-error" class="mt-1 text-xs text-[#B91C1C]">{{ $bag->first('region_code') }}</p>
        @endif
    </div>

    <label class="block">
        <span class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Rate percentage</span>
        <input
            type="text"
            name="rate_percent"
            value="{{ $values['rate_percent'] ?? '' }}"
            required
            inputmode="decimal"
            class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $bag->has('rate_percent') ? 'border-[#F87171]' : '' }}"
            @if ($bag->has('rate_percent')) aria-invalid="true" aria-describedby="{{ $formId }}-rate-error" @endif
        >
        @if ($bag->has('rate_percent'))
            <p id="{{ $formId }}-rate-error" class="mt-1 text-xs text-[#B91C1C]">{{ $bag->first('rate_percent') }}</p>
        @endif
    </label>

    <label class="flex items-center gap-2 self-end pb-1">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-[#CBD5E1]" @checked((bool) ($values['is_active'] ?? true))>
        <span class="text-sm font-semibold text-[#0F172A]">Active</span>
    </label>

    @if (! $showPriorityField)
        <input type="hidden" name="priority" value="{{ (int) ($values['priority'] ?? 100) }}">
    @endif

    <details class="sm:col-span-2 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4" @if ($showPriorityField) open @endif data-tax-rate-advanced>
        <summary class="cursor-pointer text-sm font-semibold text-[#334155]">Advanced matching</summary>
        <p class="mt-2 text-xs leading-relaxed text-[#64748B]">
            Each store can have one rate per country and region combination. Region-specific rates apply before country-wide rates for the same destination. Priority is preserved for legacy records and is not used to choose between those matches.
        </p>
        @if ($showPriorityField)
            <label class="mt-3 block max-w-xs">
                <span class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Legacy priority</span>
                <input
                    type="number"
                    name="priority"
                    value="{{ (int) ($values['priority'] ?? 100) }}"
                    min="0"
                    max="65535"
                    class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ $bag->has('priority') ? 'border-[#F87171]' : '' }}"
                    @if ($bag->has('priority')) aria-invalid="true" aria-describedby="{{ $formId }}-priority-error" @endif
                >
                @if ($bag->has('priority'))
                    <p id="{{ $formId }}-priority-error" class="mt-1 text-xs text-[#B91C1C]">{{ $bag->first('priority') }}</p>
                @endif
            </label>
        @endif
    </details>
</div>
