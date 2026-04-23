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

        return $errors;
    }
}
