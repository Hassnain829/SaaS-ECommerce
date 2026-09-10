@php
    use App\Support\Tax\TaxCountryCatalog;

    $errorBag = $errorBag ?? 'default';
    $bag = $errors->getBag($errorBag);
    $values = $values ?? [];
    $countries = $countries ?? TaxCountryCatalog::all();
    $showPriorityField = $showPriorityField ?? false;
    $formId = $formId ?? 'tax-rate-form';
    $preferRegional = (bool) ($preferRegional ?? false);
    $selectedCountry = strtoupper((string) ($values['country_code'] ?? ''));
    $selectedRegion = strtoupper((string) ($values['region_code'] ?? ''));
    $knownRegions = $selectedCountry !== '' ? TaxCountryCatalog::regionsFor($selectedCountry) : [];
    $hasKnownRegions = $knownRegions !== [];
    $customRegion = $selectedRegion !== '' && ! array_key_exists($selectedRegion, $knownRegions);
    $legacyCountry = $selectedCountry !== '' && ! array_key_exists($selectedCountry, $countries);
    $isRegional = $preferRegional || $selectedRegion !== '';
@endphp

<div
    class="trb-fields"
    data-tax-rate-form-fields
    data-form-id="{{ $formId }}"
    data-initial-region="{{ $selectedRegion }}"
>
    <label class="trb-field">
        <span class="trb-label">Rate name</span>
        <input
            type="text"
            name="name"
            value="{{ $values['name'] ?? '' }}"
            required
            maxlength="120"
            placeholder="e.g. US tax"
            class="trb-input {{ $bag->has('name') ? 'is-invalid' : '' }}"
            @if ($bag->has('name')) aria-invalid="true" aria-describedby="{{ $formId }}-name-error" @endif
        >
        @if ($bag->has('name'))
            <p id="{{ $formId }}-name-error" class="trb-field-error">{{ $bag->first('name') }}</p>
        @endif
    </label>

    <label class="trb-field">
        <span class="trb-label">Country</span>
        <select
            name="country_code"
            required
            data-tax-rate-country-select
            class="trb-input {{ $bag->has('country_code') ? 'is-invalid' : '' }}"
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
            <p id="{{ $formId }}-country-error" class="trb-field-error">{{ $bag->first('country_code') }}</p>
        @endif
        @if ($legacyCountry)
            <p class="trb-field-hint is-warn">This rate uses a legacy country value. Choose a valid country from the list.</p>
        @endif
    </label>

    <fieldset class="trb-field">
        <legend class="trb-label">Coverage</legend>
        <div class="trb-scope">
            <label class="trb-scope-option">
                <input type="radio" name="tax_coverage" value="country" data-tax-coverage @checked(! $isRegional)>
                <span>Country-wide</span>
            </label>
            <label class="trb-scope-option">
                <input type="radio" name="tax_coverage" value="regional" data-tax-coverage @checked($isRegional)>
                <span>Region-specific</span>
            </label>
        </div>
    </fieldset>

    <div class="trb-field" data-tax-rate-region-wrapper @if (! $isRegional) hidden @endif>
        <span class="trb-label">Region / state</span>
        <select
            @if ($hasKnownRegions && $isRegional) name="region_code" @endif
            data-tax-rate-region-select
            class="trb-input {{ $hasKnownRegions ? '' : 'hidden' }} {{ $bag->has('region_code') ? 'is-invalid' : '' }}"
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
            @if (! $hasKnownRegions && $isRegional) name="region_code" @endif
            value="{{ ! $hasKnownRegions ? $selectedRegion : '' }}"
            maxlength="32"
            autocomplete="off"
            placeholder="Region code, e.g. TX"
            data-tax-rate-region-text
            class="trb-input {{ $hasKnownRegions ? 'hidden' : '' }} {{ $bag->has('region_code') ? 'is-invalid' : '' }}"
            @if ($hasKnownRegions) disabled @endif
        >
        <p class="trb-field-hint">Leave blank to apply this rate across the entire country.</p>
        @if ($customRegion && $hasKnownRegions)
            <p class="trb-field-hint is-warn">This rate uses a region code not included in the suggested list. It will be preserved unless you select another region.</p>
        @endif
        @if ($bag->has('region_code'))
            <p id="{{ $formId }}-region-error" class="trb-field-error">{{ $bag->first('region_code') }}</p>
        @endif
    </div>
    <input
        type="hidden"
        value=""
        data-tax-rate-region-blank
        @unless ($isRegional) name="region_code" @endunless
        @if ($isRegional) disabled @endif
    >

    <label class="trb-field">
        <span class="trb-label">Rate percentage</span>
        <input
            type="text"
            name="rate_percent"
            value="{{ $values['rate_percent'] ?? '' }}"
            required
            inputmode="decimal"
            placeholder="0.0000"
            class="trb-input {{ $bag->has('rate_percent') ? 'is-invalid' : '' }}"
            @if ($bag->has('rate_percent')) aria-invalid="true" aria-describedby="{{ $formId }}-rate-error" @endif
        >
        <p class="trb-field-hint">0–100%, with up to four decimal places.</p>
        @if ($bag->has('rate_percent'))
            <p id="{{ $formId }}-rate-error" class="trb-field-error">{{ $bag->first('rate_percent') }}</p>
        @endif
    </label>

    <input type="hidden" name="is_active" value="0">
    <div class="trb-switch-row trb-switch-row-inline">
        <div class="trb-switch-copy">
            <strong>Active</strong>
            <small>Available when platform tax calculation is enabled.</small>
        </div>
        <label class="settings-switch">
            <input type="checkbox" name="is_active" value="1" @checked((bool) ($values['is_active'] ?? true))>
            <span class="settings-switch-track" aria-hidden="true"></span>
        </label>
    </div>

    @if (! $showPriorityField)
        <input type="hidden" name="priority" value="{{ (int) ($values['priority'] ?? 100) }}">
    @endif

    <details class="trb-advanced" @if ($showPriorityField) open @endif data-tax-rate-advanced>
        <summary>Advanced matching</summary>
        <p class="trb-field-hint">
            Each store can have one rate per country and region combination. Region-specific rates apply before country-wide rates for the same destination. Priority is preserved for legacy records and is not used to choose between those matches.
        </p>
        @if ($showPriorityField)
            <label class="trb-field" style="margin-top: 0.85rem;">
                <span class="trb-label">Priority</span>
                <input
                    type="number"
                    name="priority"
                    value="{{ (int) ($values['priority'] ?? 100) }}"
                    min="0"
                    max="65535"
                    class="trb-input {{ $bag->has('priority') ? 'is-invalid' : '' }}"
                    @if ($bag->has('priority')) aria-invalid="true" aria-describedby="{{ $formId }}-priority-error" @endif
                >
                <p class="trb-field-hint">Default: 100. Keep the existing value unless you need to adjust rate ordering.</p>
                @if ($bag->has('priority'))
                    <p id="{{ $formId }}-priority-error" class="trb-field-error">{{ $bag->first('priority') }}</p>
                @endif
            </label>
        @endif
    </details>
</div>
