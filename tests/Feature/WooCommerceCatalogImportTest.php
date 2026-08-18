<?php

namespace Tests\Feature;

use App\Catalog\ProductImportField;
use App\Jobs\ProcessProductImageJob;
use App\Models\InventoryLevel;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\DefaultLocationService;
use App\Services\Inventory\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WooCommerceCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_woocommerce_csv_is_detected_and_requires_location_and_stock_mode(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('woo-detect@example.com', 'Woo Detect Store');
        $file = UploadedFile::fake()->createWithContent('woo.csv', $this->wooCsv([
            $this->wooRow(['ID' => '1', 'Type' => 'simple', 'SKU' => 'MUG-1', 'Name' => 'Blue Mug', 'Regular price' => '12', 'Stock' => '4']),
        ]));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file])
            ->assertRedirect();

        $import = ProductImport::query()->firstOrFail();
        $this->assertSame('woocommerce', $import->import_state['source_preset'] ?? null);
        $this->assertSame(ProductImport::STATUS_PREVIEWED, $import->status);
        $this->assertTrue((bool) ($import->preview_summary['woocommerce'] ?? false));
        $this->assertSame(1, (int) ($import->preview_summary['woocommerce_summary']['simple_rows'] ?? 0));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]))
            ->assertSessionHasErrors('location_id');
    }

    public function test_imports_simple_variable_sale_price_images_categories_and_out_of_stock(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Http::fake([
            'https://cdn.example.test/mug.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('x', 50), 200, ['Content-Type' => 'image/png']),
            'https://cdn.example.test/mug-2.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('y', 50), 200, ['Content-Type' => 'image/png']),
        ]);

        [$owner, $store] = $this->ownerStore('woo-full@example.com', 'Woo Full Store');
        $warehouse = $this->extraLocation($store, 'Overflow warehouse');

        $csv = $this->wooCsv([
            $this->wooRow([
                'ID' => '101',
                'Type' => 'simple',
                'SKU' => 'MUG-1',
                'Name' => 'Blue Mug',
                'Published' => '1',
                'Regular price' => '20.00',
                'Sale price' => '12.00',
                'Stock' => '0',
                'In stock?' => '0',
                'Categories' => 'Drinkware|Kitchen',
                'Tags' => 'Sale|Ceramic',
                'Images' => 'https://cdn.example.test/mug.png, https://cdn.example.test/mug-2.png',
                'Slug' => 'blue-mug',
            ]),
            $this->wooRow([
                'ID' => '200',
                'Type' => 'variable',
                'SKU' => 'SHIRT',
                'Name' => 'Classic Shirt',
                'Published' => '1',
                'Regular price' => '30',
                'Stock' => '',
                'Attribute 1 name' => 'Color',
                'Attribute 1 value(s)' => 'Red, Blue',
                'Attribute 4 name' => 'Material',
                'Attribute 4 value(s)' => 'Cotton',
                'Slug' => 'classic-shirt',
            ]),
            $this->wooRow([
                'ID' => '201',
                'Type' => 'variation',
                'SKU' => 'SHIRT-RED',
                'Name' => 'Classic Shirt',
                'Regular price' => '30',
                'Stock' => '5',
                'Parent' => 'id:200',
                'Attribute 1 name' => 'Color',
                'Attribute 1 value(s)' => 'Red',
            ]),
            $this->wooRow([
                'ID' => '202',
                'Type' => 'variation',
                'SKU' => 'SHIRT-BLUE',
                'Name' => 'Classic Shirt',
                'Regular price' => '32',
                'Stock' => '7',
                'Parent' => 'id:200',
                'Attribute 1 name' => 'Color',
                'Attribute 1 value(s)' => 'Blue',
            ]),
        ]);

        $import = $this->uploadAndImport($owner, $store, $csv, $warehouse->id, 'replace');
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $this->assertSame(0, (int) ($import->result_summary['failed'] ?? -1));
        $this->assertNotEmpty($import->preview_summary['woocommerce_summary']['extra_option_groups'] ?? []);

        $mug = Product::query()->where('store_id', $store->id)->where('sku', 'MUG-1')->firstOrFail();
        $this->assertSame('woocommerce', $mug->source_system);
        $this->assertSame('101', $mug->source_product_id);
        $this->assertSame('blue-mug', $mug->slug);
        $this->assertEqualsWithDelta(12.0, (float) $mug->base_price, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) ($mug->meta['catalog']['compare_at_price'] ?? 0), 0.01);
        $this->assertTrue($mug->categories()->where('name', 'Drinkware')->exists());
        $this->assertTrue($mug->tags()->where('name', 'Sale')->exists());
        $mugVariant = $mug->variants()->firstOrFail();
        $this->assertSame(0, (int) $mugVariant->stock);
        $this->assertSame(0, (int) InventoryLevel::query()
            ->where('location_id', $warehouse->id)
            ->whereHas('inventoryItem', fn ($query) => $query->where('variant_id', $mugVariant->id))
            ->value('available'));

        $shirt = Product::query()->where('store_id', $store->id)->where('sku', 'SHIRT')->firstOrFail();
        $this->assertSame('200', $shirt->source_product_id);
        $this->assertSame(2, $shirt->variants()->count());
        $this->assertSame('201', $shirt->variants()->where('sku', 'SHIRT-RED')->value('source_variation_id'));
        $this->assertSame(5, (int) InventoryLevel::query()
            ->where('location_id', $warehouse->id)
            ->whereHas('inventoryItem', fn ($query) => $query->where('variant_id', $shirt->variants()->where('sku', 'SHIRT-RED')->value('id')))
            ->value('available'));

        $this->assertDatabaseHas('product_url_redirects', [
            'store_id' => $store->id,
            'product_id' => $mug->id,
            'source_path' => '/product/blue-mug/',
            'destination_slug' => 'blue-mug',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $mug))
            ->assertOk()
            ->assertSee('Imported from WooCommerce')
            ->assertSee('101')
            ->assertSee('/product/blue-mug/');
    }

    public function test_missing_duplicate_parent_and_unsupported_rows_are_reported_not_silently_imported(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('woo-errors@example.com', 'Woo Error Store');
        $location = app(DefaultLocationService::class)->ensureForStore($store);

        $csv = $this->wooCsv([
            $this->wooRow(['ID' => '1', 'Type' => 'simple', 'SKU' => 'DUP-1', 'Name' => 'One', 'Regular price' => '5', 'Stock' => '1']),
            $this->wooRow(['ID' => '2', 'Type' => 'simple', 'SKU' => 'DUP-1', 'Name' => 'Two', 'Regular price' => '6', 'Stock' => '1']),
            $this->wooRow(['ID' => '3', 'Type' => 'simple', 'SKU' => '', 'Name' => 'No Sku', 'Regular price' => '7', 'Stock' => '2']),
            $this->wooRow(['ID' => '4', 'Type' => 'grouped', 'SKU' => 'GROUP-1', 'Name' => 'Bundle', 'Regular price' => '9', 'Stock' => '1']),
            $this->wooRow(['ID' => '5', 'Type' => 'variation', 'SKU' => 'ORPHAN', 'Name' => 'Orphan', 'Regular price' => '8', 'Parent' => 'id:999', 'Stock' => '1', 'Attribute 1 name' => 'Color', 'Attribute 1 value(s)' => 'Red']),
        ]);

        $import = $this->uploadAndImport($owner, $store, $csv, $location->id, 'replace');
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $this->assertGreaterThanOrEqual(3, (int) ($import->result_summary['failed'] ?? 0));
        $this->assertTrue((bool) ($import->result_summary['partial_success'] ?? false));

        $this->assertDatabaseHas('products', ['store_id' => $store->id, 'sku' => 'DUP-1']);
        $this->assertDatabaseHas('products', ['store_id' => $store->id, 'sku' => 'woo-3']);
        $this->assertDatabaseMissing('products', ['store_id' => $store->id, 'sku' => 'GROUP-1']);
        $this->assertDatabaseMissing('products', ['store_id' => $store->id, 'sku' => 'ORPHAN']);
    }

    public function test_reimport_is_idempotent_and_preserve_keeps_existing_stock(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('woo-idem@example.com', 'Woo Idem Store');
        $location = app(DefaultLocationService::class)->ensureForStore($store);
        $csv = $this->wooCsv([
            $this->wooRow(['ID' => '77', 'Type' => 'simple', 'SKU' => 'KEEP-1', 'Name' => 'Keeper', 'Regular price' => '15', 'Stock' => '4', 'Slug' => 'keeper']),
        ]);

        $first = $this->uploadAndImport($owner, $store, $csv, $location->id, 'replace');
        $this->assertSame(ProductImport::STATUS_COMPLETED, $first->status);
        $product = Product::query()->where('store_id', $store->id)->where('source_product_id', '77')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $this->assertSame(4, (int) $variant->fresh()->stock);
        $productId = $product->id;

        $variant->update(['stock' => 99]);
        app(InventoryAdjustmentService::class)->setVariantAvailable(
            $variant->fresh(),
            99,
            'Manual test stock',
            null,
            ['source' => 'test']
        );

        $updatedCsv = $this->wooCsv([
            $this->wooRow(['ID' => '77', 'Type' => 'simple', 'SKU' => 'KEEP-1-NEW', 'Name' => 'Keeper Updated', 'Regular price' => '18', 'Stock' => '1', 'Slug' => 'keeper']),
        ]);
        $second = $this->uploadAndImport($owner, $store, $updatedCsv, $location->id, 'preserve');
        $this->assertSame(ProductImport::STATUS_COMPLETED, $second->status);

        $product->refresh();
        $this->assertSame($productId, $product->id);
        $this->assertSame('Keeper Updated', $product->name);
        $this->assertSame('KEEP-1-NEW', $product->sku);
        $this->assertSame(99, (int) $product->variants()->firstOrFail()->stock);
        $this->assertSame(1, Product::query()->where('store_id', $store->id)->count());
    }

    public function test_imperial_woocommerce_export_headers_bind_brand_barcode_dimensions_and_variation_photos(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['product_import.async_image_processing' => true]);
        Queue::fake([ProcessProductImageJob::class]);
        Http::fake([
            'https://cdn.example.test/parent-a.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('a', 50), 200, ['Content-Type' => 'image/png']),
            'https://cdn.example.test/parent-b.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('b', 50), 200, ['Content-Type' => 'image/png']),
            'https://cdn.example.test/var-regular.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('c', 50), 200, ['Content-Type' => 'image/png']),
            'https://cdn.example.test/var-eval.png' => Http::response("\x89PNG\r\n\x1a\n".str_repeat('d', 50), 200, ['Content-Type' => 'image/png']),
        ]);

        [$owner, $store] = $this->ownerStore('woo-imperial@example.com', 'Woo Imperial Store');
        $location = app(DefaultLocationService::class)->ensureForStore($store);

        $csv = $this->realExportCsv([
            $this->realExportRow([
                'ID' => '12156',
                'Type' => 'variable',
                'Name' => 'Got Pain Capsules',
                'GTIN, UPC, EAN, or ISBN' => '012345678905',
                'Weight (lbs)' => '0.45',
                'Length (in)' => '4',
                'Width (in)' => '2',
                'Height (in)' => '3',
                'Brands' => 'ECS Therapy',
                'Categories' => 'Uncategorized',
                'Images' => 'https://cdn.example.test/parent-a.png, https://cdn.example.test/parent-b.png',
                'Attribute 1 name' => 'Product Type',
                'Attribute 1 value(s)' => 'Regular, 7-Day Evaluation',
                'Meta: _kad_post_layout' => 'default',
            ]),
            $this->realExportRow([
                'ID' => '12446',
                'Type' => 'variation',
                'Name' => 'Got Pain Capsules - Regular',
                'Parent' => 'id:12156',
                'Regular price' => '35',
                'Images' => 'https://cdn.example.test/var-regular.png',
                'Attribute 1 name' => 'Product Type',
                'Attribute 1 value(s)' => 'Regular',
            ]),
            $this->realExportRow([
                'ID' => '12447',
                'Type' => 'variation',
                'Name' => 'Got Pain Capsules - 7-Day Evaluation',
                'Parent' => 'id:12156',
                'Regular price' => '15',
                'Images' => 'https://cdn.example.test/var-eval.png',
                'Attribute 1 name' => 'Product Type',
                'Attribute 1 value(s)' => '7-Day Evaluation',
            ]),
        ]);

        $file = UploadedFile::fake()->createWithContent('woo-imperial.csv', $csv);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file])
            ->assertRedirect();

        $import = ProductImport::query()->latest('id')->firstOrFail();
        $mapping = $import->column_mapping ?? [];
        $this->assertSame('woocommerce', $import->import_state['source_preset'] ?? null);
        $this->assertSame('Weight (lbs)', $mapping[ProductImportField::WEIGHT] ?? null);
        $this->assertSame('Length (in)', $mapping[ProductImportField::LENGTH] ?? null);
        $this->assertSame('Width (in)', $mapping[ProductImportField::WIDTH] ?? null);
        $this->assertSame('Height (in)', $mapping[ProductImportField::HEIGHT] ?? null);
        $this->assertSame('Brands', $mapping[ProductImportField::BRAND] ?? null);
        $this->assertSame('GTIN, UPC, EAN, or ISBN', $mapping[ProductImportField::BARCODE] ?? null);
        $this->assertSame(3, (int) ($import->preview_summary['woocommerce_summary']['empty_stock_rows'] ?? 0));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.preview', ['productImportId' => $import->id]))
            ->assertOk()
            ->assertSee('blank Stock quantity')
            ->assertSee('not treated as a quantity');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]), [
                'location_id' => $location->id,
                'stock_mode' => 'replace',
                'source_site' => 'https://shop.example.test',
            ]);

        $import->refresh();
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->status);
        $this->assertSame(0, (int) ($import->result_summary['failed'] ?? -1));

        $product = Product::query()
            ->where('store_id', $store->id)
            ->where('source_product_id', '12156')
            ->with(['brand', 'variants', 'images'])
            ->firstOrFail();

        $this->assertSame('woo-12156', $product->sku);
        $this->assertSame('Got Pain Capsules', $product->name);
        $this->assertSame('ECS Therapy', $product->brand?->name);
        $this->assertSame('012345678905', $product->meta['catalog']['barcode'] ?? null);
        $this->assertSame('0.45', (string) ($product->meta['catalog']['weight'] ?? ''));
        $this->assertSame('lb', $product->meta['catalog']['weight_unit'] ?? null);
        $this->assertSame('4', (string) ($product->meta['catalog']['length'] ?? ''));
        $this->assertSame('2', (string) ($product->meta['catalog']['width'] ?? ''));
        $this->assertSame('3', (string) ($product->meta['catalog']['height'] ?? ''));
        $this->assertSame('in', $product->meta['catalog']['dimension_unit'] ?? null);
        $this->assertSame('1', (string) ($product->meta['custom_fields']['in_stock'] ?? ''));
        $this->assertSame('default', $product->meta['import_extra']['Meta: _kad_post_layout'] ?? $product->meta['import_extra']['_kad_post_layout'] ?? null);

        $this->assertSame(2, $product->variants()->count());
        $regular = $product->variants()->where('source_variation_id', '12446')->firstOrFail();
        $eval = $product->variants()->where('source_variation_id', '12447')->firstOrFail();
        $this->assertSame(0, (int) $regular->stock);
        $this->assertEqualsWithDelta(35.0, (float) $regular->price, 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $eval->price, 0.01);

        $galleryUrls = $product->images->whereNull('product_variant_id')->pluck('source_url')->filter()->values();
        $this->assertTrue($galleryUrls->contains('https://cdn.example.test/parent-a.png'));
        $this->assertTrue($galleryUrls->contains('https://cdn.example.test/parent-b.png'));
        $this->assertSame(
            'https://cdn.example.test/var-regular.png',
            $product->images->firstWhere('product_variant_id', $regular->id)?->source_url
        );
        $this->assertSame(
            'https://cdn.example.test/var-eval.png',
            $product->images->firstWhere('product_variant_id', $eval->id)?->source_url
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('ECS Therapy')
            ->assertSee('012345678905')
            ->assertSee('0.45')
            ->assertSee('lb');
    }

    public function test_same_woocommerce_ids_from_two_source_sites_create_distinct_identities(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('woo-sites@example.com', 'Woo Source Sites');
        $location = app(DefaultLocationService::class)->ensureForStore($store);

        $siteA = $this->wooCsv([
            $this->wooRow(['ID' => '200', 'Type' => 'variable', 'SKU' => 'SITE-A-P', 'Name' => 'Site A Shirt', 'Regular price' => '20', 'Stock' => '0', 'Attribute 1 name' => 'Color', 'Attribute 1 value(s)' => 'Red']),
            $this->wooRow(['ID' => '201', 'Type' => 'variation', 'SKU' => 'SITE-A-RED', 'Name' => 'Site A Shirt', 'Regular price' => '20', 'Parent' => 'id:200', 'Stock' => '2', 'Attribute 1 name' => 'Color', 'Attribute 1 value(s)' => 'Red']),
        ]);
        $siteB = $this->wooCsv([
            $this->wooRow(['ID' => '200', 'Type' => 'variable', 'SKU' => 'SITE-B-P', 'Name' => 'Site B Shirt', 'Regular price' => '25', 'Stock' => '0', 'Attribute 1 name' => 'Color', 'Attribute 1 value(s)' => 'Blue']),
            $this->wooRow(['ID' => '201', 'Type' => 'variation', 'SKU' => 'SITE-B-BLUE', 'Name' => 'Site B Shirt', 'Regular price' => '25', 'Parent' => 'id:200', 'Stock' => '3', 'Attribute 1 name' => 'Color', 'Attribute 1 value(s)' => 'Blue']),
        ]);

        $importA = $this->uploadAndImport($owner, $store, $siteA, $location->id, 'replace', 'https://site-a.example.com');
        $importB = $this->uploadAndImport($owner, $store, $siteB, $location->id, 'replace', 'https://site-b.example.com');
        $this->assertSame(0, (int) ($importA->result_summary['failed'] ?? -1), json_encode([
            'status' => $importA->status,
            'failure' => $importA->failure_message,
            'state' => $importA->import_state,
            'summary' => $importA->result_summary,
        ]));
        $this->assertSame(0, (int) ($importB->result_summary['failed'] ?? -1), json_encode($importB->result_summary));

        $products = Product::query()
            ->where('store_id', $store->id)
            ->where('source_system', 'woocommerce')
            ->where('source_product_id', '200')
            ->orderBy('source_site')
            ->get();
        $this->assertCount(2, $products);
        $this->assertSame(['https://site-a.example.com', 'https://site-b.example.com'], $products->pluck('source_site')->all());

        $variants = $products->flatMap(fn (Product $product) => $product->variants()->where('source_variation_id', '201')->get());
        $this->assertCount(2, $variants);
        $this->assertSame(
            ['https://site-a.example.com', 'https://site-b.example.com'],
            $variants->pluck('source_site')->sort()->values()->all()
        );
    }

    public function test_manual_sku_collision_requires_explicit_link_approval(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('woo-collision@example.com', 'Woo Collision Store');
        $location = app(DefaultLocationService::class)->ensureForStore($store);
        $manual = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Manual Product',
            'slug' => 'manual-product',
            'base_price' => 10,
            'sku' => 'MANUAL-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $manual->variants()->create([
            'store_id' => $store->id,
            'sku' => 'MANUAL-1',
            'price' => 10,
            'stock' => 4,
        ]);
        $csv = $this->wooCsv([
            $this->wooRow(['ID' => '900', 'Type' => 'simple', 'SKU' => 'MANUAL-1', 'Name' => 'Woo Product', 'Regular price' => '15', 'Stock' => '6']),
        ]);

        $blocked = $this->uploadAndImport($owner, $store, $csv, $location->id, 'replace', 'https://source.example.com');
        $this->assertSame(1, (int) ($blocked->result_summary['failed'] ?? 0));
        $this->assertSame('Manual Product', $manual->fresh()->name);
        $this->assertNull($manual->fresh()->source_system);

        $approved = $this->uploadAndImport($owner, $store, $csv, $location->id, 'replace', 'https://source.example.com', true);
        $this->assertSame(0, (int) ($approved->result_summary['failed'] ?? -1));
        $this->assertSame('Woo Product', $manual->fresh()->name);
        $this->assertSame('woocommerce', $manual->fresh()->source_system);
        $this->assertSame('https://source.example.com', $manual->fresh()->source_site);
        $this->assertSame('900', $manual->fresh()->source_product_id);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $email, string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => ['connected_website_url' => 'https://shop.example.test'],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function extraLocation(Store $store, string $name): Location
    {
        app(DefaultLocationService::class)->ensureForStore($store);

        return Location::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'type' => Location::TYPE_WAREHOUSE,
            'is_default' => false,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
    }

    private function uploadAndImport(
        User $owner,
        Store $store,
        string $csv,
        int $locationId,
        string $stockMode,
        string $sourceSite = 'https://shop.example.test',
        bool $approveExistingSkuLinks = false,
    ): ProductImport {
        $file = UploadedFile::fake()->createWithContent('woo.csv', $csv);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.store'), ['file' => $file]);

        $import = ProductImport::query()->latest('id')->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.import.confirm', ['productImportId' => $import->id]), [
                'location_id' => $locationId,
                'stock_mode' => $stockMode,
                'source_site' => $sourceSite,
                'approve_existing_sku_links' => $approveExistingSkuLinks ? '1' : '0',
            ]);

        return $import->fresh();
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function wooCsv(array $rows): string
    {
        $headers = array_keys($this->wooRow([]));
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($fh, $line);
        }
        rewind($fh);

        return stream_get_contents($fh) ?: '';
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function wooRow(array $overrides): array
    {
        return array_merge([
            'ID' => '',
            'Type' => 'simple',
            'SKU' => '',
            'Name' => '',
            'Published' => '1',
            'Is featured?' => '0',
            'Visibility in catalog' => 'visible',
            'Short description' => '',
            'Description' => '',
            'Tax status' => 'taxable',
            'Tax class' => '',
            'In stock?' => '1',
            'Stock' => '',
            'Low stock amount' => '',
            'Backorders allowed?' => '0',
            'Sold individually?' => '0',
            'Weight (kg)' => '',
            'Length (cm)' => '',
            'Width (cm)' => '',
            'Height (cm)' => '',
            'Sale price' => '',
            'Regular price' => '',
            'Categories' => '',
            'Tags' => '',
            'Shipping class' => '',
            'Images' => '',
            'Parent' => '',
            'Attribute 1 name' => '',
            'Attribute 1 value(s)' => '',
            'Attribute 2 name' => '',
            'Attribute 2 value(s)' => '',
            'Attribute 3 name' => '',
            'Attribute 3 value(s)' => '',
            'Attribute 4 name' => '',
            'Attribute 4 value(s)' => '',
            'Slug' => '',
        ], $overrides);
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function realExportCsv(array $rows): string
    {
        $headers = array_keys($this->realExportRow([]));
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($fh, $line);
        }
        rewind($fh);

        return stream_get_contents($fh) ?: '';
    }

    /**
     * Headers match the merchant WooCommerce product export (imperial units, Brands, combined GTIN).
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function realExportRow(array $overrides): array
    {
        return array_merge([
            'ID' => '',
            'Type' => 'simple',
            'SKU' => '',
            'GTIN, UPC, EAN, or ISBN' => '',
            'Name' => '',
            'Published' => '1',
            'Is featured?' => '0',
            'Visibility in catalog' => 'visible',
            'Short description' => '',
            'Description' => '',
            'Date sale price starts' => '',
            'Date sale price ends' => '',
            'Tax status' => 'taxable',
            'Tax class' => '',
            'In stock?' => '1',
            'Stock' => '',
            'Low stock amount' => '',
            'Backorders allowed?' => '0',
            'Sold individually?' => '0',
            'Weight (lbs)' => '',
            'Length (in)' => '',
            'Width (in)' => '',
            'Height (in)' => '',
            'Allow customer reviews?' => '1',
            'Purchase note' => '',
            'Sale price' => '',
            'Regular price' => '',
            'Categories' => '',
            'Tags' => '',
            'Shipping class' => '',
            'Images' => '',
            'Download limit' => '',
            'Download expiry days' => '',
            'Parent' => '',
            'Grouped products' => '',
            'Upsells' => '',
            'Cross-sells' => '',
            'External URL' => '',
            'Button text' => '',
            'Position' => '0',
            'Brands' => '',
            'Attribute 1 name' => '',
            'Attribute 1 value(s)' => '',
            'Attribute 1 visible' => '1',
            'Attribute 1 global' => '0',
            'Meta: _kad_post_transparent' => '',
            'Meta: _kad_post_layout' => '',
            'Meta: _kad_post_content_style' => '',
            'Meta: _kad_post_vertical_padding' => '',
            'Meta: ekit_post_views_count' => '',
            'Meta: _dcw_sold_individually_variable' => '',
            'Meta: _dcw_sold_individually_variation' => '',
        ], $overrides);
    }
}
