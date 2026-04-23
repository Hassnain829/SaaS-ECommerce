<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportDay13Test extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_import_history_for_current_store(): void
    {
        Storage::fake('local');

        $owner = $this->createMerchantUser('owner-hist@example.com');
        $store = $this->createMemberStore($owner, 'Hist Store A', Store::ROLE_OWNER);

        $file = UploadedFile::fake()->createWithContent('alpha.csv', "Title,SKU\nA,B1\n");
        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.history'))
            ->assertOk()
            ->assertSeeText('Import history')
            ->assertSeeText('alpha.csv');
    }

    public function test_manager_can_view_import_history(): void
    {
        Storage::fake('local');

        $owner = $this->createMerchantUser('own-mgr@example.com');
        $manager = $this->createMerchantUser('mgr-hist@example.com');
        $store = $this->createMemberStore($owner, 'Mgr Hist', Store::ROLE_OWNER);
        $store->members()->syncWithoutDetaching([$manager->id => ['role' => Store::ROLE_MANAGER]]);

        $file = UploadedFile::fake()->createWithContent('mgr.csv', "Title,SKU\nX,Y2\n");
        $this->actingAs($manager)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $this->actingAs($manager)->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.history'))
            ->assertOk()
            ->assertSeeText('mgr.csv');
    }

    public function test_staff_cannot_access_import_history_report_or_retry(): void
    {
        Storage::fake('local');

        $owner = $this->createMerchantUser('own-st@example.com');
        $staff = $this->createMerchantUser('st-hist@example.com');
        $store = $this->createMemberStore($owner, 'Staff Hist', Store::ROLE_OWNER);
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);

        $file = UploadedFile::fake()->createWithContent('imp.csv', "Title,SKU\nA,S1\n");
        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);
        $import = ProductImport::query()->firstOrFail();

        $this->actingAs($staff)->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.history'))
            ->assertForbidden();

        $this->actingAs($staff)->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.report', ['productImportId' => $import->id]))
            ->assertForbidden();

        $this->actingAs($staff)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.retry-failed', ['productImportId' => $import->id]))
            ->assertForbidden();
    }

    public function test_import_report_is_not_visible_from_a_different_current_store(): void
    {
        $merchant = $this->createMerchantUser('cross@example.com');
        $alpha = $this->createMemberStore($merchant, 'Alpha X', Store::ROLE_OWNER);
        $beta = $this->createMemberStore($merchant, 'Beta X', Store::ROLE_OWNER);

        $import = ProductImport::query()->create([
            'store_id' => $alpha->id,
            'created_by' => $merchant->id,
            'original_filename' => 'only-alpha.csv',
            'stored_disk' => 'local',
            'stored_path' => 'product-imports/'.$alpha->id.'/x.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_COMPLETED,
            'headers' => ['Title', 'SKU'],
            'column_mapping' => ['product_name' => 'Title', 'sku' => 'SKU'],
            'preview_summary' => [],
            'result_summary' => [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'failures' => [],
            ],
            'total_rows' => 1,
            'last_processed_row' => 1,
        ]);

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $beta->id])
            ->get(route('products.import.report', ['productImportId' => $import->id]))
            ->assertNotFound();
    }

    public function test_import_history_only_lists_imports_for_current_store(): void
    {
        Storage::fake('local');

        $merchant = $this->createMerchantUser('two-st@example.com');
        $alpha = $this->createMemberStore($merchant, 'Alpha List', Store::ROLE_OWNER);
        $beta = $this->createMemberStore($merchant, 'Beta List', Store::ROLE_OWNER);

        $f1 = UploadedFile::fake()->createWithContent('in-alpha.csv', "Title,SKU\nA,B\n");
        $this->actingAs($merchant)->withSession(['current_store_id' => $alpha->id])
            ->post(route('products.import.store'), ['file' => $f1]);

        $f2 = UploadedFile::fake()->createWithContent('in-beta.csv', "Title,SKU\nC,D\n");
        $this->actingAs($merchant)->withSession(['current_store_id' => $beta->id])
            ->post(route('products.import.store'), ['file' => $f2]);

        $this->actingAs($merchant)->withSession(['current_store_id' => $alpha->id])
            ->get(route('products.import.history'))
            ->assertOk()
            ->assertSeeText('in-alpha.csv')
            ->assertDontSeeText('in-beta.csv');
    }

    public function test_import_report_shows_row_level_failures_in_plain_language(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('rep@example.com');
        $store = $this->createMemberStore($owner, 'Rep Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Stock\nGood,G-OK,10,1\nBad,G-BAD,notprice,1\n";
        $file = UploadedFile::fake()->createWithContent('mixed.csv', $csv);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);
        $import = ProductImport::query()->firstOrFail();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'product_name' => 'Title',
                    'sku' => 'SKU',
                    'base_price' => 'Price',
                    'stock' => 'Stock',
                ],
            ]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $summary = $import->result_summary ?? [];
        $this->assertNotEmpty($summary['partial_success'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($summary['failed'] ?? 0));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.report', ['productImportId' => $import->id]))
            ->assertOk()
            ->assertSeeText('Rows that need attention')
            ->assertSeeText('The price on this row is not a valid number');
    }

    public function test_retry_failed_rows_can_succeed_after_row_payload_is_fixed(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('retry@example.com');
        $store = $this->createMemberStore($owner, 'Retry Store', Store::ROLE_OWNER);

        $csv = "Title,SKU,Price,Stock\nGood,G-OK,10,1\n,G-RETRY,10,1\n";
        $file = UploadedFile::fake()->createWithContent('retry.csv', $csv);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);
        $import = ProductImport::query()->firstOrFail();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'product_name' => 'Title',
                    'sku' => 'SKU',
                    'base_price' => 'Price',
                    'stock' => 'Stock',
                ],
            ]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertGreaterThanOrEqual(1, (int) ($import->result_summary['failed'] ?? 0));

        $row = ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->where('row_number', 2)
            ->first();
        $this->assertNotNull($row);

        $payload = $row->payload;
        $this->assertIsArray($payload);
        $payload['cells'] = ['Retry Name', 'G-RETRY', '10', '1'];
        $row->update(['payload' => $payload]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.retry-failed', ['productImportId' => $import->id]))
            ->assertRedirect(route('products.import.report', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(0, (int) ProductImportRow::query()
            ->where('product_import_id', $import->id)
            ->where('status', ProductImportRow::STATUS_FAILED)
            ->count());

        $p = Product::query()->where('store_id', $store->id)->where('sku', 'G-RETRY')->first();
        $this->assertNotNull($p);
        $this->assertSame('Retry Name', $p->name);
    }

    public function test_completed_import_result_page_avoids_merchant_facing_queued_label(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('res-copy@example.com');
        $store = $this->createMemberStore($owner, 'Res Store', Store::ROLE_OWNER);

        $csv = "Title,SKU\nOnly,O1\n";
        $file = UploadedFile::fake()->createWithContent('one.csv', $csv);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);
        $import = ProductImport::query()->firstOrFail();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'product_name' => 'Title',
                    'sku' => 'SKU',
                ],
            ]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $html = $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.result', ['productImportId' => $import->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsStringIgnoringCase('Queued', $html);
        $this->assertStringNotContainsString('queue:work', $html);
    }

    public function test_import_history_status_filter_limits_results(): void
    {
        Storage::fake('local');

        $owner = $this->createMerchantUser('filt@example.com');
        $store = $this->createMemberStore($owner, 'Filt Store', Store::ROLE_OWNER);

        ProductImport::query()->create([
            'store_id' => $store->id,
            'created_by' => $owner->id,
            'original_filename' => 'done.csv',
            'stored_disk' => 'local',
            'stored_path' => 'x/done.csv',
            'mime_type' => 'text/csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_COMPLETED,
            'headers' => [],
            'result_summary' => [],
        ]);

        $f = UploadedFile::fake()->createWithContent('parsed.csv', "Title,SKU\nA,B\n");
        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $f]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.history', ['status' => ProductImport::STATUS_COMPLETED]))
            ->assertOk()
            ->assertSeeText('done.csv')
            ->assertDontSeeText('parsed.csv');
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
