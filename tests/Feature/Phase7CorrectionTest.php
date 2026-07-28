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
use App\Services\RefundService;
use App\Support\OrderLifecycle;
use App\Support\RefundLifecycle;
use App\Support\ReturnLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7CorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_provider_refund_does_not_apply_financial_state(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '80.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $this->bindProvider(fn () => new PaymentRefundResult(
            providerRefundId: 're_pending',
            status: 'pending',
            amount: '80.00',
            amountMinor: 8000,
            currencyCode: 'USD',
            mode: 'test',
            providerAccountId: null,
        ));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['idempotency_key' => 'pend-1'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_PROCESSING, $refund->status);
        $this->assertSame('0.00', number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', ''));
        $this->assertSame(OrderLifecycle::PAYMENT_PAID, $order->fresh()->payment_status);
        $this->assertSame(OrderLifecycle::ORDER_COMPLETED, $order->fresh()->status);
    }

    public function test_conflicting_idempotency_payload_is_rejected(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(grandTotal: '50.00');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '10.00',
                'processed_externally' => 1,
                'idempotency_key' => 'same-key',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '20.00',
                'processed_externally' => 1,
                'idempotency_key' => 'same-key',
            ])
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_failed_same_record_retry_reuses_provider_idempotency_key(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '40.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $calls = 0;
        $this->app->bind(\App\Services\Payments\StripePlatformPaymentProvider::class, function () use (&$calls) {
            return new class($calls) implements PaymentProviderInterface
            {
                public function __construct(private int &$calls) {}

                public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
                {
                    throw new \RuntimeException('unused');
                }

                public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult
                {
                    throw new \RuntimeException('unused');
                }

                public function updatePaymentIntentAmount(string $providerIntentId, int $amountMinor, string $currencyCode, array $options = []): PaymentIntentUpdateResult
                {
                    throw new \RuntimeException('unused');
                }

                public function createRefund(PaymentIntent $paymentIntent, int $amountMinor, string $currencyCode, array $options = []): PaymentRefundResult
                {
                    $this->calls++;
                    if ($this->calls === 1) {
                        throw new \RuntimeException('temporary provider outage');
                    }

                    return new PaymentRefundResult(
                        providerRefundId: 're_retry',
                        status: 'succeeded',
                        amount: '40.00',
                        amountMinor: 4000,
                        currencyCode: 'USD',
                        mode: 'test',
                    );
                }

                public function retrieveRefund(string $providerRefundId, PaymentIntent $paymentIntent, array $options = []): PaymentRefundResult
                {
                    throw new \RuntimeException('unused');
                }

                public function verifyWebhook(string $payload, string $signature, string $mode = 'test'): PaymentWebhookResult
                {
                    throw new \RuntimeException('unused');
                }

                public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
                {
                    throw new \RuntimeException('unused');
                }
            };
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['idempotency_key' => 'retry-key'])
            ->assertSessionHasErrors('payment');

        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
        $this->assertSame(RefundLifecycle::STATUS_FAILED, Refund::query()->first()->status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['idempotency_key' => 'retry-key'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, Refund::query()->first()->status);
        $this->assertSame(OrderLifecycle::ORDER_REFUNDED, $order->fresh()->status);
    }

    public function test_pending_allocation_blocks_concurrent_over_refund(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '100.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $this->bindProvider(fn () => new PaymentRefundResult(
            providerRefundId: 're_hold',
            status: 'pending',
            amount: '70.00',
            amountMinor: 7000,
            currencyCode: 'USD',
            mode: 'test',
        ));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '70.00',
                'idempotency_key' => 'hold-70',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '40.00',
                'idempotency_key' => 'over-40',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_discounted_partial_refund_and_jpy_zero_decimal(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '90.00', quantity: 2);
        $item->update([
            'subtotal' => '100.00',
            'discount_amount' => '10.00',
            'tax_amount' => '0.00',
            'total' => '90.00',
            'unit_price' => '50.00',
        ]);
        $order->update(['subtotal' => '100.00', 'discount' => '10.00', 'grand_total' => '90.00', 'total' => '90.00']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'items' => [$item->id => 1],
                'processed_externally' => 1,
                'idempotency_key' => 'disc-1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();
        $refundItem = $refund->items()->firstOrFail();
        $this->assertGreaterThan(0, (float) $refundItem->discount_amount);
        $this->assertSame((int) $refund->amount_minor, (int) $refundItem->total_minor);

        [$ownerJpy, $storeJpy, $orderJpy] = $this->seedPaidOrder(
            grandTotal: '1000',
            currency: 'JPY',
            quantity: 1
        );

        $this->actingAs($ownerJpy)
            ->withSession(['current_store_id' => $storeJpy->id])
            ->post(route('orders.refunds.store', $orderJpy), [
                'amount' => '250',
                'processed_externally' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(250, (int) Refund::query()->where('order_id', $orderJpy->id)->value('amount_minor'));
        $this->assertSame(OrderLifecycle::PAYMENT_PARTIALLY_REFUNDED, $orderJpy->fresh()->payment_status);
        $this->assertSame(OrderLifecycle::ORDER_COMPLETED, $orderJpy->fresh()->status);
    }

    public function test_cancelled_paid_order_can_refund_without_changing_cancelled_status(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(grandTotal: '55.00');
        $order->update(['status' => OrderLifecycle::ORDER_CANCELLED, 'cancelled_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['processed_externally' => 1])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderLifecycle::ORDER_CANCELLED, $order->fresh()->status);
        $this->assertSame(OrderLifecycle::PAYMENT_REFUNDED, $order->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->refunded_at);
    }

    public function test_platform_missing_payment_intent_cannot_fallback(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '30.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: false
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [])
            ->assertSessionHasErrors('payment');
    }

    public function test_unpaid_order_cannot_be_refunded(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(grandTotal: '30.00');
        $order->update(['payment_status' => OrderLifecycle::PAYMENT_PENDING]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['processed_externally' => 1])
            ->assertSessionHasErrors('payment');
    }

    public function test_partial_approval_receive_and_non_sellable_restock(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '50.00', quantity: 3);
        $location = $this->location($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 3]])
            ->assertRedirect();

        $return = OrderReturn::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.approve', $return), [
                'items' => [$item->id => 1],
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.receive', $return), [
                'items' => [
                    $item->id => [
                        'received_quantity' => 2,
                        'condition' => ReturnLifecycle::CONDITION_SELLABLE,
                        'restock' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.receive', $return), [
                'items' => [
                    $item->id => [
                        'received_quantity' => 1,
                        'condition' => ReturnLifecycle::CONDITION_DAMAGED,
                        'restock' => 1,
                        'restock_location_id' => $location->id,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $returnItem = $return->items()->first();
        $this->assertFalse((bool) $returnItem->restock);
        $this->assertSame(0, (int) $returnItem->restocked_quantity);
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RETURN_RESTOCK)->count());
    }

    public function test_expensive_exchange_requires_collection_before_complete(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00');
        $this->location($store);
        [, $expensive] = $this->product($store, 'Expensive', 'EXP-1', 60);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($expensive->fresh(), 5);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $expensive->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $expensiveExchange = Exchange::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
        $this->assertTrue(bccomp((string) $expensiveExchange->balance_due, '0', 2) > 0);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $expensiveExchange))
            ->assertSessionHasErrors('balance_due');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.collect', $expensiveExchange), [
                'collected_amount' => $expensiveExchange->balance_due,
                'collection_method' => 'manual',
                'collection_reference' => 'CASH-1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $expensiveExchange->fresh()))
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('completed', $expensiveExchange->fresh()->status);
    }

    public function test_cheaper_exchange_refund_failure_blocks_complete(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        $this->location($store);
        [, $cheap] = $this->product($store, 'Cheap', 'CHP-1', 10);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($cheap->fresh(), 5);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $cheap->id,
                'idempotency_key' => 'cheap-ex',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $cheapExchange = Exchange::query()->where('idempotency_key', 'cheap-ex')->firstOrFail();
        $priorStatus = $cheapExchange->status;

        $failedRefund = new Refund([
            'status' => RefundLifecycle::STATUS_FAILED,
            'refund_number' => 'RFN-FAIL',
            'amount' => '10.00',
            'currency_code' => 'USD',
        ]);

        $this->mock(RefundService::class, function ($mock) use ($failedRefund): void {
            $mock->shouldReceive('refundOrder')->once()->andReturn($failedRefund);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $cheapExchange))
            ->assertSessionHasErrors('refund');

        $this->assertSame($priorStatus, $cheapExchange->fresh()->status);
        $this->assertNotSame('completed', $cheapExchange->fresh()->status);
    }

    public function test_cross_order_return_id_and_duplicate_active_exchange_quantity(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        [$owner2, $store2, $otherOrder] = $this->seedPaidOrder(grandTotal: '20.00', quantity: 1);
        // Force same store for cross-order check inside same store
        $otherOrder->update(['store_id' => $store->id]);
        $otherReturn = OrderReturn::query()->create([
            'store_id' => $store->id,
            'order_id' => $otherOrder->id,
            'customer_id' => $otherOrder->customer_id,
            'return_number' => 'RMA-X',
            'status' => ReturnLifecycle::STATUS_REQUESTED,
            'source' => 'merchant',
            'requested_at' => now(),
        ]);

        [, $replacement] = $this->product($store, 'Swap', 'SWAP-1', 40);
        $this->location($store);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($replacement->fresh(), 5);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $replacement->id,
                'return_id' => $otherReturn->id,
            ])
            ->assertSessionHasErrors('return_id');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $replacement->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $replacement->id,
                'idempotency_key' => 'dup-ex',
            ])
            ->assertSessionHasErrors('quantity');
    }

    public function test_external_order_create_rejects_refunded_payment_statuses(): void
    {
        $controller = app(\App\Http\Controllers\Api\ExternalOrderSyncController::class);
        $method = new \ReflectionMethod($controller, 'validatedPayload');
        $method->setAccessible(true);

        $request = \Illuminate\Http\Request::create('/api/external/orders', 'POST', [
            'payment_status' => 'refunded',
            'currency_code' => 'USD',
            'customer' => ['email' => 'buyer@example.test'],
            'items' => [['sku' => 'X', 'quantity' => 1, 'unit_price' => 10, 'name' => 'X']],
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $method->invoke($controller, $request);
    }

    private function bindProvider(callable $resultFactory): void
    {
        $this->app->bind(\App\Services\Payments\StripePlatformPaymentProvider::class, function () use ($resultFactory) {
            return new class($resultFactory) implements PaymentProviderInterface
            {
                public function __construct(private $resultFactory) {}

                public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
                {
                    throw new \RuntimeException('unused');
                }

                public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult
                {
                    throw new \RuntimeException('unused');
                }

                public function updatePaymentIntentAmount(string $providerIntentId, int $amountMinor, string $currencyCode, array $options = []): PaymentIntentUpdateResult
                {
                    throw new \RuntimeException('unused');
                }

                public function createRefund(PaymentIntent $paymentIntent, int $amountMinor, string $currencyCode, array $options = []): PaymentRefundResult
                {
                    return ($this->resultFactory)($paymentIntent, $amountMinor, $currencyCode, $options);
                }

                public function retrieveRefund(string $providerRefundId, PaymentIntent $paymentIntent, array $options = []): PaymentRefundResult
                {
                    return ($this->resultFactory)($paymentIntent, 0, 'USD', $options);
                }

                public function verifyWebhook(string $payload, string $signature, string $mode = 'test'): PaymentWebhookResult
                {
                    throw new \RuntimeException('unused');
                }

                public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
                {
                    throw new \RuntimeException('unused');
                }
            };
        });
    }

    private function seedPaidOrder(
        string $grandTotal = '100.00',
        int $quantity = 2,
        string $orderSource = 'external_checkout',
        bool $withPaymentIntent = false,
        string $currency = 'USD',
    ): array {
        $owner = $this->merchant(fake()->unique()->safeEmail());
        $store = $this->store($owner, 'Phase 7 Correction '.Str::random(4));
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [$product, $variant] = $this->product($store);
        $customer = $this->customer($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#'.random_int(8000, 8999),
            'status' => OrderLifecycle::ORDER_COMPLETED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_FULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => $grandTotal,
            'total' => $grandTotal,
            'grand_total' => $grandTotal,
            'refunded_total' => 0,
            'currency_code' => $currency,
            'order_source' => $orderSource,
            'channel' => $orderSource === 'platform_checkout' ? 'platform' : 'external',
            'item_count' => 1,
            'total_quantity' => $quantity,
            'placed_at' => now(),
            'meta' => $orderSource === 'external_checkout'
                ? ['channel_ownership' => ['payment_owner' => 'external', 'inventory_owner' => 'platform']]
                : ['platform_checkout' => ['checkout_number' => 'CHK-1'], 'channel_ownership' => ['payment_owner' => 'platform', 'inventory_owner' => 'platform']],
        ]);

        $unit = $currency === 'JPY'
            ? (string) intdiv((int) $grandTotal, max(1, $quantity))
            : bcdiv($grandTotal, (string) $quantity, 2);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => 'Size: M',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => $quantity,
            'unit_price' => $unit,
            'subtotal' => $grandTotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => $grandTotal,
        ]);

        if ($withPaymentIntent) {
            PaymentIntent::query()->create([
                'store_id' => $store->id,
                'order_id' => $order->id,
                'provider' => 'stripe',
                'mode' => 'test',
                'provider_intent_id' => 'pi_'.Str::random(8),
                'status' => 'succeeded',
                'currency_code' => $currency,
                'amount' => $grandTotal,
                'amount_minor' => $currency === 'JPY' ? (int) $grandTotal : (int) bcmul($grandTotal, '100', 0),
            ]);
        }

        app(CustomerMetricsService::class)->recalculate($customer);

        return [$owner, $store, $order, $item, $customer];
    }

    private function location(Store $store): Location
    {
        return Location::query()->create([
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
            'full_name' => 'Correction Buyer',
            'status' => 'active',
        ]);
    }

    private function product(Store $store, string $name = 'Correction Tee', string $sku = 'C-M', float|int $price = 25): array
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
