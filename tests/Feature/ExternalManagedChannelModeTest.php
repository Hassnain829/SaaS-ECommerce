<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\ProductVariant;
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

    public function test_store_has_default_external_ownership_settings(): void
    {
        [$store] = $this->tokenedStore('Ownership Defaults Store');
        $service = app(ChannelOwnershipService::class);
        $store = $service->ensureChannelsStructure($store);

        $external = $service->externalCheckoutConfig($store);
        $platform = $service->platformCheckoutConfig($store);

        $this->assertSame('external', $external['checkout_owner']);
        $this->assertSame('external', $external['fulfillment_owner']);
        $this->assertSame('platform', $external['inventory_owner']);
        $this->assertSame('platform', $platform['checkout_owner']);
        $this->assertSame('platform', $platform['fulfillment_owner']);
        $this->assertSame('platform', $platform['inventory_owner']);
        $this->assertTrue($service->usesPlatformInventory($store, ChannelOwnershipService::CHANNEL_EXTERNAL));
        $this->assertTrue($service->isExternalManaged($store));
    }

    public function test_checkout_mode_change_keeps_channel_structure(): void
    {
        [$store, , $owner] = $this->tokenedStore('Ownership Mode Store');
        $this->connectedAccount($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.mode'), ['checkout_mode' => CheckoutMode::PLATFORM])
            ->assertRedirect(route('settings.payments.index'));

        $store = $store->fresh();
        $this->assertSame(CheckoutMode::PLATFORM, CheckoutMode::forStore($store));
        $this->assertNotNull(data_get($store->settings, 'channels.external_checkout.checkout_owner'));
        $this->assertFalse(app(ChannelOwnershipService::class)->isExternalManaged($store));
    }

    public function test_external_order_sync_records_shipping_and_fulfillment_snapshots(): void
    {
        [$store, $token] = $this->tokenedStore('External Snapshot Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-SNAPSHOT-1',
                'shipping' => [
                    'source' => 'external',
                    'method_name' => 'Standard delivery',
                    'carrier_name' => 'External courier',
                    'amount' => '9.50',
                    'currency' => 'USD',
                ],
                'fulfillment' => [
                    'managed_by' => 'external',
                    'status' => 'shipped',
                    'external_fulfillment_id' => 'ful-1',
                    'external_shipment_id' => 'ship-1',
                    'carrier_name' => 'DHL',
                    'tracking_number' => 'TRK123',
                    'tracking_url' => 'https://tracking.example.com/TRK123',
                    'shipped_at' => now()->toISOString(),
                ],
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame('Standard delivery', data_get($order->meta, 'shipping.method_name'));
        $this->assertSame('DHL', data_get($order->meta, 'fulfillment.carrier_name'));
        $this->assertSame('TRK123', data_get($order->meta, 'fulfillment.tracking_number'));
        $this->assertSame('external', data_get($order->meta, 'channel_ownership.fulfillment_owner'));
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fulfillment_status);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_EXTERNAL_SHIPPING_RECORDED,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_EXTERNAL_FULFILLMENT_RECORDED,
        ]);
    }

    public function test_external_order_sync_does_not_require_internal_shipping_or_carrier(): void
    {
        [$store, $token] = $this->tokenedStore('External No Internal Shipping Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-NO-INTERNAL-1',
                'shipping_total' => 0,
                'shipping' => [
                    'source' => 'external',
                    'method_name' => 'Store pickup',
                    'amount' => '0.00',
                    'currency' => 'USD',
                ],
            ]))
            ->assertCreated();

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_external_order_detail_shows_external_managed_fulfillment_copy(): void
    {
        [$store, $token, $owner] = $this->tokenedStore('External Detail Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-DETAIL-1',
                'fulfillment' => [
                    'managed_by' => 'external',
                    'status' => 'shipped',
                    'carrier_name' => 'UPS',
                    'tracking_number' => 'UPS-999',
                    'tracking_url' => 'https://tracking.example.com/UPS-999',
                ],
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSee('Fulfillment managed externally')
            ->assertSee('UPS')
            ->assertSee('UPS-999');
    }

    public function test_payments_page_shows_checkout_and_fulfillment_mode_section(): void
    {
        [$store, , $owner] = $this->tokenedStore('Payments Ownership UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSee('Checkout and fulfillment mode')
            ->assertSee('External managed')
            ->assertSee('Platform managed');
    }

    public function test_external_order_sync_succeeds_without_shipping_or_fulfillment_objects(): void
    {
        [$store, $token] = $this->tokenedStore('Minimal External Order Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-MINIMAL-1',
                'shipping_total' => 0,
                'shipping' => null,
                'fulfillment' => null,
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertNull(data_get($order->meta, 'shipping'));
        $this->assertNull(data_get($order->meta, 'fulfillment'));
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_EXTERNAL_SHIPPING_RECORDED,
        ]);
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_EXTERNAL_FULFILLMENT_RECORDED,
        ]);
    }

    public function test_external_order_sync_without_fulfillment_does_not_create_fulfillment_event(): void
    {
        [$store, $token] = $this->tokenedStore('No Fulfillment Event Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-NO-FUL-1',
                'shipping' => [
                    'source' => 'external',
                    'method_name' => 'Economy delivery',
                    'amount' => '5.00',
                    'currency' => 'USD',
                ],
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame('Economy delivery', data_get($order->meta, 'shipping.method_name'));
        $this->assertNull(data_get($order->meta, 'fulfillment'));
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_EXTERNAL_SHIPPING_RECORDED,
        ]);
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_EXTERNAL_FULFILLMENT_RECORDED,
        ]);
    }

    public function test_external_managed_order_detail_shows_externally_managed_badge_and_empty_state(): void
    {
        [$store, $token, $owner] = $this->tokenedStore('External Empty Fulfillment Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-EMPTY-1',
                'shipping_total' => 0,
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSee('Externally managed')
            ->assertSee('No shipment update has been received yet');
    }

    public function test_shipping_page_shows_external_managed_banner(): void
    {
        [$store, , $owner] = $this->tokenedStore('Shipping Banner Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSee('External storefront manages checkout shipping');
    }

    public function test_external_checkout_with_platform_inventory_deducts_stock(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Inventory Store');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-INV-PLATFORM-1',
                'items' => [[
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => '12.00',
                ]],
            ]))
            ->assertCreated();

        $this->assertSame(3, (int) $variant->fresh()->stock);
    }

    public function test_external_checkout_with_platform_inventory_creates_inventory_events(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Inventory Events Store');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-INV-EVENTS-1',
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_RESERVED,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
        ]);
    }

    public function test_external_checkout_with_platform_inventory_fails_on_insufficient_stock(): void
    {
        [$store, $token] = $this->tokenedStore('Platform Inventory Fail Store');
        [, $variant] = $this->product($store, ['stock' => 1]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-INV-FAIL-1',
                'items' => [[
                    'variant_id' => $variant->id,
                    'quantity' => 3,
                    'unit_price' => '12.00',
                ]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(1, (int) $variant->fresh()->stock);
    }

    public function test_payments_page_shows_platform_inventory_copy_for_external_orders(): void
    {
        [$store, , $owner] = $this->tokenedStore('Payments Platform Inventory Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSee('Inventory source for external orders')
            ->assertSee('External orders reduce dashboard stock when they sync.');
    }

    public function test_external_checkout_with_external_inventory_does_not_deduct_stock(): void
    {
        [$store, $token] = $this->tokenedStore('External Inventory Store', inventoryOwner: 'external');
        [, $variant] = $this->product($store, ['stock' => 5]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-INV-EXTERNAL-1',
                'items' => [[
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => '12.00',
                ]],
            ]))
            ->assertCreated();

        $this->assertSame(5, (int) $variant->fresh()->stock);
    }

    public function test_external_checkout_with_external_inventory_does_not_create_inventory_events(): void
    {
        [$store, $token] = $this->tokenedStore('External Inventory Events Store', inventoryOwner: 'external');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-INV-NO-EVENTS-1',
                'shipping' => [
                    'source' => 'external',
                    'method_name' => 'Economy delivery',
                    'amount' => '5.00',
                    'currency' => 'USD',
                ],
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame('Economy delivery', data_get($order->meta, 'shipping.method_name'));
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_RESERVED,
        ]);
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_DEDUCTED,
        ]);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_INVENTORY_EXTERNAL_MANAGED,
        ]);
    }

    public function test_external_inventory_order_records_external_managed_event_copy(): void
    {
        [$store, $token, $owner] = $this->tokenedStore('External Inventory Detail Store', inventoryOwner: 'external');
        [, $variant] = $this->product($store);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->payload($variant, [
                'external_order_number' => 'WEB-INV-DETAIL-1',
            ]))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSee('Inventory managed externally')
            ->assertSee('Dashboard stock was not changed for this external order');
    }

    public function test_payments_page_shows_external_inventory_copy(): void
    {
        [$store, , $owner] = $this->tokenedStore('Payments External Inventory Store', inventoryOwner: 'external');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertOk()
            ->assertSee('External orders are recorded here, but dashboard stock is not changed.');
    }

    public function test_legacy_implicit_external_inventory_defaults_to_platform(): void
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
                'channels' => [
                    'external_checkout' => [
                        'inventory_owner' => 'external',
                    ],
                ],
            ],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $service = app(ChannelOwnershipService::class);
        $store = $service->ensureChannelsStructure($store);

        $this->assertSame('platform', $service->inventoryOwner($store, ChannelOwnershipService::CHANNEL_EXTERNAL));
    }

    public function test_owner_can_change_external_inventory_source_setting(): void
    {
        [$store, , $owner] = $this->tokenedStore('Inventory Setting Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.external-inventory'), [
                'inventory_owner' => 'external',
            ])
            ->assertRedirect(route('settings.payments.index'));

        $store = $store->fresh();
        $this->assertSame('external', data_get($store->settings, 'channels.external_checkout.inventory_owner'));
        $this->assertTrue(data_get($store->settings, 'channels.external_checkout.inventory_owner_configured'));
    }

    public function test_store_a_cannot_change_store_b_inventory_setting(): void
    {
        [$storeA, , $ownerA] = $this->tokenedStore('Inventory Store A');
        [$storeB] = $this->tokenedStore('Inventory Store B');

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.payments.external-inventory'), [
                'inventory_owner' => 'external',
            ])
            ->assertRedirect(route('settings.payments.index'));

        $storeB = $storeB->fresh();
        $service = app(ChannelOwnershipService::class);
        $this->assertSame('platform', $service->inventoryOwner($storeB, ChannelOwnershipService::CHANNEL_EXTERNAL));
    }

    public function test_staff_without_settings_permission_cannot_change_inventory_source(): void
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $staff = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Staff Inventory Store',
            'slug' => 'staff-inventory-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.payments.external-inventory'), [
                'inventory_owner' => 'external',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: Store, 1: string, 2: User}
     */
    private function tokenedStore(string $name, ?string $inventoryOwner = null): array
    {
        $settings = [];
        if ($inventoryOwner !== null) {
            $settings['channels'] = [
                'external_checkout' => [
                    'inventory_owner' => $inventoryOwner,
                    'inventory_owner_configured' => true,
                ],
            ];
        }

        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => $settings,
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $token, $owner];
    }

    private function connectedAccount(Store $store): void
    {
        \App\Models\PaymentProviderAccount::query()->create([
            'store_id' => $store->id,
            'provider' => 'stripe',
            'connection_type' => 'connect',
            'mode' => (string) config('payments.stripe.mode', 'test'),
            'provider_account_id' => 'acct_test_'.Str::random(8),
            'status' => 'active',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'is_default' => true,
            'last_verified_at' => now(),
        ]);
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'External Product',
            'slug' => 'external-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'EXT-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
            'price' => $overrides['price'] ?? 12,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'external_order_number' => 'WEB-'.Str::upper(Str::random(8)),
            'external_checkout_reference' => 'checkout-'.Str::random(8),
            'payment_status' => 'paid',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-'.Str::random(8),
            'placed_at' => now()->toISOString(),
            'currency_code' => 'USD',
            'shipping_total' => 4.50,
            'tax_total' => 0,
            'discount_total' => 0,
            'customer' => [
                'full_name' => 'External Buyer',
                'email' => 'external.buyer@example.test',
                'phone' => '+15550199',
            ],
            'shipping_address' => [
                'name' => 'External Buyer',
                'address_line1' => '45 External Road',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
                'phone' => '+15550199',
            ],
            'billing_address' => [
                'same_as_shipping' => true,
            ],
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_price' => '12.00',
                    'external_line_id' => 'line-1',
                ],
            ],
        ], $overrides);
    }
}
