{{-- Zone drawer — merchant simple form (right-side) --}}
<div id="shipping-drawer-zone" class="shipping-drawer shipping-side-drawer hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="zone-drawer-title">
    <button type="button" class="shipping-drawer-backdrop" data-close-drawer aria-label="Close drawer"></button>
    <div class="shipping-drawer-panel">
        <div class="shipping-drawer-head">
            <div>
                <h3 id="zone-drawer-title">Add delivery area</h3>
                <p>Choose where customers can receive orders.</p>
            </div>
            <button type="button" class="shipping-drawer-close" data-close-drawer aria-label="Close">×</button>
        </div>
        <form id="zone-drawer-form" method="POST" action="{{ route('settings.shipping.zones.store') }}" class="shipping-drawer-form shipping-submit-form">
            @csrf
            <input type="hidden" name="_method" id="zone-form-method" value="POST" disabled>
            <input type="hidden" name="zone_editor_mode" id="zone-editor-mode" value="simple">
            <input type="hidden" name="sort_order" id="zone-field-sort" value="0">
            <input type="hidden" name="legacy_countries" id="zone-field-legacy-countries" value="">
            <input type="hidden" name="legacy_regions" id="zone-field-legacy-regions" value="">
            <input type="hidden" name="legacy_postal_patterns" id="zone-field-legacy-postal" value="">

            <div class="shipping-drawer-body space-y-4">
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Area name</span>
                    <input name="name" id="zone-field-name" required placeholder="United States" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>

                <x-geo.country-select id="zone-field-country" :countries="$countries ?? []" required />

                <div class="dh-togglecard">
                    <div>
                        <strong>Entire country</strong>
                        <p class="help">Deliver to every supported state. Turn this off only if you need selected states.</p>
                    </div>
                    <button type="button" id="zone-entire-country" class="dh-switch is-on" aria-pressed="true" aria-label="Entire country"></button>
                </div>

                <div id="zone-region-multi-host" class="hidden">
                    <x-geo.region-multi-select id="zone-region-multi" />
                </div>

                <details class="dh-advanced">
                    <summary>Advanced postal coverage</summary>
                    <div class="mt-3">
                        <x-geo.postal-rule-builder input-id="zone-postal-rules-json" container-id="zone-postal-builder" />
                    </div>
                </details>

                <label class="flex items-center gap-2 text-sm text-[#334155]">
                    <input type="hidden" name="is_active" value="0" id="zone-active-hidden">
                    <input type="checkbox" name="is_active" id="zone-field-active" value="1" checked class="rounded border-[#CBD5E1]">
                    Available for customers
                </label>
            </div>

            <div class="shipping-drawer-foot">
                <button type="button" class="dh-btn dh-btn-ghost" data-close-drawer>Cancel</button>
                <button type="submit" class="dh-btn dh-btn-primary shipping-submit-btn">Save delivery area</button>
            </div>
        </form>
    </div>
</div>

