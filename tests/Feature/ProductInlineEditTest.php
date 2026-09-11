<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariationOption;
use App\Models\ProductVariationType;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductInlineEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_detach_category_from_product_in_current_store(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Cookie Pack');
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Cookies',
            'slug' => 'cookies-'.Str::random(4),
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $product->categories()->attach($category->id);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->deleteJson(route('products.inline.detach-category', [
                'product' => $product->id,
                'category' => $category->id,
            ]))
            ->assertOk()
            ->assertJson(['ok' => true, 'removed' => true]);

        $this->assertDatabaseMissing('product_categories', [
            'product_id' => $product->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_detach_category_rejects_cross_store_product(): void
    {
        $owner = $this->makeUser();
        $storeA = $this->makeStore($owner, 'Store A');
        $storeB = $this->makeStore($owner, 'Store B');
        $productB = $this->makeProduct($storeB, 'Other');
        $categoryB = Category::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Other Cat',
            'slug' => 'other-'.Str::random(4),
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $productB->categories()->attach($categoryB->id);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->deleteJson(route('products.inline.detach-category', [
                'product' => $productB->id,
                'category' => $categoryB->id,
            ]))
            ->assertNotFound();

        $this->assertDatabaseHas('product_categories', [
            'product_id' => $productB->id,
            'category_id' => $categoryB->id,
        ]);
    }

    public function test_owner_can_inline_update_price(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Priced Item');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.price', $product), [
                'base_price' => 42.5,
            ])
            ->assertOk()
            ->assertJsonPath('base_price', 42.5)
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonPath('variant_id', $product->variants()->first()->id);

        $product->refresh();
        $this->assertSame('42.50', (string) $product->base_price);
        $this->assertSame('42.50', (string) $product->variants()->first()->price);
        $this->assertTrue($response->json('ok'));
        $this->assertSame('USD 42.50', $response->json('display'));
        $this->assertFalse($response->json('mixed'));
    }

    public function test_inline_price_update_aligns_optionless_variant_rows_to_the_shared_price(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Mixed Pricing Item');

        $inherits = $product->variants()->first();
        $second = $product->variants()->create([
            'sku' => $product->sku.'-XL',
            'price' => 25,
            'stock' => 3,
            'stock_alert' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.price', $product), ['base_price' => 42.5])
            ->assertOk();

        $this->assertSame('42.50', (string) $inherits->fresh()->price);
        $this->assertSame('42.50', (string) $second->fresh()->price);
        $this->assertNull($second->fresh()->priceOverride());
    }

    public function test_inline_price_clears_a_stored_simple_variant_price_so_it_follows_the_list(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Stored Simple Price');
        $variant = $product->variants()->first();
        $variant->update(['price' => 18]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.price', $product), ['base_price' => 22])
            ->assertOk()
            ->assertJsonPath('display', 'USD 22.00')
            ->assertJsonPath('mixed', false);

        $this->assertNull($variant->fresh()->priceOverride());
        $this->assertSame('22.00', (string) $variant->fresh()->price);
    }

    public function test_inline_price_applies_to_option_variants_when_there_is_no_standard_base_row(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Garlic Flavor');
        $product->variants()->delete();

        $size = ProductVariationType::query()->create([
            'product_id' => $product->id,
            'name' => 'Net Wt.',
            'type' => 'select',
        ]);
        $oneOz = ProductVariationOption::query()->create([
            'variation_type_id' => $size->id,
            'value' => '1 OZ',
            'sort_order' => 0,
        ]);
        $twoFiveOz = ProductVariationOption::query()->create([
            'variation_type_id' => $size->id,
            'value' => '2.5 OZ',
            'sort_order' => 1,
        ]);

        $small = $product->variants()->create([
            'sku' => 'woo-1259',
            'price' => 50,
            'stock' => 100,
            'stock_alert' => 0,
        ]);
        $large = $product->variants()->create([
            'sku' => 'woo-1264',
            'price' => 1500,
            'stock' => 100,
            'stock_alert' => 0,
        ]);
        $small->options()->sync([$oneOz->id]);
        $large->options()->sync([$twoFiveOz->id]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.price', $product), ['base_price' => 1500])
            ->assertOk()
            ->assertJsonPath('base_price', 1500);

        $this->assertSame('1500.00', (string) $product->fresh()->base_price);
        $this->assertNull($small->fresh()->priceOverride());
        $this->assertNull($large->fresh()->priceOverride());
        $this->assertSame('1500.00', (string) $small->fresh()->price);
        $this->assertSame('1500.00', (string) $large->fresh()->price);
    }

    public function test_inline_price_leaves_option_overrides_when_a_standard_base_row_exists(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Base And Options');
        $base = $product->variants()->first();

        $size = ProductVariationType::query()->create([
            'product_id' => $product->id,
            'name' => 'Size',
            'type' => 'select',
        ]);
        $largeOpt = ProductVariationOption::query()->create([
            'variation_type_id' => $size->id,
            'value' => 'Large',
            'sort_order' => 0,
        ]);
        $optionVariant = $product->variants()->create([
            'sku' => $product->sku.'-L',
            'price' => 25,
            'stock' => 3,
            'stock_alert' => 1,
        ]);
        $optionVariant->options()->sync([$largeOpt->id]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.price', $product), ['base_price' => 42.5])
            ->assertOk();

        $this->assertSame('42.50', (string) $base->fresh()->price);
        $this->assertSame('25.00', (string) $optionVariant->fresh()->price);
        $this->assertSame('25.00', (string) $optionVariant->fresh()->priceOverride());
    }

    public function test_inline_price_can_apply_to_every_option_when_asked(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Apply All Options');
        $base = $product->variants()->first();

        $size = ProductVariationType::query()->create([
            'product_id' => $product->id,
            'name' => 'Size',
            'type' => 'select',
        ]);
        $largeOpt = ProductVariationOption::query()->create([
            'variation_type_id' => $size->id,
            'value' => 'Large',
            'sort_order' => 0,
        ]);
        $optionVariant = $product->variants()->create([
            'sku' => $product->sku.'-L',
            'price' => 25,
            'stock' => 3,
            'stock_alert' => 1,
        ]);
        $optionVariant->options()->sync([$largeOpt->id]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.price', $product), [
                'base_price' => 18,
                'apply_to_every_variant' => true,
            ])
            ->assertOk()
            ->assertJsonPath('mixed', false)
            ->assertJsonPath('display', 'USD 18.00');

        $this->assertSame('18.00', (string) $base->fresh()->price);
        $this->assertNull($optionVariant->fresh()->priceOverride());
        $this->assertSame('18.00', (string) $optionVariant->fresh()->price);
    }

    public function test_owner_can_inline_update_stock_and_records_movement(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Stocked Item');
        $variant = $product->variants()->first();
        $variant->update(['stock' => 5, 'stock_alert' => 1]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.stock', $product), [
                'stock' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('stock', 25)
            ->assertJsonPath('stock_state', 'in')
            ->assertJsonPath('variant_id', $variant->id)
            ->assertJsonPath('inventory_total', 25);

        $variant->refresh();
        $this->assertSame(25, (int) $variant->stock);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_EDIT_UPDATE,
        ]);
    }

    public function test_inline_stock_returns_low_stock_state_when_at_or_below_alert(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Low Alert Item');
        $product->variants()->first()->update([
            'stock' => 20,
            'stock_alert' => 5,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.stock', $product), [
                'stock' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('stock', 1)
            ->assertJsonPath('inventory_total', 1)
            ->assertJsonPath('stock_alert', 5)
            ->assertJsonPath('stock_state', 'low')
            ->assertJsonPath('is_low', true);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('Low Stock', $html);
        $this->assertStringContainsString('data-stock-state="low"', $html);
    }

    public function test_inline_stock_zero_returns_out_of_stock_state(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Empty Item');
        $product->variants()->first()->update([
            'stock' => 8,
            'stock_alert' => 3,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.stock', $product), [
                'stock' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('stock_state', 'out')
            ->assertJsonPath('is_out', true);
    }

    public function test_staff_cannot_inline_edit(): void
    {
        $owner = $this->makeUser('owner-inline@x.com');
        $staff = $this->makeUser('staff-inline@x.com');
        $store = $this->makeStore($owner);
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $product = $this->makeProduct($store, 'Staff Locked');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.price', $product), ['base_price' => 9])
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.variant-prices', $product), [
                'variants' => [['id' => $product->variants()->first()->id, 'price' => 9]],
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.stock', $product), ['stock' => 3])
            ->assertForbidden();
    }

    public function test_products_list_shows_inline_edit_controls_for_managers(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Listed');
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Snacks',
            'slug' => 'snacks-'.Str::random(4),
            'parent_id' => null,
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $product->categories()->attach($category->id);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('js-detach-category', $html);
        $this->assertStringContainsString('js-inline-price', $html);
        $this->assertStringContainsString('js-inline-stock', $html);
        $this->assertStringContainsString('/products/'.$product->id.'/inline-price', $html);
        $this->assertStringContainsString('inline-variant-price-popover', $html);
        $this->assertStringContainsString('syncAnchoredPopover', $html);
        $this->assertStringNotContainsString('rect.bottom + window.scrollY', $html);
        $this->assertStringContainsString('syncEditPopupAfterInlinePrice', $html);
        $this->assertStringContainsString('syncEditPopupAfterInlineStock', $html);
        $this->assertStringContainsString('resolveProductEditPayloadFromButton', $html);
        $this->assertStringContainsString('hydratePayloadFromListRow', $html);
        $this->assertStringContainsString('__liveProductValuesById', $html);
        $this->assertStringContainsString('rememberLiveValues', $html);
        $this->assertStringContainsString('data-live-stock', $html);
        $this->assertStringContainsString('inline-variant-stock-popover', $html);
        $this->assertStringContainsString("event.key === 'Enter'", $html);
        $this->assertStringContainsString('liveMap.variants', $html);
        $this->assertStringContainsString('mergedVariants', $html);
        $this->assertStringContainsString('Prefer the latest typed option stocks', $html);
    }

    public function test_owner_can_batch_update_variant_stocks_from_list(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Multi Stock');
        $first = $product->variants()->first();
        $first->update(['stock' => 2, 'stock_alert' => 1]);
        $second = $product->variants()->create([
            'sku' => 'MS-2',
            'price' => 10,
            'stock' => 3,
            'stock_alert' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.variant-stocks', $product), [
                'variants' => [
                    ['id' => $first->id, 'stock' => 11],
                    ['id' => $second->id, 'stock' => 7],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('inventory_total', 18)
            ->assertJsonPath('ok', true);

        $this->assertSame(11, (int) $first->fresh()->stock);
        $this->assertSame(7, (int) $second->fresh()->stock);
    }

    public function test_owner_can_batch_update_variant_prices_from_list(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Garlic Flavor');
        $product->variants()->delete();

        $size = ProductVariationType::query()->create([
            'product_id' => $product->id,
            'name' => 'Net Wt.',
            'type' => 'select',
        ]);
        $oneOz = ProductVariationOption::query()->create([
            'variation_type_id' => $size->id,
            'value' => '1 OZ',
            'sort_order' => 0,
        ]);
        $twoFiveOz = ProductVariationOption::query()->create([
            'variation_type_id' => $size->id,
            'value' => '2.5 OZ',
            'sort_order' => 1,
        ]);

        $small = $product->variants()->create([
            'sku' => 'woo-1259',
            'price' => 50,
            'stock' => 100,
            'stock_alert' => 0,
        ]);
        $large = $product->variants()->create([
            'sku' => 'woo-1264',
            'price' => 1500,
            'stock' => 100,
            'stock_alert' => 0,
        ]);
        $small->options()->sync([$oneOz->id]);
        $large->options()->sync([$twoFiveOz->id]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.variant-prices', $product), [
                'variants' => [
                    ['id' => $small->id, 'price' => 50],
                    ['id' => $large->id, 'price' => 1500],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('mixed', true)
            ->assertJsonPath('display', 'USD 50.00 – 1,500.00')
            ->assertJsonPath('base_price', 50);

        $this->assertSame('50.00', (string) $small->fresh()->priceOverride());
        $this->assertSame('1500.00', (string) $large->fresh()->priceOverride());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.variant-prices', $product), [
                'variants' => [
                    ['id' => $small->id, 'price' => 12],
                    ['id' => $large->id, 'price' => 12],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('mixed', false)
            ->assertJsonPath('display', 'USD 12.00');

        $this->assertNull($small->fresh()->priceOverride());
        $this->assertNull($large->fresh()->priceOverride());
        $this->assertSame('12.00', (string) $small->fresh()->price);
        $this->assertSame('12.00', (string) $large->fresh()->price);
    }

    public function test_variant_price_update_rejects_cross_store_product(): void
    {
        $owner = $this->makeUser();
        $storeA = $this->makeStore($owner, 'Store A');
        $storeB = $this->makeStore($owner, 'Store B');
        $productB = $this->makeProduct($storeB, 'Other');
        $variantB = $productB->variants()->first();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->patchJson(route('products.inline.variant-prices', $productB), [
                'variants' => [['id' => $variantB->id, 'price' => 9]],
            ])
            ->assertNotFound();
    }

    private function makeUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $user, string $name = 'Inline Store'): Store
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
            'price' => null, // inherits the product base price
            'stock' => 5,
            'stock_alert' => 1,
        ]);

        return $product;
    }
}
