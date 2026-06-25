@php
    /** @var array<string, mixed> $taxDisplay */
    use App\Support\Tax\TaxDisplayPresenter;

    $currency = $currency ?? 'USD';
    $snapshot = $taxDisplay['snapshot'] ?? null;
    $taxLines = $taxDisplay['tax_lines'] ?? collect();
    $title = $title ?? 'Tax details';
@endphp

<section class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 md:p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
            <p class="mt-1 text-xs text-slate-600">{{ $taxDisplay['source_label'] }}</p>
        </div>
        @if ($taxDisplay['source'] === \App\Support\Tax\TaxDisplayPresenter::SOURCE_PLATFORM_CALCULATED)
            <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-indigo-800">Calculated</span>
        @elseif ($taxDisplay['source'] === \App\Support\Tax\TaxDisplayPresenter::SOURCE_MANUAL)
            <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-900">Manual</span>
        @elseif ($taxDisplay['source'] === \App\Support\Tax\TaxDisplayPresenter::SOURCE_EXTERNAL_PRESERVED)
            <span class="inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-800">External</span>
        @endif
    </div>

    @if ($taxDisplay['source'] === \App\Support\Tax\TaxDisplayPresenter::SOURCE_EXTERNAL_PRESERVED)
        <p class="mt-3 text-sm text-slate-700">External checkout tax total preserved by the platform: <strong>{{ $currency }}{{ number_format((float) $taxDisplay['total_tax'], 2) }}</strong></p>
        <p class="mt-2 text-xs text-slate-500">This amount was supplied by the external website or integration. The platform did not recalculate it.</p>
    @elseif ($taxDisplay['source'] === \App\Support\Tax\TaxDisplayPresenter::SOURCE_MANUAL)
        <p class="mt-3 text-sm text-slate-700">Manual tax amount: <strong>{{ $currency }}{{ number_format((float) $taxDisplay['total_tax'], 2) }}</strong></p>
        <p class="mt-2 text-xs text-slate-500">No calculated rate breakdown is available for manual tax.</p>
    @elseif ($taxDisplay['source'] === \App\Support\Tax\TaxDisplayPresenter::SOURCE_NONE)
        <p class="mt-3 text-sm text-slate-600">No tax was recorded on this order.</p>
    @elseif ($taxDisplay['source'] === \App\Support\Tax\TaxDisplayPresenter::SOURCE_LEGACY)
        <p class="mt-3 text-sm text-slate-700">Tax total: <strong>{{ $currency }}{{ number_format((float) $taxDisplay['total_tax'], 2) }}</strong></p>
        <p class="mt-2 text-xs text-slate-500">Detailed tax breakdown was not stored for this order.</p>
    @else
        @if ($taxDisplay['show_inclusive_note'])
            <p class="mt-3 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-xs text-indigo-900">Tax is included in item prices. Item tax shown below is extracted from displayed prices and is not added again to the order total.</p>
        @endif

        <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            @if ($destination = TaxDisplayPresenter::destinationLabel($snapshot))
                <div><dt class="text-xs uppercase tracking-wide text-slate-500">Destination</dt><dd class="font-medium text-slate-900">{{ $destination }}</dd></div>
            @endif
            <div><dt class="text-xs uppercase tracking-wide text-slate-500">Price mode</dt><dd class="font-medium text-slate-900">{{ TaxDisplayPresenter::priceModeLabel((bool) $taxDisplay['prices_include_tax']) }}</dd></div>
            @if ($rateLabel = TaxDisplayPresenter::matchedRateLabel($snapshot))
                <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-wide text-slate-500">Matched rate</dt><dd class="font-medium text-slate-900">{{ $rateLabel }}</dd></div>
            @endif
            <div><dt class="text-xs uppercase tracking-wide text-slate-500">Item tax</dt><dd class="font-medium tabular-nums text-slate-900">{{ $currency }}{{ number_format((float) $taxDisplay['item_tax_total'], 2) }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-500">Shipping tax</dt><dd class="font-medium tabular-nums text-slate-900">{{ $currency }}{{ number_format((float) $taxDisplay['shipping_tax_total'], 2) }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-500">Total tax</dt><dd class="font-semibold tabular-nums text-slate-900">{{ $currency }}{{ number_format((float) $taxDisplay['total_tax'], 2) }}</dd></div>
        </dl>

        @if ($taxLines->isNotEmpty())
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Applies to</th>
                            <th class="px-3 py-2">Jurisdiction</th>
                            <th class="px-3 py-2">Rate</th>
                            <th class="px-3 py-2">Taxable amount</th>
                            <th class="px-3 py-2">Tax</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($taxLines as $line)
                            @php
                                $region = trim((string) ($line->jurisdiction_region_code ?? ''));
                                $jurisdiction = strtoupper((string) $line->jurisdiction_country_code).($region !== '' ? ' / '.$region : '');
                            @endphp
                            <tr>
                                <td class="px-3 py-2 text-slate-700">{{ TaxDisplayPresenter::lineAppliesLabel((string) $line->applies_to) }}</td>
                                <td class="px-3 py-2 text-slate-700">{{ $jurisdiction !== '' ? $jurisdiction : '—' }}</td>
                                <td class="px-3 py-2 tabular-nums text-slate-700">{{ number_format((float) $line->rate_percent, 4) }}%</td>
                                <td class="px-3 py-2 tabular-nums text-slate-700">{{ $currency }}{{ number_format((float) $line->taxable_amount, 2) }}</td>
                                <td class="px-3 py-2 tabular-nums font-medium text-slate-900">{{ $currency }}{{ number_format((float) $line->tax_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif (! $taxDisplay['has_breakdown'])
            <p class="mt-3 text-sm text-slate-600">No calculated tax lines were stored for this record.</p>
        @endif

        @if ($snapshot)
            <details class="mt-4 rounded-lg border border-slate-200 bg-white px-3 py-2">
                <summary class="cursor-pointer text-xs font-semibold text-slate-600">Calculation metadata</summary>
                <dl class="mt-2 grid grid-cols-1 gap-2 text-xs text-slate-600 sm:grid-cols-2">
                    @if (data_get($snapshot, 'settings_version'))
                        <div><dt class="font-semibold text-slate-700">Settings version</dt><dd>{{ data_get($snapshot, 'settings_version') }}</dd></div>
                    @endif
                    @if (data_get($snapshot, 'calculated_at'))
                        <div><dt class="font-semibold text-slate-700">Calculated at</dt><dd>{{ data_get($snapshot, 'calculated_at') }}</dd></div>
                    @endif
                    @if (data_get($snapshot, 'destination.country_code'))
                        <div><dt class="font-semibold text-slate-700">Country code</dt><dd>{{ strtoupper((string) data_get($snapshot, 'destination.country_code')) }}</dd></div>
                    @endif
                    @if (filled(data_get($snapshot, 'destination.region_code')))
                        <div><dt class="font-semibold text-slate-700">Region code</dt><dd>{{ strtoupper((string) data_get($snapshot, 'destination.region_code')) }}</dd></div>
                    @endif
                </dl>
            </details>
        @endif
    @endif
</section>
