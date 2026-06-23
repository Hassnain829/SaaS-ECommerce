<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\InventoryLevel;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventorySyncService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6NearestEligibleOriginRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_phase6c0a',
            'payments.stripe.secret' => 'sk_test_phase6c0a',
            'payments.stripe.webhook_secret' => 'whsec_phase6c0a',
        ]);

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_phase6c0a_'.$checkout->id.'_'.Str::random(6),
                    clientSecret: 'pi_phase6c0a_'.$checkout->id.'_secret_test',
                    status: 'requires_payment_method',
                    amount: (float) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: ['id' => 'pi_phase6c0a_'.$checkout->id, 'status' => 'requires_payment_method'],
                );
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                return new PaymentWebhookResult(
                    eventType: 'payment_intent.succeeded',
                    providerIntentId: $providerIntentId,
                    status: 'succeeded',
                    amount: 24.00,
                    currencyCode: 'USD',
                    raw: [
                        'id' => 'client_confirm_'.$providerIntentId,
                        'type' => 'payment_intent.succeeded',
                        'object' => [
                            'id' => $providerIntentId,
                            'status' => 'succeeded',
                            'amount' => 2400,
                            'currency' => 'usd',
                        ],
                    ],
                );
            }
        });
    }

    public function test_platform_checkout_selects_stock_aware_origin_by_service_area(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6C Routing Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();
        $chicago = $this->location($store, 'Chicago stock room', [
            'state' => 'IL',
            'postal_code' => '60601',
            'service_countries' => ['US'],
            'service_regions' => ['IL'],
            'service_postal_patterns' => ['606*'],
            'routing_priority' => 10,
        ]);
        $this->stockAtLocations($variant, [
            $default->id => 0,
            $chicago->id => 5,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'shipping_address' => [
                    'name' => 'Chicago Buyer',
                    'address_line1' => '100 Loop Street',
                    'city' => 'Chicago',
                    'state' => 'IL',
                    'postal_code' => '60611',
                    'country' => 'US',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.fulfillment_origin.location_id', $chicago->id);

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->assertSame($chicago->id, (int) $checkout->fulfillment_origin_location_id);
        $this->assertSame('nearest_eligible_0a', data_get($checkout->fulfillment_routing_snapshot, 'routing_strategy'));
        $this->assertSame('service_area_stock_priority', data_get($checkout->fulfillment_routing_snapshot, 'routing_basis'));
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $chicago->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('checkout_events', [
            'checkout_id' => $checkout->id,
            'event_type' => 'fulfillment.origin_selected',
        ]);
    }

    public function test_pickup_delivery_method_requires_pickup_location_and_reroutes_reservation(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6C Pickup Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();
        $pickupOne = $this->location($store, 'Downtown pickup counter', [
            'type' => Location::TYPE_STORE,
            'pickup_enabled' => true,
            'routing_priority' => 5,
        ]);
        $pickupTwo = $this->location($store, 'North pickup counter', [
            'type' => Location::TYPE_STORE,
            'pickup_enabled' => true,
            'routing_priority' => 6,
        ]);
        $methods = $this->shippingSetup($store, includePickup: true);
        $this->stockAtLocations($variant, [
            $default->id => 5,
            $pickupOne->id => 5,
            $pickupTwo->id => 5,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $initialOriginId = (int) $checkout->fulfillment_origin_location_id;

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/delivery-options', [
                'shipping_address' => $this->checkoutPayload($variant)['shipping_address'],
            ])
            ->assertOk()
            ->assertJsonPath('delivery_options.1.name', 'Store pickup')
            ->assertJsonPath('delivery_options.1.pickup_required', true)
            ->assertJsonCount(2, 'delivery_options.1.pickup_locations');

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['pickup']->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_location_id']);

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['pickup']->id,
                'pickup_location_id' => $pickupTwo->id,
            ])
            ->assertOk()
            ->assertJsonPath('checkout.pickup_location_id', $pickupTwo->id)
            ->assertJsonPath('checkout.fulfillment_origin.location_id', $pickupTwo->id);

        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $pickupTwo->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $initialOriginId,
            'status' => InventoryReservation::STATUS_RELEASED,
        ]);
    }

    public function test_delivery_options_count_the_current_checkout_reservation_for_exact_stock(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6C Exact Stock Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();
        $this->shippingSetup($store);
        $this->stockAtLocations($variant, [
            $default->id => 2,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(0, (int) InventoryLevel::query()
            ->where('location_id', $default->id)
            ->whereHas('inventoryItem', fn ($query) => $query->where('variant_id', $variant->id))
            ->value('available'));

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/delivery-options', [
                'shipping_address' => $this->checkoutPayload($variant)['shipping_address'],
            ])
            ->assertOk()
            ->assertJsonPath('delivery_options.0.name', 'Standard delivery')
            ->assertJsonPath('delivery_options.0.fulfillment_origin.location_id', $default->id);
    }

    public function test_platform_order_copies_routing_snapshot_and_shipment_uses_prefilled_origin(): void
    {
        [$store, $token, $owner] = $this->tokenedStore('Phase 6C Order Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();
        $origin = $this->location($store, 'West fulfillment origin', [
            'service_countries' => ['US'],
            'service_regions' => ['CA'],
            'service_postal_patterns' => ['941*'],
            'routing_priority' => 1,
        ]);
        $this->stockAtLocations($variant, [
            $default->id => 0,
            $origin->id => 5,
        ]);
        $methods = $this->shippingSetup($store);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'shipping_method_id' => $methods['standard']->id,
            ]))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/confirm')
            ->assertOk();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $item = $order->items()->firstOrFail();

        $this->assertSame($origin->id, (int) data_get($order->meta, 'fulfillment_routing.origin_location_id'));
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'event_type' => OrderLifecycle::EVENT_FULFILLMENT_ORIGIN_SELECTED,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSee('Fulfillment origin selected by service area routing')
            ->assertSee($origin->name);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'origin_location_id' => $origin->id,
                'carrier_account_id' => $methods['account']->id,
                'shipping_method_id' => $methods['standard']->id,
                'items' => [$item->id => 1],
            ])
            ->assertRedirect();

        $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame($origin->id, (int) data_get($shipment->metadata, 'routed_origin_location_id'));
        $this->assertSame('routed_origin', data_get($shipment->metadata, 'origin_selection'));
    }

    public function test_external_checkout_routes_platform_inventory_but_not_external_inventory(): void
    {
        [$store, $token] = $this->tokenedStore('Phase 6C External Platform Inventory', platformMode: false);
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();
        $origin = $this->location($store, 'Texas origin', [
            'service_countries' => ['US'],
            'service_regions' => ['TX'],
            'routing_priority' => 1,
        ]);
        $this->stockAtLocations($variant, [
            $default->id => 0,
            $origin->id => 3,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/external/orders', $this->externalPayload($variant))
            ->assertCreated();

        $order = Order::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame($origin->id, (int) data_get($order->meta, 'fulfillment_routing.origin_location_id'));
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'external_order',
            'reference_id' => (string) $order->id,
            'location_id' => $origin->id,
            'status' => InventoryReservation::STATUS_DEDUCTED,
        ]);

        [$externalStore, $externalToken] = $this->tokenedStore(
            'Phase 6C External Inventory Owner',
            platformMode: false,
            inventoryOwner: 'external'
        );
        [, $externalVariant] = $this->product($externalStore, ['stock' => 2]);

        $this->withToken($externalToken)
            ->postJson('/api/v1/external/orders', $this->externalPayload($externalVariant))
            ->assertCreated();

        $externalOrder = Order::query()->where('store_id', $externalStore->id)->firstOrFail();
        $this->assertNull(data_get($externalOrder->meta, 'fulfillment_routing'));
        $this->assertDatabaseMissing('order_events', [
            'order_id' => $externalOrder->id,
            'event_type' => OrderLifecycle::EVENT_FULFILLMENT_ORIGIN_SELECTED,
        ]);
    }

    private function tokenedStore(string $name, bool $platformMode = true, ?string $inventoryOwner = null): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $settings = $platformMode ? ['checkout_mode' => CheckoutMode::PLATFORM] : [];
        if ($inventoryOwner) {
            $settings['channels'] = [
                'external_checkout' => [
                    'inventory_owner' => $inventoryOwner,
                    'inventory_owner_configured' => true,
                ],
            ];
        }

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

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'Routing Product',
            'slug' => 'routing-product-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'ROUTE-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $overrides['variant_sku'] ?? $product->sku.'-D',
            'price' => $overrides['price'] ?? 12,
            'stock' => $overrides['stock'] ?? 5,
        ]);

        return [$product, $variant];
    }

    private function location(Store $store, string $name, array $overrides = []): Location
    {
        return Location::query()->create(array_merge([
            'store_id' => $store->id,
            'name' => $name,
            'type' => Location::TYPE_WAREHOUSE,
            'city' => 'Test City',
            'state' => null,
            'postal_code' => null,
            'country_code' => 'US',
            'is_default' => false,
            'is_active' => true,
            'fulfills_online_orders' => true,
            'pickup_enabled' => false,
            'routing_priority' => 100,
        ], $overrides));
    }

    /**
     * @param  array<int, int>  $stockByLocation
     */
    private function stockAtLocations(ProductVariant $variant, array $stockByLocation): void
    {
        $sync = app(InventorySyncService::class);
        $item = $sync->ensureInventoryItemForVariant($variant);
        InventoryLevel::query()
            ->where('inventory_item_id', $item->id)
            ->update(['available' => 0, 'reserved' => 0, 'committed' => 0, 'incoming' => 0]);

        foreach ($stockByLocation as $locationId => $available) {
            $location = Location::query()->findOrFail($locationId);
            $level = $sync->ensureLevel($item, $location, 0);
            $level->forceFill([
                'available' => $available,
                'reserved' => 0,
                'committed' => 0,
                'incoming' => 0,
            ])->save();
        }

        $sync->syncVariantStockCache($variant);
    }

    private function shippingSetup(Store $store, bool $includePickup = false): array
    {
        $carrier = Carrier::query()->create([
            'name' => 'Manual courier '.Str::random(5),
            'code' => 'manual-'.Str::random(8),
            'type' => Carrier::TYPE_MANUAL,
            'is_system' => false,
            'is_active' => true,
        ]);
        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $carrier->id,
            'display_name' => 'Manual courier',
            'connection_type' => CarrierAccount::CONNECTION_MANUAL,
            'status' => CarrierAccount::STATUS_ENABLED,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => true,
        ]);
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
        ]);
        $standard = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Standard delivery',
            'code' => 'standard-delivery-'.Str::random(5),
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 6.50,
            'enabled_for_checkout' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $result = ['account' => $account, 'standard' => $standard, 'zone' => $zone];
        if ($includePickup) {
            $pickupCarrier = Carrier::query()->create([
                'name' => 'Store pickup '.Str::random(5),
                'code' => 'store-pickup-'.Str::random(8),
                'type' => Carrier::TYPE_PICKUP,
                'is_system' => false,
                'is_active' => true,
            ]);
            $pickupAccount = CarrierAccount::query()->create([
                'store_id' => $store->id,
                'carrier_id' => $pickupCarrier->id,
                'display_name' => 'Store pickup',
                'connection_type' => CarrierAccount::CONNECTION_MANUAL,
                'status' => CarrierAccount::STATUS_ENABLED,
                'supported_countries' => ['US'],
                'enabled_for_checkout' => true,
            ]);
            $result['pickup'] = ShippingMethod::query()->create([
                'store_id' => $store->id,
                'shipping_zone_id' => $zone->id,
                'carrier_account_id' => $pickupAccount->id,
                'name' => 'Store pickup',
                'code' => 'store-pickup',
                'rate_type' => ShippingMethod::RATE_FREE,
                'flat_rate' => 0,
                'enabled_for_checkout' => true,
                'is_active' => true,
                'sort_order' => 2,
            ]);
        }

        return $result;
    }

    private function checkoutPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'source_channel' => 'dev_storefront',
            'currency_code' => 'USD',
            'customer' => [
                'full_name' => 'Routing Buyer',
                'email' => 'routing.buyer@example.test',
                'phone' => '+15550188',
            ],
            'shipping_address' => [
                'name' => 'Routing Buyer',
                'address_line1' => '123 Routing Way',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postal_code' => '94105',
                'country' => 'US',
                'phone' => '+15550188',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 2],
            ],
        ], $overrides);
    }

    private function externalPayload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace_recursive([
            'external_order_number' => 'WEB-'.Str::upper(Str::random(8)),
            'payment_status' => 'paid',
            'payment_gateway' => 'external_test',
            'payment_method' => 'card',
            'payment_reference' => 'pay-'.Str::random(8),
            'currency_code' => 'USD',
            'shipping_total' => 0,
            'customer' => [
                'full_name' => 'External Buyer',
                'email' => 'external.routing@example.test',
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
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '12.00'],
            ],
        ], $overrides);
    }
}
