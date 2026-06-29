@php
    $taxSetting = $taxSetting ?? null;
    $defaultProductTaxable = (bool) ($taxSetting?->default_product_taxable ?? true);
    $taxEnabled = (bool) ($taxSetting?->enabled ?? false);
    $inputId = $inputId ?? 'product-is-taxable';
    $checkedOverride = $checkedOverride ?? null;

    $oldTaxable = old('is_taxable');
    if ($oldTaxable !== null) {
        if (is_array($oldTaxable)) {
            $oldTaxable = end($oldTaxable);
        }
        $isTaxableChecked = filter_var($oldTaxable, FILTER_VALIDATE_BOOLEAN);
    } elseif ($checkedOverride !== null) {
        $isTaxableChecked = (bool) $checkedOverride;
    } else {
        $isTaxableChecked = $defaultProductTaxable;
    }
@endphp

<div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
    <input type="hidden" name="is_taxable" value="0">
    <label class="flex items-start gap-3">
        <input
            id="{{ $inputId }}"
            name="is_taxable"
            type="checkbox"
            value="1"
            class="mt-0.5 rounded border-[#CBD5E1] text-[#0052CC] focus:ring-[#0052CC]"
            @checked($isTaxableChecked)
            @error('is_taxable') aria-invalid="true" aria-describedby="{{ $inputId }}-help {{ $inputId }}-error" @enderror
        >
        <span class="min-w-0">
            <span class="block text-sm font-semibold text-[#0F172A]">Charge tax on this product</span>
            <span id="{{ $inputId }}-help" class="mt-1 block text-xs leading-relaxed text-[#64748B]">
                This product is eligible for platform tax. The actual rate is selected from the customer&apos;s shipping address.
            </span>
            <span class="mt-2 block text-xs text-[#64748B]">
                Store default:
                <span class="font-semibold text-[#334155]">{{ $defaultProductTaxable ? 'Taxable' : 'Not taxable' }}</span>
                @if (Route::has('settings.taxes.index'))
                    · <a href="{{ route('settings.taxes.index') }}" class="font-semibold text-[#0052CC] hover:underline">Manage tax settings</a>
                @endif
            </span>
            @if (! $taxEnabled && $isTaxableChecked)
                <span class="mt-2 block rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs text-[#92400E]">
                    Saved as taxable, but platform checkout tax is currently disabled.
                </span>
            @endif
        </span>
    </label>
    @error('is_taxable')
        <p id="{{ $inputId }}-error" class="mt-2 text-xs text-[#B42318]">{{ $message }}</p>
    @enderror
</div>
