<?php

namespace Tests\Feature;

use App\Models\ProductImport;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportStuckRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config(['queue.default' => 'sync']);
        config(['product_import.explicit_queue_connection' => null]);
        if (isset($this->app)) {
            config(['product_import.queue_connection' => \App\Support\Catalog\ProductImportQueue::connection()]);
        }

        parent::tearDown();
    }

    public function test_stale_queued_import_is_marked_failed_when_result_page_is_viewed(): void
    {
        $owner = $this->createMerchantUser('stale-q@example.com');
        $store = $this->createMemberStore($owner, 'Stale Q Store', Store::ROLE_OWNER);

        config(['queue.default' => 'database']);
        config(['product_import.explicit_queue_connection' => 'database']);
        config(['product_import.queue_connection' => \App\Support\Catalog\ProductImportQueue::connection()]);

        Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'old.csv',
            'stored_disk' => 'local',
            'stored_path' => 'product-imports/'.$store->id.'/missing.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_QUEUED,
            'headers' => ['A'],
            'column_mapping' => ['product_name' => 'A'],
            'preview_summary' => [],
            'queued_at' => Carbon::parse('2026-06-01 11:49:00'),
        ]);

        config(['product_import.stale_queued_minutes' => 5]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.result', ['productImportId' => $import->id]))
            ->assertOk()
            ->assertSeeText('We could not finish this import');

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_FAILED, $import->status);
        $this->assertNotEmpty($import->failure_message);
        $this->assertSame('queued_timeout', $import->result_summary['stale_reason'] ?? null);

        Carbon::setTestNow();
    }

    public function test_stale_processing_import_is_marked_failed_when_result_page_is_viewed(): void
    {
        $owner = $this->createMerchantUser('stale-p@example.com');
        $store = $this->createMemberStore($owner, 'Stale P Store', Store::ROLE_OWNER);

        Carbon::setTestNow(Carbon::parse('2026-06-01 15:00:00'));

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'big.csv',
            'stored_disk' => 'local',
            'stored_path' => 'product-imports/'.$store->id.'/x.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_PROCESSING,
            'headers' => ['A'],
            'column_mapping' => ['product_name' => 'A'],
            'preview_summary' => [],
            'started_at' => Carbon::parse('2026-06-01 13:00:00'),
            'total_rows' => 100,
            'last_processed_row' => 1,
        ]);

        config(['product_import.stale_processing_minutes' => 45]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.result', ['productImportId' => $import->id]))
            ->assertOk()
            ->assertSeeText('We could not finish this import');

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_FAILED, $import->status);
        $this->assertSame('processing_timeout', $import->result_summary['stale_reason'] ?? null);

        Carbon::setTestNow();
    }

    public function test_confirm_fails_fast_when_uploaded_file_is_missing(): void
    {
        Storage::fake('local');

        $owner = $this->createMerchantUser('missing@example.com');
        $store = $this->createMemberStore($owner, 'Missing File Store', Store::ROLE_OWNER);

        $file = UploadedFile::fake()->createWithContent('gone.csv', "Title,SKU\nA,B\n");

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();
        $path = $import->stored_path;

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'product_name' => 'Title',
                    'sku' => 'SKU',
                ],
            ]);

        Storage::disk('local')->delete($path);
        $this->assertFalse(Storage::disk('local')->exists($path));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]))
            ->assertRedirect(route('products.import.result', ['productImportId' => $import->id]))
            ->assertSessionHasErrors('import');

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_FAILED, $import->status);
        $this->assertStringContainsString('could not find', strtolower((string) $import->failure_message));
    }

    public function test_import_history_resolves_stale_queued_imports(): void
    {
        $owner = $this->createMerchantUser('hist-stale@example.com');
        $store = $this->createMemberStore($owner, 'Hist Stale', Store::ROLE_OWNER);

        config(['queue.default' => 'database']);
        config(['product_import.explicit_queue_connection' => 'database']);
        config(['product_import.queue_connection' => \App\Support\Catalog\ProductImportQueue::connection()]);

        Carbon::setTestNow(Carbon::parse('2026-07-01 10:00:00'));

        ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'stuck.csv',
            'stored_disk' => 'local',
            'stored_path' => 'product-imports/'.$store->id.'/s.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_QUEUED,
            'headers' => ['H'],
            'column_mapping' => ['product_name' => 'H'],
            'preview_summary' => [],
            'queued_at' => Carbon::parse('2026-07-01 09:50:00'),
        ]);

        config(['product_import.stale_queued_minutes' => 5]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.history'))
            ->assertOk()
            ->assertSeeText('Could not finish');

        $this->assertDatabaseHas('product_imports', [
            'store_id' => $store->id,
            'original_filename' => 'stuck.csv',
            'status' => ProductImport::STATUS_FAILED,
        ]);

        Carbon::setTestNow();
    }

    public function test_sync_inline_queue_does_not_apply_queued_stale_timeout(): void
    {
        $owner = $this->createMerchantUser('sync-stale@example.com');
        $store = $this->createMemberStore($owner, 'Sync Stale Store', Store::ROLE_OWNER);

        config(['queue.default' => 'sync']);
        config(['product_import.explicit_queue_connection' => null]);
        config(['product_import.queue_connection' => \App\Support\Catalog\ProductImportQueue::connection()]);

        Carbon::setTestNow(Carbon::parse('2026-08-01 14:00:00'));

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'sync-stale.csv',
            'stored_disk' => 'local',
            'stored_path' => 'product-imports/'.$store->id.'/x.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_QUEUED,
            'headers' => ['A'],
            'column_mapping' => ['product_name' => 'A'],
            'preview_summary' => [],
            'queued_at' => Carbon::parse('2026-08-01 13:00:00'),
        ]);

        config(['product_import.stale_queued_minutes' => 5]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.result', ['productImportId' => $import->id]))
            ->assertOk();

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_QUEUED, $import->status);

        Carbon::setTestNow();
    }

    public function test_upload_persists_file_and_advances_to_parsed(): void
    {
        Storage::fake('local');

        $owner = $this->createMerchantUser('up@example.com');
        $store = $this->createMemberStore($owner, 'Up Store', Store::ROLE_OWNER);

        $file = UploadedFile::fake()->createWithContent('keep.csv', "Title,SKU\nX,Y\n");

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file])
            ->assertRedirect();

        $import = ProductImport::query()->firstOrFail();
        Storage::disk('local')->assertExists($import->stored_path);
        $this->assertSame(ProductImport::STATUS_PARSED, $import->status);
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
