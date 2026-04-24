<?php

namespace App\Services\Catalog;

use App\Catalog\ProductImportField;
use App\Support\Catalog\SpreadsheetValueNormalizer;

/**
 * Shared row validation for preview + processor (after normalization rules).
 */
final class ProductImportRowValidator
{
    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $columnMapping  system field => source header
     * @param  list<array{source: string, key: string, scope: string}>  $customFieldMappings
     * @return list<string>
     */
    public static function validateMappedRow(array $row, array $columnMapping, array $customFieldMappings = []): array
    {
        $structured = ProductImportField::usesStructuredVariantRows($columnMapping);

        if ($structured) {
            return self::validateStructuredVariantRow($row, $columnMapping, $customFieldMappings);
        }

        return self::validateSimpleCatalogRow($row, $columnMapping, $customFieldMappings);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $columnMapping
     * @param  list<array{source: string, key: string, scope: string}>  $customFieldMappings
     * @return list<string>
     */
    private static function validateSimpleCatalogRow(array $row, array $columnMapping, array $customFieldMappings): array
    {
        $errors = [];
        $nameCol = $columnMapping[ProductImportField::PRODUCT_NAME] ?? null;
        $skuCol = $columnMapping[ProductImportField::SKU] ?? null;
        if (! is_string($nameCol) || $nameCol === '') {
            $errors[] = 'Mapping must include product name.';
        } else {
            $name = trim((string) ($row[$nameCol] ?? ''));
            if ($name === '') {
                $errors[] = 'Product name is empty.';
            }
        }
        if (! is_string($skuCol) || $skuCol === '') {
            $errors[] = 'Mapping must include SKU.';
        } else {
            $sku = trim((string) ($row[$skuCol] ?? ''));
            if ($sku === '') {
                $errors[] = 'SKU is empty.';
            }
        }

        self::appendNumericCustomChecks($errors, $row, $columnMapping, $customFieldMappings);

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $columnMapping
     * @param  list<array{source: string, key: string, scope: string}>  $customFieldMappings
     * @return list<string>
     */
    private static function validateStructuredVariantRow(array $row, array $columnMapping, array $customFieldMappings): array
    {
        $errors = [];
        $nameCol = $columnMapping[ProductImportField::PRODUCT_NAME] ?? null;
        if (! is_string($nameCol) || $nameCol === '') {
            $errors[] = 'Mapping must include product name.';
        } else {
            $name = trim((string) ($row[$nameCol] ?? ''));
            if ($name === '') {
                $errors[] = 'Product name is empty.';
            }
        }

        $parentCol = $columnMapping[ProductImportField::PARENT_SKU] ?? null;
        $skuCol = $columnMapping[ProductImportField::SKU] ?? null;
        $hasParentCol = is_string($parentCol) && $parentCol !== '';
        $hasSkuCol = is_string($skuCol) && $skuCol !== '';
        if (! $hasParentCol && ! $hasSkuCol) {
            $errors[] = 'Map either Parent product SKU or Product SKU so rows can be grouped safely.';
        }
        if ($hasParentCol) {
            $p = trim((string) ($row[$parentCol] ?? ''));
            if ($p === '') {
                $errors[] = 'Parent product SKU is empty on this row.';
            }
        }

        foreach ([1, 2, 3] as $slot) {
            $nk = ProductImportField::optionNameField($slot);
            $vk = ProductImportField::optionValueField($slot);
            $nCol = $columnMapping[$nk] ?? null;
            $vCol = $columnMapping[$vk] ?? null;
            $nMapped = is_string($nCol) && $nCol !== '';
            $vMapped = is_string($vCol) && $vCol !== '';
            if ($nMapped xor $vMapped) {
                $errors[] = 'Option group label and value columns must be mapped together for each option slot.';

                break;
            }
            if (! $nMapped) {
                continue;
            }
            $nv = trim((string) ($row[$nCol] ?? ''));
            $vv = trim((string) ($row[$vCol] ?? ''));
            if ($nv === '' && $vv !== '') {
                $errors[] = 'This row has an option value without a group label.';

                break;
            }
            if ($nv !== '' && $vv === '') {
                $errors[] = 'This row has an option group label without a value.';

                break;
            }
        }

        $priceCol = $columnMapping[ProductImportField::VARIANT_PRICE] ?? null;
        if (! $priceCol) {
            $priceCol = $columnMapping[ProductImportField::BASE_PRICE] ?? null;
        }
        if (is_string($priceCol) && $priceCol !== '') {
            $p = (string) ($row[$priceCol] ?? '');
            if (trim($p) !== '' && ! SpreadsheetValueNormalizer::isValidDecimalCell($p)) {
                $errors[] = 'Variant price is not a valid number.';
            } elseif (trim($p) !== '') {
                $n = SpreadsheetValueNormalizer::normalizeDecimal($p);
                if ($n !== null && $n < 0) {
                    $errors[] = 'Variant price cannot be negative.';
                }
            }
        }

        $stockCol = $columnMapping[ProductImportField::VARIANT_STOCK] ?? null;
        if (! $stockCol) {
            $stockCol = $columnMapping[ProductImportField::STOCK] ?? null;
        }
        if (is_string($stockCol) && $stockCol !== '') {
            $s = (string) ($row[$stockCol] ?? '');
            if (trim($s) !== '' && ! SpreadsheetValueNormalizer::isValidIntegerCell($s)) {
                $errors[] = 'Variant stock must be a whole number.';
            }
        }

        $lowCol = $columnMapping[ProductImportField::VARIANT_STOCK_ALERT] ?? null;
        if (! $lowCol) {
            $lowCol = $columnMapping[ProductImportField::LOW_STOCK_THRESHOLD] ?? null;
        }
        if (is_string($lowCol) && $lowCol !== '') {
            $s = (string) ($row[$lowCol] ?? '');
            if (trim($s) !== '' && ! SpreadsheetValueNormalizer::isValidIntegerCell($s)) {
                $errors[] = 'Variant low stock alert must be a whole number.';
            }
        }

        $vCompareCol = $columnMapping[ProductImportField::VARIANT_COMPARE_AT_PRICE] ?? null;
        if (! $vCompareCol) {
            $vCompareCol = $columnMapping[ProductImportField::COMPARE_AT_PRICE] ?? null;
        }
        if (is_string($vCompareCol) && $vCompareCol !== '') {
            $p = (string) ($row[$vCompareCol] ?? '');
            if (trim($p) !== '' && ! SpreadsheetValueNormalizer::isValidDecimalCell($p)) {
                $errors[] = 'Variant compare-at price is not a valid number.';
            }
        }

        $imgCol = $columnMapping[ProductImportField::VARIANT_IMAGE_URL] ?? null;
        if (is_string($imgCol) && $imgCol !== '') {
            $u = trim((string) ($row[$imgCol] ?? ''));
            if ($u !== '' && ! preg_match('#^https?://#i', $u)) {
                $errors[] = 'Variant image URL must start with http:// or https://.';
            }
        }

        self::appendNumericCustomChecks($errors, $row, $columnMapping, $customFieldMappings);

        return $errors;
    }

    /**
     * @param  list<string>  $errors
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $columnMapping
     * @param  list<array{source: string, key: string, scope: string}>  $customFieldMappings
     */
    private static function appendNumericCustomChecks(
        array &$errors,
        array $row,
        array $columnMapping,
        array $customFieldMappings,
    ): void {
        if (! ProductImportField::usesStructuredVariantRows($columnMapping)) {
            $priceField = ProductImportField::BASE_PRICE;
            if (! empty($columnMapping[$priceField]) && is_string($columnMapping[$priceField])) {
                $p = (string) ($row[$columnMapping[$priceField]] ?? '');
                if (trim($p) !== '' && ! SpreadsheetValueNormalizer::isValidDecimalCell($p)) {
                    $errors[] = 'Base price is not a valid number.';
                } elseif (trim($p) !== '') {
                    $n = SpreadsheetValueNormalizer::normalizeDecimal($p);
                    if ($n !== null && $n < 0) {
                        $errors[] = 'Base price cannot be negative.';
                    }
                }
            }

            $stockField = ProductImportField::STOCK;
            if (! empty($columnMapping[$stockField]) && is_string($columnMapping[$stockField])) {
                $s = (string) ($row[$columnMapping[$stockField]] ?? '');
                if (trim($s) !== '' && ! SpreadsheetValueNormalizer::isValidIntegerCell($s)) {
                    $errors[] = 'Stock must be a whole number (spreadsheet formats like 1,200 or 10.0 are accepted).';
                }
            }

            $lowStock = ProductImportField::LOW_STOCK_THRESHOLD;
            if (! empty($columnMapping[$lowStock]) && is_string($columnMapping[$lowStock])) {
                $s = (string) ($row[$columnMapping[$lowStock]] ?? '');
                if (trim($s) !== '' && ! SpreadsheetValueNormalizer::isValidIntegerCell($s)) {
                    $errors[] = 'Low stock threshold must be a whole number.';
                }
            }

            $compareField = ProductImportField::COMPARE_AT_PRICE;
            if (! empty($columnMapping[$compareField]) && is_string($columnMapping[$compareField])) {
                $p = (string) ($row[$columnMapping[$compareField]] ?? '');
                if (trim($p) !== '' && ! SpreadsheetValueNormalizer::isValidDecimalCell($p)) {
                    $errors[] = 'Compare-at price is not a valid number.';
                }
            }

            $costField = ProductImportField::COST_PRICE;
            if (! empty($columnMapping[$costField]) && is_string($columnMapping[$costField])) {
                $p = (string) ($row[$columnMapping[$costField]] ?? '');
                if (trim($p) !== '' && ! SpreadsheetValueNormalizer::isValidDecimalCell($p)) {
                    $errors[] = 'Cost price is not a valid number.';
                }
            }
        }

        foreach ($customFieldMappings as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $src = trim((string) ($entry['source'] ?? ''));
            $key = trim((string) ($entry['key'] ?? ''));
            if ($src === '' || $key === '') {
                continue;
            }
            $val = (string) ($row[$src] ?? '');
            if (strlen($val) > 5000) {
                $errors[] = 'Custom field '.$key.' value is too long.';
            }
        }
    }
}
