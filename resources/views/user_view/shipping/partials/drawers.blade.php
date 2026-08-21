{{-- Zone drawer — merchant simple form --}}
<div id="shipping-drawer-zone" class="shipping-drawer shipping-drawer-modal hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="zone-drawer-title">
    <div class="shipping-drawer-backdrop absolute inset-0 bg-slate-900/40" data-close-drawer></div>
    <div class="shipping-drawer-panel relative flex flex-col">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-5 py-4">
            <h3 id="zone-drawer-title" class="text-lg font-semibold text-[#0F172A]">Add delivery area</h3>
            <button type="button" class="text-[#64748B]" data-close-drawer aria-label="Close">✕</button>
        </div>
        <form id="zone-drawer-form" method="POST" action="{{ route('settings.shipping.zones.store') }}" class="flex flex-1 flex-col overflow-y-auto p-5 shipping-submit-form">
            @csrf
            <input type="hidden" name="_method" id="zone-form-method" value="POST" disabled>
            <input type="hidden" name="zone_editor_mode" id="zone-editor-mode" value="simple">
            <input type="hidden" name="sort_order" id="zone-field-sort" value="0">
            <input type="hidden" name="legacy_countries" id="zone-field-legacy-countries" value="">
            <input type="hidden" name="legacy_regions" id="zone-field-legacy-regions" value="">
            <input type="hidden" name="legacy_postal_patterns" id="zone-field-legacy-postal" value="">

            <div id="zone-simple-panel" class="space-y-4">
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Area name</span>
                    <input name="name" id="zone-field-name" required placeholder="United States" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>

                <x-geo.country-select id="zone-field-country" :countries="$countries ?? []" required />

                <div id="zone-region-multi-host">
                    <x-geo.region-multi-select id="zone-region-multi" />
                </div>

                <x-geo.postal-rule-builder input-id="zone-postal-rules-json" container-id="zone-postal-builder" />

                <label class="flex items-center gap-2 text-sm text-[#334155]">
                    <input type="hidden" name="is_active" value="0" id="zone-active-hidden">
                    <input type="checkbox" name="is_active" id="zone-field-active" value="1" checked class="rounded border-[#CBD5E1]">
                    Available delivery area
                </label>
            </div>

            <div class="mt-auto border-t border-[#F1F5F9] pt-4">
                <button type="submit" class="h-10 w-full rounded-lg bg-brand text-sm font-bold text-white shipping-submit-btn">Save delivery area</button>
            </div>
        </form>
    </div>
</div>

{{-- Method drawer — merchant simple form --}}
<div id="shipping-drawer-method" class="shipping-drawer shipping-drawer-modal hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="method-drawer-title">
    <div class="shipping-drawer-backdrop absolute inset-0 bg-slate-900/40" data-close-drawer></div>
    <div class="shipping-drawer-panel relative flex flex-col">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-5 py-4">
            <h3 id="method-drawer-title" class="text-lg font-semibold text-[#0F172A]">Add delivery option</h3>
            <button type="button" class="text-[#64748B]" data-close-drawer aria-label="Close">✕</button>
        </div>
        <form id="method-drawer-form" method="POST" action="{{ route('settings.shipping.methods.store') }}" class="flex flex-1 flex-col overflow-y-auto p-5 shipping-submit-form">
            @csrf
            <input type="hidden" name="_method" id="method-form-method" value="POST" disabled>
            <input type="hidden" name="rate_type" id="method-field-rate-type-hidden" value="flat">
            <input type="hidden" name="description" id="method-field-description" value="">
            <input type="hidden" name="min_order_amount" id="method-field-min-order" value="">
            <input type="hidden" name="max_order_amount" id="method-field-max-order" value="">
            <input type="hidden" name="sort_order" id="method-field-sort" value="0">
            <input type="hidden" name="carrier_account_id" id="method-field-carrier" value="">
            <input type="hidden" id="method-field-rate-type-advanced" value="flat">

            <div class="space-y-5">
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Delivery area</span>
                    <select name="shipping_zone_id" id="method-field-zone" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        @foreach ($shippingZones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach
                    </select>
                </label>

                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Option name</span>
                    <input name="name" id="method-field-name" required placeholder="Standard delivery" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>

                <div>
                    <p class="text-xs font-semibold text-[#64748B]">Pricing</p>
                    <div class="mt-2 grid gap-2">
                        <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2.5 text-sm"><input type="radio" name="delivery_price_mode" value="fixed" checked class="border-[#CBD5E1]" data-method-price-mode> Fixed price</label>
                        <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2.5 text-sm"><input type="radio" name="delivery_price_mode" value="free" class="border-[#CBD5E1]" data-method-price-mode> Free</label>
                        <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2.5 text-sm"><input type="radio" name="delivery_price_mode" value="free_over" class="border-[#CBD5E1]" data-method-price-mode> Free over an amount</label>
                    </div>
                    <div id="method-price-fixed" class="mt-3">
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Price</span><input name="flat_rate" id="method-field-flat" type="number" min="0" step="0.01" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    </div>
                    <div id="method-price-free-over" class="mt-3 hidden space-y-3">
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Price before free shipping</span><input id="method-field-flat-mirror" type="number" min="0" step="0.01" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Free when order is at least</span><input name="free_over_amount" id="method-field-free-over" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold text-[#64748B]">Estimated delivery</p>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Min days</span><input name="estimated_min_days" id="method-field-min-days" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Max days</span><input name="estimated_max_days" id="method-field-max-days" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    </div>
                    <label class="mt-3 block space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Customer label <span class="font-normal text-[#94A3B8]">(optional)</span></span>
                        <input name="delivery_speed_label" id="method-field-label" placeholder="3–5 business days" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                    </label>
                </div>

                <div id="method-simple-availability" class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3">
                    <label class="inline-flex items-center gap-2 text-sm text-[#334155]">
                        <input type="hidden" name="available_to_customers" value="0" id="method-available-hidden">
                        <input type="checkbox" name="available_to_customers" id="method-field-available" value="1" checked class="rounded border-[#CBD5E1]">
                        Available at checkout
                    </label>
                </div>
                <div id="method-flag-warning" class="hidden rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-3 text-xs text-[#92400E]"></div>
            </div>

            <div class="mt-6 border-t border-[#F1F5F9] pt-4">
                <button type="submit" class="h-10 w-full rounded-lg bg-brand text-sm font-bold text-white shipping-submit-btn">Save delivery option</button>
            </div>
        </form>
    </div>
</div>

@php
    $deliveryRegionCatalog = [];
    foreach (array_keys($countries ?? \App\Support\Tax\TaxCountryCatalog::all()) as $catalogCountryCode) {
        $deliveryRegionCatalog[$catalogCountryCode] = \App\Support\Tax\TaxCountryCatalog::regionsFor($catalogCountryCode);
    }
@endphp
<script type="application/json" id="delivery-region-catalog">@json($deliveryRegionCatalog)</script>
