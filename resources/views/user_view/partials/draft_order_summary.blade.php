@php
    use App\Support\MoneyDisplay;

    $currency = $currency ?? 'USD';
    $subtotal = $subtotal ?? '0.00';
    $discount = $discount ?? '0.00';
    $shipping = $shipping ?? '0.00';
    $tax = $tax ?? '0.00';
    $total = $total ?? '0.00';
    $isEstimate = $isEstimate ?? false;
    $taxDisplay = $taxDisplay ?? null;
    $pricesIncludeTax = (bool) ($taxDisplay['prices_include_tax'] ?? false);
    $summaryTaxLabel = $summaryTaxLabel ?? null;
    $summaryTotalLabel = $summaryTotalLabel ?? null;
@endphp

<section class="rounded-2xl border border-[#CBD5E1] bg-white p-5" data-order-summary>
    <h3 class="text-lg font-semibold text-[#0F172A]">Order summary</h3>
    @if ($isEstimate)
        <p class="mt-1 text-xs text-[#64748B]">Estimated preview before save. Totals are confirmed when you save the draft.</p>
    @endif
    @if ($pricesIncludeTax)
        <p class="mt-2 rounded-lg border border-[#E0E7FF] bg-[#EEF2FF] px-3 py-2 text-xs text-[#3730A3]">Tax is included in item prices. Item tax shown below is not added again to the total.</p>
    @endif

    <dl class="mt-4 space-y-2 text-sm">
        <div class="flex items-center justify-between gap-4">
            <dt class="text-[#64748B]">Subtotal</dt>
            <dd class="font-semibold tabular-nums text-[#0F172A]" data-subtotal-display>{{ MoneyDisplay::formatWithCode($subtotal, $currency) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4">
            <dt class="text-[#64748B]">Discount</dt>
            <dd class="font-semibold tabular-nums text-[#166534]" data-discount-display>{{ MoneyDisplay::formatDiscountWithCode($discount, $currency) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4">
            <dt class="text-[#64748B]">Shipping</dt>
            <dd class="font-semibold tabular-nums text-[#0F172A]" data-shipping-display>{{ MoneyDisplay::formatWithCode($shipping, $currency) }}</dd>
        </div>
        <div class="flex items-start justify-between gap-4">
            <dt class="text-[#64748B]">
                Tax
                @if ($taxDisplay && ($taxDisplay['compact_summary'] ?? null) && ! $summaryTaxLabel)
                    <span class="mt-0.5 block text-xs font-normal text-[#94A3B8]">{{ $taxDisplay['compact_summary'] }}</span>
                @endif
            </dt>
            <dd class="font-semibold tabular-nums text-[#0F172A] {{ $summaryTaxLabel ? 'text-sm italic text-[#92400E]' : '' }}" data-tax-summary-display>
                {{ $summaryTaxLabel ?? MoneyDisplay::formatWithCode($tax, $currency) }}
            </dd>
        </div>
    </dl>

    <div class="mt-4 border-t border-[#E2E8F0] pt-4 flex items-end justify-between gap-4">
        <span class="text-base font-semibold text-[#0F172A]">Total</span>
        <span class="text-2xl font-bold tabular-nums text-[#0052CC] {{ $summaryTotalLabel ? 'text-base italic text-[#92400E]' : '' }}" data-total-display>
            {{ $summaryTotalLabel ?? MoneyDisplay::formatWithCode($total, $currency) }}
        </span>
    </div>

    <div class="mt-4 space-y-3">
        <label for="draft-discount-total" class="block text-xs font-semibold text-[#64748B]">Discount amount</label>
        <input id="draft-discount-total" name="discount_total" value="{{ old('discount_total', $discount) }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm tabular-nums {{ $errors->has('discount_total') ? 'border-[#F87171]' : '' }}" data-discount-input data-tax-driving-input @readonly(! ($isEditable ?? true)) @error('discount_total') aria-invalid="true" aria-describedby="draft-discount-error" @enderror>
        @error('discount_total')
            <p id="draft-discount-error" class="text-xs text-[#B91C1C]">{{ $message }}</p>
        @enderror

        <label for="draft-shipping-total" class="block text-xs font-semibold text-[#64748B]">Shipping amount</label>
        <input id="draft-shipping-total" name="shipping_total" value="{{ old('shipping_total', $shipping) }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm tabular-nums {{ $errors->has('shipping_total') ? 'border-[#F87171]' : '' }}" data-shipping-input data-tax-driving-input @readonly(! ($isEditable ?? true)) @error('shipping_total') aria-invalid="true" aria-describedby="draft-shipping-error" @enderror>
        @error('shipping_total')
            <p id="draft-shipping-error" class="text-xs text-[#B91C1C]">{{ $message }}</p>
        @enderror
    </div>
</section>
