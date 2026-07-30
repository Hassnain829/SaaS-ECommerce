<?php

namespace Tests\Feature;

use App\Data\Payments\PaymentIntentResult;
use App\Data\Payments\PaymentIntentUpdateResult;
use App\Data\Payments\PaymentWebhookResult;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\InventoryLevel;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\InventorySyncService;
use App\Services\Payments\StripePlatformPaymentProvider;
use App\Support\CheckoutMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnterpriseQaOriginRoutingHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.default_provider' => 'stripe',
            'payments.stripe.mode' => 'test',
            'payments.stripe.key' => 'pk_test_qa_routing',
            'payments.stripe.secret' => 'sk_test_qa_routing',
            'payments.stripe.webhook_secret' => 'whsec_qa_routing',
        ]);

        $this->app->instance(StripePlatformPaymentProvider::class, new class(app(\App\Services\Payments\StripeConfig::class)) extends StripePlatformPaymentProvider
        {
            public function createPaymentIntent(Checkout $checkout, array $options = []): PaymentIntentResult
            {
                return new PaymentIntentResult(
                    provider: 'stripe',
                    providerIntentId: 'pi_qa_routing_'.$checkout->id.'_'.Str::random(6),
                    clientSecret: 'pi_qa_routing_'.$checkout->id.'_secret',
                    status: 'requires_payment_method',
                    amount: (string) $checkout->grand_total,
                    currencyCode: $checkout->currency_code,
                    raw: ['id' => 'pi_qa_routing_'.$checkout->id, 'status' => 'requires_payment_method'],
                );
            }

            public function retrievePaymentIntent(string $providerIntentId, ?string $mode = null): PaymentWebhookResult
            {
                return new PaymentWebhookResult(
                    eventType: 'payment_intent.succeeded',
                    providerIntentId: $providerIntentId,
                    status: 'succeeded',
                    amount: '24.00',
                    currencyCode: 'USD',
                    raw: ['id' => $providerIntentId, 'status' => 'succeeded'],
                );
            }

            public function cancelPaymentIntent(string $providerIntentId, array $options = []): PaymentWebhookResult
            {
                return new PaymentWebhookResult(
                    eventType: 'payment_intent.canceled',
                    providerIntentId: $providerIntentId,
                    status: 'canceled',
                    amount: null,
                    currencyCode: null,
                    raw: ['id' => $providerIntentId, 'status' => 'canceled'],
                );
            }

            public function updatePaymentIntentAmount(
                string $providerIntentId,
                int $amountMinor,
                string $currencyCode,
                array $options = [],
            ): PaymentIntentUpdateResult {
                return new PaymentIntentUpdateResult(
                    providerIntentId: $providerIntentId,
                    amountMinor: $amountMinor,
                    currencyCode: strtoupper($currencyCode),
                    status: 'requires_payment_method',
                    clientSecret: $providerIntentId.'_secret',
                    raw: [
                        'id' => $providerIntentId,
                        'status' => 'requires_payment_method',
                        'amount' => $amountMinor,
                        'currency' => strtolower($currencyCode),
                        'client_secret' => $providerIntentId.'_secret',
                    ],
                );
            }
        });
    }

    public function test_pickup_location_from_another_store_is_rejected(): void
    {
        [$storeA, $tokenA] = $this->tokenedStore('QA Routing Store A');
        [$storeB] = $this->tokenedStore('QA Routing Store B');
        [, $variantA] = $this->product($storeA, ['stock' => 0]);
        [, $variantB] = $this->product($storeB, ['stock' => 5]);

        $defaultA = $storeA->defaultLocation()->firstOrFail();
        $pickupB = $this->location($storeB, 'Store B pickup counter', [
            'type' => Location::TYPE_STORE,
            'pickup_enabled' => true,
        ]);
        $methodsA = $this->shippingSetup($storeA, includePickup: true);

        $this->stockAtLocations($variantA, [$defaultA->id => 5]);
        $this->stockAtLocations($variantB, [$pickupB->id => 5]);

        $this->withToken($tokenA)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variantA))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $storeA->id)->firstOrFail();
        $originBefore = (int) $checkout->fulfillment_origin_location_id;

        $this->withToken($tokenA)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methodsA['pickup']->id,
                'pickup_location_id' => $pickupB->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_location_id']);

        $checkout->refresh();
        $this->assertSame($originBefore, (int) $checkout->fulfillment_origin_location_id);
        $this->assertNull($checkout->pickup_location_id);
        $this->assertDatabaseMissing('inventory_reservations', [
            'store_id' => $storeB->id,
            'location_id' => $pickupB->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $storeA->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $originBefore,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
    }

    public function test_pickup_location_without_stock_is_rejected(): void
    {
        [$store, $token] = $this->tokenedStore('QA Pickup No Stock Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();
        $pickupNoStock = $this->location($store, 'Empty pickup counter', [
            'type' => Location::TYPE_STORE,
            'pickup_enabled' => true,
            'routing_priority' => 1,
        ]);
        $methods = $this->shippingSetup($store, includePickup: true);

        $this->stockAtLocations($variant, [
            $default->id => 5,
            $pickupNoStock->id => 0,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $originBefore = (int) $checkout->fulfillment_origin_location_id;

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['pickup']->id,
                'pickup_location_id' => $pickupNoStock->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_location_id']);

        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $originBefore,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('inventory_reservations', [
            'store_id' => $store->id,
            'location_id' => $pickupNoStock->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
    }

    public function test_multiple_eligible_pickup_locations_require_explicit_selection(): void
    {
        [$store, $token] = $this->tokenedStore('QA Multi Pickup Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();
        $pickupOne = $this->location($store, 'Pickup one', ['pickup_enabled' => true, 'routing_priority' => 1]);
        $pickupTwo = $this->location($store, 'Pickup two', ['pickup_enabled' => true, 'routing_priority' => 2]);
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
        $originBefore = (int) $checkout->fulfillment_origin_location_id;

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['pickup']->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_location_id']);

        $checkout->refresh();
        $this->assertSame($originBefore, (int) $checkout->fulfillment_origin_location_id);
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $originBefore,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
    }

    public function test_failed_reroute_preserves_original_reservation(): void
    {
        [$store, $token] = $this->tokenedStore('QA Failed Reroute Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $originA = $store->defaultLocation()->firstOrFail();
        $originB = $this->location($store, 'Pickup B no stock', [
            'type' => Location::TYPE_STORE,
            'pickup_enabled' => true,
            'routing_priority' => 1,
        ]);
        $methods = $this->shippingSetup($store, includePickup: true);

        $this->stockAtLocations($variant, [
            $originA->id => 5,
            $originB->id => 0,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $snapshotBefore = $checkout->fulfillment_routing_snapshot;

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['pickup']->id,
                'pickup_location_id' => $originB->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_location_id']);

        $checkout->refresh();
        $this->assertSame($originA->id, (int) $checkout->fulfillment_origin_location_id);
        $this->assertSame($snapshotBefore, $checkout->fulfillment_routing_snapshot);
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $originA->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('inventory_reservations', [
            'store_id' => $store->id,
            'location_id' => $originB->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
    }

    public function test_successful_reroute_moves_reservation_and_updates_snapshot(): void
    {
        [$store, $token] = $this->tokenedStore('QA Successful Reroute Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $originA = $store->defaultLocation()->firstOrFail();
        $originB = $this->location($store, 'Pickup B with stock', [
            'type' => Location::TYPE_STORE,
            'pickup_enabled' => true,
            'fulfills_online_orders' => false,
            'routing_priority' => 200,
        ]);
        $methods = $this->shippingSetup($store, includePickup: true);

        $this->stockAtLocations($variant, [
            $originA->id => 5,
            $originB->id => 5,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertCreated();

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame($originA->id, (int) $checkout->fulfillment_origin_location_id);
        $originAId = $originA->id;

        $this->withToken($token)
            ->postJson('/api/v1/checkout/'.$checkout->id.'/shipping-method', [
                'shipping_method_id' => $methods['pickup']->id,
                'pickup_location_id' => $originB->id,
            ])
            ->assertOk()
            ->assertJsonPath('checkout.pickup_location_id', $originB->id)
            ->assertJsonPath('checkout.fulfillment_origin.location_id', $originB->id);

        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $originB->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'reference_type' => 'checkout',
            'reference_id' => (string) $checkout->id,
            'location_id' => $originAId,
            'status' => InventoryReservation::STATUS_RELEASED,
        ]);
        $this->assertDatabaseHas('checkout_events', [
            'checkout_id' => $checkout->id,
            'event_type' => 'fulfillment.origin_selected',
        ]);
    }

    public function test_service_area_specificity_wins_before_routing_priority(): void
    {
        [$store, $token] = $this->tokenedStore('QA Service Area Specificity Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();

        $countryOnly = $this->location($store, 'Country-wide warehouse', [
            'service_countries' => ['US'],
            'routing_priority' => 1,
            'is_default' => false,
        ]);
        $postalMatch = $this->location($store, 'Chicago postal room', [
            'service_countries' => ['US'],
            'service_regions' => ['IL'],
            'service_postal_patterns' => ['606*'],
            'routing_priority' => 50,
        ]);

        $this->stockAtLocations($variant, [
            $default->id => 0,
            $countryOnly->id => 5,
            $postalMatch->id => 5,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'shipping_address' => [
                    'name' => 'Chicago Buyer',
                    'address_line1' => '200 Michigan Ave',
                    'city' => 'Chicago',
                    'state' => 'IL',
                    'postal_code' => '60611',
                    'country' => 'US',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.fulfillment_origin.location_id', $postalMatch->id);

        $checkout = Checkout::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame($postalMatch->id, (int) $checkout->fulfillment_origin_location_id);
        $this->assertSame('service_area_stock_priority', data_get($checkout->fulfillment_routing_snapshot, 'routing_basis'));
    }

    public function test_stock_availability_beats_service_area_preference(): void
    {
        [$store, $token] = $this->tokenedStore('QA Stock Beats Match Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();

        $strongMatchNoStock = $this->location($store, 'Strong match empty', [
            'service_countries' => ['US'],
            'service_regions' => ['TX'],
            'service_postal_patterns' => ['787*'],
            'routing_priority' => 1,
        ]);
        $weakerMatchWithStock = $this->location($store, 'Weaker match stocked', [
            'service_countries' => ['US'],
            'service_regions' => ['TX'],
            'routing_priority' => 20,
        ]);

        $this->stockAtLocations($variant, [
            $default->id => 0,
            $strongMatchNoStock->id => 0,
            $weakerMatchWithStock->id => 5,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant, [
                'shipping_address' => [
                    'name' => 'Austin Buyer',
                    'address_line1' => '500 Congress Ave',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'postal_code' => '78701',
                    'country' => 'US',
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('checkout.fulfillment_origin.location_id', $weakerMatchWithStock->id);

        $this->assertDatabaseHas('inventory_reservations', [
            'store_id' => $store->id,
            'location_id' => $weakerMatchWithStock->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('inventory_reservations', [
            'store_id' => $store->id,
            'location_id' => $strongMatchNoStock->id,
            'status' => InventoryReservation::STATUS_ACTIVE,
        ]);
    }

    public function test_no_eligible_origin_returns_clean_validation_error(): void
    {
        [$store, $token] = $this->tokenedStore('QA No Origin Store');
        [, $variant] = $this->product($store, ['stock' => 0]);
        $default = $store->defaultLocation()->firstOrFail();
        $this->shippingSetup($store);

        $this->stockAtLocations($variant, [$default->id => 0]);

        $this->withToken($token)
            ->postJson('/api/v1/checkout', $this->checkoutPayload($variant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(0, Checkout::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, InventoryReservation::query()->where('store_id', $store->id)->count());
        $this->assertSame(0, PaymentIntent::query()->where('store_id', $store->id)->count());
    }

    public function test_routing_ui_and_docs_do_not_claim_physical_nearest(): void
    {
        $forbiddenInMerchantUi = [
            'true nearest',
            'gps nearest',
            'geocoded nearest',
            'miles away',
            'kilometers away',
        ];

        $orderViewPath = base_path('resources/views/user_view/orderViewDetails.blade.php');
        $this->assertFileExists($orderViewPath);
        $orderView = strtolower(file_get_contents($orderViewPath) ?: '');

        foreach ($forbiddenInMerchantUi as $phrase) {
            $this->assertStringNotContainsString(
                strtolower($phrase),
                $orderView,
                "Misleading routing claim [{$phrase}] found in merchant order view"
            );
        }

        $this->assertStringContainsString('service area routing', $orderView);

        $phaseReport = base_path('docs/phases/PHASE_6C_0A_NEAREST_ELIGIBLE_ORIGIN_ROUTING_REPORT.md');
        $this->assertFileExists($phaseReport);
        $reportContents = strtolower(file_get_contents($phaseReport) ?: '');
        $this->assertStringContainsString('nearest eligible', $reportContents);
        $this->assertStringContainsString('service area', $reportContents);

        $roadmap = base_path('ENTERPRISE_ROADMAP_2026.md');
        $this->assertFileExists($roadmap);
        $roadmapContents = file_get_contents($roadmap) ?: '';
        $this->assertStringContainsString('6C-0B', $roadmapContents);
        $this->assertTrue(
            str_contains(strtolower($roadmapContents), 'geocod')
            || str_contains(strtolower($roadmapContents), 'lat/lng')
            || str_contains(strtolower($roadmapContents), 'physical nearest'),
            'Roadmap should defer true physical nearest to Phase 6C-0B'
        );
    }

    /**
     * @return array{0: Store, 1: string}
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
            'settings' => ['checkout_mode' => CheckoutMode::PLATFORM],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $token = 'baa_dev_test_'.Str::random(32);
        $store->forceFill([
            'developer_storefront_token_hash' => hash('sha256', $token),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        return [$store, $token];
    }

    private function product(Store $store, array $overrides = []): array
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $overrides['name'] ?? 'QA Routing Product',
            'slug' => 'qa-routing-'.Str::random(6),
            'base_price' => $overrides['price'] ?? 12,
            'sku' => $overrides['sku'] ?? 'QAR-'.Str::random(4),
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
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
                'phone' => '+15550188',
            ],
            'billing_address' => ['same_as_shipping' => true],
            'items' => [
                ['variant_id' => $variant->id, 'quantity' => 2],
            ],
        ], $overrides);
    }
}
