@extends('layouts.user.user-sidebar')

@section('title', 'Taxes | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Checkout & tax" lead="Configure platform checkout tax separately from delivery.">
        @if ($canManageTax ?? false)
            <x-slot:actions>
                <button type="submit" form="tax-behavior-form" class="tax-console-btn tax-console-btn-primary shrink-0">
                    Save tax settings
                </button>
            </x-slot:actions>
        @endif
    </x-ui.merchant-topbar>
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

        $sampleScopeLabel = 'Not configured';
        $sampleScopeDetail = 'Add a rate to define scope';
        if ($taxRates->isNotEmpty()) {
            $firstJurisdiction = TaxCountryCatalog::jurisdictionSummary(
                $taxRates->first()->country_code,
                $taxRates->first()->region_code
            );
            $sampleScopeLabel = $firstJurisdiction['scope'] === 'country-wide' ? 'Country-wide' : 'Region-specific';
            $sampleScopeDetail = $firstJurisdiction['scope'] === 'country-wide'
                ? 'Applies across the country'
                : 'Matches a specific region';
        }

        $jurisdictionBadge = static function ($rate): string {
            $summary = TaxCountryCatalog::jurisdictionSummary($rate->country_code, $rate->region_code);
            if ($summary['scope'] === 'region-specific' && filled($rate->region_code)) {
                return $summary['country_name'].' ('.strtoupper((string) $rate->region_code).')';
            }

            return $summary['country_name'];
        };
    @endphp

    <div class="settings-workspace-fluid settings-page tax-console">
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

        <section class="tax-console-summary" aria-labelledby="tax-status-heading">
            <h2 id="tax-status-heading" class="sr-only">Tax status</h2>
            <div class="tax-console-summary-grid">
                <article @class([
                    'tax-console-stat',
                    'tax-console-stat-accent' => $taxSetting->enabled,
                ])>
                    <div class="tax-console-stat-head">
                        <span>Status</span>
                        <span @class([
                            'tax-console-stat-icon',
                            'tax-console-stat-icon-success' => $taxSetting->enabled,
                        ]) aria-hidden="true">
                            {{-- sensors --}}
                            <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path d="M12 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm6.36-6.36A8.95 8.95 0 0 0 12 5c-2.48 0-4.71 1-6.36 2.64L7.05 9.05A6.97 6.97 0 0 1 12 7c1.93 0 3.68.78 4.95 2.05l1.41-1.41zM4.22 6.22A11.94 11.94 0 0 0 0 12h2c0-2.76 1.12-5.26 2.93-7.07L4.22 6.22zm15.56 0-1.41 1.41A9.94 9.94 0 0 1 22 12h2c0-2.76-1.12-5.26-2.93-7.07l-1.29 1.29zM7.05 14.95 5.64 16.36A8.95 8.95 0 0 0 12 19c2.48 0 4.71-1 6.36-2.64l-1.41-1.41A6.97 6.97 0 0 1 12 17c-1.93 0-3.68-.78-4.95-2.05z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="tax-console-stat-body">
                        <p class="tax-console-stat-value">
                            @if ($taxSetting->enabled)
                                <span class="tax-console-pulse" aria-hidden="true"></span>
                            @endif
                            {{ $taxSetting->enabled ? 'Active' : 'Disabled' }}
                        </p>
                        <p class="tax-console-stat-meta">
                            {{ $taxSetting->enabled ? 'Live for platform checkouts' : 'Tax calculation is off' }}
                        </p>
                    </div>
                </article>

                <article class="tax-console-stat">
                    <div class="tax-console-stat-head">
                        <span>Active rates</span>
                        <span class="tax-console-stat-icon" aria-hidden="true">
                            {{-- reorder / list --}}
                            <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path d="M3 15h18v-2H3v2zm0 4h18v-2H3v2zm0-8h18V9H3v2zm0-6v2h18V5H3z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="tax-console-stat-body">
                        <p class="tax-console-stat-value">{{ $activeRatesCount }}</p>
                        <p class="tax-console-stat-meta">Primary configurations</p>
                    </div>
                </article>

                <article class="tax-console-stat">
                    <div class="tax-console-stat-head">
                        <span>Product prices</span>
                        <span class="tax-console-stat-icon" aria-hidden="true">
                            {{-- shopping_bag --}}
                            <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path d="M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2zm6 16H6V8h2v2c0 .55.45 1 1 1s1-.45 1-1V8h4v2c0 .55.45 1 1 1s1-.45 1-1V8h2v12z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="tax-console-stat-body">
                        <p class="tax-console-stat-value tax-console-stat-value-sm">
                            {{ $taxSetting->prices_include_tax ? 'Tax included' : 'Tax added at checkout' }}
                        </p>
                        <p class="tax-console-stat-meta">
                            {{ $taxSetting->prices_include_tax ? 'Inclusive of tax' : 'Exclusive of tax' }}
                        </p>
                    </div>
                </article>

                <article class="tax-console-stat">
                    <div class="tax-console-stat-head">
                        <span>New products</span>
                        <span class="tax-console-stat-icon" aria-hidden="true">
                            {{-- verified / new_releases --}}
                            <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72-2.69-2.7 1.06-1.06 1.63 1.62 4.52-4.52 1.06 1.07-5.58 5.59z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="tax-console-stat-body">
                        <p class="tax-console-stat-value tax-console-stat-value-sm">
                            {{ $taxSetting->default_product_taxable ? 'Taxable by default' : 'Not taxable by default' }}
                        </p>
                        <p class="tax-console-stat-meta">By default setting</p>
                    </div>
                </article>

                <article class="tax-console-stat tax-console-stat-muted">
                    <div class="tax-console-stat-head">
                        <span>Sample scope</span>
                        <span class="tax-console-stat-icon" aria-hidden="true">
                            {{-- public / globe --}}
                            <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="tax-console-stat-body">
                        <p class="tax-console-stat-value tax-console-stat-value-sm">{{ $sampleScopeLabel }}</p>
                        <p class="tax-console-stat-meta">{{ $sampleScopeDetail }}</p>
                    </div>
                </article>
            </div>
        </section>

        <div class="tax-console-layout">
            <div class="tax-console-main">
                <section id="tax-behavior-settings" class="tax-console-card">
                    <div class="tax-console-card-header">
                        <div>
                            <h2 class="tax-console-card-title">Tax behavior</h2>
                            <p class="tax-console-card-lead">Define how tax applies to transactions. These settings affect new platform checkouts. Historical order snapshots stay unchanged.</p>
                        </div>
                    </div>

                    <div class="tax-console-card-body">
                        @if ($canManageTax)
                            <form id="tax-behavior-form" method="POST" action="{{ route('settings.taxes.update') }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="calculation_address" value="shipping">

                                <div class="tax-console-toggle-list">
                                    <div class="tax-console-toggle-row">
                                        <span class="tax-console-toggle-copy">
                                            <strong>Enable platform tax calculation</strong>
                                            <span>Applies configured rates to eligible platform checkouts.</span>
                                        </span>
                                        <label class="settings-switch">
                                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $taxSetting->enabled))>
                                            <span class="settings-switch-track" aria-hidden="true"></span>
                                        </label>
                                    </div>

                                    <div class="tax-console-toggle-row">
                                        <span class="tax-console-toggle-copy">
                                            <strong>Product prices include tax</strong>
                                            <span>Catalog prices already include tax when this is on.</span>
                                        </span>
                                        <label class="settings-switch">
                                            <input type="checkbox" name="prices_include_tax" value="1" @checked(old('prices_include_tax', $taxSetting->prices_include_tax))>
                                            <span class="settings-switch-track" aria-hidden="true"></span>
                                        </label>
                                    </div>

                                    <div class="tax-console-toggle-row">
                                        <span class="tax-console-toggle-copy">
                                            <strong>New products are taxable by default</strong>
                                            <span>Applies to new products created after you save this setting.</span>
                                        </span>
                                        <label class="settings-switch">
                                            <input type="checkbox" name="default_product_taxable" value="1" @checked(old('default_product_taxable', $taxSetting->default_product_taxable))>
                                            <span class="settings-switch-track" aria-hidden="true"></span>
                                        </label>
                                    </div>

                                    <div class="tax-console-toggle-row">
                                        <span class="tax-console-toggle-copy">
                                            <strong>Charge tax on shipping</strong>
                                            <span>Adds shipping tax when a matching rate applies.</span>
                                        </span>
                                        <label class="settings-switch">
                                            <input type="checkbox" name="shipping_taxable" value="1" @checked(old('shipping_taxable', $taxSetting->shipping_taxable))>
                                            <span class="settings-switch-track" aria-hidden="true"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="tax-console-address-card">
                                    <div class="tax-console-address-head">
                                        <span class="tax-console-address-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/>
                                            </svg>
                                        </span>
                                        <p class="tax-console-address-title">Calculation address</p>
                                    </div>
                                    <p class="tax-console-address-value">Customer shipping address</p>
                                    @if ($taxSetting->enabled)
                                        <p class="tax-console-address-note">Platform checkout uses the customer shipping address to match configured tax rates.</p>
                                    @else
                                        <p class="tax-console-address-note">Configured rates are saved, but platform checkout will not apply calculated tax until platform tax is enabled.</p>
                                    @endif
                                </div>

                                <div class="tax-console-form-footer">
                                    <p>Changes apply to future checkouts only.</p>
                                    <button type="submit" class="tax-console-btn tax-console-btn-primary">Save tax settings</button>
                                </div>
                            </form>
                        @else
                            <div class="tax-console-toggle-list">
                                <div class="tax-console-toggle-row">
                                    <span class="tax-console-toggle-copy"><strong>Platform tax</strong><span>{{ $taxSetting->enabled ? 'Active' : 'Disabled' }}</span></span>
                                </div>
                                <div class="tax-console-toggle-row">
                                    <span class="tax-console-toggle-copy"><strong>Product prices</strong><span>{{ $taxSetting->prices_include_tax ? 'Tax included' : 'Tax added at checkout' }}</span></span>
                                </div>
                                <div class="tax-console-toggle-row">
                                    <span class="tax-console-toggle-copy"><strong>New products</strong><span>{{ $taxSetting->default_product_taxable ? 'Taxable by default' : 'Not taxable by default' }}</span></span>
                                </div>
                                <div class="tax-console-toggle-row">
                                    <span class="tax-console-toggle-copy"><strong>Shipping</strong><span>{{ $taxSetting->shipping_taxable ? 'Taxable' : 'Not taxable' }}</span></span>
                                </div>
                            </div>
                            <p class="mt-4 text-sm font-semibold text-[#64748B]">Only the store owner can change tax settings.</p>
                        @endif

                        <details class="settings-collapse mt-5 text-xs leading-relaxed text-[#64748B]">
                            <summary>Tax and legal disclaimer</summary>
                            <p class="mt-2">These are basic configurable tax rates and are not tax or legal advice. Confirm the correct rates and rules with your accountant or tax adviser.</p>
                        </details>
                    </div>
                </section>
            </div>

            <div class="tax-console-aside">
                <section class="tax-console-card tax-console-rates-card">
                    <div class="tax-console-card-header tax-console-card-header-row">
                        <div>
                            <h2 class="tax-console-card-title">Tax rates</h2>
                            <p class="tax-console-card-lead">Add a country-wide rate or a region-specific rate. Regional rates assigned to checkout flows.</p>
                        </div>
                        @if ($canManageTax)
                            <a href="{{ $createRateUrl }}" class="tax-console-btn tax-console-btn-primary shrink-0" data-open-tax-rate-create>
                                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true">
                                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                </svg>
                                + Add tax rate
                            </a>
                        @endif
                    </div>

                    <div class="tax-console-card-body tax-console-rates-body">
                        @if ($taxRates->isEmpty())
                            <div class="tax-console-empty">
                                <div class="tax-console-empty-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                                        <path d="M20 2H4c-1 0-2 1-2 2v3.01c0 .72.43 1.34 1 1.69V20c0 1.1 1.1 2 2 2h14c.9 0 2-.9 2-2V8.7c.57-.35 1-.97 1-1.69V4c0-1-1-2-2-2zm-5 12H9v-2h6v2zm5-7H4V4h16v3z"/>
                                    </svg>
                                </div>
                                <p class="tax-console-empty-title">No tax rates yet</p>
                                <p class="tax-console-empty-copy">Add a country-wide rate or a region-specific rate to start matching checkout addresses.</p>
                                @if ($canManageTax)
                                    <a href="{{ $createRateUrl }}" class="tax-console-btn tax-console-btn-primary mt-4" data-open-tax-rate-create>Add tax rate</a>
                                @endif
                            </div>
                        @else
                            <div class="tax-console-table-wrap hidden md:block">
                                <table class="tax-console-table">
                                    <thead>
                                        <tr>
                                            <th>Rate name</th>
                                            <th>Jurisdiction</th>
                                            <th class="text-right">Rate</th>
                                            <th>Status</th>
                                            @if ($canManageTax)
                                                <th class="text-right">Actions</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($taxRates as $rate)
                                            @php
                                                $jurisdiction = TaxCountryCatalog::jurisdictionSummary($rate->country_code, $rate->region_code);
                                            @endphp
                                            <tr @class(['tax-console-row-inactive' => ! $rate->is_active])>
                                                <td>
                                                    <div class="tax-console-rate-name">
                                                        <span @class([
                                                            'tax-console-rate-bar',
                                                            'tax-console-rate-bar-active' => $rate->is_active,
                                                            'tax-console-rate-bar-muted' => ! $rate->is_active,
                                                        ]) aria-hidden="true"></span>
                                                        <div>
                                                            <p class="font-semibold text-[#0F172A]">{{ $rate->name }}</p>
                                                            <p class="tax-console-rate-id">ID: {{ $rate->id }}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="tax-console-jurisdiction">{{ $jurisdictionBadge($rate) }}</span>
                                                    <div class="mt-1 text-xs text-[#64748B]">{{ $jurisdiction['scope'] === 'country-wide' ? 'Country-wide' : 'Region-specific' }}</div>
                                                    @if ($jurisdiction['scope'] === 'region-specific')
                                                        <div class="text-xs font-medium text-[#334155]">{{ $jurisdiction['region_label'] ?? strtoupper((string) $rate->region_code) }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-right font-semibold tabular-nums text-[#0F172A]">{{ $rate->rate_percent }}%</td>
                                                <td>
                                                    <span @class([
                                                        'tax-console-pill',
                                                        'tax-console-pill-success' => $rate->is_active,
                                                        'tax-console-pill-muted' => ! $rate->is_active,
                                                    ])>
                                                        @if ($rate->is_active)
                                                            <span class="tax-console-pill-dot" aria-hidden="true"></span>
                                                        @endif
                                                        {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                                    </span>
                                                </td>
                                                @if ($canManageTax)
                                                    <td class="text-right">
                                                        <a href="{{ route('settings.taxes.index', ['edit_rate' => $rate->id]) }}" class="tax-console-link">Edit</a>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="space-y-3 md:hidden">
                                @foreach ($taxRates as $rate)
                                    @php
                                        $jurisdiction = TaxCountryCatalog::jurisdictionSummary($rate->country_code, $rate->region_code);
                                    @endphp
                                    <article class="tax-console-mobile-rate">
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
                                            <span @class([
                                                'tax-console-pill',
                                                'tax-console-pill-success' => $rate->is_active,
                                                'tax-console-pill-muted' => ! $rate->is_active,
                                            ])>
                                                @if ($rate->is_active)
                                                    <span class="tax-console-pill-dot" aria-hidden="true"></span>
                                                @endif
                                                {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                            @if ($canManageTax)
                                                <a href="{{ route('settings.taxes.index', ['edit_rate' => $rate->id]) }}" class="tax-console-link">Edit</a>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif

                        @if (! $canManageTax)
                            <p class="mt-4 text-sm font-semibold text-[#64748B]">Only the store owner can change tax rates.</p>
                        @endif
                    </div>
                </section>
            </div>
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
                    <a href="{{ route('settings.taxes.index') }}" class="rounded-lg border border-[#E3E1EA] px-3 py-1.5 text-sm font-semibold text-[#475569]">Cancel</a>
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
                    <a href="{{ route('settings.taxes.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E3E1EA] px-4 text-sm font-semibold text-[#475569]">Cancel</a>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-brand px-5 text-sm font-semibold text-white">Save rate</button>
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
                            <a href="{{ route('settings.taxes.index') }}" class="rounded-lg border border-[#E3E1EA] px-3 py-1.5 text-sm font-semibold text-[#475569]">Cancel</a>
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
                            <a href="{{ route('settings.taxes.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-[#E3E1EA] px-4 text-sm font-semibold text-[#475569]">Cancel</a>
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-brand px-5 text-sm font-semibold text-white">Save rate</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('settings.taxes.rates.destroy', $rate) }}" class="mt-4 border-t border-[#E3E1EA] px-5 pb-5 pt-4 sm:px-6" onsubmit="return confirm('Remove this tax rate? Existing checkout snapshots are not changed.');">
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
