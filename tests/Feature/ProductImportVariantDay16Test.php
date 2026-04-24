<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Catalog\ProductImportPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImportVariantDay16Test extends TestCase
{
    use RefreshDatabase;

    public function test_variant_import_groups_rows_into_one_product_with_options_and_variants(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('var-day16@example.com');
        $store = $this->createMemberStore($owner, 'Variant Import Store', Store::ROLE_OWNER);

        $csv = <<<'CSV'
parent_sku,product_name,opt1n,opt1v,opt2n,opt2v,vsku,vprice,vstock,vcompare
P-SHIRT-1,Classic Tee,Color,Red,Size,S,SKU-RS,29.99,5,39.99
P-SHIRT-1,Classic Tee,Color,Red,Size,M,SKU-RM,29.99,8,39.99
P-SHIRT-1,Classic Tee,Color,Blue,Size,S,SKU-BS,31.00,3,
P-SHIRT-1,Classic Tee,Color,Blue,Size,M,SKU-BM,31.00,4,
CSV;

        $file = UploadedFile::fake()->createWithContent('variants.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'parent_sku' => 'parent_sku',
                    'product_name' => 'product_name',
                    'option_1_name' => 'opt1n',
                    'option_1_value' => 'opt1v',
                    'option_2_name' => 'opt2n',
                    'option_2_value' => 'opt2v',
                    'variant_sku' => 'vsku',
                    'variant_price' => 'vprice',
                    'variant_stock' => 'vstock',
                    'variant_compare_at_price' => 'vcompare',
                ],
            ])->assertRedirect(route('products.import.preview', ['productImportId' => $import->id]));

        $import->refresh();
        $preview = $import->preview_summary;
        $this->assertTrue($preview['variant_import'] ?? false);
        $this->assertSame(1, $preview['variant_summary']['unique_products_detected'] ?? 0);
        $this->assertSame(4, $preview['variant_summary']['variant_line_rows'] ?? 0);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertSame(\App\Models\ProductImport::STATUS_COMPLETED, $import->status);

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'P-SHIRT-1')->first();
        $this->assertNotNull($product);
        $this->assertCount(2, $product->variationTypes);
        $this->assertSame(4, $product->variants()->count());

        $v = ProductVariant::query()->where('sku', 'SKU-RM')->first();
        $this->assertNotNull($v);
        $this->assertSame(8, (int) $v->stock);
        $this->assertSame('29.99', (string) $v->price);
        $this->assertSame('39.99', (string) $v->compare_at_price);

        $this->assertSame(4, (int) \App\Models\StockMovement::query()
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->where('movement_type', 'import')
            ->count());
    }

    public function test_duplicate_variant_combination_marks_row_failed(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('var-dup@example.com');
        $store = $this->createMemberStore($owner, 'Dup Store', Store::ROLE_OWNER);

        $csv = <<<'CSV'
parent_sku,product_name,opt1n,opt1v,vsku,vprice,vstock
P-DUP,Tee,Color,Red,VR1,10,1
P-DUP,Tee,Color,Red,VR2,11,2
CSV;

        $file = UploadedFile::fake()->createWithContent('dup.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'parent_sku' => 'parent_sku',
                    'product_name' => 'product_name',
                    'option_1_name' => 'opt1n',
                    'option_1_value' => 'opt1v',
                    'variant_sku' => 'vsku',
                    'variant_price' => 'vprice',
                    'variant_stock' => 'vstock',
                ],
            ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $import->refresh();
        $this->assertGreaterThanOrEqual(1, (int) ($import->result_summary['failed'] ?? 0));
        $this->assertSame(1, Product::query()->where('store_id', $store->id)->where('sku', 'P-DUP')->count());
    }

    public function test_variant_custom_fields_and_simple_import_still_work(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('var-cf@example.com');
        $store = $this->createMemberStore($owner, 'CF Store', Store::ROLE_OWNER);

        $csv = "parent_sku,product_name,o1n,o1v,vsku,vprice,vstock,vcf\nP-CF,Hat,Size,S,VS1,9,2,x1\n";
        $file = UploadedFile::fake()->createWithContent('cf.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'parent_sku' => 'parent_sku',
                    'product_name' => 'product_name',
                    'option_1_name' => 'o1n',
                    'option_1_value' => 'o1v',
                    'variant_sku' => 'vsku',
                    'variant_price' => 'vprice',
                    'variant_stock' => 'vstock',
                ],
                'custom_field_mappings' => [
                    ['source' => 'vcf', 'key' => 'vendor_note', 'scope' => 'variant'],
                ],
            ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $v = ProductVariant::query()->where('sku', 'VS1')->first();
        $this->assertNotNull($v);
        $this->assertSame('x1', $v->meta['custom_fields']['vendor_note'] ?? null);

        // Simple one-row import (no option columns)
        $csv2 = "Title,SKU,Price,Stock\nOnly One,O-1,5,1\n";
        $file2 = UploadedFile::fake()->createWithContent('simple.csv', $csv2);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file2]);
        $import2 = ProductImport::query()->latest('id')->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import2->id]), [
                'column_mapping' => [
                    'product_name' => 'Title',
                    'sku' => 'SKU',
                    'base_price' => 'Price',
                    'stock' => 'Stock',
                ],
            ]);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import2->id]));
        $this->assertTrue(Product::query()->where('store_id', $store->id)->where('sku', 'O-1')->exists());
    }

    public function test_variant_image_url_links_to_variant_when_sync_processing(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['product_import.async_image_processing' => false]);

        Http::fake([
            'https://img.example/red.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('x', 40), 200, ['Content-Type' => 'image/png']),
        ]);

        $owner = $this->createMerchantUser('var-img@example.com');
        $store = $this->createMemberStore($owner, 'Img Var Store', Store::ROLE_OWNER);

        $csv = "parent_sku,product_name,o1n,o1v,vsku,vprice,vstock,vimg\nP-IMG,Mug,Color,R,VM1,5,1,https://img.example/red.png\n";
        $file = UploadedFile::fake()->createWithContent('vim.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'parent_sku' => 'parent_sku',
                    'product_name' => 'product_name',
                    'option_1_name' => 'o1n',
                    'option_1_value' => 'o1v',
                    'variant_sku' => 'vsku',
                    'variant_price' => 'vprice',
                    'variant_stock' => 'vstock',
                    'variant_image_url' => 'vimg',
                ],
            ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $v = ProductVariant::query()->where('sku', 'VM1')->first();
        $this->assertNotNull($v);
        $this->assertTrue(
            \App\Models\ProductImage::query()->where('product_variant_id', $v->id)->where('product_id', $v->product_id)->exists()
        );
    }

    public function test_variant_import_does_not_create_products_in_other_store(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $ownerA = $this->createMerchantUser('var-a@example.com');
        $storeA = $this->createMemberStore($ownerA, 'Store A Var', Store::ROLE_OWNER);
        $ownerB = $this->createMerchantUser('var-b@example.com');
        $storeB = $this->createMemberStore($ownerB, 'Store B Var', Store::ROLE_OWNER);

        $csv = "parent_sku,product_name,o1n,o1v,vsku,vprice,vstock\nP-X,Tee,Color,R,VX,1,1\n";
        $file = UploadedFile::fake()->createWithContent('x.csv', $csv);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('products.import.store'), ['file' => $file]);
        $import = ProductImport::query()->firstOrFail();
        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'parent_sku' => 'parent_sku',
                    'product_name' => 'product_name',
                    'option_1_name' => 'o1n',
                    'option_1_value' => 'o1v',
                    'variant_sku' => 'vsku',
                    'variant_price' => 'vprice',
                    'variant_stock' => 'vstock',
                ],
            ]);
        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $this->assertTrue(Product::query()->where('store_id', $storeA->id)->where('sku', 'P-X')->exists());
        $this->assertFalse(Product::query()->where('store_id', $storeB->id)->where('sku', 'P-X')->exists());
    }

    public function test_preview_service_surfaces_variant_summary(): void
    {
        Storage::fake('local');
        $owner = $this->createMerchantUser('var-prev@example.com');
        $store = $this->createMemberStore($owner, 'Prev Store', Store::ROLE_OWNER);

        $csv = "parent_sku,product_name,o1n,o1v,vsku,vprice,vstock\nP-1,T,o1,v1,s1,1,1\nP-1,T,o1,v2,s2,1,1\n";
        $file = UploadedFile::fake()->createWithContent('p.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);
        $import = ProductImport::query()->firstOrFail();
        $import->update([
            'column_mapping' => [
                'parent_sku' => 'parent_sku',
                'product_name' => 'product_name',
                'option_1_name' => 'o1n',
                'option_1_value' => 'o1v',
                'variant_sku' => 'vsku',
                'variant_price' => 'vprice',
                'variant_stock' => 'vstock',
            ],
        ]);

        $svc = app(ProductImportPreviewService::class);
        $out = $svc->build($import->fresh());
        $this->assertTrue($out['variant_import']);
        $this->assertSame(1, $out['variant_summary']['unique_products_detected']);
        $this->assertSame(2, $out['variant_summary']['variant_line_rows']);
    }

    public function test_product_workspace_shows_imported_variant_labels(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = $this->createMerchantUser('var-ws@example.com');
        $store = $this->createMemberStore($owner, 'WS Var Store', Store::ROLE_OWNER);

        $csv = "parent_sku,product_name,o1n,o1v,vsku,vprice,vstock\nP-WS,WS Tee,Color,Green,VG,12,3\n";
        $file = UploadedFile::fake()->createWithContent('ws.csv', $csv);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);
        $import = ProductImport::query()->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.mapping.save', ['productImportId' => $import->id]), [
                'column_mapping' => [
                    'parent_sku' => 'parent_sku',
                    'product_name' => 'product_name',
                    'option_1_name' => 'o1n',
                    'option_1_value' => 'o1v',
                    'variant_sku' => 'vsku',
                    'variant_price' => 'vprice',
                    'variant_stock' => 'vstock',
                ],
            ]);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]));

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'P-WS')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Green', false)
            ->assertSee('Color', false);
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
