<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariationOption;
use App\Models\ProductVariationType;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use App\Services\Delivery\StoreShippingPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_bulk_delete_current_store_products_only(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $p1 = $this->makeProduct($store, 'A');
        $p2 = $this->makeProduct($store, 'B');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'delete',
                'product_ids' => [$p1->id, $p2->id],
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('products', ['id' => $p1->id]);
        $this->assertSoftDeleted('products', ['id' => $p2->id]);
    }

    public function test_bulk_rejects_cross_store_product_ids(): void
    {
        $owner = $this->makeUser();
        $storeA = $this->makeStore($owner, 'Store A');
        $storeB = $this->makeStore($owner, 'Store B');
        $pA = $this->makeProduct($storeA, 'In A');
        $pB = $this->makeProduct($storeB, 'In B');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('products.bulk'), [
                'action' => 'delete',
                'product_ids' => [$pA->id, $pB->id],
            ])
            ->assertSessionHasErrors('bulk');

        $this->assertDatabaseHas('products', ['id' => $pA->id]);
        $this->assertDatabaseHas('products', ['id' => $pB->id]);
    }

    public function test_staff_cannot_post_bulk_actions(): void
    {
        $owner = $this->makeUser('owner@x.com');
        $staff = $this->makeUser('staff@x.com');
        $store = $this->makeStore($owner);
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $p = $this->makeProduct($store, 'S');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'delete',
                'product_ids' => [$p->id],
            ])
            ->assertForbidden();
    }

    public function test_staff_cannot_run_bulk_stock(): void
    {
        $owner = $this->makeUser('owner-stock-staff@x.com');
        $staff = $this->makeUser('staff-stock@x.com');
        $store = $this->makeStore($owner);
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $p = $this->makeProduct($store, 'Stock Staff');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'stock',
                'product_ids' => [$p->id],
                'stock_mode' => 'set',
                'stock_value' => 5,
                'bulk_variant_stock_scope' => 'default_variant_only',
            ])
            ->assertForbidden();
    }

    public function test_bulk_stock_set_records_stock_movements(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Stocky');
        $variant = $product->variants()->first();
        $variant->update(['stock' => 3]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'stock',
                'product_ids' => [$product->id],
                'stock_mode' => 'set',
                'stock_value' => 11,
                'stock_apply_mode' => 'replace_all',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($msg): bool => str_contains((string) $msg, '1 product'));

        $variant->refresh();
        $this->assertSame(11, (int) $variant->stock);

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'previous_stock' => 3,
            'new_stock' => 11,
            'movement_type' => StockMovement::TYPE_EDIT_UPDATE,
        ]);
    }

    public function test_bulk_stock_set_empty_only_skips_products_that_already_have_stock(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $withStock = $this->makeProduct($store, 'Has Stock');
        $empty = $this->makeProduct($store, 'Empty Stock');
        $withStock->variants()->first()->update(['stock' => 20]);
        $empty->variants()->first()->update(['stock' => 0]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'stock',
                'product_ids' => [$withStock->id, $empty->id],
                'stock_mode' => 'set',
                'stock_value' => 10,
                'stock_apply_mode' => 'empty_only',
                'bulk_variant_stock_scope' => 'default_variant_only',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($msg): bool => str_contains((string) $msg, 'already had stock'));

        $this->assertSame(20, (int) $withStock->variants()->first()->fresh()->stock);
        $this->assertSame(10, (int) $empty->variants()->first()->fresh()->stock);
    }

    public function test_bulk_stock_set_replace_all_overwrites_existing_stock(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $withStock = $this->makeProduct($store, 'Overwrite Me');
        $empty = $this->makeProduct($store, 'Also Set');
        $withStock->variants()->first()->update(['stock' => 20]);
        $empty->variants()->first()->update(['stock' => 0]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'stock',
                'product_ids' => [$withStock->id, $empty->id],
                'stock_mode' => 'set',
                'stock_value' => 10,
                'stock_apply_mode' => 'replace_all',
                'bulk_variant_stock_scope' => 'default_variant_only',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(10, (int) $withStock->variants()->first()->fresh()->stock);
        $this->assertSame(10, (int) $empty->variants()->first()->fresh()->stock);
    }

    public function test_bulk_categories_and_tags_are_store_scoped(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $other = $this->makeStore($owner, 'Other');
        $cat = Category::query()->create([
            'store_id' => $other->id,
            'name' => 'Evil Cat',
            'slug' => 'evil-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);
        $tag = Tag::query()->create([
            'store_id' => $other->id,
            'name' => 'Evil Tag',
            'slug' => 'evil-tag',
            'status' => 'active',
            'sort_order' => 0,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $product = $this->makeProduct($store, 'P');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'categories',
                'product_ids' => [$product->id],
                'category_ids' => [$cat->id],
            ])
            ->assertSessionHasErrors('category_ids');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'tags',
                'product_ids' => [$product->id],
                'tag_ids' => [$tag->id],
            ])
            ->assertSessionHasErrors('tag_ids');
    }

    public function test_bulk_assign_categories_brand_tags_on_current_store(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $cat = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Cat A',
            'slug' => 'cat-a',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);
        $tag = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Tag A',
            'slug' => 'tag-a',
            'status' => 'active',
            'sort_order' => 0,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Brand A',
            'slug' => 'brand-a',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $product = $this->makeProduct($store, 'Multi');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'categories',
                'product_ids' => [$product->id],
                'category_ids' => [$cat->id],
            ])
            ->assertRedirect();
        $this->assertTrue($product->fresh()->categories->contains('id', $cat->id));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'tags',
                'product_ids' => [$product->id],
                'tag_ids' => [$tag->id],
            ])
            ->assertRedirect();
        $this->assertTrue($product->fresh()->tags->contains('id', $tag->id));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'brand',
                'product_ids' => [$product->id],
                'brand_id' => $brand->id,
            ])
            ->assertRedirect();
        $this->assertSame($brand->id, (int) $product->fresh()->brand_id);
    }

    public function test_bulk_status_updates_products(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Drafty');
        $product->update(['status' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'status',
                'product_ids' => [$product->id],
                'product_status' => 'published',
            ])
            ->assertRedirect();

        $this->assertTrue((bool) $product->fresh()->status);
    }

    public function test_bulk_accepts_product_ids_json_for_large_matching_sets(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->makeProduct($store, 'Bulk JSON '.$i)->id;
        }

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'status',
                'product_ids_json' => json_encode($ids),
                'product_status' => 'draft',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($ids as $id) {
            $this->assertFalse((bool) Product::query()->find($id)?->status);
        }
    }

    public function test_bulk_shipping_weight_missing_only_preserves_existing_and_skips_non_shippable(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $missing = $this->makeProduct($store, 'Missing Weight');
        $existing = $this->makeProduct($store, 'Has Weight');
        $existing->forceFill(['meta' => ['shipping_weight' => 2.25]])->save();
        $digital = $this->makeProduct($store, 'Digital');
        $digital->forceFill(['requires_shipping' => false, 'product_type' => 'digital'])->save();
        $variant = $existing->variants()->first();
        $variant->forceFill(['meta' => ['shipping_weight' => 9.99]])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$missing->id, $existing->id, $digital->id],
                'shipping_weight_value' => 0.7,
                'shipping_weight_mode' => 'missing_only',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0.7, (float) data_get($missing->fresh()->meta, 'shipping_weight'));
        $this->assertSame(2.25, (float) data_get($existing->fresh()->meta, 'shipping_weight'));
        $this->assertNull(data_get($digital->fresh()->meta, 'shipping_weight'));
        $this->assertSame(9.99, (float) data_get($variant->fresh()->meta, 'shipping_weight'));
    }

    public function test_bulk_shipping_weight_replace_all_updates_product_level_only(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Replace Weight');
        $product->forceFill(['meta' => ['shipping_weight' => 1.1]])->save();
        $variant = $product->variants()->first();
        $variant->forceFill(['meta' => ['shipping_weight' => 5.5]])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$product->id],
                'shipping_weight_value' => 15,
                'shipping_weight_mode' => 'replace_all',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(15.0, (float) data_get($product->fresh()->meta, 'shipping_weight'));
        $this->assertSame(5.5, (float) data_get($variant->fresh()->meta, 'shipping_weight'));
    }

    public function test_bulk_variant_shipping_weight_map_by_option_updates_matching_variants_only(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeSizedVariantProduct($store, [
            'Small' => null,
            'Large' => 9.0,
        ]);
        $product->forceFill(['meta' => ['shipping_weight' => 2.0]])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$product->id],
                'shipping_weight_target' => 'variants',
                'variant_bulk_mode' => 'map_by_option',
                'variant_option_name' => 'Size',
                'variant_weight_map_json' => json_encode(['Small' => 0.55, 'Large' => 0.85]),
                'shipping_weight_mode' => 'missing_only',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $variants = $product->fresh()->variants()->with('options')->get()->keyBy(fn ($v) => $v->options->first()?->value);
        $this->assertSame(0.55, (float) data_get($variants['Small']->meta, 'shipping_weight'));
        $this->assertSame(9.0, (float) data_get($variants['Large']->meta, 'shipping_weight'));
        $this->assertSame(2.0, (float) data_get($product->fresh()->meta, 'shipping_weight'));
    }

    public function test_bulk_variant_map_rejects_weights_above_store_maximum(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        app(StoreShippingPreferences::class)->update($store, [
            'weight_unit' => 'LB',
        ]);
        $product = $this->makeSizedVariantProduct($store, [
            'Large' => null,
            'XL' => null,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$product->id],
                'shipping_weight_target' => 'variants',
                'variant_bulk_mode' => 'map_by_option',
                'variant_option_name' => 'Size',
                'variant_weight_map_json' => json_encode(['Large' => 500, 'XL' => 600]),
                'shipping_weight_mode' => 'replace_all',
            ])
            ->assertSessionHasErrors('bulk');

        foreach ($product->fresh()->variants as $variant) {
            $this->assertNull(data_get($variant->meta, 'shipping_weight'));
        }
    }

    public function test_bulk_variant_use_option_values_bare_numbers_respect_store_kg_unit(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        app(StoreShippingPreferences::class)->update($store, [
            'weight_unit' => 'KG',
        ]);
        $product = $this->makeOptionVariantProduct($store, 'Weight', ['5', '10', '20']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$product->id],
                'shipping_weight_target' => 'variants',
                'variant_bulk_mode' => 'use_option_values',
                'variant_option_name' => 'Weight',
                'shipping_weight_mode' => 'replace_all',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $variants = $product->fresh()->variants()->with('options')->get()->keyBy(fn ($v) => $v->options->first()?->value);
        $this->assertSame(5.0, (float) data_get($variants['5']->meta, 'shipping_weight'));
        $this->assertSame(10.0, (float) data_get($variants['10']->meta, 'shipping_weight'));
        $this->assertSame(20.0, (float) data_get($variants['20']->meta, 'shipping_weight'));
    }

    public function test_bulk_variant_use_option_values_rejects_non_weight_bare_numbers(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeOptionVariantProduct($store, 'Size', ['10', '12', '14']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$product->id],
                'shipping_weight_target' => 'variants',
                'variant_bulk_mode' => 'use_option_values',
                'variant_option_name' => 'Size',
                'shipping_weight_mode' => 'replace_all',
            ])
            ->assertSessionHasErrors('bulk');

        foreach ($product->fresh()->variants as $variant) {
            $this->assertNull(data_get($variant->meta, 'shipping_weight'));
        }
    }

    public function test_bulk_variant_use_option_values_rejects_pack_size_bare_numbers(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeOptionVariantProduct($store, 'Pack size', ['6', '12', '24']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$product->id],
                'shipping_weight_target' => 'variants',
                'variant_bulk_mode' => 'use_option_values',
                'variant_option_name' => 'Pack size',
                'shipping_weight_mode' => 'replace_all',
            ])
            ->assertSessionHasErrors('bulk');

        foreach ($product->fresh()->variants as $variant) {
            $this->assertNull(data_get($variant->meta, 'shipping_weight'));
        }
    }

    public function test_products_page_escapes_variant_option_labels_in_bulk_weight_ui(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeOptionVariantProduct($store, 'Size', ['<img src=x onerror=alert(1)>']);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'));

        $response->assertOk();
        // Catalog option labels must not be emitted as raw HTML attributes/content in the page shell.
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
        $response->assertSee('createElement', false);
        $response->assertSee('textContent', false);
    }

    public function test_bulk_variant_shipping_weight_use_option_values_parses_lb_labels(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        app(StoreShippingPreferences::class)->update($store, [
            'weight_unit' => 'LB',
        ]);
        $product = $this->makeOptionVariantProduct($store, 'Weight', ['5 lb', '10 lb']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$product->id],
                'shipping_weight_target' => 'variants',
                'variant_bulk_mode' => 'use_option_values',
                'variant_option_name' => 'Weight',
                'shipping_weight_mode' => 'replace_all',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $variants = $product->fresh()->variants()->with('options')->get()->keyBy(fn ($v) => $v->options->first()?->value);
        $this->assertSame(5.0, (float) data_get($variants['5 lb']->meta, 'shipping_weight'));
        $this->assertSame(10.0, (float) data_get($variants['10 lb']->meta, 'shipping_weight'));
        $this->assertNull(data_get($product->fresh()->meta, 'shipping_weight'));
    }

    public function test_bulk_variant_shipping_weight_clear_removes_variant_overrides(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeSizedVariantProduct($store, [
            'Small' => 0.5,
            'Large' => 2.5,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'shipping_weight',
                'product_ids' => [$product->id],
                'shipping_weight_target' => 'variants',
                'variant_bulk_mode' => 'clear',
                'shipping_weight_mode' => 'missing_only',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($product->fresh()->variants as $variant) {
            $this->assertNull(data_get($variant->meta, 'shipping_weight'));
        }
    }

    public function test_bulk_variant_shipping_weight_preview_returns_option_groups(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeSizedVariantProduct($store, ['Small' => null, 'Large' => null]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->postJson(route('products.bulk.shipping-weight.preview'), [
                'product_ids' => [$product->id],
                'shipping_weight_target' => 'variants',
                'variant_bulk_mode' => 'map_by_option',
                'variant_option_name' => 'Size',
                'shipping_weight_mode' => 'missing_only',
                'variant_weight_map_json' => json_encode(['Small' => 0.5]),
            ])
            ->assertOk()
            ->assertJsonPath('compatible_products_count', 1)
            ->assertJsonPath('matching_variants_count', 1)
            ->assertJsonFragment(['name' => 'Size']);
    }

    public function test_product_workspace_is_store_scoped(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'WS');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Product workspace', false)
            ->assertSee('WS', false);
    }

    private function makeUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $user, string $name = 'Test Store'): Store
    {
        $store = Store::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    private function makeProduct(Store $store, string $name): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 0,
            'stock_alert' => 0,
        ]);

        return $product;
    }

    /**
     * @param  array<string, float|null>  $sizeWeights
     */
    private function makeSizedVariantProduct(Store $store, array $sizeWeights): Product
    {
        return $this->makeOptionVariantProduct($store, 'Size', array_keys($sizeWeights), $sizeWeights);
    }

    /**
     * @param  list<string>  $optionValues
     * @param  array<string, float|null>  $presetWeights
     */
    private function makeOptionVariantProduct(Store $store, string $groupName, array $optionValues, array $presetWeights = []): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Variant '.fake()->unique()->word(),
            'slug' => 'variant-'.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);

        $variationType = ProductVariationType::query()->create([
            'product_id' => $product->id,
            'name' => $groupName,
            'type' => 'select',
        ]);

        $options = [];
        foreach ($optionValues as $index => $value) {
            $options[$value] = ProductVariationOption::query()->create([
                'variation_type_id' => $variationType->id,
                'value' => $value,
                'sort_order' => $index,
            ]);
        }

        foreach ($options as $value => $option) {
            $meta = [];
            $preset = $presetWeights[$value] ?? null;
            if ($preset !== null) {
                $meta['shipping_weight'] = $preset;
            }
            $variant = $product->variants()->create([
                'sku' => $product->sku.'-'.strtoupper(Str::slug($value, '')),
                'price' => 10,
                'stock' => 5,
                'stock_alert' => 0,
                'meta' => $meta,
            ]);
            $variant->options()->sync([$option->id]);
        }

        return $product->fresh(['variationTypes.options', 'variants.options']);
    }
}
