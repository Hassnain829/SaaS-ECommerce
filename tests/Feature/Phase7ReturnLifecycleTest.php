<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use App\Support\OrderLifecycle;
use App\Support\ReturnLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7ReturnLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_request_approve_receive_and_complete_return(): void
    {
        [$owner, $store, $order, $item] = $this->seedOrderContext();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Record return')
            ->assertSeeText('No returns are recorded yet');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), [
                'items' => [$item->id => 1],
                'manual_instructions' => 'Ship back in original packaging.',
                'merchant_notes' => 'Customer called about size.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $return = OrderReturn::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(ReturnLifecycle::STATUS_REQUESTED, $return->status);
        $this->assertStringStartsWith('RMA-', $return->return_number);
        $this->assertDatabaseHas('return_items', [
            'return_id' => $return->id,
            'order_item_id' => $item->id,
            'requested_quantity' => 1,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => ReturnLifecycle::EVENT_RETURN_REQUESTED,
        ]);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'event_type' => 'return.requested',
            'severity' => SecurityLog::SEVERITY_INFO,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.approve', $return))
            ->assertRedirect()
            ->assertSessionHas('success');

        $return->refresh();
        $this->assertSame(ReturnLifecycle::STATUS_APPROVED, $return->status);
        $this->assertSame(1, (int) $return->items()->first()->approved_quantity);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.receive', $return), [
                'items' => [
                    $item->id => [
                        'received_quantity' => 1,
                        'condition' => ReturnLifecycle::CONDITION_SELLABLE,
                        'restock' => 0,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $return->refresh();
        $item->refresh();
        $this->assertSame(ReturnLifecycle::STATUS_RECEIVED, $return->status);
        $this->assertSame(1, (int) $item->returned_quantity);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('returns.complete', $return))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ReturnLifecycle::STATUS_COMPLETED, $return->fresh()->status);
    }

    public function test_cannot_return_more_than_remaining_quantity(): void
    {
        [$owner, $store, $order, $item] = $this->seedOrderContext(quantity: 2);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), [
                'items' => [$item->id => 2],
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), [
                'items' => [$item->id => 1],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(1, OrderReturn::query()->where('order_id', $order->id)->count());
    }

    public function test_received_quantity_reduces_future_returnable_amount(): void
    {
        [$owner, $store, $order, $item] = $this->seedOrderContext(quantity: 2);

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
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 2]])
            ->assertSessionHasErrors('items');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 1]])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_digital_items_cannot_use_physical_return_flow(): void
    {
        [$owner, $store, $order] = $this->seedOrderContext(quantity: 1, productType: 'digital');
        $item = $order->items()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertDontSeeText('Record return')
            ->assertSeeText('No returnable items are available on this order.');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 1]])
            ->assertSessionHasErrors();
    }

    public function test_store_isolation_and_staff_cannot_manage_returns(): void
    {
        [$owner, $store, $order, $item] = $this->seedOrderContext();
        $staff = $this->merchant('staff@return.test');
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $otherOwner = $this->merchant('other@return.test');
        $otherStore = $this->store($otherOwner, 'Other Return Store');
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 1]])
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.returns.store', $order), ['items' => [$item->id => 1]])
            ->assertRedirect();

        $return = OrderReturn::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($otherOwner)
            ->withSession(['current_store_id' => $otherStore->id])
            ->post(route('returns.approve', $return))
            ->assertNotFound();
    }

    public function test_status_only_refunded_mutation_is_blocked(): void
    {
        [$owner, $store, $order] = $this->seedOrderContext();
        $order->update(['status' => OrderLifecycle::ORDER_COMPLETED]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('orders.updateStatus', $order), ['status' => OrderLifecycle::ORDER_REFUNDED])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderLifecycle::ORDER_COMPLETED, $order->fresh()->status);
        $this->assertNull($order->fresh()->refunded_at);
    }

    /**
     * @return array{0: User, 1: Store, 2: Order, 3?: \App\Models\OrderItem}
     */
    private function seedOrderContext(int $quantity = 2, string $productType = 'physical'): array
    {
        $owner = $this->merchant('owner@return.test');
        $store = $this->store($owner, 'Phase 7 Returns');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [$product, $variant] = $this->product($store, $productType);
        $customer = $this->customer($store);
        $order = $this->order($store, $customer);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Return Tee',
            'variant_label' => 'Size: M',
            'sku_snapshot' => 'RET-M',
            'product_type_snapshot' => $productType,
            'quantity' => $quantity,
            'unit_price' => 25,
            'subtotal' => 25 * $quantity,
            'total' => 25 * $quantity,
        ]);

        return [$owner, $store, $order, $item];
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
            'full_name' => 'Return Buyer',
            'status' => 'active',
        ]);
    }

    private function order(Store $store, Customer $customer): Order
    {
        return Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#7001',
            'status' => OrderLifecycle::ORDER_COMPLETED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_FULFILLED,
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

    private function product(Store $store, string $productType = 'physical'): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Return Tee',
            'slug' => 'return-tee-'.Str::random(6),
            'base_price' => 25,
            'sku' => 'RET',
            'product_type' => $productType,
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'RET-M',
            'price' => 25,
            'stock' => 10,
        ]);

        return [$product, $variant];
    }
}
