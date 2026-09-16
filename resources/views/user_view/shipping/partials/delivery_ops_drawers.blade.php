{{-- Extra Delivery operations drawers: health, troubleshooting, FedEx connection next-steps. --}}
<div id="shipping-drawer-health" class="shipping-drawer shipping-side-drawer hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="health-drawer-title">
    <button type="button" class="shipping-drawer-backdrop" data-close-drawer aria-label="Close drawer"></button>
    <div class="shipping-drawer-panel">
        <div class="shipping-drawer-head">
            <div>
                <h3 id="health-drawer-title">Delivery health</h3>
                <p>Overall status: {{ $statusBadgeLabel }}</p>
            </div>
            <button type="button" class="shipping-drawer-close" data-close-drawer aria-label="Close">×</button>
        </div>
        <div class="shipping-drawer-body space-y-1">
            @php
                $healthOriginOk = (bool) ($originCarrierReady ?? false);
                $healthAreaOk = $allZones->contains(fn ($z) => $z->is_active);
                $healthCheckoutOk = $methods->contains(fn ($m) => $m->is_active && $m->enabled_for_checkout);
                $healthFedExOk = (bool) ($fedExConnected ?? false);
                $healthPackageOk = (bool) ($defaultPreset ?? false);
            @endphp
            <div class="delivery-ops-check-row {{ $healthOriginOk ? '' : 'is-alert' }}">
                <svg class="do-icon" aria-hidden="true"><use href="#{{ $healthOriginOk ? 'do-i-check' : 'do-i-alert' }}"/></svg>
                <span>
                    <strong>Ship-from location</strong>
                    <small>{{ $healthOriginOk ? ($defaultLocation->name ?? 'Ready') : 'Add a complete active location' }}</small>
                </span>
            </div>
            <div class="delivery-ops-check-row {{ $healthAreaOk ? '' : 'is-alert' }}">
                <svg class="do-icon" aria-hidden="true"><use href="#{{ $healthAreaOk ? 'do-i-check' : 'do-i-alert' }}"/></svg>
                <span>
                    <strong>Delivery area</strong>
                    <small>{{ $allZones->where('is_active', true)->count() }} active</small>
                </span>
            </div>
            <div class="delivery-ops-check-row {{ $healthCheckoutOk ? '' : 'is-alert' }}">
                <svg class="do-icon" aria-hidden="true"><use href="#{{ $healthCheckoutOk ? 'do-i-check' : 'do-i-alert' }}"/></svg>
                <span>
                    <strong>Checkout option</strong>
                    <small>{{ $methods->filter(fn ($m) => $m->is_active && $m->enabled_for_checkout)->count() }} available</small>
                </span>
            </div>
            <div class="delivery-ops-check-row {{ $healthFedExOk ? '' : 'is-alert' }}">
                <svg class="do-icon" aria-hidden="true"><use href="#{{ $healthFedExOk ? 'do-i-check' : 'do-i-alert' }}"/></svg>
                <span>
                    <strong>FedEx</strong>
                    <small>{{ $healthFedExOk ? 'Connected' : 'Optional for fixed/free delivery' }}</small>
                </span>
            </div>
            <div class="delivery-ops-check-row {{ $healthPackageOk ? '' : 'is-alert' }}">
                <svg class="do-icon" aria-hidden="true"><use href="#{{ $healthPackageOk ? 'do-i-check' : 'do-i-alert' }}"/></svg>
                <span>
                    <strong>Default package</strong>
                    <small>{{ $defaultPreset->name ?? 'Recommended for FedEx' }}</small>
                </span>
            </div>
            @if ($healthItems->isNotEmpty())
                <div class="mt-4 border-t border-[#EDF1F4] pt-3">
                    <h4 class="mb-2 text-xs font-semibold text-[#334155]">Recommendations</h4>
                    @foreach ($healthItems as $item)
                        <div class="mb-2 rounded-lg border border-[#F2D087] bg-[#FFF8E8] px-3 py-2 text-xs text-[#93470A]">
                            <p>{{ $item['message'] ?? ($item['label'] ?? '') }}</p>
                            @if (! empty($item['action_href']))
                                <a href="{{ $item['action_href'] }}" class="mt-1 inline-block font-semibold text-[#087A5B] hover:underline">{{ $item['action_label'] ?? 'Review' }} →</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="shipping-drawer-foot">
            <button type="button" class="dh-btn dh-btn-ghost" data-close-drawer>Close</button>
        </div>
    </div>
