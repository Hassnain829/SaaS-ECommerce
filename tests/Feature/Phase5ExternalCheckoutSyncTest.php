<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\CheckoutMode;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase5ExternalCheckoutSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_order_endpoint_is_gone_without_a_token(): void
    {
        $this->postJson('/api/v1/external/orders', [])
            ->assertNotFound();
    }

    public function test_external_order_endpoint_is_gone_with_a_storefront_token(): void
    {
        [, $token] = $this->tokenedStore('Gone External Sync Store');

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', [
                'external_order_number' => 'WEB-10001',
                'payment_status' => 'paid',
            ])
            ->assertNotFound();
    }

    public function test_historical_external_orders_remain_readable(): void
    {
        [$store, , $owner] = $this->tokenedStore('Historical External Store');
        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'email' => 'historical.buyer@example.test',
            'full_name' => 'Historical Buyer',
            'status' => 'guest',
            'source' => 'external_checkout',
        ]);

        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#EXT-HIST-1',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'customer_phone' => '+15550100',
            'billing_same_as_shipping' => true,
            'subtotal' => 24,
            'shipping' => 4.50,
            'tax' => 1.50,
            'discount' => 0,
            'total' => 30,
            'grand_total' => 30,
            'currency_code' => 'USD',
            'order_source' => CheckoutMode::EXTERNAL,
            'channel' => 'api',
            'external_order_number' => 'WEB-HIST-1',
            'external_checkout_reference' => 'checkout-hist-1',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-hist-1',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
            'meta' => [],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertSeeText('External checkout')
            ->assertSeeText('WEB-HIST-1');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('WEB-HIST-1')
            ->assertSeeText('checkout-hist-1')
            ->assertSeeText('Historical Buyer');
    }

    /**
     * @return array{0: Store, 1: string, 2: User}
     */
    private function tokenedStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);
        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store->fresh(), $token, $owner];
    }
}
