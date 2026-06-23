<?php

namespace App\Services\Inventory;

use App\Models\ProductVariant;
use App\Models\StockMovement;

class InventoryBackfillService
{
    public function __construct(
        private readonly DefaultLocationService $defaultLocationService,
        private readonly InventorySyncService $syncService,
        private readonly InventoryAdjustmentService $adjustmentService,
    ) {}

    public function backfill(?int $storeId = null): array
    {
        $createdLocations = $this->defaultLocationService->ensureForAllStores($storeId);
        $createdItems = 0;
        $createdLevels = 0;
        $syncedVariants = 0;

        $query = ProductVariant::query()
            ->with('product.store')
            ->orderBy('id');

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $query->chunkById(100, function ($variants) use (&$createdItems, &$createdLevels, &$syncedVariants): void {
            foreach ($variants as $variant) {
                if (! $variant->product || ! $variant->product->store) {
                    continue;
                }

                $location = $this->defaultLocationService->ensureForStore($variant->product->store);
                $item = $this->syncService->ensureInventoryItemForVariant($variant);
                if ($item->wasRecentlyCreated) {
                    $createdItems++;
                }

                $level = $this->syncService->ensureLevel($item, $location, 0);
                if ($level->wasRecentlyCreated) {
                    $createdLevels++;
                    $target = max(0, (int) $variant->stock);
                    if ($target > 0) {
                        $this->adjustmentService->setAvailable($item, $location, $target, 'Inventory backfill from existing stock', null, [
                            'movement_type' => StockMovement::TYPE_BACKFILL,
                            'source' => 'inventory_backfill',
                            'previous_stock_for_movement' => null,
                        ]);
                    }
                } else {
                    $this->syncService->syncVariantStockCache($variant);
                }

                $syncedVariants++;
            }
        });

        return [
            'locations_created' => $createdLocations,
            'items_created' => $createdItems,
            'levels_created' => $createdLevels,
            'variants_synced' => $syncedVariants,
        ];
    }
}
