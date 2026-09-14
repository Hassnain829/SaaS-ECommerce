@props([
    'name' => 'country_code',
    'id' => null,
    'selected' => '',
    'countries' => null,
    'required' => false,
    'selectClass' => 'h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm',
    'label' => 'Country',
    'dataRole' => 'geo-country-select',
    'wrapperClass' => 'block space-y-1',
])

@php
    use App\Support\Tax\TaxCountryCatalog;

    $countries = $countries ?? TaxCountryCatalog::all();
    $selected = strtoupper(trim((string) $selected));
    $fieldId = $id ?? $name;
    $legacyCountry = $selected !== '' && ! array_key_exists($selected, $countries);
    $isDisabled = $attributes->has('disabled');
    $searchId = $fieldId.'-search';
    $listId = $fieldId.'-list';
@endphp

<div class="{{ $wrapperClass }} relative" data-country-combobox>
    <label class="text-xs font-semibold text-[#64748B]" id="{{ $fieldId }}-label" for="{{ $fieldId }}" data-country-combobox-label>{{ $label }}</label>
    <select
        name="{{ $name }}"
        id="{{ $fieldId }}"
        @if ($required) required @endif
        data-role="{{ $dataRole }}"
        data-country-combobox-native
        aria-labelledby="{{ $fieldId }}-label"
        {{ $attributes->merge(['class' => $selectClass]) }}
    >
        <option value="">Select a country</option>
        @foreach ($countries as $code => $labelText)
            <option value="{{ $code }}" @selected($selected === $code)>{{ $labelText }} ({{ $code }})</option>
        @endforeach
        @if ($legacyCountry)
            <option value="{{ $selected }}" selected>{{ $selected }} (update required)</option>
        @endif
    </select>
    <input
        type="text"
        id="{{ $searchId }}"
        class="{{ $selectClass }} hidden"
        data-country-combobox-search
        role="combobox"
        aria-labelledby="{{ $fieldId }}-label"
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="{{ $listId }}"
        placeholder="Search country or code"
        autocomplete="off"
        autocapitalize="off"
        autocorrect="off"
        spellcheck="false"
        data-lpignore="true"
        data-1p-ignore="true"
        readonly
        @if ($isDisabled) disabled @endif
    >
    <div
        id="{{ $listId }}"
        class="hidden max-h-64 overflow-y-auto rounded-lg border border-[#CBD5E1] bg-white py-1 shadow-lg shadow-slate-200/70"
        data-country-combobox-panel
        role="listbox"
        hidden
    ></div>
    @if ($legacyCountry)
        <p class="text-[11px] text-[#92400E]">This record uses a legacy country value. Choose a valid country from the list.</p>
    @endif
</div>
