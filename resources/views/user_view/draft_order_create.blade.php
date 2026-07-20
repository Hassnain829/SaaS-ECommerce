@extends('layouts.user.user-sidebar')

@section('title', 'Create manual order | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="New draft" lead="Add customer, products, and addresses before creating the order.">
        <x-slot:actions>
            <a href="{{ route('orders') }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back to orders</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    use App\Support\MoneyDisplay;

    $currency = $selectedStore->currency ?? 'USD';
    $billingSameAsShipping = filter_var(
        is_array(old('billing_same_as_shipping'))
            ? end(old('billing_same_as_shipping'))
            : old('billing_same_as_shipping', true),
        FILTER_VALIDATE_BOOLEAN
    );
    $oldItems = old('items');
    if (! is_array($oldItems) || $oldItems === []) {
        $lineRows = [['product_variant_id' => '', 'quantity' => 1, 'unit_price' => '']];
    } else {
        $lineRows = array_values($oldItems);
    }
    $selectedTaxMode = $defaultTaxMode;
    $isEstimate = true;
    $automaticCreatePending = $selectedTaxMode === \App\Models\DraftOrder::TAX_SOURCE_CALCULATED;
@endphp
<div class="w-full py-2 md:py-4 pb-24 xl:pb-4 space-y-4">
    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('draft-orders.store') }}" method="POST" class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start" data-draft-order-form data-currency="{{ $currency }}" data-calculated-tax="{{ $defaultTaxMode === \App\Models\DraftOrder::TAX_SOURCE_CALCULATED ? '1' : '0' }}" data-prices-include-tax="{{ ($taxSetting?->prices_include_tax ?? false) ? '1' : '0' }}" data-is-saved-draft="0" data-is-estimate="1" data-tax-preview-url="{{ route('draft-orders.preview-tax') }}">
        @csrf

        <div class="space-y-4">
            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h2 class="text-lg font-semibold text-[#0F172A]">Customer</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-[#64748B]">Existing customer</span>
                        <select name="customer_id" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                            <option value="">Create from details below</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((int) old('customer_id') === $customer->id)>{{ $customer->full_name ?: $customer->email }} — {{ $customer->email }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">Customer name</span>
                        <input name="customer_name" value="{{ old('customer_name') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">Email</span>
                        <input name="customer_email" value="{{ old('customer_email') }}" type="email" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">Phone</span>
                        <input name="customer_phone" value="{{ old('customer_phone') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h2 class="text-lg font-semibold text-[#0F172A]">Products</h2>
                <p class="mt-1 text-sm text-[#64748B]">Add one or more variants. Stock shown is guidance — inventory is checked when you create the order.</p>

                @if($variants->isEmpty())
                    <div class="mt-4 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-6 text-sm text-[#64748B]">
                        No sellable products are available yet. Add products before creating manual orders.
                    </div>
                @else
                    @include('user_view.partials.draft_order_line_items', [
                        'variants' => $variants,
                        'currency' => $currency,
                        'isEditable' => true,
                        'lineRows' => $lineRows,
                    ])
                @endif
            </section>

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h2 class="text-lg font-semibold text-[#0F172A]">Shipping address</h2>
                <p class="mt-1 text-sm text-[#64748B]">Address, city, and country are required before creating the order.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">Recipient name</span>
                        <input name="shipping_name" value="{{ old('shipping_name') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">Phone</span>
                        <input name="shipping_phone" value="{{ old('shipping_phone') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-[#64748B]">Address line 1</span>
                        <input name="shipping_address_line1" value="{{ old('shipping_address_line1') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm @error('shipping_address_line1') border-[#F87171] @enderror" @error('shipping_address_line1') aria-invalid="true" @enderror>
                        @error('shipping_address_line1')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-[#64748B]">Address line 2</span>
                        <input name="shipping_address_line2" value="{{ old('shipping_address_line2') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">City</span>
                        <input name="shipping_city" value="{{ old('shipping_city') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm @error('shipping_city') border-[#F87171] @enderror" @error('shipping_city') aria-invalid="true" @enderror>
                        @error('shipping_city')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">State / region code</span>
                        <input name="shipping_state" value="{{ old('shipping_state') }}" maxlength="32" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase @error('shipping_state') border-[#F87171] @enderror" placeholder="NY" data-tax-driving-input @error('shipping_state') aria-invalid="true" @enderror>
                        <p class="mt-1 text-xs text-[#64748B]">Enter one state or region code that matches your tax rate, such as NY for New York.</p>
                        @error('shipping_state')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">Postal code</span>
                        <input name="shipping_postal_code" value="{{ old('shipping_postal_code') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-[#64748B]">Country code</span>
                        <input name="shipping_country" value="{{ old('shipping_country') }}" list="draft-create-country-codes" maxlength="2" pattern="[A-Za-z]{2}" autocomplete="off" title="Enter a two-letter country code such as US, CA, GB, or AU." class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase @error('shipping_country') border-[#F87171] @enderror" placeholder="US, CA, GB, AU" data-tax-driving-input @error('shipping_country') aria-invalid="true" @enderror>
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
                    <input type="hidden" name="billing_same_as_shipping" value="0">
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

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <label for="draft-notes" class="text-lg font-semibold text-[#0F172A]">Notes</label>
                <textarea id="draft-notes" name="notes" rows="4" class="mt-3 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" placeholder="Internal order note">{{ old('notes') }}</textarea>
            </section>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
            @include('user_view.partials.draft_order_summary', [
                'currency' => $currency,
                'subtotal' => '0.00',
                'discount' => old('discount_total', '0.00'),
                'shipping' => old('shipping_total', '0.00'),
                'tax' => '0.00',
                'total' => '0.00',
                'isEstimate' => $isEstimate,
                'isEditable' => true,
                'summaryTaxLabel' => $automaticCreatePending ? 'Calculated when saved' : null,
                'summaryTotalLabel' => $automaticCreatePending ? 'Confirmed after tax calculation' : null,
            ])

            @include('user_view.partials.draft_tax_mode_fields', [
                'taxSetting' => $taxSetting,
                'isEditable' => true,
                'selectedTaxMode' => $selectedTaxMode,
                'calculatedTaxTotal' => '0.00',
                'manualTaxTotal' => old('manual_tax_total', old('tax_total', '0.00')),
                'wasCalculated' => false,
            ])

            <section class="hidden xl:block rounded-2xl border border-[#CBD5E1] bg-white p-5 space-y-3">
                <button type="submit" @disabled($variants->isEmpty()) class="w-full h-11 rounded-lg bg-brand text-white font-semibold text-sm disabled:cursor-not-allowed disabled:bg-[#94A3B8]" data-primary-save-button>
                    Save draft
                </button>
                <p class="text-xs text-[#64748B]">Payment collection is not available here yet. Creating the order does not charge a card.</p>
            </section>
        </aside>

        <div class="fixed inset-x-0 bottom-0 z-20 border-t border-[#E2E8F0] bg-white/95 p-4 backdrop-blur xl:hidden">
            <button type="submit" @disabled($variants->isEmpty()) class="w-full h-11 rounded-lg bg-brand text-white font-semibold text-sm disabled:cursor-not-allowed disabled:bg-[#94A3B8]" data-primary-save-button>
                Save draft
            </button>
        </div>
    </form>
</div>

@include('user_view.partials.draft_order_form_scripts')
@endsection
