@php
    $fedExAccount = $fedExActiveAccount ?? null;
    $addressReview = session('fedex_address_review');
    $availabilityReview = session('fedex_service_availability');
    $rateQuotes = session('fedex_rate_quotes');
    $showFedExOps = $fedExAccount
        && ($canManageOrders ?? false)
        && ! ($isOrderExternallyManaged ?? false);
@endphp

@if ($showFedExOps)
    <section class="rounded-2xl border border-[#BFDBFE] bg-[#F8FBFF] p-5 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-[#0F172A]">FedEx shipping checks</h3>
                <p class="mt-1 text-sm leading-6 text-[#64748B]">
                    Validate the destination, confirm available services, then get negotiated rates from the connected FedEx account
                    ending in {{ $fedExAccount->maskedAccountNumber() }}.
                </p>
            </div>
            <x-ui.badge tone="info">{{ $fedExAccount->environment === 'live' ? 'Live' : 'Sandbox' }}</x-ui.badge>
        </div>

        @error('fedex_address')
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p>
        @enderror
        @error('fedex_availability')
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p>
        @enderror
        @error('fedex_rates')
            <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p>
        @enderror

        <div class="mt-4 grid gap-3 lg:grid-cols-3">
            <form method="POST" action="{{ route('orders.fedex.validate-address', $order) }}" class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                @csrf
                <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                <p class="text-sm font-semibold text-[#0F172A]">1. Validate address</p>
                <p class="mt-1 text-xs leading-5 text-[#64748B]">FedEx may suggest a corrected US/Canada address and residential/business classification. Review before shipping.</p>
                <button type="submit" class="mt-3 inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]">Validate destination</button>
            </form>

            <form method="POST" action="{{ route('orders.fedex.service-availability', $order) }}" class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                @csrf
                <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                <p class="text-sm font-semibold text-[#0F172A]">2. Service availability</p>
                <label class="mt-2 block text-xs font-semibold text-[#64748B]">Ship from</label>
                <select name="origin_location_id" required class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm">
                    @foreach ($fulfillmentLocations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('origin_location_id', $routedOriginLocationId ?: '') === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                <label class="mt-2 block text-xs font-semibold text-[#64748B]">Ship date</label>
                <input type="date" name="ship_date" value="{{ old('ship_date', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm">
                <button type="submit" class="mt-3 inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]">Check services</button>
            </form>

            <form method="POST" action="{{ route('orders.fedex.rates', $order) }}" class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                @csrf
                <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                <p class="text-sm font-semibold text-[#0F172A]">3. Negotiated rates</p>
                <label class="mt-2 block text-xs font-semibold text-[#64748B]">Ship from</label>
                <select name="origin_location_id" required class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm">
                    @foreach ($fulfillmentLocations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('origin_location_id', $routedOriginLocationId ?: '') === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-semibold text-[#64748B]">Weight (lb)</label>
                        <input type="number" step="0.01" min="0.01" name="weight" value="{{ old('weight', 1) }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#64748B]">Ship date</label>
                        <input type="date" name="ship_date" value="{{ old('ship_date', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm">
                    </div>
                </div>
                <label class="mt-2 inline-flex items-center gap-2 text-xs text-[#475569]">
                    <input type="checkbox" name="residential" value="1" @checked(old('residential'))>
                    Residential delivery
                </label>
                <button type="submit" class="mt-3 inline-flex h-9 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-3 text-xs font-semibold text-[#1D4ED8]">Get FedEx rates</button>
            </form>
        </div>

        @if (is_array($addressReview) && (int) ($addressReview['order_id'] ?? 0) === (int) $order->id)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-amber-950">Review suggested address before continuing</p>
                <p class="mt-1 text-xs text-amber-900">FedEx address validation is advisory. Confirm the corrected address matches the customer’s intent.</p>
                <ul class="mt-3 space-y-2 text-sm text-amber-950">
                    @foreach (($addressReview['suggestions'] ?? []) as $suggestion)
                        <li class="rounded-lg border border-amber-200 bg-white px-3 py-2">
                            <span class="font-medium">{{ $suggestion['street'] ?? '—' }}</span>,
                            {{ $suggestion['city'] ?? '' }} {{ $suggestion['state'] ?? '' }} {{ $suggestion['postal_code'] ?? '' }}
                            ({{ $suggestion['country_code'] ?? '' }})
                            @if (! empty($suggestion['classification']))
                                <span class="ml-2 text-xs uppercase tracking-wide text-amber-800">{{ $suggestion['classification'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (is_array($availabilityReview) && (int) ($availabilityReview['order_id'] ?? 0) === (int) $order->id)
            <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-white p-4">
                <p class="text-sm font-semibold text-[#0F172A]">Available FedEx services ({{ (int) ($availabilityReview['service_count'] ?? 0) }})</p>
                <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach (array_slice($availabilityReview['services'] ?? [], 0, 12) as $service)
                        <li class="rounded-lg border border-[#F1F5F9] px-3 py-2 text-sm text-[#334155]">
                            {{ is_array($service) ? ($service['service_name'] ?? $service['service_type'] ?? 'Service') : (string) $service }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (is_array($rateQuotes) && (int) ($rateQuotes['order_id'] ?? 0) === (int) $order->id)
            <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-white p-4">
                <p class="text-sm font-semibold text-[#0F172A]">Negotiated FedEx rates</p>
                <p class="mt-1 text-xs text-[#64748B]">Account rates from your connected FedEx merchant account.</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-[#64748B]">
                            <tr>
                                <th class="py-2 pr-4">Service</th>
                                <th class="py-2 pr-4">Type</th>
                                <th class="py-2 pr-4">Amount</th>
                                <th class="py-2">Transit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rateQuotes['rates'] ?? [] as $rate)
                                <tr class="border-t border-[#F1F5F9]">
                                    <td class="py-2 pr-4 font-medium text-[#0F172A]">{{ $rate['service_name'] ?? $rate['service_type'] ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-[#64748B]">{{ $rate['rate_type'] ?? '—' }}</td>
                                    <td class="py-2 pr-4 tabular-nums">{{ $rate['currency'] ?? '' }} {{ $rate['amount'] ?? '—' }}</td>
                                    <td class="py-2 text-[#64748B]">
                                        @if (! empty($rate['transit_days']))
                                            {{ $rate['transit_days'] }} day(s)
                                        @elseif (! empty($rate['delivery_date']))
                                            {{ $rate['delivery_date'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if (filter_var(config('carriers.fedex.ops_ship_labels_enabled', false), FILTER_VALIDATE_BOOL))
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <form method="POST" action="{{ route('orders.fedex.shipments.create', $order) }}" class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                    @csrf
                    <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                    <p class="text-sm font-semibold text-[#0F172A]">4. Create FedEx label</p>
                    <p class="mt-1 text-xs leading-5 text-[#64748B]">Select a fresh negotiated rate quote, choose remaining item quantities, then purchase the label.</p>
                    @error('fedex_ship')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    @error('carrier_rate_quote_id')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    <label class="mt-2 block text-xs font-semibold text-[#64748B]">Ship from</label>
                    <select name="origin_location_id" required class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm">
                        @foreach ($fulfillmentLocations as $location)
                            <option value="{{ $location->id }}" @selected((string) old('origin_location_id', $routedOriginLocationId ?: '') === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @php
                        $rateServiceOptions = collect($rateQuotes['rates'] ?? [])
                            ->filter(fn ($rate) => filled(data_get($rate, 'quote_id')) && filled(data_get($rate, 'service_type')))
                            ->values();
                        $defaultQuoteId = old('carrier_rate_quote_id', data_get($rateQuotes, 'selected.quote_id', data_get($rateServiceOptions->first(), 'quote_id')));
                    @endphp
                    <label class="mt-2 block text-xs font-semibold text-[#64748B]">Rate quote</label>
                    @if ($rateServiceOptions->isNotEmpty() && (int) ($rateQuotes['order_id'] ?? 0) === (int) $order->id)
                        <select name="carrier_rate_quote_id" required class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm">
                            @foreach ($rateServiceOptions as $rate)
                                <option value="{{ $rate['quote_id'] }}" @selected((string) $defaultQuoteId === (string) $rate['quote_id'])>
                                    {{ $rate['service_name'] ?? $rate['service_type'] }}
                                    — {{ $rate['currency'] ?? '' }} {{ $rate['amount'] ?? '—' }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <p class="mt-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">Get negotiated rates first. Labels require a selected ACCOUNT quote.</p>
                        <input type="hidden" name="carrier_rate_quote_id" value="">
                    @endif
                    @php
                        $selectedOrigin = collect($fulfillmentLocations ?? [])->firstWhere('id', (int) old('origin_location_id', $routedOriginLocationId ?: 0))
                            ?? collect($fulfillmentLocations ?? [])->first();
                        $originCountry = strtoupper((string) ($selectedOrigin->country_code ?? 'US'));
                        $destinationCountry = strtoupper((string) optional($order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first())->country_code);
                        $needsCustoms = $originCountry !== '' && $destinationCountry !== '' && $originCountry !== $destinationCountry;
                    @endphp
                    @if (($order->items ?? collect())->isNotEmpty())
                        <p class="mt-3 text-xs font-semibold text-[#64748B]">Items to ship</p>
                        <p class="mt-1 text-[11px] leading-4 text-[#94A3B8]">Uncheck any product you want to leave for a later shipment.</p>
                        @if ($needsCustoms)
                            <p class="mt-1 text-[11px] leading-4 text-amber-800">International ({{ $originCountry }} → {{ $destinationCountry }}): customs fields apply only to checked items.</p>
                        @endif
                        <div class="mt-1 space-y-2" data-fedex-ship-items>
                            @foreach ($order->items as $itemIndex => $item)
                                @php $remainingQty = (int) ($remainingFulfillmentQuantities[$item->id] ?? $item->quantity); @endphp
                                @if ($remainingQty > 0)
                                    @php
                                        $itemSelected = old('items.'.$itemIndex.'.selected', '1') === '1' || old('items.'.$itemIndex.'.selected') === true;
                                    @endphp
                                    <div class="space-y-2 rounded-lg border border-[#F1F5F9] px-2.5 py-2" data-ship-item-row>
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="flex min-w-0 flex-1 items-center gap-2 text-xs text-[#334155]">
                                                <input type="hidden" name="items[{{ $itemIndex }}][selected]" value="0">
                                                <input type="checkbox" name="items[{{ $itemIndex }}][selected]" value="1" @checked($itemSelected) class="rounded border-stone-300" data-ship-item-selected>
                                                <input type="hidden" name="items[{{ $itemIndex }}][order_item_id]" value="{{ $item->id }}">
                                                <span class="truncate">{{ $item->product_name ?? ('Item #'.$item->id) }}</span>
                                            </label>
                                            <input type="number" min="0" max="{{ $remainingQty }}" name="items[{{ $itemIndex }}][quantity]" value="{{ old('items.'.$itemIndex.'.quantity', $remainingQty) }}" class="w-20 rounded-lg border border-stone-200 px-2 py-1 text-sm" data-ship-item-qty>
                                        </div>
                                        @if ($needsCustoms)
                                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-2 {{ $itemSelected ? '' : 'hidden' }}" data-ship-item-customs>
                                                <input type="hidden" name="customs_clearance[commodities][{{ $itemIndex }}][order_item_id]" value="{{ $item->id }}">
                                                <input type="hidden" name="customs_clearance[commodities][{{ $itemIndex }}][quantity]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.quantity', old('items.'.$itemIndex.'.quantity', $remainingQty)) }}" data-customs-qty>
                                                <label class="block text-[11px] font-semibold text-amber-900">Customs description</label>
                                                <input type="text" name="customs_clearance[commodities][{{ $itemIndex }}][description]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.description', $item->product_name) }}" maxlength="450" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm" @disabled(! $itemSelected) data-customs-field>
                                                <div class="mt-1 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-amber-900">Customs value</label>
                                                        <input type="number" step="0.01" min="0.01" name="customs_clearance[commodities][{{ $itemIndex }}][customs_value][amount]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.customs_value.amount', $item->unit_price ?? '') }}" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm" @disabled(! $itemSelected) data-customs-field>
                                                        <input type="hidden" name="customs_clearance[commodities][{{ $itemIndex }}][customs_value][currency]" value="{{ $order->currency_code ?: 'USD' }}">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-amber-900">Weight (lb)</label>
                                                        <input type="number" step="0.01" min="0.01" name="customs_clearance[commodities][{{ $itemIndex }}][weight]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.weight', 1) }}" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm" @disabled(! $itemSelected) data-customs-field>
                                                        <input type="hidden" name="customs_clearance[commodities][{{ $itemIndex }}][weight_unit]" value="LB">
                                                    </div>
                                                </div>
                                                <div class="mt-1 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-amber-900">Made in</label>
                                                        <input type="text" name="customs_clearance[commodities][{{ $itemIndex }}][country_of_manufacture]" maxlength="2" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.country_of_manufacture', $originCountry) }}" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm uppercase" @disabled(! $itemSelected) data-customs-field>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-amber-900">HS code</label>
                                                        <input type="text" name="customs_clearance[commodities][{{ $itemIndex }}][harmonized_code]" value="{{ old('customs_clearance.commodities.'.$itemIndex.'.harmonized_code') }}" maxlength="18" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-sm" placeholder="Optional" @disabled(! $itemSelected) data-customs-field>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @error('items')
                            <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                        @enderror
                    @endif
                    @if ($needsCustoms)
                        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs font-semibold text-amber-950">Customs shipment totals ({{ $originCountry }} → {{ $destinationCountry }})</p>
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
                        <script>
                            (function () {
                                const root = document.currentScript.previousElementSibling?.closest('form')
                                    || document.currentScript.parentElement?.closest('form');
                                if (!root) return;
                                root.querySelectorAll('[data-ship-item-row]').forEach(function (row) {
                                    const checkbox = row.querySelector('[data-ship-item-selected]');
                                    const qty = row.querySelector('[data-ship-item-qty]');
                                    const customs = row.querySelector('[data-ship-item-customs]');
                                    const customsQty = row.querySelector('[data-customs-qty]');
                                    const fields = row.querySelectorAll('[data-customs-field]');
                                    function sync() {
                                        const selected = !!(checkbox && checkbox.checked);
                                        if (customs) customs.classList.toggle('hidden', !selected);
                                        fields.forEach(function (field) { field.disabled = !selected; });
                                        if (customsQty && qty) customsQty.value = qty.value || '';
                                    }
                                    if (checkbox) checkbox.addEventListener('change', sync);
                                    if (qty) qty.addEventListener('input', sync);
                                    sync();
                                });
                            })();
                        </script>
                    @endif
                    @if (! empty($fedExTradeDocuments) && count($fedExTradeDocuments))
                        <label class="mt-2 block text-xs font-semibold text-[#64748B]">Trade document (international)</label>
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
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">Label format</label>
                            <select name="label_format" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm">
                                <option value="PDF">PDF</option>
                                <option value="PNG">PNG</option>
                                <option value="ZPL">ZPL</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">Weight (lb)</label>
                            <input type="number" step="0.01" min="0.01" name="weight" value="{{ old('weight', 1) }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm">
                        </div>
                    </div>
                    <label class="mt-2 inline-flex items-center gap-2 text-xs text-[#475569]">
                        <input type="checkbox" name="residential" value="1" @checked(old('residential'))>
                        Residential delivery
                    </label>
                    <button type="submit" class="mt-3 inline-flex h-9 items-center rounded-lg border border-[#BBF7D0] bg-[#ECFDF5] px-3 text-xs font-semibold text-[#047857]" @disabled($rateServiceOptions->isEmpty())>Create label</button>
                </form>

                <form method="POST" action="{{ route('orders.fedex.return-label', $order) }}" class="rounded-xl border border-[#E2E8F0] bg-white p-4">
                    @csrf
                    <input type="hidden" name="carrier_account_id" value="{{ $fedExAccount->id }}">
                    <p class="text-sm font-semibold text-[#0F172A]">Return label</p>
                    <p class="mt-1 text-xs leading-5 text-[#64748B]">Select the items being returned, then create a FedEx return label.</p>
                    @error('fedex_return')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    @error('items')
                        <p class="mt-2 text-xs text-red-700">{{ $message }}</p>
                    @enderror
                    <label class="mt-2 block text-xs font-semibold text-[#64748B]">Return to</label>
                    <select name="origin_location_id" required class="mt-1 w-full rounded-lg border border-stone-200 bg-white px-2.5 py-2 text-sm">
                        @foreach ($fulfillmentLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @if (($order->items ?? collect())->isNotEmpty())
                        <p class="mt-3 text-xs font-semibold text-[#64748B]">Items to return</p>
                        <div class="mt-1 space-y-2">
                            @foreach ($order->items as $itemIndex => $item)
                                @php
                                    $returnSelected = old('items.'.$itemIndex.'.selected') === '1' || old('items.'.$itemIndex.'.selected') === true;
                                @endphp
                                <div class="flex items-center justify-between gap-2 rounded-lg border border-[#F1F5F9] px-2.5 py-2">
                                    <label class="flex min-w-0 flex-1 items-center gap-2 text-xs text-[#334155]">
                                        <input type="hidden" name="items[{{ $itemIndex }}][selected]" value="0">
                                        <input type="checkbox" name="items[{{ $itemIndex }}][selected]" value="1" @checked($returnSelected) class="rounded border-stone-300">
                                        <input type="hidden" name="items[{{ $itemIndex }}][order_item_id]" value="{{ $item->id }}">
                                        <span class="truncate">{{ $item->product_name ?? ('Item #'.$item->id) }}</span>
                                    </label>
                                    <input type="number" min="0" max="{{ (int) $item->quantity }}" name="items[{{ $itemIndex }}][quantity]" value="{{ old('items.'.$itemIndex.'.quantity', (int) $item->quantity) }}" class="w-20 rounded-lg border border-stone-200 px-2 py-1 text-sm">
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <input type="hidden" name="service_type" value="FEDEX_GROUND">
                    <button type="submit" class="mt-3 inline-flex h-9 items-center rounded-lg border border-[#E2E8F0] bg-white px-3 text-xs font-semibold text-[#334155]">Create return label</button>
                </form>
            </div>
        @endif

        @php
            $orderFedExShipments = ($order->shipments ?? collect())->filter(function ($shipment) {
                return filled(data_get($shipment->metadata, 'fedex.idempotency_key'))
                    || filled($shipment->tracking_number);
            });
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
                                    @if (filter_var(config('carriers.fedex.ops_ship_labels_enabled', false), FILTER_VALIDATE_BOOL)
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
                            @if (filled(data_get($shipment->metadata, 'fedex.public_tracking_token')))
                                <p class="mt-2 text-xs text-[#64748B]">
                                    Customer tracking:
                                    <a class="text-[#1D4ED8] underline" href="{{ route('public.fedex.tracking', ['storeSlug' => $selectedStore->slug ?? $order->store?->slug, 'token' => data_get($shipment->metadata, 'fedex.public_tracking_token')]) }}" target="_blank" rel="noopener">
                                        open page
                                    </a>
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (filter_var(config('carriers.fedex.ops_ship_labels_enabled', false), FILTER_VALIDATE_BOOL))
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
                            <input type="text" name="origin_country_code" maxlength="2" value="{{ old('origin_country_code', 'US') }}" class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm uppercase" placeholder="US">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#64748B]">Destination</label>
                            <input type="text" name="destination_country_code" maxlength="2" value="{{ old('destination_country_code', strtoupper((string) optional($order->addresses->firstWhere('type', 'shipping'))->country_code)) }}" required class="mt-1 w-full rounded-lg border border-stone-200 px-2.5 py-2 text-sm uppercase" placeholder="CA">
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
    </section>
@endif
