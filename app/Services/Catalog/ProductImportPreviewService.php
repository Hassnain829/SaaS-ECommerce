<?php

namespace App\Services\Catalog;

use App\Catalog\ProductImportField;
use App\Models\ProductImport;
use Illuminate\Support\Facades\Storage;

final class ProductImportPreviewService
{
    public function __construct(
        private readonly ProductImportSpreadsheetReader $reader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ProductImport $import): array
    {
        $store = $import->store;
        $mapping = $import->column_mapping ?? [];
        $headers = $import->headers ?? [];
        if ($headers === [] || $mapping === []) {
            return [
                'error' => 'Missing headers or column mapping.',
            ];
        }

        $customMappings = ProductImportProcessor::normalizeCustomMappings($import->custom_field_mappings ?? []);

        $usedSources = [];
        foreach ($mapping as $src) {
            if (is_string($src) && $src !== '') {
                $usedSources[$src] = true;
            }
        }
        foreach ($customMappings as $cm) {
            $usedSources[$cm['source']] = true;
        }
        $usedSourceList = array_keys($usedSources);

        $unmappedHeaders = array_values(array_filter($headers, static function (string $h) use ($usedSources): bool {
            return $h !== '' && ! isset($usedSources[$h]);
        }));

        $path = Storage::disk($import->stored_disk)->path($import->stored_path);
        $ext = $import->file_extension;

        $variantMode = ProductImportField::usesStructuredVariantRows($mapping)
            && ! ProductImportField::hasPartialOptionSlotMapping($mapping);
        $slots = ProductImportField::structuredVariantSlots($mapping);

        $total = 0;
        $valid = 0;
        $invalid = 0;
        $invalidSamples = [];
        $seenSku = [];
        $duplicateSkusInFile = [];
        $seenVariantSkuLower = [];
        $duplicateVariantSkusInFile = [];
        /** @var array<string, int> $groupRowCounts */
        $groupRowCounts = [];
        /** @var array<string, array<string, true>> $groupComboKeys */
        $groupComboKeys = [];
        /** @var array<int, array<string, true>> $optionLabelSamples */
        $optionLabelSamples = [];
        $weakGroupingNameOnly = false;
        $brandsToCreate = [];
        $categoriesToCreate = [];
        $tagsToCreate = [];

        $this->reader->eachDataRow($path, $ext, function (array $cells) use (
            &$total,
            &$valid,
            &$invalid,
            &$invalidSamples,
            &$seenSku,
            &$duplicateSkusInFile,
            &$seenVariantSkuLower,
            &$duplicateVariantSkusInFile,
            &$groupRowCounts,
            &$groupComboKeys,
            &$optionLabelSamples,
            &$weakGroupingNameOnly,
            &$brandsToCreate,
            &$categoriesToCreate,
            &$tagsToCreate,
            $headers,
            $mapping,
            $customMappings,
            $store,
            $variantMode,
            $slots
        ): void {
            $total++;
            if ($total > 5000) {
                return;
            }

            $row = $this->cellsToKeyedRow($headers, $cells);
            $errors = ProductImportRowValidator::validateMappedRow($row, $mapping, $customMappings);
            $fields = $this->extractMappedFields($row, $mapping);

            if ($variantMode) {
                $vSku = trim((string) ($fields[ProductImportField::VARIANT_SKU] ?? ''));
                if ($errors === [] && $vSku !== '') {
                    $vk = mb_strtolower($vSku);
                    if (isset($seenVariantSkuLower[$vk])) {
                        $errors[] = 'Duplicate variant SKU in file.';
                        $duplicateVariantSkusInFile[$vSku] = true;
                    } else {
                        $seenVariantSkuLower[$vk] = true;
                    }
                }

                $gk = $this->previewVariantGroupKey($fields, $mapping);
                if ($errors === [] && $gk === '') {
                    $errors[] = 'This row needs a Parent product SKU, or Brand filled in when several rows share the same product name.';
                }

                if ($errors === [] && $gk !== '') {
                    foreach ($slots as $slot) {
                        $label = trim((string) ($fields[ProductImportField::optionNameField($slot)] ?? ''));
                        $value = trim((string) ($fields[ProductImportField::optionValueField($slot)] ?? ''));
                        if ($label !== '' && $value !== '') {
                            $optionLabelSamples[$slot][$label] = true;
                        }
                    }
                    $combo = $this->previewCombinationKey($fields, $slots);
                    if ($combo === '') {
                        $errors[] = 'Each variant row needs a value for every option group you mapped.';
                    } elseif (isset($groupComboKeys[$gk][$combo])) {
                        $errors[] = 'Duplicate variant combination for the same product in this file.';
                    } else {
                        $groupComboKeys[$gk][$combo] = true;
                    }
                    $groupRowCounts[$gk] = ($groupRowCounts[$gk] ?? 0) + 1;
                }

                $parentMapped = is_string($mapping[ProductImportField::PARENT_SKU] ?? null) && $mapping[ProductImportField::PARENT_SKU] !== '';
                if (! $parentMapped && $errors === []) {
                    $brand = trim((string) ($fields[ProductImportField::BRAND] ?? ''));
                    if ($brand === '') {
                        $weakGroupingNameOnly = true;
                    }
                }
            } else {
                $sku = trim((string) ($fields[ProductImportField::SKU] ?? ''));
                $skuKey = $sku !== '' ? mb_strtolower($sku) : '';
                if ($errors === [] && $skuKey !== '' && isset($seenSku[$skuKey])) {
                    $errors[] = 'Duplicate SKU in file.';
                    $duplicateSkusInFile[$sku] = true;
                }

                if ($errors === [] && $skuKey !== '') {
                    $seenSku[$skuKey] = true;
                }
            }

            if ($errors !== []) {
                $invalid++;
                if (count($invalidSamples) < 25) {
                    $invalidSamples[] = [
                        'row' => $total + 1,
                        'messages' => $errors,
                    ];
                }

                return;
            }

            $valid++;

            $brand = trim((string) ($fields[ProductImportField::BRAND] ?? ''));
            if ($brand !== '' && ! $store->brands()->whereRaw('LOWER(name) = ?', [mb_strtolower($brand)])->exists()) {
                $brandsToCreate[$brand] = true;
            }

            foreach ($this->splitDelimited($fields[ProductImportField::CATEGORY] ?? '') as $cat) {
                if ($cat !== '' && ! $store->categories()->whereRaw('LOWER(name) = ?', [mb_strtolower($cat)])->exists()) {
                    $categoriesToCreate[$cat] = true;
                }
            }

            foreach ($this->splitDelimited($fields[ProductImportField::TAGS] ?? '') as $tag) {
                if ($tag !== '' && ! $store->tags()->whereRaw('LOWER(name) = ?', [mb_strtolower($tag)])->exists()) {
                    $tagsToCreate[$tag] = true;
                }
            }
        });

        $duplicateSkuList = array_keys($duplicateSkusInFile);
        $duplicateVariantSkuList = array_keys($duplicateVariantSkusInFile);

        $customPreviewLines = [];
        foreach ($customMappings as $cm) {
            $scope = $cm['scope'] === 'variant' ? 'Variant meta' : 'Product meta';
            $pathHint = $cm['scope'] === 'variant'
                ? 'product_variants.meta.custom_fields.'.$cm['key']
                : 'products.meta.custom_fields.'.$cm['key'];
            $customPreviewLines[] = $cm['source'].' → '.$cm['key'].' ('.$scope.'; '.$pathHint.')';
        }

        $variantSummary = null;
        if ($variantMode) {
            $optionGroupLabels = [];
            foreach ($slots as $slot) {
                foreach (array_keys($optionLabelSamples[$slot] ?? []) as $lab) {
                    $optionGroupLabels[] = $lab;
                }
            }
            $optionGroupLabels = array_values(array_unique($optionGroupLabels));
            $sampleCombos = [];
            $n = 0;
            foreach ($groupComboKeys as $gk => $combos) {
                foreach (array_keys($combos) as $c) {
                    if ($n >= 8) {
                        break 2;
                    }
                    $sampleCombos[] = str_replace("\n", ' · ', $c);
                    $n++;
                }
            }
            $variantSummary = [
                'mode' => 'multi_row_variants',
                'unique_products_detected' => count($groupRowCounts),
                'variant_line_rows' => array_sum($groupRowCounts),
                'option_group_labels' => $optionGroupLabels,
                'sample_variant_lines' => $sampleCombos,
                'duplicate_variant_skus_in_file' => $duplicateVariantSkuList,
                'weak_grouping_without_parent_sku' => $weakGroupingNameOnly,
            ];
        }

        return [
            'total_rows_sampled' => min($total, 5000),
            'total_rows_truncated' => $total > 5000,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'invalid_samples' => $invalidSamples,
            'duplicate_skus_in_file' => $duplicateSkuList,
            'variant_import' => $variantMode,
            'variant_summary' => $variantSummary,
            'unmapped_headers' => $unmappedHeaders,
            'unmapped_will_capture_to_meta' => $unmappedHeaders,
            'custom_field_mappings' => $customMappings,
            'custom_field_preview_lines' => $customPreviewLines,
            'brands_to_create' => array_keys($brandsToCreate),
            'categories_to_create' => array_keys($categoriesToCreate),
            'tags_to_create' => array_keys($tagsToCreate),
            'columns_used_count' => count($usedSourceList),
            'columns_total_count' => count(array_filter($headers, static fn ($h) => $h !== '')),
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $mapping
     */
    private function previewVariantGroupKey(array $fields, array $mapping): string
    {
        $parentMapped = is_string($mapping[ProductImportField::PARENT_SKU] ?? null) && $mapping[ProductImportField::PARENT_SKU] !== '';
        $parent = trim((string) ($fields[ProductImportField::PARENT_SKU] ?? ''));
        if ($parentMapped) {
            return $parent !== '' ? 'p:'.mb_strtolower($parent) : '';
        }

        $name = mb_strtolower(trim((string) ($fields[ProductImportField::PRODUCT_NAME] ?? '')));
        $brand = mb_strtolower(trim((string) ($fields[ProductImportField::BRAND] ?? '')));

        return 'nb:'.$name.'|'.$brand;
    }

    /**
     * @param  list<int>  $slots
     */
    private function previewCombinationKey(array $fields, array $slots): string
    {
        $parts = [];
        foreach ($slots as $slot) {
            $name = trim((string) ($fields[ProductImportField::optionNameField($slot)] ?? ''));
            $val = trim((string) ($fields[ProductImportField::optionValueField($slot)] ?? ''));
            if ($name === '' || $val === '') {
                return '';
            }
            $parts[] = mb_strtolower($name)."\n".mb_strtolower($val);
        }

        return implode("\n", $parts);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $cells
     * @return array<string, string>
     */
    private function cellsToKeyedRow(array $headers, array $cells): array
    {
        $row = [];
        foreach ($headers as $i => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = $cells[$i] ?? '';
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, string>
     */
    private function extractMappedFields(array $row, array $mapping): array
    {
        $allowed = array_flip(array_keys(ProductImportField::labels()));
        $out = [];
        foreach ($mapping as $field => $sourceHeader) {
            if (! isset($allowed[$field])) {
                continue;
            }
            if (! is_string($sourceHeader) || $sourceHeader === '') {
                continue;
            }
            $out[$field] = $row[$sourceHeader] ?? '';
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function splitDelimited(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        foreach (['|', ';', "\n"] as $delim) {
            if (str_contains($value, $delim)) {
                return array_values(array_filter(array_map('trim', explode($delim, $value))));
            }
        }
        if (str_contains($value, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [$value];
    }
}
