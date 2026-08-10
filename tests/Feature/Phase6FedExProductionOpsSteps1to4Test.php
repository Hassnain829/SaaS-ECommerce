<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\CarrierRateQuote;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExOperationGuard;
use App\Services\Shipping\DeliveryOptionService;
use App\Support\OrderLifecycle;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase6FedExProductionOpsSteps1to4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        Cache::flush();
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.integrator_production_enabled' => false,
            'carriers.fedex.ops_address_validation_enabled' => true,
            'carriers.fedex.ops_service_availability_enabled' => true,
            'carriers.fedex.ops_negotiated_rates_enabled' => true,
            'carriers.fedex.checkout_rates_enabled' => false,
            'carriers.fedex.sandbox.client_id' => 'parent-client-id-1234567890',
            'carriers.fedex.sandbox.client_secret' => 'parent-client-secret',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.comprehensive_rate_quote_path' => '/rate/v1/comprehensiverates/quotes',
        ]);
    }

    public function test_operation_guard_blocks_cross_store(): void
    {
        [$owner, $store, $account] = $this->modelAFixture('Ops Guard Store');
        $other = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Other Store',
            'slug' => 'other-ops-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        $this->expectException(HttpException::class);
        app(FedExOperationGuard::class)->assertAccountForOperation(
            $other,
            $account,
            FedExOperationGuard::CAPABILITY_NEGOTIATED_RATES,
        );
    }

    public function test_address_validation_order_route_uses_child_oauth(): void
    {
        [$owner, $store, $account] = $this->modelAFixture('Address Ops Store');
        $order = $this->orderWithShippingAddress($store);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'child-token-address',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/address/v1/addresses/resolve' => Http::response([
                'transactionId' => 'addr-txn-1',
                'output' => [
                    'resolvedAddresses' => [[
                        'streetLinesToken' => ['123 MAIN ST'],
                        'city' => 'MEMPHIS',
                        'stateOrProvinceCode' => 'TN',
                        'postalCode' => '38116',
                        'countryCode' => 'US',
                        'classification' => 'BUSINESS',
                        'attributes' => ['residential' => false],
                    ]],
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.fedex.validate-address', $order), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('fedex_address_review');

        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_negotiated_rates_persist_quotes_without_platform_fallback(): void
    {
        [$owner, $store, $account, $location] = $this->modelAFixture('Rates Ops Store');
        $order = $this->orderWithShippingAddress($store);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'child-token-rates',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/rate/v1/comprehensiverates/quotes' => Http::response([
                'transactionId' => 'rate-txn-1',
                'output' => [
                    'rateReplyDetails' => [[
                        'serviceType' => 'FEDEX_GROUND',
                        'serviceName' => 'FedEx Ground',
                        'commit' => ['transitDays' => 3],
                        'ratedShipmentDetails' => [
                            [
                                'rateType' => 'ACCOUNT',
                                'totalNetCharge' => 12.34,
                                'shipmentRateDetail' => [
                                    'currency' => 'USD',
                                    'surCharges' => [[
                                        'type' => 'FUEL',
                                        'description' => 'Fuel',
                                        'amount' => 1.10,
                                    ]],
                                ],
                            ],
                            [
                                'rateType' => 'LIST',
                                'totalNetCharge' => 18.90,
                                'shipmentRateDetail' => ['currency' => 'USD'],
                            ],
                        ],
                    ]],
                ],
            ], 200),
        ]);

        $item = $order->items()->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.fedex.rates', $order), [
                'carrier_account_id' => $account->id,
                'origin_location_id' => $location->id,
                'package_source' => 'custom',
                'weight' => 2,
                'length' => 10,
                'width' => 8,
                'height' => 4,
                'weight_unit' => 'LB',
                'dimension_unit' => 'IN',
                'items' => [[
                    'selected' => '1',
                    'order_item_id' => $item->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHas('fedex_rate_quotes');

        $this->assertDatabaseHas('carrier_rate_quotes', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'service_code' => 'FEDEX_GROUND',
            'status' => CarrierRateQuote::STATUS_SUCCEEDED,
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/rate/v1/comprehensiverates/quotes')) {
                return true;
            }

            $body = $request->data();

            return ($body['accountNumber']['value'] ?? null) === '700257037'
                && in_array('ACCOUNT', $body['requestedShipment']['rateRequestType'] ?? [], true)
                && in_array('LIST', $body['requestedShipment']['rateRequestType'] ?? [], true)
                && ($body['rateRequestControlParameters']['returnTransitTimes'] ?? false) === true;
        });
    }

    public function test_checkout_rates_stay_on_flat_placeholder_when_flag_off(): void
    {
        [$owner, $store, $account] = $this->modelAFixture('Checkout Flat Store');
        $account->forceFill(['enabled_for_checkout' => true])->save();

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'US',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground-'.Str::random(4),
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'flat_rate' => 9.99,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 1,
        ]);

        Http::fake();
        $options = app(DeliveryOptionService::class)->optionsFor(
            $store,
            ['country_code' => 'US', 'postal_code' => '38116', 'state' => 'TN', 'city' => 'Memphis'],
            '50.00',
            'USD',
        );

        $this->assertNotEmpty($options);
        $this->assertSame('9.99', $options[0]['amount']);
        Http::assertNothingSent();
    }

    public function test_checkout_live_rates_use_merchant_account_without_platform_fallback(): void
    {
        [$owner, $store, $account, $location] = $this->modelAFixture('Checkout Live Store');
        $account->forceFill([
            'enabled_for_checkout' => true,
            'capabilities' => array_merge((array) $account->capabilities, ['checkout_rates' => true]),
        ])->save();
        config(['carriers.fedex.checkout_rates_enabled' => true]);

        $preset = \App\Models\ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Default box',
            'length' => 10,
            'width' => 8,
            'height' => 4,
            'dimension_unit' => 'IN',
            'weight_value' => 1,
            'weight_unit' => 'LB',
            'is_default' => true,
            'is_active' => true,
        ]);
        app(\App\Services\Delivery\StoreShippingPreferences::class)->update($store, [
            'default_package_preset_id' => $preset->id,
            'weight_unit' => 'LB',
        ]);
        $store->refresh();

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'US',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground-'.Str::random(4),
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'flat_rate' => 9.99,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 1,
        ]);

        $checkout = \App\Models\Checkout::query()->create([
            'store_id' => $store->id,
            'checkout_number' => 'CHK-'.Str::upper(Str::random(8)),
            'source_channel' => 'web',
            'mode' => 'hosted',
            'status' => \App\Models\Checkout::STATUS_PAYMENT_PENDING,
            'currency_code' => 'USD',
            'subtotal' => '50.00',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '50.00',
        ]);

        $product = \App\Models\Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Checkout Rate Product',
            'slug' => 'checkout-rate-'.Str::random(6),
            'base_price' => 50,
            'sku' => 'CRP-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => ['weight' => 2, 'length' => 10, 'width' => 8, 'height' => 4],
        ]);
        $variant = \App\Models\ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 50,
            'stock' => 10,
            'meta' => ['weight' => 2, 'length' => 10, 'width' => 8, 'height' => 4],
        ]);
        $sync = app(\App\Services\Inventory\InventorySyncService::class);
        $inventoryItem = $sync->ensureInventoryItemForVariant($variant);
        $sync->ensureDefaultLevelForVariant($variant->fresh(), 10);
        \App\Models\InventoryLevel::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->update([
                'location_id' => $location->id,
                'available' => 10,
                'reserved' => 0,
                'committed' => 0,
                'incoming' => 0,
            ]);

        \App\Models\CheckoutItem::query()->create([
            'checkout_id' => $checkout->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Checkout Rate Product',
            'quantity' => 1,
            'unit_price' => '50.00',
            'subtotal' => '50.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total' => '50.00',
            'metadata' => ['weight' => 2, 'length' => 10, 'width' => 8, 'height' => 4],
        ]);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'child-token-checkout',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/rate/v1/comprehensiverates/quotes' => function ($request) use ($location) {
                $body = $request->data();
                $this->assertSame('2', (string) data_get($body, 'requestedShipment.requestedPackageLineItems.0.weight.value'));
                $this->assertSame(
                    (string) $location->postal_code,
                    (string) data_get($body, 'requestedShipment.shipper.address.postalCode'),
                );

                return Http::response([
                    'transactionId' => 'checkout-rate-1',
                    'output' => [
                        'rateReplyDetails' => [[
                            'serviceType' => 'FEDEX_GROUND',
                            'serviceName' => 'FedEx Ground',
                            'ratedShipmentDetails' => [[
                                'rateType' => 'ACCOUNT',
                                'totalNetCharge' => 15.55,
                                'shipmentRateDetail' => ['currency' => 'USD'],
                            ]],
                        ]],
                    ],
                ], 200);
            },
        ]);

        $liveOptions = app(DeliveryOptionService::class)->optionsFor(
            $store,
            ['country_code' => 'US', 'postal_code' => '38116', 'state' => 'TN', 'city' => 'Memphis', 'phone' => '+19015550999'],
            '50.00',
            'USD',
            $checkout->fresh(['items', 'store']),
        );

        $this->assertNotEmpty($liveOptions);
        $this->assertSame('15.55', $liveOptions[0]['amount']);
        $this->assertSame('fedex_negotiated_rates', $liveOptions[0]['snapshot']['source']);
        $this->assertFalse($liveOptions[0]['snapshot']['platform_fallback_used']);
        $this->assertSame($location->id, $liveOptions[0]['fulfillment_origin_location_id']);
    }

    public function test_manage_page_shows_honest_ops_capability_labels(): void
    {
        [$owner, $store, $account] = $this->modelAFixture('Manage Caps Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.manage', $account))
            ->assertOk()
            ->assertSeeText('FedEx Center')
            ->assertSeeText('Checkout rates')
            ->assertSeeText('Shipping & labels')
            ->assertSeeText('Tracking')
            ->assertSeeText('Not ready');
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount, 3: Location}
     */
    private function modelAFixture(string $name): array
    {
        [$owner, $store] = $this->ownerStore($name);
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '90 FedEx Pkwy',
            'city' => 'Collierville',
            'state' => 'TN',
            'postal_code' => '38017',
            'country_code' => 'US',
            'phone' => '+19015550100',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'Model A FedEx',
            'provider_account_number' => '700257037',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
            'fedex_active_store_key' => CarrierAccount::fedExActiveStoreKeyFor(
                (int) $store->id,
                CarrierAccount::ENVIRONMENT_SANDBOX,
            ),
            'enabled_for_checkout' => false,
            'settings' => ['default_origin_location_id' => $location->id],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $account->setCredentials([
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
        ]);
        $account->setFedExAccountNumber('700257037');
        $account->save();

        return [$owner, $store, $account->fresh(), $location];
    }

    private function orderWithShippingAddress(Store $store): Order
    {
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'currency_code' => 'USD',
            'subtotal' => 20,
            'shipping' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 20,
            'grand_total' => 20,
        ]);

        OrderAddress::query()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'name' => 'Test Buyer',
            'address_line1' => '123 Main St',
            'city' => 'Memphis',
            'state' => 'TN',
            'province_code' => 'TN',
            'postal_code' => '38116',
            'country_code' => 'US',
            'country' => 'United States',
            'phone' => '+19015550999',
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Ops Rate Item',
            'slug' => 'ops-rate-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'OPS-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Ops Rate Item',
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 20,
        ]);

        return $order->fresh(['addresses', 'items']);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
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
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        return [$owner, $store];
    }
}
