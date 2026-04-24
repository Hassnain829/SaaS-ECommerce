<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use App\Models\ProductVariationType;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CatalogDay177CompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_add_posts_to_catalog_and_redirects_to_full_editor(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_open_add_product_modal' => '1',
                'name' => 'Quick Add Tee',
                'description' => null,
                'bulk_price' => 19.99,
                'bulk_stock' => 42,
                'stock_alert' => 2,
                'product_type' => 'physical',
                'sku' => 'QAT-001',
                'inventory_variant_stock_mode' => 'split_total',
            ]);

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Quick Add Tee')->firstOrFail();
        $response->assertRedirect(route('products.edit', ['product' => $product->id]));

        $variant = $product->variants()->firstOrFail();
        $this->assertSame(42, (int) $variant->stock);
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_products_page_collapses_advanced_filters_and_table_settings(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('id="products-advanced-filters-panel"', $html);
        $this->assertStringContainsString('Advanced filters &amp; table settings', $html);
        $this->assertStringContainsString('Product list columns', $html);
        $this->assertStringNotContainsString('>Product list highlights<', $html);
    }

    public function test_workspace_shows_variant_catalog_image_after_save(): void
    {
        Storage::fake('public');
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);
        $product = $this->simpleProduct($store);

        $path = UploadedFile::fake()->create('gal.jpg', 10, 'image/jpeg')->store('products/'.$store->id, 'public');
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => $product->name,
                'description' => 'd',
                'base_price' => 10,
                'sku' => $product->sku,
                'product_type' => 'physical',
                'stock_alert' => 1,
                'existing_image_paths' => [$path],
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['L']],
                ],
                'variants' => [
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'ROW-IMG',
                        'price' => 10,
                        'stock' => 2,
                        'stock_alert' => 1,
                        'product_image_id' => $image->id,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('title="Variant catalog image"', false);
    }

    public function test_promote_import_extra_and_apply_category_are_store_scoped(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Import Meta',
            'slug' => 'import-meta-'.Str::random(6),
            'description' => null,
            'base_price' => 5,
            'sku' => 'IM-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'import_extra' => [
                    'Supplier Notes' => 'Fragile',
                    'Product Category' => 'Seasonal > Sale',
                ],
            ],
        ]);
        $product->variants()->create([
            'sku' => 'IM-1',
            'price' => 5,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.workspace.promote-import-extra', $product), [
                'source_key' => 'Supplier Notes',
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('Fragile', (string) ($product->meta['custom_fields']['supplier_notes'] ?? ''));
        $this->assertArrayHasKey('Supplier Notes', is_array($product->meta['import_extra'] ?? null) ? $product->meta['import_extra'] : []);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.workspace.apply-import-category', $product), [
                'source_key' => 'Product Category',
            ])
            ->assertRedirect();

        $this->assertTrue($product->fresh()->categories()->exists());
    }

    public function test_staff_cannot_promote_import_extra(): void
    {
        $owner = $this->merchantUser('owner-promo@example.com');
        $staff = $this->merchantUser('staff-promo@example.com');
        $store = $this->makeStore($owner);
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Staff Block',
            'slug' => 'staff-block-'.Str::random(6),
            'description' => null,
            'base_price' => 3,
            'sku' => 'SB-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'import_extra' => ['Note' => 'x'],
            ],
        ]);
        $product->variants()->create([
            'sku' => 'SB-1',
            'price' => 3,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.workspace.promote-import-extra', $product), [
                'source_key' => 'Note',
            ])
            ->assertForbidden();
    }

    public function test_bulk_stock_skip_multi_variant_does_not_change_rows(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);
        $product = $this->twoOptionVariantProduct($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'stock',
                'product_ids' => [$product->id],
                'stock_mode' => 'set',
                'stock_value' => 99,
                'bulk_variant_stock_scope' => 'skip_multi_variant',
            ])
            ->assertRedirect();

        $stocks = $product->fresh()->variants()->orderBy('id')->pluck('stock')->map(fn ($s) => (int) $s)->all();
        $this->assertSame([5, 7], $stocks);
    }

    public function test_bulk_stock_all_variants_same_updates_each_row(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);
        $product = $this->twoOptionVariantProduct($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'stock',
                'product_ids' => [$product->id],
                'stock_mode' => 'set',
                'stock_value' => 12,
                'bulk_variant_stock_scope' => 'all_variants_same',
            ])
            ->assertRedirect();

        $stocks = $product->fresh()->variants()->orderBy('id')->pluck('stock')->map(fn ($s) => (int) $s)->all();
        $this->assertSame([12, 12], $stocks);
    }

    public function test_quick_add_create_modal_has_no_variant_form_fields(): void
    {
        $store = $this->makeStore($this->merchantUser());

        $html = View::make('user_view.partials.product_create_modal', [
            'errors' => new ViewErrorBag,
            'productModalSelectedStore' => $store,
            'productModalIsOpen' => false,
            'productModalOpenQuery' => 'openAddProduct',
            'catalogBrands' => collect(),
            'catalogTags' => collect(),
            'catalogTaxonomyCategories' => collect(),
            'catalogImagesForVariantPicker' => [],
        ])->render();

        $this->assertStringContainsString('id="product-create-form"', $html);
        $this->assertStringNotContainsString('name="variants[', $html);
    }

    protected function merchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    protected function makeStore(User $owner, string $name = 'Catalog Store'): Store
    {
        $store = Store::create([
            'user_id' => $owner->id,
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
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    protected function simpleProduct(Store $store): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Simple '.fake()->unique()->numerify('####'),
            'slug' => 'simple-'.fake()->unique()->numerify('####'),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SMP-'.strtoupper(fake()->lexify('????')),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        return $product;
    }

    /**
     * Two sellable rows (Size L / M), each with options attached.
     */
    protected function twoOptionVariantProduct(Store $store): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Multi '.fake()->unique()->numerify('####'),
            'slug' => 'multi-'.Str::random(6),
            'description' => null,
            'base_price' => 10,
            'sku' => 'MULTI-BASE',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $vt = ProductVariationType::query()->create([
            'product_id' => $product->id,
            'name' => 'Size',
            'type' => 'select',
        ]);
        $oL = ProductVariationOption::query()->create([
            'variation_type_id' => $vt->id,
            'value' => 'L',
            'sort_order' => 0,
        ]);
        $oM = ProductVariationOption::query()->create([
            'variation_type_id' => $vt->id,
            'value' => 'M',
            'sort_order' => 1,
        ]);

        $v1 = $product->variants()->create([
            'sku' => 'MULTI-L',
            'price' => 10,
            'stock' => 5,
            'stock_alert' => 0,
        ]);
        $v2 = $product->variants()->create([
            'sku' => 'MULTI-M',
            'price' => 10,
            'stock' => 7,
            'stock_alert' => 0,
        ]);
        $v1->options()->sync([$oL->id]);
        $v2->options()->sync([$oM->id]);

        return $product->fresh();
    }
}
