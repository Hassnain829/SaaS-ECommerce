<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentIntentUpdateResult;
use App\Data\Payments\PaymentRefundResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Exchange;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\CustomerMetricsService;
use App\Services\Inventory\InventorySyncService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\OrderLifecycle;
use App\Support\RefundLifecycle;
use App\Support\ReturnLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7RefundRestockExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_full_and_partial_refunds_update_payment_and_order_state(): void
    {
        [$owner, $store, $order, $item, $customer] = $this->seedPaidOrder(grandTotal: '100.00');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '40.00',
                'processed_externally' => 1,
                'reason' => 'Partial goodwill',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('40.00', number_format((float) $order->refunded_total, 2, '.', ''));
        $this->assertSame(OrderLifecycle::PAYMENT_PARTIALLY_REFUNDED, $order->payment_status);
        $this->assertSame(OrderLifecycle::ORDER_COMPLETED, $order->status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'processed_externally' => 1,
                'reason' => 'Remainder',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('100.00', number_format((float) $order->refunded_total, 2, '.', ''));
        $this->assertSame(OrderLifecycle::PAYMENT_REFUNDED, $order->payment_status);
        $this->assertSame(OrderLifecycle::ORDER_REFUNDED, $order->status);
        $this->assertNotNull($order->refunded_at);

        app(CustomerMetricsService::class)->recalculate($customer->fresh());
        $this->assertSame('0.00', number_format((float) $customer->fresh()->total_spent, 2, '.', ''));
        $this->assertSame(1, (int) $customer->fresh()->total_orders);
    }

    public function test_refund_cannot_exceed_remaining_and_is_idempotent(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(grandTotal: '50.00');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '60.00',
                'processed_externally' => 1,
            ])
            ->assertSessionHasErrors('amount');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '25.00',
                'processed_externally' => 1,
                'idempotency_key' => 'refund-key-1',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '25.00',
                'processed_externally' => 1,
                'idempotency_key' => 'refund-key-1',
            ])
            ->assertRedirect();

        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_platform_provider_refund_uses_payment_boundary(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '80.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $this->app->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                throw new \RuntimeException('not used');
            }

            public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult
            {
                throw new \RuntimeException('not used');
            }

            public function updatePaymentIntentAmount(string $providerIntentId, int $amountMinor, string $currencyCode, array $options = []): PaymentIntentUpdateResult
            {
                throw new \RuntimeException('not used');
            }

            public function createRefund(PaymentIntent $paymentIntent, int $amountMinor, string $currencyCode, array $options = []): PaymentRefundResult
            {
                return new PaymentRefundResult(
                    providerRefundId: 're_test_123',
                    status: 'succeeded',
                    amount: '80.00',
                    amountMinor: 8000,
                    currencyCode: 'USD',
                    raw: ['id' => 're_test_123', 'status' => 'succeeded'],
                    mode: 'test',
                );
            }

            public function retrieveRefund(string $providerRefundId, PaymentIntent $paymentIntent, array $options = []): PaymentRefundResult
            {
                throw new \RuntimeException('not used');
            }

            public function verifyWebhook(string $payload, string $signature, string $mode = 'test'): PaymentWebhookResult
            {
                throw new \RuntimeException('not used');
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                throw new \RuntimeException('not used');
            }
        });

        // Manager resolves Stripe via driver(); bind manager driver by swapping Stripe provider class.
        $this->app->bind(StripePlatformPaymentProvider::class, fn () => $this->app->make(PaymentProviderInterface::class));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'processed_externally' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(RefundLifecycle::METHOD_PROVIDER, $refund->method);
        $this->assertSame('re_test_123', $refund->provider_refund_id);
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(OrderLifecycle::ORDER_REFUNDED, $order->fresh()->status);
    }

    public function test_failed_provider_refund_does_not_mark_order_refunded(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '80.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $this->app->bind(StripePlatformPaymentProvider::class, fn () => new class implements PaymentProviderInterface
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                throw new \RuntimeException('not used');
            }

            public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult
            {
                throw new \RuntimeException('not used');
            }

            public function updatePaymentIntentAmount(string $providerIntentId, int $amountMinor, string $currencyCode, array $options = []): PaymentIntentUpdateResult
            {
                throw new \RuntimeException('not used');
            }

            public function createRefund(PaymentIntent $paymentIntent, int $amountMinor, string $currencyCode, array $options = []): PaymentRefundResult
            {
                throw new \RuntimeException('Stripe unavailable');
            }

            public function retrieveRefund(string $providerRefundId, PaymentIntent $paymentIntent, array $options = []): PaymentRefundResult
            {
                throw new \RuntimeException('not used');
            }

            public function verifyWebhook(string $payload, string $signature, string $mode = 'test'): PaymentWebhookResult
            {
                throw new \RuntimeException('not used');
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                throw new \RuntimeException('not used');
            }
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [])
            ->assertSessionHasErrors('payment');

        $this->assertSame(OrderLifecycle::ORDER_COMPLETED, $order->fresh()->status);
        $this->assertSame('0.00', number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', ''));
        $this->assertSame(RefundLifecycle::STATUS_PROCESSING, Refund::query()->where('order_id', $order->id)->value('status'));
    }

    public function test_receive_with_restock_creates_return_restock_movement_once(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '50.00', quantity: 2);
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Returns warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '1 Stock Rd',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $variant = ProductVariant::query()->findOrFail($item->product_variant_id);
        $variant->update(['stock' => 5]);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($variant, 5);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 1]])
            ->assertRedirect();

        $return = OrderReturn::query()->where('order_id', $order->id)->firstOrFail();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.approve', $return))
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.receive', $return), [
                'items' => [
                    $item->id => [
                        'received_quantity' => 1,
                        'condition' => ReturnLifecycle::CONDITION_SELLABLE,
                        'restock' => '1',
                        'restock_location_id' => $location->id,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'movement_type' => StockMovement::TYPE_RETURN_RESTOCK,
            'quantity_change' => 1,
        ]);
        $this->assertSame(1, (int) $return->items()->first()->restocked_quantity);

        // Second restock attempt via restock endpoint should be idempotent.
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.restock', $return), [
                'items' => [
                    $return->items()->first()->id => [
                        'restock' => 1,
                        'restock_location_id' => $location->id,
                        'condition' => ReturnLifecycle::CONDITION_SELLABLE,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovement::TYPE_RETURN_RESTOCK)->count());
    }

    public function test_exchange_reserves_replacement_and_can_complete(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00');
        [$product, $replacement] = $this->product($store, 'Replacement Tee', 'REP-L', 15);

        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '1 Main',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
        $replacement->update(['stock' => 3]);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($replacement, 3);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $replacement->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $exchange = Exchange::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('reserved', $exchange->status);
        $this->assertNotNull($exchange->items()->where('direction', 'inbound')->value('inventory_reservation_id'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange))
            ->assertRedirect();

        $this->assertSame('completed', $exchange->fresh()->status);
    }

    public function test_customer_metrics_use_net_of_partial_refunds(): void
    {
        [$owner, $store, $order, $item, $customer] = $this->seedPaidOrder(grandTotal: '100.00');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '30.00',
                'processed_externally' => 1,
            ])
            ->assertRedirect();

        app(CustomerMetricsService::class)->recalculate($customer->fresh());
        $this->assertSame('70.00', number_format((float) $customer->fresh()->total_spent, 2, '.', ''));
    }

    /**
     * @return array{0: User, 1: Store, 2: Order, 3: OrderItem, 4: Customer}
     */
    private function seedPaidOrder(
        string $grandTotal = '100.00',
        int $quantity = 2,
        string $orderSource = 'external_checkout',
        bool $withPaymentIntent = false,
    ): array {
        $owner = $this->merchant('owner@phase7b.test');
        $store = $this->store($owner, 'Phase 7B Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [$product, $variant] = $this->product($store);
        $customer = $this->customer($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#7101',
            'status' => OrderLifecycle::ORDER_COMPLETED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_FULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => $grandTotal,
            'total' => $grandTotal,
            'grand_total' => $grandTotal,
            'refunded_total' => 0,
            'currency_code' => 'USD',
            'order_source' => $orderSource,
            'channel' => $orderSource === 'platform_checkout' ? 'platform' : 'external',
            'item_count' => 1,
            'total_quantity' => $quantity,
            'placed_at' => now(),
            'meta' => $orderSource === 'external_checkout'
                ? ['channel_ownership' => ['payment_owner' => 'external', 'inventory_owner' => 'platform']]
                : ['platform_checkout' => ['checkout_number' => 'CHK-1']],
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => 'Size: M',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => $quantity,
            'unit_price' => bcdiv($grandTotal, (string) $quantity, 2),
            'subtotal' => $grandTotal,
            'tax_amount' => 0,
            'total' => $grandTotal,
        ]);

        if ($withPaymentIntent) {
            PaymentIntent::query()->create([
                'store_id' => $store->id,
                'order_id' => $order->id,
                'provider' => 'stripe',
                'mode' => 'test',
                'provider_intent_id' => 'pi_test_phase7',
                'status' => 'succeeded',
                'currency_code' => 'USD',
                'amount' => $grandTotal,
                'amount_minor' => (int) bcmul($grandTotal, '100', 0),
            ]);
        }

        app(CustomerMetricsService::class)->recalculate($customer);

        return [$owner, $store, $order, $item, $customer];
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);
    }

    private function customer(Store $store): Customer
    {
        return Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Phase 7 Buyer',
            'status' => 'active',
        ]);
    }

    private function product(Store $store, string $name = 'Phase 7 Tee', string $sku = 'P7-M', float $price = 25): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'base_price' => $price,
            'sku' => $sku,
            'product_type' => 'physical',
            'status' => true,
            'track_inventory' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'price' => $price,
            'stock' => 10,
        ]);

        return [$product, $variant];
    }
}
