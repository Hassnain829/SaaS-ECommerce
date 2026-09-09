<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_product_images_on_create(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img@example.com');
        $store = $this->createMemberStore($owner, 'Img Store', Store::ROLE_OWNER);

        $file = UploadedFile::fake()->create('shot.jpg', 12, 'image/jpeg');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Photo Product',
                'description' => 'd',
                'bulk_price' => 10,
                'sku' => 'IMG-1',
                'product_type' => 'physical',
                'bulk_stock' => 1,
                'stock_alert' => 1,
                'product_images' => [$file],
                '_open_add_product_modal' => '1',
            ]);

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Photo Product')->firstOrFail();
        $response->assertRedirect(route('products.edit', ['product' => $product->id]));
        $this->assertCount(1, $product->images);
        $path = $product->images->first()->image_path;
        $this->assertIsString($path);
        $this->assertStringNotContainsStringIgnoringCase('base64', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'image_path' => $path,
            'is_primary' => true,
        ]);

        $meta = $product->meta ?? [];
        $this->assertArrayNotHasKey('image_path', $meta);
        $this->assertArrayNotHasKey('image_paths', $meta);
    }

    public function test_database_stores_path_string_not_binary(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img2@example.com');
        $store = $this->createMemberStore($owner, 'Img Store 2', Store::ROLE_OWNER);

        $file = UploadedFile::fake()->create('a.png', 12, 'image/png');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Binary Check',
                'description' => 'd',
                'bulk_price' => 5,
                'sku' => 'BIN-1',
                'product_type' => 'physical',
                'bulk_stock' => 1,
                'stock_alert' => 1,
                'product_images' => [$file],
            ])
            ->assertRedirect(route('products'));

        $row = DB::table('product_images')->first();
        $this->assertNotNull($row);
        $this->assertIsString($row->image_path);
        $this->assertLessThanOrEqual(512, strlen($row->image_path));
    }

    public function test_product_update_can_add_images_and_list_retains_existing(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img3@example.com');
        $store = $this->createMemberStore($owner, 'Img Store 3', Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Updatable',
            'slug' => 'updatable-img',
            'description' => 'x',
            'base_price' => 10,
            'sku' => 'UP-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $first = UploadedFile::fake()->create('one.jpg', 12, 'image/jpeg');
        $path1 = $first->store('products/'.$store->id, 'public');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path1,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $newFile = UploadedFile::fake()->create('two.jpg', 12, 'image/jpeg');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => 'Updatable',
                'description' => 'x',
                'base_price' => 10,
                'sku' => 'UP-1',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'existing_image_paths' => [$path1],
                'product_images' => [$newFile],
            ])
            ->assertRedirect(route('products'));

        $product->refresh();
        $this->assertCount(2, $product->images);
        $this->assertTrue($product->images->contains('image_path', $path1));
    }

    public function test_cross_store_existing_image_path_is_rejected_on_update(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img4@example.com');
        $storeA = $this->createMemberStore($owner, 'Store A Img', Store::ROLE_OWNER);
        $storeB = $this->createMemberStore($owner, 'Store B Img', Store::ROLE_OWNER);

        $productA = Product::query()->create([
            'store_id' => $storeA->id,
            'name' => 'PA',
            'slug' => 'pa-img',
            'description' => 'x',
            'base_price' => 1,
            'sku' => 'PA-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);
        $productB = Product::query()->create([
            'store_id' => $storeB->id,
            'name' => 'PB',
            'slug' => 'pb-img',
            'description' => 'x',
            'base_price' => 1,
            'sku' => 'PB-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $foreignPath = UploadedFile::fake()->create('foreign.jpg', 12, 'image/jpeg')->store('products/'.$storeB->id, 'public');
        ProductImage::query()->create([
            'product_id' => $productB->id,
            'image_path' => $foreignPath,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->put(route('product.update', ['productId' => $productA->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $productA->id,
                'name' => 'PA',
                'description' => 'x',
                'base_price' => 1,
                'sku' => 'PA-1',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'existing_image_paths' => [$foreignPath],
            ]);

        $response->assertSessionHasErrors('existing_image_paths.0');
    }

    public function test_products_page_shows_thumbnail_from_product_images(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img5@example.com');
        $store = $this->createMemberStore($owner, 'Img Store 5', Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Thumb Product',
            'slug' => 'thumb-product',
            'description' => 'x',
            'base_price' => 12,
            'sku' => 'TH-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $path = UploadedFile::fake()->create('thumb.jpg', 12, 'image/jpeg')->store('products/'.$store->id, 'public');
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $page = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'));

        $page->assertOk();
        $page->assertSee('storage/'.$path, false);
    }

    public function test_staff_cannot_create_product_with_images(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img6@example.com');
        $staff = $this->createMerchantUser('staff-img6@example.com');
        $store = $this->createMemberStore($owner, 'Img Store 6', Store::ROLE_OWNER);
        $this->attachMember($store, $staff, Store::ROLE_STAFF);

        $file = UploadedFile::fake()->create('no.jpg', 12, 'image/jpeg');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Blocked',
                'description' => 'd',
                'bulk_price' => 1,
                'sku' => 'NO-1',
                'product_type' => 'physical',
                'bulk_stock' => 1,
                'stock_alert' => 1,
                'product_images' => [$file],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['name' => 'Blocked']);
    }

    public function test_first_uploaded_image_is_primary_when_multiple_uploaded(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img7@example.com');
        $store = $this->createMemberStore($owner, 'Img Store 7', Store::ROLE_OWNER);

        $f1 = UploadedFile::fake()->create('a.jpg', 12, 'image/jpeg');
        $f2 = UploadedFile::fake()->create('b.jpg', 12, 'image/jpeg');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Multi Img',
                'description' => 'd',
                'bulk_price' => 2,
                'sku' => 'MUL-1',
                'product_type' => 'physical',
                'bulk_stock' => 1,
                'stock_alert' => 1,
                'product_images' => [$f1, $f2],
            ])
            ->assertRedirect(route('products'));

        $product = Product::query()->where('name', 'Multi Img')->firstOrFail();
        $this->assertCount(2, $product->images);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
        $primary = $product->images()->where('is_primary', true)->first();
        $this->assertSame(0, (int) $primary->sort_order);
    }

    public function test_create_honors_image_order_so_later_upload_can_be_main(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img-order@example.com');
        $store = $this->createMemberStore($owner, 'Img Order Store', Store::ROLE_OWNER);

        $first = UploadedFile::fake()->create('first.jpg', 12, 'image/jpeg');
        $second = UploadedFile::fake()->create('second.jpg', 12, 'image/jpeg');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                'name' => 'Ordered Img',
                'description' => 'd',
                'bulk_price' => 2,
                'sku' => 'ORD-1',
                'product_type' => 'physical',
                'bulk_stock' => 1,
                'stock_alert' => 1,
                'product_images' => [$first, $second],
                'image_order' => ['new:1', 'new:0'],
            ])
            ->assertRedirect(route('products'));

        $product = Product::query()->where('store_id', $store->id)->where('name', 'Ordered Img')->firstOrFail();
        $images = $product->images()->orderBy('id')->get();
        $this->assertCount(2, $images);
        $this->assertFalse((bool) $images[0]->is_primary);
        $this->assertTrue((bool) $images[1]->is_primary);
        $this->assertSame(1, (int) $images[0]->sort_order);
        $this->assertSame(0, (int) $images[1]->sort_order);
    }

    public function test_update_reorders_existing_photos_from_existing_image_paths(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img-reorder@example.com');
        $store = $this->createMemberStore($owner, 'Img Reorder Store', Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Reorder Me',
            'slug' => 'reorder-me-img',
            'description' => 'x',
            'base_price' => 10,
            'sku' => 'REO-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $pathA = UploadedFile::fake()->create('a.jpg', 12, 'image/jpeg')->store('products/'.$store->id, 'public');
        $pathB = UploadedFile::fake()->create('b.jpg', 12, 'image/jpeg')->store('products/'.$store->id, 'public');
        $imageA = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $pathA,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $imageB = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $pathB,
            'sort_order' => 1,
            'is_primary' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => 'Reorder Me',
                'description' => 'x',
                'base_price' => 10,
                'sku' => 'REO-1',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'existing_image_paths' => [$pathB, $pathA],
            ])
            ->assertRedirect(route('products'));

        $imageA->refresh();
        $imageB->refresh();
        $this->assertTrue((bool) $imageB->is_primary);
        $this->assertFalse((bool) $imageA->is_primary);
        $this->assertSame(0, (int) $imageB->sort_order);
        $this->assertSame(1, (int) $imageA->sort_order);
    }

    public function test_update_can_place_a_new_upload_before_existing_photos(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img-mixed@example.com');
        $store = $this->createMemberStore($owner, 'Img Mixed Store', Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Mixed Gallery',
            'slug' => 'mixed-gallery-img',
            'description' => 'x',
            'base_price' => 10,
            'sku' => 'MIX-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $pathA = UploadedFile::fake()->create('keep.jpg', 12, 'image/jpeg')->store('products/'.$store->id, 'public');
        $existing = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => $pathA,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $newFile = UploadedFile::fake()->create('fresh.jpg', 12, 'image/jpeg');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => 'Mixed Gallery',
                'description' => 'x',
                'base_price' => 10,
                'sku' => 'MIX-1',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'existing_image_paths' => [$pathA],
                'product_images' => [$newFile],
                'image_order' => ['new:0', 'existing:'.$pathA],
            ])
            ->assertRedirect(route('products'));

        $product->refresh();
        $this->assertCount(2, $product->images);
        $existing->refresh();
        $newImage = $product->images->firstWhere('id', '!=', $existing->id);
        $this->assertNotNull($newImage);
        $this->assertTrue((bool) $newImage->is_primary);
        $this->assertSame(0, (int) $newImage->sort_order);
        $this->assertFalse((bool) $existing->is_primary);
        $this->assertSame(1, (int) $existing->sort_order);
    }

    public function test_owner_can_keep_nine_product_images(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img9@example.com');
        $store = $this->createMemberStore($owner, 'Img Store 9', Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Nine Photos',
            'slug' => 'nine-photos-img',
            'description' => 'x',
            'base_price' => 10,
            'sku' => 'IMG-9',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $paths = [];
        for ($i = 1; $i <= 8; $i++) {
            $path = UploadedFile::fake()->create('keep-'.$i.'.jpg', 12, 'image/jpeg')->store('products/'.$store->id, 'public');
            $paths[] = $path;
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $path,
                'sort_order' => $i - 1,
                'is_primary' => $i === 1,
            ]);
        }

        $newFile = UploadedFile::fake()->create('ninth.jpg', 12, 'image/jpeg');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => 'Nine Photos',
                'description' => 'x',
                'base_price' => 10,
                'sku' => 'IMG-9',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'existing_image_paths' => $paths,
                'product_images' => [$newFile],
            ])
            ->assertRedirect(route('products'));

        $this->assertSame(9, $product->images()->count());
    }

    public function test_owner_cannot_keep_more_than_thirty_two_product_images(): void
    {
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-img33@example.com');
        $store = $this->createMemberStore($owner, 'Img Store 33', Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Too Many Photos',
            'slug' => 'too-many-photos-img',
            'description' => 'x',
            'base_price' => 10,
            'sku' => 'IMG-33',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['default_stock' => 1, 'stock_alert' => 1],
        ]);

        $paths = [];
        for ($i = 1; $i <= 32; $i++) {
            $path = UploadedFile::fake()->create('keep-'.$i.'.jpg', 12, 'image/jpeg')->store('products/'.$store->id, 'public');
            $paths[] = $path;
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $path,
                'sort_order' => $i - 1,
                'is_primary' => $i === 1,
            ]);
        }

        $newFile = UploadedFile::fake()->create('fresh.jpg', 12, 'image/jpeg');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                '_open_edit_product_modal' => '1',
                '_edit_product_id' => (string) $product->id,
                'name' => 'Too Many Photos',
                'description' => 'x',
                'base_price' => 10,
                'sku' => 'IMG-33',
                'product_type' => 'physical',
                'stock_alert' => 1,
                'existing_image_paths' => $paths,
                'product_images' => [$newFile],
            ])
            ->assertSessionHasErrors('product_images');

        $this->assertSame(32, $product->images()->count());
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

    protected function attachMember(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }
}