</div>

<div id="shipping-drawer-troubleshooting" class="shipping-drawer shipping-side-drawer hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="troubleshooting-drawer-title">
    <button type="button" class="shipping-drawer-backdrop" data-close-drawer aria-label="Close drawer"></button>
    <div class="shipping-drawer-panel">
        <div class="shipping-drawer-head">
            <div>
                <h3 id="troubleshooting-drawer-title">Delivery troubleshooting</h3>
                <p>Checks use the same configuration shown on this page.</p>
            </div>
            <button type="button" class="shipping-drawer-close" data-close-drawer aria-label="Close">×</button>
        </div>
        <div class="shipping-drawer-body space-y-4">
            <div class="rounded-lg border border-dashed border-[#DCE3E8] bg-[#FAFCFC] p-3">
                <strong class="block text-sm">Checkout preview</strong>
                <p class="mt-1 text-xs text-[#64748B]">Test destination matching, method visibility, and example pricing with your live delivery settings.</p>
                <button type="button" class="dh-btn dh-btn-primary mt-3 inline-flex" data-delivery-action="test-checkout">Test checkout</button>
            </div>
            <div>
                <h4 class="mb-1 text-xs font-semibold text-[#334155]">Configuration checks</h4>
                <div class="delivery-ops-check-row {{ $originCarrierReady ? '' : 'is-alert' }}">
                    <svg class="do-icon" aria-hidden="true"><use href="#{{ $originCarrierReady ? 'do-i-check' : 'do-i-alert' }}"/></svg>
                    <span>
                        <strong>Shipping origin</strong>
                        <small>{{ $originCarrierReady ? 'Ready for carrier requests' : 'Missing required address details' }}</small>
                    </span>
                </div>
                <div class="delivery-ops-check-row {{ $allZones->contains(fn ($z) => $z->is_active) ? '' : 'is-alert' }}">
                    <svg class="do-icon" aria-hidden="true"><use href="#{{ $allZones->contains(fn ($z) => $z->is_active) ? 'do-i-check' : 'do-i-alert' }}"/></svg>
                    <span>
                        <strong>Delivery coverage</strong>
                        <small>{{ $allZones->where('is_active', true)->count() }} active area(s)</small>
                    </span>
                </div>
                <div class="delivery-ops-check-row {{ $methods->contains(fn ($m) => $m->enabled_for_checkout) ? '' : 'is-alert' }}">
                    <svg class="do-icon" aria-hidden="true"><use href="#{{ $methods->contains(fn ($m) => $m->enabled_for_checkout) ? 'do-i-check' : 'do-i-alert' }}"/></svg>
                    <span>
                        <strong>Checkout options</strong>
                        <small>{{ $methods->where('enabled_for_checkout', true)->count() }} visible option(s)</small>
                    </span>
                </div>
            </div>
        </div>
        <div class="shipping-drawer-foot">
            <button type="button" class="dh-btn dh-btn-ghost" data-close-drawer>Close</button>
        </div>
    </div>
</div>

@php
    $testCountries = \App\Support\Tax\TaxCountryCatalog::all();
    $testPresets = $packagePresetsList ?? collect($packagePresets ?? []);
    $testCurrency = $currency ?? 'USD';
