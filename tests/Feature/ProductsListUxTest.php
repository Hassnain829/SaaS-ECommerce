<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
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

        $product->forceDelete();
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
        $category = \App\Models\Category::query()->create([
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
