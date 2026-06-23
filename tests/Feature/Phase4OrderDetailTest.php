<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderEventRecorder;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase4OrderDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_detail_renders_real_snapshots_empty_states_and_note_events(): void
    {
        $owner = $this->merchant('owner@example.com');
        $store = $this->store($owner, 'Phase 4 Order Detail');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        [$product, $variant] = $this->product($store, ['stock' => 6]);
        $customer = $this->customer($store);
        $order = $this->order($store, $customer);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Snapshot Jacket',
            'variant_label' => 'Size: M',
            'sku_snapshot' => 'SNAP-M',
            'product_type_snapshot' => 'physical',
            'quantity' => 2,
            'unit_price' => 30,
            'subtotal' => 60,
            'total' => 60,
        ]);
        $order->addresses()->create([
            'type' => 'shipping',
            'name' => 'Test Buyer',
            'email' => $customer->email,
            'address_line1' => '12 Snapshot Street',
            'city' => 'Karachi',
            'postal_code' => '74000',
            'country' => 'Pakistan',
        ]);

        app(OrderEventRecorder::class)->record($order, OrderLifecycle::EVENT_ORDER_CREATED, 'Order placed', 'Created from test.');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.notes.store', $order), ['body' => 'Pack with care.'])
            ->assertRedirect(route('orderViewDetails', $order));

        $this->assertDatabaseHas('order_events', [
            'store_id' => $store->id,
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_ORDER_NOTE_ADDED,
            'description' => 'Pack with care.',
        ]);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'order_note_added',
            'severity' => SecurityLog::SEVERITY_INFO,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Snapshot Jacket')
            ->assertSeeText('SKU SNAP-M')
            ->assertSeeText('12 Snapshot Street')
            ->assertSeeText('No shipments have been created yet')
            ->assertSeeText('No returns or refunds are recorded yet')
            ->assertSeeText('Pack with care.')
            ->assertDontSeeText('DHL Express')
            ->assertDontSeeText('Print Label')
            ->assertDontSeeText('Requested gift wrapping');
    }

    public function test_store_cannot_access_another_store_order(): void
    {
        $owner = $this->merchant('owner@example.com');
        $otherOwner = $this->merchant('other@example.com');
        $store = $this->store($owner, 'Store A');
        $otherStore = $this->store($otherOwner, 'Store B');
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($otherStore, $otherOwner, Store::ROLE_OWNER);

        $order = $this->order($otherStore, $this->customer($otherStore));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertNotFound();
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
            'full_name' => 'Test Buyer',
            'status' => 'active',
        ]);
    }

    private function order(Store $store, Customer $customer): Order
    {
        return Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#4001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'subtotal' => 60,
            'total' => 60,
            'grand_total' => 60,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 2,
            'placed_at' => now(),
        ]);
    }

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Snapshot Jacket',
            'slug' => 'snapshot-jacket-'.Str::random(6),
            'base_price' => 30,
            'sku' => 'SNAP',
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SNAP-M',
            'price' => 30,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }
}
