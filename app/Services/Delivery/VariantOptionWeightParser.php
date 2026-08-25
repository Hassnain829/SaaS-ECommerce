<?php

namespace App\Services\Delivery;

/**
 * Parses numeric weight labels from variant option values (e.g. "5 lb", "16 oz").
 *
 * Bare numbers are never treated as LB by default. They are only accepted as the
 * store's canonical unit when the option group is clearly weight-related.
 */
final class VariantOptionWeightParser
{
    /**
     * Unmistakably weight-specific option names only.
     * Do NOT include "Pack size" — that usually means quantity (6-pack), not mass.
     */
    private const WEIGHT_OPTION_PATTERN = '/\b((?:item|shipping|net|package|pack)\s+)?weight\b/i';

    private const UNIT_PATTERN = 'lb|lbs|pound|pounds|oz|ounce|ounces|kg|kilogram|kilograms|g|gram|grams';

    public function optionGroupLooksWeightRelated(string $optionGroupName): bool
    {
        return (bool) preg_match(self::WEIGHT_OPTION_PATTERN, trim($optionGroupName));
    }

    public function labelHasExplicitWeightUnit(string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }

        return (bool) preg_match('/^[\d.,]+\s*(?:'.self::UNIT_PATTERN.')\.?$/iu', $label);
    }

    public function isBareNumericLabel(string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }

        return (bool) preg_match('/^[\d.,]+$/u', $label);
    }

    /**
     * Convert an explicit-unit label (e.g. "5 lb", "16 oz") into the store unit.
     * Bare numbers always return null — use {@see parseBareNumberAsStoreUnit()} instead.
     */
    public function parseToStoreUnit(string $label, string $storeUnit): ?float
    {
        $label = trim($label);
        if ($label === '' || ! $this->labelHasExplicitWeightUnit($label)) {
            return null;
        }

        if (! preg_match('/^([\d.,]+)\s*('.self::UNIT_PATTERN.')\.?$/iu', $label, $matches)) {
            return null;
        }

        $numeric = (float) str_replace(',', '', $matches[1]);
        if ($numeric <= 0) {
            return null;
        }

        $rawUnit = strtolower(trim((string) $matches[2]));
        $weightLb = match ($rawUnit) {
            'oz', 'ounce', 'ounces' => $numeric / 16,
            'g', 'gram', 'grams' => $numeric / 453.592,
            'kg', 'kilogram', 'kilograms' => $numeric * 2.20462,
            'lb', 'lbs', 'pound', 'pounds' => $numeric,
            default => null,
        };

        if ($weightLb === null) {
            return null;
        }

        $storeUnit = strtoupper(trim($storeUnit)) ?: 'LB';
        $converted = $storeUnit === 'KG'
            ? $weightLb / 2.20462
            : $weightLb;

        return $this->clampToStoreUnitMax($converted, $storeUnit);
    }

    /**
     * Interpret a bare number as already expressed in the store's canonical unit.
     */
    public function parseBareNumberAsStoreUnit(string $label, string $storeUnit): ?float
    {
        if (! $this->isBareNumericLabel($label)) {
            return null;
        }

        $numeric = (float) str_replace(',', '', trim($label));
        if ($numeric <= 0) {
            return null;
        }

        $storeUnit = strtoupper(trim($storeUnit)) ?: 'LB';

        return $this->clampToStoreUnitMax($numeric, $storeUnit);
    }

    /**
     * @param  list<string>  $optionValues
     * @return array<string, float|null>
     */
    public function buildWeightMapFromOptionValues(array $optionValues, string $storeUnit, bool $assumeStoreUnitForBareNumbers = false): array
    {
        $map = [];
        foreach ($optionValues as $value) {
            $label = trim((string) $value);
            if ($label === '') {
                continue;
            }

            $parsed = $this->parseToStoreUnit($label, $storeUnit);
            if ($parsed === null && $assumeStoreUnitForBareNumbers) {
                $parsed = $this->parseBareNumberAsStoreUnit($label, $storeUnit);
            }

            $map[$label] = $parsed;
        }

        return $map;
    }

    /**
     * Safe for automatic "use option values as weights" when:
     * - the option group is weight-related (bare numbers = store unit), or
     * - every option value has an explicit recognized weight unit.
     *
     * @param  list<string>  $optionValues
     */
    public function isSafeForAutomaticWeightParse(string $optionGroupName, array $optionValues): bool
    {
        $labels = [];
        foreach ($optionValues as $value) {
            $label = trim((string) $value);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        if ($labels === []) {
            return false;
        }

        if ($this->optionGroupLooksWeightRelated($optionGroupName)) {
            return true;
        }

        foreach ($labels as $label) {
            if (! $this->labelHasExplicitWeightUnit($label)) {
                return false;
            }
        }

        return true;
    }

    private function clampToStoreUnitMax(float $value, string $storeUnit): ?float
    {
        $max = app(StoreShippingPreferences::class)->maxItemWeightForUnit($storeUnit);
        if ($value <= 0 || $value > $max) {
            return null;
        }

        return max(0.01, round($value, 3));
    }
}
