@php
    $fedExAccount = $fedExActiveAccount ?? null;
    $shippingPackagePresets = collect($shippingPackagePresets ?? []);
    $shippingPreferences = is_array($shippingPreferences ?? null) ? $shippingPreferences : [];
    $addressReview = session('fedex_address_review');
    $shippingOptions = session('fedex_shipping_options');
    $rateQuotes = session('fedex_rate_quotes');
    if (! is_array($rateQuotes) && is_array($shippingOptions) && isset($shippingOptions['rates'])) {
        $rateQuotes = $shippingOptions;
    }
    $addressSuggestionSource = null;
    if (is_array($shippingOptions) && (int) ($shippingOptions['order_id'] ?? 0) === (int) $order->id && ! empty($shippingOptions['address_suggestions'] ?? $shippingOptions['suggestions'] ?? null)) {
        $addressSuggestionSource = $shippingOptions;
    } elseif (is_array($addressReview) && (int) ($addressReview['order_id'] ?? 0) === (int) $order->id) {
        $addressSuggestionSource = $addressReview;
    }
    $showFedExOps = $fedExAccount
        && ($canManageOrders ?? false)
        && ! ($isOrderExternallyManaged ?? false);
    $opsShipLabelsEnabled = filter_var(config('carriers.fedex.ops_ship_labels_enabled', false), FILTER_VALIDATE_BOOL);
    $defaultPreset = $shippingPackagePresets->firstWhere('is_default', true) ?? $shippingPackagePresets->first();
    $packageSource = old('package_source', $defaultPreset ? 'preset' : 'custom');
    if ($shippingPackagePresets->isEmpty()) {
        $packageSource = 'custom';
    }
    $selectedPresetId = (string) old('shipping_package_preset_id', $defaultPreset?->id ?? '');
    $defaultHandoff = old('pickup_type', $shippingPreferences['default_handoff_type'] ?? 'USE_SCHEDULED_PICKUP');
    $defaultSignature = old('signature_option', $shippingPreferences['default_signature_option'] ?? 'SERVICE_DEFAULT');
    $shippingAddress = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
    $recipientPhonePrefill = old('recipient_phone', $shippingAddress->phone ?? '');
    $routedOriginLocationId = (int) data_get($order->meta ?? [], 'fulfillment_routing.origin_location_id', 0);
    $selectedOriginId = (int) old('origin_location_id', $routedOriginLocationId ?: 0);
    $selectedOrigin = collect($fulfillmentLocations ?? [])->firstWhere('id', $selectedOriginId)
        ?? collect($fulfillmentLocations ?? [])->firstWhere('is_default', true)
        ?? collect($fulfillmentLocations ?? [])->first();
    $originMissingPhone = $selectedOrigin && blank($selectedOrigin->phone);
    $guidedOpen = old('carrier_rate_quote_id')
        || old('package_source')
        || old('recipient_phone')
        || (is_array($addressSuggestionSource))
        || (is_array($rateQuotes) && (int) ($rateQuotes['order_id'] ?? 0) === (int) $order->id)
        || $errors->has('fedex_ship')
        || $errors->has('fedex_rates')
        || $errors->has('fedex_address')
        || $errors->has('carrier_rate_quote_id')
        || $errors->has('items')
        || $errors->has('package_source')
        || $errors->has('weight');
    $originCountry = strtoupper((string) ($selectedOrigin->country_code ?? 'US'));
    $destinationCountry = strtoupper((string) ($shippingAddress->country_code ?? ''));
    $needsCustoms = $originCountry !== '' && $destinationCountry !== '' && $originCountry !== $destinationCountry;
    $enteredStreet = trim(implode(', ', array_filter([
        $shippingAddress->address_line1 ?? null,
        $shippingAddress->address_line2 ?? null,
    ])));
    $enteredCityLine = trim(implode(' ', array_filter([
        $shippingAddress->city ?? null,
        $shippingAddress->province_code ?: ($shippingAddress->state ?? null),
        $shippingAddress->postal_code ?? null,
    ])));
    $suggestions = collect($addressSuggestionSource['suggestions'] ?? $addressSuggestionSource['address_suggestions'] ?? [])
        ->filter(fn ($row) => is_array($row))
        ->values();
    $primarySuggestion = $suggestions->first();
    $packageSummary = data_get($rateQuotes, 'package_summary')
        ?? data_get($rateQuotes, 'package')
        ?? data_get($shippingOptions, 'package_summary')
        ?? data_get($shippingOptions, 'package');
    $shipmentPackageId = data_get($rateQuotes, 'shipment_package_id')
        ?? data_get($rateQuotes, 'package_id')
        ?? data_get($shippingOptions, 'shipment_package_id')
        ?? data_get($shippingOptions, 'package_id');
    $rateServiceOptions = collect($rateQuotes['rates'] ?? [])
        ->filter(fn ($rate) => is_array($rate) && filled(data_get($rate, 'quote_id')))
        ->values();
    $defaultQuoteId = old('carrier_rate_quote_id', data_get($rateQuotes, 'selected.quote_id', data_get($rateServiceOptions->first(), 'quote_id')));
    $ratesForThisOrder = is_array($rateQuotes) && (int) ($rateQuotes['order_id'] ?? 0) === (int) $order->id;
@endphp

