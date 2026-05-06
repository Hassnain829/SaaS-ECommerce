<?php

namespace App\Services\Catalog;

use App\Catalog\ProductImportField;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use App\Models\ProductVariationType;
use App\Models\Store;
use App\Support\Catalog\ProductImportMerchantMessages;
use App\Support\Catalog\SpreadsheetValueNormalizer;
use App\Support\ProductTypeBehavior;
use App\Support\StockMovementRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Second pass for structured variant spreadsheets: groups deferred rows and writes
 * products, option groups, variants, stock movements, and optional variant images.
 */
final class ProductImportVariantFinalizer
{
    public function __construct(
        private readonly ProductCatalogImageDownloader $imageDownloader,
    ) {}

    /**
     * @param  array<string, string>  $mapping
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @param  array<string, true>  $assignedVariantSkusLower
     * @param  list<array{row:int, message:string}>  $failures
     */
    public function finalize(
        ProductImport $import,
        Store $store,
        array $headers,
        array $mapping,
        array $customMappings,
        ProductImportTaxonomyCache $taxonomyCache,
        array &$assignedVariantSkusLower,
        int &$created,
        int &$updated,
        int &$failed,
        array &$failures,
        int &$warningsCount,
    ): void {
        $slots = ProductImportField::structuredVariantSlots($mapping);
        if ($slots === []) {
            return;
        }

        $rows = ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_DEFERRED)
            ->orderBy('row_number')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        /** @var list<array{import_row: ProductImportRow, row_number: int, excel_row: int, keyed: array<string, string>, fields: array<string, string>}> $prepared */
        $prepared = [];
        foreach ($rows as $importRow) {
            if (! $importRow instanceof ProductImportRow) {
                continue;
            }
            $cells = $importRow->payload['cells'] ?? null;
            if (! is_array($cells)) {
                $this->failPreparedRow($import, $importRow, $failures, $failed, 'Saved row data was incomplete.');
                continue;
            }
            $keyed = $this->cellsToKeyedRow($headers, $cells);
            $fields = $this->extractMappedFields($keyed, $mapping);
            $gk = $this->groupKeyForFields($fields, $mapping);
            if ($gk === '') {
                $this->failPreparedRow($import, $importRow, $failures, $failed, 'This row does not have a stable product identifier (map Parent product SKU, or use Brand when several rows share the same product name).');
                continue;
            }
            $prepared[] = [
                'import_row' => $importRow,
                'row_number' => (int) $importRow->row_number,
                'excel_row' => (int) $importRow->row_number + 1,
                'keyed' => $keyed,
                'fields' => $fields,
            ];
        }

        /** @var array<string, list<int>> $groups */
        $groups = [];
        foreach ($prepared as $idx => $item) {
            $gk = $this->groupKeyForFields($item['fields'], $mapping);
            $groups[$gk][] = $idx;
        }

