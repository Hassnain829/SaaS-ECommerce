<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariationType;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\ProductEditPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VariantSystemUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_product_workspace_still_renders_default_variant(): void
    {
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Var Simple Store');
        $product = $this->createSimpleProduct($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Default inventory', false)
            ->assertSee('Default variant', false);
    }

    public function test_two_option_groups_without_custom_rows_generate_cartesian_variants(): void
    {
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Var Grid Store');
        $product = $this->createSimpleProduct($store);

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
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M']],
                    ['name' => 'Color', 'type' => 'select', 'options' => ['Red', 'Blue']],
                ],
                'variants' => [],
            ])
            ->assertRedirect(route('products'));

        $product->refresh();
        $this->assertSame(4, $product->variants()->count());
    }

    public function test_orphan_option_group_without_values_does_not_block_simple_product_edit(): void
    {
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Orphan Opt Store');
        $product = $this->createSimpleProduct($store);

        ProductVariationType::query()->create([
            'product_id' => $product->id,
            'name' => 'Size',
            'type' => 'select',
        ]);

        $payload = ProductEditPayload::forProduct($product->fresh());
        $this->assertSame([], $payload['variation_types']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => $product->name,
                'description' => 'updated description',
                'base_price' => 29.99,
                'sku' => $product->sku,
                'product_type' => 'physical',
                'stock_alert' => 10,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select'],
                ],
                'variants' => [
                    [
                        'id' => $product->variants()->first()->id,
                        'sku' => $product->sku,
                        'price' => 29.99,
                        'stock' => 5,
                        'stock_alert' => 10,
                    ],
                ],
            ])
            ->assertRedirect(route('products'))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(0, $product->variationTypes()->count());
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame('updated description', $product->description);
    }

    public function test_duplicate_option_values_in_one_group_are_rejected(): void
    {
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Dup Opt Store');
        $product = $this->createSimpleProduct($store);

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
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 's']],
                ],
                'variants' => [],
            ])
            ->assertSessionHasErrors('variation_types.0.options');
    }

    public function test_duplicate_variant_combinations_are_rejected(): void
    {
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Dup Var Store');
        $product = $this->createSimpleProduct($store);

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
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M']],
                ],
                'variants' => [
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'A',
                        'price' => 10,
                        'stock' => 1,
                        'stock_alert' => 1,
                    ],
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'B',
                        'price' => 11,
                        'stock' => 2,
                        'stock_alert' => 1,
                    ],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_duplicate_variant_skus_across_rows_return_validation(): void
    {
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Dup Sku Store');
        $product = $this->createSimpleProduct($store);

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
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M']],
                ],
                'variants' => [
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'SHARED-SKU-ROW',
                        'price' => 10,
                        'stock' => 1,
                        'stock_alert' => 1,
                    ],
                    [
                        'option_map' => ['0' => 1],
                        'sku' => 'SHARED-SKU-ROW',
                        'price' => 11,
                        'stock' => 2,
                        'stock_alert' => 1,
                    ],
                ],
            ])
            ->assertSessionHasErrors('variants.1.sku');
    }

    public function test_staff_cannot_update_product_variants(): void
    {
        $owner = $this->createMerchantUser('owner-vsu@example.com');
        $staff = $this->createMerchantUser('staff-vsu@example.com');
        $store = $this->createMemberStore($owner, 'Staff Var Store');
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);
        $product = $this->createSimpleProduct($store);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => 'Hacked',
                'description' => 'd',
                'base_price' => 10,
                'sku' => $product->sku,
                'product_type' => 'physical',
                'stock_alert' => 1,
            ])
            ->assertForbidden();
    }

    public function test_variant_compare_at_and_label_surface_on_workspace(): void
    {
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Label Store');
        $product = $this->createSimpleProduct($store);

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
                'stock_alert' => 2,
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['M']],
                ],
                'variants' => [
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'LAB-1',
                        'price' => 10,
                        'compare_at_price' => 15,
                        'stock' => 3,
                        'stock_alert' => 2,
                    ],
                ],
            ])
            ->assertRedirect(route('products'));

        $product->refresh();
        $variant = $product->variants()->firstOrFail();
        $this->assertSame('15.00', (string) $variant->compare_at_price);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Size: M', false)
            ->assertSee('15.00', false);
    }

    public function test_variant_catalog_image_link_is_persisted(): void
    {
        Storage::fake('public');
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Img Var Store');
        $product = $this->createSimpleProduct($store);

        $path = UploadedFile::fake()->create('v.jpg', 10, 'image/jpeg')->store('products/'.$store->id, 'public');
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
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
                        'sku' => 'IMG-1',
                        'price' => 10,
                        'stock' => 1,
                        'stock_alert' => 1,
                        'product_image_id' => $image->id,
                    ],
                ],
            ])
            ->assertRedirect(route('products'));

        $image->refresh();
        $variant = ProductVariant::query()->where('sku', 'IMG-1')->firstOrFail();
        $this->assertSame((int) $image->id, (int) $variant->product_image_id);
        $this->assertSame((int) $image->id, (int) $variant->linkedCatalogImage?->id);
    }

    public function test_same_catalog_image_can_be_shared_across_multiple_variants(): void
    {
        Storage::fake('public');
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Shared Img Store');
        $product = $this->createSimpleProduct($store);

        $path = UploadedFile::fake()->create('shared.jpg', 10, 'image/jpeg')->store('products/'.$store->id, 'public');
        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
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
                    ['name' => 'Color', 'type' => 'select', 'options' => ['red', 'green', 'blue']],
                    ['name' => 'Size', 'type' => 'select', 'options' => ['large', 'medium', 'small']],
                ],
                'variants' => [
                    ['option_map' => ['0' => 0, '1' => 0], 'sku' => 'SH-RL', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                    ['option_map' => ['0' => 0, '1' => 1], 'sku' => 'SH-RM', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                    ['option_map' => ['0' => 0, '1' => 2], 'sku' => 'SH-RS', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                    ['option_map' => ['0' => 1, '1' => 0], 'sku' => 'SH-GL', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                    ['option_map' => ['0' => 1, '1' => 1], 'sku' => 'SH-GM', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                    ['option_map' => ['0' => 1, '1' => 2], 'sku' => 'SH-GS', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                    ['option_map' => ['0' => 2, '1' => 0], 'sku' => 'SH-BL', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                    ['option_map' => ['0' => 2, '1' => 1], 'sku' => 'SH-BM', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                    ['option_map' => ['0' => 2, '1' => 2], 'sku' => 'SH-BS', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $image->id],
                ],
            ])
            ->assertRedirect(route('products'));

        $linkedCount = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('product_image_id', $image->id)
            ->count();

        $this->assertSame(9, $linkedCount);
    }

    public function test_distinct_catalog_images_persist_on_multiple_variants_across_updates(): void
    {
        Storage::fake('public');
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Distinct Img Store');
        $product = $this->createSimpleProduct($store);

        $paths = [];
        $images = [];
        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $i => $name) {
            $path = UploadedFile::fake()->create($name, 10, 'image/jpeg')->store('products/'.$store->id, 'public');
            $paths[] = $path;
            $images[] = ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $path,
                'sort_order' => $i,
                'is_primary' => $i === 0,
            ]);
        }

        $basePayload = [
            '_open_edit_product_modal' => '1',
            '_edit_product_id' => (string) $product->id,
            'name' => $product->name,
            'description' => 'd',
            'base_price' => 10,
            'sku' => $product->sku,
            'product_type' => 'physical',
            'stock_alert' => 1,
            'existing_image_paths' => $paths,
            'variation_types' => [
                ['name' => 'Color', 'type' => 'select', 'options' => ['red', 'blue']],
                ['name' => 'Size', 'type' => 'select', 'options' => ['L', 'M']],
            ],
        ];

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $basePayload + [
                'variants' => [
                    ['option_map' => ['0' => 0, '1' => 0], 'sku' => 'DI-RL', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $images[0]->id],
                    ['option_map' => ['0' => 0, '1' => 1], 'sku' => 'DI-RM', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $images[1]->id],
                    ['option_map' => ['0' => 1, '1' => 0], 'sku' => 'DI-BL', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $images[2]->id],
                    ['option_map' => ['0' => 1, '1' => 1], 'sku' => 'DI-BM', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => ''],
                ],
            ])
            ->assertRedirect(route('products'));

        $this->assertSame((int) $images[0]->id, (int) ProductVariant::query()->where('sku', 'DI-RL')->value('product_image_id'));
        $this->assertSame((int) $images[1]->id, (int) ProductVariant::query()->where('sku', 'DI-RM')->value('product_image_id'));
        $this->assertSame((int) $images[2]->id, (int) ProductVariant::query()->where('sku', 'DI-BL')->value('product_image_id'));
        $this->assertNull(ProductVariant::query()->where('sku', 'DI-BM')->value('product_image_id'));

        $payload = ProductEditPayload::forProduct($product->fresh());
        $bySku = collect($payload['variants'])->keyBy('sku');
        $this->assertSame((int) $images[0]->id, (int) $bySku['DI-RL']['product_image_id']);
        $this->assertSame((int) $images[1]->id, (int) $bySku['DI-RM']['product_image_id']);
        $this->assertSame((int) $images[2]->id, (int) $bySku['DI-BL']['product_image_id']);
        $this->assertNull($bySku['DI-BM']['product_image_id']);

        // Second save only changes one row's photo — others must remain.
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $basePayload + [
                'variants' => [
                    ['option_map' => ['0' => 0, '1' => 0], 'sku' => 'DI-RL', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $images[2]->id],
                    ['option_map' => ['0' => 0, '1' => 1], 'sku' => 'DI-RM', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $images[1]->id],
                    ['option_map' => ['0' => 1, '1' => 0], 'sku' => 'DI-BL', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $images[2]->id],
                    ['option_map' => ['0' => 1, '1' => 1], 'sku' => 'DI-BM', 'price' => 10, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => $images[0]->id],
                ],
            ])
            ->assertRedirect(route('products'));

        $this->assertSame((int) $images[2]->id, (int) ProductVariant::query()->where('sku', 'DI-RL')->value('product_image_id'));
        $this->assertSame((int) $images[1]->id, (int) ProductVariant::query()->where('sku', 'DI-RM')->value('product_image_id'));
        $this->assertSame((int) $images[2]->id, (int) ProductVariant::query()->where('sku', 'DI-BL')->value('product_image_id'));
        $this->assertSame((int) $images[0]->id, (int) ProductVariant::query()->where('sku', 'DI-BM')->value('product_image_id'));
    }

    public function test_create_product_can_assign_newly_uploaded_image_to_variants(): void
    {
        Storage::fake('public');
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Create Img Store');
        $imageA = UploadedFile::fake()->create('create-a.jpg', 12, 'image/jpeg');
        $imageB = UploadedFile::fake()->create('create-b.jpg', 12, 'image/jpeg');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Create Photo Tee',
                'description' => 'd',
                'base_price' => 15,
                'bulk_price' => 15,
                'bulk_stock' => 4,
                'sku' => 'CPT-001',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'inventory_variant_stock_mode' => 'split_total',
                'product_images' => [$imageA, $imageB],
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['L', 'M']],
                ],
                'variants' => [
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'CPT-L',
                        'price' => 15,
                        'stock' => 2,
                        'stock_alert' => 1,
                        'product_image_id' => 'new:0',
                    ],
                    [
                        'option_map' => ['0' => 1],
                        'sku' => 'CPT-M',
                        'price' => 15,
                        'stock' => 2,
                        'stock_alert' => 1,
                        'product_image_id' => 'new:1',
                    ],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Create Photo Tee')->firstOrFail();
        $this->assertSame(2, $product->images()->count());

        $large = ProductVariant::query()->where('sku', 'CPT-L')->firstOrFail();
        $medium = ProductVariant::query()->where('sku', 'CPT-M')->firstOrFail();
        $firstImage = $product->images()->orderBy('sort_order')->orderBy('id')->firstOrFail();
        $secondImage = $product->images()->orderBy('sort_order')->orderBy('id')->skip(1)->firstOrFail();

        $this->assertSame((int) $firstImage->id, (int) $large->product_image_id);
        $this->assertSame((int) $secondImage->id, (int) $medium->product_image_id);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('No image', false);
    }

    public function test_create_product_can_share_one_uploaded_image_across_variants(): void
    {
        Storage::fake('public');
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Create Share Img Store');
        $image = UploadedFile::fake()->create('shared-create.jpg', 12, 'image/jpeg');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Shared Create Tee',
                'description' => 'd',
                'base_price' => 12,
                'bulk_price' => 12,
                'bulk_stock' => 3,
                'sku' => 'SCT-001',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'inventory_variant_stock_mode' => 'split_total',
                'product_images' => [$image],
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['S', 'M', 'L']],
                ],
                'variants' => [
                    ['option_map' => ['0' => 0], 'sku' => 'SCT-S', 'price' => 12, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => 'new:0'],
                    ['option_map' => ['0' => 1], 'sku' => 'SCT-M', 'price' => 12, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => 'new:0'],
                    ['option_map' => ['0' => 2], 'sku' => 'SCT-L', 'price' => 12, 'stock' => 1, 'stock_alert' => 1, 'product_image_id' => 'new:0'],
                ],
            ])
            ->assertRedirect(route('products.show', [
                'product' => Product::query()->where('store_id', $store->id)->where('sku', 'SCT-001')->value('id'),
            ]));

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'SCT-001')->firstOrFail();
        $imageId = (int) $product->images()->value('id');
        $this->assertSame(3, ProductVariant::query()->where('product_id', $product->id)->where('product_image_id', $imageId)->count());
    }

    public function test_newly_uploaded_image_can_be_assigned_to_one_variant_before_save(): void
    {
        Storage::fake('public');
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Pending Img Var Store');
        $product = $this->createSimpleProduct($store);
        $newImage = UploadedFile::fake()->create('new-variant.jpg', 12, 'image/jpeg');

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
                'product_images' => [$newImage],
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['L', 'M']],
                ],
                'variants' => [
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'IMG-NEW-L',
                        'price' => 10,
                        'stock' => 1,
                        'stock_alert' => 1,
                        'product_image_id' => 'new:0',
                    ],
                    [
                        'option_map' => ['0' => 1],
                        'sku' => 'IMG-NEW-M',
                        'price' => 10,
                        'stock' => 1,
                        'stock_alert' => 1,
                        'product_image_id' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('products'));

        $assignedVariant = ProductVariant::query()->where('sku', 'IMG-NEW-L')->firstOrFail();
        $blankVariant = ProductVariant::query()->where('sku', 'IMG-NEW-M')->firstOrFail();
        $uploadedImage = ProductImage::query()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame((int) $uploadedImage->id, (int) $assignedVariant->product_image_id);
        $this->assertNull($blankVariant->product_image_id);
        $this->assertNull($blankVariant->linkedCatalogImage);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('No image', false)
            ->assertDontSee('Main product image', false);
    }

    public function test_cross_store_product_update_returns_404(): void
    {
        $owner = $this->createMerchantUser();
        $storeA = $this->createMemberStore($owner, 'Var Store A');
        $storeB = $this->createMemberStore($owner, 'Var Store B');
        $productB = $this->createSimpleProduct($storeB);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->put(route('product.update', ['productId' => $productB->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $productB->id,
                'name' => 'X',
                'description' => 'd',
                'base_price' => 10,
                'sku' => 'X',
                'product_type' => 'physical',
                'stock_alert' => 1,
            ])
            ->assertNotFound();
    }

    public function test_invalid_variant_image_id_is_rejected(): void
    {
        $owner = $this->createMerchantUser();
        $store = $this->createMemberStore($owner, 'Bad Img Store');
        $product = $this->createSimpleProduct($store);

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
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['L']],
                ],
                'variants' => [
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'BAD-IMG',
                        'price' => 10,
                        'stock' => 1,
                        'stock_alert' => 1,
                        'product_image_id' => 999999,
                    ],
                ],
            ])
            ->assertSessionHasErrors('variants.0.product_image_id');
    }

    protected function createMerchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    protected function createMemberStore(User $user, string $name): Store
    {
        $store = Store::create([
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

    protected function createSimpleProduct(Store $store): Product
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
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 1,
        ]);

        return $product;
    }
}
