<?php

namespace App\Services\Notifications;

use App\Models\InventoryLevel;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\NotificationEvent;

/**
 * Emits low-stock merchant alerts after inventory mutations.
 */
class LowStockNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly NotificationCommitBoundary $boundary,
    ) {}

    public function checkVariant(ProductVariant $variant, ?Store $store = null): void
    {
        $variantId = (int) $variant->id;
        $storeId = $store !== null ? (int) $store->id : null;

        $this->boundary->run('inventory.low', function () use ($variantId, $storeId): void {
            $variant = ProductVariant::query()->with('product')->find($variantId);
            $product = $variant?->product;
            if (! $variant || ! $product) {
                return;
            }

            $productStoreId = (int) $product->store_id;

            // Never route a variant alert to a mismatched supplied store.
            if ($storeId !== null && $storeId !== $productStoreId) {
                return;
            }

            $store = Store::query()->find($storeId ?? $productStoreId);
            if (! $store || (int) $store->id !== $productStoreId) {
                return;
            }

            $this->emitIfLow($store, $variant, $product, (int) $variant->stock);
        }, ['variant_id' => $variantId, 'store_id' => $storeId]);
    }

    public function checkLevel(InventoryLevel $level): void
    {
        $levelId = (int) $level->id;
        $available = (int) $level->available;

        $this->boundary->run('inventory.low.level', function () use ($levelId, $available): void {
            $level = InventoryLevel::query()->with('inventoryItem.variant.product')->find($levelId);
            $variant = $level?->inventoryItem?->variant;
            $product = $variant?->product;
            if (! $variant || ! $product) {
                return;
            }

            $store = Store::query()->find($product->store_id);
            if (! $store || (int) $store->id !== (int) $product->store_id) {
                return;
            }

            $this->emitIfLow($store, $variant, $product, $available);
        }, ['inventory_level_id' => $levelId]);
    }

    private function emitIfLow(Store $store, ProductVariant $variant, $product, int $available): void
    {
        $alert = $variant->stock_alert;
        if ($alert === null || (int) $alert < 0) {
            return;
        }

        if ($available > (int) $alert) {
            return;
        }

        $sku = $variant->sku ?: ('variant #'.$variant->id);

        $this->dispatcher->notifyStore(
            store: $store,
            eventType: NotificationEvent::INVENTORY_LOW,
            title: 'Low stock: '.$product->name,
            body: sprintf(
                '%s (%s) has %d unit(s) left. Alert threshold is %d.',
                $product->name,
                $sku,
                $available,
                (int) $alert
            ),
            dedupeKey: 'inventory.low:variant:'.$variant->id.':level:'.$available,
            data: [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'available' => $available,
                'stock_alert' => (int) $alert,
                'action_url' => route('products.edit', $product),
                'action_label' => 'Manage inventory',
            ],
        );
    }
}
