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
use App\Models\InventoryReservation;
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
use App\Support\ExchangeLifecycle;
use App\Support\OrderLifecycle;
use App\Support\RefundLifecycle;
use App\Support\ReturnLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7FinalCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_mismatch_cannot_finalize_or_persist_account_mode(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '50.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true,
            providerAccountId: 'acct_original'
        );

        $this->bindProvider(fn () => new PaymentRefundResult(
            providerRefundId: 're_wrong',
            status: 'succeeded',
            amount: '50.00',
            amountMinor: 5000,
            currencyCode: 'USD',
            mode: 'live',
            providerAccountId: 'acct_other',
        ));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['idempotency_key' => 'mismatch-1'])
            ->assertSessionHasErrors('payment');

        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_FAILED, $refund->status);
        $this->assertNotSame('live', $refund->mode);
        $this->assertNotSame('acct_other', $refund->provider_account_id);
        $this->assertSame('0.00', number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', ''));
        $this->assertSame(OrderLifecycle::PAYMENT_PAID, $order->fresh()->payment_status);
    }

    public function test_requires_capture_without_capture_is_rejected(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '40.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true,
            paymentIntentStatus: 'requires_capture'
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [])
            ->assertSessionHasErrors('payment');
    }

    public function test_platform_without_captured_evidence_cannot_use_grand_total_fallback(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '40.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: false
        );
        $order->update([
            'meta' => ['channel_ownership' => ['payment_owner' => 'platform']],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [])
            ->assertSessionHasErrors('payment');
    }

    public function test_three_sequential_awkward_cent_refunds_reconcile_exactly(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '10.00', quantity: 3);
        $item->update([
            'subtotal' => '10.00',
            'discount_amount' => '0.01',
            'tax_amount' => '0.02',
            'total' => '10.01',
            'unit_price' => '3.3333',
            'quantity' => 3,
        ]);
        $order->update(['subtotal' => '10.00', 'discount' => '0.01', 'tax' => '0.02', 'grand_total' => '10.01', 'total' => '10.01']);

        foreach (['a', 'b', 'c'] as $key) {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->post(route('orders.refunds.store', $order), [
                    'items' => [$item->id => 1],
                    'processed_externally' => 1,
                    'idempotency_key' => 'seq-'.$key,
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        }

        $refunds = Refund::query()->where('order_id', $order->id)->with('items')->orderBy('id')->get();
        $this->assertCount(3, $refunds);

        $sub = 0;
        $disc = 0;
        $tax = 0;
        $total = 0;
        foreach ($refunds as $refund) {
            $line = $refund->items->first();
            $this->assertSame(
                (int) round(((float) $line->subtotal - (float) $line->discount_amount + (float) $line->tax_amount) * 100),
                (int) $line->total_minor
            );
            $sub += (int) round((float) $line->subtotal * 100);
            $disc += (int) round((float) $line->discount_amount * 100);
            $tax += (int) round((float) $line->tax_amount * 100);
            $total += (int) $line->total_minor;
        }

        $this->assertSame(1000, $sub);
        $this->assertSame(1, $disc);
        $this->assertSame(2, $tax);
        $this->assertSame(1001, $total);
    }

    public function test_jpy_sequential_allocation_is_exact(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '1000', quantity: 3, currency: 'JPY');
        $item->update([
            'subtotal' => '1000',
            'discount_amount' => '10',
            'tax_amount' => '20',
            'total' => '1010',
            'unit_price' => '333',
        ]);
        $order->update(['subtotal' => '1000', 'discount' => '10', 'tax' => '20', 'grand_total' => '1010', 'total' => '1010']);

        foreach ([1, 2, 3] as $n) {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->post(route('orders.refunds.store', $order), [
                    'items' => [$item->id => 1],
                    'processed_externally' => 1,
                    'idempotency_key' => 'jpy-'.$n,
                ])
                ->assertRedirect();
        }

        $sum = (int) Refund::query()->where('order_id', $order->id)->sum('amount_minor');
        $this->assertSame(1010, $sum);
    }

    public function test_explicit_amount_breakdown_mismatch_is_rejected(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '50.00', quantity: 2);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'items' => [$item->id => 1],
                'amount' => '10.00',
                'processed_externally' => 1,
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_same_refund_key_same_payload_returns_one_record(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(grandTotal: '30.00');

        $payload = [
            'amount' => '10.00',
            'processed_externally' => 1,
            'idempotency_key' => 'same-refund',
        ];

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), $payload)->assertRedirect();
        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '10.0',
                'processed_externally' => 1,
                'idempotency_key' => 'same-refund',
            ])->assertRedirect();

        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_same_exchange_key_same_payload_returns_one_record_and_conflict_rejected(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 2);
        $this->location($store);
        [, $replacement] = $this->product($store, 'Alt', 'ALT-1', 40);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($replacement->fresh(), 5);

        $payload = [
            'order_item_id' => $item->id,
            'quantity' => 1,
            'replacement_variant_id' => $replacement->id,
            'idempotency_key' => 'ex-same',
        ];

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), $payload)->assertRedirect();
        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), $payload)->assertRedirect();

        $this->assertSame(1, Exchange::query()->where('idempotency_key', 'ex-same')->count());

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 2,
                'replacement_variant_id' => $replacement->id,
                'idempotency_key' => 'ex-same',
            ])
            ->assertSessionHasErrors('idempotency_key');
    }

    public function test_provider_retry_reuses_exact_provider_idempotency_key(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '40.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $seen = [];
        $this->app->bind(\App\Services\Payments\StripePlatformPaymentProvider::class, function () use (&$seen) {
            return new class($seen) implements PaymentProviderInterface
            {
                public function __construct(private array &$seen) {}

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
                    $this->seen[] = $options['idempotency_key'] ?? null;
                    if (count($this->seen) === 1) {
                        throw new \RuntimeException('temporary outage');
                    }

                    return new PaymentRefundResult(
                        providerRefundId: 're_ok',
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

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['idempotency_key' => 'retry-prov'])
            ->assertSessionHasErrors('payment');

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['idempotency_key' => 'retry-prov'])
            ->assertRedirect();

        $this->assertCount(2, $seen);
        $this->assertNotNull($seen[0]);
        $this->assertSame($seen[0], $seen[1]);
        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_forms_contain_idempotency_keys_and_shipping_tax_field(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(grandTotal: '40.00');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('name="shipping_tax_amount"', false)
            ->assertSee('name="tax_amount"', false)
            ->assertSee('name="other_amount"', false);
    }

    public function test_shipping_tax_adjustment_works_through_controller(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(grandTotal: '55.00');
        $order->update(['shipping' => '5.00', 'shipping_tax' => '0.50', 'tax' => '4.50', 'grand_total' => '55.00']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'shipping_tax_amount' => '0.50',
                'amount' => '0.50',
                'processed_externally' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertTrue($refund->adjustments()->where('type', RefundLifecycle::ADJUSTMENT_SHIPPING_TAX)->exists());
    }

    public function test_expensive_exchange_collection_visible_and_overcollection_rejected(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00');
        $this->location($store);
        [, $expensive] = $this->product($store, 'Expensive', 'EXP-2', 60);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($expensive->fresh(), 5);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $expensive->id,
            ])->assertRedirect();

        $exchange = Exchange::query()->where('order_id', $order->id)->latest('id')->firstOrFail();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSee('Remaining balance due')
            ->assertSee('collection_reference');

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.collect', $exchange), [
                'collected_amount' => bcadd((string) $exchange->balance_due, '1.00', 2),
                'collection_method' => 'manual',
                'collection_reference' => 'CASH-X',
            ])
            ->assertSessionHasErrors('collected_amount');

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.collect', $exchange), [
                'collected_amount' => $exchange->balance_due,
                'collection_method' => 'manual',
                'collection_reference' => 'CASH-OK',
            ])
            ->assertRedirect();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange->fresh()))
            ->assertRedirect();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.collect', $exchange->fresh()), [
                'collected_amount' => '1.00',
                'collection_method' => 'manual',
                'collection_reference' => 'LATE',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_cheaper_exchange_cannot_skip_refund_and_failure_preserves_reservation(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        $this->location($store);
        [, $cheap] = $this->product($store, 'Cheap', 'CHP-2', 10);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($cheap->fresh(), 5);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $cheap->id,
                'idempotency_key' => 'cheap-final',
            ])->assertRedirect();

        $exchange = Exchange::query()->where('idempotency_key', 'cheap-final')->firstOrFail();
        $reservationId = $exchange->items()->where('direction', 'inbound')->value('inventory_reservation_id');
        $this->assertNotNull($reservationId);

        $this->mock(RefundService::class, function ($mock): void {
            $mock->shouldReceive('refundOrder')->once()->andReturn(new Refund([
                'status' => RefundLifecycle::STATUS_FAILED,
                'refund_number' => 'RFN-X',
                'amount' => '10.00',
                'currency_code' => 'USD',
            ]));
        });

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange))
            ->assertSessionHasErrors('refund');

        $exchange = $exchange->fresh(['items']);
        $this->assertSame(ExchangeLifecycle::STATUS_RESERVED, $exchange->status);
        $this->assertSame(
            (int) $reservationId,
            (int) $exchange->items()->where('direction', 'inbound')->value('inventory_reservation_id')
        );
        $this->assertTrue(
            InventoryReservation::query()->whereKey($reservationId)->where('status', InventoryReservation::STATUS_ACTIVE)->exists()
        );
    }

    public function test_cancel_during_processing_blocks_orphan_refund_path(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        $this->location($store);
        [, $cheap] = $this->product($store, 'Cheap2', 'CHP-3', 10);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($cheap->fresh(), 5);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $cheap->id,
            ])->assertRedirect();

        $exchange = Exchange::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
        $exchange->update(['status' => ExchangeLifecycle::STATUS_PROCESSING]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.cancel', $exchange))
            ->assertSessionHasErrors('status');

        $this->assertSame(ExchangeLifecycle::STATUS_PROCESSING, $exchange->fresh()->status);
    }

    public function test_damaged_return_cannot_become_sellable_via_restock_route(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '50.00', quantity: 1);
        $location = $this->location($store);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 1]])
            ->assertRedirect();

        $return = OrderReturn::query()->where('order_id', $order->id)->firstOrFail();
        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('returns.approve', $return), ['items' => [$item->id => 1]])
            ->assertRedirect();
        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('returns.receive', $return), [
                'items' => [
                    $item->id => [
                        'received_quantity' => 1,
                        'condition' => ReturnLifecycle::CONDITION_DAMAGED,
                        'restock' => 0,
                    ],
                ],
            ])->assertRedirect();

        $returnItem = $return->items()->firstOrFail();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('returns.restock', $return), [
                'items' => [
                    $returnItem->id => [
                        'condition' => ReturnLifecycle::CONDITION_SELLABLE,
                        'restock' => 1,
                        'restock_location_id' => $location->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(ReturnLifecycle::CONDITION_DAMAGED, $returnItem->fresh()->condition);
        $this->assertSame(0, (int) $returnItem->fresh()->restocked_quantity);
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovement::TYPE_RETURN_RESTOCK)->count());
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
    }

    private function seedPaidOrder(
        string $grandTotal = '100.00',
        int $quantity = 2,
        string $orderSource = 'external_checkout',
        bool $withPaymentIntent = false,
        string $currency = 'USD',
        string $paymentIntentStatus = 'succeeded',
        ?string $providerAccountId = null,
    ): array {
        $owner = $this->merchant(fake()->unique()->safeEmail());
        $store = $this->store($owner, 'Phase 7 Final '.Str::random(4), $currency);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [$product, $variant] = $this->product($store);
        $customer = $this->customer($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#'.random_int(9000, 9999),
            'status' => OrderLifecycle::ORDER_COMPLETED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_FULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => $grandTotal,
            'total' => $grandTotal,
            'grand_total' => $grandTotal,
            'currency_code' => $currency,
            'order_source' => $orderSource,
            'placed_at' => now(),
        ]);

        $unit = $currency === 'JPY'
            ? (string) intdiv((int) $grandTotal, max(1, $quantity))
            : bcdiv($grandTotal, (string) $quantity, 4);

        $item = $order->items()->create([
            'store_id' => $store->id,
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
                'provider_account_id' => $providerAccountId,
                'status' => $paymentIntentStatus,
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

    private function store(User $owner, string $name, string $currency = 'USD'): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => $currency,
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
            'full_name' => 'Final Correction Buyer',
            'status' => 'active',
        ]);
    }

    private function product(Store $store, string $name = 'Item', string $sku = 'SKU', float|int|string $price = 20): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
            'product_type' => 'physical',
            'has_variants' => false,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $sku.'-'.Str::random(4),
            'price' => $price,
            'stock' => 10,
            'is_default' => true,
            'is_active' => true,
        ]);

        return [$product, $variant];
    }
}
