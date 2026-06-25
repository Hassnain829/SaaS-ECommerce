@extends('layouts.user.user-sidebar')

@section('title', 'Create manual order | BaaS Core')

@section('topbar')
<header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-4 shrink-0">
    <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
        <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
    </button>
    <div class="min-w-0">
        <h1 class="truncate text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Create order manually</h1>
        <p class="hidden md:block text-xs text-[#64748B]">Save a draft first, then create the confirmed order when it is ready.</p>
    </div>
    <a href="{{ route('orders') }}" class="h-10 px-4 rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A] inline-flex items-center justify-center hover:bg-[#F8FAFC]">Back to orders</a>
</header>
@endsection

@section('content')
@php
    $currency = $selectedStore->currency ?? 'USD';
    $billingSameAsShipping = old('billing_same_as_shipping') !== null
        ? filter_var(old('billing_same_as_shipping'), FILTER_VALIDATE_BOOLEAN)
        : true;
@endphp
<div class="w-full py-2 md:py-4 space-y-4">
    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
        <div class="flex flex-col gap-1">
            <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Manual order</p>
            <h2 class="text-2xl font-poppins font-semibold text-[#0F172A]">Draft order workspace</h2>
            <p class="text-sm text-[#64748B]">Drafts do not deduct inventory. Stock is checked and deducted only when you create the order.</p>
        </div>
    </section>

    <form action="{{ route('draft-orders.store') }}" method="POST" class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start" data-draft-order-form data-currency="{{ $currency }}">
        @csrf

        <div class="space-y-4">
            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Customer</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Existing customer</span>
                        <select name="customer_id" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                            <option value="">Create from details below</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((int) old('customer_id') === $customer->id)>{{ $customer->full_name ?: $customer->email }} - {{ $customer->email }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Customer name</span>
                        <input name="customer_name" value="{{ old('customer_name') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Email</span>
                        <input name="customer_email" value="{{ old('customer_email') }}" type="email" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Phone</span>
                        <input name="customer_phone" value="{{ old('customer_phone') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Add products</h3>
                <p class="mt-1 text-sm text-[#64748B]">Choose catalog variants for this manual order.</p>

                @if($variants->isEmpty())
                    <div class="mt-4 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">
                        No sellable products are available yet. Add products before creating manual orders.
                    </div>
                @else
                    <div class="mt-4 space-y-3">
                        @for($i = 0; $i < 3; $i++)
                            <div class="grid grid-cols-1 gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 md:grid-cols-[minmax(0,1fr)_90px_120px_120px]" data-draft-line>
                                <select name="items[{{ $i }}][product_variant_id]" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" data-variant-select>
                                    <option value="">Select product</option>
                                    @foreach($variants as $variant)
                                        @php($label = \App\Support\ProductVariantLabel::forVariant($variant, 0, 1))
                                        <option value="{{ $variant->id }}"
                                            data-price="{{ number_format((float) $variant->price, 2, '.', '') }}"
                                            data-stock="{{ (int) $variant->stock }}"
                                            data-sku="{{ $variant->sku }}"
                                            data-label="{{ $variant->product?->name }} - {{ $label }}"
                                            @selected((int) old("items.$i.product_variant_id") === $variant->id)>
                                            {{ $variant->product?->name }} - {{ $label }} - {{ $currency }} {{ number_format((float) $variant->price, 2) }} - {{ (int) $variant->stock }} available
                                        </option>
                                    @endforeach
                                </select>
                                <input name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", $i === 0 ? 1 : '') }}" type="number" min="1" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" placeholder="Qty" data-quantity-input>
                                <input name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price") }}" type="number" min="0" step="0.01" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-2.5 text-sm" placeholder="Price" data-unit-price-input>
                                <div class="flex min-h-10 items-center justify-between rounded-lg bg-white px-3 py-2 text-sm text-[#64748B]">
                                    <span>Line</span>
                                    <span class="font-semibold text-[#0F172A]" data-line-total>{{ $currency }} 0.00</span>
                                </div>
                            </div>
                        @endfor
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Shipping address</h3>
                <p class="mt-1 text-sm text-[#64748B]">Address, city, and country are required before creating the order.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <input name="shipping_name" value="{{ old('shipping_name') }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Recipient name">
                    <input name="shipping_phone" value="{{ old('shipping_phone') }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Phone">
                    <input name="shipping_address_line1" value="{{ old('shipping_address_line1') }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm md:col-span-2" placeholder="Address line 1">
                    @error('shipping_address_line1')
                        <p class="-mt-3 text-xs text-[#B91C1C] md:col-span-2">{{ $message }}</p>
                    @enderror
                    <input name="shipping_address_line2" value="{{ old('shipping_address_line2') }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm md:col-span-2" placeholder="Address line 2">
                    <input name="shipping_city" value="{{ old('shipping_city') }}" class="rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="City">
                    @error('shipping_city')
                        <p class="-mt-3 text-xs text-[#B91C1C]">{{ $message }}</p>
                    @enderror
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">State / region code</span>
                        <input name="shipping_state" value="{{ old('shipping_state') }}" maxlength="32" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase" placeholder="CA, NY, ON">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Postal code</span>
                        <input name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Postal code">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Country code</span>
                        <input name="shipping_country" value="{{ old('shipping_country') }}" list="draft-create-country-codes" maxlength="2" pattern="[A-Za-z]{2}" autocomplete="off" title="Enter a two-letter country code such as US, CA, GB, or AU." class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase @error('shipping_country') border-[#F87171] @enderror" placeholder="US, CA, GB, AU" @error('shipping_country') aria-invalid="true" @enderror>
                        <datalist id="draft-create-country-codes">
                            @include('user_view.partials.country_code_options')
                        </datalist>
                        <p class="mt-1 text-xs text-[#64748B]">Use a two-letter code such as US, CA, GB, or AU.</p>
                        @error('shipping_country')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm text-[#475569]">
                    <input type="checkbox" name="billing_same_as_shipping" value="1" @checked($billingSameAsShipping) class="rounded border-[#CBD5E1]" data-billing-same-checkbox>
                    Billing address is the same as shipping
                </label>
                @include('user_view.partials.draft_billing_address_fields', [
                    'billing' => [],
                    'billingSameAsShipping' => $billingSameAsShipping,
                    'isEditable' => true,
                    'countryDatalistId' => 'draft-create-billing-country-codes',
                ])
            </section>
        </div>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <h3 class="text-lg font-poppins font-semibold text-[#0F172A]">Totals</h3>
                <div class="mt-4 space-y-3">
                    <div class="flex justify-between gap-4 text-sm"><span class="text-[#64748B]">Subtotal</span><span class="font-semibold text-[#0F172A]" data-subtotal-display>{{ $currency }} 0.00</span></div>
                    <input name="discount_total" value="{{ old('discount_total', '0.00') }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Discount" data-discount-input>
                    <input name="tax_total" value="{{ old('tax_total', '0.00') }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Tax" data-tax-input>
                    <p class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs leading-relaxed text-[#64748B]">New drafts start in manual tax mode. Save the draft first, then open it to calculate tax from store settings.</p>
                    <input name="shipping_total" value="{{ old('shipping_total', '0.00') }}" type="number" min="0" step="0.01" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Shipping" data-shipping-input>
                    <textarea name="notes" rows="4" class="w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Internal order note">{{ old('notes') }}</textarea>
                    <div class="border-t border-[#E2E8F0] pt-4 flex justify-between gap-4 text-lg font-bold"><span>Total</span><span class="text-[#0052CC]" data-total-display>{{ $currency }} 0.00</span></div>
                </div>
            </section>

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5">
                <button type="submit" @disabled($variants->isEmpty()) class="w-full h-11 rounded-lg bg-[#0052CC] text-white font-semibold text-sm disabled:cursor-not-allowed disabled:bg-[#94A3B8]">
                    Save draft
                </button>
                <p class="mt-3 text-xs text-[#64748B]">Payment collection is not available here yet. Payment gateway work belongs to the next checkout/payment phase.</p>
            </section>
        </aside>
    </form>
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

            form.querySelector('[data-billing-same-checkbox]')?.addEventListener('change', (event) => {
                const billingFields = form.querySelector('[data-draft-billing-fields]');
                if (billingFields) {
                    billingFields.classList.toggle('hidden', event.target.checked);
                }
            });

            updateTotals();
        });
    })();
</script>
@endsection
