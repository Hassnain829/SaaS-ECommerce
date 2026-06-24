<?php

namespace App\Services\Tax;

use App\Data\Tax\ItemTaxAllocation;
use App\Data\Tax\MatchedTaxRate;
use App\Data\Tax\TaxAddressInput;
use App\Data\Tax\TaxCalculationRequest;
use App\Data\Tax\TaxCalculationResult;
use App\Data\Tax\TaxLineItemInput;
use App\Data\Tax\TaxLineOutput;
use App\Models\Store;
use App\Models\TaxRate;
use App\Support\Money\CurrencyPrecision;

class TaxCalculator
{
    public function calculate(TaxCalculationRequest $request): TaxCalculationResult
    {
        $currency = $request->currencyCode;
        $settings = $request->settings;
        $settingsVersion = (int) $settings->settings_version;
        $zero = $this->zeroAmount($currency);

        if (! $settings->enabled) {
            return $this->buildZeroTaxResult(
                $request,
                $settingsVersion,
                taxCalculationSkipped: false,
                skipReason: null,
                matchedRate: null,
            );
        }

        $destination = $request->destination;

        if ($destination->countryCode === '') {
            return $this->buildZeroTaxResult(
                $request,
                $settingsVersion,
                taxCalculationSkipped: true,
                skipReason: TaxCalculationResult::SKIP_REASON_MISSING_COUNTRY,
                matchedRate: null,
            );
        }

        $matchedRate = $this->matchRate($request->store, $destination);

        if ($matchedRate === null) {
            return $this->buildZeroTaxResult(
                $request,
                $settingsVersion,
                taxCalculationSkipped: false,
                skipReason: null,
                matchedRate: null,
            );
        }

        $allocations = [];
        $itemsSubtotal = $zero;
        $taxableItemsSubtotal = $zero;
        $itemsTax = $zero;

        foreach ($request->items as $item) {
            $allocation = $this->allocateItemTax(
                $item,
                $currency,
                $settings->prices_include_tax,
                $matchedRate->ratePercent,
            );

            $allocations[] = $allocation;
            $itemsSubtotal = CurrencyPrecision::roundMajor(
                bcadd($itemsSubtotal, $allocation->lineSubtotal, 6),
                $currency,
            );
            $taxableItemsSubtotal = CurrencyPrecision::roundMajor(
                bcadd($taxableItemsSubtotal, $allocation->taxableAmount, 6),
                $currency,
            );
            $itemsTax = CurrencyPrecision::roundMajor(
                bcadd($itemsTax, $allocation->taxAmount, 6),
                $currency,
            );
        }

        $shippingAmount = CurrencyPrecision::roundMajor($request->shippingAmount, $currency);
        $shippingTax = $zero;

        if (
            $settings->shipping_taxable
            && bccomp($shippingAmount, $zero, 6) > 0
        ) {
            $shippingTax = $this->calculateExclusiveTax($shippingAmount, $matchedRate->ratePercent, $currency);
        }

        $totalTax = CurrencyPrecision::roundMajor(
            bcadd($itemsTax, $shippingTax, 6),
            $currency,
        );

        $taxLines = $this->buildTaxLines(
            $matchedRate,
            $settingsVersion,
            $taxableItemsSubtotal,
            $itemsTax,
            $shippingAmount,
            $shippingTax,
            $currency,
            $settings->shipping_taxable,
        );

        return new TaxCalculationResult(
            itemsSubtotal: $itemsSubtotal,
            taxableItemsSubtotal: $taxableItemsSubtotal,
            itemsTax: $itemsTax,
            shippingTax: $shippingTax,
            totalTax: $totalTax,
            itemAllocations: $allocations,
            taxLines: $taxLines,
            settingsVersion: $settingsVersion,
            matchedRate: $matchedRate,
            taxCalculationSkipped: false,
            skipReason: null,
        );
    }

    private function buildZeroTaxResult(
        TaxCalculationRequest $request,
        int $settingsVersion,
        bool $taxCalculationSkipped,
        ?string $skipReason,
        ?MatchedTaxRate $matchedRate,
    ): TaxCalculationResult {
        $currency = $request->currencyCode;
        $zero = $this->zeroAmount($currency);
        $allocations = [];
        $itemsSubtotal = $zero;

        foreach ($request->items as $item) {
            $lineSubtotal = $this->lineSubtotal($item, $currency);
            $allocations[] = new ItemTaxAllocation(
                lineKey: $item->lineKey,
                lineSubtotal: $lineSubtotal,
                taxableAmount: $zero,
                taxAmount: $zero,
            );
            $itemsSubtotal = CurrencyPrecision::roundMajor(
                bcadd($itemsSubtotal, $lineSubtotal, 6),
                $currency,
            );
        }

        return new TaxCalculationResult(
            itemsSubtotal: $itemsSubtotal,
            taxableItemsSubtotal: $zero,
            itemsTax: $zero,
            shippingTax: $zero,
            totalTax: $zero,
            itemAllocations: $allocations,
            taxLines: [],
            settingsVersion: $settingsVersion,
            matchedRate: $matchedRate,
            taxCalculationSkipped: $taxCalculationSkipped,
            skipReason: $skipReason,
        );
    }

