<?php

namespace App\Services;

use App\Jobs\DeliverConnectedSiteCatalogEventJob;
use App\Models\Category;
use App\Models\ConnectedSite;
use App\Models\ConnectedSiteEventDelivery;
use App\Models\ConnectedSiteOutboxEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\CatalogRevision;
use App\Support\ConnectedSiteCatalogEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StorefrontCatalogEventRecorder
{
    /**
     * @param  array<string, mixed>  $resource
     */
    public function record(int $storeId, string $type, array $resource = []): ?ConnectedSiteOutboxEvent
    {
        if (! Schema::hasTable('connected_site_outbox_events')) {
            return null;
        }

        $store = Store::query()->find($storeId);
        if (! $store) {
            return null;
        }

        $occurredAt = now();
        $event = ConnectedSiteOutboxEvent::query()->create([
            'store_id' => $store->id,
            'public_id' => 'csevt_'.Str::lower(Str::random(24)),
            'type' => $type,
            'payload' => [
                'store_id' => $store->id,
                'product_id' => $resource['product_id'] ?? null,
                'variant_id' => $resource['variant_id'] ?? null,
                'category_id' => $resource['category_id'] ?? null,
                'published' => $resource['published'] ?? null,
            ],
            'catalog_version' => CatalogRevision::forStore($store),
            'occurred_at' => $occurredAt,
        ]);

        $this->queueDeliveries($event);

        return $event;
    }

    public function recordProductCreated(Product $product): void
    {
        $storeId = (int) $product->store_id;
        if ($storeId < 1 || ! $product->status) {
            return;
        }

        $this->record($storeId, ConnectedSiteCatalogEvent::PRODUCT_PUBLISHED, [
            'product_id' => $product->id,
            'published' => true,
        ]);
    }

    public function recordProductUpdated(Product $product): void
    {
        $storeId = (int) $product->store_id;
        if ($storeId < 1) {
            return;
        }

        if ($product->wasChanged('status')) {
            $this->record(
                $storeId,
                $product->status
                    ? ConnectedSiteCatalogEvent::PRODUCT_PUBLISHED
                    : ConnectedSiteCatalogEvent::PRODUCT_UNPUBLISHED,
                [
                    'product_id' => $product->id,
                    'published' => (bool) $product->status,
                ]
            );

            return;
        }

        if (! $product->wasChanged()) {
            return;
        }

        $this->record($storeId, ConnectedSiteCatalogEvent::PRODUCT_UPDATED, [
            'product_id' => $product->id,
            'published' => (bool) $product->status,
        ]);
    }

    public function recordProductDeleted(Product $product): void
    {
        $storeId = (int) $product->store_id;
        if ($storeId < 1) {
            return;
        }

        $this->record($storeId, ConnectedSiteCatalogEvent::PRODUCT_DELETED, [
            'product_id' => $product->id,
            'published' => false,
        ]);
    }

    public function recordVariantSaved(ProductVariant $variant, bool $created = false): void
    {
        $storeId = (int) ($variant->store_id ?: $variant->product?->store_id);
        if ($storeId < 1) {
            return;
        }

        if (! $created && ! $variant->wasChanged()) {
            return;
        }

        $resource = [
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
        ];

        if ($created || $variant->wasChanged('stock')) {
            $this->record($storeId, ConnectedSiteCatalogEvent::INVENTORY_AVAILABILITY_CHANGED, $resource);

            return;
        }

        $this->record($storeId, ConnectedSiteCatalogEvent::VARIANT_UPDATED, $resource);
    }

    public function recordInventoryChanged(ProductVariant $variant): void
    {
        $storeId = (int) ($variant->store_id ?: $variant->product?->store_id);
        if ($storeId < 1) {
            return;
        }

        $this->record($storeId, ConnectedSiteCatalogEvent::INVENTORY_AVAILABILITY_CHANGED, [
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
        ]);
    }

    public function recordCategorySaved(Category $category, bool $created = false): void
    {
        $storeId = (int) $category->store_id;
        if ($storeId < 1) {
            return;
        }

        if (! $created && ! $category->wasChanged()) {
            return;
        }

        $this->record($storeId, ConnectedSiteCatalogEvent::CATEGORY_UPDATED, [
            'category_id' => $category->id,
        ]);
    }

    public function recordCategoryDeleted(Category $category): void
    {
        $storeId = (int) $category->store_id;
        if ($storeId < 1) {
            return;
        }

        $this->record($storeId, ConnectedSiteCatalogEvent::CATEGORY_UPDATED, [
            'category_id' => $category->id,
        ]);
    }

    public function recordVariantDeleted(ProductVariant $variant): void
    {
        $storeId = (int) ($variant->store_id ?: $variant->product?->store_id);
        if ($storeId < 1) {
            return;
        }

        $this->record($storeId, ConnectedSiteCatalogEvent::VARIANT_UPDATED, [
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
        ]);
    }

    public function recordProductRestored(Product $product): void
    {
        if (! $product->status) {
            return;
        }

        $this->record((int) $product->store_id, ConnectedSiteCatalogEvent::PRODUCT_PUBLISHED, [
            'product_id' => $product->id,
            'published' => true,
        ]);
    }

    public function recordCatalogUpdated(int $storeId, array $productIds = []): void
    {
        $this->record($storeId, ConnectedSiteCatalogEvent::CATALOG_UPDATED, [
            'product_id' => $productIds[0] ?? null,
        ]);
    }

    private function queueDeliveries(ConnectedSiteOutboxEvent $event): void
    {
        if (! Schema::hasTable('connected_site_event_deliveries')) {
            return;
        }

        $sites = ConnectedSite::query()
            ->where('store_id', $event->store_id)
            ->where('status', ConnectedSite::STATUS_ACTIVE)
            ->whereNotNull('site_url')
            ->where('site_url', '!=', '')
            ->get();

        foreach ($sites as $site) {
            $delivery = ConnectedSiteEventDelivery::query()->firstOrCreate(
                [
                    'connected_site_id' => $site->id,
                    'outbox_event_id' => $event->id,
                ],
                [
                    'status' => ConnectedSiteEventDelivery::STATUS_PENDING,
                    'next_retry_at' => now(),
                ]
            );

            $dispatch = static function () use ($delivery): void {
                DeliverConnectedSiteCatalogEventJob::dispatch($delivery->id);
            };

            if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
                DB::afterCommit($dispatch);
            } else {
                $dispatch();
            }
        }
    }
}
