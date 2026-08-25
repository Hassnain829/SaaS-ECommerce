<?php

namespace App\Services\Delivery;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use InvalidArgumentException;

final class VariantShippingWeightBulkService
{
    private const CHUNK_SIZE = 250;

    public function __construct(
        private readonly ShippingWeightResolver $weightResolver,
        private readonly StoreShippingPreferences $shippingPreferences,
        private readonly VariantOptionWeightParser $optionWeightParser,
    ) {}

    /**
     * @param  list<int>  $productIds
     * @param  array<string, float>  $valueWeightMap
     * @return array{
     *     selected_products_count: int,
     *     compatible_products_count: int,
     *     incompatible_products_count: int,
     *     matching_variants_count: int,
     *     would_update_count: int,
     *     would_skip_existing_count: int,
     *     would_skip_unmatched_count: int,
     *     option_groups: list<array{name: string, product_count: int, variant_count: int, option_values: list<string>, weight_related: bool}>,
     *     parsed_weights: list<array{option_value: string, parsed_weight: float|null, parse_ok: bool}>,
     *     weight_unit: string
     * }
     */
    public function preview(
        Store $store,
        array $productIds,
        string $variantBulkMode,
        string $optionGroupName = '',
        array $valueWeightMap = [],
        string $applyMode = 'missing_only',
    ): array {
        $normalizedGroup = $this->normalizeOptionGroupName($optionGroupName);
        $weightUnit = $this->shippingPreferences->weightUnitLabel($store);
        $maxWeight = $this->shippingPreferences->maxItemWeightForStore($store);

        $parsedWeights = [];
        if ($variantBulkMode === 'use_option_values' && $normalizedGroup !== '') {
            $values = $this->collectOptionValuesChunked($store, $productIds, $normalizedGroup);
            $assumeBare = $this->optionWeightParser->optionGroupLooksWeightRelated($optionGroupName);
            $parsedMap = $this->optionWeightParser->buildWeightMapFromOptionValues($values, $weightUnit, $assumeBare);
            foreach ($parsedMap as $optionValue => $parsed) {
                $parsedWeights[] = [
                    'option_value' => $optionValue,
                    'parsed_weight' => $parsed,
                    'parse_ok' => $parsed !== null,
                ];
            }
            $valueWeightMap = array_filter($parsedMap, static fn (?float $w): bool => $w !== null);
        }

        $normalizedMap = $this->normalizeValueWeightMap($valueWeightMap, $maxWeight);

        $stats = [
            'selected_products_count' => count($productIds),
            'compatible_products_count' => 0,
            'incompatible_products_count' => 0,
            'matching_variants_count' => 0,
            'would_update_count' => 0,
            'would_skip_existing_count' => 0,
            'would_skip_unmatched_count' => 0,
        ];
        /** @var array<string, array{name: string, product_count: int, variant_count: int, option_values: array<string, true>, weight_related: bool}> $groups */
        $groups = [];

        foreach (array_chunk($productIds, self::CHUNK_SIZE) as $chunkIds) {
            $products = $this->loadProductChunk($store, $chunkIds);
            $chunkStats = $this->analyzeVariants(
                $products,
                $normalizedGroup,
                $normalizedMap,
                $variantBulkMode,
                $applyMode,
            );
            $stats['compatible_products_count'] += $chunkStats['compatible_products_count'];
            $stats['matching_variants_count'] += $chunkStats['matching_variants_count'];
            $stats['would_update_count'] += $chunkStats['would_update_count'];
            $stats['would_skip_existing_count'] += $chunkStats['would_skip_existing_count'];
            $stats['would_skip_unmatched_count'] += $chunkStats['would_skip_unmatched_count'];
            $this->accumulateOptionGroups($products, $groups);
            unset($products);
        }

        $stats['incompatible_products_count'] = max(0, $stats['selected_products_count'] - $stats['compatible_products_count']);

        return array_merge($stats, [
            'option_groups' => $this->finalizeOptionGroups($groups),
            'parsed_weights' => $parsedWeights,
            'weight_unit' => $weightUnit,
        ]);
    }

