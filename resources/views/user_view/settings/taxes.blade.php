@extends('layouts.user.user-sidebar')

@section('title', 'Taxes — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Taxes" lead="Set rates and how tax is applied at checkout." />
@endsection

@section('content')
    @php
        use App\Support\Tax\TaxCountryCatalog;

        $canManageTax = (bool) ($canManageTax ?? false);
        $activeRatesCount = $activeRatesCount ?? $taxRates->where('is_active', true)->count();
        $countryGroups = $countryGroups ?? [];
        $countryCount = $countryCount ?? count($countryGroups);
        $selectedRateId = (int) ($selectedRateId ?? 0);
        $rulebookSection = $rulebookSection ?? 'jurisdictions';
        $lastSavedLabel = $lastSavedLabel ?? 'Not saved yet';
        $openCreateRateForm = (bool) ($openCreateRateForm ?? false);
        $editingRateId = (int) ($editingRateId ?? 0);
        $createRateUrl = route('settings.taxes.index', ['create_rate' => 1]);
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
        $openBehaviorEditor = $errors->hasAny(['enabled', 'prices_include_tax', 'default_product_taxable', 'shipping_taxable', 'calculation_address'])
            || (bool) old('_tax_editing');
        if ($openBehaviorEditor) {
            $rulebookSection = 'behavior';
        }
        $showJurisdictions = $rulebookSection === 'jurisdictions';
        $createValues = $openCreateRateForm ? [
            'name' => $createHasErrors ? old('name') : '',
            'country_code' => $createHasErrors ? old('country_code') : ($createCountryPrefill ?? ''),
            'region_code' => $createHasErrors ? old('region_code') : '',
            'rate_percent' => $createHasErrors ? old('rate_percent') : '',
            'priority' => $createHasErrors ? old('priority', 100) : 100,
            'is_active' => $createHasErrors ? old('is_active', true) : true,
        ] : [];
        $preferCreateRegional = $openCreateRateForm && (
            filled($createCountryPrefill)
            || old('tax_coverage') === 'regional'
            || filled($createValues['region_code'] ?? null)
        );
        $sampleScopeLabel = 'Not configured';
        if ($taxRates->isNotEmpty()) {
            $firstJurisdiction = TaxCountryCatalog::jurisdictionSummary(
                $taxRates->first()->country_code,
                $taxRates->first()->region_code
            );
            $sampleScopeLabel = $firstJurisdiction['scope'] === 'country-wide' ? 'Country-wide' : 'Region-specific';
        }
    @endphp

    <div
        class="settings-workspace-fluid settings-page tax-rulebook"
        data-trb-root
        data-trb-section="{{ $rulebookSection }}"
        data-trb-selected="{{ $selectedRateId }}"
        data-trb-can-manage="{{ $canManageTax ? '1' : '0' }}"
    >
        @include('user_view.partials.flash_success')

        <div class="sr-only">
            <h2 id="tax-status-heading">Tax status</h2>
            <p>Taxes</p>
            <p>Tax rates</p>
            <p>{{ $sampleScopeLabel }}</p>
            @if ($taxSetting->prices_include_tax)
                <p>Tax included</p>
            @else
                <p>Tax added at checkout</p>
            @endif
            <p>{{ $taxSetting->default_product_taxable ? 'Taxable by default' : 'Not taxable by default' }}</p>
            <p>{{ $taxSetting->enabled ? 'Active' : 'Disabled' }}</p>
            <ul>
                @foreach ($taxRates as $rate)
                    @php $catalogSummary = TaxCountryCatalog::jurisdictionSummary($rate->country_code, $rate->region_code); @endphp
                    <li>
                        {{ $rate->name }}
                        {{ $catalogSummary['country_name'] }}
                        {{ $catalogSummary['scope'] === 'country-wide' ? 'Country-wide' : 'Region-specific' }}
                        {{ $catalogSummary['region_label'] ?? strtoupper((string) $rate->region_code) }}
                    </li>
                @endforeach
            </ul>
        </div>

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
            <div class="trb-alert" data-trb-workspace-alert>
                <div class="trb-note is-warning" role="alert">
                    @include('user_view.taxes.partials.icon', ['name' => 'info', 'size' => 17])
                    <span>
                        Tax is active, but no active rates are configured.
                        @if ($canManageTax)
                            <a href="{{ $createRateUrl }}" class="ml-2 font-semibold underline" data-open-tax-rate-create>Add tax rate</a>
                        @endif
                    </span>
                </div>
            </div>
        @elseif (! $taxSetting->enabled && $activeRatesCount > 0)
            <div class="trb-alert" data-trb-workspace-alert>
                <div class="trb-note is-warning" role="alert">
                    @include('user_view.taxes.partials.icon', ['name' => 'info', 'size' => 17])
                    <span>
                        Rates are saved but currently inactive because platform tax is disabled.
                        @if ($canManageTax)
                            <a href="{{ route('settings.taxes.index', ['section' => 'behavior']) }}#tax-behavior-settings" class="ml-2 font-semibold underline" data-trb-section="behavior">Enable tax</a>
                        @endif
                    </span>
                </div>
            </div>
        @endif

        <div class="trb-heading">
            <div>
                <h1>Tax Rulebook</h1>
                <p>Manage checkout behavior and jurisdiction rules from one workspace.</p>
            </div>
            <div class="trb-heading-actions">
                @if ($canManageTax)
                    <a class="trb-btn trb-btn-primary" href="{{ $createRateUrl }}" data-open-tax-rate-create aria-label="Add tax rate">
                        @include('user_view.taxes.partials.icon', ['name' => 'plus', 'size' => 18])
                        Add tax rate
                    </a>
                    <button class="trb-btn trb-btn-square" type="button" data-trb-page-menu aria-label="Rulebook options" aria-haspopup="menu" aria-expanded="false">
                        @include('user_view.taxes.partials.icon', ['name' => 'more', 'size' => 16])
                    </button>
                @endif
            </div>
        </div>

        @unless ($canManageTax)
            <p class="mb-4 text-sm font-semibold text-[#64748B]">Only the store owner can change tax settings.</p>
        @endunless

        <section class="trb-workspace" data-trb-workspace aria-label="Tax rulebook workspace">
            <aside class="trb-rail" aria-label="Tax configuration">
                <nav class="trb-rail-top" aria-label="Tax sections">
                    <p class="trb-eyebrow">Tax configuration</p>
                    <button class="trb-section-btn {{ $rulebookSection === 'behavior' ? 'is-active' : '' }}" type="button" data-trb-section="behavior" @if ($rulebookSection === 'behavior') aria-current="page" @endif>
                        <span class="trb-dot {{ $taxSetting->enabled ? '' : 'is-gray' }}"></span>
                        <span>Behavior<small>4 settings</small></span>
                    </button>
                    <button class="trb-section-btn {{ $showJurisdictions ? 'is-active' : '' }}" type="button" data-trb-section="jurisdictions" @if ($showJurisdictions) aria-current="page" @endif>
                        <span>Jurisdictions</span>
                        <span class="trb-badge">{{ $countryCount }}</span>
                    </button>
                    <button class="trb-section-btn {{ $rulebookSection === 'legal' ? 'is-active' : '' }}" type="button" data-trb-section="legal" @if ($rulebookSection === 'legal') aria-current="page" @endif>
                        <span>Legal notice</span>
                    </button>
                </nav>
                <div class="trb-current">
                    <p class="trb-eyebrow">Current behavior</p>
                    <dl class="trb-mini">
                        <div><dt>Calculation</dt><dd>{{ $taxSetting->enabled ? 'Enabled' : 'Disabled' }}</dd></div>
                        <div><dt>Prices</dt><dd>{{ $taxSetting->prices_include_tax ? 'Tax included' : 'Tax excluded' }}</dd></div>
                        <div><dt>Products</dt><dd>{{ $taxSetting->default_product_taxable ? 'Taxable' : 'Not taxable' }}</dd></div>
                        <div><dt>Shipping</dt><dd>{{ $taxSetting->shipping_taxable ? 'Taxable' : 'Not taxable' }}</dd></div>
                    </dl>
                    @if ($canManageTax)
                        <button class="trb-btn" type="button" data-trb-edit-behavior id="tax-behavior-settings">Edit behavior</button>
                    @endif
                </div>
            </aside>

            <section class="trb-jurisdictions" data-trb-panel="jurisdictions" aria-labelledby="jurisdiction-title" @if (! $showJurisdictions) hidden @endif>
                <h2 id="jurisdiction-title">Jurisdictions</h2>
                <div class="trb-search">
                    @include('user_view.taxes.partials.icon', ['name' => 'search', 'size' => 17])
                    <input id="trb-search" type="search" placeholder="Find a jurisdiction" autocomplete="off" aria-label="Search countries, regions, or rate names">
                    <button class="trb-search-clear" type="button" data-trb-clear-search hidden aria-label="Clear search">
                        @include('user_view.taxes.partials.icon', ['name' => 'close', 'size' => 14])
                    </button>
                </div>
                <div class="trb-country-list" data-trb-country-list>
                    @if ($taxRates->isEmpty())
                        <div class="trb-empty">
                            @include('user_view.taxes.partials.icon', ['name' => 'globe', 'size' => 29])
                            <h3>No tax rates yet</h3>
                            <p>Add a country-wide rate or a region-specific rate to start your rulebook.</p>
                        </div>
                    @else
                        @foreach ($countryGroups as $group)
                            <div class="trb-country-group" data-trb-country="{{ $group['code'] }}" data-trb-search="{{ strtolower($group['name'].' '.$group['code']) }}">
                                <button
                                    class="trb-country-heading"
                                    type="button"
                                    data-trb-toggle-country="{{ $group['code'] }}"
                                    aria-expanded="true"
                                    aria-controls="trb-children-{{ $group['code'] }}"
                                >
                                    @include('user_view.taxes.partials.icon', ['name' => 'chevron', 'size' => 13])
                                    <span class="trb-flag" aria-hidden="true">{{ $group['flag'] }}</span>
                                    <span class="trb-country-name">
                                        {{ $group['name'] }}
                                        <small>{{ $group['total'] }} configured {{ $group['total'] === 1 ? 'rate' : 'rates' }}</small>
                                    </span>
                                    <span class="trb-pill {{ $group['status'] === 'Inactive' ? 'is-neutral' : ($group['status'] === 'Mixed' ? 'is-amber' : '') }}">{{ $group['status'] }}</span>
                                </button>
                                <div class="trb-rate-children" id="trb-children-{{ $group['code'] }}">
                                    @foreach ($group['rates'] as $entry)
                                        @php $rate = $entry['model']; @endphp
                                        <a
                                            class="trb-rule {{ (int) $rate->id === $selectedRateId ? 'is-active' : '' }}"
                                            href="{{ route('settings.taxes.index', ['rate' => $rate->id, 'section' => 'jurisdictions']) }}"
                                            data-trb-select-rate="{{ $rate->id }}"
                                            data-trb-search="{{ $entry['search'] }}"
                                            @if ((int) $rate->id === $selectedRateId) aria-current="true" @endif
                                        >
                                            <span class="trb-dot {{ $rate->is_active ? '' : 'is-gray' }}"></span>
                                            <span>
                                                <strong>{{ $entry['scopeLabel'] }}</strong>
                                                <em>{{ $rate->name }}</em>
                                            </span>
                                            <span>{{ $rate->rate_percent }}%</span>
                                        </a>
                                    @endforeach
                                    @if ($canManageTax)
                                        <a class="trb-add-region" href="{{ route('settings.taxes.index', ['create_rate' => 1, 'country' => $group['code']]) }}" data-open-tax-rate-create data-trb-country-prefill="{{ $group['code'] }}">
                                            @include('user_view.taxes.partials.icon', ['name' => 'plus', 'size' => 15])
                                            Add regional rate
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        <div class="trb-empty" data-trb-search-empty hidden>
                            @include('user_view.taxes.partials.icon', ['name' => 'search', 'size' => 29])
                            <h3>No matching jurisdictions</h3>
                            <p>Try a country, region, or rate name.</p>
                            <button class="trb-btn" type="button" data-trb-clear-search>Clear search</button>
                        </div>
                    @endif
                </div>
                <p class="trb-index-note">Regional rates appear beneath their country.</p>
                <p class="sr-only" data-trb-search-status role="status" aria-live="polite"></p>
            </section>

            <section class="trb-inspector-stack" data-trb-panel="inspector" @if (! $showJurisdictions) hidden @endif aria-label="Selected tax rate">
                @forelse ($countryGroups as $group)
                    @foreach ($group['rates'] as $entry)
                        @include('user_view.taxes.partials.inspector', [
                            'entry' => $entry,
                            'selected' => (int) $entry['model']->id === $selectedRateId,
                            'canManageTax' => $canManageTax,
                            'taxSetting' => $taxSetting,
                        ])
                    @endforeach
                @empty
                    <div class="trb-inspector">
                        <div class="trb-empty">
                            @include('user_view.taxes.partials.icon', ['name' => 'file', 'size' => 29])
                            <h3>Your rulebook is ready</h3>
                            <p>Create a rate to manage its jurisdiction, percentage, and status here.</p>
                            @if ($canManageTax)
                                <a class="trb-btn trb-btn-primary" href="{{ $createRateUrl }}" data-open-tax-rate-create>Add tax rate</a>
                            @endif
                        </div>
                    </div>
                @endforelse
            </section>

            <section class="trb-wide" data-trb-panel="behavior" id="tax-behavior-panel" @if ($rulebookSection !== 'behavior') hidden @endif>
                <div class="trb-panel-heading">
                    <div>
                        <p class="trb-eyebrow">Store-wide configuration</p>
                        <h2>Tax behavior</h2>
                        <p>Define how tax is applied across this store’s platform checkout.</p>
                    </div>
                    @if ($canManageTax)
                        <button class="trb-btn" type="button" data-trb-edit-behavior>
                            @include('user_view.taxes.partials.icon', ['name' => 'edit', 'size' => 16])
                            Edit behavior
                        </button>
                    @endif
                </div>
                <dl class="trb-readout">
                    <div>
                        <dt>Platform tax calculation<small>Apply configured rates to eligible platform checkouts.</small></dt>
                        <dd><span class="trb-pill {{ $taxSetting->enabled ? '' : 'is-neutral' }}">{{ $taxSetting->enabled ? 'Enabled' : 'Disabled' }}</span></dd>
                    </div>
                    <div>
                        <dt>Product prices include tax<small>Catalog prices already contain tax when this is on.</small></dt>
                        <dd><span class="trb-pill {{ $taxSetting->prices_include_tax ? '' : 'is-neutral' }}">{{ $taxSetting->prices_include_tax ? 'Enabled' : 'Disabled' }}</span></dd>
                    </div>
                    <div>
                        <dt>New products are taxable by default<small>Affects new products; existing product settings stay unchanged.</small></dt>
                        <dd><span class="trb-pill {{ $taxSetting->default_product_taxable ? '' : 'is-neutral' }}">{{ $taxSetting->default_product_taxable ? 'Enabled' : 'Disabled' }}</span></dd>
                    </div>
                    <div>
                        <dt>Charge tax on shipping<small>Apply shipping tax when a matching rate applies.</small></dt>
                        <dd><span class="trb-pill {{ $taxSetting->shipping_taxable ? '' : 'is-neutral' }}">{{ $taxSetting->shipping_taxable ? 'Enabled' : 'Disabled' }}</span></dd>
                    </div>
                </dl>
                <div class="trb-address">
                    <span class="trb-address-icon" aria-hidden="true">
                        @include('user_view.taxes.partials.icon', ['name' => 'pin', 'size' => 16])
                    </span>
                    <div>
                        <strong>Customer shipping address</strong>
                        @if ($taxSetting->enabled)
                            <p>Platform checkout uses the customer shipping address to match configured tax rates.</p>
                        @else
                            <p>Configured rates are saved, but platform checkout will not apply calculated tax until platform tax is enabled.</p>
                        @endif
                    </div>
                </div>
                <div class="trb-note">
                    @include('user_view.taxes.partials.icon', ['name' => 'info', 'size' => 17])
                    <span>Changes apply to future checkouts. Existing order tax snapshots are not recalculated.</span>
                </div>
            </section>

            <section class="trb-wide" data-trb-panel="legal" @if ($rulebookSection !== 'legal') hidden @endif>
                <div class="trb-panel-heading">
                    <div>
                        <p class="trb-eyebrow">Tax configuration</p>
                        <h2>Tax and legal notice</h2>
                        <p>Understand the scope of these settings.</p>
                    </div>
                    @include('user_view.taxes.partials.icon', ['name' => 'shield', 'size' => 22])
                </div>
                <div class="trb-legal">
                    <p>These are basic configurable tax rates and are not tax or legal advice. Confirm the correct rates and rules with your accountant or tax adviser.</p>
                    <h3>What this rulebook manages</h3>
                    <ul>
                        <li>Store-specific country-wide and regional rates.</li>
                        <li>Tax-inclusive prices, new-product tax defaults, and shipping tax behavior.</li>
                        <li>Matching rates using the customer’s shipping address.</li>
                    </ul>
                    <h3>What it does not do</h3>
                    <p>This interface does not determine your registration obligations, automatically maintain government tax rates, or file tax returns for you.</p>
                    <h3>Historical orders</h3>
                    <p>Editing, deactivating, or deleting a rate does not rewrite existing order tax snapshots.</p>
                </div>
            </section>

            <footer class="trb-footer">
                <span>
                    <span class="trb-dot {{ $taxSetting->enabled ? '' : 'is-gray' }}"></span>
                    Tax calculation {{ $taxSetting->enabled ? 'enabled' : 'disabled' }}
                </span>
                <span>{{ $activeRatesCount }} active jurisdiction {{ $activeRatesCount === 1 ? 'rule' : 'rules' }}</span>
                <span>
                    <span class="trb-check-icon">
                        @include('user_view.taxes.partials.icon', ['name' => 'check', 'size' => 14])
                    </span>
                    Last configuration update saved · {{ $lastSavedLabel }}
                </span>
            </footer>
        </section>
    </div>
@endsection

@push('overlays')
    <div class="trb-popover" id="trb-popover" role="menu" hidden></div>

    @if ($canManageTax)
        <dialog id="tax-behavior-dialog" class="ui-native-dialog trb-drawer" @if ($openBehaviorEditor) open data-auto-open-tax-dialog="behavior" @endif>
            <form class="trb-drawer-form" method="POST" action="{{ route('settings.taxes.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="calculation_address" value="shipping">
                <input type="hidden" name="_tax_editing" value="1">
                <div class="trb-drawer-header">
                    <div>
                        <h2>Edit tax behavior</h2>
                        <p>{{ $selectedStore->name ?? 'This store' }} · Store-wide settings</p>
                    </div>
                    <button class="trb-drawer-close" type="button" data-trb-close-dialog="tax-behavior-dialog" aria-label="Close editor">
                        @include('user_view.taxes.partials.icon', ['name' => 'close', 'size' => 16])
                    </button>
                </div>
                <div class="trb-drawer-body">
                    <div class="trb-switch-row">
                        <div class="trb-switch-copy">
                            <strong>Enable platform tax calculation</strong>
                            <small>Applies configured rates to eligible platform checkouts.</small>
                        </div>
                        <label class="settings-switch">
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $taxSetting->enabled))>
                            <span class="settings-switch-track" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="trb-switch-row">
                        <div class="trb-switch-copy">
                            <strong>Product prices include tax</strong>
                            <small>Catalog prices already include tax when this is on.</small>
                        </div>
                        <label class="settings-switch">
                            <input type="checkbox" name="prices_include_tax" value="1" @checked(old('prices_include_tax', $taxSetting->prices_include_tax))>
                            <span class="settings-switch-track" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="trb-switch-row">
                        <div class="trb-switch-copy">
                            <strong>New products are taxable by default</strong>
                            <small>Applies to new products created after you save this setting.</small>
                        </div>
                        <label class="settings-switch">
                            <input type="checkbox" name="default_product_taxable" value="1" @checked(old('default_product_taxable', $taxSetting->default_product_taxable))>
                            <span class="settings-switch-track" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="trb-switch-row">
                        <div class="trb-switch-copy">
                            <strong>Charge tax on shipping</strong>
                            <small>Adds shipping tax when a matching rate applies.</small>
                        </div>
                        <label class="settings-switch">
                            <input type="checkbox" name="shipping_taxable" value="1" @checked(old('shipping_taxable', $taxSetting->shipping_taxable))>
                            <span class="settings-switch-track" aria-hidden="true"></span>
                        </label>
                    </div>
                    <div class="trb-address">
                        <span class="trb-address-icon" aria-hidden="true">
                            @include('user_view.taxes.partials.icon', ['name' => 'pin', 'size' => 16])
                        </span>
                        <div>
                            <strong>Customer shipping address</strong>
                            <p>Calculation address is fixed for this version. Platform checkout uses the customer shipping address to match configured tax rates.</p>
                        </div>
                    </div>
                    <div class="trb-note">
                        @include('user_view.taxes.partials.icon', ['name' => 'info', 'size' => 17])
                        <span>New-product defaults do not change existing products. Saved order tax snapshots stay unchanged.</span>
                    </div>
                </div>
                <div class="trb-drawer-footer">
                    <p class="text-xs text-[#65738e]">Changes apply to future checkouts only.</p>
                    <div class="trb-drawer-actions">
                        <button class="trb-btn" type="button" data-trb-close-dialog="tax-behavior-dialog">Cancel</button>
                        <button class="trb-btn trb-btn-primary" type="submit">Save tax settings</button>
                    </div>
                </div>
            </form>
        </dialog>

        <dialog id="tax-rate-create-dialog" class="ui-native-dialog trb-drawer" @if ($openCreateRateForm) open data-auto-open-tax-dialog="create" @endif>
            <form class="trb-drawer-form" method="POST" action="{{ route('settings.taxes.rates.store') }}">
                @csrf
                <div class="trb-drawer-header">
                    <div>
                        <h3>Add tax rate</h3>
                        <p>Leave region blank to apply this rate across the entire country.</p>
                    </div>
                    <a class="trb-drawer-close" href="{{ route('settings.taxes.index') }}" aria-label="Close editor">
                        @include('user_view.taxes.partials.icon', ['name' => 'close', 'size' => 16])
                    </a>
                </div>
                <div class="trb-drawer-body">
                    @include('user_view.partials.tax_rate_form_fields', [
                        'errorBag' => $createErrorBag,
                        'values' => $createValues,
                        'countries' => $countries,
                        'showPriorityField' => false,
                        'formId' => 'tax-rate-create-form',
                        'preferRegional' => $preferCreateRegional,
                    ])
                </div>
                <div class="trb-drawer-footer">
                    <p class="text-xs text-[#65738e]">One rate per country and region. Changes apply to future checkouts only.</p>
                    <div class="trb-drawer-actions">
                        <a class="trb-btn" href="{{ route('settings.taxes.index') }}">Cancel</a>
                        <button class="trb-btn trb-btn-primary" type="submit">Save rate</button>
                    </div>
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
                <dialog id="tax-rate-edit-dialog-{{ $rate->id }}" class="ui-native-dialog trb-drawer" open data-auto-open-tax-dialog="edit">
                    <form class="trb-drawer-form" method="POST" action="{{ route('settings.taxes.rates.update', $rate) }}">
                        @csrf
                        @method('PATCH')
                        <div class="trb-drawer-header">
                            <div>
                                <h3>Edit tax rate</h3>
                                <p>{{ $rate->name }}</p>
                            </div>
                            <a class="trb-drawer-close" href="{{ route('settings.taxes.index', ['rate' => $rate->id]) }}" aria-label="Close editor">
                                @include('user_view.taxes.partials.icon', ['name' => 'close', 'size' => 16])
                            </a>
                        </div>
                        <div class="trb-drawer-body">
                            @include('user_view.partials.tax_rate_form_fields', [
                                'errorBag' => $editErrorBag,
                                'values' => $editValues,
                                'countries' => $countries,
                                'showPriorityField' => (int) $rate->priority !== 100 || $errors->getBag($editErrorBag)->has('priority'),
                                'formId' => 'tax-rate-edit-form-'.$rate->id,
                                'preferRegional' => filled($editValues['region_code'] ?? null) || old('tax_coverage') === 'regional',
                            ])
                        </div>
                        <div class="trb-drawer-footer">
                            <p class="text-xs text-[#65738e]">Existing order snapshots are not changed.</p>
                            <div class="trb-drawer-actions">
                                <a class="trb-btn" href="{{ route('settings.taxes.index', ['rate' => $rate->id]) }}">Cancel</a>
                                <button class="trb-btn trb-btn-primary" type="submit">Save rate</button>
                            </div>
                        </div>
                    </form>
                </dialog>
            @endif

            <form
                id="trb-delete-{{ $rate->id }}"
                class="hidden"
                method="POST"
                action="{{ route('settings.taxes.rates.destroy', $rate) }}"
                data-ui-confirm="Existing checkout snapshots are not changed."
                data-ui-confirm-title="Remove this tax rate?"
                data-ui-confirm-action="Remove rate"
            >
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
@endpush

