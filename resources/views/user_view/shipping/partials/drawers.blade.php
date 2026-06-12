{{-- Zone drawer --}}
<div id="shipping-drawer-zone" class="shipping-drawer fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="shipping-drawer-backdrop absolute inset-0 bg-slate-900/40" data-close-drawer></div>
    <div class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-5 py-4">
            <h3 id="zone-drawer-title" class="text-lg font-semibold text-[#0F172A]">Add zone</h3>
            <button type="button" class="text-[#64748B]" data-close-drawer aria-label="Close">✕</button>
        </div>
        <form id="zone-drawer-form" method="POST" action="{{ route('settings.shipping.zones.store') }}" class="flex flex-1 flex-col overflow-y-auto p-5 shipping-submit-form">
            @csrf
            <input type="hidden" name="_method" id="zone-form-method" value="POST" disabled>
            <div class="space-y-4">
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Zone name</span><input name="name" id="zone-field-name" required placeholder="United States" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Countries</span><input name="countries" id="zone-field-countries" placeholder="US, CA" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Regions</span><input name="regions" id="zone-field-regions" placeholder="California, Ontario" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Postal patterns</span><input name="postal_patterns" id="zone-field-postal" placeholder="941*, 10001" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Sort order</span><input name="sort_order" id="zone-field-sort" type="number" min="0" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                <label class="flex items-center gap-2 text-sm text-[#475569]"><input type="hidden" name="is_active" value="0" id="zone-active-hidden"><input type="checkbox" name="is_active" id="zone-field-active" value="1" checked class="rounded border-[#CBD5E1]"> Active</label>
            </div>
            <div class="mt-auto border-t border-[#F1F5F9] pt-4">
                <button type="submit" class="h-10 w-full rounded-lg bg-[#0052CC] text-sm font-bold text-white shipping-submit-btn">Save zone</button>
            </div>
        </form>
    </div>
</div>

{{-- Method drawer --}}
<div id="shipping-drawer-method" class="shipping-drawer fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="shipping-drawer-backdrop absolute inset-0 bg-slate-900/40" data-close-drawer></div>
    <div class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-[#E2E8F0] px-5 py-4">
            <h3 id="method-drawer-title" class="text-lg font-semibold text-[#0F172A]">Add delivery method</h3>
            <button type="button" class="text-[#64748B]" data-close-drawer aria-label="Close">✕</button>
        </div>
        <form id="method-drawer-form" method="POST" action="{{ route('settings.shipping.methods.store') }}" class="flex flex-1 flex-col overflow-y-auto p-5 shipping-submit-form">
            @csrf
            <input type="hidden" name="_method" id="method-form-method" value="POST" disabled>
            <div class="space-y-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Basic info</p>
                    <div class="mt-3 space-y-3">
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Method name</span><input name="name" id="method-field-name" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Customer label</span><input name="delivery_speed_label" id="method-field-label" placeholder="2-4 business days" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Zone</span>
                            <select name="shipping_zone_id" id="method-field-zone" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                @foreach ($shippingZones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach
                            </select>
                        </label>
                        <div class="flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm"><input type="hidden" name="enabled_for_checkout" value="0" id="method-checkout-hidden"><input type="checkbox" name="enabled_for_checkout" id="method-field-checkout" value="1" checked class="rounded border-[#CBD5E1]"> Checkout</label>
                            <label class="inline-flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0" id="method-active-hidden"><input type="checkbox" name="is_active" id="method-field-active" value="1" checked class="rounded border-[#CBD5E1]"> Active</label>
                        </div>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Fulfillment / carrier</p>
                    <label class="mt-3 block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Carrier account</span>
                        <select name="carrier_account_id" id="method-field-carrier" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                            <option value="">No carrier account</option>
                            @foreach ($carrierAccounts as $account)<option value="{{ $account->id }}">{{ $account->display_name }} ({{ $account->carrier?->name ?? $account->provider }})</option>@endforeach
                        </select>
                    </label>
                    <p id="method-carrier-note" class="mt-2 hidden text-xs text-amber-800">No carrier account — suitable for manual flat-rate delivery.</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Pricing</p>
                    <div class="mt-3 space-y-3">
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Rate type</span>
                            <select name="rate_type" id="method-field-rate-type" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                @foreach ($rateTypes as $rateType)<option value="{{ $rateType }}">{{ $rateLabels[$rateType] ?? $rateType }}</option>@endforeach
                            </select>
                        </label>
                        <p id="method-rate-carrier-note" class="hidden text-xs text-[#64748B]">Carrier-calculated rates are not enabled in this phase. Use flat rate or free shipping.</p>
                        <div id="method-flat-fields" class="grid grid-cols-2 gap-3">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Flat rate</span><input name="flat_rate" id="method-field-flat" type="number" min="0" step="0.01" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Free over</span><input name="free_over_amount" id="method-field-free-over" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Min order</span><input name="min_order_amount" id="method-field-min-order" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Max order</span><input name="max_order_amount" id="method-field-max-order" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
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
                    <label class="mt-3 block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Sort order</span><input name="sort_order" id="method-field-sort" type="number" min="0" value="0" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                </div>
            </div>
            <div class="mt-6 border-t border-[#F1F5F9] pt-4">
                <button type="submit" class="h-10 w-full rounded-lg bg-[#0052CC] text-sm font-bold text-white shipping-submit-btn">Save method</button>
            </div>
        </form>
    </div>
</div>
