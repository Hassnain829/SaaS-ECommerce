@php
    use App\Support\MoneyDisplay;

    $variants = $variants ?? collect();
    $currency = $currency ?? 'USD';
    $isEditable = $isEditable ?? true;
    $lineRows = $lineRows ?? [[]];
    $persistedTaxByVariant = $persistedTaxByVariant ?? [];
    $showPersistedLineTax = $showPersistedLineTax ?? false;
@endphp

<div class="mt-4 space-y-3" data-draft-lines-container>
    <div class="hidden md:grid md:grid-cols-[minmax(0,1.4fr)_110px_90px_110px_110px_110px_44px] md:gap-3 md:px-3 md:text-xs md:font-semibold md:uppercase md:tracking-wide md:text-[#64748B]">
        <span>Product / variant</span>
        <span>Tax</span>
        <span>Qty</span>
        <span>Unit price</span>
        <span>Line tax</span>
        <span>Line total</span>
        <span class="sr-only">Remove</span>
    </div>

    @foreach ($lineRows as $index => $row)
        @php
            $variantId = (int) ($row['product_variant_id'] ?? 0);
            $selectedVariant = $variants->firstWhere('id', $variantId);
            $isTaxable = $selectedVariant ? (bool) $selectedVariant->product?->is_taxable : null;
            $lineTax = $row['tax_amount'] ?? ($persistedTaxByVariant[$variantId] ?? null);
            $qty = old("items.$index.quantity", $row['quantity'] ?? '');
            $unitPrice = old("items.$index.unit_price", $row['unit_price'] ?? '');
            $lineTotalAmount = \App\Support\Draft\DraftOrderFormState::lineTotalAmount([
                'product_variant_id' => $variantId,
                'quantity' => $qty !== '' ? $qty : ($row['quantity'] ?? ''),
                'unit_price' => $unitPrice !== '' ? $unitPrice : ($row['unit_price'] ?? ''),
                'line_total' => $row['line_total'] ?? null,
            ]);
        @endphp
        <div class="grid grid-cols-1 gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 md:grid-cols-[minmax(0,1.4fr)_110px_90px_110px_110px_110px_44px] md:items-end" data-draft-line>
            <div class="block min-w-0">
                <label for="items-{{ $index }}-product_variant_id" class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Product / variant</label>
                <select id="items-{{ $index }}-product_variant_id" name="items[{{ $index }}][product_variant_id]" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm {{ $errors->has('items.'.$index.'.product_variant_id') ? 'border-[#F87171]' : '' }}" data-variant-select @disabled(! $isEditable) @error('items.'.$index.'.product_variant_id') aria-invalid="true" aria-describedby="items-{{ $index }}-product_variant_id-error" @enderror>
                    <option value="">Select product</option>
                    @foreach ($variants as $variant)
                        @php($label = \App\Support\ProductVariantLabel::forVariant($variant, 0, 1))
                        <option value="{{ $variant->id }}"
                            data-price="{{ number_format((float) $variant->price, 2, '.', '') }}"
                            data-stock="{{ (int) $variant->stock }}"
                            data-sku="{{ $variant->sku }}"
                            data-taxable="{{ $variant->product?->is_taxable ? '1' : '0' }}"
                            data-label="{{ $variant->product?->name }} - {{ $label }}"
                            @selected($variantId === (int) $variant->id)>
                            {{ $variant->product?->name }} — {{ $label }} · SKU {{ $variant->sku ?: '—' }} · {{ (int) $variant->stock }} available
                        </option>
                    @endforeach
                </select>
                @error('items.'.$index.'.product_variant_id')
                    <p id="items-{{ $index }}-product_variant_id-error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                @enderror
                @if ($selectedVariant)
                    <p class="mt-1 text-xs text-[#64748B]" data-variant-meta>{{ $selectedVariant->sku ? 'SKU '.$selectedVariant->sku.' · ' : '' }}{{ (int) $selectedVariant->stock }} available</p>
                @else
                    <p class="mt-1 hidden text-xs text-[#64748B]" data-variant-meta></p>
                @endif
            </div>

            <div>
                <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Taxability</span>
                <span class="inline-flex min-h-10 w-full items-center rounded-lg bg-white px-3 text-xs font-semibold text-[#334155]" data-taxability-badge>
                    @if ($isTaxable === true)
                        <span class="text-[#166534]">Taxable</span>
                    @elseif ($isTaxable === false)
                        <span class="text-[#64748B]">Non-taxable</span>
                    @else
                        <span class="text-[#94A3B8]">—</span>
                    @endif
                </span>
            </div>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Quantity</span>
                <input id="items-{{ $index }}-quantity" name="items[{{ $index }}][quantity]" value="{{ $qty }}" type="number" min="1" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm {{ $errors->has('items.'.$index.'.quantity') ? 'border-[#F87171]' : '' }}" data-quantity-input @readonly(! $isEditable) @error('items.'.$index.'.quantity') aria-invalid="true" aria-describedby="items-{{ $index }}-quantity-error" @enderror>
                @error('items.'.$index.'.quantity')
                    <p id="items-{{ $index }}-quantity-error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Unit price</span>
                <input id="items-{{ $index }}-unit_price" name="items[{{ $index }}][unit_price]" value="{{ $unitPrice }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm {{ $errors->has('items.'.$index.'.unit_price') ? 'border-[#F87171]' : '' }}" data-unit-price-input @readonly(! $isEditable) @error('items.'.$index.'.unit_price') aria-invalid="true" aria-describedby="items-{{ $index }}-unit_price-error" @enderror>
                @error('items.'.$index.'.unit_price')
                    <p id="items-{{ $index }}-unit_price-error" class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                @enderror
            </label>

            <div>
                <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Line tax</span>
                <span class="flex min-h-10 items-center rounded-lg bg-white px-3 text-sm tabular-nums text-[#475569]" data-line-tax-display @if($showPersistedLineTax && $lineTax !== null) data-persisted-line-tax="{{ number_format((float) $lineTax, 2, '.', '') }}" @endif>
                    @if ($showPersistedLineTax && $lineTax !== null)
                        {{ MoneyDisplay::formatWithCode($lineTax, $currency) }}
                    @else
                        —
                    @endif
                </span>
            </div>

            <div>
                <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Line total</span>
                <span class="flex min-h-10 items-center rounded-lg bg-white px-3 text-sm font-semibold tabular-nums text-[#0F172A]" data-line-total>{{ MoneyDisplay::formatWithCode($lineTotalAmount, $currency) }}</span>
            </div>

            @if ($isEditable)
                <div class="flex items-end justify-end">
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#FECACA] bg-white text-[#991B1B] hover:bg-[#FEF2F2]" data-remove-line aria-label="Remove line item">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
            @endif
        </div>
    @endforeach
