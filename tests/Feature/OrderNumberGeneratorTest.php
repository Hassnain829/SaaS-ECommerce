<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_store_gets_sequential_human_readable_order_numbers(): void
    {
        $store = $this->createStore('Sequence Store');
        $generator = app(OrderNumberGenerator::class);

        $this->assertSame('#1001', $generator->generate($store));
        $this->assertSame('#1002', $generator->generate($store));

        $this->assertDatabaseHas('store_order_sequences', [
            'store_id' => $store->id,
            'next_number' => 1003,
        ]);
    }

    public function test_different_stores_can_use_the_same_numeric_sequence(): void
    {
        $firstStore = $this->createStore('First Sequence Store');
        $secondStore = $this->createStore('Second Sequence Store');
        $generator = app(OrderNumberGenerator::class);

        $this->assertSame('#1001', $generator->generate($firstStore));
        $this->assertSame('#1001', $generator->generate($secondStore));
    }

    public function test_order_number_uniqueness_is_scoped_to_store(): void
    {
        $firstStore = $this->createStore('First Order Store');
        $secondStore = $this->createStore('Second Order Store');

        Order::query()->create([
            'store_id' => $firstStore->id,
            'order_number' => '#1001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'subtotal' => 10,
            'total' => 10,
            'grand_total' => 10,
            'currency_code' => 'USD',
        ]);

        Order::query()->create([
            'store_id' => $secondStore->id,
            'order_number' => '#1001',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'subtotal' => 10,
            'total' => 10,
            'grand_total' => 10,
            'currency_code' => 'USD',
        ]);

        $this->assertDatabaseCount('orders', 2);
    }

    private function createStore(string $name): Store
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);

        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }
}
