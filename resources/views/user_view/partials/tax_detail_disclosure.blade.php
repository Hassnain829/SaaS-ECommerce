@php
    use App\Support\MoneyDisplay;

    $taxDisplay = $taxDisplay ?? [];
    $currency = $currency ?? 'USD';
    $disclosureId = $disclosureId ?? 'tax-breakdown-'.md5(json_encode($taxDisplay['source'] ?? 'none'));
    $title = $title ?? 'Tax details';
@endphp

<details id="{{ $disclosureId }}" class="rounded-xl border border-slate-200 bg-white" data-tax-breakdown-disclosure>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-slate-900 [&::-webkit-details-marker]:hidden">
        <span>
            {{ $title }} · {{ MoneyDisplay::formatWithCode($taxDisplay['total_tax'] ?? 0, $currency) }}
            @if ($taxDisplay['compact_summary'] ?? null)
                <span class="mt-0.5 block text-xs font-normal text-slate-500">{{ $taxDisplay['compact_summary'] }}</span>
            @elseif ($taxDisplay['source_label'] ?? null)
                <span class="mt-0.5 block text-xs font-normal text-slate-500">{{ $taxDisplay['source_label'] }}</span>
            @endif
        </span>
        <span class="shrink-0 text-xs font-semibold text-indigo-700" data-tax-breakdown-toggle-label>View breakdown</span>
    </summary>
    <div class="border-t border-slate-100 px-4 py-4">
        @include('user_view.partials.tax_detail_breakdown', [
            'taxDisplay' => $taxDisplay,
            'currency' => $currency,
            'title' => $title,
            'embedded' => true,
        ])
    </div>
</details>
