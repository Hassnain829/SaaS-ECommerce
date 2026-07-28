<?php

namespace Tests\Feature\Notifications;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentIntentUpdateResult;
use App\Data\Payments\PaymentRefundResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Jobs\SendNotificationEmailJob;
use App\Mail\StoreEventMail;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\User;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Inventory\InventorySyncService;
use App\Services\Notifications\CommerceNotificationEmitter;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\ReturnService;
use App\Support\NotificationEvent;
use App\Support\OrderLifecycle;
use App\Support\RefundLifecycle;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class NotificationCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_failure_does_not_roll_back_successful_provider_refund(): void
    {
        [$owner, $store, $order] = $this->seedPaidOrder(
            grandTotal: '80.00',
            orderSource: 'platform_checkout',
            withPaymentIntent: true,
        );

        $this->app->instance(PaymentProviderInterface::class, $this->succeedingRefundProvider());
        $this->app->bind(
            \App\Services\Payments\StripePlatformPaymentProvider::class,
            fn () => $this->app->make(PaymentProviderInterface::class)
        );

        $this->bindThrowingDispatcher();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.refunds.store', $order), [
                'processed_externally' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund = Refund::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(RefundLifecycle::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame('re_notify_ok', $refund->provider_refund_id);
        $this->assertSame(OrderLifecycle::ORDER_REFUNDED, $order->fresh()->status);
    }

    public function test_notification_failure_does_not_fail_commerce_side_effects(): void
    {
        $this->bindThrowingDispatcher();
        [$owner, $store] = $this->ownerWithStore('Commerce Safe');
        $customer = $this->customer($store);
        $order = $this->order($store, $customer);
        [$product, $variant] = $this->product($store, stock: 10, alert: 5);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => 'Size: M',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => 2,
            'unit_price' => 25,
            'subtotal' => 50,
            'total' => 50,
        ]);

        // Order conversion-style emit after local order create.
        DB::transaction(function () use ($order, $owner): void {
            $order->forceFill(['notes' => 'converted'])->save();
            app(CommerceNotificationEmitter::class)->orderCreated($order->fresh(), $owner);
        });
        $this->assertSame('converted', $order->fresh()->notes);

        $return = app(ReturnService::class)->requestReturn($order, [
            'items' => [$item->id => 1],
        ], $owner);
        $this->assertNotNull($return->id);

        $level = app(InventorySyncService::class)->ensureDefaultLevelForVariant($variant->fresh(), 10);
        $updated = app(InventoryAdjustmentService::class)->setAvailable(
            $level->inventoryItem,
            $level->location,
            2,
            'Low stock test',
            $owner
        );
        $this->assertSame(2, (int) $updated->available);

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-N1',
            'status' => Shipment::STATUS_PENDING,
            'tracking_number' => null,
        ]);
        DB::transaction(function () use ($shipment, $owner): void {
            $shipment->forceFill(['tracking_number' => 'TRK-1', 'status' => Shipment::STATUS_SHIPPED])->save();
            app(CommerceNotificationEmitter::class)->shipmentEvent(
                $shipment->fresh(),
                NotificationEvent::SHIPMENT_SHIPPED,
                'Shipment shipped',
                $owner
            );
        });
        $this->assertSame('TRK-1', $shipment->fresh()->tracking_number);

        $import = ProductImport::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'original_filename' => 'ok.csv',
            'stored_path' => 'imports/ok.csv',
            'file_extension' => 'csv',
            'status' => ProductImport::STATUS_PROCESSING,
            'result_summary' => ['processed_rows' => 4],
        ]);
        DB::transaction(function () use ($import): void {
            $import->forceFill(['status' => ProductImport::STATUS_COMPLETED])->save();
            app(CommerceNotificationEmitter::class)->importFinished($import->fresh(), false);
        });
        $this->assertSame(ProductImport::STATUS_COMPLETED, $import->fresh()->status);
    }

    public function test_rolled_back_commerce_transaction_creates_no_notification(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Rollback Notify');
        $customer = $this->customer($store);
        $order = $this->order($store, $customer);

        try {
            DB::transaction(function () use ($order, $owner): void {
                $order->forceFill(['notes' => 'should-roll-back'])->save();
                app(CommerceNotificationEmitter::class)->orderCreated($order->fresh(), $owner);
                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull($order->fresh()->notes);
        $this->assertSame(0, StoreNotification::query()->where('store_id', $store->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_duplicate_domain_events_create_one_row_per_recipient_channel(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Dedupe Corr');
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::ORDER_CREATED,
            'Order',
            'Body',
            'order.created:99',
        );
        $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::ORDER_CREATED,
            'Order',
            'Body',
            'order.created:99',
        );

        $this->assertSame(2, StoreNotification::query()->where('store_id', $store->id)->count());
        Queue::assertPushed(SendNotificationEmailJob::class, 1);
    }

    public function test_two_stale_retry_requests_dispatch_one_email_job(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Retry Atomic');
        $failed = StoreNotification::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_EMAIL,
            'title' => 'Failed mail',
            'body' => 'Body',
            'status' => NotificationEvent::STATUS_FAILED,
            'dedupe_key' => 'retry:1',
            'recipient_key' => 'user:'.$owner->id,
            'recipient_email' => $owner->email,
            'failed_at' => now(),
            'attempts' => 1,
            'error_message' => 'SMTP down',
        ]);

        $dispatcher = app(NotificationDispatcher::class);
        $staleA = $failed->fresh();
        $staleB = $failed->fresh();

        $this->assertTrue($dispatcher->retryEmail($staleA));
        $this->assertFalse($dispatcher->retryEmail($staleB));
        Queue::assertPushed(SendNotificationEmailJob::class, 1);
        $this->assertSame(NotificationEvent::STATUS_QUEUED, $failed->fresh()->status);
    }

    public function test_duplicate_jobs_cannot_send_same_notification_concurrently(): void
    {
        Mail::fake();
        [$owner, $store] = $this->ownerWithStore('Job Unique');
        $row = StoreNotification::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_EMAIL,
            'title' => 'Queued mail',
            'body' => 'Body',
            'status' => NotificationEvent::STATUS_QUEUED,
            'dedupe_key' => 'job:1',
            'recipient_key' => 'user:'.$owner->id,
            'recipient_email' => $owner->email,
            'data' => ['audience' => 'merchant'],
            'attempts' => 0,
        ]);

        $job = new SendNotificationEmailJob($row->id);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('send-notification-email:'.$row->id, $job->uniqueId());
        $this->assertInstanceOf(
            \Illuminate\Queue\Middleware\WithoutOverlapping::class,
            $job->middleware()[0] ?? null
        );

        $job->handle();
        (new SendNotificationEmailJob($row->id))->handle();

        $this->assertSame(NotificationEvent::STATUS_SENT, $row->fresh()->status);
        Mail::assertSent(StoreEventMail::class, 1);
    }

    public function test_failed_customer_email_visible_and_retryable_for_authorized_merchant(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Customer Fail A');
        $failed = $this->failedCustomerEmail($store, 'buyer@example.com');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('notifications'))
            ->assertOk()
            ->assertSeeText('Customer email failures')
            ->assertSeeText('buyer@example.com');

        Queue::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('notifications.retry-customer', $failed))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(NotificationEvent::STATUS_QUEUED, $failed->fresh()->status);
        Queue::assertPushed(SendNotificationEmailJob::class, fn ($job) => $job->notificationId === $failed->id);
    }

    public function test_customer_email_failure_is_hidden_across_stores(): void
    {
        [$ownerA, $storeA] = $this->ownerWithStore('Store A Fail', 'ownera@example.com');
        [, $storeB] = $this->ownerWithStore('Store B Fail', 'ownerb@example.com');
        $failedB = $this->failedCustomerEmail($storeB, 'other-buyer@example.com');

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('notifications'))
            ->assertOk()
            ->assertDontSeeText('other-buyer@example.com');

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('notifications.retry-customer', $failedB))
            ->assertNotFound();
    }

    public function test_staff_cannot_manage_customer_email_failures(): void
    {
        [$owner, $store] = $this->ownerWithStore('Staff Gate');
        $staff = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
            'email' => 'staff-notify@example.com',
        ]);
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);
        $failed = $this->failedCustomerEmail($store, 'staff-hidden@example.com');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('notifications'))
            ->assertOk()
            ->assertDontSeeText('Customer email failures')
            ->assertDontSeeText('staff-hidden@example.com');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('notifications.retry-customer', $failed))
            ->assertForbidden();
    }

    public function test_generic_integrity_violations_are_not_treated_as_dedupe_success(): void
    {
        [$owner, $store] = $this->ownerWithStore('Integrity');
        StoreNotification::creating(function (): void {
            throw new QueryException(
                'sqlite',
                'insert into "notifications"',
                [],
                new PDOException('SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed', '23000')
            );
        });

        $this->expectException(QueryException::class);
        app(NotificationDispatcher::class)->notifyUser(
            $store,
            $owner,
            NotificationEvent::INVENTORY_LOW,
            'Low',
            'Body',
            'inventory.low:x',
            [],
            null,
            [NotificationEvent::CHANNEL_IN_APP]
        );
    }

    public function test_customer_emails_omit_merchant_only_order_urls(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Customer Links');
        $customer = $this->customer($store);
        $order = $this->order($store, $customer);

        app(CommerceNotificationEmitter::class)->orderCreated($order, $owner);
        app(CommerceNotificationEmitter::class)->refundFinished(
            Refund::query()->create([
                'store_id' => $store->id,
                'order_id' => $order->id,
                'refund_number' => 'RF-1',
                'status' => RefundLifecycle::STATUS_SUCCEEDED,
                'method' => RefundLifecycle::METHOD_EXTERNAL,
                'amount' => 10,
                'currency_code' => 'USD',
            ]),
            false,
            $owner
        );

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-L1',
            'status' => Shipment::STATUS_SHIPPED,
            'tracking_number' => 'T-1',
            'tracking_url' => 'not-a-url',
        ]);
        app(CommerceNotificationEmitter::class)->shipmentEvent(
            $shipment,
            NotificationEvent::SHIPMENT_SHIPPED,
            'Shipped',
            $owner
        );

        $customerRows = StoreNotification::query()
            ->where('store_id', $store->id)
            ->whereNull('user_id')
            ->where('channel', NotificationEvent::CHANNEL_EMAIL)
            ->get();

        $this->assertNotEmpty($customerRows);
        foreach ($customerRows as $row) {
            $data = $row->data ?? [];
            $this->assertArrayNotHasKey('action_url', $data);
            $this->assertStringNotContainsString('/orders/', (string) json_encode($data));
        }

        $merchant = StoreNotification::query()
            ->where('store_id', $store->id)
            ->where('user_id', $owner->id)
            ->where('type', NotificationEvent::ORDER_CREATED)
            ->where('channel', NotificationEvent::CHANNEL_IN_APP)
            ->firstOrFail();
        $this->assertNotEmpty($merchant->data['action_url'] ?? null);

        $shipment->forceFill(['tracking_url' => 'https://track.example/T-1'])->save();
        StoreNotification::query()->where('type', NotificationEvent::SHIPMENT_SHIPPED)->delete();
        app(CommerceNotificationEmitter::class)->shipmentEvent(
            $shipment->fresh(),
            NotificationEvent::SHIPMENT_SHIPPED,
            'Shipped',
            $owner
        );
        $withTracking = StoreNotification::query()
            ->whereNull('user_id')
            ->where('type', NotificationEvent::SHIPMENT_SHIPPED)
            ->firstOrFail();
        $this->assertSame('https://track.example/T-1', $withTracking->data['action_url'] ?? null);
    }

    public function test_channel_and_event_preferences_still_work_without_quiet_hours_gating(): void
    {
        Queue::fake();
        [$owner, $store] = $this->ownerWithStore('Prefs Corr');
        $prefs = app(NotificationPreferenceService::class);

        NotificationPreference::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'user_id' => $owner->id,
                'channel' => NotificationEvent::CHANNEL_EMAIL,
            ],
            [
                'is_enabled' => true,
                'event_types' => array_merge(NotificationEvent::defaultEventTypes(), [
                    NotificationEvent::INVENTORY_LOW => true,
                    NotificationEvent::ORDER_CREATED => false,
                ]),
                'quiet_hours' => [
                    'enabled' => true,
                    'start' => '00:00',
                    'end' => '23:59',
                    'timezone' => 'UTC',
                ],
            ]
        );

        $dispatcher = app(NotificationDispatcher::class);

        $blockedOrder = $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::ORDER_CREATED,
            'Blocked',
            'Body',
            'order.created:pref',
            [],
            null,
            [NotificationEvent::CHANNEL_EMAIL]
        );
        $this->assertSame([], $blockedOrder);

        $allowedLow = $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::INVENTORY_LOW,
            'Low',
            'Body',
            'inventory.low:pref',
            [],
            null,
            [NotificationEvent::CHANNEL_EMAIL]
        );
        $this->assertCount(1, $allowedLow);
        $this->assertTrue($prefs->allows($store, $owner, NotificationEvent::CHANNEL_EMAIL, NotificationEvent::INVENTORY_LOW));
    }

    private function bindThrowingDispatcher(): void
    {
        $mock = Mockery::mock(NotificationDispatcher::class);
        $mock->shouldReceive('notifyStore')->andThrow(new RuntimeException('notification persist failed'));
        $mock->shouldReceive('notifyCustomer')->andThrow(new RuntimeException('notification persist failed'));
        $mock->shouldReceive('notifyUser')->andThrow(new RuntimeException('notification persist failed'));
        $mock->shouldReceive('retryEmail')->andReturn(false);
        $this->app->instance(NotificationDispatcher::class, $mock);
        $this->app->forgetInstance(CommerceNotificationEmitter::class);
        $this->app->forgetInstance(\App\Services\Notifications\LowStockNotifier::class);
        $this->app->forgetInstance(\App\Services\RefundService::class);
        $this->app->forgetInstance(\App\Services\ReturnService::class);
        $this->app->forgetInstance(\App\Services\Inventory\InventoryAdjustmentService::class);
    }

    private function succeedingRefundProvider(): PaymentProviderInterface
    {
        return new class implements PaymentProviderInterface
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                throw new RuntimeException('not used');
            }

            public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult
            {
                throw new RuntimeException('not used');
            }

            public function updatePaymentIntentAmount(string $providerIntentId, int $amountMinor, string $currencyCode, array $options = []): PaymentIntentUpdateResult
            {
                throw new RuntimeException('not used');
            }

            public function createRefund(PaymentIntent $paymentIntent, int $amountMinor, string $currencyCode, array $options = []): PaymentRefundResult
            {
                return new PaymentRefundResult(
                    providerRefundId: 're_notify_ok',
                    status: 'succeeded',
                    amount: '80.00',
                    amountMinor: 8000,
                    currencyCode: 'USD',
                    raw: ['id' => 're_notify_ok', 'status' => 'succeeded'],
                    mode: 'test',
                );
            }

            public function retrieveRefund(string $providerRefundId, PaymentIntent $paymentIntent, array $options = []): PaymentRefundResult
            {
                throw new RuntimeException('not used');
            }

            public function verifyWebhook(string $payload, string $signature, string $mode = 'test'): PaymentWebhookResult
            {
                throw new RuntimeException('not used');
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                throw new RuntimeException('not used');
            }
        };
    }

    private function failedCustomerEmail(Store $store, string $email): StoreNotification
    {
        return StoreNotification::query()->create([
            'store_id' => $store->id,
            'user_id' => null,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_EMAIL,
            'title' => 'Order confirmation',
            'body' => 'Thanks',
            'status' => NotificationEvent::STATUS_FAILED,
            'dedupe_key' => 'order.created:cust:'.Str::random(6),
            'recipient_key' => 'email:'.hash('sha256', strtolower($email)),
            'recipient_email' => $email,
            'data' => ['audience' => 'customer'],
            'failed_at' => now(),
            'error_message' => 'Mailbox full',
            'attempts' => 1,
        ]);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerWithStore(string $name, string $email = 'owner-corr@example.com'): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'role_id' => $role->id,
            'email' => $email,
        ]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'corr-'.Str::lower(Str::random(8)),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function customer(Store $store): Customer
    {
        return Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Corr Buyer',
            'status' => 'active',
        ]);
    }

    private function order(Store $store, Customer $customer): Order
    {
        return Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#C-1001',
            'status' => OrderLifecycle::ORDER_COMPLETED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 50,
            'total' => 50,
            'grand_total' => 50,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 2,
            'placed_at' => now(),
        ]);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store, int $stock = 10, int $alert = 5): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Corr Tee',
            'slug' => 'corr-tee-'.Str::random(6),
            'base_price' => 25,
            'sku' => 'CORR',
            'product_type' => 'physical',
            'status' => true,
            'track_inventory' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'CORR-M',
            'price' => 25,
            'stock' => $stock,
            'stock_alert' => $alert,
        ]);

        return [$product, $variant];
    }

    /**
     * @return array{0: User, 1: Store, 2: Order}
     */
    private function seedPaidOrder(
        string $grandTotal = '100.00',
        string $orderSource = 'external_checkout',
        bool $withPaymentIntent = false,
    ): array {
        [$owner, $store] = $this->ownerWithStore('Refund Corr', 'refund-corr@example.com');
        $customer = $this->customer($store);
        [$product, $variant] = $this->product($store);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#7201',
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
            'total_quantity' => 2,
            'placed_at' => now(),
            'meta' => $orderSource === 'external_checkout'
                ? ['channel_ownership' => ['payment_owner' => 'external', 'inventory_owner' => 'platform']]
                : ['platform_checkout' => ['checkout_number' => 'CHK-N']],
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_label' => 'Size: M',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => 2,
            'unit_price' => bcdiv($grandTotal, '2', 2),
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
                'provider_intent_id' => 'pi_test_notify',
                'status' => 'succeeded',
                'currency_code' => 'USD',
                'amount' => $grandTotal,
                'amount_minor' => (int) bcmul($grandTotal, '100', 0),
            ]);
        }

        return [$owner, $store, $order];
    }
}