    /**
     * @param  list<int>  $productIds
     * @param  array<string, float>  $valueWeightMap
     * @return array{updated_variant_count: int, skipped_existing_count: int, skipped_unmatched_count: int, skipped_non_shipping_count: int, updated_product_ids: list<int>}
     */
    public function apply(
        Store $store,
        array $productIds,
        string $variantBulkMode,
        string $optionGroupName = '',
        array $valueWeightMap = [],
        string $applyMode = 'missing_only',
    ): array {
        $normalizedGroup = $this->normalizeOptionGroupName($optionGroupName);
        $weightUnit = $this->shippingPreferences->weightUnitLabel($store);
        $maxWeight = $this->shippingPreferences->maxItemWeightForStore($store);

        if ($variantBulkMode === 'use_option_values') {
            if ($normalizedGroup === '') {
                throw new InvalidArgumentException('Choose an option group before applying option-value weights.');
            }

            $values = $this->collectOptionValuesChunked($store, $productIds, $normalizedGroup);
            if (! $this->optionWeightParser->isSafeForAutomaticWeightParse($optionGroupName, $values)) {
                throw new InvalidArgumentException(
                    'Option values can only be used as weights when the option group is weight-related, or every value includes an explicit unit such as lb, oz, or kg.'
                );
            }

            $parsedMap = $this->optionWeightParser->buildWeightMapFromOptionValues(
                $values,
                $weightUnit,
                $this->optionWeightParser->optionGroupLooksWeightRelated($optionGroupName),
            );
            $valueWeightMap = array_filter($parsedMap, static fn (?float $w): bool => $w !== null);
            if ($valueWeightMap === []) {
                throw new InvalidArgumentException('No option values could be parsed into valid shipping weights for this store.');
            }
        }

        $normalizedMap = $this->normalizeValueWeightMap($valueWeightMap, $maxWeight);
        if ($variantBulkMode === 'map_by_option' && $normalizedMap === []) {
            throw new InvalidArgumentException('Enter at least one valid option value weight within the store maximum.');
        }

        $updated = 0;
        $skippedExisting = 0;
        $skippedUnmatched = 0;
        $skippedNonShipping = 0;
        $updatedProductIds = [];

        foreach (array_chunk($productIds, self::CHUNK_SIZE) as $chunkIds) {
            $products = $this->loadProductChunk($store, $chunkIds);

            foreach ($products as $product) {
                if (! (bool) $product->requires_shipping) {
                    $skippedNonShipping += $product->variants->count();

                    continue;
                }

                $variationType = $this->findVariationType($product, $normalizedGroup);

                foreach ($product->variants as $variant) {
                    if ($variantBulkMode === 'clear') {
                        if ($this->weightResolver->resolveExactVariantLevel($variant) === null) {
                            continue;
                        }

                        $meta = is_array($variant->meta) ? $variant->meta : [];
                        $this->weightResolver->persistVariantShippingWeightMeta($meta, null);
                        $variant->forceFill(['meta' => $meta])->save();
                        $updated++;
                        $updatedProductIds[(int) $product->id] = (int) $product->id;

                        continue;
                    }

                    if ($variationType === null) {
                        $skippedUnmatched++;

                        continue;
                    }

                    $optionValue = $this->variantOptionValueForType($variant, $variationType);
                    if ($optionValue === null) {
                        $skippedUnmatched++;

                        continue;
                    }

                    $targetWeight = $normalizedMap[$this->normalizeOptionValueKey($optionValue)] ?? null;
                    if ($targetWeight === null) {
                        $skippedUnmatched++;

                        continue;
                    }

                    if ($applyMode === 'missing_only' && $this->weightResolver->resolveExactVariantLevel($variant) !== null) {
                        $skippedExisting++;

                        continue;
                    }

                    $meta = is_array($variant->meta) ? $variant->meta : [];
                    $this->weightResolver->persistVariantShippingWeightMeta($meta, $targetWeight);
                    $variant->forceFill(['meta' => $meta])->save();
                    $updated++;
                    $updatedProductIds[(int) $product->id] = (int) $product->id;
                }
            }

            unset($products);
        }

        return [
            'updated_variant_count' => $updated,
            'skipped_existing_count' => $skippedExisting,
            'skipped_unmatched_count' => $skippedUnmatched,
            'skipped_non_shipping_count' => $skippedNonShipping,
            'updated_product_ids' => array_values($updatedProductIds),
        ];
    }

