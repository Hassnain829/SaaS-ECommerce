<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendNotificationEmailJob;
use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreNotification;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_page_is_dynamic_and_store_scoped(): void
    {
        [$owner, $storeA] = $this->ownerWithStore('Notify A');
        [, $storeB] = $this->ownerWithStore('Notify B', 'other@example.com');

        StoreNotification::query()->create([
            'store_id' => $storeA->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_IN_APP,
            'title' => 'Order for store A',
            'body' => 'Visible in store A',
            'status' => NotificationEvent::STATUS_SENT,
            'dedupe_key' => 'a-1',
            'recipient_key' => 'user:'.$owner->id,
            'is_read' => false,
            'sent_at' => now(),
        ]);

        StoreNotification::query()->create([
            'store_id' => $storeB->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::ORDER_CREATED,
            'channel' => NotificationEvent::CHANNEL_IN_APP,
            'title' => 'Order for store B',
            'body' => 'Must not leak',
            'status' => NotificationEvent::STATUS_SENT,
            'dedupe_key' => 'b-1',
            'recipient_key' => 'user:'.$owner->id,
            'is_read' => false,
            'sent_at' => now(),
        ]);

        // Owner is not a member of store B — attach wrongly would still be filtered by current store.
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('notifications'))
            ->assertOk()
            ->assertSeeText('Order for store A')
            ->assertDontSeeText('Order for store B')
            ->assertDontSeeText('New login from unrecognized device')
            ->assertSeeText('Settings')
            ->assertSeeText('Channels')
            ->assertSeeText('Email alerts')
            ->assertDontSeeText('Email Digest')
            ->assertDontSeeText('Quiet hours')
            ->assertSeeText('Notification Types');
    }

    public function test_mark_read_and_preferences_gate_dispatch(): void
    {
        Queue::fake();

        [$owner, $store] = $this->ownerWithStore('Prefs Store');
        $dispatcher = app(NotificationDispatcher::class);

        $created = $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::ORDER_CREATED,
            'New order #1',
            'A customer placed an order.',
            'order.created:1',
            ['action_url' => '/orders']
        );

        $this->assertNotEmpty($created);
        $inApp = collect($created)->firstWhere('channel', NotificationEvent::CHANNEL_IN_APP);
        $this->assertNotNull($inApp);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('notifications.read', $inApp))
            ->assertRedirect();

        $this->assertTrue($inApp->fresh()->is_read);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('notifications.preferences.update'), [
                'in_app_enabled' => '1',
                'email_enabled' => '0',
                'groups' => [
                    'orders' => '0',
                    'inventory' => '1',
                    'system' => '1',
                ],
            ])
            ->assertRedirect();

        $pref = NotificationPreference::query()
            ->where('store_id', $store->id)
            ->where('user_id', $owner->id)
            ->where('channel', NotificationEvent::CHANNEL_IN_APP)
            ->firstOrFail();

        $this->assertFalse($pref->allowsEvent(NotificationEvent::ORDER_CREATED));
        $this->assertTrue($pref->allowsEvent(NotificationEvent::INVENTORY_LOW));

        $blocked = $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::ORDER_CREATED,
            'Should not create',
            'Blocked by preference',
            'order.created:blocked',
        );

        $this->assertSame([], $blocked);
    }

    public function test_duplicate_events_do_not_create_duplicate_rows_or_jobs(): void
    {
        Queue::fake();

        [$owner, $store] = $this->ownerWithStore('Dedupe Store');
        $dispatcher = app(NotificationDispatcher::class);

        $first = $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::INVENTORY_LOW,
            'Low stock',
            'Only one alert',
            'inventory.low:variant:9',
        );

        $second = $dispatcher->notifyUser(
            $store,
            $owner,
            NotificationEvent::INVENTORY_LOW,
            'Low stock',
            'Only one alert',
            'inventory.low:variant:9',
        );

        $this->assertCount(2, $first); // in_app + email
        $this->assertCount(2, $second); // returns existing rows

        $this->assertSame(2, StoreNotification::query()->where('store_id', $store->id)->count());
        Queue::assertPushed(SendNotificationEmailJob::class, 1);
    }

    public function test_failed_email_can_be_retried(): void
    {
        Queue::fake();

        [$owner, $store] = $this->ownerWithStore('Retry Store');

        $failed = StoreNotification::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::IMPORT_COMPLETED,
            'channel' => NotificationEvent::CHANNEL_EMAIL,
            'title' => 'Import completed',
            'body' => 'Import finished',
            'status' => NotificationEvent::STATUS_FAILED,
            'dedupe_key' => 'import.completed:55',
            'recipient_key' => 'user:'.$owner->id,
            'recipient_email' => $owner->email,
            'failed_at' => now(),
            'error_message' => 'SMTP down',
            'attempts' => 1,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('notifications.retry', $failed))
            ->assertRedirect();

        $this->assertSame(NotificationEvent::STATUS_QUEUED, $failed->fresh()->status);
        Queue::assertPushed(SendNotificationEmailJob::class, fn ($job) => $job->notificationId === $failed->id);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerWithStore(string $name, string $email = 'owner@example.com'): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'role_id' => $role->id,
            'email' => $email,
        ]);
        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => 'notify-'.fake()->unique()->numberBetween(1000, 9999),
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
