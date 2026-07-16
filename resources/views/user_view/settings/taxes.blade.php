@extends('layouts.user.user-sidebar')

@section('title', 'Taxes | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 md:px-8">
        <button id="sidebarToggle" onclick="openSidebar()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm md:hidden" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>
        <div>
            <h1 class="font-poppins text-lg font-semibold md:text-xl">Checkout &amp; tax</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Configure platform checkout tax separately from delivery setup.</p>
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

    <div class="settings-workspace-fluid settings-page">
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

        <div class="settings-status-strip">
            <span @class([
                'settings-status-pill',
                'settings-status-pill-ready' => $taxSetting->enabled,
                'settings-status-pill-pending' => ! $taxSetting->enabled,
            ])>Platform tax <strong>{{ $taxSetting->enabled ? 'On' : 'Off' }}</strong></span>
            <span class="settings-status-pill">Active rates <strong>{{ $activeRatesCount }}</strong></span>
            <span class="settings-status-pill">Product prices <strong>{{ $taxSetting->prices_include_tax ? 'Tax included' : 'Tax added at checkout' }}</strong></span>
        </div>

        @if ($taxSetting->enabled && $activeRatesCount === 0)
            <div class="settings-alert settings-alert-error" role="alert">
                Tax is active, but no active rates are configured.
                @if ($canManageTax)
                    <a href="{{ $createRateUrl }}" class="ml-2 font-semibold underline" data-open-tax-rate-create>Add tax rate</a>
                @endif
            </div>
        @elseif (! $taxSetting->enabled && $activeRatesCount > 0)
            <div class="settings-alert" role="alert">
                Rates are saved but currently inactive because platform tax is disabled.
                @if ($canManageTax)
                    <a href="#tax-behavior-settings" class="ml-2 font-semibold underline">Enable tax</a>
                @endif
            </div>
        @endif

        <div class="settings-page-layout">
            <div class="settings-page-stack">
                <section id="tax-behavior-settings" class="settings-panel">
                    <div class="settings-panel-header">
                        <h2 class="settings-panel-title">Tax behavior</h2>
                        <p class="settings-panel-lead">These settings affect new platform checkouts. Historical order snapshots stay unchanged.</p>
                    </div>

                    @if ($canManageTax)
                        <form method="POST" action="{{ route('settings.taxes.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="calculation_address" value="shipping">

                            <div class="settings-toggle-list">
                                <div class="settings-toggle-row">
                                    <span class="settings-toggle-copy">
                                        <strong>Enable platform tax calculation</strong>
                                        <span>Applies configured rates to eligible platform checkouts.</span>
                                    </span>
                                    <label class="settings-switch">
                                        <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $taxSetting->enabled))>
                                        <span class="settings-switch-track" aria-hidden="true"></span>
                                    </label>
                                </div>

                                <div class="settings-toggle-row">
                                    <span class="settings-toggle-copy">
                                        <strong>Product prices include tax</strong>
                                        <span>Catalog prices already include tax when this is on.</span>
                                    </span>
                                    <label class="settings-switch">
                                        <input type="checkbox" name="prices_include_tax" value="1" @checked(old('prices_include_tax', $taxSetting->prices_include_tax))>
                                        <span class="settings-switch-track" aria-hidden="true"></span>
                                    </label>
                                </div>

                                <div class="settings-toggle-row">
                                    <span class="settings-toggle-copy">
                                        <strong>New products are taxable by default</strong>
                                        <span>Applies to new products created after you save this setting.</span>
                                    </span>
                                    <label class="settings-switch">
                                        <input type="checkbox" name="default_product_taxable" value="1" @checked(old('default_product_taxable', $taxSetting->default_product_taxable))>
                                        <span class="settings-switch-track" aria-hidden="true"></span>
                                    </label>
                                </div>

                                <div class="settings-toggle-row">
                                    <span class="settings-toggle-copy">
                                        <strong>Charge tax on shipping</strong>
                                        <span>Adds shipping tax when a matching rate applies.</span>
                                    </span>
                                    <label class="settings-switch">
                                        <input type="checkbox" name="shipping_taxable" value="1" @checked(old('shipping_taxable', $taxSetting->shipping_taxable))>
                                        <span class="settings-switch-track" aria-hidden="true"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                                <p class="text-sm font-semibold text-[#0F172A]">Calculation address</p>
                                <p class="mt-1 text-sm text-[#64748B]">Customer shipping address</p>
                                @if ($taxSetting->enabled)
                                    <p class="mt-2 text-xs leading-relaxed text-[#64748B]">Platform checkout uses the customer shipping address to match configured tax rates.</p>
                                @else
                                    <p class="mt-2 text-xs leading-relaxed text-[#64748B]">Configured rates are saved, but platform checkout will not apply calculated tax until platform tax is enabled.</p>
                                @endif
                            </div>

                            <div class="settings-form-footer">
                                <p class="text-xs text-[#64748B]">Changes apply to future checkouts only.</p>
                                <button type="submit" class="settings-btn settings-btn-primary">Save tax settings</button>
                            </div>
                        </form>
                    @else
                        <div class="settings-toggle-list">
                            <div class="settings-toggle-row">
                                <span class="settings-toggle-copy"><strong>Platform tax</strong><span>{{ $taxSetting->enabled ? 'Active' : 'Disabled' }}</span></span>
                            </div>
                            <div class="settings-toggle-row">
                                <span class="settings-toggle-copy"><strong>Product prices</strong><span>{{ $taxSetting->prices_include_tax ? 'Tax included' : 'Tax added at checkout' }}</span></span>
                            </div>
                            <div class="settings-toggle-row">
                                <span class="settings-toggle-copy"><strong>New products</strong><span>{{ $taxSetting->default_product_taxable ? 'Taxable by default' : 'Not taxable by default' }}</span></span>
                            </div>
                            <div class="settings-toggle-row">
                                <span class="settings-toggle-copy"><strong>Shipping</strong><span>{{ $taxSetting->shipping_taxable ? 'Taxable' : 'Not taxable' }}</span></span>
                            </div>
                        </div>
                        <p class="mt-4 text-sm font-semibold text-[#64748B]">Only the store owner can change tax settings.</p>
                    @endif

                    <details class="settings-collapse mt-4 text-xs leading-relaxed text-[#64748B]">
                        <summary>Tax and legal disclaimer</summary>
                        <p class="mt-2">These are basic configurable tax rates and are not tax or legal advice. Confirm the correct rates and rules with your accountant or tax adviser.</p>
                    </details>
                </section>

                <section class="settings-panel">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="settings-panel-header" style="margin-bottom: 0;">
                            <h2 class="settings-panel-title">Tax rates</h2>
                            <p class="settings-panel-lead">Add a country-wide rate or a region-specific rate.</p>
                        </div>
                        @if ($canManageTax)
                            <a href="{{ $createRateUrl }}" class="settings-btn settings-btn-primary shrink-0" data-open-tax-rate-create>+ Add tax rate</a>
                        @endif
                    </div>

                    @if ($taxRates->isEmpty())
                        <div class="mt-4 rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-4 py-10 text-center">
                            <p class="text-sm font-semibold text-[#0F172A]">No tax rates yet</p>
                            <p class="mt-2 text-sm text-[#64748B]">Add a country-wide rate or a region-specific rate to start matching checkout addresses.</p>
                            @if ($canManageTax)
                                <a href="{{ $createRateUrl }}" class="settings-btn settings-btn-primary mt-4" data-open-tax-rate-create>Add tax rate</a>
                            @endif
                        </div>
                    @else
                        <div class="settings-table-wrap hidden md:block">
                            <table class="settings-table">
                                <thead>
                                    <tr>
                                        <th>Rate name</th>
                                        <th>Jurisdiction</th>
                                        <th>Rate</th>
                                        <th>Status</th>
                                        @if ($canManageTax)
                                            <th>Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($taxRates as $rate)
                                        @php
                                            $jurisdiction = TaxCountryCatalog::jurisdictionSummary($rate->country_code, $rate->region_code);
                                        @endphp
                                        <tr>
                                            <td class="font-semibold text-[#0F172A]">{{ $rate->name }}</td>
                                            <td class="text-[#475569]">
                                                <div>{{ $jurisdiction['country_name'] }}</div>
                                                <div class="text-xs text-[#64748B]">{{ $jurisdiction['scope'] === 'country-wide' ? 'Country-wide' : 'Region-specific' }}</div>
                                                @if ($jurisdiction['scope'] === 'region-specific')
                                                    <div class="text-xs font-medium text-[#334155]">{{ $jurisdiction['region_label'] ?? strtoupper((string) $rate->region_code) }}</div>
                                                @endif
                                            </td>
                                            <td class="text-[#475569]">{{ $rate->rate_percent }}%</td>
                                            <td>
                                                <span class="settings-pill {{ $rate->is_active ? 'settings-pill-success' : 'settings-pill-muted' }}">
                                                    {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            @if ($canManageTax)
                                                <td>
                                                    <a href="{{ route('settings.taxes.index', ['edit_rate' => $rate->id]) }}" class="text-sm font-semibold text-[#4F46E5] hover:underline">Edit</a>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 space-y-3 md:hidden">
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
                                        <span class="settings-pill {{ $rate->is_active ? 'settings-pill-success' : 'settings-pill-muted' }}">
                                            {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        @if ($canManageTax)
                                            <a href="{{ route('settings.taxes.index', ['edit_rate' => $rate->id]) }}" class="text-sm font-semibold text-[#4F46E5]">Edit</a>
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

            <aside class="settings-page-aside">
                <p class="settings-page-aside-title">Tax status</p>
                <div class="settings-page-aside-list">
                    <div class="settings-page-aside-item">Status <strong>{{ $taxSetting->enabled ? 'Active' : 'Disabled' }}</strong></div>
                    <div class="settings-page-aside-item">Active rates <strong>{{ $activeRatesCount }}</strong></div>
                    <div class="settings-page-aside-item">Product prices <strong>{{ $taxSetting->prices_include_tax ? 'Tax included' : 'Tax added at checkout' }}</strong></div>
                    <div class="settings-page-aside-item">New products <strong>{{ $taxSetting->default_product_taxable ? 'Taxable by default' : 'Not taxable by default' }}</strong></div>
                    <div class="settings-page-aside-item">Shipping <strong>{{ $taxSetting->shipping_taxable ? 'Taxable' : 'Not taxable' }}</strong></div>
                    @if ($taxRates->isNotEmpty())
                        @php
                            $firstRate = $taxRates->first();
                            $firstJurisdiction = TaxCountryCatalog::jurisdictionSummary($firstRate->country_code, $firstRate->region_code);
                        @endphp
                        <div class="settings-page-aside-item">Sample scope <strong>{{ $firstJurisdiction['scope'] === 'country-wide' ? 'Country-wide' : 'Region-specific' }}</strong></div>
                    @endif
                </div>
            </aside>
        </div>
    </div>

    @if ($canManageTax)
        <dialog id="tax-rate-create-dialog" class="ui-native-dialog" @if ($openCreateRateForm) open data-auto-open-tax-dialog="create" @endif>
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
                <dialog id="tax-rate-edit-dialog-{{ $rate->id }}" class="ui-native-dialog" open data-auto-open-tax-dialog="edit">
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

