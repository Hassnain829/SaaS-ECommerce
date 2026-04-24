<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
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
            ->assertSee('Sellable combinations and inventory', false)
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
        $this->assertSame((int) $variant->id, (int) $image->product_variant_id);
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