@push('scripts')
    <script type="application/json" id="tax-region-catalog">@json($regionCatalog ?? [])</script>
    <script>
    (() => {
        const regionCatalog = JSON.parse(document.getElementById('tax-region-catalog')?.textContent || '{}');
        const root = document.querySelector('[data-trb-root]');

        const isRegionalCoverage = (formRoot) =>
            formRoot.querySelector('[data-tax-coverage]:checked')?.value === 'regional';

        const setCoverage = (formRoot, regional) => {
            formRoot.querySelectorAll('[data-tax-coverage]').forEach((radio) => {
                radio.checked = radio.value === (regional ? 'regional' : 'country');
            });
        };

        const setBlankRegionControl = (formRoot, enabled) => {
            const blank = formRoot.querySelector('[data-tax-rate-region-blank]');
            if (!blank) return;
            blank.disabled = !enabled;
            if (enabled) blank.setAttribute('name', 'region_code');
            else blank.removeAttribute('name');
        };

        const syncRegionFields = (formRoot, { preserveRegion = false } = {}) => {
            const countrySelect = formRoot.querySelector('[data-tax-rate-country-select]');
            const regionSelect = formRoot.querySelector('[data-tax-rate-region-select]');
            const regionText = formRoot.querySelector('[data-tax-rate-region-text]');
            const regionWrapper = formRoot.querySelector('[data-tax-rate-region-wrapper]');
            if (!countrySelect || !regionSelect || !regionText) return;

            const regional = isRegionalCoverage(formRoot);
            if (regionWrapper) regionWrapper.hidden = !regional;

            if (!regional) {
                regionSelect.innerHTML = '<option value="">Country-wide (all regions)</option>';
                regionSelect.classList.add('hidden');
                regionSelect.disabled = true;
                regionSelect.removeAttribute('name');
                regionText.classList.add('hidden');
                regionText.disabled = true;
                regionText.removeAttribute('name');
                regionText.value = '';
                setBlankRegionControl(formRoot, true);
                return;
            }

            setBlankRegionControl(formRoot, false);

            const country = (countrySelect.value || '').toUpperCase();
            const regions = regionCatalog[country] || null;
            const hasRegions = regions && Object.keys(regions).length > 0;
            const initialRegion = (formRoot.dataset.initialRegion || '').toUpperCase();
            const current = preserveRegion
                ? (regionSelect.value || regionText.value || initialRegion)
                : '';

            if (hasRegions) {
                regionSelect.innerHTML = '<option value="">Country-wide (all regions)</option>';
                Object.entries(regions).forEach(([code, label]) => {
                    const option = document.createElement('option');
                    option.value = code;
                    option.textContent = `${label} (${code})`;
                    if (code === current) option.selected = true;
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

        document.querySelectorAll('[data-tax-rate-form-fields]').forEach((formRoot) => {
            const countrySelect = formRoot.querySelector('[data-tax-rate-country-select]');
            countrySelect?.addEventListener('change', () => syncRegionFields(formRoot, { preserveRegion: false }));
            formRoot.querySelectorAll('[data-tax-coverage]').forEach((radio) => {
                radio.addEventListener('change', () => syncRegionFields(formRoot, { preserveRegion: true }));
            });
            syncRegionFields(formRoot, { preserveRegion: true });
        });

        const closeOtherTaxDrawers = (keep) => {
            document.querySelectorAll('dialog.trb-drawer[open]').forEach((dialog) => {
                if (dialog !== keep) {
                    try { dialog.close(); } catch (error) {}
                }
            });
        };

        const upgradeDialogToModal = (dialog) => {
            if (typeof dialog.showModal !== 'function') return;
            try {
                closeOtherTaxDrawers(dialog);
                if (dialog.hasAttribute('open')) dialog.close();
                dialog.showModal();
            } catch (error) {}
        };

        const focusTaxDialogField = (dialog) => {
            const invalid = dialog.querySelector('[aria-invalid="true"]');
            const field = invalid || dialog.querySelector('input[name="name"], input[type="checkbox"]');
            field?.focus({ preventScroll: true });
        };

        const createDialog = document.getElementById('tax-rate-create-dialog');
        const openCreateLinks = document.querySelectorAll('[data-open-tax-rate-create]');

        const prefillCreateCountry = (code) => {
            if (!createDialog || !code) return;
            const countrySelect = createDialog.querySelector('[data-tax-rate-country-select]');
            const fields = createDialog.querySelector('[data-tax-rate-form-fields]');
            if (!countrySelect) return;
            countrySelect.value = code;
            if (fields) {
                setCoverage(fields, true);
                syncRegionFields(fields, { preserveRegion: false });
            }
        };

        openCreateLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                if (!createDialog || typeof createDialog.showModal !== 'function') return;
                event.preventDefault();
                try {
                    closeOtherTaxDrawers(createDialog);
                    if (createDialog.hasAttribute('open')) createDialog.close();
                    createDialog.showModal();
                    prefillCreateCountry(link.getAttribute('data-trb-country-prefill'));
                    focusTaxDialogField(createDialog);
                } catch (error) {}
            });
        });

        document.querySelectorAll('[data-auto-open-tax-dialog]').forEach((dialog) => {
            upgradeDialogToModal(dialog);
            focusTaxDialogField(dialog);
        });

        if (!root) return;

        const workspace = root.querySelector('[data-trb-workspace]');
        const popover = document.getElementById('trb-popover');
        const searchInput = document.getElementById('trb-search');
        const state = {
            section: root.getAttribute('data-trb-section') || 'jurisdictions',
            selected: root.getAttribute('data-trb-selected') || '',
            mobileDetail: false
        };
        let menuReturn = null;

        const bySel = (selector) => root.querySelector(selector);
        const closeMenu = (restore) => {
            if (!popover) return;
            const trigger = menuReturn;
            popover.hidden = true;
            trigger?.setAttribute('aria-expanded', 'false');
            menuReturn = null;
            if (restore && trigger?.isConnected) trigger.focus();
        };

        const setSection = (section) => {
            if (!['behavior', 'jurisdictions', 'legal'].includes(section)) return;
            state.section = section;
            root.setAttribute('data-trb-section', section);
            root.querySelectorAll('[data-trb-section]').forEach((el) => {
                if (!el.classList.contains('trb-section-btn') && el.tagName !== 'BUTTON' && el.tagName !== 'A') return;
                const on = el.getAttribute('data-trb-section') === section && el.classList.contains('trb-section-btn');
                if (el.classList.contains('trb-section-btn')) {
                    el.classList.toggle('is-active', on);
                    if (on) el.setAttribute('aria-current', 'page');
                    else el.removeAttribute('aria-current');
                }
            });
            const showRates = section === 'jurisdictions';
            root.querySelectorAll('[data-trb-panel]').forEach((panel) => {
                const name = panel.getAttribute('data-trb-panel');
                if (name === 'jurisdictions' || name === 'inspector') panel.hidden = !showRates;
                else panel.hidden = name !== section;
            });
            if (workspace) workspace.classList.toggle('is-mobile-detail', state.mobileDetail && showRates);
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('section', section);
                if (showRates && state.selected) url.searchParams.set('rate', state.selected);
                else url.searchParams.delete('rate');
                window.history.replaceState({}, '', url);
            } catch (e) {}
            closeMenu(false);
        };

        const selectRate = (id, mobile) => {
            state.selected = String(id || '');
            root.setAttribute('data-trb-selected', state.selected);
            root.querySelectorAll('[data-trb-select-rate]').forEach((el) => {
                const on = el.getAttribute('data-trb-select-rate') === state.selected;
                el.classList.toggle('is-active', on);
                if (on) el.setAttribute('aria-current', 'true');
                else el.removeAttribute('aria-current');
            });
            root.querySelectorAll('[data-trb-inspector]').forEach((el) => {
                el.hidden = el.getAttribute('data-trb-inspector') !== state.selected;
            });
            if (mobile) state.mobileDetail = true;
            if (workspace) workspace.classList.toggle('is-mobile-detail', state.mobileDetail && state.section === 'jurisdictions');
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('section', 'jurisdictions');
                if (state.selected) url.searchParams.set('rate', state.selected);
                window.history.replaceState({}, '', url);
            } catch (e) {}
        };

        const filterSearch = () => {
            const query = (searchInput?.value || '').trim().toLowerCase();
            const clearBtns = root.querySelectorAll('[data-trb-clear-search]');
            clearBtns.forEach((btn) => { if (btn.classList.contains('trb-search-clear')) btn.hidden = !query; });
            const groups = root.querySelectorAll('[data-trb-country]');
            let visibleRates = 0;
            let visibleGroups = 0;
            groups.forEach((group) => {
                const rates = group.querySelectorAll('[data-trb-select-rate]');
                let groupVisible = false;
                rates.forEach((rate) => {
                    const hay = (rate.getAttribute('data-trb-search') || '') + ' ' + (group.getAttribute('data-trb-search') || '');
                    const show = !query || hay.includes(query);
                    rate.hidden = !show;
                    if (show) {
                        groupVisible = true;
                        visibleRates += 1;
                    }
                });
                group.hidden = !groupVisible;
                if (groupVisible) {
                    visibleGroups += 1;
                    if (query) {
                        const children = group.querySelector('[id^="trb-children-"]');
                        const toggle = group.querySelector('[data-trb-toggle-country]');
                        if (children) children.hidden = false;
                        toggle?.setAttribute('aria-expanded', 'true');
                    }
                }
            });
            const empty = root.querySelector('[data-trb-search-empty]');
            if (empty) empty.hidden = !query || visibleGroups > 0;
            const status = root.querySelector('[data-trb-search-status]');
            if (status) status.textContent = `${visibleRates} matching rate${visibleRates === 1 ? '' : 's'} in ${visibleGroups} jurisdiction${visibleGroups === 1 ? '' : 's'}.`;
        };

        const openMenu = (trigger, html) => {
            if (!popover) return;
            if (menuReturn === trigger && !popover.hidden) {
                closeMenu();
                return;
            }
            closeMenu(false);
            menuReturn = trigger;
            trigger.setAttribute('aria-expanded', 'true');
            popover.innerHTML = html;
            popover.hidden = false;
            const rect = trigger.getBoundingClientRect();
            const left = Math.max(12, Math.min(rect.right - popover.offsetWidth, window.innerWidth - popover.offsetWidth - 12));
            const top = rect.bottom + 6 + popover.offsetHeight < window.innerHeight - 12
                ? rect.bottom + 6
                : Math.max(12, rect.top - popover.offsetHeight - 6);
            popover.style.left = left + 'px';
            popover.style.top = top + 'px';
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const postRateActive = (trigger, active) => {
            if (!trigger) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = trigger.getAttribute('data-trb-update') || '';
            form.style.display = 'none';
            const fields = {
                _token: csrfToken,
                _method: 'PATCH',
                name: trigger.getAttribute('data-trb-rate-name') || '',
                country_code: trigger.getAttribute('data-trb-country') || '',
                region_code: trigger.getAttribute('data-trb-region') || '',
                rate_percent: trigger.getAttribute('data-trb-percent') || '',
                priority: trigger.getAttribute('data-trb-priority') || '100',
                is_active: active ? '1' : '0'
            };
            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        };

        const submitForm = (id) => {
            const form = document.getElementById(id);
            if (!form) return;
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        };

        const openDialog = (id) => {
            const dialog = document.getElementById(id);
            if (!dialog || typeof dialog.showModal !== 'function') return;
            try {
                closeOtherTaxDrawers(dialog);
                if (dialog.hasAttribute('open')) dialog.close();
                dialog.showModal();
                focusTaxDialogField(dialog);
            } catch (error) {}
        };

        root.querySelectorAll('[data-trb-section]').forEach((el) => {
            el.addEventListener('click', (event) => {
                if (el.tagName === 'A' && el.getAttribute('data-trb-section')) {
                    event.preventDefault();
                }
                if (el.classList.contains('trb-section-btn') || el.getAttribute('data-trb-section')) {
                    setSection(el.getAttribute('data-trb-section'));
                }
            });
        });

        root.querySelectorAll('[data-trb-select-rate]').forEach((el) => {
            el.addEventListener('click', (event) => {
                event.preventDefault();
                selectRate(el.getAttribute('data-trb-select-rate'), window.matchMedia('(max-width:710px)').matches);
                setSection('jurisdictions');
            });
        });

        root.querySelectorAll('[data-trb-toggle-country]').forEach((el) => {
            el.addEventListener('click', () => {
                const code = el.getAttribute('data-trb-toggle-country');
                const children = document.getElementById('trb-children-' + code);
                if (!children) return;
                const expanded = el.getAttribute('aria-expanded') !== 'true';
                el.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                children.hidden = !expanded;
            });
        });

        root.querySelectorAll('[data-trb-clear-search]').forEach((el) => {
            el.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                filterSearch();
                searchInput?.focus();
            });
        });

        root.querySelectorAll('[data-trb-back]').forEach((el) => {
            el.addEventListener('click', () => {
                state.mobileDetail = false;
                if (workspace) workspace.classList.remove('is-mobile-detail');
            });
        });

        root.querySelectorAll('[data-trb-edit-behavior]').forEach((el) => {
            el.addEventListener('click', () => {
                setSection('behavior');
                openDialog('tax-behavior-dialog');
            });
        });

        const pageMenu = root.querySelector('[data-trb-page-menu]');
        if (pageMenu) {
            pageMenu.addEventListener('click', () => {
                openMenu(pageMenu, `
                    <button type="button" data-trb-menu-action="edit-behavior">Edit tax behavior</button>
                    <button type="button" data-trb-menu-action="legal">View legal notice</button>
                `);
            });
        }

        root.querySelectorAll('[data-trb-rate-menu]').forEach((el) => {
            el.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const id = el.getAttribute('data-trb-rate-menu');
                const active = el.getAttribute('data-trb-rate-active') === '1';
                openMenu(el, `
                    <button type="button" data-trb-menu-action="edit-rate" data-id="${id}">Edit rate</button>
                    <button type="button" data-trb-menu-action="${active ? 'deactivate' : 'activate'}" data-id="${id}">${active ? 'Deactivate rate' : 'Activate rate'}</button>
                    <hr>
                    <button class="is-danger" type="button" data-trb-menu-action="delete" data-id="${id}">Delete rate</button>
                `);
            });
        });

        if (popover) {
            popover.addEventListener('click', (event) => {
                const btn = event.target.closest('[data-trb-menu-action]');
                if (!btn) return;
                const action = btn.getAttribute('data-trb-menu-action');
                const id = btn.getAttribute('data-id');
                closeMenu(false);
                if (action === 'edit-behavior') {
                    setSection('behavior');
                    openDialog('tax-behavior-dialog');
                }
                if (action === 'legal') setSection('legal');
                if (action === 'edit-rate' && id) window.location.href = @json(route('settings.taxes.index')) + '?edit_rate=' + encodeURIComponent(id);
                if (action === 'activate' && id) postRateActive(root.querySelector('[data-trb-rate-menu="'+id+'"]'), true);
                if (action === 'deactivate' && id) postRateActive(root.querySelector('[data-trb-rate-menu="'+id+'"]'), false);
                if (action === 'delete' && id) submitForm('trb-delete-' + id);
            });
        }

        document.querySelectorAll('[data-trb-close-dialog]').forEach((el) => {
            el.addEventListener('click', () => {
                const dialog = document.getElementById(el.getAttribute('data-trb-close-dialog'));
                if (dialog?.open) dialog.close();
            });
        });

        searchInput?.addEventListener('input', filterSearch);
        document.addEventListener('pointerdown', (event) => {
            if (popover && !popover.hidden && !popover.contains(event.target) && !menuReturn?.contains(event.target)) closeMenu(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeMenu();
        });
        window.addEventListener('resize', () => closeMenu(false));
        window.addEventListener('scroll', () => { if (popover && !popover.hidden) closeMenu(false); }, { passive: true });
    })();
    </script>
@endpush
