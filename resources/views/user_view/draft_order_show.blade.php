@extends('layouts.user.user-sidebar')

@section('title', $draftOrder->draft_number . ' | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
    </button>
    <div class="min-w-0">
        <h1 class="truncate text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">{{ $draftOrder->draft_number }}</h1>
        <p class="hidden md:block text-xs text-[#64748B]">Manual order draft for {{ $draftOrder->customer?->full_name ?? $draftOrder->customer?->email ?? 'a customer' }}.</p>
    </div>
    <a href="{{ route('orders') }}" class="h-10 px-4 rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A] inline-flex items-center justify-center hover:bg-[#F8FAFC]">Back to orders</a>
</header>
@endsection

@section('content')
@php
    $currency = $draftOrder->currency ?: ($selectedStore->currency ?? 'USD');
    $shipping = $draftOrder->shippingAddress();
    $isEditable = $draftOrder->status === \App\Models\DraftOrder::STATUS_DRAFT;
    $taxSource = $draftOrder->taxSource();
@endphp

<div class="w-full py-2 md:py-4 space-y-4">
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Draft order</p>
                <h2 class="mt-1 text-2xl md:text-3xl font-poppins font-semibold text-[#0F172A]">{{ $draftOrder->draft_number }}</h2>
                <p class="mt-1 text-sm text-[#64748B]">Draft status: {{ ucfirst($draftOrder->status) }}</p>
            </div>
            @if($draftOrder->convertedOrder)
                <a href="{{ route('orderViewDetails', $draftOrder->convertedOrder) }}" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white">View created order</a>
            @endif
        </div>
    </section>

    <form id="draftOrderForm" action="{{ route('draft-orders.update', $draftOrder) }}" method="POST" class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start" data-draft-order-form data-currency="{{ $currency }}">
        @csrf

        <div class="space-y-4">
            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Customer</h3>
                <div class="mt-4 rounded-xl bg-[#F8FAFC] p-4 text-sm text-[#334155]">
                    <p class="font-semibold text-[#0F172A]">{{ $draftOrder->customer?->full_name ?? $draftOrder->customer?->email ?? 'No customer selected' }}</p>
                    <p class="mt-1 text-[#64748B]">{{ $draftOrder->customer?->email ?? 'Add a customer before creating the order.' }}</p>
                    @if($draftOrder->customer?->phone)
                        <p class="mt-1 text-[#64748B]">{{ $draftOrder->customer->phone }}</p>
                    @endif
                </div>
                <input type="hidden" name="customer_id" value="{{ $draftOrder->customer_id }}">
            </section>

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Products</h3>
                <div class="mt-4 space-y-3">
                    @foreach($draftOrder->items as $i => $item)
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 md:grid-cols-[minmax(0,1fr)_90px_120px_120px]" data-draft-line>
                            <select name="items[{{ $i }}][product_variant_id]" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" data-variant-select @disabled(! $isEditable)>
                                @foreach($variants as $variant)
                                    @php($label = \App\Support\ProductVariantLabel::forVariant($variant, 0, 1))
                                    <option value="{{ $variant->id }}"
                                        data-price="{{ number_format((float) $variant->price, 2, '.', '') }}"
                                        data-stock="{{ (int) $variant->stock }}"
                                        data-sku="{{ $variant->sku }}"
                                        data-label="{{ $variant->product?->name }} - {{ $label }}"
                                        @selected($item->product_variant_id === $variant->id)>
                                        {{ $variant->product?->name }} - {{ $label }} - {{ $currency }} {{ number_format((float) $variant->price, 2) }} - {{ (int) $variant->stock }} available
                                    </option>
                                @endforeach
                            </select>
                            <input name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", $item->quantity) }}" type="number" min="1" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" data-quantity-input @readonly(! $isEditable)>
                            <input name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price", $item->unit_price) }}" type="number" min="0" step="0.01" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" data-unit-price-input @readonly(! $isEditable)>
                            <div class="flex min-h-10 items-center justify-between rounded-lg bg-white px-3 py-2 text-sm text-[#64748B]">
                                <span>Line</span>
                                <span class="font-semibold text-[#0F172A]" data-line-total>{{ $currency }} {{ number_format((float) $item->line_total, 2) }}</span>
                            </div>
                        </div>
                    @endforeach

                    @if($isEditable)
                        @php($newIndex = $draftOrder->items->count())
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-dashed border-[#CBD5E1] bg-white p-3 md:grid-cols-[minmax(0,1fr)_90px_120px_120px]" data-draft-line>
                            <select name="items[{{ $newIndex }}][product_variant_id]" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" data-variant-select>
                                <option value="">Add another product</option>
                                @foreach($variants as $variant)
                                    @php($label = \App\Support\ProductVariantLabel::forVariant($variant, 0, 1))
                                    <option value="{{ $variant->id }}"
                                        data-price="{{ number_format((float) $variant->price, 2, '.', '') }}"
                                        data-stock="{{ (int) $variant->stock }}"
                                        data-sku="{{ $variant->sku }}"
                                        data-label="{{ $variant->product?->name }} - {{ $label }}">
                                        {{ $variant->product?->name }} - {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <input name="items[{{ $newIndex }}][quantity]" type="number" min="1" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" placeholder="Qty" data-quantity-input>
                            <input name="items[{{ $newIndex }}][unit_price]" type="number" min="0" step="0.01" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" placeholder="Price" data-unit-price-input>
                            <div class="flex min-h-10 items-center justify-between rounded-lg bg-[#F8FAFC] px-3 py-2 text-sm text-[#64748B]">
                                <span>Line</span>
                                <span class="font-semibold text-[#0F172A]" data-line-total>{{ $currency }} 0.00</span>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Shipping address</h3>
                <p class="mt-1 text-sm text-[#64748B]">Address, city, and country are required before creating the order.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach([
                        'shipping_name' => 'Recipient name',
                        'shipping_phone' => 'Phone',
                        'shipping_address_line1' => 'Address line 1',
                        'shipping_address_line2' => 'Address line 2',
                        'shipping_city' => 'City',
                    ] as $name => $placeholder)
                        @php($key = str_replace('shipping_', '', $name))
                        <input name="{{ $name }}" value="{{ old($name, $shipping[$key] ?? '') }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm {{ str_contains($name, 'address_line') ? 'md:col-span-2' : '' }}" placeholder="{{ $placeholder }}" @readonly(! $isEditable)>
                        @error($name)
                            <p class="-mt-3 text-xs text-[#B91C1C] {{ str_contains($name, 'address_line') ? 'md:col-span-2' : '' }}">{{ $message }}</p>
                        @enderror
                    @endforeach
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">State / region code</span>
                        <input name="shipping_state" value="{{ old('shipping_state', $shipping['state'] ?? '') }}" maxlength="32" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase" placeholder="CA, NY, ON" @readonly(! $isEditable)>
                        @error('shipping_state')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Postal code</span>
                        <input name="shipping_postal_code" value="{{ old('shipping_postal_code', $shipping['postal_code'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Postal code" @readonly(! $isEditable)>
                        @error('shipping_postal_code')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Country code</span>
                        <input name="shipping_country" value="{{ old('shipping_country', $shipping['country'] ?? '') }}" maxlength="2" pattern="[A-Za-z]{2}" autocomplete="country" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase" placeholder="US, CA, GB, AU" @readonly(! $isEditable)>
                        @error('shipping_country')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm text-[#475569]">
                    <input type="checkbox" name="billing_same_as_shipping" value="1" @checked($draftOrder->billingSameAsShipping()) @disabled(! $isEditable) class="rounded border-[#CBD5E1]">
                    Billing address is the same as shipping
                </label>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Totals</h3>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><span class="text-[#64748B]">Subtotal</span><span class="font-semibold" data-subtotal-display>{{ $currency }} {{ number_format((float) $draftOrder->subtotal, 2) }}</span></div>
                    <input name="discount_total" value="{{ old('discount_total', $draftOrder->discount_total) }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Discount" data-discount-input @readonly(! $isEditable)>
                    <input name="tax_total" value="{{ old('tax_total', $draftOrder->tax_total) }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Tax" data-tax-input @readonly(! $isEditable)>
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Tax source</p>
                                <p class="mt-1 text-sm font-semibold text-[#0F172A]">{{ $taxSource === \App\Models\DraftOrder::TAX_SOURCE_CALCULATED ? 'Calculated from store settings' : 'Manual tax' }}</p>
                            </div>
                            @if($isEditable)
                                <button type="submit" formaction="{{ route('draft-orders.calculate-tax', $draftOrder) }}" formmethod="POST" class="rounded-lg border border-[#BFDBFE] bg-white px-3 py-2 text-xs font-semibold text-[#1D4ED8] hover:bg-[#EFF6FF]">Save and calculate tax</button>
                            @endif
                        </div>
                        <p class="mt-2 text-xs leading-relaxed text-[#64748B]">Calculated tax uses your configured basic rates and is not tax advice.</p>
                    </div>
                    <input name="shipping_total" value="{{ old('shipping_total', $draftOrder->shipping_total) }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Shipping" data-shipping-input @readonly(! $isEditable)>
                    <textarea name="notes" rows="4" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Internal order note" @readonly(! $isEditable)>{{ old('notes', $draftOrder->notes) }}</textarea>
                    <div class="border-t border-[#E2E8F0] pt-4 flex justify-between gap-4 text-lg font-bold"><span>Total</span><span class="text-[#0052CC]" data-total-display>{{ $currency }} {{ number_format((float) $draftOrder->total, 2) }}</span></div>
                </div>
            </section>

            @if($isEditable)
                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 space-y-3">
                    <button type="submit" name="_method" value="PATCH" class="w-full h-11 rounded-lg bg-[#0052CC] text-white font-semibold text-sm">Save draft</button>
                    <button type="submit" formaction="{{ route('draft-orders.convert', $draftOrder) }}" formmethod="POST" class="w-full h-11 rounded-lg bg-[#059669] text-white font-semibold text-sm">Save and create order</button>
                    <p class="text-xs text-[#64748B]">Creating the order saves the current draft fields first, checks stock, and deducts inventory. It does not charge a card.</p>
                </section>
            @endif
        </aside>
    </form>

    @if($isEditable || $draftOrder->status === \App\Models\DraftOrder::STATUS_CANCELLED)
        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_180px_180px] md:items-center">
                <p class="text-sm text-[#64748B]">Delete this draft order? This removes it from your active draft list. Converted orders cannot be deleted.</p>
                @if($isEditable)
                    <form action="{{ route('draft-orders.cancel', $draftOrder) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full h-10 rounded-lg border border-[#FECACA] bg-white text-sm font-semibold text-[#991B1B]">Cancel draft</button>
                    </form>
                @else
                    <span></span>
                @endif
                <form action="{{ route('draft-orders.destroy', $draftOrder) }}" method="POST" onsubmit="return confirm('Delete this draft order? This will remove it from your active draft list. Converted orders cannot be deleted.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full h-10 rounded-lg border border-[#FECACA] bg-white text-sm font-semibold text-[#991B1B]">Delete draft</button>
                </form>
            </div>
        </section>
    @endif
</div>

<script>
    (() => {
        const parseMoney = (value) => {
            const parsed = Number.parseFloat(value);
            return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
        };

        document.querySelectorAll('[data-draft-order-form]').forEach((form) => {
            const currency = form.dataset.currency || 'USD';
            const format = (amount) => `${currency} ${amount.toFixed(2)}`;

            const updateTotals = () => {
                let subtotal = 0;

                form.querySelectorAll('[data-draft-line]').forEach((row) => {
                    const select = row.querySelector('[data-variant-select]');
                    const quantityInput = row.querySelector('[data-quantity-input]');
                    const priceInput = row.querySelector('[data-unit-price-input]');
                    const lineTotal = row.querySelector('[data-line-total]');
                    const selected = select?.selectedOptions?.[0];
                    const hasProduct = Boolean(select?.value);
                    const quantity = Math.max(1, Number.parseInt(quantityInput?.value || '1', 10) || 1);
                    const price = hasProduct ? parseMoney(priceInput?.value || selected?.dataset.price || '0') : 0;
                    const total = hasProduct ? quantity * price : 0;

                    if (lineTotal) {
                        lineTotal.textContent = format(total);
                    }

                    subtotal += total;
                });

                const discount = parseMoney(form.querySelector('[data-discount-input]')?.value || '0');
                const tax = parseMoney(form.querySelector('[data-tax-input]')?.value || '0');
                const shipping = parseMoney(form.querySelector('[data-shipping-input]')?.value || '0');
                const total = Math.max(0, subtotal + tax + shipping - discount);

                form.querySelectorAll('[data-subtotal-display]').forEach((node) => node.textContent = format(subtotal));
                form.querySelectorAll('[data-total-display]').forEach((node) => node.textContent = format(total));
            };

            form.querySelectorAll('[data-draft-line]').forEach((row) => {
                const select = row.querySelector('[data-variant-select]');
                const selected = select?.selectedOptions?.[0];
                const quantityInput = row.querySelector('[data-quantity-input]');
                const priceInput = row.querySelector('[data-unit-price-input]');

                if (select?.value && quantityInput && ! quantityInput.value) {
                    quantityInput.value = '1';
                }

                if (select?.value && priceInput && ! priceInput.value && selected?.dataset.price) {
                    priceInput.value = selected.dataset.price;
                }
            });

            form.querySelectorAll('[data-variant-select]').forEach((select) => {
                select.addEventListener('change', () => {
                    const row = select.closest('[data-draft-line]');
                    const selected = select.selectedOptions[0];
                    const quantityInput = row?.querySelector('[data-quantity-input]');
                    const priceInput = row?.querySelector('[data-unit-price-input]');

                    if (select.value && quantityInput && ! quantityInput.value) {
                        quantityInput.value = '1';
                    }

                    if (select.value && priceInput && selected?.dataset.price) {
                        priceInput.value = selected.dataset.price;
                    }

                    updateTotals();
                });
            });

            form.querySelectorAll('[data-quantity-input], [data-unit-price-input], [data-discount-input], [data-tax-input], [data-shipping-input]').forEach((input) => {
                input.addEventListener('input', updateTotals);
            });

            updateTotals();
        });
    })();
</script>
@endsection
