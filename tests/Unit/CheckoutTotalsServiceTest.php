<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\Checkout\CheckoutTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTotalsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_usd_exclusive_twenty_at_ten_percent_grand_total_is_twenty_two(): void
    {
        [$store, $variant] = $this->fixture('USD', 20);
        $settings = $this->enableTax($store, rates: [[
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'TX Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = app(CheckoutTotalsService::class)->calculate(
            $store,
            $settings,
            'USD',
            [['variant' => $variant, 'quantity' => 1]],
            '0',
            ['country' => 'US', 'state' => 'TX'],
        );

        $this->assertSame('20.00', $result->subtotal);
        $this->assertSame('2.00', $result->itemsTax);
        $this->assertSame('2.00', $result->taxTotal);
        $this->assertSame('22.00', $result->grandTotal);
        $this->assertFalse($result->pricesIncludeTax);
    }

    public function test_jpy_exclusive_one_thousand_at_ten_percent_tax_is_one_hundred(): void
    {
        [$store, $variant] = $this->fixture('JPY', 1000);
        $settings = $this->enableTax($store, rates: [[
            'country_code' => 'JP',
            'region_code' => '',
            'name' => 'JP Consumption',
            'rate_percent' => '10.0000',
        ]]);

        $result = app(CheckoutTotalsService::class)->calculate(
            $store,
            $settings,
            'JPY',
            [['variant' => $variant, 'quantity' => 1]],
            '0',
            ['country' => 'JP'],
        );

        $this->assertSame('1000', $result->subtotal);
        $this->assertSame('100', $result->itemsTax);
        $this->assertSame('100', $result->taxTotal);
        $this->assertSame('1100', $result->grandTotal);
    }

    public function test_normalize_destination_rejects_invalid_country_code_and_full_country_name(): void
    {
        $service = app(CheckoutTotalsService::class);

        $destination = $service->normalizeDestinationFromAddress([
            'country_code' => 'USA',
            'country' => 'United States',
            'state' => 'TX',
        ]);

        $this->assertSame('', $destination->countryCode);
        $this->assertSame('TX', $destination->regionCode);
    }

    /**
     * @return array{0: Store, 1: ProductVariant}
     */
    private function fixture(string $currency, float|int $price): array
    {
        $owner = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);

        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Checkout Totals Store',
            'slug' => 'checkout-totals-'.Str::random(6),
            'currency' => $currency,
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Totals Product',
            'slug' => 'totals-product-'.Str::random(6),
            'base_price' => $price,
            'sku' => 'TOT-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => $price,
            'stock' => 10,
        ]);
        $variant->load('product');

        return [$store, $variant];
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     */
    private function enableTax(Store $store, array $settingsOverrides = [], array $rates = []): TaxSetting
    {
        $settings = $store->taxSetting;
        $settings->update(array_merge([
            'enabled' => true,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
        ], $settingsOverrides));

        foreach ($rates as $rate) {
            TaxRate::query()->create(array_merge([
                'store_id' => $store->id,
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'Default Rate',
                'rate_percent' => '10.0000',
                'priority' => 100,
                'is_active' => true,
            ], $rate));
        }

        return $settings->fresh();
    }
}
