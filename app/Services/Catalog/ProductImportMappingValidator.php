<?php

namespace App\Services\Catalog;

use App\Catalog\ProductImportField;

/**
 * Shared validation for manual and auto-detected import column mappings.
 */
final class ProductImportMappingValidator
{
    /**
     * @param  array<string, mixed>  $mapping
     * @param  list<string|int>  $headers
     * @param  array<int, mixed>  $rawCustom
     * @return list<string>
     */
    public static function validate(array $mapping, array $headers, array $rawCustom): array
    {
        $errors = [];

        $headerSet = array_flip(array_filter($headers, static fn ($h) => is_string($h) && $h !== ''));

        if (ProductImportField::hasPartialOptionSlotMapping($mapping)) {
            $errors[] = 'For each option slot, map both the group label column and the value column, or leave that slot unused.';

            return $errors;
        }

        $structuredVariant = ProductImportField::usesStructuredVariantRows($mapping);
        $hasParentSkuColumn = is_string($mapping[ProductImportField::PARENT_SKU] ?? null) && $mapping[ProductImportField::PARENT_SKU] !== '';
        $hasSkuColumn = is_string($mapping[ProductImportField::SKU] ?? null) && $mapping[ProductImportField::SKU] !== '';
        if ($structuredVariant && ! $hasParentSkuColumn && ! $hasSkuColumn) {
            $errors[] = 'For multi-row variants, map Parent product SKU or Product SKU so rows can be grouped into the correct product.';

            return $errors;
        }

        foreach (ProductImportField::requiredForImport() as $required) {
            if ($structuredVariant && $required === ProductImportField::SKU && $hasParentSkuColumn) {
                continue;
            }
            if (empty($mapping[$required]) || ! is_string($mapping[$required])) {
                $errors[] = 'The field "'.(ProductImportField::labels()[$required] ?? $required).'" must be mapped.';

                continue;
            }
            if (! isset($headerSet[$mapping[$required]])) {
                $errors[] = 'Mapped column for "'.(ProductImportField::labels()[$required] ?? $required).'" is not present in the file.';
            }
        }

        foreach (ProductImportField::requiredWhenStructuredVariants($mapping) as $required) {
            if (empty($mapping[$required]) || ! is_string($mapping[$required])) {
                $errors[] = 'The field "'.(ProductImportField::labels()[$required] ?? $required).'" must be mapped for this variant layout.';

                continue;
            }
            if (! isset($headerSet[$mapping[$required]])) {
                $errors[] = 'Mapped column for "'.(ProductImportField::labels()[$required] ?? $required).'" is not present in the file.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $sourcesUsed = [];
        foreach ($mapping as $field => $source) {
            if (! is_string($source) || $source === '') {
                continue;
            }
            if (isset($sourcesUsed[$source])) {
                $errors[] = 'Each file column can only be mapped once ('.$source.').';

                return $errors;
            }
            $sourcesUsed[$source] = (string) $field;
        }

        $customErrors = self::validateCustomFieldMappingsInput($rawCustom, $headerSet, $sourcesUsed);

        return $customErrors;
    }

    /**
     * @param  array<int, mixed>  $rawCustom
     * @param  array<string, int>  $headerSet
     * @param  array<string, string>  $sourcesUsed
     * @return list<string>
     */
    public static function validateCustomFieldMappingsInput(array $rawCustom, array $headerSet, array $sourcesUsed): array
    {
        $errors = [];
        $reserved = array_flip(array_map('strtolower', array_keys(ProductImportField::labels())));
        $keyPattern = '/^[a-zA-Z0-9_.-]{1,128}$/';

        $rows = [];
        foreach ($rawCustom as $idx => $row) {
            if (! is_array($row)) {
                $errors[] = 'Custom field row '.($idx + 1).' is invalid.';

                continue;
            }
            $source = trim((string) ($row['source'] ?? ''));
            $key = trim((string) ($row['key'] ?? ''));
            $scopeRaw = strtolower(trim((string) ($row['scope'] ?? 'product')));
            $scope = in_array($scopeRaw, ['product', 'variant', 'attribute'], true) ? $scopeRaw : 'product';

            if ($source === '' && $key === '') {
                continue;
            }
            if ($source === '' || $key === '') {
                $errors[] = 'Each custom field needs both a source column and a destination key.';

                continue;
            }
            if (! isset($headerSet[$source])) {
                $errors[] = 'Custom field source column "'.$source.'" is not in this file.';

                continue;
            }
            if (isset($sourcesUsed[$source])) {
                $errors[] = 'Column "'.$source.'" is already mapped to a catalog field and cannot be reused as a custom field.';

                continue;
            }
            if (preg_match($keyPattern, $key) !== 1) {
                $errors[] = 'Custom field key "'.$key.'" must be 1–128 characters (letters, numbers, underscore, dot, hyphen).';

                continue;
            }
            if (isset($reserved[strtolower($key)])) {
                $errors[] = 'Custom field key "'.$key.'" is reserved for a built-in import field.';

                continue;
            }

            $rows[] = ['source' => $source, 'key' => $key, 'scope' => $scope];
        }

        $seenSources = [];
        $seenKeys = [];
        foreach ($rows as $row) {
            if (isset($seenSources[$row['source']])) {
                $errors[] = 'Duplicate custom mapping for column "'.$row['source'].'".';
            }
            $seenSources[$row['source']] = true;

            $lk = strtolower($row['key']);
            if (isset($seenKeys[$lk])) {
                $errors[] = 'Duplicate custom field key "'.$row['key'].'".';
            }
            $seenKeys[$lk] = true;
        }

        return $errors;
    }
}
