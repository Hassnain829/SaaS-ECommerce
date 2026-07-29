<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendNotificationEmailJob;
use App\Mail\StoreEventMail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\User;
use App\Services\Notifications\LowStockNotifier;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NotificationEmailRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_transient_mail_failures_retry_then_succeed_with_exact_attempts(): void
    {
        [$owner, $store] = $this->ownerWithStore('Retry Success');
        $row = $this->queuedEmail($store, $owner);

        $sendCount = 0;
        $this->bindThrowingThenSucceedingMailer($sendCount, failTimes: 2);

        $job = $this->jobWithAttempt($row->id, 1);
        try {
            $job->handle();
            $this->fail('Expected first attempt to throw.');
        } catch (RuntimeException) {
        }

        $this->assertSame(NotificationEvent::STATUS_QUEUED, $row->fresh()->status);
        $this->assertSame(1, (int) $row->fresh()->attempts);
        $this->assertNotNull($row->fresh()->error_message);

        $job = $this->jobWithAttempt($row->id, 2);
        try {
            $job->handle();
            $this->fail('Expected second attempt to throw.');
        } catch (RuntimeException) {
        }

        $this->assertSame(NotificationEvent::STATUS_QUEUED, $row->fresh()->status);
        $this->assertSame(2, (int) $row->fresh()->attempts);

        $job = $this->jobWithAttempt($row->id, 3);
        $job->handle();

        $this->assertSame(NotificationEvent::STATUS_SENT, $row->fresh()->status);
        $this->assertSame(2, (int) $row->fresh()->attempts);
        $this->assertSame(3, $sendCount);
        $this->assertNull($row->fresh()->failed_at);
    }

    public function test_final_exhausted_attempt_becomes_failed_without_double_increment(): void
    {
        [$owner, $store] = $this->ownerWithStore('Retry Exhaust');
        $row = $this->queuedEmail($store, $owner);

        $sendCount = 0;
        $this->bindThrowingThenSucceedingMailer($sendCount, failTimes: 99);

        foreach ([1, 2] as $attempt) {
            $job = $this->jobWithAttempt($row->id, $attempt);
            try {
                $job->handle();
            } catch (RuntimeException) {
            }
            $this->assertSame(NotificationEvent::STATUS_QUEUED, $row->fresh()->status);
            $this->assertSame($attempt, (int) $row->fresh()->attempts);
        }

        $job = $this->jobWithAttempt($row->id, 3);
        try {
            $job->handle();
        } catch (RuntimeException) {
        }

        $this->assertSame(NotificationEvent::STATUS_FAILED, $row->fresh()->status);
        $this->assertSame(3, (int) $row->fresh()->attempts);

        $job->failed(new RuntimeException('Email delivery failed.'));
        $this->assertSame(3, (int) $row->fresh()->attempts);
        $this->assertSame(NotificationEvent::STATUS_FAILED, $row->fresh()->status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('notifications'))
            ->assertOk()
            ->assertSeeText('Some emails could not be delivered');
    }

    public function test_manual_retry_works_after_exhausted_job_when_under_global_cap(): void
    {
        [$owner, $store] = $this->ownerWithStore('Manual After Exhaust');
        $row = $this->queuedEmail($store, $owner);
        $row->forceFill([
            'status' => NotificationEvent::STATUS_FAILED,
            'attempts' => 3,
            'failed_at' => now(),
            'error_message' => 'smtp down',
        ])->save();

        \Illuminate\Support\Facades\Queue::fake();
        Mail::fake();

        $this->assertTrue(app(NotificationDispatcher::class)->retryEmail($store, $row->fresh()));
        $this->assertSame(NotificationEvent::STATUS_QUEUED, $row->fresh()->status);
        $this->assertSame(3, (int) $row->fresh()->attempts);

        $job = $this->jobWithAttempt($row->id, 1);
        $job->handle();

        $this->assertSame(NotificationEvent::STATUS_SENT, $row->fresh()->status);
        Mail::assertSent(StoreEventMail::class, 1);
    }

    public function test_overlapping_workers_result_in_one_mail_send_via_atomic_claim(): void
    {
        Mail::fake();
        [$owner, $store] = $this->ownerWithStore('Overlap Claim');
        $row = $this->queuedEmail($store, $owner);

        $workerA = $this->jobWithAttempt($row->id, 1);
        $workerB = $this->jobWithAttempt($row->id, 1);

        $this->assertTrue($this->invokeClaim($workerA));
        $this->assertSame(NotificationEvent::STATUS_SENDING, $row->fresh()->status);
        $this->assertFalse(
            $this->invokeClaim($workerB),
            'Second worker must lose the queued->sending claim.'
        );

        // Losing worker sees non-queued status and must not call Mail::send.
        $workerB->handle();
        Mail::assertNothingSent();

        $sendCalls = 0;
        $mailer = Mockery::mock();
        $mailer->shouldReceive('send')->once()->andReturnUsing(function () use (&$sendCalls): void {
            $sendCalls++;
        });
        Mail::shouldReceive('mailer')->andReturn($mailer);

        // Only a claimed queued->sending transition may send. Reset for the exclusive winner.
        StoreNotification::query()->whereKey($row->id)->update([
            'status' => NotificationEvent::STATUS_QUEUED,
        ]);
        $this->jobWithAttempt($row->id, 1)->handle();

        $this->assertSame(1, $sendCalls);
        $this->assertSame(NotificationEvent::STATUS_SENT, $row->fresh()->status);

        // A duplicate worker after send is a no-op.
        $this->jobWithAttempt($row->id, 1)->handle();
        $this->assertSame(1, $sendCalls);
    }

    public function test_unique_and_overlap_locks_cover_full_retry_window(): void
    {
        $job = new SendNotificationEmailJob(42);
        $backoffTotal = array_sum($job->backoff);

        $this->assertGreaterThanOrEqual(
            $backoffTotal,
            $job->uniqueFor,
            'ShouldBeUnique uniqueFor must cover sum of configured backoffs.'
        );

        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);

        $expiresAfter = (new \ReflectionObject($middleware[0]))->getProperty('expiresAfter');
        $expiresAfter->setAccessible(true);
        $expireValue = $expiresAfter->getValue($middleware[0]);
        $expireSeconds = $expireValue instanceof \DateTimeInterface
            ? $expireValue->getTimestamp() - time()
            : (int) $expireValue;

        $this->assertGreaterThanOrEqual(
            $backoffTotal,
            $expireSeconds,
            'WithoutOverlapping expireAfter must cover the full retry window.'
        );
        $this->assertSame($job->uniqueId(), 'send-notification-email:42');
    }

    public function test_low_stock_notifier_does_not_load_product_before_commit_boundary(): void
    {
        [$owner, $store] = $this->ownerWithStore('Low Stock Boundary');
        [$product, $variant] = $this->product($store, stock: 1, alert: 5);
        $variant = ProductVariant::query()->findOrFail($variant->id);
        $this->assertFalse($variant->relationLoaded('product'));

        DB::beginTransaction();
        try {
            app(LowStockNotifier::class)->checkVariant($variant);
            $this->assertFalse(
                $variant->relationLoaded('product'),
                'checkVariant must not lazy-load product before the commit boundary.'
            );
        } finally {
            DB::rollBack();
        }

        $this->assertSame(0, StoreNotification::query()->where('store_id', $store->id)->count());
    }

    public function test_low_stock_cross_store_supplied_store_cannot_misroute(): void
    {
        [$ownerA, $storeA] = $this->ownerWithStore('Low Stock A', 'low-a@example.com');
        [, $storeB] = $this->ownerWithStore('Low Stock B', 'low-b@example.com');
        [, $variant] = $this->product($storeA, stock: 1, alert: 5);

        app(LowStockNotifier::class)->checkVariant($variant->fresh(), $storeB);

        $this->assertSame(0, StoreNotification::query()->where('store_id', $storeB->id)->count());
        $this->assertSame(0, StoreNotification::query()->where('store_id', $storeA->id)->count());

        app(LowStockNotifier::class)->checkVariant($variant->fresh(), $storeA);
        $this->assertTrue(
            StoreNotification::query()
                ->where('store_id', $storeA->id)
                ->where('type', NotificationEvent::INVENTORY_LOW)
                ->exists()
        );
    }

    public function test_stale_sending_on_queue_attempt_two_moves_to_failed_without_mail_send(): void
    {
        Mail::fake();
        [$owner, $store] = $this->ownerWithStore('Stale Sending Recover');
        $row = $this->queuedEmail($store, $owner);
        $attemptsBefore = 1;
        $row->forceFill([
            'status' => NotificationEvent::STATUS_SENDING,
            'attempts' => $attemptsBefore,
        ])->save();

        $this->jobWithAttempt($row->id, 2)->handle();

        $fresh = $row->fresh();
        $this->assertSame(NotificationEvent::STATUS_FAILED, $fresh->status);
        $this->assertSame(SendNotificationEmailJob::UNCERTAIN_DELIVERY_MESSAGE, $fresh->error_message);
        $this->assertSame($attemptsBefore, (int) $fresh->attempts);
        $this->assertNotNull($fresh->failed_at);
        Mail::assertNothingSent();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('notifications'))
            ->assertOk()
            ->assertSeeText('Some emails could not be delivered')
            ->assertSeeText('Queued mail')
            ->assertSeeText('uncertain after an interrupted worker');

        \Illuminate\Support\Facades\Queue::fake();
        $this->assertTrue(app(NotificationDispatcher::class)->retryEmail($store, $fresh));
        $this->assertSame(NotificationEvent::STATUS_QUEUED, $row->fresh()->status);
    }

    public function test_concurrent_attempt_one_seeing_sending_remains_noop(): void
    {
        Mail::fake();
        [$owner, $store] = $this->ownerWithStore('Sending Concurrent Noop');
        $row = $this->queuedEmail($store, $owner);
        $row->forceFill([
            'status' => NotificationEvent::STATUS_SENDING,
            'attempts' => 0,
        ])->save();

        $this->jobWithAttempt($row->id, 1)->handle();

        $fresh = $row->fresh();
        $this->assertSame(NotificationEvent::STATUS_SENDING, $fresh->status);
        $this->assertNull($fresh->error_message);
        $this->assertSame(0, (int) $fresh->attempts);
        Mail::assertNothingSent();
    }

    public function test_sent_notification_is_never_downgraded(): void
    {
        Mail::fake();
        [$owner, $store] = $this->ownerWithStore('Sent Never Downgrade');
        $row = $this->queuedEmail($store, $owner);
        $row->forceFill([
            'status' => NotificationEvent::STATUS_SENT,
            'sent_at' => now(),
            'attempts' => 2,
            'error_message' => null,
        ])->save();

        $this->jobWithAttempt($row->id, 2)->handle();
        $this->jobWithAttempt($row->id, 1)->handle();

        $fresh = $row->fresh();
        $this->assertSame(NotificationEvent::STATUS_SENT, $fresh->status);
        $this->assertNull($fresh->failed_at);
        $this->assertNull($fresh->error_message);
        Mail::assertNothingSent();
    }

    public function test_attempts_never_exceed_max_email_attempts(): void
    {
        Mail::fake();
        [$owner, $store] = $this->ownerWithStore('Attempts Cap');
        $row = $this->queuedEmail($store, $owner);
        $row->forceFill([
            'status' => NotificationEvent::STATUS_QUEUED,
            'attempts' => NotificationDispatcher::MAX_EMAIL_ATTEMPTS,
            'recipient_email' => null,
        ])->save();

        $this->jobWithAttempt($row->id, 1)->handle();

        $fresh = $row->fresh();
        $this->assertSame(NotificationEvent::STATUS_FAILED, $fresh->status);
        $this->assertSame(NotificationDispatcher::MAX_EMAIL_ATTEMPTS, (int) $fresh->attempts);

        $row2 = $this->queuedEmail($store, $owner, 'job:cap-2');
        $row2->forceFill([
            'status' => NotificationEvent::STATUS_QUEUED,
            'attempts' => NotificationDispatcher::MAX_EMAIL_ATTEMPTS - 1,
            'recipient_email' => null,
        ])->save();

        $this->jobWithAttempt($row2->id, 1)->handle();
        $this->assertSame(NotificationDispatcher::MAX_EMAIL_ATTEMPTS, (int) $row2->fresh()->attempts);
        $this->assertSame(NotificationEvent::STATUS_FAILED, $row2->fresh()->status);
    }

    /**
     * @return SendNotificationEmailJob
     */
    private function jobWithAttempt(int $notificationId, int $attempt): SendNotificationEmailJob
    {
        return new class($notificationId, $attempt) extends SendNotificationEmailJob
        {
            public function __construct(int $notificationId, private readonly int $attemptNumber)
            {
                parent::__construct($notificationId);
            }

            public function attempts(): int
            {
                return $this->attemptNumber;
            }
        };
    }

    private function invokeClaim(SendNotificationEmailJob $job): bool
    {
        $method = new \ReflectionMethod($job, 'claimForSending');
        $method->setAccessible(true);

        return (bool) $method->invoke($job);
    }

    private function bindThrowingThenSucceedingMailer(int &$sendCount, int $failTimes): void
    {
        $mailer = Mockery::mock();
        $mailer->shouldReceive('send')->andReturnUsing(function () use (&$sendCount, $failTimes): void {
            $sendCount++;
            if ($sendCount <= $failTimes) {
                throw new RuntimeException('transient smtp failure');
            }
        });

        Mail::shouldReceive('mailer')->andReturn($mailer);
    }

    private function queuedEmail(Store $store, User $owner, string $dedupe = 'job:retry'): StoreNotification
    {
        return StoreNotification::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_EMAIL,
            'title' => 'Queued mail',
            'body' => 'Body',
            'status' => NotificationEvent::STATUS_QUEUED,
            'dedupe_key' => $dedupe.'-'.Str::random(4),
            'recipient_key' => 'user:'.$owner->id,
            'recipient_email' => $owner->email,
            'data' => ['audience' => 'merchant'],
            'attempts' => 0,
        ]);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerWithStore(string $name, string $email = 'retry-owner@example.com'): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'role_id' => $role->id,
            'email' => $email,
        ]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'retry-'.Str::lower(Str::random(8)),
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

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store, int $stock = 10, int $alert = 5): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Retry Tee',
            'slug' => 'retry-tee-'.Str::random(6),
            'base_price' => 25,
            'sku' => 'RTY',
            'product_type' => 'physical',
            'status' => true,
            'track_inventory' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'RTY-M',
            'price' => 25,
            'stock' => $stock,
            'stock_alert' => $alert,
        ]);

        return [$product, $variant];
    }
}
