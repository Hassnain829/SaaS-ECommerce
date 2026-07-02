@props([
    'name' => 'region_codes',
    'id' => 'geo-region-multi',
    'countryCode' => '',
    'selected' => [],
    'label' => 'States / provinces (optional)',
    'help' => 'Leave empty to cover the entire country.',
])

@php
    use App\Support\Tax\TaxCountryCatalog;

    $countryCode = strtoupper(trim((string) $countryCode));
    $selected = collect(is_array($selected) ? $selected : [])
        ->map(fn ($code): string => strtoupper(trim((string) $code)))
        ->filter()
        ->values()
        ->all();
    $regions = $countryCode !== '' ? TaxCountryCatalog::regionsFor($countryCode) : [];
@endphp

<div
    id="{{ $id }}"
    class="space-y-2"
    data-role="geo-region-multi"
    data-country="{{ $countryCode }}"
    data-name="{{ $name }}"
>
    <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold text-[#64748B]">{{ $label }}</span>
        @if ($regions !== [])
            <button type="button" class="text-[11px] font-semibold text-[#1D4ED8] hover:underline" data-region-action="clear">Clear all</button>
        @endif
    </div>
    <p class="text-[11px] text-[#94A3B8]">{{ $help }}</p>

    @if ($countryCode === '')
        <p class="rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">Choose a country first to see states or provinces.</p>
    @elseif ($regions === [])
        <p class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">This country has no predefined regions. The entire country will be covered.</p>
    @else
        <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2">
            @foreach ($regions as $code => $regionLabel)
                <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-[#334155] hover:bg-white">
                    <input
                        type="checkbox"
                        name="{{ $name }}[]"
                        value="{{ $code }}"
                        @checked(in_array($code, $selected, true))
                        class="rounded border-[#CBD5E1]"
                    >
                    <span>{{ $regionLabel }} ({{ $code }})</span>
                </label>
            @endforeach
        </div>
    @endif
</div>
