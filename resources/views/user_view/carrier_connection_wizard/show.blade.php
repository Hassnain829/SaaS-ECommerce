@extends('layouts.user.user-sidebar')

@section('title', 'Connect '.$carrierCard['name'].' | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Connect {{ $carrierCard['name'] }}</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">{{ $carrierCard['summary'] }}</p>
        </div>
        <a href="{{ route('shipping.carriers.connect.index') }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569]">Back to carriers</a>
    </header>
@endsection

@section('content')
    @php
        $originLocationId = (int) ($originLocationId ?? request('origin_location_id', $account?->defaultOriginLocationId()));
        $fedExFormValues = [
            'display_name' => old('display_name'),
            'provider_account_number' => old('provider_account_number'),
            'company_name' => old('company_name', $fedExPrefill['company_name'] ?? ''),
            'contact_name' => old('contact_name', $fedExPrefill['contact_name'] ?? ''),
            'address_line1' => old('address_line1', $fedExPrefill['address_line1'] ?? ''),
            'address_line2' => old('address_line2', $fedExPrefill['address_line2'] ?? ''),
            'city' => old('city', $fedExPrefill['city'] ?? ''),
            'state' => old('state', $fedExPrefill['state'] ?? ''),
            'postal_code' => old('postal_code', $fedExPrefill['postal_code'] ?? ''),
            'country_code' => old('country_code', $fedExPrefill['country_code'] ?? \App\Support\CarrierCountryOptions::defaultFedExCountry($fedExOriginLocation?->country_code)),
            'phone' => old('phone'),
            'email' => old('email'),
        ];
        $steps = ['origin' => 'Ship-from location', 'ownership' => 'Account type', 'fedex_details' => 'FedEx details', 'test' => 'Connection test'];
    @endphp

    <div class="mx-auto max-w-[960px] space-y-6">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @if ($carrierCard['blocked'] ?? false)
            <section class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-950">
                FedEx setup is blocked by carrier account validation. Contact FedEx support or use manual delivery for now.
            </section>
        @endif

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap gap-2">
                @foreach ($steps as $stepKey => $stepLabel)
                    @continue(! in_array($stepKey, ['origin', 'ownership', 'fedex_details', 'test'], true))
                    @continue($stepKey === 'fedex_details' && $carrier !== 'fedex')
                    @continue($stepKey === 'ownership' && in_array($carrier, ['manual', 'fedex'], true))
                    <span class="rounded-full {{ $step === $stepKey ? 'bg-[#0052CC] text-white' : 'bg-[#F1F5F9] text-[#64748B]' }} px-3 py-1 text-xs font-bold">{{ $stepLabel }}</span>
                @endforeach
            </div>

            @if ($step === 'origin')
                <h2 class="mt-5 text-xl font-poppins font-semibold text-[#0F172A]">Step 2 — Choose ship-from location</h2>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">This is where orders ship from. Carrier rates and labels use this fulfillment location — not your store business address.</p>

                @if ($originOptions->isEmpty())
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        Add a fulfillment location first.
                        <a href="{{ route('settings.locations.index') }}" class="font-semibold underline">Manage fulfillment locations</a>
                    </div>
                @else
                    <form method="POST" action="{{ route('shipping.carriers.connect.origin', $carrier) }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="carrier_account_id" value="{{ $account?->id }}">
                        <div class="space-y-3">
                            @foreach ($originOptions as $option)
                                @php($location = $option['location'])
                                @php($readiness = $option['readiness'])
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 {{ $readiness->ready ? '' : 'opacity-80' }}">
                                    <input type="radio" name="origin_location_id" value="{{ $location->id }}" class="mt-1" @checked($originLocationId === $location->id) @disabled(! $readiness->ready) required>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-[#0F172A]">{{ $location->name }}</span>
                                        <span class="mt-1 block text-sm text-[#64748B]">{{ $readiness->displayAddress ?: 'No ship-from address saved' }}</span>
                                        <span class="mt-2 inline-flex rounded-full {{ $readiness->ready ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#FEF2F2] text-[#991B1B]' }} px-2 py-0.5 text-xs font-bold">{{ $readiness->badgeLabel }}</span>
                                        @if (! $readiness->ready)
                                            <span class="mt-2 block text-xs text-[#64748B]">{{ $readiness->merchantMessage }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <button class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Continue</button>
                    </form>
                @endif
            @elseif ($step === 'ownership')
                <h2 class="mt-5 text-xl font-poppins font-semibold text-[#0F172A]">Step 3 — Choose account setup type</h2>
                <form method="POST" action="{{ route('shipping.carriers.connect.ownership', $carrier) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="origin_location_id" value="{{ $originLocationId }}">
                    <input type="hidden" name="carrier_account_id" value="{{ $account?->id }}">
                    @if ($carrier === 'manual')
                        <label class="space-y-1 block">
                            <span class="text-xs font-semibold text-[#64748B]">Delivery type</span>
                            <select name="carrier_id" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" required>
                                @foreach ($carriers as $carrierOption)
                                    @continue(in_array($carrierOption->code, ['fedex', 'usps', 'ups', 'dhl'], true))
                                    <option value="{{ $carrierOption->id }}">{{ $carrierOption->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="space-y-1 block">
                            <span class="text-xs font-semibold text-[#64748B]">Countries/regions served</span>
                            <input name="supported_countries" value="{{ old('supported_countries') }}" placeholder="US, CA" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            <span class="text-xs text-[#64748B]">Leave blank if not limited to specific countries.</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-[#475569]"><input type="checkbox" name="enabled_for_checkout" value="1" @checked(old('enabled_for_checkout', true)) class="rounded border-[#CBD5E1]"> Available for checkout</label>
                    @endif
                    <label class="space-y-1 block">
                        <span class="text-xs font-semibold text-[#64748B]">Display name</span>
                        <input name="display_name" value="{{ old('display_name') }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                    </label>
                    <div class="space-y-3">
                        @foreach ($ownershipOptions as $option)
                            <label class="flex gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 {{ ($option['disabled'] ?? false) ? 'opacity-70' : '' }}">
                                <input type="radio" name="ownership_mode" value="{{ $option['value'] }}" class="mt-1" @disabled($option['disabled'] ?? false) required>
                                <span>
                                    <span class="block font-semibold text-[#0F172A]">{{ $option['label'] }}</span>
                                    <span class="mt-1 block text-sm text-[#64748B]">{{ $option['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <button class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Save and continue</button>
                </form>
            @elseif ($step === 'fedex_details')
                <h2 class="mt-5 text-xl font-poppins font-semibold text-[#0F172A]">Step 3 — Enter FedEx account details</h2>
                <p class="mt-2 text-sm leading-6 text-[#64748B]">This should match your FedEx account records. FedEx may reject setup if the account number, contact, or billing address does not match.</p>
                <div class="mt-4 space-y-2 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#475569]">
                    <p><span class="font-semibold text-[#0F172A]">Billing:</span> FedEx billing stays between you and FedEx. This platform does not pay FedEx charges or buy labels for you.</p>
                    <p><span class="font-semibold text-[#0F172A]">Labels:</span> Labels are not enabled in this phase.</p>
                    <p><span class="font-semibold text-[#0F172A]">Rates:</span> Test quotes are for setup verification only and do not change checkout totals.</p>
                    <p><span class="font-semibold text-[#0F172A]">Account match:</span> Account name and address must match your FedEx records.</p>
                </div>
                @if (($fedExOriginLocation ?? null) && ($canManageShipping ?? false))
                    <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 text-sm text-[#475569]">
                        <p class="font-semibold text-[#0F172A]">Ship-from location: {{ $fedExOriginLocation->name }}</p>
                        <p class="mt-1">Use this only if your FedEx account address matches your ship-from location.</p>
                        <a href="{{ route('shipping.carriers.connect.show', ['carrier' => 'fedex', 'step' => 'fedex_details', 'origin_location_id' => $originLocationId, 'prefill_from_origin' => 1]) }}" class="mt-2 inline-flex text-sm font-semibold text-[#1D4ED8]">Use selected ship-from location address</a>
                    </div>
                @endif
                <form method="POST" action="{{ route('shipping.carriers.connect.fedex.details') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="origin_location_id" value="{{ $originLocationId }}">
                    <label class="space-y-1 sm:col-span-2"><span class="text-xs font-semibold text-[#64748B]">Account nickname</span><input name="display_name" value="{{ $fedExFormValues['display_name'] }}" placeholder="Main FedEx account" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                    <label class="space-y-1 sm:col-span-2"><span class="text-xs font-semibold text-[#64748B]">FedEx account number</span><input name="provider_account_number" value="{{ $fedExFormValues['provider_account_number'] }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" autocomplete="off" inputmode="numeric"></label>
                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Account holder / company name</span><input name="company_name" value="{{ $fedExFormValues['company_name'] }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Contact name</span><input name="contact_name" value="{{ $fedExFormValues['contact_name'] }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                    <label class="space-y-1 sm:col-span-2"><span class="text-xs font-semibold text-[#64748B]">Address line 1</span><input name="address_line1" value="{{ $fedExFormValues['address_line1'] }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                    <label class="space-y-1 sm:col-span-2"><span class="text-xs font-semibold text-[#64748B]">Address line 2</span><input name="address_line2" value="{{ $fedExFormValues['address_line2'] }}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">City</span><input name="city" value="{{ $fedExFormValues['city'] }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">State / region</span><input name="state" value="{{ $fedExFormValues['state'] }}" required maxlength="2" pattern="[A-Za-z]{2}" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm uppercase" placeholder="TX"></label>
                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Postal code</span><input name="postal_code" value="{{ $fedExFormValues['postal_code'] }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm" placeholder="75002"></label>
                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Country</span>
                        <select name="country_code" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">
                            @foreach (($fedExCountryOptions ?? \App\Support\CarrierCountryOptions::fedExOptions()) as $code => $label)
                                <option value="{{ $code }}" @selected((string) $fedExFormValues['country_code'] === (string) $code)>{{ $label }} ({{ $code }})</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1"><span class="text-xs font-semibold text-[#64748B]">Contact phone</span><input name="phone" value="{{ $fedExFormValues['phone'] }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                    <label class="space-y-1 sm:col-span-2"><span class="text-xs font-semibold text-[#64748B]">Contact email</span><input name="email" type="email" value="{{ $fedExFormValues['email'] }}" required class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm"></label>
                    <div class="sm:col-span-2"><button class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Save FedEx account</button></div>
                </form>
            @elseif ($step === 'test' && $account)
                <h2 class="mt-5 text-xl font-poppins font-semibold text-[#0F172A]">Step 4 — Connection readiness</h2>
                @if ($presenter)
                    <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-4 text-sm text-[#475569]">
                        <p><span class="font-semibold text-[#0F172A]">Ownership:</span> {{ $presenter->ownershipLabel() }}</p>
                        @if ($presenter->maskedAccountNumberLabel())
                            <p class="mt-2"><span class="font-semibold text-[#0F172A]">Account:</span> {{ $presenter->maskedAccountNumberLabel() }}</p>
                        @endif
                        <p class="mt-2"><span class="font-semibold text-[#0F172A]">Billing:</span> {{ $presenter->billingLabel() }}</p>
                        <p class="mt-2"><span class="font-semibold text-[#0F172A]">Status:</span> {{ $presenter->connectionStatusLabel() }}</p>
                        <p class="mt-2">{{ $presenter->merchantStatusLabel() }}</p>
                        <ul class="mt-3 space-y-1">
                            @foreach ($presenter->merchantCapabilityLabels() as $capabilityLabel)
                                <li>{{ $capabilityLabel }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($canManageShipping && ! $account->isManualProvider())
                    <form method="POST" action="{{ route('shipping.carriers.connect.test', $carrier) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="carrier_account_id" value="{{ $account->id }}">
                        <button class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Run connection check</button>
                    </form>
                @endif
                <a href="{{ route('shippingAutomation') }}" class="mt-4 inline-flex text-sm font-semibold text-[#1D4ED8]">Return to Shipping &amp; Delivery</a>
            @endif
        </section>
    </div>
@endsection
