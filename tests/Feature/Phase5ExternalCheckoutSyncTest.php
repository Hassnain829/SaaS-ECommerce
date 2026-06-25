<?php

namespace Tests\Feature;

use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderTaxLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5ExternalCheckoutSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_order_sync_requires_storefront_token(): void
    {
        $this->postJson('/api/v1/external/orders', [])
            ->assertUnauthorized();
    }

    public function test_external_paid_order_creates_snapshots_events_and_deducts_inventory(): void
    {
        [$store, $token] = $this->tokenedStore('External Checkout Store');
        [, $variant] = $this->product($store, ['price' => 12, 'stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-10001',
                'external_checkout_reference' => 'checkout-10001',
                'payment_reference' => 'pay-10001',
            ]))
            ->assertCreated()
            ->assertJsonPath('order.order_number', '#1001')
            ->assertJsonPath('order.external_order_number', 'WEB-10001')
            ->assertJsonPath('order.payment_status', OrderLifecycle::PAYMENT_PAID)
            ->assertJsonPath('order.status', OrderLifecycle::ORDER_CONFIRMED)
            ->assertJsonPath('order.total', '28.00');

        $this->assertSame(3, (int) $variant->fresh()->stock);

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'store_id' => $store->id,
            'email' => 'external.buyer@example.test',
            'full_name' => 'External Buyer',
            'source' => 'external_checkout',
            'total_orders' => 1,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'store_id' => $store->id,
            'order_source' => 'external_checkout',
            'channel' => 'api',
            'external_order_number' => 'WEB-10001',
            'external_checkout_reference' => 'checkout-10001',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-10001',
            'subtotal' => 24.00,
            'shipping' => 4.50,
            'tax' => 1.50,
            'discount' => 2.00,
            'grand_total' => 28.00,
        ]);

        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'type' => 'shipping',
            'address_line1' => '45 External Road',
            'city' => 'Austin',
            'country' => 'US',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 12.00,
            'total' => 24.00,
        ]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_EXTERNAL_ORDER_RECEIVED,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_PAYMENT_STATUS_RECORDED,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_ORDER_RESERVED,
            'source' => 'external_checkout',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovement::TYPE_ORDER_DEDUCTED,
            'source' => 'external_checkout',
        ]);
    }

    public function test_idempotency_key_returns_cached_order_and_rejects_different_payload(): void
    {
        [$store, $token] = $this->tokenedStore('External Idempotency Store');
        [, $variant] = $this->product($store, ['stock' => 5]);
        $payload = $this->payload($variant, ['external_order_number' => 'WEB-IDEMPOTENT']);

        $this->withHeaders(['Idempotency-Key' => 'sync-key-1'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.order_number', '#1001');

        $this->withHeaders(['Idempotency-Key' => 'sync-key-1'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.order_number', '#1001');

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
        $this->assertSame(1, IdempotencyKey::query()->where('store_id', $store->id)->count());

        $changed = $payload;
        $changed['payment_reference'] = 'different-reference';

        $this->withHeaders(['Idempotency-Key' => 'sync-key-1'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $changed)
            ->assertStatus(409)
            ->assertJsonPath('message', 'This Idempotency-Key was already used for a different request.');
    }

    public function test_duplicate_external_order_number_returns_existing_or_conflict(): void
    {
        [$store, $token] = $this->tokenedStore('External Duplicate Store');
        [, $variant] = $this->product($store, ['stock' => 5]);
        $payload = $this->payload($variant, ['external_order_number' => 'WEB-DUP']);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('created', true);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('order.order_number', '#1001');

        $changed = $payload;
        $changed['items'][0]['quantity'] = 1;

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $changed)
            ->assertStatus(409);

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->count());
    }

    public function test_external_order_rejects_raw_card_data_and_cross_store_variant(): void
    {
        [$store, $token] = $this->tokenedStore('External Safe Store');
        [$otherStore] = $this->tokenedStore('External Other Store');
        [, $variant] = $this->product($store);
        [, $otherVariant] = $this->product($otherStore);

        $withCard = $this->payload($variant, ['external_order_number' => 'WEB-CARD']);
        $withCard['payment'] = ['card_number' => '4242424242424242'];

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $withCard)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment'])
            ->assertJsonPath('errors.payment.0', 'Raw payment card data must not be sent to this API.');

        $crossStore = $this->payload($otherVariant, ['external_order_number' => 'WEB-CROSS-STORE']);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $crossStore)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.variant_id']);

        $this->assertSame(0, Order::query()->where('store_id', $store->id)->count());
    }

    public function test_pending_external_payment_maps_to_pending_order(): void
    {
        [$store, $token] = $this->tokenedStore('External Pending Store');
        [, $variant] = $this->product($store, ['stock' => 3]);

        $payload = $this->payload($variant, [
            'external_order_number' => 'WEB-COD',
            'payment_status' => 'cod_pending',
            'payment_method' => 'cash_on_delivery',
            'payment_gateway' => 'cash_on_delivery',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.status', OrderLifecycle::ORDER_PENDING)
            ->assertJsonPath('order.payment_status', OrderLifecycle::PAYMENT_PENDING);

        $this->assertSame(1, (int) $variant->fresh()->stock);
    }

    public function test_external_order_is_visible_in_order_list_and_detail(): void
    {
        [$store, $token, $owner] = $this->tokenedStore('External Dashboard Store');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-DASH',
                'payment_reference' => 'pay-dashboard',
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertSeeText('External checkout')
            ->assertSeeText('External WEB-DASH')
            ->assertSeeText('External Test')
            ->assertSeeText('Paid');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Payment status recorded from external checkout.')
            ->assertSeeText('WEB-DASH')
            ->assertSeeText('External Test')
            ->assertSeeText('pay-dashboard');
    }

    public function test_missing_shipping_address_fields_return_validation_errors(): void
    {
        [$store, $token] = $this->tokenedStore('External Address Store');
        [, $variant] = $this->product($store);
        $payload = $this->payload($variant);
        unset($payload['shipping_address']['address_line1'], $payload['shipping_address']['city'], $payload['shipping_address']['country']);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shipping_address.address_line1',
                'shipping_address.city',
                'shipping_address.country',
            ]);
    }

    public function test_external_checkout_tax_totals_are_preserved_and_never_recalculated_locally(): void
    {
        $this->mock(TaxCalculator::class, function ($mock): void {
            $mock->shouldReceive('calculate')->never();
        });

        [$store, $token] = $this->tokenedStore('External Tax Preservation Store');
        [$product, $variant] = $this->product($store, [
            'price' => 10,
            'stock' => 10,
            'is_taxable' => false,
        ]);

        $store->taxSetting->update(['enabled' => false]);
        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-TAX-DISABLED',
                'external_checkout_reference' => 'checkout-tax-disabled',
                'totals' => [
                    'subtotal' => 20.00,
                    'shipping' => 3.21,
                    'tax' => 4.56,
                    'discount' => 1.23,
                    'grand_total' => 26.54,
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('order.total', '26.54');

        $store->taxSetting->update([
            'enabled' => true,
            'prices_include_tax' => true,
            'shipping_taxable' => true,
            'default_product_taxable' => true,
        ]);
        TaxRate::query()->create([
            'store_id' => $store->id,
            'country_code' => 'US',
            'region_code' => 'TX',
            'name' => 'Ignored TX Rate',
            'rate_percent' => '30.0000',
            'priority' => 100,
            'is_active' => true,
        ]);
        $product->update(['is_taxable' => false]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-TAX-ENABLED',
                'external_checkout_reference' => 'checkout-tax-enabled',
                'totals' => [
                    'subtotal' => 20.00,
                    'shipping' => 5.55,
                    'tax' => 6.66,
                    'discount' => 2.22,
                    'grand_total' => 29.99,
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('order.total', '29.99');

        TaxRate::query()->where('store_id', $store->id)->update(['rate_percent' => '45.0000']);
        $store->taxSetting->update(['prices_include_tax' => false]);

        $this->assertDatabaseHas('orders', [
            'store_id' => $store->id,
            'external_order_number' => 'WEB-TAX-DISABLED',
            'subtotal' => 20.00,
            'shipping' => 3.21,
            'tax' => 4.56,
            'discount' => 1.23,
            'grand_total' => 26.54,
        ]);
        $this->assertDatabaseHas('orders', [
            'store_id' => $store->id,
            'external_order_number' => 'WEB-TAX-ENABLED',
            'subtotal' => 20.00,
            'shipping' => 5.55,
            'tax' => 6.66,
            'discount' => 2.22,
            'grand_total' => 29.99,
        ]);
        $this->assertSame(0, OrderTaxLine::query()->where('store_id', $store->id)->count());

        $payload = $this->payload($variant, [
            'external_order_number' => 'WEB-TAX-IDEMPOTENT',
            'external_checkout_reference' => 'checkout-tax-idempotent',
            'totals' => [
                'subtotal' => 20.00,
                'shipping' => 4.50,
                'tax' => 8.88,
                'discount' => 2.00,
                'grand_total' => 31.38,
            ],
        ]);

        $this->withHeaders(['Idempotency-Key' => 'external-tax-key'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.total', '31.38');

        $this->withHeaders(['Idempotency-Key' => 'external-tax-key'])
            ->withToken($token)
            ->postJson('/api/v1/external/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.total', '31.38');

        $this->assertSame(1, Order::query()->where('store_id', $store->id)->where('external_order_number', 'WEB-TAX-IDEMPOTENT')->count());
    }

    private function tokenedStore(string $name): array
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

        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $token, $owner];
    }

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'External Product',
            'slug' => 'external-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'EXT-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => $overrides['is_taxable'] ?? true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
            'price' => $overrides['price'] ?? 12,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }

    private function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'external_order_number' => 'WEB-'.Str::upper(Str::random(8)),
            'external_checkout_reference' => 'checkout-'.Str::random(8),
            'payment_status' => 'paid',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-'.Str::random(8),
            'placed_at' => now()->toISOString(),
            'currency_code' => 'USD',
            'shipping_total' => 4.50,
            'tax_total' => 1.50,
            'discount_total' => 2.00,
            'discounts' => [
                ['code' => 'WELCOME', 'amount' => 2.00],
            ],
            'notes' => 'Synced from an external checkout.',
            'customer' => [
                'full_name' => 'External Buyer',
                'email' => 'external.buyer@example.test',
                'phone' => '+15550199',
            ],
            'shipping_address' => [
                'name' => 'External Buyer',
                'address_line1' => '45 External Road',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'phone' => '+15550199',
            ],
            'billing_address' => [
                'same_as_shipping' => true,
            ],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => '12.00',
                    'external_line_id' => 'line-1',
                ],
            ],
        ], $overrides);
    }
}