@endphp
<div id="shipping-drawer-test-checkout" class="shipping-drawer shipping-side-drawer hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="test-checkout-drawer-title">
    <button type="button" class="shipping-drawer-backdrop" data-close-drawer aria-label="Close drawer"></button>
    <div class="shipping-drawer-panel">
        <div class="shipping-drawer-head">
            <div>
                <h3 id="test-checkout-drawer-title">Preview checkout delivery</h3>
                <p>See the options a customer would get for an address. This does not change orders or inventory.</p>
            </div>
            <button type="button" class="shipping-drawer-close" data-close-drawer aria-label="Close">×</button>
        </div>
        <div class="shipping-drawer-body space-y-4">
            <form
                id="delivery-test-checkout-form"
                method="POST"
                action="{{ route('settings.delivery.test-address') }}"
                class="space-y-3"
            >
                @csrf
                <x-geo.country-select
                    id="delivery-test-country"
                    name="country_code"
                    :selected="'US'"
                    :countries="$testCountries"
                    required
                />
                <div data-test-region-host>
                    <x-geo.region-select
                        name="region_code"
                        country-code="US"
                        label="State / province (optional)"
                    />
                </div>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">ZIP / postal code</span>
                    <input name="postal_code" value="" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase">
                </label>
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Order subtotal ({{ $testCurrency }}, optional)</span>
                    <input name="order_subtotal" type="number" min="0" step="0.01" placeholder="50.00" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>
                <details class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <summary class="cursor-pointer text-sm font-semibold text-[#087A5B]">Use a custom package</summary>
                    <div class="mt-3 space-y-3">
                        <label class="block space-y-1">
                            <span class="text-xs font-semibold text-[#64748B]">Package preset</span>
                            <select name="package_preset_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                <option value="">Store default{{ $testPresets->isEmpty() ? ' (none yet)' : '' }}</option>
                                @foreach ($testPresets as $preset)
                                    <option value="{{ $preset->id }}">{{ $preset->name }}{{ $preset->is_default ? ' (default)' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Weight</span>
                                <input name="package_weight" type="number" min="0.01" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                            </label>
                            <label class="block space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Unit</span>
                                <select name="package_weight_unit" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                    <option value="LB">LB</option>
                                    <option value="KG">KG</option>
                                </select>
                            </label>
                            <label class="block space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Length</span>
                                <input name="package_length" type="number" min="0.01" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                            </label>
                            <label class="block space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Width</span>
                                <input name="package_width" type="number" min="0.01" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                            </label>
                            <label class="block space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Height</span>
                                <input name="package_height" type="number" min="0.01" step="0.01" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                            </label>
                            <label class="block space-y-1">
                                <span class="text-xs font-semibold text-[#64748B]">Dim unit</span>
                                <select name="package_dimension_unit" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                                    <option value="IN">IN</option>
                                    <option value="CM">CM</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </details>
                <button type="submit" class="dh-btn dh-btn-primary w-full">Preview checkout delivery</button>
            </form>
            <div id="delivery-test-checkout-results" class="space-y-3"></div>
        </div>
        <div class="shipping-drawer-foot">
            <button type="button" class="dh-btn dh-btn-ghost" data-close-drawer>Close</button>
        </div>
    </div>
</div>

@if ($canManage)
<div id="shipping-drawer-fedex-connect" class="shipping-drawer shipping-side-drawer hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="fedex-connect-drawer-title">
    <button type="button" class="shipping-drawer-backdrop" data-close-drawer aria-label="Close drawer"></button>
    <div class="shipping-drawer-panel">
        <div class="shipping-drawer-head">
            <div>
                <h3 id="fedex-connect-drawer-title">{{ ($fedExUiStatus ?? '') === 'pending_validation' ? 'Resume FedEx connection' : 'Connect FedEx' }}</h3>
                <p>Your account is verified through the existing hosted FedEx onboarding flow.</p>
            </div>
            <button type="button" class="shipping-drawer-close" data-close-drawer aria-label="Close">×</button>
        </div>
        <div class="shipping-drawer-body space-y-3">
            <div class="rounded-lg border border-dashed border-[#DCE3E8] bg-[#FAFCFC] p-3 text-sm">
                <strong>What happens next</strong>
                <p class="mt-2 text-xs leading-5 text-[#64748B]">
                    1. Choose the shipping origin.<br>
                    2. Review the FedEx agreement.<br>
                    3. Verify the account using FedEx’s supported method.<br>
                    4. Return here to choose checkout services.
                </p>
            </div>
            <p class="delivery-ops-note">No developer keys are entered on this page. Billing remains on the merchant’s connected FedEx account.</p>
        </div>
        <div class="shipping-drawer-foot">
            <button type="button" class="dh-btn dh-btn-ghost" data-close-drawer>Cancel</button>
            @if (($fedExUiStatus ?? '') === 'disabled')
                <button type="button" class="dh-btn dh-btn-primary" disabled>Unavailable</button>
            @else
                <a href="{{ $fedExHref }}" class="dh-btn dh-btn-primary">{{ ($fedExUiStatus ?? '') === 'pending_validation' ? 'Complete connection' : 'Start connection' }}</a>
            @endif
        </div>
    </div>
</div>
@endif
