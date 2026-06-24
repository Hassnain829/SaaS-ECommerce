<?php

namespace Tests\Feature;

use App\Models\Checkout;
use App\Models\CheckoutTaxLine;
use App\Models\Order;
use App\Models\OrderTaxLine;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5RTaxSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_settings_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tax_settings'));
        $this->assertTrue(Schema::hasColumns('tax_settings', [
            'id',
            'store_id',
            'enabled',
            'prices_include_tax',
            'default_product_taxable',
            'shipping_taxable',
            'calculation_address',
            'settings_version',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_tax_rates_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tax_rates'));
        $this->assertTrue(Schema::hasColumns('tax_rates', [
            'id',
            'store_id',
            'country_code',
            'region_code',
            'name',
            'rate_percent',
            'priority',
            'is_active',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_checkout_tax_lines_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('checkout_tax_lines'));
        $this->assertTrue(Schema::hasColumns('checkout_tax_lines', [
            'id',
            'store_id',
            'checkout_id',
            'tax_rate_id',
            'jurisdiction_country_code',
            'jurisdiction_region_code',
            'rate_percent',
            'taxable_amount',
            'tax_amount',
            'applies_to',
            'settings_version',
            'calculated_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_order_tax_lines_table_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('order_tax_lines'));
        $this->assertTrue(Schema::hasColumns('order_tax_lines', [
            'id',
            'store_id',
            'order_id',
            'tax_rate_id',
            'jurisdiction_country_code',
            'jurisdiction_region_code',
            'rate_percent',
            'taxable_amount',
            'tax_amount',
            'applies_to',
            'settings_version',
            'calculated_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_products_table_has_is_taxable_column(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'is_taxable'));
    }

    public function test_one_tax_settings_row_per_store_is_enforced(): void
    {
        $store = $this->store();

        $this->assertSame(1, TaxSetting::query()->where('store_id', $store->id)->count());

        $this->expectException(QueryException::class);

        TaxSetting::query()->create([
            'store_id' => $store->id,
            'enabled' => true,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
            'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
            'settings_version' => 2,
        ]);
    }

    public function test_duplicate_country_region_within_store_is_rejected(): void
    {
        $store = $this->store();

        $this->createTaxRate($store, [
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'California',
        ]);

        $this->expectException(QueryException::class);

        $this->createTaxRate($store, [
            'country_code' => 'us',
            'region_code' => 'ca',
            'name' => 'California duplicate',
        ]);
    }

    public function test_country_wide_rate_uses_empty_region_string(): void
    {
        $store = $this->store();

        $rate = $this->createTaxRate($store, [
            'country_code' => 'US',
            'region_code' => '',
            'name' => 'US Country Wide',
        ]);

        $rate->refresh();

        $this->assertSame('', $rate->region_code);
        $this->assertNotNull($rate->getRawOriginal('region_code'));
        $this->assertSame('', $rate->getRawOriginal('region_code'));
    }

    public function test_same_jurisdiction_allowed_across_stores(): void
    {
        $storeA = $this->store('Store A Tax');
        $storeB = $this->store('Store B Tax');

        $rateA = $this->createTaxRate($storeA, [
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'Texas A',
        ]);

        $rateB = $this->createTaxRate($storeB, [
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'Texas B',
        ]);

        $this->assertNotSame($rateA->id, $rateB->id);
        $this->assertSame('US', $rateA->country_code);
        $this->assertSame('US', $rateB->country_code);
        $this->assertSame('TX', $rateA->region_code);
        $this->assertSame('TX', $rateB->region_code);
    }

    public function test_tax_rate_for_store_scope_never_leaks_other_store(): void
    {
        $storeA = $this->store('Scoped Store A');
        $storeB = $this->store('Scoped Store B');

        $this->createTaxRate($storeA, ['name' => 'Store A Rate']);
        $this->createTaxRate($storeB, ['name' => 'Store B Rate']);

        $storeARates = TaxRate::query()->forStore($storeA->id)->get();
        $storeBRates = TaxRate::query()->forStore($storeB->id)->get();

        $this->assertCount(1, $storeARates);
        $this->assertCount(1, $storeBRates);
        $this->assertSame('Store A Rate', $storeARates->first()->name);
        $this->assertSame('Store B Rate', $storeBRates->first()->name);
    }

    public function test_checkout_tax_lines_belong_to_correct_store_and_checkout(): void
    {
        [$store, $checkout] = $this->checkoutFixture();
        $rate = $this->createTaxRate($store);

        $line = CheckoutTaxLine::query()->create([
            'store_id' => $store->id,
            'checkout_id' => $checkout->id,
            'tax_rate_id' => $rate->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => 'CA',
            'rate_percent' => '8.2500',
            'taxable_amount' => '100.00',
            'tax_amount' => '8.25',
            'applies_to' => CheckoutTaxLine::APPLIES_TO_ITEMS,
            'settings_version' => 1,
            'calculated_at' => now(),
        ]);

        $line->load(['store', 'checkout', 'taxRate']);

        $this->assertTrue($checkout->taxLines->contains($line));
        $this->assertTrue($store->checkoutTaxLines->contains($line));
        $this->assertSame($store->id, $line->store->id);
        $this->assertSame($checkout->id, $line->checkout->id);
        $this->assertSame($rate->id, $line->taxRate->id);
    }

    public function test_order_tax_lines_belong_to_correct_store_and_order(): void
    {
        [$store, $order] = $this->orderFixture();
        $rate = $this->createTaxRate($store);

        $line = OrderTaxLine::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'tax_rate_id' => $rate->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => '',
            'rate_percent' => '10.0000',
            'taxable_amount' => '50.00',
            'tax_amount' => '5.00',
            'applies_to' => OrderTaxLine::APPLIES_TO_SHIPPING,
            'settings_version' => 2,
            'calculated_at' => now(),
        ]);

        $line->load(['store', 'order', 'taxRate']);

        $this->assertTrue($order->taxLines->contains($line));
        $this->assertTrue($store->orderTaxLines->contains($line));
        $this->assertSame($store->id, $line->store->id);
        $this->assertSame($order->id, $line->order->id);
        $this->assertSame($rate->id, $line->taxRate->id);
    }

    public function test_deleting_checkout_deletes_checkout_tax_lines(): void
    {
        [$store, $checkout] = $this->checkoutFixture();
        $line = CheckoutTaxLine::query()->create([
            'store_id' => $store->id,
            'checkout_id' => $checkout->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => '',
            'rate_percent' => '5.0000',
            'taxable_amount' => '20.00',
            'tax_amount' => '1.00',
            'applies_to' => CheckoutTaxLine::APPLIES_TO_ITEMS,
            'settings_version' => 1,
            'calculated_at' => now(),
        ]);

        $checkout->forceDelete();

        $this->assertDatabaseMissing('checkout_tax_lines', ['id' => $line->id]);
    }

    public function test_deleting_order_deletes_order_tax_lines(): void
    {
        [$store, $order] = $this->orderFixture();
        $line = OrderTaxLine::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => '',
            'rate_percent' => '5.0000',
            'taxable_amount' => '20.00',
            'tax_amount' => '1.00',
            'applies_to' => OrderTaxLine::APPLIES_TO_ITEMS,
            'settings_version' => 1,
            'calculated_at' => now(),
        ]);

        $order->delete();

        $this->assertDatabaseMissing('order_tax_lines', ['id' => $line->id]);
    }

    public function test_deleting_tax_rate_nulls_tax_rate_id_on_tax_lines(): void
    {
        $store = $this->store('Rate Null Store');
        [, $checkout] = $this->checkoutFixtureForStore($store);
        [, $order] = $this->orderFixtureForStore($store);

        $rate = $this->createTaxRate($store);

        $checkoutLine = CheckoutTaxLine::query()->create([
            'store_id' => $store->id,
            'checkout_id' => $checkout->id,
            'tax_rate_id' => $rate->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => '',
            'rate_percent' => '7.5000',
            'taxable_amount' => '10.00',
            'tax_amount' => '0.75',
            'applies_to' => CheckoutTaxLine::APPLIES_TO_ITEMS,
            'settings_version' => 1,
            'calculated_at' => now(),
        ]);

        $orderLine = OrderTaxLine::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'tax_rate_id' => $rate->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => '',
            'rate_percent' => '7.5000',
            'taxable_amount' => '10.00',
            'tax_amount' => '0.75',
            'applies_to' => OrderTaxLine::APPLIES_TO_ITEMS,
            'settings_version' => 1,
            'calculated_at' => now(),
        ]);

        $rate->delete();

        $checkoutLine->refresh();
        $orderLine->refresh();

        $this->assertNull($checkoutLine->tax_rate_id);
        $this->assertNull($orderLine->tax_rate_id);
        $this->assertSame('7.5000', $checkoutLine->rate_percent);
        $this->assertSame('0.75', $orderLine->tax_amount);
    }

    public function test_deleting_store_cascades_tax_entities(): void
    {
        $store = $this->store('Cascade Tax Store');
        [, $checkout] = $this->checkoutFixtureForStore($store);
        [, $order] = $this->orderFixtureForStore($store);

        $settings = TaxSetting::query()->where('store_id', $store->id)->firstOrFail();
        $settingsId = $settings->id;

        $rate = $this->createTaxRate($store);

        $checkoutLineId = CheckoutTaxLine::query()->create([
            'store_id' => $store->id,
            'checkout_id' => $checkout->id,
            'tax_rate_id' => $rate->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => '',
            'rate_percent' => '6.0000',
            'taxable_amount' => '15.00',
            'tax_amount' => '0.90',
            'applies_to' => CheckoutTaxLine::APPLIES_TO_ITEMS,
            'settings_version' => 1,
            'calculated_at' => now(),
        ])->id;

        $orderLineId = OrderTaxLine::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'tax_rate_id' => $rate->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => '',
            'rate_percent' => '6.0000',
            'taxable_amount' => '15.00',
            'tax_amount' => '0.90',
            'applies_to' => OrderTaxLine::APPLIES_TO_ITEMS,
            'settings_version' => 1,
            'calculated_at' => now(),
        ])->id;

        $store->delete();

        $this->assertDatabaseMissing('tax_settings', ['id' => $settingsId]);
        $this->assertDatabaseMissing('tax_rates', ['id' => $rate->id]);
        $this->assertDatabaseMissing('checkout_tax_lines', ['id' => $checkoutLineId]);
        $this->assertDatabaseMissing('order_tax_lines', ['id' => $orderLineId]);
    }

    public function test_boolean_and_decimal_casts_return_expected_values(): void
    {
        $store = $this->store();

        $settings = TaxSetting::query()->where('store_id', $store->id)->firstOrFail();
        $settings->update([
            'enabled' => true,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
            'settings_version' => 3,
        ]);
        $settings->refresh();

        $this->assertIsBool($settings->enabled);
        $this->assertTrue($settings->enabled);
        $this->assertFalse($settings->prices_include_tax);
        $this->assertSame(3, $settings->settings_version);

        $rate = $this->createTaxRate($store, [
            'country_code' => 'us',
            'region_code' => ' ny ',
            'rate_percent' => '8.1250',
            'is_active' => 1,
        ]);

        $this->assertSame('US', $rate->country_code);
        $this->assertSame('NY', $rate->region_code);
        $this->assertSame('8.1250', $rate->rate_percent);
        $this->assertTrue($rate->is_active);

        [$store, $checkout] = $this->checkoutFixture();

        $line = CheckoutTaxLine::query()->create([
            'store_id' => $store->id,
            'checkout_id' => $checkout->id,
            'jurisdiction_country_code' => 'US',
            'jurisdiction_region_code' => '',
            'rate_percent' => '8.1250',
            'taxable_amount' => '123.45',
            'tax_amount' => '10.03',
            'applies_to' => CheckoutTaxLine::APPLIES_TO_ITEMS,
            'settings_version' => 1,
            'calculated_at' => now(),
        ]);

        $this->assertSame('8.1250', $line->rate_percent);
        $this->assertSame('123.45', $line->taxable_amount);
        $this->assertSame('10.03', $line->tax_amount);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $line->calculated_at);
    }

    public function test_existing_products_default_to_taxable_after_migration(): void
    {
        $store = $this->store();

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Legacy Product',
            'slug' => 'legacy-product-'.Str::random(6),
            'base_price' => 19.99,
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $product->refresh();

        $this->assertTrue($product->is_taxable);
    }

    public function test_new_products_receive_database_default_is_taxable_true(): void
    {
        $store = $this->store();

        $productId = Product::query()->insertGetId([
            'store_id' => $store->id,
            'name' => 'Default Taxable Product',
            'slug' => 'default-taxable-'.Str::random(6),
            'base_price' => 9.99,
            'product_type' => 'physical',
            'status' => true,
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->findOrFail($productId);

        $this->assertTrue($product->is_taxable);
    }

    public function test_schema_migrations_work_on_sqlite_testing_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));

        $store = $this->store();

        $this->createTaxRate($store, [
            'country_code' => 'US',
            'region_code' => '',
            'name' => 'SQLite Country Wide',
        ]);

        $this->createTaxRate($store, [
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'SQLite Regional',
        ]);

        $this->assertSame(2, TaxRate::query()->forStore($store->id)->count());
    }

    private function store(string $name = 'Tax Schema Store'): Store
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);

        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTaxRate(Store $store, array $overrides = []): TaxRate
    {
        return TaxRate::query()->create(array_merge([
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => '',
            'name' => 'Default Rate',
            'rate_percent' => '8.2500',
            'priority' => 100,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @return array{0: Store, 1: Checkout}
     */
    private function checkoutFixture(): array
    {
        $store = $this->store('Checkout Tax Store');

        return $this->checkoutFixtureForStore($store);
    }

    /**
     * @return array{0: Store, 1: Checkout}
     */
    private function checkoutFixtureForStore(Store $store): array
    {
        $checkout = Checkout::query()->create([
            'store_id' => $store->id,
            'checkout_number' => 'CHK-'.Str::random(8),
            'source_channel' => 'dev_storefront',
            'mode' => 'platform_checkout',
            'status' => Checkout::STATUS_PAYMENT_PENDING,
            'currency_code' => 'USD',
            'subtotal' => 20,
            'tax_total' => 0,
            'grand_total' => 20,
        ]);

        return [$store, $checkout];
    }

    /**
     * @return array{0: Store, 1: Order}
     */
    private function orderFixture(): array
    {
        $store = $this->store('Order Tax Store');

        return $this->orderFixtureForStore($store);
    }

    /**
     * @return array{0: Store, 1: Order}
     */
    private function orderFixtureForStore(Store $store): array
    {
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#'.random_int(10000, 99999),
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'customer_email' => 'tax-order@example.test',
            'billing_same_as_shipping' => true,
            'subtotal' => 30,
            'total' => 30,
            'grand_total' => 30,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 0,
            'total_quantity' => 0,
            'placed_at' => now(),
        ]);

        return [$store, $order];
    }
}
