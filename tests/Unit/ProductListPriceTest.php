<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\ProductListPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductListPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_variants_use_the_product_base_price(): void
    {
        [$product] = $this->storeWithProduct(12.5);
        $product->setRelation('variants', collect());

        $list = ProductListPrice::forProduct($product, collect(), 'USD');

        $this->assertFalse($list['mixed']);
        $this->assertTrue($list['uniform']);
        $this->assertSame(12.5, $list['min']);
        $this->assertSame(12.5, $list['max']);
        $this->assertSame(0, $list['variant_count']);
        $this->assertSame('USD 12.50', $list['display']);
    }

    public function test_inheriting_variants_follow_the_product_base_price(): void
    {
        [$product] = $this->storeWithProduct(33);
        $variant = $product->variants()->first();
        $variant->update(['price' => null]);
        $product->unsetRelation('variants');
        $product->load(['store:id,currency', 'variants']);

        $list = ProductListPrice::forProduct($product);

        $this->assertFalse($list['mixed']);
        $this->assertSame(33.0, $list['min']);
        $this->assertSame('USD 33.00', $list['display']);
    }

    public function test_mixed_option_prices_render_as_a_range(): void
    {
        [$product] = $this->storeWithProduct(10);
        $first = $product->variants()->first();
        $first->update(['price' => 50]);
        $product->variants()->create([
            'sku' => $product->sku.'-L',
            'price' => 1500,
            'stock' => 4,
            'stock_alert' => 0,
        ]);
        $product->unsetRelation('variants');
        $product->load(['store:id,currency', 'variants.options']);

        $list = ProductListPrice::forProduct($product);

        $this->assertTrue($list['mixed']);
        $this->assertSame(50.0, $list['min']);
        $this->assertSame(1500.0, $list['max']);
        $this->assertSame(2, $list['variant_count']);
        $this->assertSame('USD 50.00 – 1,500.00', $list['display']);
        $this->assertSame('USD 50.00 – 1,500.00', $list['list_display']);
        $this->assertFalse($list['compact_list']);
    }

    public function test_large_mixed_catalogs_use_a_from_price_on_the_list(): void
    {
        [$product] = $this->storeWithProduct(10);
        $product->variants()->first()->update(['price' => 4.5]);
        for ($i = 2; $i <= 10; $i++) {
            $product->variants()->create([
                'sku' => $product->sku.'-'.$i,
                'price' => 4.5 + $i,
                'stock' => 1,
                'stock_alert' => 0,
            ]);
        }
        $product->unsetRelation('variants');
        $product->load(['store:id,currency', 'variants.options']);

        $list = ProductListPrice::forProduct($product);

        $this->assertTrue($list['compact_list']);
        $this->assertTrue($list['mixed']);
        $this->assertSame(10, $list['variant_count']);
        $this->assertSame('From USD 4.50', $list['list_display']);
        $this->assertStringContainsString('4.50', $list['display']);
        $this->assertStringContainsString('14.50', $list['display']);
    }

    /**
     * @return array{0: Product, 1: Store}
     */
    private function storeWithProduct(float $basePrice): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => 'Price List Store',
            'slug' => 'price-list-'.Str::random(6),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Priced',
            'slug' => 'priced-'.Str::random(6),
            'description' => null,
            'base_price' => $basePrice,
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => null,
            'stock' => 1,
            'stock_alert' => 0,
        ]);

        return [$product->fresh(['store', 'variants']), $store];
    }
}
