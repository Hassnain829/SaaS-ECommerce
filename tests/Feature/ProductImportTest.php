<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductImageJob;
use App\Jobs\ProcessProductImportJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use App\Services\Catalog\ProductImportProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_and_complete_import_flow(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('owner-import@example.com');
        $store = $this->createMemberStore($owner, 'Import Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Stock,ExtraCol\nWidget One,IMP-001,19.99,5,hello\n";
        $file = UploadedFile::fake()->createWithContent('catalog.csv', $csv);

        $importResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->first();
        $this->assertNotNull($import);
        $this->assertContains('Title', $import->headers);

        $importResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_PREVIEWED, $import->status);
        $this->assertNotEmpty($import->preview_summary);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]))
            ->assertRedirect(route('products.import.result', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'IMP-001')->first();
        $this->assertNotNull($product);
        $this->assertSame('Widget One', $product->name);
        $this->assertSame('hello', $product->meta['import_extra']['ExtraCol'] ?? null);

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'movement_type' => 'import',
            'reference_type' => 'product_import',
            'reference_id' => $import->id,
            'performed_by' => $owner->id,
        ]);
    }

    public function test_manager_can_start_import(): void
    {
        Storage::fake('local');
        $owner = $this->createMerchantUser('o2@example.com');
        $manager = $this->createMerchantUser('m2@example.com');
        $store = $this->createMemberStore($owner, 'Mgr Import', Store::ROLE_OWNER);
        $store->members()->syncWithoutDetaching([$manager->id => ['role' => Store::ROLE_MANAGER]]);

        $file = UploadedFile::fake()->createWithContent('a.csv', "Name,Code\nA,B1\n");

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('product_imports', ['store_id' => $store->id, 'created_by' => $manager->id]);
    }

    public function test_staff_cannot_access_import_routes(): void
    {
        $owner = $this->createMerchantUser('o3@example.com');
        $staff = $this->createMerchantUser('s3@example.com');
        $store = $this->createMemberStore($owner, 'Staff Block', Store::ROLE_OWNER);
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.create'))
            ->assertForbidden();
    }

    public function test_import_creates_taxonomy_in_current_store(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('tax@example.com');
        $store = $this->createMemberStore($owner, 'Tax Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Brand,Category,Tags\nT,SKU-T,10,New Brand X,New Cat Y|New Cat Z,Sale|New\n";
        $file = UploadedFile::fake()->createWithContent('tax.csv', $csv);

        $taxResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $taxResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));
        $this->assertSame(ProductImport::STATUS_PREVIEWED, $import->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $this->assertTrue(Brand::query()->where('store_id', $store->id)->where('name', 'New Brand X')->exists());
        $this->assertTrue(Category::query()->where('store_id', $store->id)->where('name', 'New Cat Y')->exists());
        $this->assertTrue(Tag::query()->where('store_id', $store->id)->where('name', 'Sale')->exists());
    }

    public function test_import_updates_existing_product_and_logs_stock_change(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('upd@example.com');
        $store = $this->createMemberStore($owner, 'Upd Store', Store::ROLE_OWNER);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Old',
            'slug' => 'old-upd-1',
            'description' => null,
            'base_price' => 5,
            'sku' => 'UPD-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = $product->variants()->create([
            'sku' => 'UPD-1',
            'price' => 5,
            'stock' => 2,
            'stock_alert' => 0,
        ]);
        $variant->options()->sync([]);

        $csv = "Title,SKU,Stock\nNew Name,UPD-1,9\n";
        $file = UploadedFile::fake()->createWithContent('u.csv', $csv);

        $updResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->latest('id')->firstOrFail();
        $updResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $variant->refresh();
        $this->assertSame(9, (int) $variant->stock);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'movement_type' => 'import',
            'previous_stock' => 2,
            'quantity_change' => 7,
            'new_stock' => 9,
        ]);
    }

    public function test_invalid_row_does_not_stop_valid_row(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('mix@example.com');
        $store = $this->createMemberStore($owner, 'Mix Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Stock\nGood,G1,1\n,,,\n";
        $file = UploadedFile::fake()->createWithContent('mix.csv', $csv);

        $mixResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $mixResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(1, (int) ($import->result_summary['created'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($import->result_summary['failed'] ?? 0));
        $this->assertDatabaseHas('products', ['store_id' => $store->id, 'sku' => 'G1']);
    }

    public function test_image_url_import_stores_file_on_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        Http::fake([
            'https://example.test/image.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('x', 50), 200, ['Content-Type' => 'image/png']),
        ]);

        $owner = $this->createMerchantUser('img@example.com');
        $store = $this->createMemberStore($owner, 'Img Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Images\nImg P,IMG-P,1,https://example.test/image.png\n";
        $file = UploadedFile::fake()->createWithContent('img.csv', $csv);

        $imgResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $imgResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $product = Product::query()->where('sku', 'IMG-P')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, $product->images()->count());
        Storage::disk('public')->assertExists($product->images()->first()->image_path);
    }

    public function test_import_accepts_messy_stock_and_price_cells(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('messy@example.com');
        $store = $this->createMemberStore($owner, 'Messy Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Stock\nWidget,MSK-1,\"PKR 2,499.00\",\" 1,200 \"\n";
        $file = UploadedFile::fake()->createWithContent('messy.csv', $csv);

        $messyResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $messyResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(1, (int) ($import->preview_summary['valid_rows'] ?? 0));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'MSK-1')->firstOrFail();
        $this->assertEqualsWithDelta(2499.0, (float) $product->base_price, 0.01);
        $variant = $product->variants()->firstOrFail();
        $this->assertSame(1200, (int) $variant->stock);
    }

    public function test_custom_field_mapping_is_stored_under_meta_custom_fields(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('cust@example.com');
        $store = $this->createMemberStore($owner, 'Custom Meta Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Supplier\nWidget,CUST-1,ACME-77\n";
        $file = UploadedFile::fake()->createWithContent('cust.csv', $csv);

        $custResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $custResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertNotEmpty($import->preview_summary['custom_field_preview_lines'] ?? []);
        $this->assertStringContainsString('supplier_code', (string) ($import->preview_summary['custom_field_preview_lines'][0] ?? ''));
        $this->assertNotEmpty($import->custom_field_mappings);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $product = Product::query()->where('sku', 'CUST-1')->firstOrFail();
        $this->assertSame('ACME-77', $product->meta['custom_fields']['supplier_code'] ?? null);
        $this->assertArrayNotHasKey('Supplier', $product->meta['import_extra'] ?? []);
    }

    public function test_large_import_completes_and_exposes_progress_summary(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('large@example.com');
        $store = $this->createMemberStore($owner, 'Large Import Store', Store::ROLE_OWNER);

        $lines = ["Title,SKU,Price,Stock\n"];
        for ($i = 1; $i <= 35; $i++) {
            $lines[] = sprintf("Item %d,SKU-L-%03d,10,%d\n", $i, $i, $i);
        }
        $csv = implode('', $lines);
        $file = UploadedFile::fake()->createWithContent('large.csv', $csv);

        $largeResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $largeResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $this->assertSame(35, (int) ($import->result_summary['created'] ?? 0));
        $this->assertArrayHasKey('progress', $import->result_summary ?? []);
        $this->assertSame('completed', $import->result_summary['progress']['phase'] ?? null);
        $this->assertSame(35, (int) ($import->result_summary['progress']['processed_rows'] ?? 0));
        $this->assertSame(35, Product::query()->where('store_id', $store->id)->count());
        $this->assertGreaterThan(0, ProductImportRow::query()->where('product_import_id', $import->id)->count());
    }

    public function test_import_resume_processes_remaining_rows(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('resume@example.com');
        $store = $this->createMemberStore($owner, 'Resume Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Stock\nR1,S1,1,1\nR2,S2,1,1\n";
        Storage::disk('local')->put('imports/resume.csv', $csv);

        $p1 = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'R1',
            'slug' => 'r1-resume',
            'description' => null,
            'base_price' => 1,
            'sku' => 'S1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $v1 = $p1->variants()->create([
            'sku' => 'S1',
            'price' => 1,
            'stock' => 1,
            'stock_alert' => 0,
        ]);
        $v1->options()->sync([]);

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'resume.csv',
            'stored_disk' => 'local',
            'stored_path' => 'imports/resume.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_FAILED,
            'headers' => ['Title', 'SKU', 'Price', 'Stock'],
            'column_mapping' => [
                'product_name' => 'Title',
                'sku' => 'SKU',
                'base_price' => 'Price',
                'stock' => 'Stock',
            ],
            'custom_field_mappings' => null,
            'preview_summary' => [],
            'last_processed_row' => 1,
            'total_rows' => 2,
            'import_state' => [
                'seen_sku_keys' => ['s1'],
                'assigned_variant_sku_lower' => ['s1'],
            ],
            'result_summary' => [
                'failures' => [],
                'warnings_count' => 0,
                'progress' => [
                    'created' => 1,
                    'updated' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.resume', ['productImportId' => $import->id]))
            ->assertRedirect(route('products.import.result', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $this->assertTrue(Product::query()->where('store_id', $store->id)->where('sku', 'S2')->exists());
    }

    public function test_import_row_failures_are_persisted_on_product_import_rows(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('rows@example.com');
        $store = $this->createMemberStore($owner, 'Row Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Stock\nGood,G1,1,1\n,BAD,1,1\n";
        $file = UploadedFile::fake()->createWithContent('rows.csv', $csv);

        $rowsResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->latest('id')->firstOrFail();
        $rowsResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $failedRow = ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->orderByDesc('row_number')
            ->first();
        $this->assertNotNull($failedRow);
        $this->assertNotEmpty($failedRow->error_message);
    }

    public function test_image_job_dispatched_when_async_images_enabled(): void
    {
        config(['product_import.async_image_processing' => true]);
        Queue::fake();

        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('imgjob@example.com');
        $store = $this->createMemberStore($owner, 'Img Job Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Stock,Img\nItem,IMGJ-1,5,2,https://example.com/a.png\n";
        $file = UploadedFile::fake()->createWithContent('ij.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->latest('id')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'product_name' => 'Title',
                    'sku' => 'SKU',
                    'base_price' => 'Price',
                    'stock' => 'Stock',
                    'image_urls' => 'Img',
                ],
            ]);

        $import->refresh();
        $import->update([
            'status' => ProductImport::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        // Run the processor directly: Queue::fake() would otherwise prevent a sync bus run
        // from executing this job’s handle(), leaving the import stuck as “queued”.
        app(ProductImportProcessor::class)->run($import->fresh());

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);

        Queue::assertPushed(ProcessProductImageJob::class, function (ProcessProductImageJob $job) use ($import): bool {
            return $job->productImageId > 0
                && (int) $job->productImportId === (int) $import->id;
        });
    }

    public function test_import_job_is_dispatched_on_confirm(): void
    {
        Bus::fake();
        Storage::fake('local');

        $owner = $this->createMerchantUser('bus@example.com');
        $store = $this->createMemberStore($owner, 'Bus Store', Store::ROLE_OWNER);

        $file = UploadedFile::fake()->createWithContent('b.csv', "Title,SKU\nX,Y1\n");

        $busResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $busResponse->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $import->refresh();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        Bus::assertDispatched(ProcessProductImportJob::class, fn (ProcessProductImportJob $job): bool => $job->productImportId === $import->id);
    }

    public function test_mapping_page_shows_grouped_merchant_sections(): void
    {
        Storage::fake('local');

        $owner = $this->createMerchantUser('map-sections@example.com');
        $store = $this->createMemberStore($owner, 'Map Sections Store', Store::ROLE_OWNER);

        $file = UploadedFile::fake()->createWithContent('sections.csv', "H1,H2,H3\nWidget,S-1,12\n");

        $secResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $secResponse->assertRedirect(route('products.import.mapping', ['productImportId' => $import->id]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.mapping', ['productImportId' => $import->id]))
            ->assertOk()
            ->assertSee('id="import-mapping-section-required_basics"', false)
            ->assertSee('id="import-mapping-section-product_information"', false)
            ->assertSee('id="import-mapping-section-pricing_inventory"', false)
            ->assertSee('id="import-mapping-section-variants"', false)
            ->assertSee('id="import-mapping-section-images"', false)
            ->assertSee('id="import-mapping-section-additional_details"', false);
    }

    protected function createMerchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
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