        foreach ($groups as $groupKey => $memberIndexes) {
            $members = [];
            foreach ($memberIndexes as $i) {
                $members[] = $prepared[$i];
            }
            if ($members === []) {
                continue;
            }

            $slotLabelsResult = $this->assertConsistentOptionLabelsAcrossGroup($members, $slots);
            if (isset($slotLabelsResult['error'])) {
                $msg = (string) $slotLabelsResult['error'];
                foreach ($members as $m) {
                    $this->failPreparedRow($import, $m['import_row'], $failures, $failed, $msg);
                }

                continue;
            }
            /** @var array<int, string> $slotLabels */
            $slotLabels = $slotLabelsResult['labels'];

            $comboKeys = [];
            /** @var list<array{import_row: ProductImportRow, row_number: int, excel_row: int, keyed: array<string, string>, fields: array<string, string>}> $eligible */
            $eligible = [];
            foreach ($members as $m) {
                $ck = $this->combinationKey($m['fields'], $mapping, $slots);
                if ($ck === '') {
                    $this->failPreparedRow($import, $m['import_row'], $failures, $failed, 'Each variant row needs a value for every option group you mapped.');
                    continue;
                }
                if (isset($comboKeys[$ck])) {
                    $this->failPreparedRow($import, $m['import_row'], $failures, $failed, 'Duplicate variant combination in this file for the same product.');
                    continue;
                }
                $comboKeys[$ck] = true;
                $eligible[] = $m;
            }

            if ($eligible === []) {
                continue;
            }

            $inconsistent = $this->detectInconsistentProductLevelFields($eligible, $mapping);
            if ($inconsistent !== null) {
                foreach ($eligible as $m) {
                    $this->failPreparedRow($import, $m['import_row'], $failures, $failed, $inconsistent);
                }

                continue;
            }

            try {
                DB::transaction(function () use (
                    $import,
                    $store,
                    $mapping,
                    $customMappings,
                    $taxonomyCache,
                    &$assignedVariantSkusLower,
                    &$created,
                    &$updated,
                    $eligible,
                    $slots,
                    $slotLabels,
                    $groupKey,
                    &$warningsCount,
                ): void {
                    $this->persistVariantGroup(
                        $import,
                        $store,
                        $mapping,
                        $customMappings,
                        $taxonomyCache,
                        $assignedVariantSkusLower,
                        $created,
                        $updated,
                        $eligible,
                        $slots,
                        $slotLabels,
                        $groupKey,
                        $warningsCount
                    );
                });
            } catch (\Throwable $e) {
                $msg = ProductImportMerchantMessages::humanizeException($e);
                foreach ($eligible as $m) {
                    $this->failPreparedRow($import, $m['import_row'], $failures, $failed, $msg);
                }

                continue;
            }

            foreach ($eligible as $m) {
                $this->markRow($import, $m['import_row']->row_number, ProductImportRow::STATUS_PROCESSED, null);
            }
        }
    }

    /**
     * @param  list<array{import_row: ProductImportRow, row_number: int, excel_row: int, keyed: array<string, string>, fields: array<string, string>}>  $members
     * @param  list<int>  $slots
     * @return array{labels: array<int, string>}|array{error: string}
     */
    private function assertConsistentOptionLabelsAcrossGroup(array $members, array $slots): array
    {
        /** @var array<int, string> $labels */
        $labels = [];
        foreach ($members as $m) {
            $fields = $m['fields'];
            foreach ($slots as $slot) {
                $name = trim((string) ($fields[ProductImportField::optionNameField($slot)] ?? ''));
                $val = trim((string) ($fields[ProductImportField::optionValueField($slot)] ?? ''));
                if ($name === '' && $val === '') {
                    continue;
                }
                if ($name === '' || $val === '') {
                    return ['error' => 'Option values must be paired with their group label on every row.'];
                }
                if (isset($labels[$slot]) && mb_strtolower($labels[$slot]) !== mb_strtolower($name)) {
                    return ['error' => 'Option group labels do not match across rows for the same product.'];
                }
                $labels[$slot] = $name;
            }
        }

        foreach ($slots as $slot) {
            if (! isset($labels[$slot]) || $labels[$slot] === '') {
                return ['error' => 'Each mapped option group needs at least one value in the file for this product.'];
            }
        }

        return ['labels' => $labels];
    }

    /**
     * @param  list<array{import_row: ProductImportRow, row_number: int, excel_row: int, keyed: array<string, string>, fields: array<string, string>}>  $members
     */
    private function detectInconsistentProductLevelFields(array $members, array $mapping): ?string
    {
        $checks = [
            ProductImportField::PRODUCT_NAME => 'Product name',
        ];
        if (is_string($mapping[ProductImportField::PARENT_SKU] ?? null) && $mapping[ProductImportField::PARENT_SKU] !== '') {
            $checks[ProductImportField::PARENT_SKU] = 'Parent product SKU';
        }
        $seen = [];
        foreach ($checks as $field => $_label) {
            $seen[$field] = null;
        }
        foreach ($members as $m) {
            foreach ($checks as $field => $_l) {
                $v = trim((string) ($m['fields'][$field] ?? ''));
                if ($v === '') {
                    continue;
                }
                if ($seen[$field] === null) {
                    $seen[$field] = mb_strtolower($v);
                } elseif ($seen[$field] !== mb_strtolower($v)) {
                    return 'Rows for the same product disagree on '.$checks[$field].'.';
                }
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $slots
     */
    private function combinationKey(array $fields, array $mapping, array $slots): string
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
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $mapping
     */
    private function groupKeyForFields(array $fields, array $mapping): string
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
     * @param  array<string, string>  $mapping
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @param  list<array{import_row: ProductImportRow, row_number: int, excel_row: int, keyed: array<string, string>, fields: array<string, string>}>  $members
     * @param  list<int>  $slots
     * @param  array<int, string>  $slotLabels
     * @param  array<string, true>  $assignedVariantSkusLower
     */
    private function persistVariantGroup(
        ProductImport $import,
        Store $store,
        array $mapping,
        array $customMappings,
        ProductImportTaxonomyCache $taxonomyCache,
        array &$assignedVariantSkusLower,
        int &$created,
        int &$updated,
        array $members,
        array $slots,
        array $slotLabels,
        string $groupKey,
        int &$warningsCount,
    ): void {
        usort($members, static fn (array $a, array $b): int => $a['row_number'] <=> $b['row_number']);
        $first = $members[0];
        $fields0 = $first['fields'];
        $name = trim((string) ($fields0[ProductImportField::PRODUCT_NAME] ?? ''));
        $parentMapped = is_string($mapping[ProductImportField::PARENT_SKU] ?? null) && $mapping[ProductImportField::PARENT_SKU] !== '';
        $parentSku = $parentMapped ? trim((string) ($fields0[ProductImportField::PARENT_SKU] ?? '')) : '';
        $productSku = $parentSku !== '' ? $parentSku : $this->deriveAutoProductSku($store->id, $groupKey, $fields0);

        $product = Product::query()
            ->where('store_id', $store->id)
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower($productSku)])
            ->first();

        $productExistedBefore = $product !== null;

        $mergedProductCustom = [];
        $mergedAttributeValues = [];
        $mergedExtras = [];
        $mergedDescription = '';
        $mergedShort = '';
        $basePrice = 0.0;
        $productType = 'physical';
        $status = true;
        foreach ($members as $m) {
            $f = $m['fields'];
            [$pc, $_vc] = $this->extractCustomFieldValues($m['keyed'], $customMappings);
            $mergedProductCustom = array_merge($mergedProductCustom, $pc);
            foreach ($this->extractAttributeValues($m['keyed'], $customMappings) as $attributeName => $values) {
                $mergedAttributeValues[$attributeName] = array_merge($mergedAttributeValues[$attributeName] ?? [], $values);
            }
            $mergedExtras = array_merge($mergedExtras, $this->collectUnmappedExtras($m['keyed'], array_keys($m['keyed']), $mapping, $customMappings));
            $d = trim((string) ($f[ProductImportField::DESCRIPTION] ?? ''));
            if ($d !== '' && strlen($d) > strlen($mergedDescription)) {
                $mergedDescription = $d;
            }
            $sd = trim((string) ($f[ProductImportField::SHORT_DESCRIPTION] ?? ''));
            if ($sd !== '' && strlen($sd) > strlen($mergedShort)) {
                $mergedShort = $sd;
            }
            $bp = SpreadsheetValueNormalizer::normalizeDecimal($f[ProductImportField::BASE_PRICE] ?? '') ?? SpreadsheetValueNormalizer::normalizeDecimal($f[ProductImportField::VARIANT_PRICE] ?? '');
            if ($bp !== null && $bp > $basePrice) {
                $basePrice = (float) $bp;
            }
            $productType = $this->normalizeProductType(trim((string) ($f[ProductImportField::PRODUCT_TYPE] ?? '')));
            $status = $this->parseStatus($f[ProductImportField::STATUS] ?? '', $f[ProductImportField::VISIBILITY] ?? '');
        }

        $catalogMeta = $this->buildMergedCatalogMeta($members);

        $variantOptionsMeta = [];
        foreach ($slots as $slot) {
            $variantOptionsMeta['option_'.$slot] = trim((string) ($fields0[ProductImportField::optionValueField($slot)] ?? ''));
        }

        $performedBy = $import->created_by;
        $importId = (int) $import->id;

        if (! $product) {
            $meta = $this->mergeMetaLayer([], $mergedExtras, $catalogMeta, $variantOptionsMeta, 0, $mergedProductCustom);
            $slug = $this->uniqueProductSlug($store->id, $name);
            $brandName = trim((string) ($fields0[ProductImportField::BRAND] ?? ''));
            $product = Product::query()->create([
                'store_id' => $store->id,
                'brand_id' => $brandName !== '' ? $taxonomyCache->brandId($brandName) : null,
                'name' => $name,
                'slug' => $slug,
                'description' => $mergedDescription !== '' ? $mergedDescription : null,
                'base_price' => $basePrice,
                'sku' => $productSku,
                'product_type' => $productType,
                ...ProductTypeBehavior::defaultColumnsFor($productType),
                'status' => $status,
                'meta' => $meta,
            ]);
            $this->syncTaxonomyFromFields($product, $fields0, $taxonomyCache);
        } else {
            $meta = $this->mergeMetaLayer($product->meta ?? [], $mergedExtras, $catalogMeta, $variantOptionsMeta, (int) ($product->meta['stock_alert'] ?? 0), $mergedProductCustom);
            $brandName = trim((string) ($fields0[ProductImportField::BRAND] ?? ''));
            $product->update([
                'name' => $name,
                'slug' => $this->uniqueProductSlug($store->id, $name, $product->id),
                'description' => $mergedDescription !== '' ? $mergedDescription : $product->description,
                'base_price' => $basePrice > 0 ? $basePrice : $product->base_price,
                'sku' => $productSku,
                'product_type' => $productType,
                ...ProductTypeBehavior::defaultColumnsFor($productType),
                'status' => $status,
                'brand_id' => $brandName !== '' ? $taxonomyCache->brandId($brandName) : $product->brand_id,
                'meta' => $meta,
            ]);
            $this->syncTaxonomyFromFields($product, $fields0, $taxonomyCache);
        }

        $product->refresh();
        $this->syncAttributeValues($store, $product, $mergedAttributeValues, $performedBy);

        /** @var array<int, ProductVariationType> $typeBySlot */
        $typeBySlot = [];
        /** @var array<string, ProductVariationOption> $optionByTypeAndLowerValue */
        $optionByTypeAndLowerValue = [];

        $product->load(['variationTypes.options']);
        foreach ($slots as $slot) {
            $label = $slotLabels[$slot];
            $type = $product->variationTypes->first(fn (ProductVariationType $t): bool => mb_strtolower($t->name) === mb_strtolower($label));
            if (! $type) {
                $type = ProductVariationType::query()->create([
                    'product_id' => $product->id,
                    'name' => $label,
                    'type' => 'select',
                ]);
            }
            $typeBySlot[$slot] = $type;
            foreach ($type->options as $opt) {
                $optionByTypeAndLowerValue[(string) $type->id.'|'.mb_strtolower($opt->value)] = $opt;
            }
        }

        $valuesFirstSeen = [];
        foreach ($slots as $slot) {
            $valuesFirstSeen[$slot] = [];
        }
        foreach ($members as $m) {
            $f = $m['fields'];
            foreach ($slots as $slot) {
                $v = trim((string) ($f[ProductImportField::optionValueField($slot)] ?? ''));
                if ($v === '') {
                    continue;
                }
                $lk = mb_strtolower($v);
                if (! isset($valuesFirstSeen[$slot][$lk])) {
                    $valuesFirstSeen[$slot][$lk] = $v;
                }
            }
        }

        foreach ($slots as $slot) {
            $type = $typeBySlot[$slot];
            $maxOrder = (int) $type->options()->max('sort_order');
            $order = $maxOrder;
            foreach ($valuesFirstSeen[$slot] as $lk => $canonicalValue) {
                $key = (string) $type->id.'|'.$lk;
                if (isset($optionByTypeAndLowerValue[$key])) {
                    continue;
                }
                $order++;
                $opt = ProductVariationOption::query()->create([
                    'variation_type_id' => $type->id,
                    'value' => $canonicalValue,
                    'sort_order' => $order,
                ]);
                $optionByTypeAndLowerValue[$key] = $opt;
            }
        }

        $product->load(['variants.options', 'variationTypes.options']);

        $singletonDefault = $product->variants->count() === 1
            && $product->variants->first() !== null
            && $product->variants->first()->options->isEmpty();
        $firstAssignable = true;

        foreach ($members as $mi => $m) {
            $f = $m['fields'];
            $optionIds = [];
            foreach ($slots as $slot) {
                $type = $typeBySlot[$slot];
                $v = trim((string) ($f[ProductImportField::optionValueField($slot)] ?? ''));
                $key = (string) $type->id.'|'.mb_strtolower($v);
                $opt = $optionByTypeAndLowerValue[$key] ?? null;
                if (! $opt) {
                    throw new \RuntimeException('Internal import error resolving option "'.$v.'".');
                }
                $optionIds[] = $opt->id;
            }
            sort($optionIds);

            $variant = $this->findVariantWithExactOptions($product, $optionIds);
            if (! $variant && $singletonDefault && $firstAssignable) {
                $variant = $product->variants->first();
                $firstAssignable = false;
                if ($variant) {
                    $variant->options()->sync($optionIds);
                }
            }
            $desiredVariantSku = trim((string) ($f[ProductImportField::VARIANT_SKU] ?? ''));
            if ($desiredVariantSku === '') {
                $desiredVariantSku = $productSku.'-'.implode('-', array_map(static fn (int $id): string => (string) $id, $optionIds));
            }
            $variantSku = $this->ensureUniqueVariantSku($desiredVariantSku, $store->id, $assignedVariantSkusLower, $variant?->id);

            $price = SpreadsheetValueNormalizer::normalizeDecimal($f[ProductImportField::VARIANT_PRICE] ?? '')
                ?? SpreadsheetValueNormalizer::normalizeDecimal($f[ProductImportField::BASE_PRICE] ?? '')
                ?? 0.0;
            $compareAt = SpreadsheetValueNormalizer::normalizeDecimal($f[ProductImportField::VARIANT_COMPARE_AT_PRICE] ?? '')
                ?? SpreadsheetValueNormalizer::normalizeDecimal($f[ProductImportField::COMPARE_AT_PRICE] ?? '');
            $stock = SpreadsheetValueNormalizer::normalizeInteger($f[ProductImportField::VARIANT_STOCK] ?? '')
                ?? SpreadsheetValueNormalizer::normalizeInteger($f[ProductImportField::STOCK] ?? '')
                ?? 0;
            $stockAlert = SpreadsheetValueNormalizer::normalizeInteger($f[ProductImportField::VARIANT_STOCK_ALERT] ?? '')
                ?? SpreadsheetValueNormalizer::normalizeInteger($f[ProductImportField::LOW_STOCK_THRESHOLD] ?? '')
                ?? 0;

            if (! $variant) {
                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'sku' => $variantSku,
                    'price' => $price,
                    'compare_at_price' => $compareAt,
                    'stock' => $stock,
                    'stock_alert' => max(0, $stockAlert),
                ]);
                $variant->options()->sync($optionIds);
                StockMovementRecorder::recordImport(
                    $store,
                    $product,
                    $variant,
                    null,
                    $stock,
                    $performedBy,
                    $importId,
                    'Imported variant row'
                );
            } else {
                $previousStock = (int) $variant->stock;
                $variant->update([
                    'sku' => $variantSku,
                    'price' => $price,
                    'compare_at_price' => $compareAt,
                    'stock' => $stock,
                    'stock_alert' => max(0, $stockAlert),
                ]);
                $variant->options()->sync($optionIds);
                $variant->refresh();
                StockMovementRecorder::recordImport(
                    $store,
                    $product,
                    $variant,
                    $previousStock,
                    $stock,
                    $performedBy,
                    $importId,
                    'Imported variant row'
                );
            }

            $variant->refresh();
            [, $vCustom] = $this->extractCustomFieldValues($m['keyed'], $customMappings);
            $this->mergeVariantCustomFields($variant, $vCustom);
            $vs = trim((string) ($f[ProductImportField::VARIANT_STATUS] ?? ''));
            if ($vs !== '') {
                $meta = $variant->meta ?? [];
                $meta['listing_note'] = $vs;
                $variant->update(['meta' => $meta]);
            }

            $imgUrl = trim((string) ($f[ProductImportField::VARIANT_IMAGE_URL] ?? ''));
            if ($imgUrl !== '' && preg_match('#^https?://#i', $imgUrl)) {
                ProductImage::query()
                    ->where('product_id', $product->id)
                    ->where('product_variant_id', $variant->id)
                    ->delete();
                $urls = [$imgUrl];
                if (config('product_import.async_image_processing', true)) {
                    $n = $this->imageDownloader->enqueueRemoteUrlsForImport($import, $product->fresh(), $store, $urls, $performedBy, $variant);
                    if ($n > 0) {
                        ProductImportMediaProgress::adjust((int) $import->id, $n, 0, 0);
                    }
                } else {
                    $n = $this->imageDownloader->importUrls($product->fresh(), $store, $urls, $performedBy, $variant);
                    if ($n > 0) {
                        ProductImportMediaProgress::adjust((int) $import->id, $n, $n, 0);
                    }
                }
            }

            $gallery = trim((string) ($f[ProductImportField::IMAGE_URLS] ?? ''));
            if ($gallery !== '' && $mi === 0) {
                $urls = $this->splitDelimited($gallery);
                if ($urls === []) {
                    $warningsCount++;
                } else {
                    if (config('product_import.async_image_processing', true)) {
                        $n = $this->imageDownloader->enqueueRemoteUrlsForImport($import, $product->fresh(), $store, $urls, $performedBy, null);
                        if ($n > 0) {
                            ProductImportMediaProgress::adjust((int) $import->id, $n, 0, 0);
                        }
                    } else {
                        $n = $this->imageDownloader->importUrls($product->fresh(), $store, $urls, $performedBy, null);
                        if ($n > 0) {
                            ProductImportMediaProgress::adjust((int) $import->id, $n, $n, 0);
                        }
                    }
                }
            }
        }

        $rowCount = count($members);
        if (! $productExistedBefore) {
            $created += $rowCount;
        } else {
            $updated += $rowCount;
        }
    }

    private function deriveAutoProductSku(int $storeId, string $groupKey, array $fields0): string
    {
        $skuCol = trim((string) ($fields0[ProductImportField::SKU] ?? ''));
        if ($skuCol !== '') {
            return $skuCol;
        }

        return 'VGRP-'.$storeId.'-'.strtoupper(substr(sha1($groupKey), 0, 10));
    }

    /**
     * @param  list<array{import_row: ProductImportRow, row_number: int, excel_row: int, keyed: array<string, string>, fields: array<string, string>}>  $members
     * @return array<string, mixed>
     */
    private function buildMergedCatalogMeta(array $members): array
    {
        $catalogMeta = [];
        foreach ($members as $m) {
            $f = $m['fields'];
            $rowMeta = array_filter([
                'barcode' => trim((string) ($f[ProductImportField::BARCODE] ?? '')) ?: null,
                'compare_at_price' => SpreadsheetValueNormalizer::normalizeDecimal($f[ProductImportField::COMPARE_AT_PRICE] ?? ''),
                'cost_price' => SpreadsheetValueNormalizer::normalizeDecimal($f[ProductImportField::COST_PRICE] ?? ''),
                'short_description' => trim((string) ($f[ProductImportField::SHORT_DESCRIPTION] ?? '')) ?: null,
                'weight' => trim((string) ($f[ProductImportField::WEIGHT] ?? '')) ?: null,
                'length' => trim((string) ($f[ProductImportField::LENGTH] ?? '')) ?: null,
                'width' => trim((string) ($f[ProductImportField::WIDTH] ?? '')) ?: null,
                'height' => trim((string) ($f[ProductImportField::HEIGHT] ?? '')) ?: null,
            ], static fn ($v) => $v !== null && $v !== '');
            $catalogMeta = array_merge($catalogMeta, $rowMeta);
        }

        return $catalogMeta;
    }

    private function findVariantWithExactOptions(Product $product, array $sortedOptionIds): ?ProductVariant
    {
        foreach ($product->variants as $variant) {
            $ids = $variant->options->pluck('id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
            if ($ids === $sortedOptionIds) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * @param  array<string, true>  $assignedLower
     */
    private function ensureUniqueVariantSku(string $desiredSku, int $storeId, array &$assignedLower, ?int $ignoreVariantId = null): string
    {
        $sku = $desiredSku !== '' ? $desiredSku : 'SKU-'.$storeId.'-'.Str::upper(Str::random(6));
        $base = $sku;
        $n = 0;
        while (true) {
            $lk = mb_strtolower($sku);
            if (isset($assignedLower[$lk]) || ProductVariant::query()
                ->where('store_id', $storeId)
                ->whereRaw('LOWER(sku) = ?', [$lk])
                ->when($ignoreVariantId, fn ($query) => $query->where('id', '!=', $ignoreVariantId))
                ->exists()) {
                $n++;
                $sku = Str::limit($base, 90, '').'-'.$storeId.'-'.$n;

                continue;
            }
            break;
        }
        $assignedLower[mb_strtolower($sku)] = true;

        return $sku;
    }

    private function uniqueProductSlug(int $storeId, string $name, ?int $ignoreProductId = null): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'product';
        $slug = $base;
        $counter = 1;
        while (Product::query()->where('store_id', $storeId)
            ->where('slug', $slug)
            ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function normalizeProductType(string $type): string
    {
        return ProductTypeBehavior::normalize($type);
    }

    private function parseStatus(string $statusField, string $visibilityField): bool
    {
        $s = trim($statusField);
        $v = trim($visibilityField);
        $bool = SpreadsheetValueNormalizer::normalizeBoolean($s !== '' ? $s : $v);
        if ($bool !== null) {
            return $bool;
        }
        $raw = strtolower($s !== '' ? $s : $v);
        if ($raw === '' || $raw === 'published' || $raw === 'active' || $raw === 'visible') {
            return true;
        }
        if ($raw === 'draft' || $raw === 'hidden' || $raw === 'inactive') {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function syncTaxonomyFromFields(Product $product, array $fields, ProductImportTaxonomyCache $taxonomyCache): void
    {
        $categoryIds = [];
        foreach ($this->splitDelimited($fields[ProductImportField::CATEGORY] ?? '') as $catName) {
            if ($catName === '') {
                continue;
            }
            $id = $taxonomyCache->categoryId($catName);
            if ($id > 0) {
                $categoryIds[] = $id;
            }
        }
        $product->categories()->sync(array_values(array_unique($categoryIds)));

        $tagIds = [];
        foreach ($this->splitDelimited($fields[ProductImportField::TAGS] ?? '') as $tagName) {
            if ($tagName === '') {
                continue;
            }
            $id = $taxonomyCache->tagId($tagName);
            if ($id > 0) {
                $tagIds[] = $id;
            }
        }
        $product->tags()->sync(array_values(array_unique($tagIds)));
    }

    /**
     * @param  array<string, mixed>  $existingMeta
     * @param  array<string, string>  $extras
     * @param  array<string, mixed>  $catalogMeta
     * @param  array<string, string>  $variantOptions
     * @param  array<string, string>  $productCustomFields
     * @return array<string, mixed>
     */
    private function mergeMetaLayer(
        array $existingMeta,
        array $extras,
        array $catalogMeta,
        array $variantOptions,
        int $stockAlert,
        array $productCustomFields = [],
    ): array {
        $meta = $existingMeta;
        if ($extras !== []) {
            $meta['import_extra'] = array_merge($meta['import_extra'] ?? [], $extras);
        }
        if ($catalogMeta !== []) {
            $meta['catalog'] = array_merge($meta['catalog'] ?? [], $catalogMeta);
        }
        if ($variantOptions !== []) {
            $meta['import_variant_options'] = array_merge($meta['import_variant_options'] ?? [], $variantOptions);
        }
        if ($productCustomFields !== []) {
            $meta['custom_fields'] = array_merge($meta['custom_fields'] ?? [], $productCustomFields);
        }
        if ($stockAlert > 0) {
            $meta['stock_alert'] = $stockAlert;
        }

        return $meta;
    }

    /**
     * @param  array<string, string>  $variantCustom
     */
    private function mergeVariantCustomFields(ProductVariant $variant, array $variantCustom): void
    {
        if ($variantCustom === []) {
            return;
        }
        $meta = $variant->meta ?? [];
        $meta['custom_fields'] = array_merge($meta['custom_fields'] ?? [], $variantCustom);
        $variant->update(['meta' => $meta]);
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function extractCustomFieldValues(array $row, array $customMappings): array
    {
        $product = [];
        $variant = [];
        foreach ($customMappings as $map) {
            $src = $map['source'];
            $key = $map['key'];
            $scope = $map['scope'];
            $val = trim((string) ($row[$src] ?? ''));
            if ($val === '') {
                continue;
            }
            if ($scope === 'attribute') {
                continue;
            }
            if ($scope === 'variant') {
                $variant[$key] = $val;
            } else {
                $product[$key] = $val;
            }
        }

        return [$product, $variant];
    }

    /**
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @return array<string, list<string>>
     */
    private function extractAttributeValues(array $row, array $customMappings): array
    {
        $attributes = [];
        foreach ($customMappings as $map) {
            if (($map['scope'] ?? '') !== 'attribute') {
                continue;
            }

            $source = (string) ($map['source'] ?? '');
            $key = trim((string) ($map['key'] ?? ''));
            if ($source === '' || $key === '') {
                continue;
            }

            foreach ($this->splitDelimited((string) ($row[$source] ?? '')) as $value) {
                if ($value !== '') {
                    $attributes[$key][] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, list<string>>  $attributeValues
     */
    private function syncAttributeValues(Store $store, Product $product, array $attributeValues, ?int $userId): void
    {
        if ($attributeValues === []) {
            return;
        }

        $assigner = app(ProductAttributeAssigner::class);
        foreach ($attributeValues as $attributeName => $values) {
            foreach (array_values(array_unique($values)) as $value) {
                $assigner->attachTermByNames($store, $product, $attributeName, $value, $userId);
            }
        }
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $headerKeys
     * @param  array<string, string>  $mapping
     * @param  list<array{source: string, key: string, scope: string}>  $customMappings
     * @return array<string, string>
     */
    private function collectUnmappedExtras(array $row, array $headerKeys, array $mapping, array $customMappings): array
    {
        $used = array_filter(array_values($mapping), static fn ($h) => is_string($h) && $h !== '');
        foreach ($customMappings as $cm) {
            $used[] = $cm['source'];
        }
        $used = array_values(array_unique($used));
        $extras = [];
        foreach ($headerKeys as $h) {
            if ($h === '' || in_array($h, $used, true)) {
                continue;
            }
            $val = trim((string) ($row[$h] ?? ''));
            if ($val !== '') {
                $extras[$h] = $val;
            }
        }

        return $extras;
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

    /**
     * @param  list<array{row:int, message:string}>  $failures
     */
    private function failPreparedRow(ProductImport $import, ProductImportRow $row, array &$failures, int &$failed, string $message): void
    {
        $failed++;
        $merchantMsg = ProductImportMerchantMessages::humanizeRowError($message);
        if (count($failures) < 200) {
            $failures[] = ['row' => (int) $row->row_number + 1, 'message' => $merchantMsg];
        }
        $this->markRow($import, $row->row_number, ProductImportRow::STATUS_FAILED, $merchantMsg);
    }

    private function markRow(ProductImport $import, int $rowNumber, string $status, ?string $error): void
    {
        ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('row_number', $rowNumber)
            ->update([
                'status' => $status,
                'error_message' => $error !== null
                    ? ProductImportMerchantMessages::truncateForStorage($error, 4000)
                    : null,
                'updated_at' => now(),
            ]);
    }
}
