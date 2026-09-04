<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCreateValidationPreservesInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_sku_keeps_photos_and_entered_fields_on_add_product(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-create-keep@example.com');
        $store = $this->createMemberStore($owner, 'Keep Draft Store');
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Apparel',
            'slug' => 'apparel-keep',
            'sort_order' => 0,
            'status' => true,
        ]);

        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Existing SKU Product',
            'slug' => 'existing-sku-product',
            'description' => 'x',
            'base_price' => 10,
            'sku' => 'HELEN-SKU',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $photo = UploadedFile::fake()->create('listing.jpg', 12, 'image/jpeg');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products.create'))
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                '_custom_fields_editor' => '1',
                'name' => 'Helen Osborne',
                'description' => 'A detailed product description that must remain.',
                'base_price' => 547,
                'bulk_price' => 547,
                'bulk_stock' => 93,
                'sku' => 'HELEN-SKU',
                'product_type' => 'physical',
                'stock_alert' => 5,
                'inventory_variant_stock_mode' => 'split_total',
                'category_ids' => [$category->id],
                'custom_fields' => [
                    ['key' => 'material', 'type' => 'text', 'value' => 'cotton'],
                ],
                'product_images' => [$photo],
                'image_order' => ['new:0'],
                'variants' => [
                    [
                        'sku' => '',
                        'price' => 547,
                        'stock' => 93,
                        'stock_alert' => 5,
                        'option_map' => [],
                    ],
                ],
            ]);

        $response->assertRedirect(route('products.create'));
        $response->assertSessionHasErrors('sku');

        $old = session('_old_input');
        $this->assertIsArray($old);
        $this->assertSame('Helen Osborne', $old['name'] ?? null);
        $this->assertSame('A detailed product description that must remain.', $old['description'] ?? null);
        $this->assertSame('cotton', data_get($old, 'custom_fields.0.value'));
        $this->assertContains((string) $category->id, array_map('strval', (array) ($old['category_ids'] ?? [])));
        $paths = $old['existing_image_paths'] ?? [];
        $this->assertIsArray($paths);
        $this->assertCount(1, $paths);
        $this->assertStringContainsString('create-drafts', (string) $paths[0]);
        Storage::disk('public')->assertExists($paths[0]);
        $this->assertSame(['existing:'.$paths[0]], $old['image_order'] ?? null);

        $page = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.create'));
        $page->assertOk();
        $html = $page->getContent();
        preg_match('/window\.__workspaceEditInitialPayload = (.*?);/s', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Create page did not boot the product editor payload.');
        $payload = json_decode($matches[1], true);
        $this->assertIsArray($payload);
        $this->assertSame('Helen Osborne', $payload['name'] ?? null);
        $this->assertSame('A detailed product description that must remain.', $payload['description'] ?? null);
        $this->assertSame('cotton', data_get($payload, 'custom_fields.0.value'));
        $this->assertSame($paths[0], data_get($payload, 'catalog_images.0.image_path'));
        $this->assertSame([$paths[0]], $payload['image_paths'] ?? null);
        $this->assertEquals(1, substr_count($html, 'That product SKU is already used in this store.'));
    }

    public function test_retry_after_duplicate_sku_saves_stashed_photos(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-create-retry@example.com');
        $store = $this->createMemberStore($owner, 'Retry Draft Store');

        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Taken SKU',
            'slug' => 'taken-sku',
            'description' => 'x',
            'base_price' => 10,
            'sku' => 'TAKEN-SKU',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $photo = UploadedFile::fake()->create('retry.jpg', 12, 'image/jpeg');

        $failed = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products.create'))
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Retry Photo Product',
                'description' => 'kept',
                'base_price' => 20,
                'bulk_price' => 20,
                'bulk_stock' => 4,
                'sku' => 'TAKEN-SKU',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'product_images' => [$photo],
                'image_order' => ['new:0'],
            ]);

        $failed->assertSessionHasErrors('sku');
        $paths = session('_old_input.existing_image_paths');
        $this->assertIsArray($paths);
        $this->assertCount(1, $paths);

        $saved = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => 'Retry Photo Product',
                'description' => 'kept',
                'base_price' => 20,
                'bulk_price' => 20,
                'bulk_stock' => 4,
                'sku' => 'UNIQUE-RETRY',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'existing_image_paths' => $paths,
                'image_order' => ['existing:'.$paths[0]],
            ]);

        $product = Product::query()
            ->where('store_id', $store->id)
            ->where('name', 'Retry Photo Product')
            ->firstOrFail();
        $saved->assertRedirect(route('products.show', ['product' => $product->id]));
        $this->assertCount(1, $product->images);
        Storage::disk('public')->assertExists($product->images->first()->image_path);
        $this->assertStringNotContainsString('create-drafts', (string) $product->images->first()->image_path);
    }

    public function test_missing_name_still_keeps_uploaded_photos(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-create-name@example.com');
        $store = $this->createMemberStore($owner, 'Name Draft Store');
        $photo = UploadedFile::fake()->create('named.jpg', 12, 'image/jpeg');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products.create'))
            ->post(route('product.store'), [
                '_full_workspace_create' => '1',
                'name' => '',
                'description' => 'Still here',
                'base_price' => 12,
                'bulk_price' => 12,
                'bulk_stock' => 2,
                'sku' => 'NAME-KEEP',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'product_images' => [$photo],
                'image_order' => ['new:0'],
            ]);

        $response->assertSessionHasErrors('name');
        $paths = session('_old_input.existing_image_paths');
        $this->assertIsArray($paths);
        $this->assertCount(1, $paths);
        Storage::disk('public')->assertExists($paths[0]);
        $this->assertSame('Still here', session('_old_input.description'));
        $this->assertSame('NAME-KEEP', session('_old_input.sku'));
    }

    protected function createMerchantUser(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    protected function createMemberStore(User $user, string $name, string $role = Store::ROLE_OWNER): Store
    {
        $store = Store::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);

        $store->members()->attach($user->id, ['role' => $role]);

        return $store;
    }
}
