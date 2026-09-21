<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\StoreUser;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\NotificationEvent;
use App\Support\OrderLifecycle;
use App\Support\StoreMemberAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPermissionP1HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_manager_does_not_receive_store_notifications(): void
    {
        $owner = $this->merchant('p1-owner@example.com');
        $manager = $this->merchant('p1-manager@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER, StoreUser::STATUS_SUSPENDED);

        app(NotificationDispatcher::class)->notifyStore(
            $store,
            NotificationEvent::ORDER_CREATED,
            'New order',
            'Order arrived.',
            'p1-order-1',
        );

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::ORDER_CREATED,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'store_id' => $store->id,
            'user_id' => $manager->id,
            'type' => NotificationEvent::ORDER_CREATED,
        ]);
    }

    public function test_member_without_orders_view_does_not_receive_order_notifications(): void
    {
        $owner = $this->merchant('p1-owner2@example.com');
        $member = $this->merchant('p1-products-only@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['products.view', 'products.edit']);

        app(NotificationDispatcher::class)->notifyStore(
            $store,
            NotificationEvent::ORDER_CREATED,
            'New order',
            'Order arrived.',
            'p1-order-2',
        );

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'type' => NotificationEvent::ORDER_CREATED,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'type' => NotificationEvent::ORDER_CREATED,
        ]);
    }

    public function test_member_with_orders_view_receives_order_notifications(): void
    {
        $owner = $this->merchant('p1-owner3@example.com');
        $member = $this->merchant('p1-orders@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['orders.view']);

        app(NotificationDispatcher::class)->notifyStore(
            $store,
            NotificationEvent::ORDER_CREATED,
            'New order',
            'Order arrived.',
            'p1-order-3',
        );

        $this->assertDatabaseHas('notifications', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'type' => NotificationEvent::ORDER_CREATED,
        ]);
    }

    public function test_store_management_hides_order_and_catalog_metrics_without_permissions(): void
    {
        $owner = $this->merchant('hub-owner@example.com');
        $member = $this->merchant('hub-member@example.com');
        $store = $this->store($owner, 'Hub Leak Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['customers.view']);

        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Secret Product',
            'slug' => 'secret-product-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => null,
            'base_price' => 20,
            'sku' => 'SEC-'.fake()->unique()->numberBetween(1000, 9999),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#HUB-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'subtotal' => 99,
            'total' => 99,
            'grand_total' => 99,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);

        OrderEvent::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'event_type' => 'order.created',
            'title' => 'Secret order event',
            'description' => 'Should stay hidden',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Hub Leak Store')
            ->assertDontSeeText('Secret Product')
            ->assertDontSeeText('Secret order event')
            ->assertDontSee('$99')
            ->assertDontSee('99.00');

        $response->assertSee('—', false);
        $response->assertSeeText('Hidden without product access');
        $response->assertSeeText('Order activity is hidden until you have order access');
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name = 'P1 Store'): Store
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

    private function attach(Store $store, User $user, string $role, ?string $status = null): void
    {
        $pivot = ['role' => $role];
        if ($status !== null) {
            $pivot['status'] = $status;
        }

        $store->members()->syncWithoutDetaching([
            $user->id => $pivot,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grant(Store $store, User $user, array $permissions): void
    {
        $this->attach($store, $user, Store::ROLE_MEMBER, StoreUser::STATUS_ACTIVE);

        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $user,
            $permissions,
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            StoreUser::STATUS_ACTIVE,
        );
    }
}
