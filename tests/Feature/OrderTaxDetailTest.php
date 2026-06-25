<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DraftOrder;
use App\Models\DraftTaxLine;
use App\Models\Order;
use App\Models\OrderTaxLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTaxDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculated_exclusive_order_renders_item_and_shipping_tax_lines(): void
    {
        [$owner, $store, $order] = $this->calculatedOrderFromDraft(shippingTaxable: true);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Tax details')
            ->assertSeeText('Calculated by platform')
            ->assertSeeText('Items')
            ->assertSeeText('Shipping')
            ->assertSeeText('Tax added to prices');
    }

    public function test_calculated_inclusive_order_renders_included_tax_message(): void
    {
        [$owner, $store, $order] = $this->calculatedOrderFromDraft(
            settingsOverrides: ['prices_include_tax' => true],
            productPrice: 22,
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Tax is included in item prices');
    }

    public function test_zero_percent_calculated_line_remains_visible_on_order_detail(): void
    {
        $owner = $this->merchant('order-zero-tax@example.test');
        $store = $this->store($owner, 'Order Zero Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);
        $this->enableTax($store, rates: [[
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'Zero TX',
            'rate_percent' => '0.0000',
        ]]);

        $draft = $this->createCalculatedDraft($owner, $store, $variant);
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->with('taxLines')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('0.0000%');
    }

    public function test_manual_order_renders_manual_source_without_fake_lines(): void
    {
        $owner = $this->merchant('order-manual-tax@example.test');
        $store = $this->store($owner, 'Order Manual Tax Store');
        [, $variant] = $this->product($store, ['price' => 20, 'stock' => 5]);

        $draft = $this->createDraft($owner, $store, $variant, [
            'tax_total' => '4.50',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Entered manually')
            ->assertSeeText('No calculated rate breakdown is available for manual tax')
            ->assertDontSeeText('Matched rate');
    }

    public function test_external_order_renders_external_source_and_preserved_total(): void
    {
        $owner = $this->merchant('order-external-tax@example.test');
        $store = $this->store($owner, 'Order External Tax Store');
        $customer = $this->customer($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#EXT-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 100,
            'shipping' => 10,
            'tax' => 7.25,
            'discount' => 0,
            'total' => 117.25,
            'grand_total' => 117.25,
            'currency_code' => 'USD',
            'order_source' => 'external_checkout',
            'channel' => 'external',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Preserved from external checkout')
            ->assertSeeText('7.25')
            ->assertSeeText('did not recalculate');
    }

    public function test_legacy_order_without_snapshot_renders_safely(): void
    {
        $owner = $this->merchant('order-legacy-tax@example.test');
        $store = $this->store($owner, 'Order Legacy Tax Store');
        $customer = $this->customer($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#LEG-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 50,
            'shipping' => 0,
            'tax' => 3.50,
            'discount' => 0,
            'total' => 53.50,
            'grand_total' => 53.50,
            'currency_code' => 'USD',
            'order_source' => 'platform_checkout',
            'channel' => 'platform',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Tax total only')
            ->assertSeeText('3.50');
    }

    public function test_manual_order_with_zero_tax_renders_entered_manually(): void
    {
        $owner = $this->merchant('order-zero-manual-tax@example.test');
        $store = $this->store($owner, 'Order Zero Manual Tax Store');
        $customer = $this->customer($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#MAN-0-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 50,
            'shipping' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 50,
            'grand_total' => 50,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Entered manually')
            ->assertDontSeeText('No tax recorded');
    }

    public function test_external_order_with_zero_tax_renders_external_source(): void
    {
        $owner = $this->merchant('order-zero-external-tax@example.test');
        $store = $this->store($owner, 'Order Zero External Tax Store');
        $customer = $this->customer($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#EXT-0-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 50,
            'shipping' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 50,
            'grand_total' => 50,
            'currency_code' => 'USD',
            'order_source' => 'external_checkout',
            'channel' => 'external',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Preserved from external checkout')
            ->assertDontSeeText('No tax recorded');
    }

    public function test_true_no_tax_order_renders_no_tax_recorded(): void
    {
        $owner = $this->merchant('order-no-tax@example.test');
        $store = $this->store($owner, 'Order No Tax Store');
        $customer = $this->customer($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#NONE-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 50,
            'shipping' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 50,
            'grand_total' => 50,
            'currency_code' => 'USD',
            'order_source' => 'platform_checkout',
            'channel' => 'platform',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('No tax recorded');
    }

    public function test_another_store_cannot_view_order_tax_details(): void
    {
        [$owner, $store, $order] = $this->calculatedOrderFromDraft();
        $otherOwner = $this->merchant('other-order-tax@example.test');
        $otherStore = $this->store($otherOwner, 'Other Order Tax Store');

        $this->actingAs($otherOwner)
            ->withSession(['current_store_id' => $otherStore->id])
            ->get(route('orderViewDetails', $order))
            ->assertNotFound();
    }

    public function test_get_order_details_does_not_mutate_order_tax_data(): void
    {
        [$owner, $store, $order] = $this->calculatedOrderFromDraft(shippingTaxable: true);
        $before = $order->fresh()->getAttributes();
        $lineCountBefore = OrderTaxLine::query()->where('order_id', $order->id)->count();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk();

        $after = $order->fresh()->getAttributes();
        $this->assertSame($before['tax'], $after['tax']);
        $this->assertSame($before['updated_at'], $after['updated_at']);
        $this->assertSame($lineCountBefore, OrderTaxLine::query()->where('order_id', $order->id)->count());
    }

    /**
     * @param  array<string, mixed>  $settingsOverrides
     * @return array{0: User, 1: Store, 2: Order}
     */
    private function calculatedOrderFromDraft(
        array $settingsOverrides = [],
        bool $shippingTaxable = false,
        float $productPrice = 20,
    ): array {
        $owner = $this->merchant('order-calculated-tax@example.test');
        $store = $this->store($owner, 'Order Calculated Tax Store '.Str::random(4));
        [, $variant] = $this->product($store, ['price' => $productPrice, 'stock' => 5]);
        $this->enableTax($store, array_merge(['shipping_taxable' => $shippingTaxable], $settingsOverrides));

        $draft = $this->createCalculatedDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.convert', $draft))
            ->assertRedirect();

        $order = Order::query()->where('store_id', $store->id)->with('taxLines')->firstOrFail();

        return [$owner, $store, $order];
    }

    private function createCalculatedDraft(User $owner, Store $store, ProductVariant $variant): DraftOrder
    {
        $draft = $this->createDraft($owner, $store, $variant);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.calculate-tax', $draft), $this->draftPayload($variant))
            ->assertRedirect();

        return $draft->fresh(['taxLines']);
    }

    private function createDraft(User $owner, Store $store, ProductVariant $variant, array $overrides = []): DraftOrder
    {
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('draft-orders.store'), $this->draftPayload($variant, $overrides))
            ->assertRedirect();

        return DraftOrder::query()->where('store_id', $store->id)->latest('id')->firstOrFail();
    }

    private function draftPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_name' => 'Order Tax Buyer',
            'customer_email' => 'order.tax@example.test',
            'shipping_name' => 'Order Tax Buyer',
            'shipping_address_line1' => '10 Tax Road',
            'shipping_city' => 'Austin',
            'shipping_state' => 'TX',
            'shipping_postal_code' => '73301',
            'shipping_country' => 'US',
            'billing_same_as_shipping' => '1',
            'shipping_total' => '5.00',
            'tax_total' => '0.00',
            'discount_total' => '0.00',
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'unit_price' => (string) $variant->price,
            ]],
        ], $overrides);
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

        if ($rates === []) {
            $rates = [[
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'TX Sales Tax',
                'rate_percent' => '10.0000',
            ]];
        }

        foreach ($rates as $rate) {
            TaxRate::query()->create(array_merge([
                'store_id' => $store->id,
                'country_code' => 'US',
                'region_code' => 'TX',
                'name' => 'TX Sales Tax',
                'rate_percent' => '10.0000',
                'priority' => 100,
                'is_active' => true,
            ], $rate));
        }

        return $settings->fresh();
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }

    private function store(User $owner, string $name): Store
    {
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
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        return $store;
    }

    private function customer(Store $store): Customer
    {
        return Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Tax Detail Buyer',
            'status' => 'active',
        ]);
    }

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Order Tax Product',
            'slug' => 'order-tax-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 20,
            'sku' => 'ORDER-TAX-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => $overrides['price'] ?? 20,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }
}
