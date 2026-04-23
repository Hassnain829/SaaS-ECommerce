<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductImageJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Catalog\ProductCatalogImageDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessProductImageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_job_downloads_and_sets_ready_status(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://example.test/p.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('x', 40), 200, ['Content-Type' => 'image/png']),
        ]);

        $owner = $this->createMerchantUser('img-job@example.com');
        $store = $this->createMemberStore($owner, 'Img Job', Store::ROLE_OWNER);

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'x.csv',
            'stored_disk' => 'local',
            'stored_path' => 'x.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_COMPLETED,
            'headers' => [],
            'column_mapping' => [],
            'result_summary' => [
                'total_images' => 1,
                'processed_images' => 0,
                'failed_images' => 0,
            ],
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'P',
            'slug' => 'p-'.uniqid(),
            'description' => null,
            'base_price' => 1,
            'sku' => 'PJ-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $row = ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => ProductImage::PENDING_DISK_PATH,
            'source_url' => 'https://example.test/p.png',
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_QUEUED,
            'product_import_id' => $import->id,
        ]);

        $job = new ProcessProductImageJob($row->id, $import->id);
        $job->handle(app(ProductCatalogImageDownloader::class));

        $row->refresh();
        $this->assertSame(ProductImage::STATUS_READY, $row->status);
        $this->assertNotSame(ProductImage::PENDING_DISK_PATH, $row->image_path);
        Storage::disk('public')->assertExists($row->image_path);

        $import->refresh();
        $this->assertSame(1, (int) ($import->result_summary['processed_images'] ?? 0));
    }

    public function test_import_progress_endpoint_returns_snapshot_json(): void
    {
        $owner = $this->createMerchantUser('prog@example.com');
        $store = $this->createMemberStore($owner, 'Prog Store', Store::ROLE_OWNER);

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'x.csv',
            'stored_disk' => 'local',
            'stored_path' => 'x.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_COMPLETED,
            'total_rows' => 10,
            'headers' => [],
            'column_mapping' => [],
            'result_summary' => [
                'total_products' => 10,
                'processed_products' => 10,
                'total_images' => 5,
                'processed_images' => 2,
                'failed_images' => 1,
            ],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->getJson(route('products.import.progress', ['productImportId' => $import->id]))
            ->assertOk()
            ->assertJsonPath('products.total', 10)
            ->assertJsonPath('products.processed', 10)
            ->assertJsonPath('images.total', 5)
            ->assertJsonPath('images.processed', 2)
            ->assertJsonPath('images.failed', 1);
    }

    public function test_products_primary_images_endpoint_reports_pending_state(): void
    {
        $owner = $this->createMerchantUser('thumb@example.com');
        $store = $this->createMemberStore($owner, 'Thumb Store', Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'T',
            'slug' => 't-'.uniqid(),
            'description' => null,
            'base_price' => 1,
            'sku' => 'TH-99',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_path' => ProductImage::PENDING_DISK_PATH,
            'source_url' => 'https://example.test/x.png',
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_QUEUED,
            'product_import_id' => null,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->getJson(route('products.primary-images', ['ids' => (string) $product->id]))
            ->assertOk()
            ->assertJsonPath('products.'.(string) $product->id.'.state', 'pending');
    }

    protected function createMerchantUser(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    protected function createMemberStore(User $user, string $name, string $role): Store
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
