<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendNotificationEmailJob;
use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationPreferenceService;
use App\Support\NotificationEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTenantRecipientGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_store_notify_user_is_rejected(): void
    {
        Queue::fake();

        [$ownerA, $storeA] = $this->ownerWithStore('Tenant Guard A', 'owner-a@example.com');
        [$ownerB] = $this->ownerWithStore('Tenant Guard B', 'owner-b@example.com');

        $dispatcher = app(NotificationDispatcher::class);
        $result = $dispatcher->notifyUser(
            $storeA,
            $ownerB,
            NotificationEvent::ORDER_CREATED,
            'Must not create',
            'Cross-store recipient',
            'cross-store:notify:1',
        );

        $this->assertSame([], $result);
        $this->assertSame(0, StoreNotification::query()->count());
        $this->assertSame(0, NotificationPreference::query()
            ->where('store_id', $storeA->id)
            ->where('user_id', $ownerB->id)
            ->count());
        Queue::assertNothingPushed();
        $this->assertNull($storeA->fresh()->roleForUser($ownerB));
    }

    public function test_inactive_store_member_notify_user_is_rejected(): void
    {
        Queue::fake();

        [$owner, $store] = $this->ownerWithStore('Inactive Member Store', 'inactive-owner@example.com');
        $owner->forceFill(['is_active' => false])->save();

        $result = app(NotificationDispatcher::class)->notifyUser(
            $store,
            $owner->fresh(),
            NotificationEvent::ORDER_CREATED,
            'Must not create',
            'Inactive recipient',
            'inactive:notify:1',
        );

        $this->assertSame([], $result);
        $this->assertSame(0, StoreNotification::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_cross_store_retry_email_is_rejected(): void
    {
        Queue::fake();

        [$ownerA, $storeA] = $this->ownerWithStore('Retry Scope A', 'retry-a@example.com');
        [$ownerB, $storeB] = $this->ownerWithStore('Retry Scope B', 'retry-b@example.com');

        $failed = StoreNotification::query()->create([
            'store_id' => $storeB->id,
            'user_id' => $ownerB->id,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_EMAIL,
            'title' => 'Store B failed mail',
            'body' => 'Body',
            'status' => NotificationEvent::STATUS_FAILED,
            'dedupe_key' => 'cross-retry:1',
            'recipient_key' => 'user:'.$ownerB->id,
            'recipient_email' => $ownerB->email,
            'failed_at' => now(),
            'attempts' => 1,
            'error_message' => 'SMTP down',
        ]);

        $ok = app(NotificationDispatcher::class)->retryEmail($storeA, $failed);

        $this->assertFalse($ok);
        $this->assertSame(NotificationEvent::STATUS_FAILED, $failed->fresh()->status);
        $this->assertSame('SMTP down', $failed->fresh()->error_message);
        Queue::assertNothingPushed();
        $this->assertSame(1, StoreNotification::query()->whereKey($failed->id)->where('store_id', $storeB->id)->count());
        $this->assertTrue($storeA->roleForUser($ownerA) !== null);
    }

    public function test_preference_for_user_rejects_cross_store_recipient(): void
    {
        [, $storeA] = $this->ownerWithStore('Pref Scope A', 'pref-a@example.com');
        [$ownerB] = $this->ownerWithStore('Pref Scope B', 'pref-b@example.com');

        try {
            app(NotificationPreferenceService::class)->forUser(
                $storeA,
                $ownerB,
                NotificationEvent::CHANNEL_EMAIL
            );
            $this->fail('Expected AuthorizationException for cross-store preference access.');
        } catch (AuthorizationException $e) {
            $this->assertSame(
                'The notification recipient does not belong to this store.',
                $e->getMessage()
            );
        }

        $this->assertSame(0, NotificationPreference::query()
            ->where('store_id', $storeA->id)
            ->where('user_id', $ownerB->id)
            ->count());
    }

    public function test_same_store_notify_and_retry_still_work(): void
    {
        Queue::fake();

        [$owner, $store] = $this->ownerWithStore('Same Store OK', 'same-ok@example.com');
        $dispatcher = app(NotificationDispatcher::class);

        $created = $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::ORDER_CREATED,
            'Same-store order',
            'Should create rows',
            'same-store:ok:1',
        );

        $this->assertNotEmpty($created);
        $this->assertGreaterThanOrEqual(1, StoreNotification::query()->where('store_id', $store->id)->count());
        Queue::assertPushed(SendNotificationEmailJob::class);

        $failed = StoreNotification::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::IMPORT_FAILED,
            'channel' => NotificationEvent::CHANNEL_EMAIL,
            'title' => 'Failed mail',
            'body' => 'Body',
            'status' => NotificationEvent::STATUS_FAILED,
            'dedupe_key' => 'same-store:retry:1',
            'recipient_key' => 'user:'.$owner->id,
            'recipient_email' => $owner->email,
            'failed_at' => now(),
            'attempts' => 1,
            'error_message' => 'SMTP down',
        ]);

        Queue::fake();
        $this->assertTrue($dispatcher->retryEmail($store, $failed));
        $this->assertSame(NotificationEvent::STATUS_QUEUED, $failed->fresh()->status);
        Queue::assertPushed(SendNotificationEmailJob::class, 1);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerWithStore(string $name, string $email): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'role_id' => $role->id,
            'email' => $email,
            'is_active' => true,
        ]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'tenant-guard-'.fake()->unique()->numberBetween(1000, 9999),
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
}
