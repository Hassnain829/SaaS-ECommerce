{{-- Zone drawer --}}
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

            <div id="zone-simple-panel" class="space-y-4">
                <p class="text-sm text-[#64748B]">Choose one country, optional states or provinces, and optional ZIP/postal rules.</p>

                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Delivery area name</span>
                    <input name="name" id="zone-field-name" required placeholder="United States" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>

                <x-geo.country-select id="zone-field-country" :countries="$countries ?? []" required />

                <div id="zone-region-multi-host">
                    <x-geo.region-multi-select id="zone-region-multi" />
                </div>

                <x-geo.postal-rule-builder input-id="zone-postal-rules-json" container-id="zone-postal-builder" />

                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Display order</span>
                    <input name="sort_order" id="zone-field-sort" type="number" min="0" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
                <label class="flex items-center gap-2 text-sm text-[#475569]">
                    <input type="hidden" name="is_active" value="0" id="zone-active-hidden">
                    <input type="checkbox" name="is_active" id="zone-field-active" value="1" checked class="rounded border-[#CBD5E1]"> Active
                </label>
            </div>

            <details id="zone-legacy-panel" class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <summary class="cursor-pointer text-sm font-semibold text-[#475569]">Advanced delivery area (multi-country / legacy)</summary>
                <div class="mt-4 space-y-3">
                    <p class="text-xs text-[#64748B]">Use this only for existing multi-country areas or overlapping legacy rules. Simple areas should use one country above.</p>
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Countries</span><input name="legacy_countries" id="zone-field-legacy-countries" placeholder="US, CA" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">States / provinces</span><input name="legacy_regions" id="zone-field-legacy-regions" placeholder="TX, CA" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">ZIP / postal patterns</span><input name="legacy_postal_patterns" id="zone-field-legacy-postal" placeholder="75002, 606*" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>
                </div>
            </details>

            <div class="mt-auto border-t border-[#F1F5F9] pt-4">
                <button type="submit" class="h-10 w-full rounded-lg bg-brand text-sm font-bold text-white shipping-submit-btn">Save delivery area</button>
            </div>
        </form>
    </div>
</div>

{{-- Method drawer --}}
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
            <div class="space-y-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Basic info</p>
                    <div class="mt-3 space-y-3">
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Option name</span><input name="name" id="method-field-name" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Customer label</span><input name="delivery_speed_label" id="method-field-label" placeholder="2-4 business days" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Delivery area</span>
                            <select name="shipping_zone_id" id="method-field-zone" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                @foreach ($shippingZones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach
                            </select>
                        </label>
                        <div id="method-simple-availability" class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-3">
                            <label class="inline-flex items-center gap-2 text-sm text-[#334155]">
                                <input type="hidden" name="available_to_customers" value="0" id="method-available-hidden">
                                <input type="checkbox" name="available_to_customers" id="method-field-available" value="1" checked class="rounded border-[#CBD5E1]">
                                Available to customers at checkout
                            </label>
                        </div>
                        <div id="method-flag-warning" class="hidden rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-3 text-xs text-[#92400E]"></div>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Delivery price</p>
                    <div class="mt-3 space-y-3">
                        <div class="grid gap-2 sm:grid-cols-3">
                            <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm"><input type="radio" name="delivery_price_mode" value="fixed" checked class="border-[#CBD5E1]" data-method-price-mode> Fixed price</label>
                            <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm"><input type="radio" name="delivery_price_mode" value="free" class="border-[#CBD5E1]" data-method-price-mode> Free</label>
                            <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm"><input type="radio" name="delivery_price_mode" value="free_over" class="border-[#CBD5E1]" data-method-price-mode> Free over amount</label>
                        </div>
                        <input type="hidden" name="rate_type" id="method-field-rate-type-hidden" value="flat">
                        <div id="method-price-fixed">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Price</span><input name="flat_rate" id="method-field-flat" type="number" min="0" step="0.01" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                        <div id="method-price-free-over" class="hidden space-y-3">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Delivery price before free</span><input id="method-field-flat-mirror" type="number" min="0" step="0.01" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Free when order is at least</span><input name="free_over_amount" id="method-field-free-over" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Delivery timing</p>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Min days</span><input name="estimated_min_days" id="method-field-min-days" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Max days</span><input name="estimated_max_days" id="method-field-max-days" type="number" min="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    </div>
                    <label class="mt-3 block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Description</span><input name="description" id="method-field-description" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                </div>

                <details id="method-advanced-panel" class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-[#475569]">Advanced delivery option settings</summary>
                    <div class="mt-4 space-y-4">
                        <div class="flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm"><input type="hidden" name="enabled_for_checkout" value="0" id="method-checkout-hidden"><input type="checkbox" name="enabled_for_checkout" id="method-field-checkout" value="1" checked class="rounded border-[#CBD5E1]"> Show at checkout</label>
                            <label class="inline-flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0" id="method-active-hidden"><input type="checkbox" name="is_active" id="method-field-active" value="1" checked class="rounded border-[#CBD5E1]"> Active</label>
                        </div>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Delivery provider account</span>
                            <select name="carrier_account_id" id="method-field-carrier" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                <option value="">Use manual delivery (recommended for flat rate)</option>
                                @foreach ($carrierAccounts as $account)<option value="{{ $account->id }}">{{ $account->display_name }} ({{ $account->carrier?->name ?? $account->provider }})</option>@endforeach
                            </select>
                        </label>
                        <p id="method-carrier-note" class="text-xs text-[#64748B]">Flat-rate options without a carrier account use your store manual delivery provider automatically.</p>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Full rate type (advanced)</span>
                            <select id="method-field-rate-type-advanced" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                @foreach ($rateTypes as $rateType)<option value="{{ $rateType }}">{{ $rateLabels[$rateType] ?? $rateType }}</option>@endforeach
                            </select>
                        </label>
                        <p id="method-rate-carrier-note" class="hidden text-xs text-amber-800">Carrier-calculated rates are not enabled in this phase. Use flat rate or free shipping.</p>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Min order</span><input name="min_order_amount" id="method-field-min-order" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Max order</span><input name="max_order_amount" id="method-field-max-order" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Display order</span><input name="sort_order" id="method-field-sort" type="number" min="0" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    </div>
                </details>
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
