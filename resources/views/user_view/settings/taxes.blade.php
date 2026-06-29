@extends('layouts.user.user-sidebar')

@section('title', 'Taxes | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 md:px-8">
        <button id="sidebarToggle" onclick="openSidebar()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm md:hidden" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>
        <div>
            <h1 class="font-poppins text-lg font-semibold md:text-xl">Taxes</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Configure platform checkout tax for this store.</p>
        </div>
        <a href="{{ route('generalSettings') }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">General settings</a>
    </header>
@endsection

@section('content')
    @php
        use App\Support\Tax\TaxCountryCatalog;

        $activeRatesCount = $activeRatesCount ?? $taxRates->where('is_active', true)->count();
        $openCreateRateForm = $openCreateRateForm ?? false;
        $editingRateId = $editingRateId ?? 0;
        $createErrorBag = 'createTaxRate';
        $createHasErrors = $errors->getBag($createErrorBag)->any();
        $editRateErrorSummary = null;
        foreach ($errors->getBags() as $bagName => $bag) {
            if (str_starts_with($bagName, 'updateTaxRate_') && $bag->any()) {
                $editRateErrorSummary = $bag->first();
                break;
            }
        }
        $hasRateMutationErrors = $createHasErrors || $editRateErrorSummary !== null;
        $rateMutationSummary = $createHasErrors ? $errors->getBag($createErrorBag)->first() : $editRateErrorSummary;
        $createRateUrl = route('settings.taxes.index', ['create_rate' => 1]);
        $createValues = $openCreateRateForm ? [
            'name' => $createHasErrors ? old('name') : '',
            'country_code' => $createHasErrors ? old('country_code') : '',
            'region_code' => $createHasErrors ? old('region_code') : '',
            'rate_percent' => $createHasErrors ? old('rate_percent') : '',
            'priority' => $createHasErrors ? old('priority', 100) : 100,
            'is_active' => $createHasErrors ? old('is_active', true) : true,
        ] : [];
    @endphp

    <div class="mx-auto max-w-[1280px] space-y-6">
        @include('user_view.partials.flash_success')

        @if ($hasRateMutationErrors && $rateMutationSummary)
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                {{ $rateMutationSummary }}
            </div>
        @elseif ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- SECTION 1: Tax status --}}
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Tax status</p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Platform tax</dt>
                    <dd class="mt-1 font-semibold text-[#0F172A]">{{ $taxSetting->enabled ? 'Active' : 'Disabled' }}</dd>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Product prices</dt>
                    <dd class="mt-1 font-semibold text-[#0F172A]">{{ $taxSetting->prices_include_tax ? 'Tax included' : 'Tax added at checkout' }}</dd>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">New products</dt>
                    <dd class="mt-1 font-semibold text-[#0F172A]">{{ $taxSetting->default_product_taxable ? 'Taxable by default' : 'Not taxable by default' }}</dd>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Shipping</dt>
                    <dd class="mt-1 font-semibold text-[#0F172A]">{{ $taxSetting->shipping_taxable ? 'Taxable' : 'Not taxable' }}</dd>
                </div>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Active rates</dt>
                    <dd class="mt-1 font-semibold text-[#0F172A]">{{ $activeRatesCount }}</dd>
                </div>
            </dl>

            <div class="mt-4 space-y-2 text-sm leading-relaxed text-[#475569]">
                @if ($taxSetting->enabled)
                    <p>Platform checkout uses the customer&apos;s shipping country and region to match an active tax rate.</p>
                @else
                    <p>Configured rates are saved, but platform checkout will not apply calculated tax.</p>
                @endif
                <p class="text-xs text-[#64748B]">External checkouts continue to send and preserve their own tax totals.</p>
            </div>

            @if ($taxSetting->enabled && $activeRatesCount === 0)
                <div class="mt-4 flex flex-col gap-3 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-[#92400E]">Tax is active, but no active rates are configured. Eligible checkouts may calculate zero tax.</p>
                    @if ($canManageTax)
                        <a href="{{ $createRateUrl }}" class="inline-flex h-10 shrink-0 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white" data-open-tax-rate-create>Add tax rate</a>
                    @endif
                </div>
            @elseif (! $taxSetting->enabled && $activeRatesCount > 0)
                <div class="mt-4 flex flex-col gap-3 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-[#1E3A8A]">Rates are saved but currently inactive because platform tax is disabled.</p>
                    @if ($canManageTax)
                        <a href="#tax-behavior-settings" class="inline-flex h-10 shrink-0 items-center rounded-lg border border-[#93C5FD] bg-white px-4 text-sm font-semibold text-[#1D4ED8]">Enable tax</a>
                    @endif
                </div>
            @endif
        </section>

        {{-- SECTION 2: Tax behavior --}}
        <section id="tax-behavior-settings" class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Tax behavior</p>
            <h2 class="mt-1 font-[Poppins] text-xl font-semibold text-[#0F172A]">Platform checkout settings</h2>
            <p class="mt-2 text-sm text-[#64748B]">These settings affect new platform checkouts. Historical order snapshots stay unchanged.</p>

            @if ($canManageTax)
                <form method="POST" action="{{ route('settings.taxes.update') }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="calculation_address" value="shipping">

                    <label class="flex items-start gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <input type="checkbox" name="enabled" value="1" class="mt-1" @checked(old('enabled', $taxSetting->enabled))>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">Enable platform tax calculation</span>
                            <span class="mt-1 block text-sm text-[#64748B]">Applies configured rates to eligible platform checkouts.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <input type="checkbox" name="prices_include_tax" value="1" class="mt-1" @checked(old('prices_include_tax', $taxSetting->prices_include_tax))>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">Product prices include tax</span>
                            <span class="mt-1 block text-sm text-[#64748B]">Catalog prices already include tax when this is on.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <input type="checkbox" name="default_product_taxable" value="1" class="mt-1" @checked(old('default_product_taxable', $taxSetting->default_product_taxable))>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">New products are taxable by default</span>
                            <span class="mt-1 block text-sm text-[#64748B]">Applies to new products created after you save this setting.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <input type="checkbox" name="shipping_taxable" value="1" class="mt-1" @checked(old('shipping_taxable', $taxSetting->shipping_taxable))>
                        <span>
                            <span class="block text-sm font-semibold text-[#0F172A]">Charge tax on shipping</span>
                            <span class="mt-1 block text-sm text-[#64748B]">Adds shipping tax when a matching rate applies.</span>
                        </span>
                    </label>

                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                        <p class="text-sm font-semibold text-[#0F172A]">Calculation address</p>
                        <p class="mt-1 text-sm text-[#64748B]">Customer shipping address</p>
                    </div>

                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-5 text-sm font-semibold text-white hover:bg-[#0047B3]">Save tax settings</button>
                </form>
            @else
                <div class="mt-6 space-y-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-sm text-[#475569]">
                    <p><span class="font-semibold text-[#0F172A]">Platform tax:</span> {{ $taxSetting->enabled ? 'Active' : 'Disabled' }}</p>
                    <p><span class="font-semibold text-[#0F172A]">Product prices:</span> {{ $taxSetting->prices_include_tax ? 'Tax included' : 'Tax added at checkout' }}</p>
                    <p><span class="font-semibold text-[#0F172A]">New products:</span> {{ $taxSetting->default_product_taxable ? 'Taxable by default' : 'Not taxable by default' }}</p>
                    <p><span class="font-semibold text-[#0F172A]">Shipping:</span> {{ $taxSetting->shipping_taxable ? 'Taxable' : 'Not taxable' }}</p>
                    <p class="pt-2 text-sm font-semibold text-[#64748B]">Only the store owner can change tax settings.</p>
                </div>
            @endif

            <details class="mt-6 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 text-xs leading-relaxed text-[#64748B]">
                <summary class="cursor-pointer text-sm font-semibold text-[#475569]">Tax and legal disclaimer</summary>
                <p class="mt-2">These are basic configurable tax rates and are not tax or legal advice. Confirm the correct rates and rules with your accountant or tax adviser.</p>
            </details>
        </section>

        {{-- SECTION 3: Tax rates --}}
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-[Poppins] text-xl font-semibold text-[#0F172A]">Tax rates</h2>
                    <p class="mt-1 text-sm text-[#64748B]">Add a country-wide rate or a region-specific rate.</p>
                </div>
                @if ($canManageTax)
                    <a href="{{ $createRateUrl }}" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white hover:bg-[#0047B3]" data-open-tax-rate-create>+ Add tax rate</a>
                @endif
            </div>

            @if ($taxRates->isEmpty())
                <div class="mt-6 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-10 text-center">
                    <p class="text-sm font-semibold text-[#0F172A]">No tax rates yet</p>
                    <p class="mt-2 text-sm text-[#64748B]">Add a country-wide rate or a region-specific rate to start matching checkout addresses.</p>
                    @if ($canManageTax)
                        <a href="{{ $createRateUrl }}" class="mt-4 inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-semibold text-white" data-open-tax-rate-create>Add tax rate</a>
                    @endif
                </div>
            @else
                <div class="mt-6 hidden md:block overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-[#E2E8F0] text-xs uppercase tracking-wide text-[#64748B]">
                            <tr>
                                <th class="px-3 py-2">Rate name</th>
                                <th class="px-3 py-2">Jurisdiction</th>
                                <th class="px-3 py-2">Rate</th>
                                <th class="px-3 py-2">Status</th>
                                @if ($canManageTax)
                                    <th class="px-3 py-2">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            @foreach ($taxRates as $rate)
                                @php
                                    $jurisdiction = TaxCountryCatalog::jurisdictionSummary($rate->country_code, $rate->region_code);
                                @endphp
                                <tr>
                                    <td class="px-3 py-3 font-semibold text-[#0F172A]">{{ $rate->name }}</td>
                                    <td class="px-3 py-3 text-[#475569]">
                                        <div>{{ $jurisdiction['country_name'] }}</div>
                                        <div class="text-xs text-[#64748B]">{{ $jurisdiction['scope'] === 'country-wide' ? 'Country-wide' : 'Region-specific' }}</div>
                                        @if ($jurisdiction['scope'] === 'region-specific')
                                            <div class="text-xs font-medium text-[#334155]">{{ $jurisdiction['region_label'] ?? strtoupper((string) $rate->region_code) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-[#475569]">{{ $rate->rate_percent }}%</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $rate->is_active ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-[#F1F5F9] text-[#64748B]' }}">
                                            {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    @if ($canManageTax)
                                        <td class="px-3 py-3">
                                            <a href="{{ route('settings.taxes.index', ['edit_rate' => $rate->id]) }}" class="text-sm font-semibold text-[#0052CC] hover:underline">Edit</a>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 space-y-3 md:hidden">
                    @foreach ($taxRates as $rate)
                        @php
                            $jurisdiction = TaxCountryCatalog::jurisdictionSummary($rate->country_code, $rate->region_code);
                        @endphp
                        <article class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-[#0F172A]">{{ $rate->name }}</h3>
                                    <p class="mt-1 text-sm text-[#475569]">{{ $jurisdiction['country_name'] }}</p>
                                    @if ($jurisdiction['scope'] === 'region-specific')
                                        <p class="text-sm font-medium text-[#334155]">{{ $jurisdiction['region_label'] ?? strtoupper((string) $rate->region_code) }}</p>
                                    @endif
                                    <p class="text-xs text-[#64748B]">{{ $jurisdiction['scope'] === 'country-wide' ? 'Country-wide' : 'Region-specific' }}</p>
                                </div>
                                <span class="text-sm font-semibold text-[#0F172A]">{{ $rate->rate_percent }}%</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $rate->is_active ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-white text-[#64748B]' }}">
                                    {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if ($canManageTax)
                                    <a href="{{ route('settings.taxes.index', ['edit_rate' => $rate->id]) }}" class="text-sm font-semibold text-[#0052CC]">Edit</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if (! $canManageTax)
                <p class="mt-4 text-sm font-semibold text-[#64748B]">Only the store owner can change tax rates.</p>
            @endif
        </section>
    </div>

    @if ($canManageTax)
        <dialog id="tax-rate-create-dialog" class="w-[min(100%,640px)] max-h-[90vh] overflow-y-auto rounded-2xl border border-[#E2E8F0] bg-white p-0 shadow-xl backdrop:bg-black/40" @if ($openCreateRateForm) open data-auto-open-tax-dialog="create" @endif>
            <form method="POST" action="{{ route('settings.taxes.rates.store') }}" class="p-5 sm:p-6">
                @csrf
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-[#0F172A]">Add tax rate</h3>
                        <p class="mt-1 text-sm text-[#64748B]">Leave region blank to apply this rate across the entire country.</p>
                    </div>
                    <a href="{{ route('settings.taxes.index') }}" class="rounded-lg border border-[#E2E8F0] px-3 py-1.5 text-sm font-semibold text-[#475569]">Cancel</a>
                </div>
                <div class="mt-5">
                    @include('user_view.partials.tax_rate_form_fields', [
                        'errorBag' => $createErrorBag,
                        'values' => $createValues,
                        'countries' => $countries,
                        'showPriorityField' => false,
                        'formId' => 'tax-rate-create-form',
                    ])
                </div>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('settings.taxes.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E2E8F0] px-4 text-sm font-semibold text-[#475569]">Cancel</a>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0052CC] px-5 text-sm font-semibold text-white">Save rate</button>
                </div>
            </form>
        </dialog>

        @foreach ($taxRates as $rate)
            @if ($editingRateId === (int) $rate->id)
                @php
                    $editErrorBag = 'updateTaxRate_'.$rate->id;
                    $editHasErrors = $errors->getBag($editErrorBag)->any();
                    $editValues = [
                        'name' => $editHasErrors ? old('name', $rate->name) : $rate->name,
                        'country_code' => $editHasErrors ? old('country_code', $rate->country_code) : $rate->country_code,
                        'region_code' => $editHasErrors ? old('region_code', $rate->region_code) : $rate->region_code,
                        'rate_percent' => $editHasErrors ? old('rate_percent', $rate->rate_percent) : $rate->rate_percent,
                        'priority' => $editHasErrors ? old('priority', $rate->priority) : $rate->priority,
                        'is_active' => $editHasErrors ? old('is_active', $rate->is_active) : $rate->is_active,
                    ];
                @endphp
                <dialog id="tax-rate-edit-dialog-{{ $rate->id }}" class="w-[min(100%,640px)] max-h-[90vh] overflow-y-auto rounded-2xl border border-[#E2E8F0] bg-white p-0 shadow-xl backdrop:bg-black/40" open data-auto-open-tax-dialog="edit">
                    <form method="POST" action="{{ route('settings.taxes.rates.update', $rate) }}" class="p-5 sm:p-6">
                        @csrf
                        @method('PATCH')
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-[#0F172A]">Edit tax rate</h3>
                                <p class="mt-1 text-sm text-[#64748B]">{{ $rate->name }}</p>
                            </div>
                            <a href="{{ route('settings.taxes.index') }}" class="rounded-lg border border-[#E2E8F0] px-3 py-1.5 text-sm font-semibold text-[#475569]">Cancel</a>
                        </div>
                        <div class="mt-5">
                            @include('user_view.partials.tax_rate_form_fields', [
                                'errorBag' => $editErrorBag,
                                'values' => $editValues,
                                'countries' => $countries,
                                'showPriorityField' => (int) $rate->priority !== 100 || $errors->getBag($editErrorBag)->has('priority'),
                                'formId' => 'tax-rate-edit-form-'.$rate->id,
                            ])
                        </div>
                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <a href="{{ route('settings.taxes.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E2E8F0] px-4 text-sm font-semibold text-[#475569]">Cancel</a>
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-[#0052CC] px-5 text-sm font-semibold text-white">Save rate</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('settings.taxes.rates.destroy', $rate) }}" class="mt-4 border-t border-[#E2E8F0] px-5 pb-5 pt-4 sm:px-6" onsubmit="return confirm('Remove this tax rate? Existing checkout snapshots are not changed.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-semibold text-[#B42318] hover:underline">Delete rate</button>
                    </form>
                </dialog>
            @endif
        @endforeach
    @endif

    <script type="application/json" id="tax-region-catalog">@json($regionCatalog ?? [])</script>
    <script>
        (() => {
            const regionCatalog = JSON.parse(document.getElementById('tax-region-catalog')?.textContent || '{}');

            const syncRegionFields = (root, { preserveRegion = false } = {}) => {
                const countrySelect = root.querySelector('[data-tax-rate-country-select]');
                const regionSelect = root.querySelector('[data-tax-rate-region-select]');
                const regionText = root.querySelector('[data-tax-rate-region-text]');
                if (!countrySelect || !regionSelect || !regionText) return;

                const country = (countrySelect.value || '').toUpperCase();
                const regions = regionCatalog[country] || null;
                const hasRegions = regions && Object.keys(regions).length > 0;
                const initialRegion = (root.dataset.initialRegion || '').toUpperCase();
                const current = preserveRegion
                    ? (regionSelect.value || regionText.value || initialRegion)
                    : '';

                if (hasRegions) {
                    regionSelect.innerHTML = '<option value="">Country-wide (all regions)</option>';
                    Object.entries(regions).forEach(([code, label]) => {
                        const option = document.createElement('option');
                        option.value = code;
                        option.textContent = `${label} (${code})`;
                        if (code === current) {
                            option.selected = true;
                        }
                        regionSelect.appendChild(option);
                    });

                    if (current && !Object.prototype.hasOwnProperty.call(regions, current)) {
                        const customOption = document.createElement('option');
                        customOption.value = current;
                        customOption.textContent = `${current} — Custom or legacy code`;
                        customOption.selected = true;
                        regionSelect.appendChild(customOption);
                    }

                    regionSelect.classList.remove('hidden');
                    regionSelect.disabled = false;
                    regionSelect.name = 'region_code';
                    regionText.classList.add('hidden');
                    regionText.disabled = true;
                    regionText.removeAttribute('name');
                    regionText.value = '';
                } else {
                    regionSelect.innerHTML = '<option value="">Country-wide (all regions)</option>';
                    regionSelect.classList.add('hidden');
                    regionSelect.disabled = true;
                    regionSelect.removeAttribute('name');
                    regionText.classList.remove('hidden');
                    regionText.disabled = false;
                    regionText.name = 'region_code';
                    regionText.value = current;
                }
            };

            document.querySelectorAll('[data-tax-rate-form-fields]').forEach((root) => {
                const countrySelect = root.querySelector('[data-tax-rate-country-select]');
                countrySelect?.addEventListener('change', () => syncRegionFields(root, { preserveRegion: false }));
                syncRegionFields(root, { preserveRegion: true });
            });

            const upgradeDialogToModal = (dialog) => {
                if (typeof dialog.showModal !== 'function') {
                    return;
                }

                try {
                    if (dialog.hasAttribute('open')) {
                        dialog.close();
                    }
                    dialog.showModal();
                } catch (error) {
                    // Leave the server-rendered non-modal open state when modal upgrade is unsupported.
                }
            };

            const focusTaxDialogField = (dialog) => {
                const invalid = dialog.querySelector('[aria-invalid="true"]');
                (invalid || dialog.querySelector('input[name="name"]'))?.focus();
            };

            const createDialog = document.getElementById('tax-rate-create-dialog');
            const openCreateLinks = document.querySelectorAll('[data-open-tax-rate-create]');

            openCreateLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    if (!createDialog || typeof createDialog.showModal !== 'function') {
                        return;
                    }

                    event.preventDefault();

                    try {
                        if (createDialog.hasAttribute('open')) {
                            createDialog.close();
                        }
                        createDialog.showModal();
                        focusTaxDialogField(createDialog);
                    } catch (error) {
                        // Allow the href fallback when modal upgrade fails.
                    }
                });
            });

            document.querySelectorAll('[data-auto-open-tax-dialog]').forEach((dialog) => {
                upgradeDialogToModal(dialog);
                focusTaxDialogField(dialog);
            });
        })();
    </script>
@endsection

