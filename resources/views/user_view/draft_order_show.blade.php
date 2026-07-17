@extends('layouts.user.user-sidebar')

@section('title', $draftOrder->draft_number . ' | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar :title="'Draft '.$draftOrder->draft_number" :lead="$draftOrder->customer?->full_name ?? $draftOrder->customer?->email ?? 'No customer'">
        <x-slot:actions>
            <span class="hidden rounded-full bg-[#E0E7FF] px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-[#3730A3] md:inline-flex">{{ ucfirst($draftOrder->status) }}</span>
            @if($draftOrder->convertedOrder)
                <a href="{{ route('orderViewDetails', $draftOrder->convertedOrder) }}" class="inline-flex h-10 items-center rounded-xl bg-[#0052CC] px-4 text-sm font-semibold text-white">View order</a>
            @endif
            <a href="{{ route('orders') }}" class="inline-flex h-10 items-center rounded-xl border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-800">Back</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
@php
    use App\Support\Draft\DraftOrderFormState;
    use App\Support\MoneyDisplay;

    $customerLabel = $draftOrder->customer?->full_name ?? $draftOrder->customer?->email ?? 'No customer';
    $statusLabel = ucfirst($draftOrder->status);
    $currency = $draftOrder->currency ?: ($selectedStore->currency ?? 'USD');
    $shipping = $draftOrder->shippingAddress();
    $isEditable = $draftOrder->status === \App\Models\DraftOrder::STATUS_DRAFT;
    $taxSource = $draftOrder->taxSource();
    $isCalculatedTax = $taxSource === \App\Models\DraftOrder::TAX_SOURCE_CALCULATED;
    $selectedTaxMode = old('tax_mode', $isCalculatedTax ? \App\Models\DraftOrder::TAX_SOURCE_CALCULATED : \App\Models\DraftOrder::TAX_SOURCE_MANUAL);
    $storedShippingCountry = old('shipping_country', $shipping['country'] ?? '');
    $legacyShippingCountry = $storedShippingCountry !== '' && ! preg_match('/^[A-Za-z]{2}$/', $storedShippingCountry);
    $billing = $draftOrder->billingAddress();
    $billingSameAsShipping = filter_var(
        is_array(old('billing_same_as_shipping'))
            ? end(old('billing_same_as_shipping'))
            : old('billing_same_as_shipping', $draftOrder->billingSameAsShipping()),
        FILTER_VALIDATE_BOOLEAN
    );
    $taxDisplay = $taxDisplay ?? \App\Support\Tax\TaxDisplayPresenter::forDraft($draftOrder);

    $persistedTaxByVariant = [];
    foreach ($draftOrder->items as $item) {
        $persistedTaxByVariant[(int) $item->product_variant_id] = $item->tax_amount;
    }

    if (old('items') !== null && is_array(old('items'))) {
        $lineRows = array_values(old('items'));
    } else {
        $lineRows = $draftOrder->items->map(fn ($item) => [
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'line_total' => $item->line_total,
            'tax_amount' => $item->tax_amount,
        ])->all();
    }

    $hasTaxDrivingChanges = DraftOrderFormState::taxDrivingInputDirty($draftOrder, $shipping);
    $switchedToAutomatic = $selectedTaxMode === \App\Models\DraftOrder::TAX_SOURCE_CALCULATED && ! $isCalculatedTax;
    $showAutomaticPending = $selectedTaxMode === \App\Models\DraftOrder::TAX_SOURCE_CALCULATED
        && ($hasTaxDrivingChanges || $switchedToAutomatic);

    $summaryTaxLabel = null;
    $summaryTotalLabel = null;
    $displaySubtotal = $draftOrder->subtotal;

    if ($showAutomaticPending) {
        $summaryTaxLabel = 'Recalculates on save';
        $summaryTotalLabel = 'Pending tax recalculation';
        $displaySubtotal = '0.00';
        foreach ($lineRows as $row) {
            $displaySubtotal = bcadd($displaySubtotal, DraftOrderFormState::lineTotalAmount($row), 2);
        }
    }
@endphp

<div class="w-full py-2 md:py-4 pb-24 xl:pb-4 space-y-4">
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B]">{{ $errors->first() }}</div>
    @endif

    <form id="draftOrderForm" action="{{ route('draft-orders.update', $draftOrder) }}" method="POST" class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start" data-draft-order-form data-currency="{{ $currency }}" data-calculated-tax="{{ $isCalculatedTax ? '1' : '0' }}" data-prices-include-tax="{{ ($taxDisplay['prices_include_tax'] ?? false) ? '1' : '0' }}" data-is-saved-draft="1" data-is-estimate="0" data-tax-driving-dirty="{{ $hasTaxDrivingChanges ? '1' : '0' }}" data-tax-preview-url="{{ route('draft-orders.preview-tax') }}" data-persisted-subtotal="{{ $draftOrder->subtotal }}" data-persisted-discount="{{ $draftOrder->discount_total }}" data-persisted-shipping="{{ $draftOrder->shipping_total }}" data-persisted-tax="{{ $draftOrder->tax_total }}" data-persisted-total="{{ $draftOrder->total }}">
        @csrf

        <div class="space-y-4">
            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h2 class="text-lg font-poppins font-semibold text-[#0F172A]">Customer</h2>
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
                <h2 class="text-lg font-poppins font-semibold text-[#0F172A]">Products</h2>
                <p class="mt-1 text-sm text-[#64748B]">Stock shown is guidance — inventory is checked when you create the order.</p>

                @include('user_view.partials.draft_order_line_items', [
                    'variants' => $variants,
                    'currency' => $currency,
                    'isEditable' => $isEditable,
                    'lineRows' => $lineRows,
                    'persistedTaxByVariant' => $persistedTaxByVariant,
                    'showPersistedLineTax' => $isCalculatedTax,
                ])
            </section>

            @if($taxDisplay['has_breakdown'] || in_array($taxDisplay['source'] ?? '', [
                \App\Support\Tax\TaxDisplayPresenter::SOURCE_PLATFORM_CALCULATED,
                \App\Support\Tax\TaxDisplayPresenter::SOURCE_MANUAL,
            ], true))
                <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                    @include('user_view.partials.tax_detail_disclosure', [
                        'taxDisplay' => $taxDisplay,
                        'currency' => $currency,
                        'disclosureId' => 'draft-tax-breakdown',
                        'title' => 'Tax details',
                    ])
                </section>
            @endif

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <h2 class="text-lg font-poppins font-semibold text-[#0F172A]">Shipping address</h2>
                <p class="mt-1 text-sm text-[#64748B]">Address, city, and country are required before creating the order.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach([
                        'shipping_name' => 'Recipient name',
                        'shipping_phone' => 'Phone',
                        'shipping_address_line1' => 'Address line 1',
                        'shipping_address_line2' => 'Address line 2',
                        'shipping_city' => 'City',
                    ] as $name => $label)
                        @php($key = str_replace('shipping_', '', $name))
                        <label class="block {{ str_contains($name, 'address_line') ? 'md:col-span-2' : '' }}">
                            <span class="text-xs font-semibold text-[#64748B]">{{ $label }}</span>
                            <input name="{{ $name }}" value="{{ old($name, $shipping[$key] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm @error($name) border-[#F87171] @enderror" @readonly(! $isEditable) @error($name) aria-invalid="true" @enderror>
                            @error($name)
                                <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                            @enderror
                        </label>
                    @endforeach
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">State / region code</span>
                        <input name="shipping_state" value="{{ old('shipping_state', $shipping['state'] ?? '') }}" maxlength="32" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase @error('shipping_state') border-[#F87171] @enderror" placeholder="NY" data-tax-driving-input @readonly(! $isEditable) @error('shipping_state') aria-invalid="true" @enderror>
                        <p class="mt-1 text-xs text-[#64748B]">Enter one state or region code that matches your tax rate, such as NY for New York.</p>
                        @error('shipping_state')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-[#64748B]">Postal code</span>
                        <input name="shipping_postal_code" value="{{ old('shipping_postal_code', $shipping['postal_code'] ?? '') }}" class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" @readonly(! $isEditable)>
                        @error('shipping_postal_code')
                            <p class="mt-1 text-xs text-[#B91C1C]">{{ $message }}</p>
                        @enderror
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs font-semibold text-[#64748B]">Country code</span>
                        @if($legacyShippingCountry)
                            <p class="mt-1 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs leading-relaxed text-[#92400E]">
                                This draft contains a legacy country value. Replace it with a two-letter code such as US before saving or creating the order.
                            </p>
                        @endif
                        <input
                            name="shipping_country"
                            value="{{ $storedShippingCountry }}"
                            list="draft-shipping-country-codes"
                            @unless($legacyShippingCountry) maxlength="2" pattern="[A-Za-z]{2}" @endunless
                            autocomplete="off"
                            title="Enter a two-letter country code such as US, CA, GB, or AU."
                            class="mt-1 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm uppercase {{ $legacyShippingCountry ? 'border-[#F59E0B]' : '' }} {{ $errors->has('shipping_country') ? 'border-[#F87171]' : '' }}"
                            placeholder="US, CA, GB, AU"
                            data-tax-driving-input
                            @readonly(! $isEditable)
                            @error('shipping_country') aria-invalid="true" @enderror
                        >
                        <datalist id="draft-shipping-country-codes">
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
                    <input type="checkbox" name="billing_same_as_shipping" value="1" @checked($billingSameAsShipping) @disabled(! $isEditable) class="rounded border-[#CBD5E1]" data-billing-same-checkbox>
                    Billing address is the same as shipping
                </label>
                @include('user_view.partials.draft_billing_address_fields', [
                    'billing' => $billing,
                    'billingSameAsShipping' => $billingSameAsShipping,
                    'isEditable' => $isEditable,
                    'countryDatalistId' => 'draft-billing-country-codes',
                ])
            </section>

            <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 md:p-6">
                <label for="draft-notes-edit" class="text-lg font-poppins font-semibold text-[#0F172A]">Notes</label>
                <textarea id="draft-notes-edit" name="notes" rows="4" class="mt-3 w-full rounded-lg border border-[#CBD5E1] px-3 py-2.5 text-sm" @readonly(! $isEditable)>{{ old('notes', $draftOrder->notes) }}</textarea>
            </section>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-20 xl:self-start">
            @include('user_view.partials.draft_order_summary', [
                'currency' => $currency,
                'subtotal' => $displaySubtotal,
                'discount' => old('discount_total', $draftOrder->discount_total),
                'shipping' => old('shipping_total', $draftOrder->shipping_total),
                'tax' => $draftOrder->tax_total,
                'total' => $draftOrder->total,
                'isEstimate' => false,
                'isEditable' => $isEditable,
                'taxDisplay' => $taxDisplay,
                'summaryTaxLabel' => $summaryTaxLabel,
                'summaryTotalLabel' => $summaryTotalLabel,
            ])

            @include('user_view.partials.draft_tax_mode_fields', [
                'taxSetting' => $taxSetting,
                'isEditable' => $isEditable,
                'selectedTaxMode' => $selectedTaxMode,
                'calculatedTaxTotal' => $isCalculatedTax ? $draftOrder->tax_total : '0.00',
                'manualTaxTotal' => $isCalculatedTax ? '0.00' : $draftOrder->tax_total,
                'wasCalculated' => $isCalculatedTax,
                'taxGuidance' => $taxDisplay['calculation_guidance'] ?? null,
            ])

            @if($isEditable && ($taxSetting?->enabled ?? false))
                <section class="hidden xl:block rounded-2xl border border-[#CBD5E1] bg-white p-5">
                    <button type="submit" formaction="{{ route('draft-orders.calculate-tax', $draftOrder) }}" formmethod="POST" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]" data-recalculate-tax-button>
                        Recalculate tax
                    </button>
                    <p class="mt-2 text-xs text-[#64748B]">Saves current form values and recalculates automatic tax from store settings.</p>
                </section>
            @endif

            @if($isEditable)
                <section class="hidden xl:flex xl:flex-col-reverse xl:gap-3 rounded-2xl border border-[#CBD5E1] bg-white p-5">
                    <button type="submit" name="_method" value="PATCH" class="w-full h-11 rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]" data-primary-save-button>
                        Save draft
                    </button>
                    <button type="submit" formaction="{{ route('draft-orders.convert', $draftOrder) }}" formmethod="POST" class="w-full h-11 rounded-lg bg-[#059669] text-white font-semibold text-sm" data-convert-draft-button>
                        Create order
                    </button>
                    <p class="text-xs text-[#64748B] order-first">Creating the order saves current fields, checks stock, and deducts inventory. It does not charge a card.</p>
                </section>
            @endif
        </aside>

        @if($isEditable)
            <div class="fixed inset-x-0 bottom-0 z-20 border-t border-[#E2E8F0] bg-white/95 p-4 backdrop-blur xl:hidden">
                <div class="grid grid-cols-2 gap-3">
                    <button type="submit" name="_method" value="PATCH" class="h-11 rounded-lg border border-[#CBD5E1] bg-white text-sm font-semibold text-[#0F172A]" data-primary-save-button>
                        Save draft
                    </button>
                    <button type="submit" formaction="{{ route('draft-orders.convert', $draftOrder) }}" formmethod="POST" class="h-11 rounded-lg bg-[#059669] text-white text-sm font-semibold" data-convert-draft-button>
                        Create order
                    </button>
                </div>
            </div>
        @endif
    </form>

    @if($isEditable || $draftOrder->status === \App\Models\DraftOrder::STATUS_CANCELLED)
        <section class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5">
            <h3 class="text-sm font-semibold text-[#64748B]">Draft maintenance</h3>
            <p class="mt-2 text-sm text-[#64748B]">Cancel or delete removes this draft from your active list. Converted drafts cannot be deleted because they are linked to an order.</p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                @if($isEditable)
                    <form action="{{ route('draft-orders.cancel', $draftOrder) }}" method="POST" onsubmit="return confirm('Cancel this draft? It will remain in history but cannot be converted.');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="h-10 rounded-lg border border-[#FECACA] bg-white px-4 text-sm text-[#991B1B]">Cancel draft</button>
                    </form>
                @endif
                <form action="{{ route('draft-orders.destroy', $draftOrder) }}" method="POST" onsubmit="return confirm('Delete this draft permanently? Converted drafts cannot be deleted.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="h-10 rounded-lg border border-[#FECACA] bg-white px-4 text-sm text-[#991B1B]">Delete draft</button>
                </form>
            </div>
        </section>
    @endif
</div>

@include('user_view.partials.draft_order_form_scripts')
@endsection
