@props([
    'name' => 'country_code',
    'id' => null,
    'selected' => '',
    'countries' => null,
    'required' => false,
    'selectClass' => 'h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm',
    'label' => 'Country',
    'dataRole' => 'geo-country-select',
])

@php
    use App\Support\Tax\TaxCountryCatalog;

    $countries = $countries ?? TaxCountryCatalog::all();
    $selected = strtoupper(trim((string) $selected));
    $fieldId = $id ?? $name;
    $legacyCountry = $selected !== '' && ! array_key_exists($selected, $countries);
@endphp

<label class="block space-y-1">
    <span class="text-xs font-semibold text-[#64748B]">{{ $label }}</span>
    <select
        name="{{ $name }}"
        id="{{ $fieldId }}"
        @if ($required) required @endif
        data-role="{{ $dataRole }}"
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
    @if ($legacyCountry)
        <p class="text-[11px] text-[#92400E]">This record uses a legacy country value. Choose a valid country from the list.</p>
    @endif
</label>