    private function allocateItemTax(
        TaxLineItemInput $item,
        string $currency,
        bool $pricesIncludeTax,
        string $ratePercent,
    ): ItemTaxAllocation {
        $lineSubtotal = $this->lineSubtotal($item, $currency);
        $zero = $this->zeroAmount($currency);

        if (! $item->isTaxable) {
            return new ItemTaxAllocation(
                lineKey: $item->lineKey,
                lineSubtotal: $lineSubtotal,
                taxableAmount: $zero,
                taxAmount: $zero,
            );
        }

        if ($pricesIncludeTax) {
            return $this->allocateInclusiveItemTax($item->lineKey, $lineSubtotal, $ratePercent, $currency);
        }

        $taxableAmount = $lineSubtotal;
        $taxAmount = $this->calculateExclusiveTax($taxableAmount, $ratePercent, $currency);

        return new ItemTaxAllocation(
            lineKey: $item->lineKey,
            lineSubtotal: $lineSubtotal,
            taxableAmount: $taxableAmount,
            taxAmount: $taxAmount,
        );
    }

    private function allocateInclusiveItemTax(
        string $lineKey,
        string $grossLineSubtotal,
        string $ratePercent,
        string $currency,
    ): ItemTaxAllocation {
        $divisor = bcadd('1', bcdiv($ratePercent, '100', 6), 6);
        $rawNet = bcdiv($grossLineSubtotal, $divisor, 6);
        $rawExtractedTax = bcsub($grossLineSubtotal, $rawNet, 6);
        $taxAmount = CurrencyPrecision::roundMajor($rawExtractedTax, $currency);
        $taxableAmount = CurrencyPrecision::roundMajor(
            bcsub($grossLineSubtotal, $taxAmount, 6),
            $currency,
        );

        return new ItemTaxAllocation(
            lineKey: $lineKey,
            lineSubtotal: $grossLineSubtotal,
            taxableAmount: $taxableAmount,
            taxAmount: $taxAmount,
        );
    }

    private function calculateExclusiveTax(string $taxableAmount, string $ratePercent, string $currency): string
    {
        $rawTax = bcdiv(bcmul($taxableAmount, $ratePercent, 6), '100', 6);

        return CurrencyPrecision::roundMajor($rawTax, $currency);
    }

    private function lineSubtotal(TaxLineItemInput $item, string $currency): string
    {
        $raw = bcmul($item->unitPrice, (string) $item->quantity, 6);

        return CurrencyPrecision::roundMajor($raw, $currency);
    }

    /**
     * @return list<TaxLineOutput>
     */
    private function buildTaxLines(
        MatchedTaxRate $matchedRate,
        int $settingsVersion,
        string $taxableItemsSubtotal,
        string $itemsTax,
        string $shippingAmount,
        string $shippingTax,
        string $currency,
        bool $shippingTaxable,
    ): array {
        $zero = $this->zeroAmount($currency);
        $taxLines = [];

        if (bccomp($taxableItemsSubtotal, $zero, 6) > 0) {
            $taxLines[] = new TaxLineOutput(
                taxRateId: $matchedRate->taxRateId,
                jurisdictionCountryCode: $matchedRate->countryCode,
                jurisdictionRegionCode: $matchedRate->regionCode,
                ratePercent: $matchedRate->ratePercent,
                taxableAmount: $taxableItemsSubtotal,
                taxAmount: $itemsTax,
                appliesTo: TaxLineOutput::APPLIES_TO_ITEMS,
                settingsVersion: $settingsVersion,
            );
        }

        if (
            $shippingTaxable
            && bccomp($shippingAmount, $zero, 6) > 0
        ) {
            $taxLines[] = new TaxLineOutput(
                taxRateId: $matchedRate->taxRateId,
                jurisdictionCountryCode: $matchedRate->countryCode,
                jurisdictionRegionCode: $matchedRate->regionCode,
                ratePercent: $matchedRate->ratePercent,
                taxableAmount: $shippingAmount,
                taxAmount: $shippingTax,
                appliesTo: TaxLineOutput::APPLIES_TO_SHIPPING,
                settingsVersion: $settingsVersion,
            );
        }

        return $taxLines;
    }

    private function matchRate(Store $store, TaxAddressInput $destination): ?MatchedTaxRate
    {
        $country = $destination->countryCode;
        $region = $destination->regionCode;

        if ($region !== '') {
            $regionalRate = TaxRate::query()
                ->forStore($store->id)
                ->active()
                ->forJurisdiction($country, $region)
                ->orderBy('priority')
                ->orderBy('country_code')
                ->orderBy('region_code')
                ->orderBy('name')
                ->first();

            if ($regionalRate !== null) {
                return MatchedTaxRate::fromModel($regionalRate);
            }
        }

        $countryWideRate = TaxRate::query()
            ->forStore($store->id)
            ->active()
            ->forJurisdiction($country, '')
            ->orderBy('priority')
            ->orderBy('country_code')
            ->orderBy('region_code')
            ->orderBy('name')
            ->first();

        if ($countryWideRate !== null) {
            return MatchedTaxRate::fromModel($countryWideRate);
        }

        return null;
    }

    private function zeroAmount(string $currency): string
    {
        return CurrencyPrecision::roundMajor('0', $currency);
    }
}
