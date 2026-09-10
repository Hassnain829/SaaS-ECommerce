<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxRateRequest;
use App\Http\Requests\UpdateTaxRateRequest;
use App\Http\Requests\UpdateTaxSettingsRequest;
use App\Services\Tax\TaxConfigurationService;
use App\Support\StorePermission;
use App\Support\Tax\TaxCountryCatalog;
use App\Support\Tax\TaxRulebookPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxSettingsController extends Controller
{
    public function __construct(
        private readonly TaxConfigurationService $taxConfiguration,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $taxSetting = $store->taxSetting;

        abort_if(
            ! $taxSetting,
            503,
            'Tax settings are not available for this store right now. Please try again later or contact support.'
        );

        $taxRates = $store->taxRates()
            ->orderBy('priority')
            ->orderBy('country_code')
            ->orderBy('region_code')
            ->orderBy('name')
            ->get();

        $canManageTax = $request->user()?->hasStorePermission($store, StorePermission::SETTINGS_MANAGE) ?? false;
        $editingRateId = (int) (session('_tax_rate_edit_id') ?? $request->integer('edit_rate', 0));
        $openCreateRateForm = session('_tax_rate_form') === 'create'
            || ($canManageTax && $request->boolean('create_rate'));
        $createCountry = strtoupper(trim((string) $request->query('country', '')));

        $rulebook = TaxRulebookPresenter::pageState(
            $taxRates,
            $taxSetting,
            strtolower((string) $request->query('section', '')),
            $request->integer('rate', 0),
            $editingRateId,
            $openCreateRateForm,
        );

        return view('user_view.settings.taxes', array_merge([
            'selectedStore' => $store,
            'taxSetting' => $taxSetting,
            'taxRates' => $taxRates,
            'canManageTax' => $canManageTax,
            'countries' => TaxCountryCatalog::all(),
            'regionCatalog' => TaxCountryCatalog::allRegions(),
            'openCreateRateForm' => $openCreateRateForm,
            'editingRateId' => $editingRateId,
            'createCountryPrefill' => preg_match('/^[A-Z]{2}$/', $createCountry) === 1 ? $createCountry : '',
        ], $rulebook));
    }

    public function update(UpdateTaxSettingsRequest $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $this->taxConfiguration->updateSettings(
            $store,
            $request->validated(),
            $request->user(),
            $request
        );

        return back()
            ->with('success', 'Tax settings updated.')
            ->with('success_title', 'Taxes');
    }

    public function storeRate(StoreTaxRateRequest $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $this->taxConfiguration->createRate(
            $store,
            $request->validated(),
            $request->user(),
            $request
        );

        return redirect()
            ->route('settings.taxes.index')
            ->with('success', 'Tax rate added.')
            ->with('success_title', 'Taxes');
    }

    public function updateRate(UpdateTaxRateRequest $request, int|string $taxRate): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $rate = $store->taxRates()->whereKey($taxRate)->firstOrFail();

        $this->taxConfiguration->updateRate(
            $store,
            $rate,
            $request->validated(),
            $request->user(),
            $request
        );

        return redirect()
            ->route('settings.taxes.index')
            ->with('success', 'Tax rate updated.')
            ->with('success_title', 'Taxes');
    }

    public function destroyRate(Request $request, int|string $taxRate): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $rate = $store->taxRates()->whereKey($taxRate)->firstOrFail();

        $this->taxConfiguration->deleteRate(
            $store,
            $rate,
            $request->user(),
            $request
        );

        return redirect()
            ->route('settings.taxes.index')
            ->with('success', 'Tax rate removed.')
            ->with('success_title', 'Taxes');
    }
}
