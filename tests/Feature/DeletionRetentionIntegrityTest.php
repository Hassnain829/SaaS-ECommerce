<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\ConnectedSiteEventDelivery;
use App\Models\Customer;
use App\Models\FedExTradeDocument;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\ReturnItem;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\ConnectedSiteCatalogEventDeliveryService;
use App\Services\ConnectedSiteService;
use App\Services\SecurityLogRecorder;
use App\Services\Store\StorePurgeService;
use App\Support\OrderLifecycle;
use App\Support\RefundLifecycle;
use App\Support\ReturnLifecycle;
use App\Support\StockMovementIdentitySnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeletionRetentionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_soft_delete_remains_recoverable(): void
    {
        Storage::fake('public');
        [$owner, $store] = $this->ownerStore();
        [$product, $variant, $orderItem, $movement, $imagePath] = $this->seedCatalogCommerce($store);

        $product->delete();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        Storage::disk('public')->assertExists($imagePath);
        $this->assertDatabaseHas('order_items', ['id' => $orderItem->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'product_id' => $product->id]);

        $product->restore();
        $this->assertNull($product->fresh()->deleted_at);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    public function test_product_force_delete_preserves_historical_order_line(): void
    {
        [$owner, $store] = $this->ownerStore();
        [$product, $variant, $orderItem] = $this->seedCatalogCommerce($store);
        $order = $orderItem->order;

        $snapshots = [
            'product_name' => $orderItem->product_name,
            'sku_snapshot' => $orderItem->sku_snapshot,
            'unit_price' => (string) $orderItem->unit_price,
            'variant_label' => $orderItem->variant_label,
        ];

        $product->forceDelete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);

        $freshItem = OrderItem::query()->findOrFail($orderItem->id);
        $this->assertNull($freshItem->product_id);
        $this->assertNull($freshItem->product_variant_id);
        $this->assertSame($snapshots['product_name'], $freshItem->product_name);
        $this->assertSame($snapshots['sku_snapshot'], $freshItem->sku_snapshot);
        $this->assertSame($snapshots['unit_price'], (string) $freshItem->unit_price);
        $this->assertSame($snapshots['variant_label'], $freshItem->variant_label);
        $this->assertNull($freshItem->product);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText($snapshots['product_name'])
            ->assertSeeText('SKU '.$snapshots['sku_snapshot']);
    }

    public function test_product_force_delete_preserves_inventory_ledger(): void
    {
        [$owner, $store] = $this->ownerStore();
        [$product, $variant, , $movement] = $this->seedCatalogCommerce($store);

        $this->assertSame('Retention Product', $movement->product_name_snapshot);
        $this->assertNotNull($movement->sku_snapshot);

        $product->forceDelete();

        $fresh = StockMovement::query()->findOrFail($movement->id);
        $this->assertNull($fresh->product_id);
        $this->assertNull($fresh->variant_id);
        $this->assertSame('Retention Product', $fresh->product_name_snapshot);
        $this->assertSame($movement->sku_snapshot, $fresh->sku_snapshot);
        $this->assertSame($movement->quantity_change, $fresh->quantity_change);
        $this->assertSame($movement->previous_stock, $fresh->previous_stock);
        $this->assertSame($movement->new_stock, $fresh->new_stock);
        $this->assertSame($movement->movement_type, $fresh->movement_type);
    }

    public function test_return_refund_shipment_history_survives_catalog_purge(): void
    {
        [$owner, $store] = $this->ownerStore();
        [$product, $variant, $orderItem] = $this->seedCatalogCommerce($store);
        $order = $orderItem->order;

        $return = OrderReturn::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'return_number' => 'RMA-RET-1',
            'status' => ReturnLifecycle::STATUS_REQUESTED,
            'source' => ReturnLifecycle::SOURCE_MERCHANT,
            'requested_at' => now(),
        ]);
        $returnItem = ReturnItem::query()->create([
            'store_id' => $store->id,
            'return_id' => $return->id,
            'order_item_id' => $orderItem->id,
            'requested_quantity' => 1,
            'product_name_snapshot' => $orderItem->product_name,
            'sku_snapshot' => $orderItem->sku_snapshot,
            'variant_label_snapshot' => $orderItem->variant_label,
            'product_type_snapshot' => $orderItem->product_type_snapshot,
        ]);

        $refund = Refund::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'refund_number' => 'RFN-RET-1',
            'status' => RefundLifecycle::STATUS_SUCCEEDED,
            'method' => RefundLifecycle::METHOD_EXTERNAL,
            'amount' => '10.00',
            'amount_minor' => 1000,
            'currency_code' => 'USD',
            'idempotency_key' => 'refund-retention-1',
            'processed_at' => now(),
        ]);
        $refundItem = RefundItem::query()->create([
            'store_id' => $store->id,
            'refund_id' => $refund->id,
            'order_item_id' => $orderItem->id,
            'quantity' => 1,
            'unit_amount' => '10.00',
            'subtotal' => '10.00',
            'total' => '10.00',
            'total_minor' => 1000,
            'product_name_snapshot' => $orderItem->product_name,
            'sku_snapshot' => $orderItem->sku_snapshot,
        ]);

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-RET-1',
            'status' => Shipment::STATUS_SHIPPED,
        ]);
        $shipmentItem = ShipmentItem::query()->create([
            'store_id' => $store->id,
            'shipment_id' => $shipment->id,
            'order_item_id' => $orderItem->id,
            'quantity' => 1,
        ]);

        $product->forceDelete();

        $this->assertDatabaseHas('returns', ['id' => $return->id]);
        $this->assertDatabaseHas('return_items', [
            'id' => $returnItem->id,
            'product_name_snapshot' => 'Retention Product',
        ]);
        $this->assertDatabaseHas('refunds', ['id' => $refund->id]);
        $this->assertDatabaseHas('refund_items', [
            'id' => $refundItem->id,
            'product_name_snapshot' => 'Retention Product',
        ]);
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id]);
        $this->assertDatabaseHas('shipment_items', ['id' => $shipmentItem->id, 'order_item_id' => $orderItem->id]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Retention Product');
    }

    public function test_user_deletion_cannot_delete_store(): void
    {
        $ownerA = $this->merchant('owner-a@example.com');
        $ownerB = $this->merchant('owner-b@example.com');
        $store = $this->makeStore($ownerA, 'Shared Store');
        $store->members()->syncWithoutDetaching([
            $ownerA->id => ['role' => Store::ROLE_OWNER],
            $ownerB->id => ['role' => Store::ROLE_OWNER],
        ]);
        [$product] = $this->seedCatalogCommerce($store);

        $ownerA->delete();

        $freshStore = Store::query()->findOrFail($store->id);
        $this->assertNull($freshStore->user_id);
        $this->assertTrue($ownerB->fresh()->belongsToStore($freshStore));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'store_id' => $store->id]);
        $this->assertDatabaseHas('orders', ['store_id' => $store->id]);
    }

    public function test_merchant_store_deletion_is_soft(): void
    {
        [$owner, $store] = $this->ownerStore('Soft Close Store');
        [$product, , $orderItem, $movement] = $this->seedCatalogCommerce($store);
        $customer = Customer::query()->where('store_id', $store->id)->firstOrFail();
        Checkout::query()->create([
            'store_id' => $store->id,
            'checkout_number' => 'CHK-RET-1',
            'status' => Checkout::STATUS_PAYMENT_PENDING,
            'currency_code' => 'USD',
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10,
        ]);
        ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Domestic',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        SecurityLog::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'store_fixture',
            'severity' => SecurityLog::SEVERITY_INFO,
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('store.destroy', ['storeId' => $store->id]))
            ->assertRedirect(route('store-management'))
            ->assertSessionHas('success_title', 'Store closed');

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        $this->assertNull(Store::query()->find($store->id));
        $this->assertNotNull(Store::withTrashed()->find($store->id)?->deleted_at);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', ['id' => $orderItem->order_id]);
        $this->assertDatabaseHas('checkouts', ['store_id' => $store->id]);
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id]);
        $this->assertDatabaseHas('shipping_zones', ['store_id' => $store->id]);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'store_closed',
        ]);
    }

    public function test_closed_store_is_no_longer_operational(): void
    {
        [$owner, $store] = $this->ownerStore('Operational Close');
        $issued = app(ConnectedSiteService::class)->issuePrimaryCredential($store);
        $plain = $issued['plain'];
        $this->seedCatalogCommerce($store);

        $store->delete();

        $this->assertFalse($owner->fresh()->memberStores()->where('stores.id', $store->id)->exists());

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSee('id="closed-stores"', false)
            ->assertSeeText('Operational Close');

        $this->assertStringNotContainsString(
            'data-store-name="Operational Close"',
            (string) $response->getContent()
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('current-store.update'), ['store_id' => $store->id])
            ->assertNotFound();

        $this->withToken($plain)
            ->getJson('/api/developer-storefront/catalog')
            ->assertUnauthorized();

        $this->withToken($plain)
            ->postJson('/api/v1/checkout', [
                'customer' => ['email' => 'buyer@example.test'],
                'items' => [],
            ])
            ->assertUnauthorized();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertRedirect(route('store-management'));
    }

    public function test_final_store_purge_removes_db_and_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        [$owner, $store] = $this->ownerStore('Purge Target');
        $logoPath = 'store-logos/'.$store->id.'/logo.png';
        Storage::disk('public')->put($logoPath, 'logo');
        $store->forceFill(['logo' => $logoPath])->save();

        [$product, , , , $imagePath] = $this->seedCatalogCommerce($store);
        $importPath = 'product-imports/'.$store->id.'/import.csv';
        Storage::disk('local')->put($importPath, 'sku,name');

        SecurityLog::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'pre_purge_marker',
            'severity' => SecurityLog::SEVERITY_INFO,
            'created_at' => now(),
        ]);

        $store->delete();
        $closed = Store::onlyTrashed()->findOrFail($store->id);

        $result = app(StorePurgeService::class)->purge($closed, $owner);

        $this->assertTrue($result['force_deleted']);
        $this->assertDatabaseMissing('stores', ['id' => $store->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing($logoPath);
        Storage::disk('public')->assertMissing($imagePath);
        Storage::disk('local')->assertMissing($importPath);

        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'pre_purge_marker',
            'store_id' => null,
        ]);
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'store_purged',
            'store_id' => null,
            'user_id' => $owner->id,
        ]);
    }

    public function test_store_purge_is_cross_store_safe(): void
    {
        Storage::fake('public');

        [$owner, $storeA] = $this->ownerStore('Store A Purge');
        $storeB = $this->makeStore($owner, 'Store B Keep');
        $storeB->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $pathA = 'products/'.$storeA->id.'/a.jpg';
        $pathB = 'products/'.$storeB->id.'/b.jpg';
        Storage::disk('public')->put($pathA, 'a');
        Storage::disk('public')->put($pathB, 'b');

        [$productA] = $this->seedCatalogCommerce($storeA, 'Product A', $pathA);
        [$productB] = $this->seedCatalogCommerce($storeB, 'Product B', $pathB);

        $storeA->delete();
        app(StorePurgeService::class)->purge(Store::onlyTrashed()->findOrFail($storeA->id), $owner);

        $this->assertDatabaseMissing('stores', ['id' => $storeA->id]);
        $this->assertDatabaseMissing('products', ['id' => $productA->id]);
        Storage::disk('public')->assertMissing($pathA);

        $this->assertDatabaseHas('stores', ['id' => $storeB->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('products', ['id' => $productB->id, 'store_id' => $storeB->id]);
        Storage::disk('public')->assertExists($pathB);
        $this->assertNotNull(Order::query()->where('store_id', $storeB->id)->first());
    }

    public function test_purge_aborts_on_hostile_cross_store_image_paths(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        [$owner, $storeA] = $this->ownerStore('Hostile A');
        $storeB = $this->makeStore($owner, 'Hostile B');
        $storeB->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $pathB = 'products/'.$storeB->id.'/owned-by-b.jpg';
        Storage::disk('public')->put($pathB, 'b-bytes');

        $productA = $this->makeProduct($storeA, 'A With Hostile Path');
        ProductImage::query()->create([
            'product_id' => $productA->id,
            'image_path' => $pathB,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);

        $service = app(StorePurgeService::class);
        $this->assertFalse($service->pathBelongsToStore((int) $storeA->id, 'product_image', $pathB));

        $storeA->delete();

        try {
            $service->purge(Store::onlyTrashed()->findOrFail($storeA->id), $owner);
            $this->fail('Expected purge to abort on unsafe artifact path.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('unsafe or unowned artifact path', $e->getMessage());
        }

        Storage::disk('public')->assertExists($pathB);
        $this->assertSoftDeleted('stores', ['id' => $storeA->id]);
        $this->assertDatabaseHas('stores', ['id' => $storeB->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('products', ['id' => $productA->id]);
    }

    public function test_product_permanent_delete_aborts_on_hostile_cross_store_image_path(): void
    {
        Storage::fake('public');

        [$owner, $storeA] = $this->ownerStore('Product Hostile A');
        $storeB = $this->makeStore($owner, 'Product Hostile B');
        $storeB->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $pathB = 'products/'.$storeB->id.'/owned-by-b.jpg';
        Storage::disk('public')->put($pathB, 'b-bytes');

        $productA = $this->makeProduct($storeA, 'A With Hostile Path');
        ProductImage::query()->create([
            'product_id' => $productA->id,
            'image_path' => $pathB,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);
        $productA->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->delete(route('product.force-destroy', ['productId' => $productA->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']))
            ->assertSessionHas('error');

        Storage::disk('public')->assertExists($pathB);
        $this->assertSoftDeleted('products', ['id' => $productA->id]);
        $this->assertDatabaseHas('product_images', ['product_id' => $productA->id, 'image_path' => $pathB]);
    }

    public function test_product_permanent_delete_aborts_when_gallery_staging_move_fails(): void
    {
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('Product Staging Fail');
        $path = 'products/'.$store->id.'/stuck.jpg';
        Storage::disk('public')->put($path, 'stuck');
        $product = $this->makeProduct($store, 'Stuck Image Product');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);
        $product->delete();

        $mock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mock->shouldReceive('exists')->andReturnUsing(fn (string $p): bool => $p === $path);
        $mock->shouldReceive('move')->andReturn(false);
        Storage::set('public', $mock);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $product->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'image_path' => $path]);
    }

    public function test_product_permanent_delete_restores_gallery_when_db_transaction_fails(): void
    {
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('Product DB Fail Restore');
        $path = 'products/'.$store->id.'/restore-me.jpg';
        Storage::disk('public')->put($path, 'restore-bytes');
        $product = $this->makeProduct($store, 'Restore Gallery Product');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);
        $product->delete();

        Product::deleting(function (Product $model): void {
            if ($model->isForceDeleting()) {
                throw new QueryException(
                    'sqlite',
                    'delete from products where id = ?',
                    [$model->id],
                    new \RuntimeException('simulated db failure')
                );
            }
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $product->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']))
            ->assertSessionHas('error');

        Storage::disk('public')->assertExists($path);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'image_path' => $path]);
        $this->assertEmpty(Storage::disk('public')->allFiles('product-delete-quarantine'));
    }

    public function test_bulk_product_permanent_delete_restores_all_galleries_when_db_transaction_fails(): void
    {
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('Bulk DB Fail Restore');
        $products = [];
        $paths = [];

        foreach (['Alpha', 'Beta', 'Gamma'] as $label) {
            $path = 'products/'.$store->id.'/'.strtolower($label).'.jpg';
            Storage::disk('public')->put($path, $label.'-bytes');
            $paths[] = $path;

            $product = $this->makeProduct($store, $label.' Product');
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $path,
                'sort_order' => 0,
                'is_primary' => true,
                'status' => ProductImage::STATUS_READY,
            ]);
            $product->delete();
            $products[] = $product;
        }

        $remainingForceDeletes = 2;
        Product::deleting(function (Product $model) use (&$remainingForceDeletes): void {
            if (! $model->isForceDeleting()) {
                return;
            }

            if (--$remainingForceDeletes === 0) {
                throw new QueryException(
                    'sqlite',
                    'delete from products where id = ?',
                    [$model->id],
                    new \RuntimeException('simulated bulk db failure')
                );
            }
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products', ['view' => 'deleted']))
            ->post(route('products.bulk'), [
                'action' => 'force_delete',
                'product_ids' => collect($products)->pluck('id')->all(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        foreach ($products as $product) {
            $this->assertSoftDeleted('products', ['id' => $product->id]);
            $this->assertDatabaseHas('product_images', ['product_id' => $product->id]);
        }

        $this->assertEmpty(Storage::disk('public')->allFiles('product-delete-quarantine'));
    }

    public function test_product_permanent_delete_reports_success_when_post_commit_quarantine_cleanup_fails(): void
    {
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('Post Commit Cleanup Fail');
        $path = 'products/'.$store->id.'/post-commit.jpg';
        Storage::disk('public')->put($path, 'post-commit-bytes');
        $product = $this->makeProduct($store, 'Post Commit Product');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);
        $productId = $product->id;
        $product->delete();

        $realGallery = app(\App\Services\Catalog\ProductPermanentDeleteGalleryPurgeService::class);
        $sessionHolder = new \stdClass;

        $gallery = \Mockery::mock(\App\Services\Catalog\ProductPermanentDeleteGalleryPurgeService::class);
        $gallery->shouldReceive('retryAllPendingCleanups')->andReturnNull();
        $gallery->shouldReceive('beginQuarantine')->andReturnUsing(function ($products) use ($realGallery, $sessionHolder) {
            $sessionHolder->session = $realGallery->beginQuarantine($products);

            return $sessionHolder->session;
        });
        $gallery->shouldReceive('commitQuarantine')->andReturnUsing(function (\App\Services\Catalog\ProductGalleryQuarantineSession $session): void {
            throw new \App\Exceptions\Catalog\ProductPermanentDeleteCleanupPendingException(
                $session->operationId,
                array_column($session->entries, 'quarantine'),
            );
        });
        $gallery->shouldReceive('retryPendingCleanup')->andReturn(false);
        $this->app->instance(\App\Services\Catalog\ProductPermanentDeleteGalleryPurgeService::class, $gallery);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $productId]))
            ->assertRedirect(route('products', ['view' => 'deleted']))
            ->assertSessionHas('success')
            ->assertSessionHas('success_title', 'Permanently deleted')
            ->assertSessionHas('success_meta')
            ->assertSessionMissing('error');

        $this->assertDatabaseMissing('products', ['id' => $productId]);
        Storage::disk('public')->assertMissing($path);
        $this->assertNotEmpty(
            Storage::disk('public')->allFiles('product-delete-quarantine/'.$sessionHolder->session->operationId)
        );

        $log = SecurityLog::query()
            ->where('store_id', $store->id)
            ->where('event_type', 'product_force_deleted')
            ->latest('id')
            ->firstOrFail();
        $this->assertTrue((bool) ($log->metadata['gallery_cleanup_pending'] ?? false));
        $this->assertSame($sessionHolder->session->operationId, $log->metadata['quarantine_operation_id'] ?? null);
    }

    public function test_store_close_rolls_back_when_audit_log_write_fails(): void
    {
        [$owner, $store] = $this->ownerStore('Audit Fail Close');

        $this->mock(SecurityLogRecorder::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andReturn(null);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('store.destroy', ['storeId' => $store->id]))
            ->assertStatus(500);

        $this->assertNull($store->fresh()->deleted_at);
        $this->assertDatabaseMissing('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'store_closed',
        ]);
    }

    public function test_order_items_product_id_migration_down_refuses_when_nulls_exist(): void
    {
        [$owner, $store] = $this->ownerStore();
        [$product, $variant, $orderItem] = $this->seedCatalogCommerce($store);
        $product->forceDelete();
        $this->assertNull($orderItem->fresh()->product_id);

        $migration = require database_path('migrations/2026_08_25_100000_order_items_product_id_null_on_delete.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot safely roll back order_items.product_id');
        $migration->down();
    }

    public function test_stores_user_id_migration_down_refuses_when_nulls_exist(): void
    {
        [$owner, $store] = $this->ownerStore();
        $store->forceFill(['user_id' => null])->save();

        $migration = require database_path('migrations/2026_08_25_100200_stores_user_id_null_on_delete.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot safely roll back stores.user_id');
        $migration->down();
    }

    public function test_purge_removes_fedex_labels_from_historical_disk_and_trade_docs(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('fedex_legacy');
        config(['carriers.fedex.label_storage_disk' => 'local']);

        [$owner, $store] = $this->ownerStore('FedEx Purge Store');
        [$product, $variant, $orderItem] = $this->seedCatalogCommerce($store);
        $order = $orderItem->order;

        $labelPath = 'fedex/labels/'.$store->id.'/'.$order->id.'/label.pdf';
        Storage::disk('fedex_legacy')->put($labelPath, '%PDF-label');
        $etdPath = 'fedex/etd/'.$store->id.'/ci.pdf';
        Storage::disk('fedex_legacy')->put($etdPath, '%PDF-etd');

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-PURGE-1',
            'status' => Shipment::STATUS_SHIPPED,
            'metadata' => [
                'fedex' => [
                    'labels' => [
                        ['disk' => 'fedex_legacy', 'path' => $labelPath, 'bytes' => 10],
                    ],
                ],
            ],
        ]);

        FedExTradeDocument::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'document_type' => 'COMMERCIAL_INVOICE',
            'status' => FedExTradeDocument::STATUS_UPLOADED,
            'origin_country_code' => 'US',
            'destination_country_code' => 'CA',
            'storage_disk' => 'fedex_legacy',
            'storage_path' => $etdPath,
            'original_filename' => 'ci.pdf',
            'uploaded_at' => now(),
        ]);

        $store->delete();
        app(StorePurgeService::class)->purge(Store::onlyTrashed()->findOrFail($store->id), $owner);

        Storage::disk('fedex_legacy')->assertMissing($labelPath);
        Storage::disk('fedex_legacy')->assertMissing($etdPath);
        $this->assertDatabaseMissing('stores', ['id' => $store->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_purge_aborts_when_file_delete_returns_false_and_file_remains(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        [$owner, $store] = $this->ownerStore('Abort On Delete Fail');
        $path = 'products/'.$store->id.'/stuck.jpg';
        Storage::disk('public')->put($path, 'stuck');
        $product = $this->makeProduct($store, 'Stuck Image');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);

        $store->delete();
        $storeId = (int) $store->id;

        $mock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mock->shouldReceive('exists')->andReturnUsing(function (string $p) use ($path, $storeId): bool {
            if ($p === $path) {
                return true;
            }

            // Directory cleanup sees empty / already gone after file-level abort.
            return $p === 'products/'.$storeId;
        });
        $mock->shouldReceive('delete')->andReturn(false);
        $mock->shouldReceive('deleteDirectory')->andReturn(true);
        Storage::set('public', $mock);

        try {
            app(StorePurgeService::class)->purge(Store::onlyTrashed()->findOrFail($storeId), $owner);
            $this->fail('Expected purge to abort when delete returns false.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Store purge aborted', $e->getMessage());
        }

        $this->assertSoftDeleted('stores', ['id' => $storeId]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_purge_aborts_when_directory_cleanup_fails(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        [$owner, $store] = $this->ownerStore('Abort On Dir Fail');
        $store->delete();
        $storeId = (int) $store->id;
        $dir = 'products/'.$storeId;

        $mock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mock->shouldReceive('exists')->andReturnUsing(fn (string $p): bool => $p === $dir);
        $mock->shouldReceive('delete')->andReturn(true);
        $mock->shouldReceive('deleteDirectory')->andReturn(false);
        Storage::set('public', $mock);

        try {
            app(StorePurgeService::class)->purge(Store::onlyTrashed()->findOrFail($storeId), $owner);
            $this->fail('Expected purge to abort when deleteDirectory returns false.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Store purge aborted', $e->getMessage());
        }

        $this->assertSoftDeleted('stores', ['id' => $storeId]);
    }

    public function test_closed_store_stops_outbound_connected_site_delivery(): void
    {
        config([
            'connected_sites.deliver_in_tests' => false,
            'connected_sites.allow_private_networks_non_production' => true,
        ]);
        Http::fake([
            'http://127.0.0.1:8080/wp-json/eco-portal/v1/events' => Http::response(['ok' => true], 200),
        ]);

        [$owner, $store] = $this->ownerStore('Outbound Close Store');
        $issued = app(ConnectedSiteService::class)->issuePrimaryCredential($store);
        app(ConnectedSiteService::class)->bindWebsiteUrl($store, 'http://127.0.0.1:8080');
        $site = $issued['site']->fresh();

        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Outbound Event Product',
            'slug' => 'outbound-'.Str::random(6),
            'base_price' => 12,
            'sku' => 'OUT-'.Str::random(6),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $delivery = ConnectedSiteEventDelivery::query()
            ->where('connected_site_id', $site->id)
            ->where('status', ConnectedSiteEventDelivery::STATUS_PENDING)
            ->first();
        $this->assertNotNull($delivery);

        $store->delete();

        config(['connected_sites.deliver_in_tests' => true]);
        app(ConnectedSiteCatalogEventDeliveryService::class)->deliver($delivery->fresh());

        Http::assertNothingSent();
        $this->assertSame(ConnectedSiteEventDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->next_retry_at);
        $this->assertStringContainsString('closed', (string) $delivery->fresh()->last_error);

        // retryDue must not resurrect closed-store deliveries into a retry loop.
        ConnectedSiteEventDelivery::query()->whereKey($delivery->id)->update([
            'status' => ConnectedSiteEventDelivery::STATUS_PENDING,
            'next_retry_at' => now()->subMinute(),
            'last_error' => null,
            'attempt_count' => 0,
        ]);

        app(ConnectedSiteCatalogEventDeliveryService::class)->retryDue(10);

        Http::assertNothingSent();
        $delivery->refresh();
        $this->assertSame(ConnectedSiteEventDelivery::STATUS_FAILED, $delivery->status);
        $this->assertNull($delivery->next_retry_at);
    }

    public function test_existing_product_force_delete_image_behavior_still_passes(): void
    {
        Storage::fake('public');
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'With Image');
        $path = 'products/'.$store->id.'/keep-me.jpg';
        Storage::disk('public')->put($path, 'fake-image');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);

        $product->delete();
        Storage::disk('public')->assertExists($path);

        app(\App\Services\Catalog\ProductPermanentDeleteService::class)->forceDelete($product);
        Storage::disk('public')->assertMissing($path);

        $other = $this->makeStore($owner, 'Other Store');
        $other->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);
        $foreign = $this->makeProduct($other, 'Foreign');
        $foreign->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $foreign->id]))
            ->assertNotFound();
        $this->assertSoftDeleted('products', ['id' => $foreign->id]);
    }

    public function test_stock_movement_identity_snapshot_helper_resolves_fields(): void
    {
        [, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Snap Name');
        $variant = $product->variants()->first();
        $variant->update(['sku' => 'SNAP-SKU']);

        $resolved = StockMovementIdentitySnapshot::resolve($product->fresh(), $variant->fresh());

        $this->assertSame('Snap Name', $resolved['product_name_snapshot']);
        $this->assertSame('SNAP-SKU', $resolved['sku_snapshot']);
    }

    public function test_purge_rejects_non_closed_store(): void
    {
        [, $store] = $this->ownerStore();

        $this->expectException(\InvalidArgumentException::class);
        app(StorePurgeService::class)->purge($store);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name = 'Retention Store'): array
    {
        $owner = $this->merchant();
        $store = $this->makeStore($owner, $name);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function merchant(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $user, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function makeProduct(Store $store, string $name): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 5,
            'stock_alert' => 1,
        ]);

        return $product;
    }

    /**
     * @return array{0: Product, 1: ProductVariant, 2: OrderItem, 3: StockMovement, 4: string}
     */
    private function seedCatalogCommerce(Store $store, string $productName = 'Retention Product', ?string $imagePath = null): array
    {
        $product = $this->makeProduct($store, $productName);
        $variant = $product->variants()->firstOrFail();

        $imagePath ??= 'products/'.$store->id.'/'.Str::random(8).'.jpg';
        if (! Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->put($imagePath, 'img');
        }
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $imagePath,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);

        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Retention Buyer',
            'status' => 'active',
        ]);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#RET-'.Str::upper(Str::random(4)),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'currency_code' => 'USD',
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 10,
            'grand_total' => 10,
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);

        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $productName,
            'variant_label' => 'Default',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => 1,
            'unit_price' => 10,
            'subtotal' => 10,
            'total' => 10,
        ]);

        $identity = StockMovementIdentitySnapshot::resolve($product, $variant);
        $movement = StockMovement::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'product_name_snapshot' => $identity['product_name_snapshot'],
            'sku_snapshot' => $identity['sku_snapshot'],
            'variant_label_snapshot' => $identity['variant_label_snapshot'],
            'previous_stock' => 5,
            'quantity_change' => -1,
            'new_stock' => 4,
            'available_before' => 5,
            'available_after' => 4,
            'reserved_before' => 0,
            'reserved_after' => 0,
            'committed_before' => 0,
            'committed_after' => 0,
            'movement_type' => StockMovement::TYPE_ORDER_SALE,
            'reason' => 'test sale',
            'source' => 'test',
        ]);

        return [$product, $variant, $orderItem, $movement, $imagePath];
    }
}
