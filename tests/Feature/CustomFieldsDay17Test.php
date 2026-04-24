<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Catalog\ProductImportProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomFieldsDay17Test extends TestCase
{
    use RefreshDatabase;

    private function merchant(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name): Store
    {
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    public function test_import_custom_mapping_normalization_unchanged(): void
    {
        $normalized = ProductImportProcessor::normalizeCustomMappings([
            ['source' => 'Supplier Col', 'key' => 'supplier_code', 'scope' => 'product'],
            ['source' => 'V Col', 'key' => 'variant_note', 'scope' => 'variant'],
        ]);

        $this->assertCount(2, $normalized);
        $this->assertSame('supplier_code', $normalized[0]['key']);
        $this->assertSame('variant_note', $normalized[1]['key']);
    }

    public function test_product_update_merges_additional_details_and_preserves_catalog_meta(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Merge');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Meta Merge',
            'slug' => 'meta-merge-'.Str::random(4),
            'description' => 'd',
            'base_price' => 10,
            'sku' => 'MM-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'catalog' => ['compare_at_price' => '12.00'],
                'custom_fields' => [
                    'legacy' => 'keep',
                ],
            ],
        ]);
        $product->variants()->create([
            'sku' => 'MM-1',
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                'name' => 'Meta Merge',
                'description' => 'd',
                'base_price' => 10,
                'sku' => 'MM-1',
                'product_type' => 'physical',
                'stock_alert' => 0,
                '_custom_fields_editor' => '1',
                'custom_fields' => [
                    ['key' => 'legacy', 'type' => 'text', 'value' => 'keep'],
                    ['key' => 'material', 'type' => 'text', 'value' => 'cotton'],
                ],
            ])
            ->assertRedirect(route('products'));

        $product->refresh();
        $meta = $product->meta;
        $this->assertSame('12.00', $meta['catalog']['compare_at_price'] ?? null);
        $this->assertSame('keep', $meta['custom_fields']['legacy'] ?? null);
        $this->assertSame('cotton', $meta['custom_fields']['material'] ?? null);
    }

    public function test_variant_custom_fields_survive_catalog_save_for_default_variant(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Carry');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Carry Row',
            'slug' => 'carry-row-'.Str::random(4),
            'description' => null,
            'base_price' => 11,
            'sku' => 'CR-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => 'CR-1',
            'price' => 11,
            'stock' => 3,
            'stock_alert' => 0,
            'meta' => [
                'custom_fields' => [
                    'thread' => 'silk',
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), [
                'name' => 'Carry Row Updated',
                'description' => null,
                'base_price' => 11,
                'sku' => 'CR-1',
                'product_type' => 'physical',
                'stock_alert' => 0,
                '_custom_fields_editor' => '1',
                'custom_fields' => [
                    ['key' => 'material', 'type' => 'text', 'value' => 'linen'],
                ],
            ])
            ->assertRedirect(route('products'));

        $product->refresh();
        $this->assertSame('linen', $product->meta['custom_fields']['material'] ?? null);

        $variant = $product->variants()->orderBy('id')->first();
        $this->assertNotNull($variant);
        $this->assertSame('silk', $variant->meta['custom_fields']['thread'] ?? null);
    }

    public function test_products_search_matches_additional_detail_values(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Search');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Hidden Fiber Shirt',
            'slug' => 'hidden-fiber-'.Str::random(4),
            'description' => null,
            'base_price' => 20,
            'sku' => 'HF-99',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'custom_fields' => [
                    'material' => 'cotton',
                ],
            ],
        ]);
        $product->variants()->create([
            'sku' => 'HF-99',
            'price' => 20,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['q' => 'cotton']))
            ->assertOk()
            ->assertSee('Hidden Fiber Shirt', false);
    }

    public function test_products_filter_by_extra_field(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Filter');
        $match = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Match Product',
            'slug' => 'match-'.Str::random(4),
            'description' => null,
            'base_price' => 5,
            'sku' => 'M-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['custom_fields' => ['material' => 'cotton blend']],
        ]);
        $match->variants()->create(['sku' => 'M-1', 'price' => 5, 'stock' => 1, 'stock_alert' => 0]);

        $other = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Other Product',
            'slug' => 'other-'.Str::random(4),
            'description' => null,
            'base_price' => 6,
            'sku' => 'O-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['custom_fields' => ['material' => 'polyester']],
        ]);
        $other->variants()->create(['sku' => 'O-1', 'price' => 6, 'stock' => 1, 'stock_alert' => 0]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['cf_key' => 'material', 'cf_value' => 'cotton']))
            ->assertOk()
            ->assertSee('Match Product', false)
            ->assertDontSee('Other Product', false);
    }

    public function test_product_list_highlights_render_for_configured_keys(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Chips');
        $store->update([
            'settings' => [
                'catalog' => [
                    'product_list_detail_keys' => ['material', 'supplier'],
                ],
            ],
        ]);
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Chip Product',
            'slug' => 'chip-'.Str::random(4),
            'description' => null,
            'base_price' => 7,
            'sku' => 'CH-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'custom_fields' => [
                    'material' => 'wool',
                    'supplier' => 'Acme',
                ],
            ],
        ]);
        $product->variants()->create(['sku' => 'CH-1', 'price' => 7, 'stock' => 1, 'stock_alert' => 0]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->assertSee('Material:', false)
            ->assertSee('wool', false)
            ->assertSee('Supplier:', false)
            ->assertSee('Acme', false);
    }

    public function test_owner_can_save_product_list_highlights(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Prefs');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.catalog-list-highlights'), [
                'detail_key_1' => 'material',
                'detail_key_2' => 'supplier',
            ])
            ->assertRedirect(route('products'));

        $store->refresh();
        $this->assertSame(
            ['material', 'supplier'],
            $store->settings['catalog']['product_list_detail_keys'] ?? []
        );
    }

    public function test_products_list_includes_cf_key_suggestions_datalist(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Datalist');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'List Keys Product',
            'slug' => 'list-keys-'.Str::random(4),
            'description' => null,
            'base_price' => 9,
            'sku' => 'LK-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['custom_fields' => ['material' => 'silk']],
        ]);
        $product->variants()->create(['sku' => 'LK-1', 'price' => 9, 'stock' => 1, 'stock_alert' => 0]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->assertSee('id="catalog-cf-key-suggestions"', false)
            ->assertSee('value="material"', false);
    }

    public function test_products_list_highlight_form_uses_select_for_detail_keys(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Select HL');
        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'HL Keys Product',
            'slug' => 'hl-keys-'.Str::random(4),
            'description' => null,
            'base_price' => 8,
            'sku' => 'HL-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['custom_fields' => ['supplier' => 'Acme']],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->assertSee('id="detail_key_1"', false)
            ->assertSee('id="detail_key_2"', false)
            ->assertSee('name="detail_key_1"', false)
            ->assertSee('value="supplier"', false);
    }

    public function test_reserved_custom_field_key_returns_validation_error(): void
    {
        $owner = $this->merchant();
        $store = $this->store($owner, 'CF Reserved');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Reserved Key Product',
            'slug' => 'reserved-'.Str::random(4),
            'description' => 'd',
            'base_price' => 10,
            'sku' => 'RK-1',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => 'RK-1',
            'price' => 10,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('products.edit', $product))
            ->put(route('product.update', ['productId' => $product->id]), [
                'name' => 'Reserved Key Product',
                'description' => 'd',
                'base_price' => 10,
                'sku' => 'RK-1',
                'product_type' => 'physical',
                'stock_alert' => 0,
                '_custom_fields_editor' => '1',
                'custom_fields' => [
                    ['key' => 'catalog', 'type' => 'text', 'value' => 'should-not-save'],
                ],
            ])
            ->assertSessionHasErrors(['custom_fields.0.key']);
    }

    public function test_extra_field_filter_respects_store_isolation(): void
    {
        $owner = $this->merchant('iso-owner@example.com');
        $storeA = $this->store($owner, 'Store A ISO');
        $storeB = $this->store($owner, 'Store B ISO');

        $productA = Product::query()->create([
            'store_id' => $storeA->id,
            'name' => 'Only Store A',
            'slug' => 'only-a-'.Str::random(4),
            'description' => null,
            'base_price' => 3,
            'sku' => 'A-ISO',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['custom_fields' => ['material' => 'cotton']],
        ]);
        $productA->variants()->create(['sku' => 'A-ISO', 'price' => 3, 'stock' => 1, 'stock_alert' => 0]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->get(route('products', ['cf_key' => 'material', 'cf_value' => 'cotton']))
            ->assertOk()
            ->assertDontSee('Only Store A', false);
    }
}
