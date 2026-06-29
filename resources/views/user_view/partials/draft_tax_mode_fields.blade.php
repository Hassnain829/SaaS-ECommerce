@php
    $taxSetting = $taxSetting ?? null;
    $taxEnabled = (bool) ($taxSetting?->enabled ?? false);
    $isEditable = $isEditable ?? true;
    $selectedTaxMode = $selectedTaxMode ?? \App\Models\DraftOrder::TAX_SOURCE_MANUAL;
    $isCalculatedTax = $selectedTaxMode === \App\Models\DraftOrder::TAX_SOURCE_CALCULATED;
    $calculatedTaxTotal = $calculatedTaxTotal ?? '0.00';
    $manualTaxTotal = $manualTaxTotal ?? ($persistedTaxTotal ?? '0.00');
    $wasCalculated = $wasCalculated ?? false;
    $taxGuidance = $taxGuidance ?? null;
    $manualTaxValue = old('manual_tax_total', old('tax_total', $manualTaxTotal));
@endphp

<fieldset class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
    <legend class="px-1 text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Tax mode</legend>

    @if ($isEditable)
        <div class="mt-2 space-y-2">
            <label class="flex items-start gap-2 text-sm text-[#334155]">
                <input type="radio" name="tax_mode" value="{{ \App\Models\DraftOrder::TAX_SOURCE_CALCULATED }}" @checked($selectedTaxMode === \App\Models\DraftOrder::TAX_SOURCE_CALCULATED) @disabled(! $taxEnabled) data-tax-mode-radio>
                <span>
                    <span class="font-semibold text-[#0F172A]">Automatic from store settings</span>
                    <span class="mt-0.5 block text-xs text-[#64748B]">
                        @if ($taxEnabled)
                            Tax is calculated from taxable items, the shipping destination, and taxable shipping. Use two-letter country and state codes that match your tax rates (for example US and NY).
                        @else
                            Platform tax is disabled for this store. Enable tax in settings to use automatic calculation.
                        @endif
                    </span>
                </span>
            </label>
            <label class="flex items-start gap-2 text-sm text-[#334155]">
                <input type="radio" name="tax_mode" value="{{ \App\Models\DraftOrder::TAX_SOURCE_MANUAL }}" @checked($selectedTaxMode === \App\Models\DraftOrder::TAX_SOURCE_MANUAL) data-tax-mode-radio>
                <span>
                    <span class="font-semibold text-[#0F172A]">Manual tax</span>
                    <span class="mt-0.5 block text-xs text-[#64748B]">Enter the tax amount yourself.</span>
                </span>
            </label>
        </div>
    @else
        <p class="mt-2 text-sm font-semibold text-[#0F172A]">{{ $isCalculatedTax ? 'Automatic from store settings' : 'Manual tax' }}</p>
    @endif

    <div class="mt-3 rounded-lg border border-[#E2E8F0] bg-white p-3" data-calculated-tax-fields>
        <label for="draft-calculated-tax-display" class="block text-xs font-semibold text-[#64748B]">Automatic tax total</label>
        <p id="draft-calculated-tax-display" class="mt-1 min-h-10 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2.5 text-sm tabular-nums text-[#0F172A]" data-calculated-tax-display>{{ number_format((float) $calculatedTaxTotal, 2, '.', '') }}</p>
        <p class="mt-2 text-xs leading-relaxed text-[#64748B]">Preview updates as you change products and shipping address. Tax is confirmed when you save the draft.</p>
        <p class="mt-2 hidden rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs text-[#92400E]" data-tax-stale-notice role="status">Tax will be recalculated when you save.</p>
        @if ($taxGuidance)
            <p class="mt-2 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs text-[#92400E]" data-tax-preview-guidance role="status">{{ $taxGuidance }}</p>
        @else
            <p class="mt-2 hidden rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs text-[#92400E]" data-tax-preview-guidance role="status"></p>
        @endif
    </div>

    <div class="mt-3 rounded-lg border border-[#E2E8F0] bg-white p-3" data-manual-tax-fields>
        <label for="draft-manual-tax-total" class="block text-xs font-semibold text-[#64748B]">Manual tax amount</label>
        <input id="draft-manual-tax-total" name="manual_tax_total" value="{{ $manualTaxValue }}" type="number" min="0" step="0.01" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm tabular-nums {{ $errors->has('manual_tax_total') || $errors->has('tax_total') ? 'border-[#F87171]' : '' }}" data-manual-tax-input @readonly(! $isEditable) @error('manual_tax_total') aria-invalid="true" aria-describedby="draft-manual-tax-error" @enderror @error('tax_total') aria-invalid="true" aria-describedby="draft-manual-tax-error" @enderror>
        @error('manual_tax_total')
            <p id="draft-manual-tax-error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
        @else
            @error('tax_total')
                <p id="draft-manual-tax-error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
            @enderror
        @enderror
        <p class="mt-2 text-xs text-[#64748B]">Used when manual mode is selected.</p>

        @if ($wasCalculated && $isEditable)
            <div class="mt-3 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2">
                <label class="flex items-start gap-2 text-xs text-[#92400E]">
                    <input type="checkbox" name="confirm_manual_tax_switch" value="1" @checked(old('confirm_manual_tax_switch')) data-confirm-manual-tax-switch @error('confirm_manual_tax_switch') aria-invalid="true" aria-describedby="confirm-manual-tax-switch-error" @enderror>
                    <span>I understand that calculated tax lines and rate details will be removed.</span>
                </label>
                @error('confirm_manual_tax_switch')
                    <p id="confirm-manual-tax-switch-error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>
</fieldset>
