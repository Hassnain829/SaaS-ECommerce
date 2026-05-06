<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2CatalogCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_variant_sku_is_allowed_in_different_stores(): void
    {
        $ownerA = $this->merchantUser('sku-a@example.test');
        $storeA = $this->storeFor($ownerA, 'SKU Store A');
        $ownerB = $this->merchantUser('sku-b@example.test');
        $storeB = $this->storeFor($ownerB, 'SKU Store B');

        $this->productFor($storeA, 'Store A Product', 'PA-1', 'SUPPLIER-42');
        $this->productFor($storeB, 'Store B Product', 'PB-1', 'SUPPLIER-42');

        $this->assertSame(2, ProductVariant::query()->where('sku', 'SUPPLIER-42')->count());
        $this->assertDatabaseHas('product_variants', ['store_id' => $storeA->id, 'sku' => 'SUPPLIER-42']);
        $this->assertDatabaseHas('product_variants', ['store_id' => $storeB->id, 'sku' => 'SUPPLIER-42']);
    }

    public function test_same_variant_sku_is_rejected_in_same_store_with_validation(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Duplicate SKU Store');
        $this->productFor($store, 'Existing Product', 'EXISTING-P', 'DUP-VAR');
        $candidate = $this->productFor($store, 'Candidate Product', 'CANDIDATE-P', 'CANDIDATE-V');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $candidate->id]), $this->productUpdatePayload($candidate, [
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['One Size']],
                ],
                'variants' => [
                    [
                        'option_map' => ['0' => 0],
                        'sku' => 'DUP-VAR',
                        'price' => 12,
                        'stock' => 3,
                        'stock_alert' => 1,
                    ],
                ],
            ]))
            ->assertSessionHasErrors('variants.0.sku');

        $this->assertDatabaseHas('product_variants', [
            'product_id' => $candidate->id,
            'sku' => 'CANDIDATE-V',
        ]);
    }

    public function test_submitted_variant_id_can_keep_its_existing_sku_without_creating_duplicate(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Variant Reuse Store');
        $product = $this->productFor($store, 'Reusable Variant', 'REUSE-P', 'REUSE-V');
        $defaultVariant = $product->variants()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->productUpdatePayload($product, [
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['M']],
                ],
                'variants' => [
                    [
                        'id' => $defaultVariant->id,
                        'option_map' => ['0' => 0],
                        'sku' => 'REUSE-V',
                        'price' => 12,
                        'stock' => 4,
                        'stock_alert' => 1,
                    ],
                ],
            ]))
            ->assertRedirect(route('products'));

        $variant = $product->fresh()->variants()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->productUpdatePayload($product->fresh(), [
                'variation_types' => [
                    ['name' => 'Size', 'type' => 'select', 'options' => ['M']],
                ],
                'variants' => [
                    [
                        'id' => $variant->id,
                        'option_map' => ['0' => 0],
                        'sku' => 'REUSE-V',
                        'price' => 13,
                        'stock' => 5,
                        'stock_alert' => 1,
                    ],
                ],
            ]))
            ->assertRedirect(route('products'));

        $this->assertSame(1, $product->fresh()->variants()->count());
        $variant->refresh();
        $this->assertSame((int) $product->id, (int) $variant->product_id);
        $this->assertSame('REUSE-V', $variant->sku);
        $this->assertSame('13.00', (string) $variant->price);
        $this->assertSame(5, (int) $variant->stock);
    }

    public function test_product_sku_is_unique_within_store_but_allowed_across_stores(): void
    {
        $ownerA = $this->merchantUser('product-sku-a@example.test');
        $storeA = $this->storeFor($ownerA, 'Product SKU A');
        $ownerB = $this->merchantUser('product-sku-b@example.test');
        $storeB = $this->storeFor($ownerB, 'Product SKU B');

        $this->productFor($storeA, 'Store A Same Product SKU', 'SAME-P', 'SAME-P-A');
        $this->productFor($storeB, 'Store B Same Product SKU', 'SAME-P', 'SAME-P-B');
        $candidate = $this->productFor($storeA, 'Candidate Product SKU', 'OTHER-P', 'OTHER-P-V');

        $this->assertSame(2, Product::query()->where('sku', 'SAME-P')->count());

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->put(route('product.update', ['productId' => $candidate->id]), $this->productUpdatePayload($candidate, [
                'sku' => 'SAME-P',
            ]))
            ->assertSessionHasErrors('sku');
    }

    public function test_attributes_can_be_managed_assigned_and_used_as_product_filters(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Attribute Store');
        $product = $this->productFor($store, 'Cotton Shirt', 'COT-P', 'COT-V');
        $other = $this->productFor($store, 'Leather Belt', 'LEA-P', 'LEA-V');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('catalog.attributes.store'), [
                'name' => 'Material',
                'display_type' => 'select',
                'is_filterable' => '1',
                'is_visible' => '1',
                'terms' => 'Cotton, Wool',
            ])
            ->assertRedirect();

        $attribute = Attribute::query()->where('store_id', $store->id)->where('slug', 'material')->firstOrFail();
        $term = AttributeTerm::query()->where('attribute_id', $attribute->id)->where('slug', 'cotton')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->productUpdatePayload($product, [
                'attribute_terms' => [
                    $attribute->id => [$term->id],
                ],
            ]))
            ->assertRedirect(route('products'));

        $this->assertDatabaseHas('product_attributes', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Attributes', false)
            ->assertSee('Material', false)
            ->assertSee('Cotton', false);

        $filterResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products', ['attribute_term' => $term->id]));

        $filterResponse->assertOk()
            ->assertSee('Cotton Shirt', false)
            ->assertDontSee('Leather Belt', false);

        $this->assertDatabaseMissing('product_attributes', [
            'product_id' => $other->id,
            'attribute_id' => $attribute->id,
        ]);
    }

    public function test_cross_store_attribute_terms_are_not_attached_to_product(): void
    {
        $ownerA = $this->merchantUser('attr-a@example.test');
        $storeA = $this->storeFor($ownerA, 'Attribute Store A');
        $ownerB = $this->merchantUser('attr-b@example.test');
        $storeB = $this->storeFor($ownerB, 'Attribute Store B');
        $product = $this->productFor($storeA, 'Scoped Product', 'SCOPED-P', 'SCOPED-V');

        $otherAttribute = Attribute::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Other Material',
            'slug' => 'other-material',
            'display_type' => 'select',
            'sort_order' => 1,
            'is_filterable' => true,
            'is_visible' => true,
        ]);
        $otherTerm = AttributeTerm::query()->create([
            'attribute_id' => $otherAttribute->id,
            'name' => 'Blocked',
            'slug' => 'blocked',
            'sort_order' => 1,
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->put(route('product.update', ['productId' => $product->id]), $this->productUpdatePayload($product, [
                'attribute_terms' => [
                    $otherAttribute->id => [$otherTerm->id],
                ],
            ]))
            ->assertRedirect(route('products'));

        $this->assertDatabaseMissing('product_attributes', [
            'product_id' => $product->id,
            'attribute_id' => $otherAttribute->id,
        ]);
    }

    public function test_product_type_behavior_flags_are_persisted_from_quick_add(): void
    {
        $owner = $this->merchantUser();
        $store = $this->storeFor($owner, 'Behavior Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), [
                '_open_add_product_modal' => '1',
                'name' => 'Downloadable Guide',
                'description' => 'PDF',
                'bulk_price' => 15,
                'bulk_stock' => 0,
                'sku' => 'DIGITAL-GUIDE',
                'product_type' => 'digital',
                'custom_product_type' => '',
                'stock_alert' => 0,
                'inventory_variant_stock_mode' => 'split_total',
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->where('sku', 'DIGITAL-GUIDE')->firstOrFail();

        $this->assertSame('digital', $product->product_type);
        $this->assertFalse((bool) $product->requires_shipping);
        $this->assertFalse((bool) $product->track_inventory);
    }

    public function test_catalog_api_v1_returns_store_scoped_products_attributes_and_behavior(): void
    {
        [$store, $plain] = $this->tokenedStore('API Catalog Store');
        [$otherStore] = $this->tokenedStore('Other API Catalog Store');

        $attribute = Attribute::query()->create([
            'store_id' => $store->id,
            'name' => 'Material',
            'slug' => 'material',
            'display_type' => 'select',
            'sort_order' => 1,
            'is_filterable' => true,
            'is_visible' => true,
        ]);
        $term = AttributeTerm::query()->create([
            'attribute_id' => $attribute->id,
            'name' => 'Cotton',
            'slug' => 'cotton',
            'sort_order' => 1,
        ]);

        $product = $this->productFor($store, 'API Cotton Shirt', 'API-COT-P', 'API-COT-V');
        $this->attachTerm($product, $attribute, $term);
        $this->productFor($otherStore, 'Other Store Shirt', 'API-OTHER-P', 'API-OTHER-V');

        $this->withToken($plain)
            ->getJson('/api/v1/catalog/products?attribute_term='.$term->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.behavior.requires_shipping', true)
            ->assertJsonPath('data.0.attributes.0.name', 'Material')
            ->assertJsonPath('data.0.attributes.0.terms.0.name', 'Cotton');

        $this->withToken($plain)
            ->getJson('/api/v1/catalog/attributes')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Material')
            ->assertJsonPath('data.0.terms.0.name', 'Cotton');
    }

    private function merchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function storeFor(User $user, string $name): Store
    {
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => '123 Test Street',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    /**
     * @return array{0: Store, 1: string, 2: User}
     */
    private function tokenedStore(string $name): array
    {
        $owner = $this->merchantUser(Str::slug($name).'-owner@example.test');
        $store = $this->storeFor($owner, $name);
        $plain = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $plain),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $plain, $owner];
    }

    private function productFor(Store $store, string $name, string $productSku, string $variantSku): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => 'Test product',
            'base_price' => 12,
            'sku' => $productSku,
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['stock_alert' => 1],
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $variantSku,
            'price' => 12,
            'stock' => 5,
            'stock_alert' => 1,
        ]);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productUpdatePayload(Product $product, array $overrides = []): array
    {
        $variant = $product->variants()->orderBy('id')->first();

        $payload = [
            '_open_edit_product_modal' => '1',
            '_edit_product_id' => (string) $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'base_price' => (string) $product->base_price,
            'sku' => $product->sku,
            'product_type' => $product->product_type,
            'custom_product_type' => '',
            'stock_alert' => 1,
            'variation_types' => [],
            'variants' => $variant ? [
                [
                    'id' => $variant->id,
                    'option_map' => [],
                    'sku' => $variant->sku,
                    'price' => (string) $variant->price,
                    'stock' => (int) $variant->stock,
                    'stock_alert' => (int) $variant->stock_alert,
                ],
            ] : [],
        ];

        return array_replace($payload, $overrides);
    }

    private function attachTerm(Product $product, Attribute $attribute, AttributeTerm $term): void
    {
        $productAttribute = ProductAttribute::query()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'is_variation' => false,
            'is_visible' => true,
            'sort_order' => 0,
        ]);

        $productAttribute->terms()->sync([$term->id]);
    }
}