</div>

@if ($isEditable && $variants->isNotEmpty())
    <button type="button" class="mt-3 inline-flex h-10 items-center rounded-lg border border-dashed border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#0052CC] hover:bg-[#F8FAFC]" data-add-line>
        + Add line item
    </button>
@endif

<template id="draft-line-row-template">
    <div class="grid grid-cols-1 gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 md:grid-cols-[minmax(0,1.4fr)_110px_90px_110px_110px_110px_44px] md:items-end" data-draft-line>
        <label class="block min-w-0">
            <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Product / variant</span>
            <select name="items[__INDEX__][product_variant_id]" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" data-variant-select>
                <option value="">Select product</option>
                @foreach ($variants as $variant)
                    @php($label = \App\Support\ProductVariantLabel::forVariant($variant, 0, 1))
                    <option value="{{ $variant->id }}"
                        data-price="{{ number_format((float) $variant->price, 2, '.', '') }}"
                        data-stock="{{ (int) $variant->stock }}"
                        data-sku="{{ $variant->sku }}"
                        data-taxable="{{ $variant->product?->is_taxable ? '1' : '0' }}"
                        data-label="{{ $variant->product?->name }} - {{ $label }}">
                        {{ $variant->product?->name }} — {{ $label }} · SKU {{ $variant->sku ?: '—' }} · {{ (int) $variant->stock }} available
                    </option>
                @endforeach
            </select>
            <p class="mt-1 hidden text-xs text-[#64748B]" data-variant-meta></p>
        </label>
        <div>
            <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Taxability</span>
            <span class="inline-flex min-h-10 w-full items-center rounded-lg bg-white px-3 text-xs font-semibold text-[#94A3B8]" data-taxability-badge>—</span>
        </div>
        <label class="block">
            <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Quantity</span>
            <input name="items[__INDEX__][quantity]" type="number" min="1" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" data-quantity-input>
        </label>
        <label class="block">
            <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Unit price</span>
            <input name="items[__INDEX__][unit_price]" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" data-unit-price-input>
        </label>
        <div>
            <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Line tax</span>
            <span class="flex min-h-10 items-center rounded-lg bg-white px-3 text-sm tabular-nums text-[#475569]" data-line-tax-display>—</span>
        </div>
        <div>
            <span class="mb-1 block text-xs font-semibold text-[#64748B] md:sr-only">Line total</span>
            <span class="flex min-h-10 items-center rounded-lg bg-white px-3 text-sm font-semibold tabular-nums text-[#0F172A]" data-line-total>{{ MoneyDisplay::formatWithCode(0, $currency) }}</span>
        </div>
        <div class="flex items-end justify-end">
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#FECACA] bg-white text-[#991B1B] hover:bg-[#FEF2F2]" data-remove-line aria-label="Remove line item">
                <span aria-hidden="true">×</span>
            </button>
        </div>
    </div>
</template>
