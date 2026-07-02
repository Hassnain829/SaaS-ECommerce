@props([
    'name' => 'state',
    'id' => null,
    'countryCode' => '',
    'selected' => '',
    'label' => 'State / province',
    'selectClass' => 'w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2 text-sm',
])

@php
    use App\Support\Tax\TaxCountryCatalog;

    $countryCode = strtoupper(trim((string) $countryCode));
    $selected = strtoupper(trim((string) $selected));
    $fieldId = $id ?? $name;
    $regions = $countryCode !== '' ? TaxCountryCatalog::regionsFor($countryCode) : [];
    $knownRegion = $selected !== '' && array_key_exists($selected, $regions);
    $legacyRegion = $selected !== '' && $regions !== [] && ! $knownRegion;
@endphp

<label class="block space-y-1" data-role="geo-region-single-wrapper">
    <span class="text-xs font-semibold text-[#64748B]">{{ $label }}</span>
    @if ($regions === [])
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            value="{{ $selected }}"
            placeholder="Region"
            data-role="geo-region-text"
            {{ $attributes->merge(['class' => $selectClass.' uppercase']) }}
        >
    @else
        <select
            name="{{ $name }}"
            id="{{ $fieldId }}"
            data-role="geo-region-single-select"
            data-country="{{ $countryCode }}"
            {{ $attributes->merge(['class' => $selectClass]) }}
        >
            <option value="">Select a state / province</option>
            @foreach ($regions as $code => $regionLabel)
                <option value="{{ $code }}" @selected($selected === $code && ! $legacyRegion)>{{ $regionLabel }} ({{ $code }})</option>
            @endforeach
            @if ($legacyRegion)
                <option value="{{ $selected }}" selected>{{ $selected }} (legacy)</option>
            @endif
        </select>
    @endif
</label>