{{-- Method drawer — merchant simple form --}}
<div id="shipping-drawer-method" class="shipping-drawer shipping-side-drawer hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="method-drawer-title">
    <button type="button" class="shipping-drawer-backdrop" data-close-drawer aria-label="Close drawer"></button>
    <div class="shipping-drawer-panel">
        <div class="shipping-drawer-head">
            <div>
                <h3 id="method-drawer-title">Add delivery option</h3>
                <p id="method-drawer-lead">Create a customer-facing checkout choice.</p>
            </div>
            <button type="button" class="shipping-drawer-close" data-close-drawer aria-label="Close">×</button>
        </div>
        <form id="method-drawer-form" method="POST" action="{{ route('settings.shipping.methods.store') }}" class="shipping-drawer-form shipping-submit-form">
            @csrf
            <input type="hidden" name="_method" id="method-form-method" value="POST" disabled>
            <input type="hidden" name="rate_type" id="method-field-rate-type-hidden" value="flat">
            <input type="hidden" name="description" id="method-field-description" value="">
            <input type="hidden" name="min_order_amount" id="method-field-min-order" value="">
            <input type="hidden" name="max_order_amount" id="method-field-max-order" value="">
            <input type="hidden" name="sort_order" id="method-field-sort" value="0">
            <input type="hidden" name="carrier_account_id" id="method-field-carrier" value="">
            <input type="hidden" id="method-field-rate-type-advanced" value="flat">

            <div class="shipping-drawer-body space-y-5">
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
                    <div class="mt-2 grid gap-2" id="method-price-cards">
                        <label class="dh-pricecard is-selected"><input type="radio" name="delivery_price_mode" value="fixed" checked class="sr-only" data-method-price-mode><strong>Fixed price</strong><span>Charge one set amount.</span></label>
                        <label class="dh-pricecard"><input type="radio" name="delivery_price_mode" value="free" class="sr-only" data-method-price-mode><strong>Free</strong><span>No delivery charge.</span></label>
                        <label class="dh-pricecard"><input type="radio" name="delivery_price_mode" value="free_over" class="sr-only" data-method-price-mode><strong>Free over an amount</strong><span>Free after an order threshold.</span></label>
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
                    <p class="mt-1 text-[11px] text-[#94A3B8]">Shown to customers as “3–5 business days”.</p>
                    <label class="mt-3 block space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Customer label <span class="font-normal text-[#94A3B8]">(optional)</span></span>
                        <input name="delivery_speed_label" id="method-field-label" placeholder="3–5 business days" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                    </label>
                </div>

                <div id="method-simple-availability" class="dh-togglecard">
                    <div>
                        <strong>Available at checkout</strong>
                        <p class="help">Turn off to hide this option without removing it.</p>
                    </div>
                    <div>
                        <input type="hidden" name="available_to_customers" value="0" id="method-available-hidden">
                        <input type="checkbox" name="available_to_customers" id="method-field-available" value="1" checked class="sr-only">
                        <button type="button" id="method-available-switch" class="dh-switch is-on" aria-pressed="true" aria-label="Available at checkout"></button>
                    </div>
                </div>
                <div id="method-flag-warning" class="hidden rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-3 text-xs text-[#92400E]"></div>
            </div>

            <div class="shipping-drawer-foot">
                <button type="button" class="dh-btn dh-btn-ghost" data-close-drawer>Cancel</button>
                <button type="submit" class="dh-btn dh-btn-primary shipping-submit-btn">Save changes</button>
            </div>
        </form>
    </div>
</div>

{{-- FedEx services drawer --}}
<div id="shipping-drawer-fedex-services" class="shipping-drawer shipping-side-drawer hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="fedex-services-drawer-title">
    <button type="button" class="shipping-drawer-backdrop" data-close-drawer aria-label="Close drawer"></button>
    <div class="shipping-drawer-panel">
        <div class="shipping-drawer-head">
            <div>
                <h3 id="fedex-services-drawer-title">FedEx live rates</h3>
                <p id="fedex-services-drawer-lead">Choose services customers can select.</p>
            </div>
            <button type="button" class="shipping-drawer-close" data-close-drawer aria-label="Close">×</button>
        </div>
        <form id="fedex-services-drawer-form" method="POST" action="#" class="shipping-drawer-form shipping-submit-form">
            @csrf
            <input type="hidden" name="_method" value="PATCH">
            <div class="shipping-drawer-body space-y-4">
                <div class="dh-togglecard">
                    <div>
                        <strong>Live rates at checkout</strong>
                        <p class="help">Turn off to hide FedEx rates without removing selected services.</p>
                    </div>
                    <div>
                        <input type="hidden" name="available_to_customers" value="0" id="fedex-services-available-hidden">
                        <input type="checkbox" name="available_to_customers" id="fedex-services-available" value="1" checked class="sr-only">
                        <button type="button" id="fedex-services-available-switch" class="dh-switch is-on" aria-pressed="true" aria-label="Live rates at checkout"></button>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold text-[#64748B]">Services customers can choose</p>
                    <div id="fedex-services-list" class="mt-2 space-y-2"></div>
                    <p class="mt-2 text-[11px] text-[#94A3B8]">Service availability still depends on the destination, package, and FedEx response.</p>
                </div>
            </div>
            <div class="shipping-drawer-foot">
                <button type="button" class="dh-btn dh-btn-ghost" data-close-drawer>Cancel</button>
                <button type="submit" class="dh-btn dh-btn-primary shipping-submit-btn">Save changes</button>
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
