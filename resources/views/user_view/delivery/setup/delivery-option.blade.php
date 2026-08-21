@extends(($manageMode ?? false) ? 'user_view.delivery.manage-layout' : 'user_view.delivery.wizard-layout')

@section('wizard-content')
    @php
        $fedExReady = (bool) ($fedExConnected ?? false);
        $checkoutRatesPlatformOn = (bool) ($fedExCheckoutRatesPlatformEnabled ?? false);
        $defaultMode = old('checkout_shipping_mode', $checkoutShippingMode ?? 'fixed');
        $selectedServices = old('fedex_services', $selectedFedExServices ?? ['FEDEX_GROUND', 'GROUND_HOME_DELIVERY']);
        if (! is_array($selectedServices)) {
            $selectedServices = ['FEDEX_GROUND'];
        }
        $isManage = (bool) ($manageMode ?? false);
    @endphp

    <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm md:p-6">
        <h2 class="text-xl font-semibold text-[#0F172A]">
            {{ $isManage ? 'Checkout shipping for this area' : 'How should customers get shipping prices?' }}
        </h2>
        <p class="mt-2 text-sm text-[#64748B]">
            {{ $isManage
                ? 'Turn FedEx live rates and fixed or free options on or off for customers.'
                : 'Choose FedEx live rates, a fixed/free option, or both. You can change this later.' }}
        </p>

        @php
            $orphanWizardMethods = collect($shippingMethods ?? [])->filter(function ($m) {
                return method_exists($m, 'isOrphanedFromArea') && $m->isOrphanedFromArea();
            })->values();
        @endphp
        @if ($orphanWizardMethods->isNotEmpty())
            <div class="mt-4 space-y-2 rounded-xl border border-[#FECACA] bg-[#FEF2F2] p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-[#991B1B]">Unused delivery options</p>
                        <p class="mt-0.5 text-xs text-[#B91C1C]">These are not linked to a delivery area (often left behind after an area was removed).</p>
                    </div>
                    <form method="POST" action="{{ route('settings.shipping.methods.cleanup-orphans') }}" onsubmit="return confirm('Remove all unused delivery options?')">
                        @csrf
                        <button type="submit" class="rounded-lg border border-[#FECACA] bg-white px-3 py-1.5 text-xs font-semibold text-[#991B1B]">Remove all</button>
                    </form>
                </div>
                @foreach ($orphanWizardMethods as $orphan)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[#FECACA] bg-white px-3 py-2">
                        <p class="text-sm font-semibold text-[#0F172A]">{{ $orphan->name }}</p>
                        <form method="POST" action="{{ route('settings.shipping.methods.destroy', $orphan) }}" onsubmit="return confirm('Remove “{{ $orphan->name }}”?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-1.5 text-xs font-semibold text-[#991B1B]">Remove</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        @unless ($fedExReady)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-[#92400E]">FedEx is not connected</p>
                    <p class="mt-0.5 text-xs text-[#A16207]">Connect FedEx to offer live rates at checkout. You will return here after connecting.</p>
                </div>
                @if (\Illuminate\Support\Facades\Route::has('settings.shipping.fedex-integrator.start'))
                    <a href="{{ route('settings.shipping.fedex-integrator.start', ['return_intent' => 'delivery_checkout_setup']) }}" class="inline-flex h-9 items-center rounded-lg bg-[#4D148C] px-3 text-sm font-semibold text-white">Connect FedEx</a>
                @endif
            </div>
        @endunless

        @php
            $areaSummaries = collect($shippingZones ?? [])->map(function ($zone) use ($shippingMethods) {
                $zoneMethods = collect($shippingMethods ?? [])->where('shipping_zone_id', $zone->id)->filter(fn ($m) => $m->is_active || $m->enabled_for_checkout);
                $fedEx = $zoneMethods->filter(fn ($m) => method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod());
                $fallback = $zoneMethods->first(fn ($m) => ! (method_exists($m, 'isFedExLiveRateMethod') && $m->isFedExLiveRateMethod()));
                if ($fedEx->isEmpty() && ! $fallback) {
                    return null;
                }
                return [
                    'name' => $zone->name,
                    'services' => $fedEx->pluck('carrier_service_name')->filter()->implode(', ') ?: ($fedEx->isNotEmpty() ? 'FedEx live rates' : null),
                    'fallback' => $fallback
                        ? ($fallback->name.(((float) ($fallback->flat_rate ?? 0) > 0) ? ' $'.number_format((float) $fallback->flat_rate, 2) : ''))
                        : null,
                ];
            })->filter()->values();
        @endphp
        @if ($areaSummaries->isNotEmpty())
            <div class="mt-4 space-y-2 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Checkout shipping by area</p>
                @foreach ($areaSummaries as $summary)
                    <div class="flex flex-wrap items-start justify-between gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#0F172A]">{{ $summary['name'] }}</p>
                            @if ($summary['services'])
                                <p class="mt-0.5 text-xs text-[#64748B]">{{ $summary['services'] }}</p>
                            @endif
                            @if ($summary['fallback'])
                                <p class="mt-0.5 text-xs text-[#64748B]">Fallback: {{ $summary['fallback'] }}</p>
                            @elseif (! $summary['services'])
                                <p class="mt-0.5 text-xs text-[#64748B]">Fixed/free only</p>
                            @else
                                <p class="mt-0.5 text-xs text-[#94A3B8]">No fallback</p>
                            @endif
                        </div>
                        <span class="text-xs font-semibold text-[#475569]">Select area below to edit</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($flagMismatch ?? false)
            <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]">
                @if ($selectedMethod?->is_active && ! $selectedMethod?->enabled_for_checkout)
                    This option is active but hidden from checkout.
                @else
                    This option is shown at checkout but currently inactive.
                @endif
                Choose how to resolve this before saving.
            </div>
        @endif

        <form method="POST" action="{{ route(($manageMode ?? false) ? 'settings.delivery.checkout-options' : 'settings.delivery.setup.delivery-option') }}" class="mt-6 space-y-5" id="delivery-option-form">
            @csrf

            <div>
                <span class="text-xs font-semibold text-[#64748B]">Checkout shipping mode</span>
                <div class="mt-2 space-y-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 {{ $fedExReady ? '' : 'opacity-60' }}">
                        <input type="radio" name="checkout_shipping_mode" value="fedex_live" class="mt-1" @checked($defaultMode === 'fedex_live') @disabled(! $fedExReady) data-shipping-mode>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">FedEx live rates</span>
                            <span class="mt-1 block text-xs leading-5 text-[#64748B]">Show real FedEx rates from your connected FedEx account at checkout.</span>
                            @unless ($fedExReady)
                                <span class="mt-1 block text-xs text-amber-800">Connect FedEx first to use this option.</span>
                            @endunless
                            @if ($fedExReady && ! $checkoutRatesPlatformOn)
                                <span class="mt-1 block text-xs text-amber-800">Platform checkout rates are currently off. Methods will be saved, but live prices appear only after checkout rates are enabled.</span>
                            @endif
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#E2E8F0] bg-white px-4 py-3">
                        <input type="radio" name="checkout_shipping_mode" value="fixed" class="mt-1" @checked($defaultMode === 'fixed') data-shipping-mode>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">Fixed or free shipping</span>
                            <span class="mt-1 block text-xs leading-5 text-[#64748B]">Charge a fixed amount or offer free shipping.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 {{ $fedExReady ? '' : 'opacity-60' }}">
                        <input type="radio" name="checkout_shipping_mode" value="both" class="mt-1" @checked($defaultMode === 'both') @disabled(! $fedExReady) data-shipping-mode>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">FedEx live rates + fallback</span>
                            <span class="mt-1 block text-xs leading-5 text-[#64748B]">Use FedEx live rates and keep a fixed/free option available as a fallback.</span>
                        </span>
                    </label>
                </div>
            </div>

            <label class="block space-y-1">
                <span class="text-xs font-semibold text-[#64748B]">Delivery area</span>
                <select name="shipping_zone_id" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                    @foreach ($shippingZones as $zone)
                        <option value="{{ $zone->id }}" @selected(old('shipping_zone_id', $selectedZone?->id) == $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
            </label>

            <div data-panel="fedex" class="space-y-3 {{ in_array($defaultMode, ['fedex_live', 'both'], true) ? '' : 'hidden' }}">
                <p class="text-xs font-semibold text-[#64748B]">FedEx services offered at checkout</p>
                <div class="space-y-2">
                    @foreach (($fedExServices ?? []) as $service)
                        <label class="flex items-start gap-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm">
                            <input type="checkbox" name="fedex_services[]" value="{{ $service['code'] }}" class="mt-0.5"
                                @checked(in_array($service['code'], $selectedServices, true))>
                            <span>
                                <span class="font-semibold text-[#0F172A]">{{ $service['name'] }}</span>
                                <span class="mt-0.5 block text-xs text-[#64748B]">{{ $service['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('fedex_services')
                    <p class="text-xs text-red-700">{{ $message }}</p>
                @enderror
                @error('checkout_shipping_mode')
                    <p class="text-xs text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div data-panel="fixed" class="space-y-4 {{ in_array($defaultMode, ['fixed', 'both'], true) ? '' : 'hidden' }}">
                @if ($shippingMethods->isNotEmpty())
                    <label class="block space-y-1" data-fixed-only>
                        <span class="text-xs font-semibold text-[#64748B]">Delivery option</span>
                        <select name="shipping_method_id" id="wizard-method-select" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            <option value="">Create a new delivery option</option>
                            @foreach ($shippingMethods as $method)
                                @continue($method->isFedExLiveRateMethod())
                                <option value="{{ $method->id }}" @selected(old('shipping_method_id', $selectedMethod?->id) == $method->id)>{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="block space-y-1" data-fixed-only>
                    <span class="text-xs font-semibold text-[#64748B]">Option name</span>
                    <input name="name" value="{{ old('name', $selectedMethod?->name ?? 'Standard delivery') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>

                <label class="block space-y-1 hidden" data-both-only>
                    <span class="text-xs font-semibold text-[#64748B]">Fallback option name</span>
                    <input name="fallback_name" value="{{ old('fallback_name', $selectedMethod?->name ?? 'Standard delivery') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>

                <label class="block space-y-1" data-fixed-only>
                    <span class="text-xs font-semibold text-[#64748B]">Customer label</span>
                    <input name="delivery_speed_label" value="{{ old('delivery_speed_label', $selectedMethod?->delivery_speed_label) }}" placeholder="2-4 business days" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                </label>

                <div>
                    <span class="text-xs font-semibold text-[#64748B]">Delivery price</span>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        @foreach (['fixed' => 'Fixed price', 'free' => 'Free', 'free_over' => 'Free over amount'] as $mode => $label)
                            <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-sm">
                                <input type="radio" name="delivery_price_mode" value="{{ $mode }}" @checked(old('delivery_price_mode', $priceMode) === $mode)> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Price</span><input name="flat_rate" type="number" min="0" step="0.01" value="{{ old('flat_rate', $selectedMethod?->flat_rate ?? 5) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Free when order is at least</span><input name="free_over_amount" type="number" min="0" step="0.01" value="{{ old('free_over_amount', $selectedMethod?->free_over_amount) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Min days</span><input name="estimated_min_days" type="number" min="0" value="{{ old('estimated_min_days', $selectedMethod?->estimated_min_days) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                    <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Max days</span><input name="estimated_max_days" type="number" min="0" value="{{ old('estimated_max_days', $selectedMethod?->estimated_max_days) }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                </div>
            </div>

            @if ($flagMismatch ?? false)
                <fieldset class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                    <legend class="px-1 text-sm font-semibold text-[#0F172A]">Checkout visibility</legend>
                    <div class="mt-2 space-y-2 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="resolve_flag_mismatch" value="available" @checked(old('resolve_flag_mismatch') === 'available')> Make available at checkout</label>
                        <label class="flex items-center gap-2"><input type="radio" name="resolve_flag_mismatch" value="keep" @checked(old('resolve_flag_mismatch', 'keep') === 'keep')> Keep current settings</label>
                    </div>
                </fieldset>
            @else
                <label class="flex items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-sm text-[#334155]">
                    <input type="hidden" name="available_to_customers" value="0">
                    <input type="checkbox" name="available_to_customers" value="1" @checked(old('available_to_customers', $selectedMethod?->enabled_for_checkout ?? true))>
                    Available at checkout
                </label>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#F1F5F9] pt-4">
                <a href="{{ ($manageMode ?? false) || ($selectedStore ?? null)?->delivery_setup_completed_at ? route('shippingAutomation') : route('settings.delivery.setup.deliver-to') }}" class="text-sm font-semibold text-[#64748B]">
                    {{ ($manageMode ?? false) || ($selectedStore ?? null)?->delivery_setup_completed_at ? 'Back to Delivery' : 'Back' }}
                </a>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-brand px-5 text-sm font-bold text-white">
                        {{ ($manageMode ?? false) || ($selectedStore ?? null)?->delivery_setup_completed_at ? 'Save' : 'Save and continue' }}
                    </button>
                </div>
            </div>
        </form>
    </section>

    <script type="application/json" id="wizard-method-catalog">@json($methodCatalog ?? [])</script>
    <script>
    (function () {
        var form = document.getElementById('delivery-option-form');
        if (!form) return;

        function currentMode() {
            var checked = form.querySelector('[name="checkout_shipping_mode"]:checked');
            return checked ? checked.value : 'fixed';
        }

        function syncPanels() {
            var mode = currentMode();
            form.querySelectorAll('[data-panel="fedex"]').forEach(function (el) {
                el.classList.toggle('hidden', mode !== 'fedex_live' && mode !== 'both');
            });
            form.querySelectorAll('[data-panel="fixed"]').forEach(function (el) {
                el.classList.toggle('hidden', mode !== 'fixed' && mode !== 'both');
            });
            form.querySelectorAll('[data-fixed-only]').forEach(function (el) {
                el.classList.toggle('hidden', mode !== 'fixed');
            });
            form.querySelectorAll('[data-both-only]').forEach(function (el) {
                el.classList.toggle('hidden', mode !== 'both');
            });
        }

        form.querySelectorAll('[data-shipping-mode]').forEach(function (radio) {
            radio.addEventListener('change', syncPanels);
        });
        syncPanels();

        var catalog = {};
        try {
            var el = document.getElementById('wizard-method-catalog');
            if (el) catalog = JSON.parse(el.textContent || '{}');
        } catch (e) {}

        var select = document.getElementById('wizard-method-select');
        if (!select) return;

        function setValue(name, value) {
            var field = form.querySelector('[name="' + name + '"]');
            if (!field) return;
            if (field.type === 'checkbox') {
                field.checked = !!value;
            } else {
                field.value = value ?? '';
            }
        }

        select.addEventListener('change', function () {
            var data = catalog[select.value];
            if (!data) return;
            setValue('name', data.name);
            setValue('delivery_speed_label', data.delivery_speed_label);
            setValue('shipping_zone_id', data.shipping_zone_id);
            setValue('flat_rate', data.flat_rate);
            setValue('free_over_amount', data.free_over_amount);
            setValue('estimated_min_days', data.estimated_min_days);
            setValue('estimated_max_days', data.estimated_max_days);
            setValue('available_to_customers', data.available_to_customers);
            var mode = data.delivery_price_mode || 'fixed';
            form.querySelectorAll('[name="delivery_price_mode"]').forEach(function (radio) {
                radio.checked = radio.value === mode;
            });
        });
    })();
    </script>
@endsection
