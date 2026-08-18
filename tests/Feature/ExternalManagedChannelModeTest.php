<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Channels\ChannelOwnershipService;
use App\Support\CheckoutMode;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalManagedChannelModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_checkout_is_never_externally_managed(): void
    {
        [$store] = $this->tokenedStore('Ownership Defaults Store');
        $service = app(ChannelOwnershipService::class);
        $platform = $service->platformCheckoutConfig($store);

        $this->assertSame('platform', $platform['checkout_owner']);
        $this->assertSame('platform', $platform['fulfillment_owner']);
        $this->assertSame('platform', $platform['inventory_owner']);
        $this->assertFalse($service->isExternalManaged($store));
        $this->assertTrue($service->isPlatformManaged($store));
        $this->assertSame(CheckoutMode::PLATFORM, CheckoutMode::forStore($store));
        $this->assertNull(data_get($store->settings, 'checkout_mode'));
    }

    public function test_legacy_external_inventory_is_read_only_and_runtime_defaults_to_platform(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Legacy Inventory Store',
            'slug' => 'legacy-inventory-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [
                'checkout_mode' => CheckoutMode::EXTERNAL,
                'channels' => [
                    'external_checkout' => [
                        'enabled' => true,
                        'inventory_owner' => 'external',
                        'inventory_owner_configured' => true,
                    ],
                ],
            ],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $service = app(ChannelOwnershipService::class);

        $this->assertSame(CheckoutMode::EXTERNAL, data_get($store->settings, 'checkout_mode'));
        $this->assertTrue(data_get($store->settings, 'channels.external_checkout.enabled'));
        $this->assertSame('external', $service->inventoryOwner($store, CheckoutMode::EXTERNAL));
        $this->assertSame('platform', $service->inventoryOwner($store));
        $this->assertFalse($service->isExternalManaged($store));
    }

    public function test_historical_external_orders_remain_externally_managed_for_fulfillment(): void
    {
        [$store] = $this->tokenedStore('Historical Ownership Store');
        $customer = Customer::query()->create([
            'store_id' => $store->id,
            'email' => 'hist.owner@example.test',
            'full_name' => 'Historical Owner Buyer',
            'status' => 'guest',
        ]);
        $order = Order::query()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => '#EXT-OWN-1',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => $customer->email,
            'billing_same_as_shipping' => true,
            'subtotal' => 20,
            'total' => 20,
            'grand_total' => 20,
            'currency_code' => 'USD',
            'order_source' => CheckoutMode::EXTERNAL,
            'channel' => 'api',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
            'meta' => [
                'fulfillment' => ['managed_by' => 'external'],
            ],
        ]);

        $service = app(ChannelOwnershipService::class);
        $this->assertTrue($service->isOrderExternallyManaged($order));
        $this->assertTrue($service->isExternalManaged($store, CheckoutMode::EXTERNAL));
        $this->assertFalse($service->isExternalManaged($store));
    }

    public function test_payments_page_is_platform_only(): void
    {
        [$store, , $owner] = $this->tokenedStore('Payments Ownership UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSeeText('How this store accepts payments')
            ->assertSeeText('Platform checkout')
            ->assertDontSeeText('External checkout')
            ->assertDontSeeText('Inventory for external orders');
    }

    public function test_external_order_endpoint_is_gone(): void
    {
        [, $token] = $this->tokenedStore('Gone External Channel Store');

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', [
                'external_order_number' => 'WEB-GONE',
                'payment_status' => 'paid',
            ])
            ->assertNotFound();
    }

    public function test_external_inventory_setting_route_is_gone(): void
    {
        [$store, , $owner] = $this->tokenedStore('Inventory Setting Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post('/settings/payments/external-inventory', [
                'inventory_owner' => 'external',
            ])
            ->assertNotFound();
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

        $token = app(\App\Services\ConnectedSiteService::class)->issuePrimaryCredential($store)['plain'];

        return [$store, $token, $owner];
    }
}
