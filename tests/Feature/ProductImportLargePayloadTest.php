<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImportRow;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\Catalog\ProductImportMerchantMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportLargePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_persists_compact_row_payloads_for_long_descriptions(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('big-payload@example.com');
        $store = $this->createMemberStore($owner, 'Big Payload Store', Store::ROLE_OWNER);

        $longDesc = str_repeat('Wordy text. ', 4000);
        $csv = 'Title,SKU,Price,Stock,Desc'."\n".'Big,SKU-BIG,12,3,'.$longDesc."\n";

        $file = UploadedFile::fake()->createWithContent('big.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = \App\Models\ProductImport::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'product_name' => 'Title',
                    'sku' => 'SKU',
                    'base_price' => 'Price',
                    'stock' => 'Stock',
                    'description' => 'Desc',
                ],
            ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(\App\Models\ProductImport::STATUS_COMPLETED, $import->status);

        $row = ProductImportRow::query()->where('product_import_id', $import->id)->first();
        $this->assertNotNull($row);
        $payload = $row->payload;
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('cells', $payload);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($encoded);
        $this->assertLessThan(70000, strlen($encoded), 'Stored row JSON should be bounded for DB packet safety');

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'SKU-BIG')->first();
        $this->assertNotNull($product);
        $this->assertGreaterThan(1000, strlen((string) $product->description));
    }

    public function test_failure_list_messages_are_truncated_in_result_summary(): void
    {
        $long = str_repeat('E', 5000);
        $merged = ProductImportMerchantMessages::truncateFailureList([
            ['row' => 2, 'message' => $long],
        ]);
        $this->assertLessThanOrEqual(1200, strlen($merged[0]['message']));
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
