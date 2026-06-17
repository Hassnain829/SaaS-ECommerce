@if ($account->usesMerchantFedExDeveloperCredentials() && $account->hasMerchantFedExDeveloperCredentials())
    @php
        $fedExTestResult = session('fedex_test_result');
        $showFedExTestResult = is_array($fedExTestResult) && (int) ($fedExTestResult['account_id'] ?? 0) === (int) $account->id;
    @endphp

    <details class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm">
        <summary class="cursor-pointer font-semibold text-[#0F172A]">FedEx testing tools</summary>
        <p class="mt-2 text-xs text-[#64748B]">Sandbox checks using your merchant FedEx credentials. These tools do not buy labels, create shipments, charge postage, or change checkout totals.</p>

        @if ($showFedExTestResult)
            @php
                $fedExTestSuccess = (bool) ($fedExTestResult['success'] ?? false);
                $fedExTestFailureKind = $fedExTestResult['failure_kind'] ?? null;
                $fedExTestBoxClass = $fedExTestSuccess
                    ? 'border-emerald-200 bg-emerald-50'
                    : ($fedExTestFailureKind === 'fedex_api' ? 'border-amber-200 bg-amber-50' : 'border-red-200 bg-red-50');
                $fedExTestTextClass = $fedExTestSuccess
                    ? 'text-emerald-900'
                    : ($fedExTestFailureKind === 'fedex_api' ? 'text-amber-900' : 'text-red-900');
            @endphp
            <div class="mt-4 rounded-xl border {{ $fedExTestBoxClass }} px-4 py-3">
                <p class="font-semibold text-[#0F172A]">{{ $fedExTestResult['label'] ?? 'FedEx test result' }}</p>
                <p class="mt-1 text-sm {{ $fedExTestTextClass }}">{{ $fedExTestResult['message'] ?? '' }}</p>
                @if (! empty($fedExTestResult['input_summary']))
                    <dl class="mt-3 space-y-1 text-xs text-[#475569]">
                        @foreach ($fedExTestResult['input_summary'] as $key => $value)
                            <div><span class="font-semibold capitalize text-[#0F172A]">{{ str_replace('_', ' ', $key) }}:</span> {{ $value }}</div>
                        @endforeach
                    </dl>
                @endif

                @if (($fedExTestResult['tool'] ?? '') === 'address_validation' && ! empty($fedExTestResult['presentation']['resolved_addresses']))
                    <ul class="mt-3 space-y-2 text-xs text-[#475569]">
                        @foreach ($fedExTestResult['presentation']['resolved_addresses'] as $resolved)
                            <li class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-2">
                                {{ collect([$resolved['street'] ?? null, $resolved['city'] ?? null, $resolved['state'] ?? null, $resolved['postal_code'] ?? null, $resolved['country_code'] ?? null])->filter()->implode(', ') }}
                                @if (! empty($resolved['classification'])) · {{ $resolved['classification'] }} @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (($fedExTestResult['tool'] ?? '') === 'service_availability' && ! empty($fedExTestResult['presentation']['services']))
                    <ul class="mt-3 space-y-1 text-xs text-[#475569]">
                        @foreach ($fedExTestResult['presentation']['services'] as $service)
                            <li>{{ $service['service_name'] ?? $service['service_type'] ?? 'Service' }}@if (! empty($service['packaging_name'] ?? $service['packaging_type'] ?? null)) · {{ $service['packaging_name'] ?? $service['packaging_type'] }} @endif</li>
                        @endforeach
                    </ul>
                @endif

                @if (($fedExTestResult['tool'] ?? '') === 'rate_quote')
                    <p class="mt-2 text-xs text-[#64748B]">This is a FedEx test quote only. It does not create a shipment, buy a label, charge FedEx postage, or change checkout totals.</p>
                    @if (! empty($fedExTestResult['presentation']['rates']))
                        <ul class="mt-3 space-y-1 text-xs text-[#475569]">
                            @foreach ($fedExTestResult['presentation']['rates'] as $rate)
                                <li>{{ $rate['service_name'] ?? $rate['service_type'] ?? 'Service' }} · {{ $rate['currency'] ?? 'USD' }} {{ number_format((float) ($rate['amount'] ?? 0), 2) }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif

                <details class="mt-3 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-xs">
                    <summary class="cursor-pointer font-semibold text-[#0F172A]">View redacted request/response</summary>
                    @if (! empty($fedExTestResult['request_summary']))
                        <p class="mt-2 font-semibold text-[#0F172A]">Request</p>
                        <ul class="mt-1 space-y-0.5 text-[#64748B]">
                            @foreach ($fedExTestResult['request_summary'] as $key => $value)
                                @if (! is_array($value))
                                    <li>{{ str($key)->replace('_', ' ')->title() }}: {{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($fedExTestResult['response_summary']))
                        <p class="mt-2 font-semibold text-[#0F172A]">Response</p>
                        <ul class="mt-1 space-y-0.5 text-[#64748B]">
                            @foreach ($fedExTestResult['response_summary'] as $key => $value)
                                @if (! is_array($value))
                                    <li>{{ str($key)->replace('_', ' ')->title() }}: {{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</li>
                                @elseif ($key === 'output_summary')
                                    <li>{{ str($key)->replace('_', ' ')->title() }}:
                                        <ul class="ml-4 mt-1 space-y-0.5">
                                            @foreach ($value as $nestedKey => $nestedValue)
                                                @if (! is_array($nestedValue))
                                                    <li>{{ str($nestedKey)->replace('_', ' ')->title() }}: {{ $nestedValue }}</li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </li>
                                @elseif ($key === 'errors')
                                    <li>{{ str($key)->replace('_', ' ')->title() }}:
                                        <ul class="ml-4 mt-1 space-y-0.5">
                                            @foreach ($value as $error)
                                                @if (is_array($error))
                                                    <li>{{ $error['code'] ?? 'Error' }}: {{ $error['message'] ?? '' }}</li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </details>
            </div>
        @endif

        @if ($canManageShipping ?? false)
            <div class="mt-4 space-y-3">
                <details class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-3">
                    <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Address check</summary>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test-address', $account) }}" class="shipping-submit-form mt-3 space-y-3">
                        @csrf
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Address line 1</span><input name="address_line1" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="100 Main St"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Address line 2</span><input name="address_line2" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">City</span><input name="city" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">State</span><input name="state" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="TX"></label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Postal code</span><input name="postal_code" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Country</span><input name="country_code" required value="US" maxlength="2" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-[#475569]"><input type="checkbox" name="residential" value="1" class="rounded border-[#CBD5E1]"> Residential address</label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shipping-submit-btn">Run address check</button>
                    </form>
                </details>

                <details class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-3">
                    <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Service availability check</summary>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account) }}" class="shipping-submit-form mt-3 space-y-3">
                        @csrf
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Origin location</span>
                            <select name="origin_location_id" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                <option value="">Select origin</option>
                                @foreach ($locations as $location)
                                    @php($readiness = $originReadinessByLocationId[$location->id] ?? null)
                                    <option value="{{ $location->id }}" @disabled(! ($readiness?->ready ?? false)) @selected($fedExOriginId === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination country</span><input name="destination_country" required value="US" maxlength="2" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination ZIP</span><input name="destination_postal_code" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination state</span><input name="destination_state" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="TX"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination city</span><input name="destination_city" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="Dallas"></label>
                        </div>
                        <p class="text-xs text-[#64748B]">US and Canada destinations require country, postal code, state/province, and city.</p>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shipping-submit-btn" @disabled(! ($hasCarrierReadyOrigin ?? false))>Check service availability</button>
                    </form>
                </details>

                <details class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-3">
                    <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Rate quote test</summary>
                    <p class="mt-2 text-xs text-[#64748B]">FedEx test quote only — no shipment, label, postage charge, or checkout total change.</p>
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test-rate-quote', $account) }}" class="shipping-submit-form mt-3 space-y-3">
                        @csrf
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Origin location</span>
                            <select name="origin_location_id" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                <option value="">Select origin</option>
                                @foreach ($locations as $location)
                                    @php($readiness = $originReadinessByLocationId[$location->id] ?? null)
                                    <option value="{{ $location->id }}" @disabled(! ($readiness?->ready ?? false)) @selected($fedExOriginId === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination country</span><input name="destination_country" required value="US" maxlength="2" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination ZIP</span><input name="destination_postal_code" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-4">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Weight (lb)</span><input name="weight_value" type="number" step="0.01" min="0.01" value="1" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">L (in)</span><input name="length" type="number" step="0.01" min="0.01" value="9" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">W (in)</span><input name="width" type="number" step="0.01" min="0.01" value="6" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">H (in)</span><input name="height" type="number" step="0.01" min="0.01" value="2" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-[#475569]"><input type="checkbox" name="residential" value="1" class="rounded border-[#CBD5E1]"> Residential destination</label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shipping-submit-btn" @disabled(! ($hasCarrierReadyOrigin ?? false))>Get FedEx test quote</button>
                    </form>
                </details>
            </div>
        @endif
    </details>
@endif
