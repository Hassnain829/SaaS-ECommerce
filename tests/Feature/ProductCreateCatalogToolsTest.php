<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCreateCatalogToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_product_page_includes_catalog_tools_and_empty_organization_fields(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.create'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('id="catalog-hub-brands-rows"', $html);
        $this->assertStringContainsString('id="catalog-hub-tags-rows"', $html);
        $this->assertStringContainsString('data-catalog-hub-rows="brands"', $html);
        $this->assertStringContainsString('data-catalog-hub-rows="tags"', $html);
        $this->assertStringContainsString('data-catalog-hub-rows="categories"', $html);
        $this->assertStringContainsString('data-catalog-tools-ajax="true"', $html);
        $this->assertStringContainsString('data-open-catalog-tools', $html);
        $this->assertStringContainsString('Create a category', $html);
        $this->assertStringContainsString('Create brand', $html);
        $this->assertStringContainsString('Create tag', $html);
        $this->assertStringContainsString('No categories yet', $html);
        $this->assertStringContainsString('id="catalog-edit-section-organization"', $html);
        $this->assertStringContainsString('data-pf-step="organization"', $html);
        $this->assertStringContainsString('data-pf-step="pricing"', $html);
        $this->assertStringContainsString('data-pf-step="media"', $html);
        $this->assertStringContainsString('data-pf-step="tax-shipping"', $html);
        $this->assertStringContainsString('Selling setup, price, and stock', $html);
        $this->assertStringContainsString('Option groups', $html);
        $this->assertStringContainsString('Add option group', $html);
        $this->assertStringContainsString('Single item', $html);
        $this->assertStringContainsString('Multiple variants', $html);
        $this->assertStringContainsString('Default price', $html);
        $this->assertStringContainsString('Use default price', $html);
        $this->assertStringContainsString('Set price for option variants', $html);
        $this->assertStringContainsString('Fill option variants at once', $html);
        $this->assertStringContainsString('This weight applies to every variant', $html);
        $this->assertStringContainsString("mode = 'override'", $html);
        $this->assertStringNotContainsString('show(inputRow, hasOverride)', $html);
        $this->assertStringNotContainsString('Set variant weight', $html);
        $this->assertStringNotContainsString('Apply to all variants', $html);
        $this->assertStringNotContainsString('id="editApplyProductWeightToVariants"', $html);
        $this->assertStringNotContainsString('id="editBulkProductWeight"', $html);
        $this->assertStringNotContainsString('Split evenly', $html);
        $this->assertStringNotContainsString('Split this total', $html);
        $this->assertStringNotContainsString('splitStockAcrossVariants', $html);
        $this->assertStringContainsString('Also sell a standard / base version', $html);
        $this->assertStringContainsString('Variants inherit these defaults unless overridden.', $html);
        $this->assertStringContainsString('This product is not sold separately', $html);
        $this->assertStringContainsString('id="editSellingSetup"', $html);
        $this->assertStringNotContainsString('data-pf-step="variants"', $html);
        $this->assertLessThan(
            strpos($html, 'data-pf-step="media"') ?: PHP_INT_MAX,
            strpos($html, 'data-pf-step="essentials"') ?: PHP_INT_MAX
        );
        $this->assertLessThan(
            strpos($html, 'data-pf-step="pricing"') ?: PHP_INT_MAX,
            strpos($html, 'data-pf-step="media"') ?: PHP_INT_MAX
        );
        $this->assertLessThan(
            strpos($html, 'data-pf-step="organization"') ?: PHP_INT_MAX,
            strpos($html, 'data-pf-step="pricing"') ?: PHP_INT_MAX
        );
        $this->assertLessThan(
            strpos($html, 'data-pf-step="tax-shipping"') ?: PHP_INT_MAX,
            strpos($html, 'data-pf-step="organization"') ?: PHP_INT_MAX
        );
        $this->assertStringContainsString('data-pf-panel', $html);
        $this->assertStringContainsString('class="pf-section"', $html);
        $this->assertStringNotContainsString('min-h-0 flex-1 overflow-y-auto px-6 py-6', $html);
        $this->assertStringContainsString('id="productImageLightbox"', $html);
        $this->assertStringContainsString('Drag to change photo order', $html);
        $this->assertStringContainsString('name="image_order[]"', $html);
        $this->assertStringContainsString('data-product-create-guard', $html);
        $this->assertStringContainsString('data-wizard-kind="create"', $html);
        $this->assertStringContainsString('id="productCreateLeaveModal"', $html);
        $this->assertStringContainsString('data-product-create-save-draft', $html);
        $this->assertSame(1, substr_count($html, 'data-product-create-save-draft'));
        $this->assertStringNotContainsString('class="pf-btn pf-btn-secondary" data-product-create-save-draft', $html);
        $this->assertStringContainsString('name="_save_as_draft"', $html);
        $this->assertStringContainsString('data-turbo="false"', $html);
        $this->assertStringContainsString('Finish this product first', $html);
        $this->assertStringContainsString('Leave without saving', $html);
        $this->assertStringContainsString('Leave without saving keeps this product as a draft', $html);
        $this->assertStringContainsString('Keep adding', $html);
        $appJs = (string) file_get_contents(base_path('resources/js/app.js'));
        $this->assertStringContainsString('[data-product-create-guard]', $appJs);
        $this->assertStringContainsString('isProductEditWizard', $appJs);
        $this->assertStringContainsString('continuePendingProductWizardLeave', $appJs);
        $this->assertStringContainsString('turbo:before-visit', $appJs);
        $this->assertStringContainsString("event.key === 'F5'", $appJs);
        $this->assertStringContainsString('class="pf-req"', $html);
        $this->assertStringContainsString('Assign at least one photo to a variant', $html);
        $this->assertStringContainsString('You can continue without them', $html);
        $this->assertStringContainsString('window.__productCreateGate', $html);
        $this->assertSame(1, substr_count($html, 'pf-flag-optional'));
        $this->assertStringContainsString('data-pf-step="details"', $html);
        $this->assertStringNotContainsString('data-required-flag-for="edit_product_name"', $html);
        $this->assertStringNotContainsString('data-required-flag-for="edit_product_price"', $html);
        $this->assertStringContainsString('ui-modal-shell--alert', $html);
        $this->assertStringContainsString('Unsaved product', $html);
        $this->assertStringContainsString('if(memoryKey!==domKey)return;', $html);
        $this->assertStringContainsString(
            'normalizeRowsAfterVariationChange(); closeVariationEditor(); renderVariationInputs(); renderVariationCards(); renderVariantRows({skipDomSync:true});',
            $html
        );
    }

    public function test_create_saves_standard_base_as_a_sellable_variant(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Sage Hartman Tee',
                'description' => 'Cotton tee',
                'base_price' => 30,
                'sku' => 'SAGE-HARTMAN',
                'product_type' => 'physical',
                'stock_alert' => 5,
                'bulk_stock' => 50,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M']],
                ],
                'variants' => [
                    [
                        'option_map' => [],
                        'sku' => 'SAGE-HARTMAN-STD',
                        'price' => null,
                        'stock' => 10,
                        'stock_alert' => 2,
                    ],
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'SAGE-HARTMAN-S',
                        'price' => 32,
                        'stock' => 20,
                        'stock_alert' => 4,
                    ],
                    [
                        'option_map' => ['0' => 1],
                        'sku' => 'SAGE-HARTMAN-M',
                        'price' => null,
                        'stock' => 20,
                        'stock_alert' => 4,
                    ],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Sage Hartman Tee')->firstOrFail();
        $this->assertFalse(Schema::hasColumn('products', 'stock'));
        $this->assertSame(3, $product->variants()->count());
        $this->assertSame(50, (int) $product->variants()->sum('stock'));

        $standard = $product->variants()->where('sku', 'SAGE-HARTMAN-STD')->firstOrFail();
        $this->assertSame(0, $standard->options()->count());
        $this->assertSame(10, (int) $standard->stock);
        $this->assertNull($standard->priceOverride());
        $this->assertSame('30.00', $standard->price);
        $this->assertSame(2, $product->variants()->whereHas('options')->count());
        $this->assertSame('32.00', $product->variants()->where('sku', 'SAGE-HARTMAN-S')->firstOrFail()->priceOverride());
        $this->assertNull($product->variants()->where('sku', 'SAGE-HARTMAN-M')->firstOrFail()->priceOverride());
    }

    public function test_standard_base_keeps_its_own_stock_when_option_variants_are_empty(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Independent Stock Tee',
                'description' => 'Cotton tee',
                'base_price' => 25,
                'sku' => 'IND-STOCK',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'bulk_stock' => 10,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M']],
                ],
                'variants' => [
                    ['option_map' => [], 'sku' => 'IND-STD', 'price' => null, 'stock' => 10, 'stock_alert' => 1],
                    ['option_map' => ['0' => 0], 'sku' => 'IND-S', 'price' => null, 'stock' => 0, 'stock_alert' => 1],
                    ['option_map' => ['0' => 1], 'sku' => 'IND-M', 'price' => null, 'stock' => 0, 'stock_alert' => 1],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Independent Stock Tee')->firstOrFail();
        $this->assertSame(10, (int) $product->variants()->where('sku', 'IND-STD')->value('stock'));
        $this->assertSame(0, (int) $product->variants()->where('sku', 'IND-S')->value('stock'));
        $this->assertSame(0, (int) $product->variants()->where('sku', 'IND-M')->value('stock'));
        $this->assertSame(10, (int) $product->variants()->sum('stock'));
    }

    public function test_create_does_not_copy_simple_bulk_stock_onto_the_first_option_variant(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Color Block Tee',
                'description' => 'Cotton tee',
                'base_price' => 150,
                'sku' => 'COLOR-BLOCK',
                'product_type' => 'physical',
                'stock_alert' => 20,
                'bulk_stock' => 160,
                'variation_types' => [
                    ['name' => 'color', 'type' => 'select', 'options' => ['r', 'g', 'b']],
                ],
                'variants' => [
                    ['option_map' => ['0' => 0], 'sku' => 'COLOR-R', 'price' => null, 'stock' => 0, 'stock_alert' => 20],
                    ['option_map' => ['0' => 1], 'sku' => 'COLOR-G', 'price' => null, 'stock' => 0, 'stock_alert' => 20],
                    ['option_map' => ['0' => 2], 'sku' => 'COLOR-B', 'price' => null, 'stock' => 0, 'stock_alert' => 20],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Color Block Tee')->firstOrFail();
        $this->assertSame(3, $product->variants()->count());
        $this->assertSame(0, (int) $product->variants()->where('sku', 'COLOR-R')->value('stock'));
        $this->assertSame(0, (int) $product->variants()->where('sku', 'COLOR-G')->value('stock'));
        $this->assertSame(0, (int) $product->variants()->where('sku', 'COLOR-B')->value('stock'));
        $this->assertSame(0, (int) $product->variants()->sum('stock'));
    }

    public function test_create_standard_base_stock_stays_off_option_rows_when_bulk_stock_matches_base(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Base Plus Colors Tee',
                'description' => 'Cotton tee',
                'base_price' => 150,
                'sku' => 'BASE-COLORS',
                'product_type' => 'physical',
                'stock_alert' => 20,
                'bulk_stock' => 160,
                'variation_types' => [
                    ['name' => 'color', 'type' => 'select', 'options' => ['r', 'g', 'b']],
                ],
                'variants' => [
                    ['option_map' => [], 'sku' => 'BASE-STD', 'price' => null, 'stock' => 160, 'stock_alert' => 20],
                    ['option_map' => ['0' => 0], 'sku' => 'BASE-R', 'price' => null, 'stock' => 0, 'stock_alert' => 20],
                    ['option_map' => ['0' => 1], 'sku' => 'BASE-G', 'price' => null, 'stock' => 0, 'stock_alert' => 20],
                    ['option_map' => ['0' => 2], 'sku' => 'BASE-B', 'price' => null, 'stock' => 0, 'stock_alert' => 20],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Base Plus Colors Tee')->firstOrFail();
        $this->assertSame(160, (int) $product->variants()->where('sku', 'BASE-STD')->value('stock'));
        $this->assertSame(0, (int) $product->variants()->where('sku', 'BASE-R')->value('stock'));
        $this->assertSame(0, (int) $product->variants()->where('sku', 'BASE-G')->value('stock'));
        $this->assertSame(0, (int) $product->variants()->where('sku', 'BASE-B')->value('stock'));
        $this->assertSame(160, (int) $product->variants()->sum('stock'));
    }

    public function test_apply_same_each_and_split_total_do_not_change_standard_base_stock(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $payload = [
            '_full_workspace_create' => '1',
            'name' => 'Alloc Guard Tee',
            'description' => 'Cotton tee',
            'base_price' => 20,
            'sku' => 'ALLOC-GUARD',
            'product_type' => 'physical',
            'stock_alert' => 1,
            'bulk_stock' => 10,
            'variation_types' => [
                ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M']],
            ],
            'variants' => [
                ['option_map' => [], 'sku' => 'ALLOC-STD', 'price' => null, 'stock' => 10, 'stock_alert' => 1],
                ['option_map' => ['0' => 0], 'sku' => 'ALLOC-S', 'price' => null, 'stock' => 0, 'stock_alert' => 1],
                ['option_map' => ['0' => 1], 'sku' => 'ALLOC-M', 'price' => null, 'stock' => 0, 'stock_alert' => 1],
            ],
        ];

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $payload + [
                'inventory_stock_allocation_mode' => 'apply_same_each',
                'inventory_apply_same_stock' => 7,
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Alloc Guard Tee')->firstOrFail();
        $this->assertSame(10, (int) $product->variants()->where('sku', 'ALLOC-STD')->value('stock'));
        $this->assertSame(7, (int) $product->variants()->where('sku', 'ALLOC-S')->value('stock'));
        $this->assertSame(7, (int) $product->variants()->where('sku', 'ALLOC-M')->value('stock'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => $product->name,
                'description' => 'Cotton tee',
                'base_price' => 20,
                'sku' => 'ALLOC-GUARD',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'inventory_stock_allocation_mode' => 'split_total',
                'inventory_split_total' => 100,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M']],
                ],
                'variants' => [
                    ['option_map' => [], 'sku' => 'ALLOC-STD', 'price' => null, 'stock' => 10, 'stock_alert' => 1],
                    ['option_map' => ['0' => 0], 'sku' => 'ALLOC-S', 'price' => null, 'stock' => 0, 'stock_alert' => 1],
                    ['option_map' => ['0' => 1], 'sku' => 'ALLOC-M', 'price' => null, 'stock' => 0, 'stock_alert' => 1],
                ],
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(10, (int) $product->variants()->where('sku', 'ALLOC-STD')->value('stock'));
        $this->assertEqualsCanonicalizing(
            [50, 50],
            $product->variants()->whereHas('options')->pluck('stock')->map(fn ($s) => (int) $s)->all()
        );
    }

    public function test_posted_standard_base_price_cannot_override_default_price(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Default Price Guard Tee',
                'description' => 'Cotton tee',
                'base_price' => 30,
                'sku' => 'PRICE-GUARD',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'bulk_stock' => 6,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M']],
                ],
                'variants' => [
                    ['option_map' => [], 'sku' => 'PRICE-STD', 'price' => 99, 'stock' => 2, 'stock_alert' => 1],
                    ['option_map' => ['0' => 0], 'sku' => 'PRICE-S', 'price' => 40, 'stock' => 2, 'stock_alert' => 1],
                    ['option_map' => ['0' => 1], 'sku' => 'PRICE-M', 'price' => 40, 'stock' => 2, 'stock_alert' => 1],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Default Price Guard Tee')->firstOrFail();
        $this->assertEquals(30, (float) $product->base_price);

        $standard = $product->variants()->where('sku', 'PRICE-STD')->firstOrFail();
        $this->assertNull($standard->priceOverride());
        $this->assertSame('30.00', $standard->price);

        $optionPrices = $product->variants()->whereHas('options')->get()->map->priceOverride()->all();
        $this->assertEqualsCanonicalizing(['40.00', '40.00'], $optionPrices);
    }

    public function test_update_can_add_and_remove_standard_base_variant(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Canvas Tote',
                'description' => 'Tote',
                'base_price' => 18,
                'sku' => 'TOTE-PARENT',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'bulk_stock' => 8,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'L']],
                ],
                'variants' => [
                    ['option_map' => ['0' => 0], 'sku' => 'TOTE-S', 'price' => 18, 'stock' => 4, 'stock_alert' => 1],
                    ['option_map' => ['0' => 1], 'sku' => 'TOTE-L', 'price' => 18, 'stock' => 4, 'stock_alert' => 1],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Canvas Tote')->firstOrFail();
        $this->assertSame(2, $product->variants()->count());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => $product->name,
                'description' => 'Tote',
                'base_price' => 18,
                'sku' => 'TOTE-PARENT',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'L']],
                ],
                'variants' => [
                    ['option_map' => [], 'sku' => 'TOTE-STD', 'price' => 18, 'stock' => 6, 'stock_alert' => 1],
                    ['option_map' => ['0' => 0], 'sku' => 'TOTE-S', 'price' => 18, 'stock' => 4, 'stock_alert' => 1],
                    ['option_map' => ['0' => 1], 'sku' => 'TOTE-L', 'price' => 18, 'stock' => 4, 'stock_alert' => 1],
                ],
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(3, $product->variants()->count());
        $this->assertSame(1, $product->variants()->whereDoesntHave('options')->count());
        $this->assertSame(6, (int) $product->variants()->where('sku', 'TOTE-STD')->value('stock'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => $product->name,
                'description' => 'Tote',
                'base_price' => 18,
                'sku' => 'TOTE-PARENT',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'L']],
                ],
                'variants' => [
                    ['option_map' => ['0' => 0], 'sku' => 'TOTE-S', 'price' => 18, 'stock' => 4, 'stock_alert' => 1],
                    ['option_map' => ['0' => 1], 'sku' => 'TOTE-L', 'price' => 18, 'stock' => 4, 'stock_alert' => 1],
                ],
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(0, $product->variants()->whereDoesntHave('options')->count());
        $this->assertNull($product->variants()->where('sku', 'TOTE-STD')->first());
    }

    public function test_category_json_create_returns_assignable_item_for_product_form(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('categories.store'), [
                'name' => 'Apparel',
                'status' => 'active',
                '_open_category_add_modal' => '1',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('kind', 'category')
            ->assertJsonPath('action', 'created')
            ->assertJsonPath('item.name', 'Apparel')
            ->assertJsonPath('item.assignable', true);

        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'name' => 'Apparel',
            'status' => 'active',
        ]);
    }

    public function test_brand_and_tag_json_create_from_add_product_context(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('brands.store'), [
                'name' => 'Northwind',
                'status' => 'active',
                'featured' => '0',
            ])
            ->assertCreated()
            ->assertJsonPath('kind', 'brand')
            ->assertJsonPath('item.name', 'Northwind')
            ->assertJsonPath('item.update_url', route('brands.update', Brand::query()->where('name', 'Northwind')->firstOrFail()))
            ->assertJsonPath('item.destroy_url', route('brands.destroy', Brand::query()->where('name', 'Northwind')->firstOrFail()));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('tags.store'), [
                'name' => 'Featured',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('kind', 'tag')
            ->assertJsonPath('item.name', 'Featured')
            ->assertJsonPath('item.update_url', route('tags.update', Tag::query()->where('name', 'Featured')->firstOrFail()))
            ->assertJsonPath('item.destroy_url', route('tags.destroy', Tag::query()->where('name', 'Featured')->firstOrFail()));
    }

    public function test_html_category_create_from_add_product_returns_to_add_product(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('categories.store'), [
                'name' => 'Home',
                'status' => 'active',
                '_catalog_return' => 'products.create',
            ])
            ->assertRedirect(route('products.create', ['step' => 'organization']));

        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'name' => 'Home',
        ]);
    }

    public function test_html_category_create_from_edit_product_returns_to_edit_product(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Edit Return Product',
            'slug' => 'edit-return-product-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SKU-EDIT-RETURN',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('categories.store'), [
                'name' => 'Kitchen',
                'status' => 'active',
                '_catalog_return' => 'products.edit',
                '_catalog_return_product_id' => (string) $product->id,
            ])
            ->assertRedirect(route('products.edit', ['product' => $product, 'step' => 'organization']));

        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'name' => 'Kitchen',
        ]);
    }

    public function test_html_category_create_without_return_still_lands_on_products_list(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('categories.store'), [
                'name' => 'Garden',
                'status' => 'active',
            ])
            ->assertRedirect(route('products'));
    }

    public function test_inactive_category_is_not_assignable_on_the_product_form(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('categories.store'), [
                'name' => 'Hidden group',
                'status' => 'inactive',
            ])
            ->assertCreated()
            ->assertJsonPath('item.assignable', false);
    }

    public function test_add_product_page_lists_existing_categories_for_assignment(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);
        Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Shoes',
            'slug' => 'shoes',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Stride',
            'slug' => 'stride',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Sale',
            'slug' => 'sale',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Shoes', false)
            ->assertSee('Stride', false)
            ->assertSee('Sale', false)
            ->assertSee('id="edit_product_category_ids"', false);
    }

    public function test_save_as_draft_creates_unpublished_product_without_price(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                '_save_as_draft' => '1',
                'name' => 'Half-finished lamp',
                'product_type' => 'physical',
                'stock_alert' => 0,
            ])
            ->assertRedirect(route('products', ['view' => 'drafts']));

        $product = Product::query()
            ->where('store_id', $store->id)
            ->where('name', 'Half-finished lamp')
            ->firstOrFail();

        $this->assertFalse($product->status);
        $this->assertSame(0.0, (float) $product->base_price);
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_save_as_draft_skips_empty_forms(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                '_save_as_draft' => '1',
                'product_type' => 'physical',
                'stock_alert' => 0,
                'bulk_stock' => 0,
            ])
            ->assertRedirect(route('products', ['view' => 'drafts']));

        $this->assertSame(0, Product::query()->where('store_id', $store->id)->count());
    }

    public function test_save_as_draft_uses_untitled_name_and_blocks_external_leave_urls(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                '_save_as_draft' => '1',
                '_draft_leave_to' => 'https://evil.example/phish',
                'name' => '  ',
                'sku' => 'DRAFT-SKU-1',
                'product_type' => 'physical',
                'stock_alert' => 0,
            ])
            ->assertRedirect(route('products', ['view' => 'drafts']));

        $product = Product::query()
            ->where('store_id', $store->id)
            ->where('sku', 'DRAFT-SKU-1')
            ->firstOrFail();

        $this->assertSame('Untitled product', $product->name);
        $this->assertFalse($product->status);
    }

    public function test_save_as_draft_honors_same_origin_leave_path(): void
    {
        $owner = $this->merchantUser();
        $store = $this->makeStore($owner);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                '_save_as_draft' => '1',
                '_draft_leave_to' => '/orders',
                'name' => 'Keep going later',
                'product_type' => 'physical',
                'stock_alert' => 0,
            ])
            ->assertRedirect('/orders');

        $this->assertTrue(
            Product::query()
                ->where('store_id', $store->id)
                ->where('name', 'Keep going later')
                ->where('status', false)
                ->exists()
        );
    }

    private function merchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $owner, string $name = 'Create Catalog Store'): Store
    {
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'create-catalog-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }
}
