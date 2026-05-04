<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\ProductEditPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductEditPayloadCatalogImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_images_include_merchant_picker_labels(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => 'Picker Store',
            'slug' => 'picker-'.Str::random(4),
            'logo' => null,
            'address' => 'A',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Img Product',
            'slug' => 'img-'.Str::random(4),
            'description' => null,
            'base_price' => 10,
            'sku' => 'IMG-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->images()->create([
            'image_path' => 'products/hero-shot.jpg',
            'status' => 'ready',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $product->images()->create([
            'image_path' => 'products/side-view.png',
            'status' => 'ready',
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        $product->variants()->create([
            'sku' => 'IMG-1',
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $payload = ProductEditPayload::forProduct($product->fresh(['images', 'variants']));
        $this->assertCount(2, $payload['catalog_images']);
        $this->assertStringContainsString('Main product image', (string) $payload['catalog_images'][0]['picker_label']);
        $this->assertStringContainsString('hero-shot', (string) $payload['catalog_images'][0]['picker_label']);
        $this->assertStringContainsString('Gallery image 1', (string) $payload['catalog_images'][1]['picker_label']);
        $this->assertStringContainsString('side-view', (string) $payload['catalog_images'][1]['picker_label']);
    }

    public function test_catalog_image_picker_labels_note_processing_and_failed_states(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => 'State Store',
            'slug' => 'state-'.Str::random(4),
            'logo' => null,
            'address' => 'A',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'State Product',
            'slug' => 'state-p-'.Str::random(4),
            'description' => null,
            'base_price' => 10,
            'sku' => 'ST-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->images()->create([
            'image_path' => 'products/queued.jpg',
            'status' => ProductImage::STATUS_QUEUED,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $product->images()->create([
            'image_path' => 'products/bad.png',
            'status' => ProductImage::STATUS_FAILED,
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        $product->images()->create([
            'image_path' => ProductImage::PENDING_DISK_PATH,
            'status' => ProductImage::STATUS_READY,
            'sort_order' => 2,
            'is_primary' => false,
        ]);
        $product->variants()->create([
            'sku' => 'ST-1',
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $payload = ProductEditPayload::forProduct($product->fresh(['images', 'variants']));
        $this->assertCount(3, $payload['catalog_images']);
        $this->assertStringContainsString('processing', (string) $payload['catalog_images'][0]['picker_label']);
        $this->assertStringContainsString('upload failed', (string) $payload['catalog_images'][1]['picker_label']);
        $this->assertStringContainsString('pending download', (string) $payload['catalog_images'][2]['picker_label']);
    }
}
