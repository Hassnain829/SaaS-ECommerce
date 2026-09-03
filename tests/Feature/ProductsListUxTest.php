<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Checkout;
use App\Models\CheckoutItem;
use App\Models\ConnectedSiteOutboxEvent;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\Catalog\ProductPermanentDeleteService;
use App\Support\ConnectedSiteCatalogEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductsListUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_row_delete_soft_deletes_and_hides_from_active_list(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Delete Me Softly');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.destroy', ['productId' => $product->id]))
            ->assertRedirect(route('products'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $list = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk();

        $listedIds = collect($list->viewData('products')->items())->pluck('id')->all();
        $this->assertNotContains($product->id, $listedIds);
    }

    public function test_deleted_view_lists_soft_deleted_products_for_store(): void
    {
        [$owner, $store] = $this->ownerStore();
        $active = $this->makeProduct($store, 'Still Active');
        $deleted = $this->makeProduct($store, 'In Trash');
        $deleted->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['view' => 'deleted']))
            ->assertOk()
            ->assertSeeText('In Trash')
            ->assertDontSeeText('Still Active')
            ->assertSeeText('Undo delete')
            ->assertSeeText('Permanently delete')
            ->assertDontSeeText('Archived');
    }

    public function test_restore_returns_product_to_active_catalog(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Bring Back');
        $product->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.restore', ['productId' => $product->id]))
            ->assertRedirect(route('products'));

        $this->assertNull($product->fresh()->deleted_at);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->assertSeeText('Bring Back');
    }

    public function test_force_delete_removes_product_permanently(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Gone Forever');
        $productId = $product->id;
        $product->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $productId]))
            ->assertRedirect(route('products', ['view' => 'deleted']));

        $this->assertDatabaseMissing('products', ['id' => $productId]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.restore', ['productId' => $productId]))
            ->assertNotFound();
    }

    public function test_soft_delete_keeps_gallery_files_until_force_delete(): void
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

        app(ProductPermanentDeleteService::class)->forceDelete($product);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_cross_store_restore_and_force_delete_are_404(): void
    {
        [$owner, $storeA] = $this->ownerStore('Store A');
        $storeB = $this->makeStore($owner, 'Store B');
        $product = $this->makeProduct($storeB, 'Beta Deleted');
        $product->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('product.restore', ['productId' => $product->id]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->delete(route('product.force-destroy', ['productId' => $product->id]))
            ->assertNotFound();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_unified_filter_panel_includes_search_sort_and_hides_header_search(): void
    {
        [$owner, $store] = $this->ownerStore();
        $this->makeProduct($store, 'Listed');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('id="products-filters-panel"', $html);
        $this->assertStringContainsString('id="products-filter-q"', $html);
        $this->assertStringContainsString('Search &amp; filters', $html);
        $this->assertStringContainsString('Price high → low', $html);
        $this->assertStringContainsString('id="filter-category"', $html);
        $this->assertStringContainsString('data-filter-picker', $html);
        $this->assertStringContainsString('data-picker-search', $html);
        $this->assertStringContainsString('Any category', $html);
        $this->assertStringNotContainsString('data-filter-chip-group', $html);
        $this->assertStringNotContainsString('<select id="filter-category"', $html);
        $this->assertStringNotContainsString('<select id="filter-brand"', $html);
        $this->assertStringContainsString('id="bulk-catalog-toolbar"', $html);
        $this->assertMatchesRegularExpression('/id="bulk-catalog-toolbar"[^>]*class="[^"]*\bhidden\b/', $html);
        $this->assertStringContainsString('js-bulk-action-chip', $html);
        $this->assertStringContainsString('What do you want to do?', $html);
        $this->assertStringContainsString('Update stock', $html);
        $this->assertStringContainsString('id="bulk-clear-selection"', $html);
        $this->assertStringContainsString('id="bulk-options-panel"', $html);
        $this->assertStringContainsString('>Delete</option>', $html);
        $this->assertStringNotContainsString('Move to Archived', $html);
        // Search lives in the filter panel, not the topbar slot.
        $this->assertStringNotContainsString('merchant-topbar"><form method="GET"', $html);
        $topbarPos = strpos($html, 'merchant-topbar');
        $filterQPos = strpos($html, 'id="products-filter-q"');
        $this->assertNotFalse($topbarPos);
        $this->assertNotFalse($filterQPos);
        $this->assertTrue($filterQPos > $topbarPos);
    }

    public function test_filter_apply_updates_listing(): void
    {
        [$owner, $store] = $this->ownerStore();
        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Acme',
            'slug' => 'acme-'.Str::random(4),
            'sort_order' => 1,
        ]);
        $match = $this->makeProduct($store, 'Acme Shirt');
        $match->update(['brand_id' => $brand->id]);
        $this->makeProduct($store, 'Other Shirt');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['brand' => $brand->id, 'sort' => 'price_low']))
            ->assertOk()
            ->assertSeeText('Acme Shirt')
            ->assertDontSeeText('Other Shirt');
    }

    public function test_category_filter_shows_product_counts(): void
    {
        [$owner, $store] = $this->ownerStore();
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Counted Cats',
            'slug' => 'counted-cats-'.Str::random(4),
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $first = $this->makeProduct($store, 'Cat Product One');
        $second = $this->makeProduct($store, 'Cat Product Two');
        $first->categories()->attach($category->id);
        $second->categories()->attach($category->id);
        $this->makeProduct($store, 'Uncategorized Product');

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('Counted Cats', $html);
        $this->assertStringContainsString('data-label="Counted Cats (2)"', $html);
        $this->assertMatchesRegularExpression(
            '/data-value="'.$category->id.'"[^>]*>[\s\S]*?Counted Cats[\s\S]*?>2</',
            $html
        );
    }

    public function test_pagination_per_page_and_page_links_respect_query_string(): void
    {
        [$owner, $store] = $this->ownerStore();
        for ($i = 1; $i <= 30; $i++) {
            $this->makeProduct($store, 'Paged '.$i);
        }

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['per_page' => 10, 'q' => 'Paged']));

        $response->assertOk();
        $html = $response->getContent() ?: '';
        $this->assertStringContainsString('Showing', $html);
        $this->assertStringContainsString('First', $html);
        $this->assertStringContainsString('Last', $html);
        $this->assertStringContainsString('per_page=10', $html);
        $this->assertStringContainsString('q=Paged', $html);
        $this->assertSame(10, $response->viewData('products')->perPage());
    }

    public function test_force_delete_with_linked_variant_and_image_succeeds(): void
    {
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Linked Gallery Delete');
        $variant = $product->variants()->firstOrFail();
        $path = 'products/'.$store->id.'/linked-delete.jpg';
        Storage::disk('public')->put($path, 'bytes');

        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);
        $variant->update(['product_image_id' => $image->id]);

        $productId = $product->id;
        $product->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $productId]))
            ->assertRedirect(route('products', ['view' => 'deleted']));

        $this->assertDatabaseMissing('products', ['id' => $productId]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_bulk_restore_and_force_delete_on_deleted_products(): void
    {
        [$owner, $store] = $this->ownerStore();
        $restoreTarget = $this->makeProduct($store, 'Bulk Restore');
        $forceTarget = $this->makeProduct($store, 'Bulk Force');
        $restoreTarget->delete();
        $forceTarget->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'restore',
                'product_ids' => [$restoreTarget->id],
            ])
            ->assertRedirect(route('products'));

        $this->assertNull($restoreTarget->fresh()->deleted_at);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products', ['view' => 'deleted']))
            ->post(route('products.bulk'), [
                'action' => 'force_delete',
                'product_ids' => [$forceTarget->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $forceTarget->id]);
    }

    public function test_select_all_matching_uses_full_filtered_product_count(): void
    {
        [$owner, $store] = $this->ownerStore();
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = $this->makeProduct($store, 'Match All '.$i)->id;
        }

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->assertSee('Select all matching (6)', false)
            ->assertDontSee('up to 500', false);

        $bulkIds = $response->viewData('bulkSelectableProductIds');
        $this->assertSame(6, (int) $response->viewData('bulkMatchingCount'));
        $this->assertCount(6, $bulkIds);
        foreach ($ids as $id) {
            $this->assertContains($id, $bulkIds);
        }
    }

    public function test_permanent_delete_keeps_import_session_while_sibling_products_remain(): void
    {
        Storage::fake('local');

        [$owner, $store] = $this->ownerStore();
        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'batch.csv',
            'stored_disk' => 'local',
            'stored_path' => 'product-imports/'.$store->id.'/batch.csv',
            'file_extension' => 'csv',
            'source_site' => 'https://import.example.test',
            'status' => ProductImport::STATUS_COMPLETED,
        ]);
        Storage::disk('local')->put($import->stored_path, 'csv');
        ProductImportRow::query()->create([
            'product_import_id' => $import->id,
            'row_number' => 1,
            'status' => 'processed',
            'payload' => ['cells' => ['A']],
        ]);

        $keep = $this->makeImportedProduct($store, $import, 'Keep Import Product');
        $remove = $this->makeImportedProduct($store, $import, 'Remove Import Product');
        $remove->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $remove->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']));

        $this->assertDatabaseHas('product_imports', ['id' => $import->id]);
        $this->assertDatabaseHas('product_import_rows', ['product_import_id' => $import->id]);
        Storage::disk('local')->assertExists($import->stored_path);
        $this->assertDatabaseHas('products', ['id' => $keep->id]);
    }

    public function test_permanent_delete_preserves_import_session_when_last_product_removed(): void
    {
        Storage::fake('local');

        [$owner, $store] = $this->ownerStore();
        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'solo.csv',
            'stored_disk' => 'local',
            'stored_path' => 'product-imports/'.$store->id.'/solo.csv',
            'file_extension' => 'csv',
            'source_site' => 'https://solo-import.example.test',
            'status' => ProductImport::STATUS_COMPLETED,
        ]);
        Storage::disk('local')->put($import->stored_path, 'csv');
        ProductImportRow::query()->create([
            'product_import_id' => $import->id,
            'row_number' => 1,
            'status' => 'processed',
            'payload' => ['cells' => ['Only']],
        ]);

        $product = $this->makeImportedProduct($store, $import, 'Only Import Product');
        $productId = $product->id;
        $product->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $productId]))
            ->assertRedirect(route('products', ['view' => 'deleted']));

        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertDatabaseHas('product_imports', ['id' => $import->id]);
        $this->assertDatabaseHas('product_import_rows', ['product_import_id' => $import->id]);
        Storage::disk('local')->assertExists($import->stored_path);
    }

    public function test_bulk_permanent_delete_preserves_import_session(): void
    {
        Storage::fake('local');

        [$owner, $store] = $this->ownerStore();
        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'bulk.csv',
            'stored_disk' => 'local',
            'stored_path' => 'product-imports/'.$store->id.'/bulk.csv',
            'file_extension' => 'csv',
            'source_site' => 'https://bulk-import.example.test',
            'status' => ProductImport::STATUS_COMPLETED,
        ]);
        Storage::disk('local')->put($import->stored_path, 'csv');
        ProductImportRow::query()->create([
            'product_import_id' => $import->id,
            'row_number' => 1,
            'status' => 'processed',
            'payload' => ['cells' => ['Bulk']],
        ]);

        $first = $this->makeImportedProduct($store, $import, 'Bulk Import A');
        $second = $this->makeImportedProduct($store, $import, 'Bulk Import B');
        $first->delete();
        $second->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products', ['view' => 'deleted']))
            ->post(route('products.bulk'), [
                'action' => 'force_delete',
                'product_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_imports', ['id' => $import->id]);
        $this->assertDatabaseHas('product_import_rows', ['product_import_id' => $import->id]);
        Storage::disk('local')->assertExists($import->stored_path);
    }

    public function test_permanent_delete_preserves_connected_site_outbox_events(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Outbox Retained');
        $variant = $product->variants()->firstOrFail();
        $product->delete();

        $event = ConnectedSiteOutboxEvent::query()->create([
            'store_id' => $store->id,
            'public_id' => 'csevt_test_'.Str::lower(Str::random(16)),
            'type' => ConnectedSiteCatalogEvent::PRODUCT_DELETED,
            'payload' => [
                'store_id' => $store->id,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
            ],
            'catalog_version' => 1,
            'occurred_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $product->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']));

        $this->assertDatabaseHas('connected_site_outbox_events', ['id' => $event->id]);
    }

    public function test_permanent_delete_preserves_draft_order_items_via_set_null(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Draft Line Retained');
        $variant = $product->variants()->firstOrFail();
        $product->delete();

        $draft = DraftOrder::query()->create([
            'store_id' => $store->id,
            'draft_number' => 'DRF-'.Str::upper(Str::random(6)),
            'status' => DraftOrder::STATUS_DRAFT,
            'currency' => 'USD',
            'subtotal' => 10,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'total' => 10,
            'created_by' => $owner->id,
        ]);
        $line = DraftOrderItem::query()->create([
            'store_id' => $store->id,
            'draft_order_id' => $draft->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 10,
            'line_total' => 10,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $product->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']));

        $fresh = DraftOrderItem::query()->findOrFail($line->id);
        $this->assertNull($fresh->product_id);
        $this->assertNull($fresh->product_variant_id);
        $this->assertSame('Draft Line Retained', $fresh->product_name);
    }

    public function test_permanent_delete_preserves_stock_movements_via_set_null(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Ledger Retained');
        $variant = $product->variants()->firstOrFail();
        $movement = StockMovement::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'movement_type' => 'adjustment',
            'quantity_change' => 5,
            'new_stock' => 5,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $variant->sku,
        ]);
        $product->delete();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $product->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']));

        $fresh = StockMovement::query()->findOrFail($movement->id);
        $this->assertNull($fresh->product_id);
        $this->assertNull($fresh->variant_id);
        $this->assertSame('Ledger Retained', $fresh->product_name_snapshot);
        $this->assertSame($variant->sku, $fresh->sku_snapshot);
    }

    public function test_permanent_delete_blocked_for_payment_pending_checkout(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Checkout Blocked');
        $variant = $product->variants()->firstOrFail();
        $product->delete();

        $checkout = Checkout::query()->create([
            'store_id' => $store->id,
            'checkout_number' => 'CHK-'.Str::random(8),
            'status' => Checkout::STATUS_PAYMENT_PENDING,
            'currency_code' => 'USD',
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10,
        ]);
        CheckoutItem::query()->create([
            'checkout_id' => $checkout->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 10,
            'subtotal' => 10,
            'total' => 10,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $product->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_permanent_delete_blocked_for_paid_unconverted_checkout(): void
    {
        [$owner, $store] = $this->ownerStore();
        $product = $this->makeProduct($store, 'Paid Checkout Blocked');
        $variant = $product->variants()->firstOrFail();
        $product->delete();

        $checkout = Checkout::query()->create([
            'store_id' => $store->id,
            'checkout_number' => 'CHK-'.Str::random(8),
            'status' => Checkout::STATUS_PAID,
            'currency_code' => 'USD',
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10,
        ]);
        CheckoutItem::query()->create([
            'checkout_id' => $checkout->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 10,
            'subtotal' => 10,
            'total' => 10,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.force-destroy', ['productId' => $product->id]))
            ->assertRedirect(route('products', ['view' => 'deleted']))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    private function makeImportedProduct(Store $store, ProductImport $import, string $name): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'base_price' => 35,
            'sku' => 'IMP-'.strtoupper(Str::random(6)),
            'source_system' => 'woocommerce',
            'source_site' => $import->source_site,
            'source_product_id' => (string) fake()->unique()->numberBetween(1000, 9999),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = $product->variants()->create([
            'store_id' => $store->id,
            'sku' => $product->sku,
            'price' => 35,
            'stock' => 20,
            'stock_alert' => 1,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'product_import_id' => $import->id,
            'image_path' => 'products/'.$store->id.'/import-'.Str::random(8).'.png',
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);

        StockMovement::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'movement_type' => 'import',
            'quantity_change' => 20,
            'new_stock' => 20,
            'reference_type' => 'product_import',
            'reference_id' => $import->id,
        ]);

        return $product;
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name = 'List UX Store'): array
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner, $name);

        return [$owner, $store];
    }

    private function makeUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $user, string $name = 'Test Store'): Store
    {
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    private function makeProduct(Store $store, string $name): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
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
}