@if ($showFedExOps)
    <section id="fedex-ship-panel" class="rounded-2xl border border-[#BFDBFE] bg-[#F8FBFF] p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-[#0F172A]">Ship with FedEx</h3>
                <p class="mt-1 text-sm leading-6 text-[#64748B]">
                    Choose items and a package, review the destination, get FedEx shipping options, then buy a label from the connected account ending in {{ $fedExAccount->maskedAccountNumber() }}.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.badge tone="info">{{ $fedExAccount->environment === 'live' ? 'Live' : 'Sandbox' }}</x-ui.badge>
                <button
                    type="button"
                    class="ui-btn ui-btn-primary"
                    data-fedex-ship-cta
                    aria-controls="fedex-guided-flow"
                >
                    Ship with FedEx
                </button>
            </div>
        </div>

        @if ($originMissingPhone && $selectedOrigin)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <p class="font-semibold">{{ $selectedOrigin->name }} needs a phone number before FedEx can create a label.</p>
                <a href="{{ route('settings.locations.index') }}" class="mt-1 inline-flex text-sm font-semibold text-[#1D4ED8] underline-offset-2 hover:underline">
                    Edit location
                </a>
            </div>
        @endif

        @error('fedex_rates')
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p>
        @enderror
        @error('fedex_ship')
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p>
        @enderror
        @error('fedex_address')
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p>
        @enderror

        <details id="fedex-guided-flow" class="mt-4 rounded-xl border border-[#BFDBFE] bg-white/70 p-4" @if ($guidedOpen) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Guided shipping flow</summary>
            <p class="mt-1 text-xs leading-5 text-[#64748B]">
                Select what you are shipping, choose a package once, get FedEx options, then buy the label. Existing FedEx shipments stay available below for download, tracking, and cancel.
            </p>

            {{-- Operation A: Get FedEx shipping options --}}
            <form
                id="fedex-get-options-form"
                method="POST"
                action="{{ route('orders.fedex.rates', $order) }}"
                class="mt-4 space-y-4"
                data-fedex-options-form
            >
                @csrf
                <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">

                {{-- Section 1: Items --}}
                <div class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">1. Items</p>
                    <p class="mt-1 text-xs leading-5 text-[#64748B]">Choose which remaining items to include on this shipment.</p>
                    @if (($order->items ?? collect())->isNotEmpty())
                        <div class="mt-3 space-y-2" data-fedex-options-items>
                            @foreach ($order->items as $itemIndex => $item)
                                @php $remainingQty = (int) ($remainingFulfillmentQuantities[$item->id] ?? $item->quantity); @endphp
                                @if ($remainingQty > 0)
                                    @php
                                        $itemSelected = old('items.'.$itemIndex.'.selected', '1') === '1' || old('items.'.$itemIndex.'.selected') === true;
                                    @endphp
                                    <div class="flex items-center justify-between gap-2 rounded-lg border border-[#F1F5F9] px-2.5 py-2">
                                        <label class="flex min-w-0 flex-1 items-center gap-2 text-xs text-[#334155]">
                                            <input type="hidden" name="items[{{ $itemIndex }}][selected]" value="0">
                                            <input
                                                type="checkbox"
                                                name="items[{{ $itemIndex }}][selected]"
                                                value="1"
                                                @checked($itemSelected)
                                                class="rounded border-stone-300"
                                                data-fedex-item-selected
                                                data-item-index="{{ $itemIndex }}"
                                            >
                                            <input type="hidden" name="items[{{ $itemIndex }}][order_item_id]" value="{{ $item->id }}" data-fedex-item-id data-item-index="{{ $itemIndex }}">
                                            <span class="truncate">{{ $item->product_name ?? ('Item #'.$item->id) }}</span>
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="{{ $remainingQty }}"
                                            name="items[{{ $itemIndex }}][quantity]"
                                            value="{{ old('items.'.$itemIndex.'.quantity', $remainingQty) }}"
                                            class="w-20 rounded-lg border border-stone-200 px-2 py-1 text-sm"
                                            data-fedex-item-qty
                                            data-item-index="{{ $itemIndex }}"
                                        >
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="mt-2 text-xs text-[#94A3B8]">No remaining items to ship.</p>
                    @endif
                    @error('items')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Section 2: Package --}}
                <div class="rounded-xl border border-[#E2E8F0] bg-white p-4" data-fedex-package-section>
                    <p class="text-sm font-semibold text-[#0F172A]">2. Package</p>
                    <p class="mt-1 text-xs leading-5 text-[#64748B]">Use a saved package size or enter custom dimensions. FedEx rates use this exact package.</p>

                    <div class="mt-3 space-y-3">
                        <label class="flex items-start gap-2 rounded-lg border border-[#E2E8F0] px-3 py-2.5 {{ $shippingPackagePresets->isEmpty() ? 'opacity-60' : '' }}">
                            <input
                                type="radio"
                                name="package_source"
                                value="preset"
                                class="mt-0.5"
                                data-package-source
                                @checked($packageSource === 'preset')
                                @disabled($shippingPackagePresets->isEmpty())
                            >
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-[#0F172A]">Saved package</span>
                                @if ($shippingPackagePresets->isNotEmpty())
                                    <select
                                        name="shipping_package_preset_id"
                                        class="mt-2 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm"
                                        data-package-preset-select
                                    >
                                        @foreach ($shippingPackagePresets as $preset)
                                            <option
                                                value="{{ $preset->id }}"
                                                @selected((string) $selectedPresetId === (string) $preset->id)
                                                data-weight="{{ $preset->weight_value }}"
                                                data-length="{{ $preset->length }}"
                                                data-width="{{ $preset->width }}"
                                                data-height="{{ $preset->height }}"
                                                data-weight-unit="{{ $preset->weight_unit ?: 'LB' }}"
                                                data-dim-unit="{{ $preset->dimension_unit ?: 'IN' }}"
                                            >
                                                {{ $preset->name }}{{ $preset->is_default ? ' (default)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-2 text-xs text-[#64748B]" data-package-preset-dims>
                                        @if ($defaultPreset)
                                            {{ number_format((float) $defaultPreset->length, 1) }}
                                            × {{ number_format((float) $defaultPreset->width, 1) }}
                                            × {{ number_format((float) $defaultPreset->height, 1) }}
                                            {{ $defaultPreset->dimension_unit ?: 'IN' }}
                                            @if ($defaultPreset->weight_value)
                                                · {{ number_format((float) $defaultPreset->weight_value, 2) }} {{ $defaultPreset->weight_unit ?: 'LB' }}
                                            @endif
                                        @endif
                                    </p>
                                @else
                                    <p class="mt-1 text-xs text-[#94A3B8]">No saved packages yet. Use a custom package or add sizes in Shipping settings.</p>
                                @endif
                            </span>
                        </label>

                        <label class="flex items-start gap-2 rounded-lg border border-[#E2E8F0] px-3 py-2.5">
                            <input
                                type="radio"
                                name="package_source"
                                value="custom"
                                class="mt-0.5"
                                data-package-source
                                @checked($packageSource === 'custom' || $shippingPackagePresets->isEmpty())
                            >
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-[#0F172A]">Custom package</span>
                                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4" data-custom-package-fields>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#64748B]">Weight (lb)</label>
                                        <input type="number" step="0.01" min="0.01" name="weight" value="{{ old('weight') }}" placeholder="—" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm" data-custom-package-input>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#64748B]">Length</label>
                                        <input type="number" step="0.01" min="0.01" name="length" value="{{ old('length') }}" placeholder="—" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm" data-custom-package-input>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#64748B]">Width</label>
                                        <input type="number" step="0.01" min="0.01" name="width" value="{{ old('width') }}" placeholder="—" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm" data-custom-package-input>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#64748B]">Height</label>
                                        <input type="number" step="0.01" min="0.01" name="height" value="{{ old('height') }}" placeholder="—" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm" data-custom-package-input>
                                    </div>
                                </div>
                            </span>
                        </label>
                    </div>

                    @if ($shippingPackagePresets->isEmpty())
                        <p class="mt-3 text-xs text-amber-800" data-package-empty-hint>Choose a package before requesting FedEx rates.</p>
                    @endif
                    @error('package_source')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    @error('weight')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    @error('shipping_package_preset_id')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Section 3: Ship-from / options --}}
                <div class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">3. Ship-from &amp; options</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">Ship from</label>
                            <select name="origin_location_id" required class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm" data-origin-location>
                                @foreach ($fulfillmentLocations as $location)
                                    <option
                                        value="{{ $location->id }}"
                                        @selected((string) old('origin_location_id', $selectedOrigin?->id ?: '') === (string) $location->id)
                                        data-has-phone="{{ filled($location->phone) ? '1' : '0' }}"
                                        data-location-name="{{ $location->name }}"
                                    >
                                        {{ $location->name }}{{ $location->is_default ? ' (default)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">Ship date</label>
                            <input type="date" name="ship_date" value="{{ old('ship_date', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">How will you hand off the package?</label>
                            <select name="pickup_type" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm">
                                <option value="DROPOFF_AT_FEDEX_LOCATION" @selected($defaultHandoff === 'DROPOFF_AT_FEDEX_LOCATION')>I will drop it off at FedEx</option>
                                <option value="USE_SCHEDULED_PICKUP" @selected($defaultHandoff === 'USE_SCHEDULED_PICKUP')>I already have a regular FedEx pickup</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">Recipient phone</label>
                            <input
                                type="text"
                                name="recipient_phone"
                                value="{{ $recipientPhonePrefill }}"
                                maxlength="60"
                                placeholder="Required for FedEx labels"
                                class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm"
                                data-recipient-phone
                            >
                        </div>
                    </div>
                    <label class="mt-3 inline-flex items-center gap-2 text-xs text-[#475569]">
                        <input type="checkbox" name="residential" value="1" @checked(old('residential', data_get($addressSuggestionSource, 'residential')))>
                        Residential delivery
                    </label>
                </div>

                {{-- Section 4: Address review --}}
                @if ($primarySuggestion)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-semibold text-amber-950">4. Address review</p>
                        <p class="mt-1 text-xs text-amber-900">FedEx suggested a corrected address. Choose which one to use for rates and the label.</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="rounded-lg border border-amber-200 bg-white p-3">
                                <span class="flex items-start gap-2">
                                    <input type="radio" name="address_choice" value="entered" class="mt-0.5" @checked(old('address_choice', 'entered') === 'entered')>
                                    <span>
                                        <span class="block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Entered</span>
                                        <span class="mt-1 block text-sm text-[#0F172A]">{{ $enteredStreet ?: '—' }}</span>
                                        <span class="block text-sm text-[#334155]">{{ $enteredCityLine }}</span>
                                        <span class="block text-xs text-[#64748B]">{{ strtoupper((string) ($shippingAddress->country_code ?? '')) }}</span>
                                    </span>
                                </span>
                            </label>
                            <label class="rounded-lg border border-amber-200 bg-white p-3">
                                <span class="flex items-start gap-2">
                                    <input type="radio" name="address_choice" value="suggested" class="mt-0.5" @checked(old('address_choice') === 'suggested')>
                                    <span>
                                        <span class="block text-xs font-semibold uppercase tracking-wide text-[#64748B]">Suggested</span>
                                        <span class="mt-1 block text-sm text-[#0F172A]">{{ $primarySuggestion['street'] ?? $primarySuggestion['address_line1'] ?? '—' }}</span>
                                        <span class="block text-sm text-[#334155]">
                                            {{ $primarySuggestion['city'] ?? '' }}
                                            {{ $primarySuggestion['state'] ?? $primarySuggestion['province_code'] ?? '' }}
                                            {{ $primarySuggestion['postal_code'] ?? '' }}
                                        </span>
                                        <span class="block text-xs text-[#64748B]">{{ strtoupper((string) ($primarySuggestion['country_code'] ?? '')) }}</span>
                                        @if (! empty($primarySuggestion['classification']))
                                            <span class="mt-1 inline-block text-[11px] uppercase tracking-wide text-amber-800">{{ $primarySuggestion['classification'] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </label>
                        </div>
                        <input type="hidden" name="suggested_street" value="{{ $primarySuggestion['street'] ?? $primarySuggestion['address_line1'] ?? '' }}">
                        <input type="hidden" name="suggested_address_line1" value="{{ $primarySuggestion['address_line1'] ?? $primarySuggestion['street'] ?? '' }}">
                        <input type="hidden" name="suggested_address_line2" value="{{ $primarySuggestion['address_line2'] ?? '' }}">
                        <input type="hidden" name="suggested_city" value="{{ $primarySuggestion['city'] ?? '' }}">
                        <input type="hidden" name="suggested_state" value="{{ $primarySuggestion['state'] ?? $primarySuggestion['province_code'] ?? '' }}">
                        <input type="hidden" name="suggested_postal_code" value="{{ $primarySuggestion['postal_code'] ?? '' }}">
                        <input type="hidden" name="suggested_country_code" value="{{ $primarySuggestion['country_code'] ?? '' }}">
                        <input type="hidden" name="suggested_classification" value="{{ $primarySuggestion['classification'] ?? '' }}">
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">
                        Get FedEx shipping options
                    </button>
                    <p class="text-xs text-[#64748B]">FedEx will validate the destination and return account rates for this package.</p>
                </div>
            </form>

            {{-- Rates + Operation B: Buy label --}}
            @if ($ratesForThisOrder)
                <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-white p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">FedEx shipping options</p>
                    @if (is_array($packageSummary))
                        <p class="mt-1 text-xs text-[#64748B]">
                            Rated package:
                            {{ data_get($packageSummary, 'weight') }} {{ data_get($packageSummary, 'weight_unit', 'LB') }}
                            ·
                            {{ data_get($packageSummary, 'length') }}
                            × {{ data_get($packageSummary, 'width') }}
                            × {{ data_get($packageSummary, 'height') }}
                            {{ data_get($packageSummary, 'dimension_unit', 'IN') }}
                        </p>
                    @endif

                    @if ($rateServiceOptions->isNotEmpty())
                        @if ($opsShipLabelsEnabled)
                            <form
                                id="fedex-buy-label-form"
                                method="POST"
                                action="{{ route('orders.fedex.shipments.create', $order) }}"
                                class="mt-3"
                                data-fedex-buy-form
                            >
                                @csrf
                                <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                                <input type="hidden" name="origin_location_id" value="{{ old('origin_location_id', data_get($rateQuotes, 'origin_location_id', $selectedOrigin?->id)) }}">
                                <input type="hidden" name="recipient_phone" value="{{ old('recipient_phone', $recipientPhonePrefill) }}" data-buy-recipient-phone>
                                @if (filled($shipmentPackageId))
                                    <input type="hidden" name="shipment_package_id" value="{{ $shipmentPackageId }}">
                                @endif
                                <div data-fedex-buy-items></div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-left text-sm">
                                        <thead class="text-xs uppercase tracking-wide text-[#64748B]">
                                            <tr>
                                                <th class="py-2 pr-3"></th>
                                                <th class="py-2 pr-4">Service</th>
                                                <th class="py-2 pr-4">Estimated delivery</th>
                                                <th class="py-2">Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($rateServiceOptions as $rate)
                                                @php
                                                    $eta = ! empty($rate['delivery_date'])
                                                        ? $rate['delivery_date']
                                                        : (! empty($rate['transit_days']) ? $rate['transit_days'].' day(s)' : '—');
                                                @endphp
                                                <tr class="border-t border-[#F1F5F9]">
                                                    <td class="py-2 pr-3">
                                                        <input
                                                            type="radio"
                                                            name="carrier_rate_quote_id"
                                                            value="{{ $rate['quote_id'] }}"
                                                            required
                                                            @checked((string) $defaultQuoteId === (string) $rate['quote_id'])
                                                        >
                                                    </td>
                                                    <td class="py-2 pr-4 font-medium text-[#0F172A]">{{ $rate['service_name'] ?? $rate['service_type'] ?? '—' }}</td>
                                                    <td class="py-2 pr-4 text-[#64748B]">{{ $eta }}</td>
                                                    <td class="py-2 tabular-nums text-[#0F172A]">{{ $rate['currency'] ?? '' }} {{ $rate['amount'] ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @error('carrier_rate_quote_id')
                                    <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                                @enderror

                                @if ($needsCustoms)
                                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3">
                                        <p class="text-xs font-semibold text-amber-950">Customs ({{ $originCountry }} → {{ $destinationCountry }})</p>
                                        <p class="mt-1 text-[11px] leading-4 text-amber-800">Customs details apply to the items selected above when you buy the label.</p>
                                        <div class="mt-2 space-y-2" data-fedex-buy-customs>
                                            @foreach ($order->items as $itemIndex => $item)
                                                @php $remainingQty = (int) ($remainingFulfillmentQuantities[$item->id] ?? $item->quantity); @endphp
                                                @if ($remainingQty > 0)
                                                    <div class="rounded-lg border border-amber-200 bg-white p-2" data-buy-customs-row data-item-index="{{ $itemIndex }}">
                                                        <p class="text-xs font-medium text-[#0F172A]">{{ $item->product_name ?? ('Item #'.$item->id) }}</p>
                                                        <input type="hidden" name="customs_clearance[commodities][{{ $itemIndex }}][order_item_id]" value="{{ $item->id }}">
                                                        <input type="hidden" name="customs_clearance[commodities][{{ $itemIndex }}][quantity]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.quantity', old('items.'.$itemIndex.'.quantity', $remainingQty)) }}" data-buy-customs-qty data-item-index="{{ $itemIndex }}">
                                                        <label class="mt-1 block text-[11px] font-semibold text-amber-900">Customs description</label>
                                                        <input type="text" name="customs_clearance[commodities][{{ $itemIndex }}][description]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.description', $item->product_name) }}" maxlength="450" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm">
                                                        <div class="mt-1 grid grid-cols-2 gap-2">
                                                            <div>
                                                                <label class="block text-[11px] font-semibold text-amber-900">Customs value</label>
                                                                <input type="number" step="0.01" min="0.01" name="customs_clearance[commodities][{{ $itemIndex }}][customs_value][amount]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.customs_value.amount', $item->unit_price ?? '') }}" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm">
                                                                <input type="hidden" name="customs_clearance[commodities][{{ $itemIndex }}][customs_value][currency]" value="{{ $order->currency_code ?: 'USD' }}">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[11px] font-semibold text-amber-900">Item weight (lb)</label>
                                                                <input type="number" step="0.01" min="0.01" name="customs_clearance[commodities][{{ $itemIndex }}][weight]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.weight') }}" placeholder="—" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm">
                                                                <input type="hidden" name="customs_clearance[commodities][{{ $itemIndex }}][weight_unit]" value="LB">
                                                            </div>
                                                        </div>
                                                        <div class="mt-1 grid grid-cols-2 gap-2">
                                                            <div>
                                                                <label class="block text-[11px] font-semibold text-amber-900">Made in</label>
                                                                <input type="text" name="customs_clearance[commodities][{{ $itemIndex }}][country_of_manufacture]" maxlength="2" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.country_of_manufacture', $originCountry) }}" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm uppercase">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[11px] font-semibold text-amber-900">HS code</label>
                                                                <input type="text" name="customs_clearance[commodities][{{ $itemIndex }}][harmonized_code]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.harmonized_code') }}" maxlength="18" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm" placeholder="Optional">
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                        <div class="mt-2 grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-amber-900">Duties payer</label>
                                                <select name="customs_clearance[duties_payment_type]" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm">
                                                    <option value="SENDER" @selected(old('customs_clearance.duties_payment_type', 'SENDER') === 'SENDER')>Sender</option>
                                                    <option value="RECIPIENT" @selected(old('customs_clearance.duties_payment_type') === 'RECIPIENT')>Recipient</option>
                                                    <option value="THIRD_PARTY" @selected(old('customs_clearance.duties_payment_type') === 'THIRD_PARTY')>Third party</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-amber-900">Total customs value</label>
                                                <input type="number" step="0.01" min="0.01" name="customs_clearance[total_customs_value][amount]" value="{{ old('customs_clearance.total_customs_value.amount', $order->grand_total ?? $order->total ?? '') }}" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm" required>
                                                <input type="hidden" name="customs_clearance[total_customs_value][currency]" value="{{ old('customs_clearance.total_customs_value.currency', $order->currency_code ?: 'USD') }}">
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if (! empty($fedExTradeDocuments) && count($fedExTradeDocuments))
                                    <label class="mt-3 block text-xs font-semibold text-[#64748B]">Trade document (international)</label>
                                    <select name="fedex_trade_document_id" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm">
                                        <option value="">None</option>
                                        @foreach ($fedExTradeDocuments as $doc)
                                            @if ($doc->status === 'uploaded' && filled($doc->fedex_document_id))
                                                <option value="{{ $doc->id }}" @selected((string) old('fedex_trade_document_id') === (string) $doc->id)>
                                                    {{ str($doc->document_type)->replace('_', ' ')->title() }} · {{ $doc->destination_country_code }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                @endif

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-[#64748B]">Label format</label>
                                        <select name="label_format" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm">
                                            @foreach (['PDF', 'PNG', 'ZPL'] as $format)
                                                <option value="{{ $format }}" @selected(old('label_format', $shippingPreferences['default_label_format'] ?? 'PDF') === $format)>{{ $format }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-[#64748B]">Signature</label>
                                        <select name="signature_option" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm">
                                            <option value="SERVICE_DEFAULT" @selected($defaultSignature === 'SERVICE_DEFAULT' || $defaultSignature === '')>No signature</option>
                                            <option value="INDIRECT" @selected($defaultSignature === 'INDIRECT')>Indirect</option>
                                            <option value="DIRECT" @selected($defaultSignature === 'DIRECT')>Direct</option>
                                            <option value="ADULT" @selected($defaultSignature === 'ADULT')>Adult</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-[#64748B]">Shipping reference</label>
                                        <input type="text" name="shipping_reference" maxlength="40" value="{{ old('shipping_reference') }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm" placeholder="Optional">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-[#64748B]">Email notification</label>
                                        <input type="email" name="email_notification" maxlength="120" value="{{ old('email_notification') }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm" placeholder="Optional">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-[#64748B]">Declared value</label>
                                        <input type="number" step="0.01" min="0" name="declared_value_amount" value="{{ old('declared_value_amount') }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm" placeholder="Optional">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="inline-flex items-center gap-2 text-xs text-[#475569]">
                                            <input type="checkbox" name="saturday_delivery" value="1" @checked(old('saturday_delivery', ($shippingPreferences['saturday_delivery_default'] ?? false) ? '1' : '0') === '1' || old('saturday_delivery') === true)>
                                            Saturday delivery
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="mt-4 inline-flex h-10 items-center rounded-lg border border-[#BBF7D0] bg-[#ECFDF5] px-4 text-sm font-semibold text-[#047857]" @disabled($originMissingPhone)>
                                    Buy label
                                </button>
                                @if ($originMissingPhone)
                                    <p class="mt-2 text-xs text-amber-800">Add a phone number to the ship-from location before buying a label.</p>
                                @endif
                            </form>
                        @else
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="text-xs uppercase tracking-wide text-[#64748B]">
                                        <tr>
                                            <th class="py-2 pr-4">Service</th>
                                            <th class="py-2 pr-4">Estimated delivery</th>
                                            <th class="py-2">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rateServiceOptions as $rate)
                                            @php
                                                $eta = ! empty($rate['delivery_date'])
                                                    ? $rate['delivery_date']
                                                    : (! empty($rate['transit_days']) ? $rate['transit_days'].' day(s)' : '—');
                                            @endphp
                                            <tr class="border-t border-[#F1F5F9]">
                                                <td class="py-2 pr-4 font-medium text-[#0F172A]">{{ $rate['service_name'] ?? $rate['service_type'] ?? '—' }}</td>
                                                <td class="py-2 pr-4 text-[#64748B]">{{ $eta }}</td>
                                                <td class="py-2 tabular-nums">{{ $rate['currency'] ?? '' }} {{ $rate['amount'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="mt-3 text-xs text-amber-800">Label purchase is currently disabled for this store environment.</p>
                        @endif
                    @else
                        <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">No FedEx account rates were returned. Adjust the package or destination and try again.</p>
                    @endif
                </div>
            @endif
        </details>

        <details class="mt-4 rounded-xl border border-dashed border-[#E2E8F0] bg-white/60 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-[#64748B]">Return labels</summary>
            <p class="mt-2 text-xs leading-5 text-[#64748B]">
                FedEx return labels are created from an approved return in the
                <a href="#returns-refunds" class="font-semibold text-[#1D4ED8] underline-offset-2 hover:underline">Returns</a>
                section on this order. Approve the return first, then create the label there.
            </p>
        </details>

        @php
            $orderFedExShipments = ($order->shipments ?? collect())->filter(
                fn ($shipment) => $shipment->isFedExManagedShipment($fedExAccount)
            );
        @endphp
        @if ($orderFedExShipments->isNotEmpty())
            <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-white p-4">
                <p class="text-sm font-semibold text-[#0F172A]">FedEx shipments</p>
                <ul class="mt-3 space-y-3">
                    @foreach ($orderFedExShipments as $shipment)
                        <li class="rounded-lg border border-[#F1F5F9] px-3 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="font-medium text-[#0F172A]">{{ $shipment->shipment_number }}</span>
                                    <span class="ml-2 text-[#64748B]">···{{ $shipment->tracking_number ? substr($shipment->tracking_number, -4) : '----' }}</span>
                                    <span class="ml-2 text-xs uppercase tracking-wide text-[#64748B]">{{ $shipment->status }}</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (array_values((array) data_get($shipment->metadata, 'fedex.labels', [])) as $labelIndex => $labelMeta)
                                        @if (is_array($labelMeta) && filled($labelMeta['path'] ?? null))
                                            <a href="{{ route('shipments.fedex.label.download', ['shipment' => $shipment, 'index' => $labelIndex]) }}" class="inline-flex h-8 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-2.5 text-xs font-semibold text-[#1D4ED8]">
                                                Download label{{ count((array) data_get($shipment->metadata, 'fedex.labels', [])) > 1 ? ' '.($labelIndex + 1) : '' }}
                                            </a>
                                        @endif
                                    @endforeach
                                    @if (filter_var(config('carriers.fedex.ops_tracking_enabled', false), FILTER_VALIDATE_BOOL))
                                        <form method="POST" action="{{ route('shipments.fedex.tracking.refresh', $shipment) }}">
                                            @csrf
                                            <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-[#E2E8F0] bg-white px-2.5 text-xs font-semibold text-[#334155]">Refresh tracking</button>
                                        </form>
                                    @endif
                                    @if ($opsShipLabelsEnabled
                                        && \App\Services\Carriers\FedEx\Operations\FedExShipmentCancelService::isCancellable($shipment))
                                        <form method="POST" action="{{ route('shipments.fedex.cancel', $shipment) }}" onsubmit="return confirm('Cancel this FedEx shipment?');">
                                            @csrf
                                            <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-red-200 bg-red-50 px-2.5 text-xs font-semibold text-red-700">Void / cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            @if (filled(data_get($shipment->metadata, 'fedex.tracking.status_text')))
                                <p class="mt-2 text-xs text-[#64748B]">{{ data_get($shipment->metadata, 'fedex.tracking.status_text') }}</p>
                            @endif
                            @if (filled($shipment->tracking_number))
                                @php
                                    $publicToken = data_get($shipment->metadata, 'fedex.public_tracking_token');
                                    $customerTrackingUrl = filled($publicToken)
                                        ? $shipment->publicFedExTrackingUrl($selectedStore->slug ?? $order->store?->slug)
                                        : null;
                                @endphp
                                @if ($customerTrackingUrl)
                                    <p class="mt-2 text-xs text-[#64748B]">
                                        Customer tracking:
                                        <a class="text-[#1D4ED8] underline" href="{{ $customerTrackingUrl }}" target="_blank" rel="noopener">
                                            open page
                                        </a>
                                    </p>
                                @else
                                    <p class="mt-2 text-xs text-[#64748B]">
                                        Tracking number: {{ $shipment->tracking_number }}
                                    </p>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($opsShipLabelsEnabled)
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <form method="POST" action="{{ route('orders.fedex.etd.upload', $order) }}" enctype="multipart/form-data" class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                    @csrf
                    <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                    <p class="text-sm font-semibold text-[#0F172A]">Customs / trade document</p>
                    <p class="mt-1 text-xs leading-5 text-[#64748B]">Upload a commercial invoice PDF for international electronic trade documents. Destination country is required.</p>
                    @error('fedex_etd')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    @error('document')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    @error('destination_country_code')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    <label class="mt-2 block text-xs font-semibold text-[#64748B]">PDF document</label>
                    <input type="file" name="document" accept="application/pdf,.pdf" required class="mt-1 w-full text-sm">
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">Origin</label>
                            <input type="text" name="origin_country_code" maxlength="2" value="{{ old('origin_country_code', $originCountry ?: 'US') }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm uppercase" placeholder="US">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">Destination</label>
                            <input type="text" name="destination_country_code" maxlength="2" value="{{ old('destination_country_code', $destinationCountry) }}" required class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm uppercase" placeholder="CA">
                        </div>
                    </div>
                    @if ($orderFedExShipments->isNotEmpty())
                        <label class="mt-2 block text-xs font-semibold text-[#64748B]">Link to shipment (optional)</label>
                        <select name="shipment_id" class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm">
                            <option value="">Order only</option>
                            @foreach ($orderFedExShipments as $shipment)
                                <option value="{{ $shipment->id }}">{{ $shipment->shipment_number }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="submit" class="mt-3 inline-flex h-9 items-center rounded-lg border border-[#E2E8F0] bg-white px-3 text-xs font-semibold text-[#334155]">Upload trade document</button>
                </form>

                <div class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                    <p class="text-sm font-semibold text-[#0F172A]">Trade documents and API status</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse (($fedExTradeDocuments ?? collect()) as $doc)
                            <li class="rounded-lg border border-[#F1F5F9] px-3 py-2">
                                <span class="font-medium text-[#0F172A]">{{ str($doc->document_type)->replace('_', ' ')->title() }}</span>
                                <span class="ml-2 text-xs uppercase tracking-wide text-[#64748B]">{{ $doc->status }}</span>
                                <p class="mt-1 text-xs text-[#64748B]">
                                    {{ $doc->destination_country_code ?: '—' }}
                                    @if ($doc->fedex_document_id)
                                        · FedEx doc ···{{ substr($doc->fedex_document_id, -6) }}
                                    @endif
                                </p>
                            </li>
                        @empty
                            <li class="text-xs text-[#94A3B8]">No trade documents uploaded for this order yet.</li>
                        @endforelse
                    </ul>
                    @if (! empty($fedExOrderApiEvents) && count($fedExOrderApiEvents))
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-[#64748B]">Recent FedEx activity</p>
                        <ul class="mt-2 space-y-1 text-xs text-[#64748B]">
                            @foreach ($fedExOrderApiEvents as $event)
                                <li>
                                    {{ str($event->action)->replace('_', ' ')->title() }}
                                    · {{ str($event->status)->replace('_', ' ')->title() }}
                                    @if (filled(data_get($event->response_summary, 'fedex_transaction_id')))
                                        · Ref {{ data_get($event->response_summary, 'fedex_transaction_id') }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        <script>
            (function () {
                const panel = document.getElementById('fedex-ship-panel');
                if (!panel) return;

                const guided = document.getElementById('fedex-guided-flow');
                const cta = panel.querySelector('[data-fedex-ship-cta]');
                if (cta && guided) {
                    cta.addEventListener('click', function () {
                        guided.open = true;
                        guided.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        const focusTarget = guided.querySelector('button, select, input, summary');
                        if (focusTarget && typeof focusTarget.focus === 'function') {
                            focusTarget.focus({ preventScroll: true });
                        }
                    });
                }

                const packageSection = panel.querySelector('[data-fedex-package-section]');
                function syncPackageSource() {
                    if (!packageSection) return;
                    const selected = packageSection.querySelector('[data-package-source]:checked');
                    const source = selected ? selected.value : 'custom';
                    const customFields = packageSection.querySelectorAll('[data-custom-package-input]');
                    const presetSelect = packageSection.querySelector('[data-package-preset-select]');
                    customFields.forEach(function (field) {
                        field.disabled = source !== 'custom';
                    });
                    if (presetSelect) {
                        presetSelect.disabled = source !== 'preset';
                    }
                }

                function syncPresetDims() {
                    if (!packageSection) return;
                    const select = packageSection.querySelector('[data-package-preset-select]');
                    const dims = packageSection.querySelector('[data-package-preset-dims]');
                    if (!select || !dims || !select.selectedOptions.length) return;
                    const option = select.selectedOptions[0];
                    const length = option.getAttribute('data-length') || '—';
                    const width = option.getAttribute('data-width') || '—';
                    const height = option.getAttribute('data-height') || '—';
                    const dimUnit = option.getAttribute('data-dim-unit') || 'IN';
                    const weight = option.getAttribute('data-weight');
                    const weightUnit = option.getAttribute('data-weight-unit') || 'LB';
                    dims.textContent = length + ' × ' + width + ' × ' + height + ' ' + dimUnit
                        + (weight ? (' · ' + weight + ' ' + weightUnit) : '');
                }

                if (packageSection) {
                    packageSection.querySelectorAll('[data-package-source]').forEach(function (radio) {
                        radio.addEventListener('change', syncPackageSource);
                    });
                    const presetSelect = packageSection.querySelector('[data-package-preset-select]');
                    if (presetSelect) {
                        presetSelect.addEventListener('change', syncPresetDims);
                    }
                    syncPackageSource();
                    syncPresetDims();
                }

                const optionsForm = panel.querySelector('[data-fedex-options-form]');
                const buyForm = panel.querySelector('[data-fedex-buy-form]');

                function copyItemsIntoBuyForm() {
                    if (!optionsForm || !buyForm) return;
                    const target = buyForm.querySelector('[data-fedex-buy-items]');
                    if (!target) return;
                    target.innerHTML = '';

                    const indexes = new Set();
                    optionsForm.querySelectorAll('[data-item-index]').forEach(function (el) {
                        indexes.add(el.getAttribute('data-item-index'));
                    });

                    indexes.forEach(function (index) {
                        const selectedCheckbox = optionsForm.querySelector('[data-fedex-item-selected][data-item-index="' + index + '"]');
                        const qtyInput = optionsForm.querySelector('[data-fedex-item-qty][data-item-index="' + index + '"]');
                        const idInput = optionsForm.querySelector('[data-fedex-item-id][data-item-index="' + index + '"]');
                        if (!selectedCheckbox || !qtyInput || !idInput) return;

                        const selected = selectedCheckbox.checked ? '1' : '0';
                        const fragment = document.createDocumentFragment();

                        [['selected', selected], ['order_item_id', idInput.value], ['quantity', qtyInput.value || '0']].forEach(function (pair) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'items[' + index + '][' + pair[0] + ']';
                            input.value = pair[1];
                            fragment.appendChild(input);
                        });

                        target.appendChild(fragment);

                        const customsQty = buyForm.querySelector('[data-buy-customs-qty][data-item-index="' + index + '"]');
                        const customsRow = buyForm.querySelector('[data-buy-customs-row][data-item-index="' + index + '"]');
                        if (customsQty) {
                            customsQty.value = qtyInput.value || '';
                        }
                        if (customsRow) {
                            const enabled = selected === '1' && parseInt(qtyInput.value || '0', 10) > 0;
                            customsRow.classList.toggle('hidden', !enabled);
                            customsRow.querySelectorAll('input, select, textarea').forEach(function (field) {
                                if (field.hasAttribute('data-buy-customs-qty') || field.type === 'hidden') {
                                    return;
                                }
                                field.disabled = !enabled;
                            });
                        }
                    });

                    const phoneSource = optionsForm.querySelector('[data-recipient-phone]');
                    const phoneTarget = buyForm.querySelector('[data-buy-recipient-phone]');
                    if (phoneSource && phoneTarget) {
                        phoneTarget.value = phoneSource.value || '';
                    }
                }

                if (buyForm) {
                    buyForm.addEventListener('submit', function () {
                        copyItemsIntoBuyForm();
                    });
                    copyItemsIntoBuyForm();
                }

                if (optionsForm) {
                    optionsForm.addEventListener('change', function () {
                        if (buyForm) copyItemsIntoBuyForm();
                    });
                    optionsForm.addEventListener('input', function () {
                        if (buyForm) copyItemsIntoBuyForm();
                    });
                }
            })();
        </script>
    </section>
@endif
