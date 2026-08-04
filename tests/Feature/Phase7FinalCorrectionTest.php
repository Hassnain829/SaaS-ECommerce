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
use App\Models\OrderEvent;
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
        $this->assertSame(RefundLifecycle::STATUS_PROCESSING, $refund->status);
        $this->assertTrue((bool) data_get($refund->meta, 'reconciliation_required'));
        $this->assertTrue((bool) data_get($refund->meta, 'provider_mismatch'));
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

    public function test_succeeded_mismatch_keeps_allocation_and_blocks_over_refund(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '100.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true,
            providerAccountId: 'acct_original'
        );

        $this->bindProvider(fn () => new PaymentRefundResult(
            providerRefundId: 're_mismatch',
            status: 'succeeded',
            amount: '100.00',
            amountMinor: 10000,
            currencyCode: 'USD',
            mode: 'live',
            providerAccountId: 'acct_other',
        ));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '100.00',
                'idempotency_key' => 'mismatch-alloc',
            ])
            ->assertSessionHasErrors('payment');

        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_PROCESSING, $refund->status);
        $this->assertTrue((bool) data_get($refund->meta, 'reconciliation_required'));
        $this->assertSame(10000, (int) $refund->amount_minor);
        $this->assertSame('0.00', number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', ''));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '10.00',
                'idempotency_key' => 'second-should-block',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_failed_refund_retry_cannot_exceed_remaining_capture(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '100.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $this->bindProvider(fn () => new PaymentRefundResult(
            providerRefundId: 're_fail_70',
            status: 'failed',
            amount: '70.00',
            amountMinor: 7000,
            currencyCode: 'USD',
            mode: 'test',
            failureMessage: 'Provider declined refund',
        ));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '70.00',
                'idempotency_key' => 'fail-70',
            ])
            ->assertSessionHasErrors('payment');

        $failed = Refund::query()->where('idempotency_key', 'fail-70')->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_FAILED, $failed->status);

        $this->bindProvider(fn () => new PaymentRefundResult(
            providerRefundId: 're_ok_40',
            status: 'succeeded',
            amount: '40.00',
            amountMinor: 4000,
            currencyCode: 'USD',
            mode: 'test',
        ));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '40.00',
                'idempotency_key' => 'ok-40',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, Refund::query()->where('idempotency_key', 'ok-40')->value('status'));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.recheck', [$order, $failed]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(RefundLifecycle::STATUS_FAILED, $failed->fresh()->status);
        $this->assertSame('40.00', number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', ''));
        $this->assertSame(2, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_pending_provider_refund_rechecks_through_existing_refund_action(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '55.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $phase = 0;
        $this->bindProvider(function () use (&$phase) {
            $phase++;
            if ($phase === 1) {
                return new PaymentRefundResult(
                    providerRefundId: 're_pending_55',
                    status: 'pending',
                    amount: '55.00',
                    amountMinor: 5500,
                    currencyCode: 'USD',
                    mode: 'test',
                );
            }

            return new PaymentRefundResult(
                providerRefundId: 're_pending_55',
                status: 'succeeded',
                amount: '55.00',
                amountMinor: 5500,
                currencyCode: 'USD',
                mode: 'test',
            );
        });

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '55.00',
                'idempotency_key' => 'pend-recheck',
            ])
            ->assertRedirect();

        $refund = Refund::query()->where('idempotency_key', 'pend-recheck')->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_PROCESSING, $refund->status);
        $this->assertSame('0.00', number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', ''));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSee('Recheck refund');

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.recheck', [$order, $refund]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refund->fresh()->status);
        $this->assertSame('55.00', number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', ''));
        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, $phase);
    }

    public function test_processing_cheaper_exchange_resumes_with_succeeded_deterministic_refund(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        $this->location($store);
        [, $cheap] = $this->product($store, 'Cheap Resume', 'CHP-R', 10);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($cheap->fresh(), 5);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $cheap->id,
                'idempotency_key' => 'cheap-resume',
            ])->assertRedirect();

        $exchange = Exchange::query()->where('idempotency_key', 'cheap-resume')->firstOrFail();
        $reservationId = $exchange->items()->where('direction', 'inbound')->value('inventory_reservation_id');

        $refund = Refund::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'refund_number' => 'RFN-EX-RESUME',
            'status' => RefundLifecycle::STATUS_SUCCEEDED,
            'method' => RefundLifecycle::METHOD_EXTERNAL,
            'amount' => '30.00',
            'amount_minor' => 3000,
            'currency_code' => 'USD',
            'idempotency_key' => 'exchange_refund_'.$exchange->id,
            'processed_at' => now(),
        ]);

        $exchange->update([
            'status' => ExchangeLifecycle::STATUS_PROCESSING,
            'meta' => [
                'completion_previous_status' => ExchangeLifecycle::STATUS_RESERVED,
            ],
        ]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ExchangeLifecycle::STATUS_COMPLETED, $exchange->fresh()->status);
        $this->assertSame((int) $refund->id, (int) $exchange->fresh()->refund_id);
        $this->assertSame(
            InventoryReservation::STATUS_COMMITTED,
            InventoryReservation::query()->whereKey($reservationId)->value('status')
        );
        $this->assertSame(1, Refund::query()->where('idempotency_key', 'exchange_refund_'.$exchange->id)->count());
    }

    public function test_exchange_finalization_failure_then_retry_commits_once_and_one_refund(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        $this->location($store);
        [, $cheap] = $this->product($store, 'Cheap Retry', 'CHP-T', 10);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($cheap->fresh(), 5);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $cheap->id,
                'idempotency_key' => 'cheap-finalize-retry',
            ])->assertRedirect();

        $exchange = Exchange::query()->where('idempotency_key', 'cheap-finalize-retry')->firstOrFail();
        $reservationId = (int) $exchange->items()->where('direction', 'inbound')->value('inventory_reservation_id');

        $failedOnce = false;
        InventoryReservation::updating(function (InventoryReservation $model) use (&$failedOnce): void {
            if ($failedOnce) {
                return;
            }
            if ($model->isDirty('status') && $model->status === InventoryReservation::STATUS_COMMITTED) {
                $failedOnce = true;
                throw new \RuntimeException('finalize crash');
            }
        });

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange))
            ->assertSessionHasErrors('status');

        $this->assertTrue($failedOnce);
        $this->assertSame(ExchangeLifecycle::STATUS_PROCESSING, $exchange->fresh()->status);
        $this->assertSame(
            InventoryReservation::STATUS_ACTIVE,
            InventoryReservation::query()->whereKey($reservationId)->value('status')
        );

        $refunds = Refund::query()->where('idempotency_key', 'exchange_refund_'.$exchange->id)->get();
        $this->assertCount(1, $refunds);
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refunds->first()->status);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange->fresh()))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ExchangeLifecycle::STATUS_COMPLETED, $exchange->fresh()->status);
        $this->assertSame(
            InventoryReservation::STATUS_COMMITTED,
            InventoryReservation::query()->whereKey($reservationId)->value('status')
        );
        $this->assertSame(1, Refund::query()->where('idempotency_key', 'exchange_refund_'.$exchange->id)->count());
    }

    public function test_processing_exchange_shows_resume_completion_and_completes_via_controller(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        $this->location($store);
        [, $cheap] = $this->product($store, 'Cheap UI', 'CHP-UI', 10);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($cheap->fresh(), 5);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $cheap->id,
                'idempotency_key' => 'cheap-ui-resume',
            ])->assertRedirect();

        $exchange = Exchange::query()->where('idempotency_key', 'cheap-ui-resume')->firstOrFail();

        Refund::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'refund_number' => 'RFN-UI-RESUME',
            'status' => RefundLifecycle::STATUS_SUCCEEDED,
            'method' => RefundLifecycle::METHOD_EXTERNAL,
            'amount' => '30.00',
            'amount_minor' => 3000,
            'currency_code' => 'USD',
            'idempotency_key' => 'exchange_refund_'.$exchange->id,
            'processed_at' => now(),
        ]);

        $exchange->update([
            'status' => ExchangeLifecycle::STATUS_PROCESSING,
            'meta' => ['completion_previous_status' => ExchangeLifecycle::STATUS_RESERVED],
        ]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSee('Resume completion')
            ->assertDontSee(route('exchanges.cancel', $exchange), false);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange->fresh()))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ExchangeLifecycle::STATUS_COMPLETED, $exchange->fresh()->status);
    }

    public function test_terminal_failed_provider_refund_creates_new_deterministic_retry_key(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '50.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $seenKeys = [];
        $creates = 0;
        $retrieves = 0;
        $this->bindProvider(function ($paymentIntent, $amountMinor, $currencyCode, $options = []) use (&$seenKeys, &$creates, &$retrieves) {
            if (! empty($options['retrieve'])) {
                $retrieves++;

                return new PaymentRefundResult(
                    providerRefundId: (string) ($options['provider_refund_id'] ?? 're_old_failed'),
                    status: 'failed',
                    amount: '50.00',
                    amountMinor: 5000,
                    currencyCode: 'USD',
                    mode: 'test',
                    failureMessage: 'still failed',
                );
            }

            $creates++;
            $seenKeys[] = $options['idempotency_key'] ?? null;

            if ($creates === 1) {
                return new PaymentRefundResult(
                    providerRefundId: 're_old_failed',
                    status: 'failed',
                    amount: '50.00',
                    amountMinor: 5000,
                    currencyCode: 'USD',
                    mode: 'test',
                    failureMessage: 'Provider declined refund',
                );
            }

            return new PaymentRefundResult(
                providerRefundId: 're_retry_ok',
                status: 'succeeded',
                amount: '50.00',
                amountMinor: 5000,
                currencyCode: 'USD',
                mode: 'test',
            );
        });

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '50.00',
                'idempotency_key' => 'terminal-retry-key',
            ])
            ->assertSessionHasErrors('payment');

        $refund = Refund::query()->where('idempotency_key', 'terminal-retry-key')->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_FAILED, $refund->status);
        $this->assertSame('re_old_failed', $refund->provider_refund_id);
        $baseKey = (string) $refund->provider_idempotency_key;

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.recheck', [$order, $refund]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund = $refund->fresh();
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refund->status);
        $expectedRetryKey = 'refund_'.$refund->id.':retry:1';
        $this->assertSame($expectedRetryKey, $refund->provider_idempotency_key);
        $this->assertLessThanOrEqual(120, strlen((string) $refund->provider_idempotency_key));
        $this->assertSame(1, (int) data_get($refund->meta, 'provider_retry_attempt'));
        $this->assertSame('re_old_failed', data_get($refund->meta, 'previous_provider_refund_id'));
        $this->assertSame($baseKey, data_get($refund->meta, 'previous_provider_idempotency_key'));
        $this->assertSame($baseKey, data_get($refund->meta, 'provider_idempotency_base'));
        $this->assertSame(2, $creates);
        $this->assertSame(0, $retrieves);
        $this->assertSame([$baseKey, $expectedRetryKey], $seenKeys);
        $this->assertSame(1, Refund::query()->where('idempotency_key', 'terminal-retry-key')->count());
    }

    public function test_uncertain_network_retry_reuses_same_provider_idempotency_key(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '40.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $seenKeys = [];
        $calls = 0;
        $this->bindProvider(function ($paymentIntent, $amountMinor, $currencyCode, $options = []) use (&$seenKeys, &$calls) {
            $calls++;
            $seenKeys[] = $options['idempotency_key'] ?? null;
            if ($calls === 1) {
                throw new \RuntimeException('network blip');
            }

            return new PaymentRefundResult(
                providerRefundId: 're_net_ok',
                status: 'succeeded',
                amount: '40.00',
                amountMinor: 4000,
                currencyCode: 'USD',
                mode: 'test',
            );
        });

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['idempotency_key' => 'uncertain-key'])
            ->assertSessionHasErrors('payment');

        $refund = Refund::query()->where('idempotency_key', 'uncertain-key')->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_PROCESSING, $refund->status);
        $this->assertTrue((bool) data_get($refund->meta, 'provider_uncertain'));
        $firstKey = (string) $refund->provider_idempotency_key;

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.recheck', [$order, $refund]))
            ->assertRedirect();

        $this->assertSame([$firstKey, $firstKey], $seenKeys);
        $this->assertSame($firstKey, $refund->fresh()->provider_idempotency_key);
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refund->fresh()->status);
        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_late_pending_response_cannot_downgrade_succeeded_refund(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '35.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $this->bindProvider(fn () => new PaymentRefundResult(
            providerRefundId: 're_done',
            status: 'succeeded',
            amount: '35.00',
            amountMinor: 3500,
            currencyCode: 'USD',
            mode: 'test',
        ));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '35.00',
                'idempotency_key' => 'late-pending',
            ])
            ->assertRedirect();

        $refund = Refund::query()->where('idempotency_key', 'late-pending')->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refund->status);
        $providerId = $refund->provider_refund_id;
        $mode = $refund->mode;
        $account = $refund->provider_account_id;
        $refundedTotal = number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', '');

        $paymentIntent = PaymentIntent::query()->where('order_id', $order->id)->firstOrFail();
        $service = app(RefundService::class);
        $method = new \ReflectionMethod(RefundService::class, 'handleProviderResult');
        $method->setAccessible(true);
        $result = $method->invoke(
            $service,
            $order->fresh(),
            $refund->fresh(),
            new PaymentRefundResult(
                providerRefundId: 're_late_pending',
                status: 'pending',
                amount: '35.00',
                amountMinor: 3500,
                currencyCode: 'USD',
                mode: 'live',
                providerAccountId: 'acct_other',
            ),
            $paymentIntent,
            $owner,
            null
        );

        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $result->status);
        $this->assertSame($providerId, $result->provider_refund_id);
        $this->assertSame($mode, $result->mode);
        $this->assertSame($account, $result->provider_account_id);
        $this->assertSame($refundedTotal, number_format((float) ($order->fresh()->refunded_total ?: 0), 2, '.', ''));
        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
    }

    public function test_two_completion_calls_create_one_exchange_refund_and_commit_once(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        $this->location($store);
        [, $cheap] = $this->product($store, 'Cheap Once', 'CHP-ONCE', 10);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($cheap->fresh(), 5);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $cheap->id,
                'idempotency_key' => 'cheap-once',
            ])->assertRedirect();

        $exchange = Exchange::query()->where('idempotency_key', 'cheap-once')->firstOrFail();
        $reservationId = (int) $exchange->items()->where('direction', 'inbound')->value('inventory_reservation_id');

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange))
            ->assertRedirect();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('exchanges.complete', $exchange->fresh()))
            ->assertRedirect();

        $this->assertSame(ExchangeLifecycle::STATUS_COMPLETED, $exchange->fresh()->status);
        $this->assertSame(1, Refund::query()->where('idempotency_key', 'exchange_refund_'.$exchange->id)->count());
        $this->assertSame(
            InventoryReservation::STATUS_COMMITTED,
            InventoryReservation::query()->whereKey($reservationId)->value('status')
        );
    }

    public function test_cross_store_refund_recheck_remains_404(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '25.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );
        [$owner2, $store2, $order2] = $this->seedPaidOrder(
            grandTotal: '25.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $this->bindProvider(fn () => new PaymentRefundResult(
            providerRefundId: 're_hold',
            status: 'pending',
            amount: '25.00',
            amountMinor: 2500,
            currencyCode: 'USD',
            mode: 'test',
        ));

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '25.00',
                'idempotency_key' => 'cross-store-recheck',
            ])
            ->assertRedirect();

        $refund = Refund::query()->where('idempotency_key', 'cross-store-recheck')->firstOrFail();

        $this->actingAs($owner2)->withSession(['current_store_id' => $store2->id])
            ->post(route('orders.refunds.recheck', [$order2, $refund]))
            ->assertNotFound();

        $this->actingAs($owner2)->withSession(['current_store_id' => $store2->id])
            ->post(route('orders.refunds.recheck', [$order, $refund]))
            ->assertNotFound();
    }

    public function test_terminal_retry_succeeds_with_120_char_merchant_idempotency_key(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '45.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true
        );

        $merchantKey = str_repeat('k', 120);
        $this->assertSame(120, strlen($merchantKey));

        $creates = 0;
        $seenKeys = [];
        $this->bindProvider(function ($paymentIntent, $amountMinor, $currencyCode, $options = []) use (&$creates, &$seenKeys) {
            if (! empty($options['retrieve'])) {
                return new PaymentRefundResult(
                    providerRefundId: 're_long_failed',
                    status: 'failed',
                    amount: '45.00',
                    amountMinor: 4500,
                    currencyCode: 'USD',
                    mode: 'test',
                    failureMessage: 'still failed',
                );
            }

            $creates++;
            $seenKeys[] = $options['idempotency_key'] ?? null;

            if ($creates === 1) {
                return new PaymentRefundResult(
                    providerRefundId: 're_long_failed',
                    status: 'failed',
                    amount: '45.00',
                    amountMinor: 4500,
                    currencyCode: 'USD',
                    mode: 'test',
                    failureMessage: 'Provider declined refund',
                );
            }

            return new PaymentRefundResult(
                providerRefundId: 're_long_ok',
                status: 'succeeded',
                amount: '45.00',
                amountMinor: 4500,
                currencyCode: 'USD',
                mode: 'test',
            );
        });

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '45.00',
                'idempotency_key' => $merchantKey,
            ])
            ->assertSessionHasErrors('payment');

        $refund = Refund::query()->where('idempotency_key', $merchantKey)->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_FAILED, $refund->status);
        $this->assertSame($merchantKey, $refund->provider_idempotency_key);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.recheck', [$order, $refund]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund = $refund->fresh();
        $retryKey = (string) $refund->provider_idempotency_key;
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame('refund_'.$refund->id.':retry:1', $retryKey);
        $this->assertLessThanOrEqual(120, strlen($retryKey));
        $this->assertSame($merchantKey, data_get($refund->meta, 'provider_idempotency_base'));
        $this->assertSame($merchantKey, data_get($refund->meta, 'previous_provider_idempotency_key'));
        $this->assertSame(1, Refund::query()->where('idempotency_key', $merchantKey)->count());
        $this->assertSame([$merchantKey, $retryKey], $seenKeys);
    }

    public function test_verified_success_clears_mismatch_and_uncertain_reconciliation_flags(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '60.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true,
            providerAccountId: 'acct_original'
        );

        $phase = 0;
        $this->bindProvider(function () use (&$phase) {
            $phase++;
            if ($phase === 1) {
                return new PaymentRefundResult(
                    providerRefundId: 're_mismatch_then_ok',
                    status: 'succeeded',
                    amount: '60.00',
                    amountMinor: 6000,
                    currencyCode: 'USD',
                    mode: 'live',
                    providerAccountId: 'acct_other',
                );
            }

            return new PaymentRefundResult(
                providerRefundId: 're_mismatch_then_ok',
                status: 'succeeded',
                amount: '60.00',
                amountMinor: 6000,
                currencyCode: 'USD',
                mode: 'test',
                providerAccountId: 'acct_original',
            );
        });

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'amount' => '60.00',
                'idempotency_key' => 'clear-flags-key',
            ])
            ->assertSessionHasErrors('payment');

        $refund = Refund::query()->where('idempotency_key', 'clear-flags-key')->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_PROCESSING, $refund->status);
        $this->assertTrue((bool) data_get($refund->meta, 'provider_mismatch'));
        $this->assertTrue((bool) data_get($refund->meta, 'reconciliation_required'));
        $this->assertNotNull(data_get($refund->meta, 'sanitized_error'));

        // Overlay uncertain flag as if a later network recheck left both active.
        $refund->forceFill([
            'meta' => array_merge($refund->meta ?? [], [
                'provider_uncertain' => true,
                'provider_error' => 'temporary outage',
            ]),
        ])->save();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.recheck', [$order, $refund->fresh()]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund = $refund->fresh();
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refund->status);
        $this->assertFalse((bool) data_get($refund->meta, 'provider_uncertain'));
        $this->assertFalse((bool) data_get($refund->meta, 'provider_mismatch'));
        $this->assertFalse((bool) data_get($refund->meta, 'reconciliation_required'));
        $this->assertNull(data_get($refund->meta, 'sanitized_error'));
        $this->assertNull(data_get($refund->meta, 'provider_error'));
        $this->assertSame('re_mismatch_then_ok', $refund->provider_refund_id);
        $this->assertNotNull(data_get($refund->meta, 'provider_result'));
        $this->assertSame(1, Refund::query()->where('idempotency_key', 'clear-flags-key')->count());
    }

    public function test_cancelled_order_hides_and_rejects_record_return(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00');
        $order->update(['status' => OrderLifecycle::ORDER_CANCELLED, 'cancelled_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertDontSeeText('Record return')
            ->assertSeeText('A return cannot be recorded for this order in its current state.');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), [
                'items' => [$item->id => 1],
                'customer_notes' => 'Customer emailed about a return.',
            ])
            ->assertSessionHasErrors('order');
    }

    public function test_pending_cancelled_and_fully_refunded_orders_hide_and_reject_exchanges(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00');
        [, $replacement] = $this->product($store, 'Replacement', 'REP', 15);

        foreach ([
            OrderLifecycle::ORDER_PENDING,
            OrderLifecycle::ORDER_CANCELLED,
            OrderLifecycle::ORDER_REFUNDED,
        ] as $status) {
            $order->update(['status' => $status]);

            $response = $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->get(route('orderViewDetails', $order))
                ->assertOk()
                ->assertDontSeeText('Create exchange')
                ->assertDontSee('name="order_item_id"', false);

            if ($status === OrderLifecycle::ORDER_REFUNDED) {
                $response->assertSeeText('This order has been fully refunded and cannot be exchanged.');
            }

            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->post(route('orders.exchanges.store', $order), [
                    'order_item_id' => $item->id,
                    'quantity' => 1,
                    'replacement_variant_id' => $replacement->id,
                    'idempotency_key' => 'ex-status-'.$status,
                ])
                ->assertSessionHasErrors('order');
        }
    }

    public function test_payment_fully_refunded_blocks_exchange_even_when_order_status_is_not_refunded(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00');
        [, $replacement] = $this->product($store, 'Payment Refund Alt', 'PRA', 16);

        $order->update([
            'status' => OrderLifecycle::ORDER_COMPLETED,
            'payment_status' => OrderLifecycle::PAYMENT_REFUNDED,
        ]);

        $exchangeCount = Exchange::query()->count();
        $reservationCount = InventoryReservation::query()->count();
        $refundCount = Refund::query()->count();
        $movementCount = StockMovement::query()->count();
        $eventCount = OrderEvent::query()->where('order_id', $order->id)->count();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertDontSeeText('Create exchange')
            ->assertDontSee('name="order_item_id"', false)
            ->assertSeeText('This order has been fully refunded and cannot be exchanged.');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $replacement->id,
                'idempotency_key' => 'ex-pay-refunded',
            ])
            ->assertSessionHasErrors('order');

        $this->assertSame($exchangeCount, Exchange::query()->count());
        $this->assertSame($reservationCount, InventoryReservation::query()->count());
        $this->assertSame($refundCount, Refund::query()->count());
        $this->assertSame($movementCount, StockMovement::query()->count());
        $this->assertSame($eventCount, OrderEvent::query()->where('order_id', $order->id)->count());
    }

    public function test_partially_refunded_physical_order_remains_exchange_eligible(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 2);
        [, $replacement] = $this->product($store, 'Partial Alt', 'PART-ALT', 18);

        $order->update(['payment_status' => OrderLifecycle::PAYMENT_PARTIALLY_REFUNDED]);
        $item->update(['refunded_quantity' => 1]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Create exchange')
            ->assertDontSeeText('This order has been fully refunded and cannot be exchanged.')
            ->assertDontSeeText('No different active physical replacement product is available.');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $replacement->id,
                'idempotency_key' => 'ex-partial-ok',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exchanges', [
            'order_id' => $order->id,
            'idempotency_key' => 'ex-partial-ok',
        ]);
    }

    public function test_only_original_variant_makes_exchange_ineligible(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);

        $this->assertSame(
            1,
            ProductVariant::query()
                ->whereHas('product', fn ($q) => $q->where('store_id', $store->id)->where('status', true))
                ->count()
        );

        $exchangeCount = Exchange::query()->count();
        $reservationCount = InventoryReservation::query()->count();
        $refundCount = Refund::query()->count();
        $movementCount = StockMovement::query()->count();
        $eventCount = OrderEvent::query()->where('order_id', $order->id)->count();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertDontSeeText('Create exchange')
            ->assertDontSee('name="order_item_id"', false)
            ->assertSeeText('No different active physical replacement product is available.');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $item->product_variant_id,
                'idempotency_key' => 'ex-same-only',
            ])
            ->assertSessionHasErrors('order');

        $this->assertSame($exchangeCount, Exchange::query()->count());
        $this->assertSame($reservationCount, InventoryReservation::query()->count());
        $this->assertSame($refundCount, Refund::query()->count());
        $this->assertSame($movementCount, StockMovement::query()->count());
        $this->assertSame($eventCount, OrderEvent::query()->where('order_id', $order->id)->count());
    }

    public function test_different_active_physical_variant_keeps_exchange_available(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00', quantity: 1);
        [, $replacement] = $this->product($store, 'Different Size', 'DIFF-S', 19);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Create exchange')
            ->assertDontSeeText('No different active physical replacement product is available.')
            ->assertDontSeeText('This order has been fully refunded and cannot be exchanged.');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $replacement->id,
                'idempotency_key' => 'ex-diff-ok',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exchanges', [
            'order_id' => $order->id,
            'idempotency_key' => 'ex-diff-ok',
        ]);
    }

    public function test_digital_and_service_items_hide_and_reject_exchanges(): void
    {
        foreach (['digital', 'service'] as $productType) {
            [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '25.00', quantity: 1);
            $item->update(['product_type_snapshot' => $productType]);
            [, $replacement] = $this->product($store, 'Physical Replacement '.$productType, 'PR-'.$productType, 20);

            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->get(route('orderViewDetails', $order))
                ->assertOk()
                ->assertDontSee('name="order_item_id"', false);

            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->post(route('orders.exchanges.store', $order), [
                    'order_item_id' => $item->id,
                    'quantity' => 1,
                    'replacement_variant_id' => $replacement->id,
                    'idempotency_key' => 'ex-'.$productType,
                ])
                ->assertSessionHasErrors();
        }
    }

    public function test_completed_physical_order_shows_record_return_and_create_exchange_when_eligible(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '40.00');
        [, $replacement] = $this->product($store, 'Alt Size', 'ALT-M', 18);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('After-sales service')
            ->assertSeeText('Record return')
            ->assertSeeText('Create exchange')
            ->assertDontSeeText('This order has been fully refunded and cannot be exchanged.')
            ->assertDontSeeText('No different active physical replacement product is available.')
            ->assertSee('name="order_item_id"', false)
            ->assertSee('value="'.$item->id.'"', false)
            ->assertSee('value="'.$replacement->id.'"', false);
    }

    public function test_return_form_exposes_customer_message_and_persists_customer_notes(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '35.00', quantity: 1);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Customer’s message')
            ->assertSee('name="customer_notes"', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), [
                'items' => [$item->id => 1],
                'customer_notes' => 'Customer called: wrong size shipped.',
                'manual_instructions' => 'Use prepaid label.',
                'merchant_notes' => 'Approved on phone.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Return recorded.')
            ->assertSessionHas('success_title', 'Return created');

        $return = OrderReturn::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('Customer called: wrong size shipped.', $return->customer_notes);
    }

    public function test_refund_form_explains_money_only_and_cancelled_paid_order_may_still_refund(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(grandTotal: '55.00');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Issuing a refund returns money only. It does not return or restock products. If goods are coming back, record and receive the return separately.')
            ->assertSeeText('Issue refund');

        $order->update(['status' => OrderLifecycle::ORDER_CANCELLED, 'cancelled_at' => now()]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertDontSeeText('Record return')
            ->assertDontSee('name="order_item_id"', false)
            ->assertSeeText('Issue refund');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['processed_externally' => 1])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderLifecycle::ORDER_CANCELLED, $order->fresh()->status);
        $this->assertSame(OrderLifecycle::PAYMENT_REFUNDED, $order->fresh()->payment_status);
    }

    public function test_fully_refunded_physical_order_may_still_record_return_when_quantity_remains(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '45.00', quantity: 1);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), ['processed_externally' => 1])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderLifecycle::ORDER_REFUNDED, $order->status);
        $this->assertSame(OrderLifecycle::PAYMENT_REFUNDED, $order->payment_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Record return')
            ->assertDontSee('name="order_item_id"', false)
            ->assertDontSeeText('Issue refund');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), [
                'items' => [$item->id => 1],
                'customer_notes' => 'Goods coming back after full refund.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Return recorded.');

        $this->assertDatabaseHas('returns', [
            'order_id' => $order->id,
            'customer_notes' => 'Goods coming back after full refund.',
        ]);
    }

    public function test_exchange_form_lists_only_eligible_physical_items_and_replacement_variants(): void
    {
        [$owner, $store, $order, $physicalItem] = $this->seedPaidOrder(grandTotal: '60.00', quantity: 1);
        [$digitalProduct, $digitalVariant] = $this->product($store, 'Digital Guide', 'DIG', 10);
        $digitalProduct->update(['product_type' => 'digital']);
        $order->items()->create([
            'store_id' => $store->id,
            'product_id' => $digitalProduct->id,
            'product_variant_id' => $digitalVariant->id,
            'product_name' => $digitalProduct->name,
            'variant_label' => 'Download',
            'sku_snapshot' => $digitalVariant->sku,
            'product_type_snapshot' => 'digital',
            'quantity' => 1,
            'unit_price' => 10,
            'subtotal' => 10,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 10,
        ]);

        [, $physicalReplacement] = $this->product($store, 'Physical Alt', 'PHYS-ALT', 22);
        [$inactiveProduct, $inactiveVariant] = $this->product($store, 'Inactive Alt', 'INACT', 12);
        $inactiveProduct->update(['status' => false]);
        [$digitalCatalog, $digitalCatalogVariant] = $this->product($store, 'Digital Catalog', 'DIG-CAT', 8);
        $digitalCatalog->update(['product_type' => 'digital']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Create exchange')
            ->assertSeeText($physicalItem->product_name.' (Size: M)')
            ->assertDontSeeText($digitalProduct->name.' (Download)')
            ->assertSeeText('Physical Alt')
            ->assertDontSeeText('Inactive Alt')
            ->assertDontSeeText('Digital Catalog');
    }

    public function test_after_sales_cross_store_return_and_exchange_remain_denied(): void
    {
        [$owner, $store, $order, $item] = $this->seedPaidOrder(grandTotal: '30.00', quantity: 1);
        [, $replacement] = $this->product($store, 'Other Size', 'OS', 12);

        $owner2 = $this->merchant(fake()->unique()->safeEmail());
        $store2 = $this->store($owner2, 'Other After Sales Store');
        $this->attach($store2, $owner2, Store::ROLE_OWNER);

        $this->actingAs($owner2)
            ->withSession(['current_store_id' => $store2->id])
            ->get(route('orderViewDetails', $order))
            ->assertNotFound();

        $this->actingAs($owner2)
            ->withSession(['current_store_id' => $store2->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 1]])
            ->assertNotFound();

        $this->actingAs($owner2)
            ->withSession(['current_store_id' => $store2->id])
            ->post(route('orders.exchanges.store', $order), [
                'order_item_id' => $item->id,
                'quantity' => 1,
                'replacement_variant_id' => $replacement->id,
            ])
            ->assertNotFound();
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
                    return ($this->resultFactory)($paymentIntent, 0, 'USD', array_merge($options, [
                        'retrieve' => true,
                        'provider_refund_id' => $providerRefundId,
                    ]));
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
            'status' => true,
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
