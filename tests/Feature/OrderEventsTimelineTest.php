<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderEventRecorder;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEventsTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_event_relationships_are_store_scoped(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Timeline Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $order = $this->order($store);

        $event = app(OrderEventRecorder::class)->record(
            $order,
            OrderLifecycle::EVENT_ORDER_CREATED,
            'Order placed',
            'The order was created.',
            ['order_number' => $order->order_number],
            $owner,
        );

        $this->assertSame($store->id, $event->store->id);
        $this->assertSame($order->id, $event->order->id);
        $this->assertSame($owner->id, $event->actor->id);
        $this->assertTrue($order->events()->whereKey($event->id)->exists());
    }

    public function test_order_status_update_creates_order_event_and_security_log(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Status Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $order = $this->order($store, ['status' => OrderLifecycle::ORDER_PENDING]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orderViewDetails', $order))
            ->patch(route('orders.updateStatus', $order), ['status' => OrderLifecycle::ORDER_CONFIRMED])
            ->assertRedirect(route('orderViewDetails', $order));

        $this->assertSame(OrderLifecycle::ORDER_CONFIRMED, $order->fresh()->status);

        $this->assertDatabaseHas('order_events', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'actor_user_id' => $owner->id,
            'event_type' => OrderLifecycle::EVENT_ORDER_STATUS_CHANGED,
            'title' => 'Order status changed',
        ]);

        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'order_status_changed',
            'severity' => SecurityLog::SEVERITY_INFO,
        ]);
    }

    public function test_shipped_and_delivered_are_rejected_as_order_statuses(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Invalid Status Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $order = $this->order($store, ['status' => OrderLifecycle::ORDER_PENDING]);

        foreach (['shipped', 'delivered'] as $invalidStatus) {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->from(route('orderViewDetails', $order))
                ->patch(route('orders.updateStatus', $order), ['status' => $invalidStatus])
                ->assertRedirect(route('orderViewDetails', $order))
                ->assertSessionHasErrors('status');
        }

        $this->assertSame(OrderLifecycle::ORDER_PENDING, $order->fresh()->status);
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_ORDER_STATUS_CHANGED,
        ]);
    }

    public function test_invalid_order_status_transition_is_rejected(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Transition Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $order = $this->order($store, ['status' => OrderLifecycle::ORDER_COMPLETED]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('orderViewDetails', $order))
            ->patch(route('orders.updateStatus', $order), ['status' => OrderLifecycle::ORDER_PROCESSING])
            ->assertRedirect(route('orderViewDetails', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderLifecycle::ORDER_COMPLETED, $order->fresh()->status);
    }

    public function test_staff_cannot_update_order_status(): void
    {
        $owner = $this->merchant('owner@example.com');
        $staff = $this->merchant('staff@example.com');
        $store = $this->store($owner, 'Staff Order Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $staff, Store::ROLE_STAFF);
        $order = $this->order($store, ['status' => OrderLifecycle::ORDER_PENDING]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('orders.updateStatus', $order), ['status' => OrderLifecycle::ORDER_CONFIRMED])
            ->assertForbidden();

        $this->assertSame(OrderLifecycle::ORDER_PENDING, $order->fresh()->status);
    }

    public function test_orders_list_renders_separate_status_badges(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Order List Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->order($store, [
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertSeeText('Order state')
            ->assertSeeText('Payment')
            ->assertSeeText('Fulfillment')
            ->assertSeeText('Confirmed')
            ->assertSeeText('Paid')
            ->assertSeeText('Unfulfilled')
            ->assertDontSeeText('Shipped');
    }

    public function test_order_detail_renders_real_events_and_empty_shipment_state(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Detail Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $order = $this->order($store);

        app(OrderEventRecorder::class)->record(
            $order,
            OrderLifecycle::EVENT_ORDER_CREATED,
            'Order placed',
            'This event came from the order event table.',
        );

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Order placed')
            ->assertSeeText('This event came from the order event table.')
            ->assertSeeText('No shipments have been created yet. Fulfillment tracking will appear here after shipping is implemented.')
            ->assertDontSeeText('DHL Express')
            ->assertDontSeeText('Print Label')
            ->assertDontSeeText('Pending carrier generation')
            ->assertDontSeeText('Status updated automatically via integrated courier');
    }

    public function test_order_detail_shows_empty_timeline_when_no_events_exist(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Empty Timeline Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $order = $this->order($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('No order activity has been recorded yet. Future status changes and important actions will appear here.');
    }

    public function test_cross_store_user_cannot_access_another_store_order_timeline(): void
    {
        $owner = $this->merchant('owner@example.com');
        $otherOwner = $this->merchant('other@example.com');
        $store = $this->store($owner, 'Store A');
        $otherStore = $this->store($otherOwner, 'Store B');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);
        $otherOrder = $this->order($otherStore);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $otherOrder))
            ->assertNotFound();
    }

    public function test_backfill_order_events_command_is_idempotent(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Backfill Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $order = $this->order($store, [
            'status' => OrderLifecycle::ORDER_PROCESSING,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
        ]);

        $this->assertSame(0, $order->events()->count());

        $this->artisan('orders:backfill-events')
            ->expectsOutput('Backfilled 3 order events.')
            ->assertExitCode(0);

        $this->assertSame(3, $order->fresh()->events()->count());
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_ORDER_CREATED,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_PAYMENT_STATUS_CHANGED,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_ORDER_STATUS_CHANGED,
        ]);

        $this->artisan('orders:backfill-events')
            ->expectsOutput('Backfilled 0 order events.')
            ->assertExitCode(0);

        $this->assertSame(3, $order->fresh()->events()->count());
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    private function order(Store $store, array $overrides = []): Order
    {
        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'email' => fake()->unique()->safeEmail(),
            'full_name' => 'Test Customer',
            'status' => 'guest',
        ]);

        return Order::query()->create(array_merge([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#'.fake()->unique()->numberBetween(2000, 9999),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'customer_phone' => '+1234567890',
            'billing_same_as_shipping' => true,
            'subtotal' => 25,
            'total' => 25,
            'grand_total' => 25,
            'currency_code' => $store->currency,
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 0,
            'total_quantity' => 0,
            'placed_at' => now(),
        ], $overrides));
    }
}
