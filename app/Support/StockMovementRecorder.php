<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use Illuminate\Support\Collection;

final class StockMovementRecorder
{
    /**
     * @return array<string, int> fingerprint => stock
     */
    public static function snapshotFingerprintsToStock(Product $product): array
    {
        $product->loadMissing(['variants.options.variationType']);

        $map = [];
        foreach ($product->variants as $variant) {
            $map[self::variantOptionFingerprint($variant)] = (int) $variant->stock;
        }

        return $map;
    }

    /**
     * Stable key for a variant line based on option values (survives option row id changes on product edit).
     */
    public static function variantOptionFingerprint(ProductVariant $variant): string
    {
        $variant->loadMissing(['options.variationType']);

        /** @var Collection<int, \App\Models\ProductVariationOption> $options */
        $options = $variant->options;

        if ($options->isEmpty()) {
            return '__default__';
        }

        $sorted = $options->sortBy(function ($option): array {
            $type = $option->variationType;

            return [
                (int) ($type?->id ?? 0),
                (int) $option->sort_order,
            ];
        })->values();

        return $sorted->map(static function ($option): string {
            $typeName = $option->relationLoaded('variationType')
                ? ($option->variationType?->name ?? 'type')
                : 'type';

            return $typeName.':'.$option->value;
        })->implode('|');
    }

    public static function recordInitial(
        Store $store,
        Product $product,
        ProductVariant $variant,
        int $newStock,
        ?int $performedBy,
        string $source,
        ?string $reason = null,
    ): void {
        self::assertStoreMatchesProduct($store, $product);
        self::assertVariantBelongsToProduct($product, $variant);

        StockMovement::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'previous_stock' => null,
            'quantity_change' => $newStock,
            'new_stock' => $newStock,
            'movement_type' => StockMovement::TYPE_INITIAL,
            'reason' => $reason,
            'source' => $source,
            'performed_by' => $performedBy,
        ]);
    }

    public static function recordAdjustment(
        Store $store,
        Product $product,
        ProductVariant $variant,
        int $previousStock,
        int $newStock,
        ?int $performedBy,
        string $source,
        string $movementType = StockMovement::TYPE_EDIT_UPDATE,
        ?string $reason = null,
    ): void {
        if ($previousStock === $newStock) {
            return;
        }

        self::assertStoreMatchesProduct($store, $product);
        self::assertVariantBelongsToProduct($product, $variant);

        StockMovement::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'previous_stock' => $previousStock,
            'quantity_change' => $newStock - $previousStock,
            'new_stock' => $newStock,
            'movement_type' => $movementType,
            'reason' => $reason,
            'source' => $source,
            'performed_by' => $performedBy,
        ]);
    }

    /**
     * Inventory change originating from bulk catalog import (append-only audit).
     */
    public static function recordImport(
        Store $store,
        Product $product,
        ProductVariant $variant,
        ?int $previousStock,
        int $newStock,
        ?int $performedBy,
        int $productImportId,
        ?string $reason = null,
    ): void {
        if ($previousStock !== null && $previousStock === $newStock) {
            return;
        }

        self::assertStoreMatchesProduct($store, $product);
        self::assertVariantBelongsToProduct($product, $variant);

        $delta = $previousStock === null ? $newStock : ($newStock - $previousStock);

        StockMovement::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'previous_stock' => $previousStock,
            'quantity_change' => $delta,
            'new_stock' => $newStock,
            'movement_type' => StockMovement::TYPE_IMPORT,
            'reason' => $reason ?? 'Catalog import',
            'source' => 'import',
            'reference_id' => $productImportId,
            'reference_type' => 'product_import',
            'performed_by' => $performedBy,
        ]);
    }

    /**
     * After variants are rebuilt, log initial lines, adjustments, and removed lines (variant_id null).
     *
     * @param  array<string, int>  $oldFingerprintToStock
     */
    public static function syncAfterVariantRebuild(
        Store $store,
        Product $product,
        array $oldFingerprintToStock,
        ?int $performedBy,
        string $source,
    ): void {
        self::assertStoreMatchesProduct($store, $product);

        $product->loadMissing(['variants.options.variationType']);

        $newByFp = [];
        foreach ($product->variants as $variant) {
            $fp = self::variantOptionFingerprint($variant);
            $newByFp[$fp] = $variant;
        }

        foreach ($oldFingerprintToStock as $fp => $oldStock) {
            if (! isset($newByFp[$fp]) && (int) $oldStock !== 0) {
                StockMovement::query()->create([
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'previous_stock' => $oldStock,
                    'quantity_change' => -$oldStock,
                    'new_stock' => 0,
                    'movement_type' => StockMovement::TYPE_MANUAL_ADJUSTMENT,
                    'reason' => 'Variant line removed',
                    'source' => $source,
                    'notes' => 'Fingerprint: '.$fp,
                    'performed_by' => $performedBy,
                ]);
            }
        }

        foreach ($product->variants as $variant) {
            $fp = self::variantOptionFingerprint($variant);
            $newStock = (int) $variant->stock;
            $oldStock = $oldFingerprintToStock[$fp] ?? null;

            if ($oldStock === null) {
                self::recordInitial($store, $product, $variant, $newStock, $performedBy, $source, 'New variant line');
            } else {
                self::recordAdjustment(
                    $store,
                    $product,
                    $variant,
                    (int) $oldStock,
                    $newStock,
                    $performedBy,
                    $source,
                    StockMovement::TYPE_EDIT_UPDATE,
                    'Stock changed on product edit'
                );
            }
        }
    }

    private static function assertStoreMatchesProduct(Store $store, Product $product): void
    {
        if ((int) $product->store_id !== (int) $store->id) {
            throw new \InvalidArgumentException('Stock movement store_id must match product store_id.');
        }
    }

    private static function assertVariantBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        if ((int) $variant->product_id !== (int) $product->id) {
            throw new \InvalidArgumentException('Stock movement variant must belong to the product.');
        }
    }
}
