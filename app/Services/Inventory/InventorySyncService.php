<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLevel;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;

class InventorySyncService
{
    public function __construct(private readonly DefaultLocationService $defaultLocationService)
    {
    }

    public function ensureInventoryItemForVariant(ProductVariant $variant): InventoryItem
    {
        $variant->loadMissing('product');
        $product = $variant->product;

        if (! $product) {
            throw new \InvalidArgumentException('Inventory item requires a product-backed variant.');
        }

        /** @var InventoryItem $item */
        $item = InventoryItem::query()->withTrashed()->firstOrNew([
            'store_id' => $product->store_id,
            'variant_id' => $variant->id,
        ]);

        if ($item->exists && method_exists($item, 'trashed') && $item->trashed()) {
            $item->restore();
        }

        $item->fill([
            'product_id' => $product->id,
            'sku' => $variant->sku,
            'tracked' => (bool) ($product->track_inventory ?? true),
        ]);
        $item->save();

        return $item->refresh();
    }

    public function ensureLevel(InventoryItem $item, Location $location, ?int $initialAvailable = null): InventoryLevel
    {
        if ((int) $item->store_id !== (int) $location->store_id) {
            throw new \InvalidArgumentException('Inventory level location must belong to the same store as the item.');
        }

        return InventoryLevel::query()->firstOrCreate(
            [
                'store_id' => $item->store_id,
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
            ],
            [
                'available' => max(0, (int) ($initialAvailable ?? 0)),
                'reserved' => 0,
                'committed' => 0,
                'incoming' => 0,
            ]
        );
    }

    public function ensureDefaultLevelForVariant(ProductVariant $variant, ?int $initialAvailable = null): InventoryLevel
    {
        $variant->loadMissing('product.store');
        $product = $variant->product;

        if (! $product || ! $product->store) {
            throw new \InvalidArgumentException('Variant must have a store-scoped product before inventory can be created.');
        }

        $item = $this->ensureInventoryItemForVariant($variant);
        $location = $this->defaultLocationService->ensureForStore($product->store);

        return $this->ensureLevel($item, $location, $initialAvailable ?? (int) $variant->stock);
    }

    public function syncVariantStockCache(ProductVariant $variant): void
    {
        $item = $this->ensureInventoryItemForVariant($variant);

        $available = (int) $item->levels()
            ->whereHas('location', fn ($query) => $query->where('is_active', true))
            ->sum('available');

        if ((int) $variant->stock !== $available) {
            $variant->forceFill(['stock' => $available])->saveQuietly();
        }
    }

    public function syncProductVariantsStockCache(Product $product): void
    {
        $product->loadMissing('variants');

        foreach ($product->variants as $variant) {
            $this->syncVariantStockCache($variant);
        }
    }
}
