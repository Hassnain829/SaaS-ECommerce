@if ($account->canUseFedExApiChecks())
    @php
        $fedExTestResult = session('fedex_test_result');
        $showFedExTestResult = is_array($fedExTestResult) && (int) ($fedExTestResult['account_id'] ?? 0) === (int) $account->id;
    @endphp

    <details class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm">
        <summary class="cursor-pointer font-semibold text-[#0F172A]">{{ $account->usesFedExIntegratorProvider() ? 'FedEx validation tools' : 'FedEx testing tools' }}</summary>
        <p class="mt-2 text-xs text-[#64748B]">Sandbox checks using your integrator child credentials. These tools validate FedEx API access for submission evidence. They do not buy labels for checkout, charge postage, or change checkout totals unless label generation is explicitly enabled.</p>

        @if ($showFedExTestResult)
            @php
                $fedExResultKind = $fedExTestResult['result_kind'] ?? (($fedExTestResult['success'] ?? false) ? 'success' : 'failure');
                $fedExTestBoxClass = match ($fedExResultKind) {
                    'success' => 'border-emerald-200 bg-emerald-50',
                    'warning', 'fedex_api', 'fedex_authorization_blocked' => 'border-amber-200 bg-amber-50',
                    default => 'border-red-200 bg-red-50',
                };
                $fedExTestTextClass = match ($fedExResultKind) {
                    'success' => 'text-emerald-900',
                    'warning', 'fedex_api', 'fedex_authorization_blocked' => 'text-amber-900',
                    default => 'text-red-900',
                };
            @endphp
            <div class="mt-4 rounded-xl border {{ $fedExTestBoxClass }} px-4 py-3">
                <p class="font-semibold text-[#0F172A]">{{ $fedExTestResult['label'] ?? 'FedEx test result' }}</p>
                <p class="mt-1 text-sm {{ $fedExTestTextClass }}">{{ $fedExTestResult['message'] ?? '' }}</p>
                @if (! empty($fedExTestResult['support_summary']))
                    <div class="mt-3 rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-amber-950">
                        <p class="font-semibold text-[#0F172A]">FedEx support summary</p>
                        <pre id="fedex-support-summary-{{ $account->id }}" class="mt-2 whitespace-pre-wrap font-sans">{{ $fedExTestResult['support_summary'] }}</pre>
                        <button type="button" class="mt-2 rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-3 py-1.5 text-xs font-semibold text-[#475569]" onclick="navigator.clipboard.writeText(document.getElementById('fedex-support-summary-{{ $account->id }}').innerText)">Copy support summary</button>
                    </div>
                @endif
                @if (! empty($fedExTestResult['input_summary']))
                    <dl class="mt-3 space-y-1 text-xs text-[#475569]">
                        @foreach ($fedExTestResult['input_summary'] as $key => $value)
                            <div><span class="font-semibold capitalize text-[#0F172A]">{{ str_replace('_', ' ', $key) }}:</span> {{ $value }}</div>
                        @endforeach
                    </dl>
                @endif

                @if (($fedExTestResult['tool'] ?? '') === 'address_validation')
                    @if (! empty($fedExTestResult['presentation']['resolved_addresses']))
                        <ul class="mt-3 space-y-2 text-xs text-[#475569]">
                            @foreach ($fedExTestResult['presentation']['resolved_addresses'] as $resolved)
                                <li class="rounded-lg border border-emerald-200 bg-white px-3 py-2">
                                    {{ collect([$resolved['street'] ?? null, $resolved['city'] ?? null, $resolved['state'] ?? null, $resolved['postal_code'] ?? null, $resolved['country_code'] ?? null])->filter()->implode(', ') }}
                                    @if (! empty($resolved['classification'])) · {{ $resolved['classification'] }} @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($fedExTestResult['presentation']['warnings']))
                        <ul class="mt-3 space-y-1 text-xs text-amber-900">
                            @foreach ($fedExTestResult['presentation']['warnings'] as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($fedExTestResult['presentation']['ignored_suggestions']))
                        <p class="mt-3 text-xs font-semibold text-amber-900">Ignored suggestions outside requested country</p>
                        <ul class="mt-1 space-y-1 text-xs text-amber-800">
                            @foreach ($fedExTestResult['presentation']['ignored_suggestions'] as $ignored)
                                <li>{{ collect([$ignored['street'] ?? null, $ignored['city'] ?? null, $ignored['state'] ?? null, $ignored['postal_code'] ?? null, $ignored['country_code'] ?? null])->filter()->implode(', ') }}</li>
                            @endforeach
                        </ul>
                    @endif
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
                    @if (($fedExTestResult['result_kind'] ?? '') === 'fedex_authorization_blocked')
                        <p class="mt-2 text-xs font-semibold text-amber-900">FedEx authorization blocked — treat as an entitlement blocker for validation submission, not a local payload bug.</p>
                    @endif
                    @if (! empty($fedExTestResult['presentation']['rates']))
                        <ul class="mt-3 space-y-1 text-xs text-[#475569]">
                            @foreach ($fedExTestResult['presentation']['rates'] as $rate)
                                <li>{{ $rate['service_name'] ?? $rate['service_type'] ?? 'Service' }} · {{ $rate['currency'] ?? 'USD' }} {{ number_format((float) ($rate['amount'] ?? 0), 2) }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif

                @if (in_array(($fedExTestResult['tool'] ?? ''), ['ship_validate', 'ship_label', 'ship_cancel'], true))
                    <dl class="mt-3 space-y-1 text-xs text-[#475569]">
                        @foreach (['test_case', 'label_format', 'tracking_number_last4'] as $field)
                            @if (! empty($fedExTestResult['presentation'][$field] ?? $fedExTestResult['input_summary'][$field] ?? null))
                                <div><span class="font-semibold capitalize text-[#0F172A]">{{ str_replace('_', ' ', $field) }}:</span> {{ $fedExTestResult['presentation'][$field] ?? $fedExTestResult['input_summary'][$field] }}</div>
                            @endif
                        @endforeach
                        @if (! empty($fedExTestResult['presentation']['label_saved']))
                            <div><span class="font-semibold text-[#0F172A]">Label saved:</span> yes (redacted in exports except file artifact)</div>
                        @endif
                    </dl>
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
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Address line 1</span><input name="address_line1" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" value="15 W 18TH ST FL 7"></label>
                        <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Address line 2</span><input name="address_line2" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">City</span><input name="city" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" value="NEW YORK"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">State</span><input name="state" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" value="NY"></label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Postal code</span><input name="postal_code" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" value="100114624"></label>
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
                    <p class="mt-2 text-xs text-[#64748B]">Sandbox quote only — no shipment, label, postage charge, or checkout total change.</p>
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
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination ZIP</span><input name="destination_postal_code" required value="60601" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination state</span><input name="destination_state" required value="IL" maxlength="2" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase" placeholder="IL"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Destination city</span><input name="destination_city" required value="CHICAGO" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase" placeholder="CHICAGO"></label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Service type</span>
                                <select name="service_type" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                    <option value="FEDEX_GROUND" selected>FEDEX_GROUND</option>
                                    <option value="FEDEX_2_DAY">FEDEX_2_DAY</option>
                                    <option value="STANDARD_OVERNIGHT">STANDARD_OVERNIGHT</option>
                                </select>
                            </label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Packaging type</span>
                                <select name="packaging_type" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                    <option value="YOUR_PACKAGING" selected>YOUR_PACKAGING</option>
                                    <option value="FEDEX_BOX">FEDEX_BOX</option>
                                    <option value="FEDEX_ENVELOPE">FEDEX_ENVELOPE</option>
                                </select>
                            </label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-4">
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Weight (lb)</span><input name="weight_value" type="number" step="0.01" min="0.01" value="1" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">L (in)</span><input name="length" type="number" step="0.01" min="0.01" value="9" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">W (in)</span><input name="width" type="number" step="0.01" min="0.01" value="6" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">H (in)</span><input name="height" type="number" step="0.01" min="0.01" value="2" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm"></label>
                        </div>
                        <p class="text-xs text-[#64748B]">US and Canada destinations require country, postal code, state/province, and city.</p>
                        <label class="inline-flex items-center gap-2 text-xs text-[#475569]"><input type="checkbox" name="residential" value="1" class="rounded border-[#CBD5E1]"> Residential destination</label>
                        <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shipping-submit-btn" @disabled(! ($hasCarrierReadyOrigin ?? false))>Get FedEx test quote</button>
                    </form>
                </details>

                @if ($account->usesFedExIntegratorProvider())
                    <details class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Ship API validation</summary>
                        <p class="mt-2 text-xs text-[#64748B]">Sandbox Ship API tools for FedEx integrator validation evidence. Label generation requires FEDEX_SHIP_SANDBOX_LABEL_GENERATION_ENABLED or FEDEX_SHIP_EVIDENCE_ENABLED.</p>
                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test-ship-validate', $account) }}" class="shipping-submit-form mt-3 space-y-3">
                            @csrf
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Test case</span>
                                <select name="test_case" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                    @foreach (($fedExShipTestCases ?? []) as $key => $fixture)
                                        <option value="{{ $key }}">{{ $fixture['label'] ?? $key }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shipping-submit-btn">Run ship validate</button>
                        </form>

                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test-ship-label', $account) }}" class="shipping-submit-form mt-4 space-y-3 border-t border-[#F1F5F9] pt-4">
                            @csrf
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Test case</span>
                                <select name="test_case" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                    @foreach (($fedExShipTestCases ?? []) as $key => $fixture)
                                        <option value="{{ $key }}">{{ $fixture['label'] ?? $key }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Label format</span>
                                <select name="label_format" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                                    <option value="PDF">PDF</option>
                                    <option value="PNG">PNG</option>
                                    <option value="ZPL">ZPL</option>
                                </select>
                            </label>
                            <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shipping-submit-btn">Create sandbox label</button>
                        </form>

                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.cancel-test-shipment', $account) }}" class="shipping-submit-form mt-4 space-y-3 border-t border-[#F1F5F9] pt-4">
                            @csrf
                            <label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">Tracking number</span><input name="tracking_number" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm" placeholder="From prior label test"></label>
                            <button type="submit" class="rounded-lg border border-[#CBD5E1] bg-white px-4 py-2 text-sm font-semibold text-[#475569] shipping-submit-btn">Cancel test shipment</button>
                        </form>
                    </details>
                @endif
            </div>
        @endif
    </details>
@endif
