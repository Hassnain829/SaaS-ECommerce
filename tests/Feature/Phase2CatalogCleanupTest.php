<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2CatalogCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_product_label_with_digital_behavior_saves_system_type_correctly(): void
    {
        [$owner, $store] = $this->ownerWithStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_open_add_product_modal' => '1',
                'name' => 'Online Course Bundle',
                'bulk_price' => 8.50,
                'bulk_stock' => 20,
                'sku' => 'COURSE-001',
                'product_type' => 'physical',
                'product_type_selector' => '__custom__',
                'custom_product_type' => 'Online course',
                'custom_product_type_behavior' => 'digital',
                'stock_alert' => 5,
                'inventory_variant_stock_mode' => 'split_total',
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'COURSE-001')->firstOrFail();
        $meta = is_array($product->meta) ? $product->meta : [];

        $this->assertSame('digital', $product->product_type);
        $this->assertSame('Online course', $meta['custom_product_type_label'] ?? null);
    }

    public function test_custom_product_label_with_service_behavior_saves_system_type_correctly(): void
    {
        [$owner, $store] = $this->ownerWithStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_open_add_product_modal' => '1',
                'name' => 'Consultation Package',
                'bulk_price' => 75,
                'bulk_stock' => 0,
                'sku' => 'CONSULT-001',
                'product_type' => 'physical',
                'product_type_selector' => '__custom__',
                'custom_product_type' => 'Consultation',
                'custom_product_type_behavior' => 'service',
                'stock_alert' => 0,
                'inventory_variant_stock_mode' => 'split_total',
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'CONSULT-001')->firstOrFail();
        $meta = is_array($product->meta) ? $product->meta : [];

        $this->assertSame('service', $product->product_type);
        $this->assertSame('Consultation', $meta['custom_product_type_label'] ?? null);
    }

    public function test_arbitrary_product_type_value_is_rejected(): void
    {
        [$owner, $store] = $this->ownerWithStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_open_add_product_modal' => '1',
                'name' => 'Invalid Product Type',
                'bulk_price' => 15,
                'bulk_stock' => 1,
                'sku' => 'INVALID-001',
                'product_type' => 'online_course',
                'stock_alert' => 0,
                'inventory_variant_stock_mode' => 'split_total',
            ])
            ->assertSessionHasErrors('product_type');
    }

    public function test_missing_custom_label_when_custom_type_selected_returns_validation_error(): void
    {
        [$owner, $store] = $this->ownerWithStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_open_add_product_modal' => '1',
                'name' => 'Missing Custom Label',
                'bulk_price' => 19,
                'bulk_stock' => 2,
                'sku' => 'MISSING-LABEL-001',
                'product_type' => 'physical',
                'product_type_selector' => '__custom__',
                'custom_product_type' => '',
                'custom_product_type_behavior' => 'digital',
                'stock_alert' => 0,
                'inventory_variant_stock_mode' => 'split_total',
            ])
            ->assertSessionHasErrors('custom_product_type');
    }

    public function test_workspace_shows_custom_label_and_base_behavior_details(): void
    {
        [$owner, $store] = $this->ownerWithStore();

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Warranty Plan',
            'slug' => 'warranty-plan',
            'base_price' => 12,
            'sku' => 'WAR-001',
            'product_type' => 'virtual',
            'requires_shipping' => false,
            'track_inventory' => false,
            'status' => true,
            'meta' => ['custom_product_type_label' => 'Warranty'],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Product behavior', false)
            ->assertSee('Warranty', false)
            ->assertSee('Shipping required', false)
            ->assertSee('Inventory tracking', false)
            ->assertSee('No', false);
    }

    public function test_catalog_api_v1_exposes_custom_product_type_label(): void
    {
        [$owner, $store] = $this->ownerWithStore();
        $plainToken = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $plainToken),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Warranty Plan',
            'slug' => 'warranty-plan',
            'base_price' => 12,
            'sku' => 'WAR-001',
            'product_type' => 'virtual',
            'status' => true,
            'meta' => ['custom_product_type_label' => 'Warranty'],
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.0.product_type', 'virtual')
            ->assertJsonPath('data.0.product_type_label', 'Warranty');
    }

    public function test_quick_add_and_more_menu_use_specification_wording(): void
    {
        [$owner, $store] = $this->ownerWithStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->assertSee('Manage specifications', false)
            ->assertDontSee('Manage attributes', false);
    }

    public function test_active_specification_filter_copy_uses_merchant_friendly_wording(): void
    {
        [$owner, $store] = $this->ownerWithStore();

        $attribute = $store->attributes()->create([
            'name' => 'Material',
            'slug' => 'material',
            'display_type' => 'select',
            'sort_order' => 1,
            'is_filterable' => true,
            'is_visible' => true,
        ]);
        $term = $attribute->terms()->create([
            'name' => 'Cotton',
            'slug' => 'cotton',
            'sort_order' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['attribute_term' => $term->id]))
            ->assertOk()
            ->assertSee('Filtered by product specification', false)
            ->assertSee('Clear specification filter', false);
    }

    public function test_quick_chips_do_not_force_advanced_filters_open(): void
    {
        [$owner, $store] = $this->ownerWithStore();
        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Chip Product',
            'slug' => 'chip-product',
            'base_price' => 12,
            'sku' => 'CHIP-001',
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['stock_alert' => 5],
        ]);

        $this->assertAdvancedFiltersOpenState(
            $this->actingAs($owner)->withSession(['current_store_id' => $store->id])->get(route('products')),
            false
        );
        $this->assertAdvancedFiltersOpenState(
            $this->actingAs($owner)->withSession(['current_store_id' => $store->id])->get(route('products', ['stock' => 'low'])),
            false
        );
        $this->assertAdvancedFiltersOpenState(
            $this->actingAs($owner)->withSession(['current_store_id' => $store->id])->get(route('products', ['status' => 'published'])),
            false
        );
        $this->assertAdvancedFiltersOpenState(
            $this->actingAs($owner)->withSession(['current_store_id' => $store->id])->get(route('products', ['status' => 'draft'])),
            false
        );
    }

    public function test_advanced_only_filters_open_advanced_panel(): void
    {
        [$owner, $store] = $this->ownerWithStore();
        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Material Sample',
            'slug' => 'material-sample',
            'base_price' => 12,
            'sku' => 'MAT-001',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [
                'custom_fields' => [
                    'material' => ['type' => 'text', 'value' => 'cotton'],
                ],
            ],
        ]);

        $attribute = $store->attributes()->create([
            'name' => 'Material',
            'slug' => 'material',
            'display_type' => 'select',
            'sort_order' => 1,
            'is_filterable' => true,
            'is_visible' => true,
        ]);
        $term = $attribute->terms()->create([
            'name' => 'Cotton',
            'slug' => 'cotton',
            'sort_order' => 1,
        ]);

        $this->assertAdvancedFiltersOpenState(
            $this->actingAs($owner)->withSession(['current_store_id' => $store->id])->get(route('products', ['attribute_term' => $term->id])),
            true
        );
        $this->assertAdvancedFiltersOpenState(
            $this->actingAs($owner)->withSession(['current_store_id' => $store->id])->get(route('products', ['cf_key' => 'material', 'cf_value' => 'cot'])),
            true
        );
    }

    private function assertAdvancedFiltersOpenState(\Illuminate\Testing\TestResponse $response, bool $expectedOpen): void
    {
        $response->assertOk();
        $html = $response->getContent();
        preg_match('/<details[^>]*id="products-advanced-filters-panel"[^>]*>/i', (string) $html, $matches);
        $panelTag = (string) ($matches[0] ?? '');
        $hasOpen = preg_match('/\sopen(\s|>|=)/i', $panelTag) === 1;
        $this->assertSame($expectedOpen, $hasOpen);
    }

    private function ownerWithStore(): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);

        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Cleanup Store',
            'slug' => 'cleanup-store-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => '123 Test Street',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }
}