    /**
     * @param  list<int>  $productIds
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function loadProductChunk(Store $store, array $productIds)
    {
        return Product::query()
            ->where('store_id', $store->id)
            ->whereIn('id', $productIds)
            ->with([
                'variationTypes.options' => fn ($query) => $query->orderBy('sort_order'),
                'variants.options',
            ])
            ->get();
    }

    /**
     * @param  list<int>  $productIds
     * @return list<string>
     */
    private function collectOptionValuesChunked(Store $store, array $productIds, string $normalizedGroup): array
    {
        $values = [];

        foreach (array_chunk($productIds, self::CHUNK_SIZE) as $chunkIds) {
            $products = $this->loadProductChunk($store, $chunkIds);
            foreach ($products as $product) {
                $variationType = $this->findVariationType($product, $normalizedGroup);
                if ($variationType === null) {
                    continue;
                }

                foreach ($variationType->options as $option) {
                    $value = trim((string) $option->value);
                    if ($value !== '') {
                        $values[$value] = true;
                    }
                }
            }
            unset($products);
        }

        $list = array_keys($values);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  array<string, array{name: string, product_count: int, variant_count: int, option_values: array<string, true>, weight_related: bool}>  $groups
     */
    private function accumulateOptionGroups($products, array &$groups): void
    {
        foreach ($products as $product) {
            if (! (bool) $product->requires_shipping) {
                continue;
            }

            foreach ($product->variationTypes as $variationType) {
                $name = trim((string) $variationType->name);
                if ($name === '') {
                    continue;
                }

                $key = $this->normalizeOptionGroupName($name);
                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'name' => $name,
                        'product_count' => 0,
                        'variant_count' => 0,
                        'option_values' => [],
                        'weight_related' => $this->optionWeightParser->optionGroupLooksWeightRelated($name),
                    ];
                }

                $groups[$key]['product_count']++;
                $groups[$key]['variant_count'] += $product->variants->count();

                foreach ($variationType->options as $option) {
                    $value = trim((string) $option->value);
                    if ($value !== '') {
                        $groups[$key]['option_values'][$value] = true;
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, array{name: string, product_count: int, variant_count: int, option_values: array<string, true>, weight_related: bool}>  $groups
     * @return list<array{name: string, product_count: int, variant_count: int, option_values: list<string>, weight_related: bool}>
     */
    private function finalizeOptionGroups(array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            $values = array_keys($group['option_values']);
            sort($values, SORT_NATURAL | SORT_FLAG_CASE);
            $result[] = [
                'name' => $group['name'],
                'product_count' => $group['product_count'],
                'variant_count' => $group['variant_count'],
                'option_values' => $values,
                'weight_related' => $group['weight_related'],
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  array<string, float>  $normalizedMap
     * @return array{
     *     compatible_products_count: int,
     *     matching_variants_count: int,
     *     would_update_count: int,
     *     would_skip_existing_count: int,
     *     would_skip_unmatched_count: int
     * }
     */
    private function analyzeVariants(
        $products,
        string $normalizedGroup,
        array $normalizedMap,
        string $variantBulkMode,
        string $applyMode,
    ): array {
        $compatible = 0;
        $matching = 0;
        $wouldUpdate = 0;
        $wouldSkipExisting = 0;
        $wouldSkipUnmatched = 0;

        foreach ($products as $product) {
            if (! (bool) $product->requires_shipping) {
                continue;
            }

            if ($variantBulkMode === 'clear') {
                $productHasClearable = false;
                foreach ($product->variants as $variant) {
                    if ($this->weightResolver->resolveExactVariantLevel($variant) !== null) {
                        $matching++;
                        $wouldUpdate++;
                        $productHasClearable = true;
                    }
                }
                if ($productHasClearable) {
                    $compatible++;
                }

                continue;
            }

            $variationType = $this->findVariationType($product, $normalizedGroup);
            if ($variationType === null) {
                continue;
            }

            $compatible++;

            foreach ($product->variants as $variant) {
                $optionValue = $this->variantOptionValueForType($variant, $variationType);
                if ($optionValue === null) {
                    $wouldSkipUnmatched++;

                    continue;
                }

                $targetWeight = $normalizedMap[$this->normalizeOptionValueKey($optionValue)] ?? null;
                if ($targetWeight === null) {
                    $wouldSkipUnmatched++;

                    continue;
                }

                $matching++;

                if ($applyMode === 'missing_only' && $this->weightResolver->resolveExactVariantLevel($variant) !== null) {
                    $wouldSkipExisting++;

                    continue;
                }

                $wouldUpdate++;
            }
        }

        return [
            'compatible_products_count' => $compatible,
            'matching_variants_count' => $matching,
            'would_update_count' => $wouldUpdate,
            'would_skip_existing_count' => $wouldSkipExisting,
            'would_skip_unmatched_count' => $wouldSkipUnmatched,
        ];
    }

    private function findVariationType(Product $product, string $normalizedGroupName): ?\App\Models\ProductVariationType
    {
        if ($normalizedGroupName === '') {
            return null;
        }

        foreach ($product->variationTypes as $variationType) {
            if ($this->normalizeOptionGroupName((string) $variationType->name) === $normalizedGroupName) {
                return $variationType;
            }
        }

        return null;
    }

    private function variantOptionValueForType(ProductVariant $variant, \App\Models\ProductVariationType $variationType): ?string
    {
        foreach ($variant->options as $option) {
            if ((int) $option->variation_type_id === (int) $variationType->id) {
                $value = trim((string) $option->value);

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string|int, mixed>  $valueWeightMap
     * @return array<string, float>
     */
    private function normalizeValueWeightMap(array $valueWeightMap, float $maxWeight): array
    {
        $normalized = [];
        foreach ($valueWeightMap as $key => $weight) {
            $valueKey = $this->normalizeOptionValueKey((string) $key);
            $parsed = $this->weightResolver->normalizePositiveWeight($weight);
            if ($valueKey === '' || $parsed === null || $parsed > $maxWeight) {
                continue;
            }
            $normalized[$valueKey] = $parsed;
        }

        return $normalized;
    }

    private function normalizeOptionGroupName(string $name): string
    {
        return strtolower(trim($name));
    }

    private function normalizeOptionValueKey(string $value): string
    {
        return strtolower(trim($value));
    }
}
