<?php

namespace Tests\Feature;

use App\Jobs\Carriers\FedEx\RefreshFedExShipmentTrackingJob;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierRateQuote;
use App\Models\IdempotencyKey;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExOrderTrackingSyncService;
use App\Services\Carriers\FedEx\Operations\FedExProductionShipRequestBuilder;
use App\Services\Carriers\FedEx\Operations\FedExShipmentCancelService;
use App\Services\Carriers\FedEx\Operations\FedExShipmentPurchaseService;
use App\Support\OrderLifecycle;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase6FedExProductionOpsSteps5to7Test extends TestCase
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
            'carriers.fedex.ops_ship_labels_enabled' => true,
            'carriers.fedex.ops_tracking_enabled' => true,
            'carriers.fedex.basic_integrated_visibility_path' => '/track/v1/trackingnumbers',
            'carriers.fedex.sandbox.client_id' => 'parent-client-id-1234567890',
            'carriers.fedex.sandbox.client_secret' => 'parent-client-secret',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.ship_create_path' => '/ship/v1/shipments',
            'carriers.fedex.ship_cancel_path' => '/ship/v1/shipments/cancel',
        ]);
    }

    public function test_ship_purchase_writes_processing_before_network_and_persists_items(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Ship Persist Store');

        Http::fake(function ($request) use ($store) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'ship-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/ship/v1/shipments')) {
                $processing = IdempotencyKey::query()
                    ->where('store_id', $store->id)
                    ->where('response_code', 102)
                    ->where('response_body->state', FedExShipmentPurchaseService::STATE_PROCESSING)
                    ->exists();
                $this->assertTrue($processing, 'Processing idempotency must exist before FedEx ship call');

                return Http::response([
                    'transactionId' => 'ship-txn-1',
                    'output' => [
                        'transactionShipments' => [[
                            'masterTrackingNumber' => '794612345678',
                            'serviceType' => 'FEDEX_GROUND',
                            'pieceResponses' => [[
                                'packageSequenceNumber' => 1,
                                'trackingNumber' => '794612345678',
                                'packageDocuments' => [[
                                    'contentType' => 'LABEL',
                                    'docType' => 'LABEL',
                                    'imageType' => 'PDF',
                                    'encodedLabel' => base64_encode('%PDF-1.4 fedex-label'),
                                ]],
                            ]],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $packages = [['weight' => 2, 'length' => 10, 'width' => 8, 'height' => 4, 'weight_unit' => 'LB', 'dimension_unit' => 'IN']];
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages);

        $first = app(FedExShipmentPurchaseService::class)->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $location,
            input: [
                'carrier_rate_quote_id' => $quote->id,
                'service_type' => 'FEDEX_GROUND',
                'label_format' => 'PDF',
                'packages' => $packages,
            ],
        );

        $this->assertSame(FedExShipmentPurchaseService::STATE_SUCCEEDED, $first['state']);
        $this->assertNotNull($first['shipment']);
        $this->assertDatabaseHas('shipment_items', [
            'shipment_id' => $first['shipment']->id,
            'store_id' => $store->id,
        ]);
        $this->assertGreaterThan(0, ShipmentItem::query()->where('shipment_id', $first['shipment']->id)->count());
        $this->assertNotEmpty(data_get($first['shipment']->metadata, 'fedex.labels'));

        $second = app(FedExShipmentPurchaseService::class)->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $location,
            input: [
                'carrier_rate_quote_id' => $quote->id,
                'service_type' => 'FEDEX_GROUND',
                'label_format' => 'PDF',
                'packages' => $packages,
            ],
        );
        $this->assertTrue($second['replayed']);
        $this->assertSame($first['shipment']->id, $second['shipment']->id);
    }

    public function test_return_label_swaps_parties_and_sets_print_return_detail(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Return Label Store');
        $recipient = $order->addresses->first();

        $fixture = app(FedExProductionShipRequestBuilder::class)->buildFixture(
            $store,
            $order,
            $location,
            $recipient,
            [
                'return_shipment' => true,
                'service_type' => 'FEDEX_GROUND',
                'packages' => [['weight' => 1, 'length' => 9, 'width' => 6, 'height' => 2]],
            ],
        );

        $this->assertSame('RETURN_SHIPMENT', $fixture['shipment_special_services']['specialServiceTypes'][0]);
        $this->assertSame('PRINT_RETURN_LABEL', $fixture['shipment_special_services']['returnShipmentDetail']['returnType']);
        $this->assertSame($recipient->postal_code, $fixture['shipper']['postal_code']);
        $this->assertSame($location->postal_code, $fixture['recipient']['postal_code']);
    }

    public function test_incomplete_labels_do_not_succeed(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Incomplete Label Store');

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'ship-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/ship/v1/shipments' => Http::response([
                'transactionId' => 'ship-no-label',
                'output' => [
                    'transactionShipments' => [[
                        'masterTrackingNumber' => '794600000001',
                        'serviceType' => 'FEDEX_GROUND',
                        'pieceResponses' => [[
                            'packageSequenceNumber' => 1,
                            'trackingNumber' => '794600000001',
                            'packageDocuments' => [],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $packages = [['weight' => 1, 'length' => 9, 'width' => 6, 'height' => 2, 'weight_unit' => 'LB', 'dimension_unit' => 'IN']];
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages);

        $outcome = app(FedExShipmentPurchaseService::class)->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $location,
            input: [
                'carrier_rate_quote_id' => $quote->id,
                'service_type' => 'FEDEX_GROUND',
                'packages' => $packages,
            ],
        );

        $this->assertSame(FedExShipmentPurchaseService::STATE_UNCERTAIN, $outcome['state']);
        $this->assertNotNull($outcome['shipment']);
        $this->assertSame(Shipment::STATUS_PENDING, $outcome['shipment']->status);
        $this->assertSame(1, Shipment::query()->where('store_id', $store->id)->count());
        $this->assertGreaterThan(0, ShipmentItem::query()->where('shipment_id', $outcome['shipment']->id)->count());
    }

    public function test_uncertain_502_blocks_blind_retry(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Ship Uncertain Store');

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'ship-token-502',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/ship/v1/shipments' => Http::response(['message' => 'bad gateway'], 502),
        ]);

        $packages = [['weight' => 1, 'length' => 9, 'width' => 6, 'height' => 2, 'weight_unit' => 'LB', 'dimension_unit' => 'IN']];
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages);
        $purchase = app(FedExShipmentPurchaseService::class);
        $outcome = $purchase->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $location,
            input: [
                'carrier_rate_quote_id' => $quote->id,
                'service_type' => 'FEDEX_GROUND',
                'packages' => $packages,
            ],
        );

        $this->assertSame(FedExShipmentPurchaseService::STATE_UNCERTAIN, $outcome['state']);
        $retry = $purchase->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $location,
            input: [
                'carrier_rate_quote_id' => $quote->id,
                'service_type' => 'FEDEX_GROUND',
                'packages' => $packages,
            ],
        );
        $this->assertTrue($retry['replayed']);
        $this->assertSame(FedExShipmentPurchaseService::STATE_UNCERTAIN, $retry['state']);
    }

    public function test_delivered_shipment_cannot_be_cancelled(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Cancel Block Store');

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-'.Str::upper(Str::random(6)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $account->id,
            'status' => Shipment::STATUS_DELIVERED,
            'tracking_number' => '794698765432',
            'delivered_at' => now()->subDay(),
            'package_count' => 1,
            'metadata' => ['fedex' => ['idempotency_key' => 'existing']],
        ]);

        $this->assertFalse(FedExShipmentCancelService::isCancellable($shipment));

        $this->expectException(HttpException::class);
        app(FedExShipmentCancelService::class)->cancel($store, $account, $shipment);
    }

    public function test_tracking_status_mapping_is_exact(): void
    {
        $sync = app(FedExOrderTrackingSyncService::class);
        $method = new \ReflectionMethod($sync, 'mapFedExStatusToShipment');
        $method->setAccessible(true);

        $this->assertSame(Shipment::STATUS_IN_TRANSIT, $method->invoke($sync, 'Out for delivery', null, null));
        $this->assertSame(Shipment::STATUS_FAILED, $method->invoke($sync, 'Delivery exception', null, null));
        $this->assertSame(Shipment::STATUS_FAILED, $method->invoke($sync, 'Delivery failed', null, null));
        $this->assertSame(Shipment::STATUS_DELIVERED, $method->invoke($sync, 'Delivered', now()->toIso8601String(), null));
        $this->assertSame(Shipment::STATUS_DELIVERED, $method->invoke($sync, 'Delivered', null, null));
    }

    public function test_tracking_prefers_active_account_after_reconnect(): void
    {
        [$store, $oldAccount, $location, $order] = $this->readyOrder('Track Active Store');

        $oldAccount->forceFill([
            'replaced_at' => now(),
            'fedex_active_store_key' => null,
            'connection_status' => CarrierAccount::CONNECTION_FAILED,
        ])->save();

        $active = $oldAccount->replicate();
        $active->forceFill([
            'display_name' => 'Active FedEx',
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'disconnected_at' => null,
            'replaced_at' => null,
            'fedex_active_store_key' => CarrierAccount::fedExActiveStoreKeyFor(
                (int) $store->id,
                CarrierAccount::ENVIRONMENT_SANDBOX,
            ),
            'capabilities' => array_merge((array) $oldAccount->capabilities, [
                'labels' => true,
                'tracking' => true,
            ]),
        ]);
        $active->save();
        $active->setCredentials([
            'customer_key' => 'child-key-active',
            'customer_password' => 'child-secret-active',
        ]);
        $active->setFedExAccountNumber('700257038');
        $active->save();

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-'.Str::upper(Str::random(6)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $oldAccount->id,
            'status' => Shipment::STATUS_IN_TRANSIT,
            'tracking_number' => '794611112222',
            'package_count' => 1,
            'metadata' => ['fedex' => []],
        ]);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'track-token-active',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/track/v1/trackingnumbers' => Http::response([
                'output' => [
                    'completeTrackResults' => [[
                        'trackResults' => [[
                            'latestStatusDetail' => ['description' => 'In transit', 'code' => 'IT'],
                            'scanEvents' => [],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $outcome = app(FedExOrderTrackingSyncService::class)->refreshShipment($store, $shipment->fresh());
        $this->assertTrue($outcome['synced']);
        $this->assertSame($active->id, data_get($shipment->fresh()->metadata, 'fedex.tracking.tracked_with_account_id'));
    }

    public function test_tracking_job_rethrows_transport_failures_for_retry(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Track Job Store');
        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-'.Str::upper(Str::random(6)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $account->id,
            'status' => Shipment::STATUS_IN_TRANSIT,
            'tracking_number' => '794633344455',
            'package_count' => 1,
        ]);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'track-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/track/v1/trackingnumbers' => Http::response(['message' => 'bad gateway'], 502),
        ]);

        $this->expectException(\RuntimeException::class);
        (new RefreshFedExShipmentTrackingJob((int) $shipment->id))->handle(app(FedExOrderTrackingSyncService::class));
    }

    public function test_account_level_ship_capability_is_required(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Cap Store');
        $account->forceFill([
            'capabilities' => array_merge((array) $account->capabilities, [
                'labels' => false,
                'tracking' => false,
            ]),
        ])->save();

        $this->expectException(HttpException::class);
        app(FedExShipmentPurchaseService::class)->purchase(
            store: $store,
            order: $order,
            account: $account->fresh(),
            origin: $location,
            input: [
                'service_type' => 'FEDEX_GROUND',
                'packages' => [['weight' => 1, 'length' => 9, 'width' => 6, 'height' => 2]],
            ],
        );
    }

    public function test_public_tracking_page_requires_token(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Public Track Store');
        $token = bin2hex(random_bytes(8));

        Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-'.Str::upper(Str::random(6)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $account->id,
            'status' => Shipment::STATUS_IN_TRANSIT,
            'tracking_number' => '794611112222',
            'package_count' => 1,
            'metadata' => [
                'fedex' => [
                    'public_tracking_token' => $token,
                    'tracking' => [
                        'status_text' => 'In transit',
                        'timeline' => [[
                            'description' => 'Picked up',
                            'occurred_at' => now()->toIso8601String(),
                        ]],
                    ],
                ],
            ],
        ]);

        $this->get(route('public.fedex.tracking', ['storeSlug' => $store->slug, 'token' => $token]))
            ->assertOk()
            ->assertSeeText('Shipment tracking')
            ->assertSeeText('In transit');

        $this->get(route('public.fedex.tracking', ['storeSlug' => $store->slug, 'token' => 'bad-token']))
            ->assertNotFound();
    }

    /**
     * @return array{0: Store, 1: CarrierAccount, 2: Location, 3: Order}
     */
    private function readyOrder(string $name): array
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

        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '90 FedEx Pkwy',
            'city' => 'Collierville',
            'state' => 'TN',
            'postal_code' => '38017',
            'country_code' => 'US',
            'phone' => '9015550100',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create(array_merge(
            CarrierAccount::ownershipAttributesForFedExIntegratorProvider(),
            [
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
                'capabilities' => array_merge(
                    CarrierAccount::ownershipAttributesForFedExIntegratorProvider()['capabilities'],
                    ['labels' => true, 'tracking' => true, 'rates' => true],
                ),
            ],
        ));
        $account->setCredentials([
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
        ]);
        $account->setFedExAccountNumber('700257037');
        $account->save();

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
            'phone' => '9015550199',
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Test Item',
            'slug' => 'test-item-'.Str::random(6),
            'base_price' => 10,
            'sku' => 'TI-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Test Item',
            'quantity' => 2,
            'unit_price' => 10,
            'subtotal' => 20,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 20,
        ]);

        return [$store, $account->fresh(), $location, $order->fresh(['addresses', 'items'])];
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     */
    private function makeAccountQuote(
        Store $store,
        CarrierAccount $account,
        Order $order,
        Location $location,
        array $packages,
        string $service = 'FEDEX_GROUND',
    ): CarrierRateQuote {
        $normalized = array_values(array_map(static function (array $package): array {
            return [
                'weight' => $package['weight'] ?? 1,
                'weight_unit' => $package['weight_unit'] ?? 'LB',
                'length' => $package['length'] ?? null,
                'width' => $package['width'] ?? null,
                'height' => $package['height'] ?? null,
                'dimension_unit' => $package['dimension_unit'] ?? 'IN',
            ];
        }, $packages));

        return CarrierRateQuote::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'order_id' => $order->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => $account->environment,
            'origin_postal_code' => $location->postal_code,
            'destination_postal_code' => '38116',
            'service_code' => $service,
            'service_name' => 'FedEx Ground',
            'amount' => '12.34',
            'currency' => 'USD',
            'status' => CarrierRateQuote::STATUS_SUCCEEDED,
            'request_summary' => [
                'origin_location_id' => $location->id,
                'origin_country' => strtoupper((string) ($location->country_code ?: 'US')),
                'destination_country' => 'US',
                'destination_residential' => false,
                'ship_date' => now()->toDateString(),
                'packages' => $normalized,
            ],
            'response_summary' => [
                'selected_rate_type' => 'ACCOUNT',
                'rate_type' => 'ACCOUNT',
            ],
        ]);
    }
}
