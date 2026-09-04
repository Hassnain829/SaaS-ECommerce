<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VariantPriceInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::create([
            'user_id' => $owner->id, 'name' => 'PB Store', 'slug' => 'pb-store-1',
            'address' => 'A', 'currency' => 'USD', 'timezone' => 'UTC',
            'category' => 'physical', 'settings' => [], 'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    public function test_variant_price_inherits_the_base_price_until_it_is_overridden(): void
    {
        $store = $this->store();
        $product = Product::create([
            'store_id' => $store->id, 'name' => 'Tee', 'slug' => 'tee-1',
            'base_price' => 33, 'sku' => 'TEE-1', 'product_type' => 'physical',
            'status' => true, 'meta' => [],
        ]);

        $inherits = ProductVariant::create(['product_id' => $product->id, 'sku' => 'TEE-1-R', 'price' => null, 'stock' => 5]);
        $override = ProductVariant::create(['product_id' => $product->id, 'sku' => 'TEE-1-B', 'price' => 40, 'stock' => 5]);

        $this->assertSame('33.00', $inherits->fresh()->price, 'inheriting variant resolves to base price');
        $this->assertSame('40.00', $override->fresh()->price, 'override wins');
        $this->assertNull($inherits->fresh()->priceOverride(), 'inheriting variant reports no override');
        $this->assertSame('40.00', $override->fresh()->priceOverride(), 'override is readable raw');
        $this->assertFalse($inherits->fresh()->hasPriceOverride());
        $this->assertTrue($override->fresh()->hasPriceOverride());

        // Move the base price: inheriting variant follows, override does not.
        $product->update(['base_price' => 50]);
        $this->assertSame('50.00', $inherits->fresh()->price, 'inheriting variant follows the base price');
        $this->assertSame('40.00', $override->fresh()->price, 'override is untouched');

        // Reading through the parent relation must not need extra queries.
        $loaded = Product::with('variants')->find($product->id);
        $this->assertTrue($loaded->variants->first()->relationLoaded('product'), 'chaperone back-links the parent');
        $this->assertSame('50.00', $loaded->variants->firstWhere('sku', 'TEE-1-R')->price);
    }

    public function test_migration_backfill_turns_copied_prices_into_inheritance(): void
    {
        $store = $this->store();
        $product = Product::create([
            'store_id' => $store->id, 'name' => 'Mug', 'slug' => 'mug-1',
            'base_price' => 12, 'sku' => 'MUG-1', 'product_type' => 'physical',
            'status' => true, 'meta' => [],
        ]);
        // Simulate pre-migration rows: one copy of the base price, one genuine override.
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'MUG-1-A', 'price' => 12, 'stock' => 1]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'MUG-1-B', 'price' => 18, 'stock' => 1]);

        DB::table('product_variants')
            ->whereIn('id', function ($q) {
                $q->select('product_variants.id')->from('product_variants')
                    ->join('products', 'products.id', '=', 'product_variants.product_id')
                    ->whereColumn('product_variants.price', 'products.base_price');
            })->update(['price' => null]);

        $a = ProductVariant::where('sku', 'MUG-1-A')->first();
        $b = ProductVariant::where('sku', 'MUG-1-B')->first();
        $this->assertNull($a->priceOverride(), 'copy became inheritance');
        $this->assertSame('12.00', $a->price, 'and still resolves to the same money');
        $this->assertSame('18.00', $b->priceOverride(), 'real override survived');
    }
}
