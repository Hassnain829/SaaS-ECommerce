<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierRateQuote;
use App\Models\FedExTradeDocument;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExOperationGuard;
use App\Services\Carriers\FedEx\Operations\FedExOrderTrackingSyncService;
use App\Services\Carriers\FedEx\Operations\FedExShipmentPurchaseService;
use App\Services\Fulfillment\FulfillmentStatusService;
use App\Support\OrderLifecycle;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase6FedExProductionIntegritySteps13Test extends TestCase
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
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.ops_ship_labels_enabled' => true,
            'carriers.fedex.ops_tracking_enabled' => true,
            'carriers.fedex.ops_rate_quote_ttl_seconds' => 1800,
            'carriers.fedex.label_storage_disk' => 'local',
            'carriers.fedex.sandbox.client_id' => 'parent-client-id-1234567890',
            'carriers.fedex.sandbox.client_secret' => 'parent-client-secret',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.ship_create_path' => '/ship/v1/shipments',
            'carriers.fedex.basic_integrated_visibility_path' => '/track/v1/trackingnumbers',
        ]);
    }

    public function test_production_app_does_not_resolve_sandbox_operational_account(): void
    {
        [$store, $account] = $this->readyOrder('Prod Sandbox Block');
        $this->assertSame(CarrierAccount::ENVIRONMENT_SANDBOX, $account->environment);

        $this->app->detectEnvironment(fn () => 'production');

        $resolved = app(FedExOperationGuard::class)->resolveActiveModelAAccount($store);
        $this->assertNull($resolved);
        $this->assertSame([], app(FedExOperationGuard::class)->operationalEnvironmentCandidates());
    }

    public function test_over_shipment_is_blocked_before_network(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Over Ship Store');
        $item = $order->items->first();
        $packages = $this->defaultPackages();
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages);

        // Reserve all remaining quantity with an open outbound label.
        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-'.Str::upper(Str::random(6)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $account->id,
            'status' => Shipment::STATUS_LABEL_CREATED,
            'direction' => Shipment::DIRECTION_OUTBOUND,
            'package_count' => 1,
        ]);
        ShipmentItem::query()->create([
            'store_id' => $store->id,
            'shipment_id' => $shipment->id,
            'order_item_id' => $item->id,
            'quantity' => (int) $item->quantity,
        ]);

        Http::fake(); // must not be called

        try {
            app(FedExShipmentPurchaseService::class)->purchase(
                store: $store,
                order: $order->fresh('items'),
                account: $account,
                origin: $location,
                input: [
                    'carrier_rate_quote_id' => $quote->id,
                    'service_type' => 'FEDEX_GROUND',
                    'packages' => $packages,
                ],
            );
            $this->fail('Expected ValidationException for over-shipment');
        } catch (ValidationException $e) {
            $this->assertTrue(
                isset($e->errors()['items']) || collect($e->errors())->keys()->contains(fn ($k) => str_starts_with((string) $k, 'items.')),
                'Expected an items.* validation error, got: '.json_encode($e->errors()),
            );
        }

        Http::assertNothingSent();
    }

    public function test_return_shipment_does_not_fulfill_order(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Return Isolation Store');
        $item = $order->items->first();

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-RET-'.Str::upper(Str::random(4)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $account->id,
            'status' => Shipment::STATUS_DELIVERED,
            'direction' => Shipment::DIRECTION_RETURN,
            'tracking_number' => '794699991111',
            'package_count' => 1,
            'delivered_at' => now(),
            'metadata' => ['fedex' => ['return_shipment' => true]],
        ]);
        ShipmentItem::query()->create([
            'store_id' => $store->id,
            'shipment_id' => $shipment->id,
            'order_item_id' => $item->id,
            'quantity' => (int) $item->quantity,
        ]);

        app(FulfillmentStatusService::class)->recalculateAndPersist($order->fresh('items'));
        $this->assertSame(OrderLifecycle::FULFILLMENT_UNFULFILLED, $order->fresh()->fulfillment_status);

        $remaining = app(FulfillmentStatusService::class)->remainingQuantities($order->fresh('items'));
        $this->assertSame((int) $item->quantity, (int) $remaining[$item->id]);
    }

    public function test_partial_delivered_shipment_does_not_fulfill_entire_order(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Partial Deliver Store');
        $item = $order->items->first();
        $this->assertSame(2, (int) $item->quantity);

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-PART-'.Str::upper(Str::random(4)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $account->id,
            'status' => Shipment::STATUS_DELIVERED,
            'direction' => Shipment::DIRECTION_OUTBOUND,
            'tracking_number' => '794688881111',
            'package_count' => 1,
            'delivered_at' => now(),
            'shipped_at' => now()->subDay(),
        ]);
        ShipmentItem::query()->create([
            'store_id' => $store->id,
            'shipment_id' => $shipment->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'track-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/track/v1/trackingnumbers' => Http::response([
                'output' => [
                    'completeTrackResults' => [[
                        'trackResults' => [[
                            'latestStatusDetail' => [
                                'description' => 'Delivered',
                                'statusByLocale' => 'Delivered',
                            ],
                            'dateAndTimes' => [[
                                'type' => 'ACTUAL_DELIVERY',
                                'dateTime' => now()->toIso8601String(),
                            ]],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        app(FedExOrderTrackingSyncService::class)->refreshShipment($store, $shipment->fresh());
        $this->assertSame(OrderLifecycle::FULFILLMENT_PARTIAL, $order->fresh()->fulfillment_status);
    }

    public function test_rate_quote_must_bind_to_label_purchase(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Quote Bind Store');
        $packages = $this->defaultPackages();

        $this->expectException(ValidationException::class);
        app(FedExShipmentPurchaseService::class)->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $location,
            input: [
                'service_type' => 'FEDEX_GROUND',
                'packages' => $packages,
            ],
        );
    }

    public function test_forged_trade_document_is_rejected(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Etd Forge Store');
        $order->addresses->first()->forceFill([
            'country_code' => 'CA',
            'postal_code' => 'M5V2T6',
            'city' => 'Toronto',
            'state' => 'ON',
            'province_code' => 'ON',
        ])->save();

        $other = Store::query()->create([
            'user_id' => $store->user_id,
            'name' => 'Other',
            'slug' => 'other-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        $doc = FedExTradeDocument::query()->create([
            'store_id' => $other->id,
            'order_id' => $order->id,
            'carrier_account_id' => $account->id,
            'document_type' => 'commercial_invoice',
            'status' => FedExTradeDocument::STATUS_UPLOADED,
            'fedex_document_id' => 'DOC-FORGED',
            'origin_country_code' => 'US',
            'destination_country_code' => 'CA',
            'uploaded_at' => now(),
        ]);

        $packages = $this->defaultPackages();
        $quote = $this->makeAccountQuote($store, $account, $order->fresh('addresses'), $location, $packages);

        $this->expectException(ValidationException::class);
        app(FedExShipmentPurchaseService::class)->purchase(
            store: $store,
            order: $order->fresh(['addresses', 'items']),
            account: $account,
            origin: $location,
            input: [
                'carrier_rate_quote_id' => $quote->id,
                'service_type' => 'FEDEX_GROUND',
                'packages' => $packages,
                'fedex_trade_document_id' => $doc->id,
                'customs_clearance' => [
                    'commodities' => [[
                        'description' => 'Widget',
                        'quantity' => 1,
                        'customs_value' => ['amount' => 10, 'currency' => 'USD'],
                        'weight' => 1,
                        'weight_unit' => 'LB',
                        'country_of_manufacture' => 'US',
                    ]],
                    'total_customs_value' => ['amount' => 10, 'currency' => 'USD'],
                    'duties_payment_type' => 'SENDER',
                ],
            ],
        );
    }

    public function test_failed_storage_write_does_not_succeed(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Storage Fail Store');
        $packages = $this->defaultPackages();
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages);

        // Force Storage::put to report failure without mocking the final class.
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('exists')->andReturn(false);
        Storage::shouldReceive('url')->andReturn(null);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'ship-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/ship/v1/shipments' => Http::response([
                'transactionId' => 'ship-txn-storage',
                'output' => [
                    'transactionShipments' => [[
                        'masterTrackingNumber' => '794612345699',
                        'serviceType' => 'FEDEX_GROUND',
                        'pieceResponses' => [[
                            'packageSequenceNumber' => 1,
                            'trackingNumber' => '794612345699',
                            'packageDocuments' => [[
                                'contentType' => 'LABEL',
                                'docType' => 'LABEL',
                                'imageType' => 'PDF',
                                'encodedLabel' => base64_encode('%PDF-1.4 fedex-label'),
                            ]],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

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
        $this->assertTrue((bool) data_get($outcome['shipment']->metadata, 'fedex.label_storage_failed'));
    }

    public function test_production_preflight_requires_live_environment(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.integrator_production_enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.live.client_id' => 'live-client-id-1234567890',
            'carriers.fedex.live.client_secret' => 'live-client-secret',
            'carriers.fedex.live.base_url' => 'https://apis.fedex.com',
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.sandbox_allow_platform_fallback' => false,
            'carriers.fedex.live_allowed_countries' => 'US,CA',
            'carriers.fedex.mfa_pin_generation_path' => '/registration/v2/customerauthentication/pin/generation',
            'carriers.fedex.mfa_pin_validation_path' => '/registration/v2/customerauthentication/pin/validation',
            'carriers.fedex.mfa_invoice_validation_path' => '/registration/v2/customerauthentication/invoice',
        ]);

        $this->artisan('fedex:production-preflight')
            ->expectsOutputToContain('FEDEX_ENVIRONMENT=live')
            ->assertFailed();
    }

    public function test_failed_ship_releases_quantity_reservation(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Reserve Release Store');
        $item = $order->items->first();
        $packages = $this->defaultPackages();
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'ship-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/ship/v1/shipments' => Http::response([
                'errors' => [['code' => 'SHIP.VALIDATION', 'message' => 'Rejected']],
            ], 400),
        ]);

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

        $this->assertSame(FedExShipmentPurchaseService::STATE_FAILED, $outcome['state']);
        $this->assertNull($outcome['shipment']);

        $reserved = Shipment::query()->where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($reserved);
        $this->assertSame(Shipment::STATUS_FAILED, $reserved->status);

        $remaining = app(FulfillmentStatusService::class)->remainingQuantities($order->fresh('items'));
        $this->assertSame((int) $item->quantity, (int) $remaining[$item->id]);
    }

    public function test_uncertain_ship_retains_quantity_reservation(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Reserve Retain Store');
        $item = $order->items->first();
        $packages = $this->defaultPackages();
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'ship-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/ship/v1/shipments' => Http::response('gateway timeout', 504),
        ]);

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

        $remaining = app(FulfillmentStatusService::class)->remainingQuantities($order->fresh('items'));
        $this->assertSame(0, (int) $remaining[$item->id]);
    }

    public function test_return_label_requires_order_return_id(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Return Items Required');

        $this->expectException(ValidationException::class);
        app(\App\Services\Carriers\FedEx\Operations\FedExReturnLabelService::class)->createReturnLabel(
            store: $store,
            order: $order,
            account: $account,
            origin: $location,
            input: [
                'service_type' => 'FEDEX_GROUND',
                'packages' => $this->defaultPackages(),
            ],
        );
    }

    public function test_cancel_rejects_mismatched_carrier_account(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Cancel Account Match');
        $item = $order->items->first();

        $other = CarrierAccount::query()->create(array_merge(
            CarrierAccount::ownershipAttributesForFedExIntegratorProvider(),
            [
                'store_id' => $store->id,
                'carrier_id' => $account->carrier_id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'display_name' => 'Other Model A FedEx',
                'provider_account_number' => '700257038',
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
                'status' => CarrierAccount::STATUS_ENABLED,
                'default_origin_location_id' => $location->id,
                'fedex_active_store_key' => null,
                'enabled_for_checkout' => false,
                'settings' => ['default_origin_location_id' => $location->id],
                'capabilities' => array_merge(
                    CarrierAccount::ownershipAttributesForFedExIntegratorProvider()['capabilities'],
                    ['labels' => true, 'tracking' => true, 'rates' => true],
                ),
            ],
        ));
        $other->setCredentials([
            'customer_key' => 'child-key-b',
            'customer_password' => 'child-secret-b',
        ]);
        $other->setFedExAccountNumber('700257038');
        $other->save();

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-CAN-'.Str::upper(Str::random(4)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $account->id,
            'status' => Shipment::STATUS_LABEL_CREATED,
            'direction' => Shipment::DIRECTION_OUTBOUND,
            'tracking_number' => '794611112222',
            'package_count' => 1,
        ]);
        ShipmentItem::query()->create([
            'store_id' => $store->id,
            'shipment_id' => $shipment->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\Carriers\FedEx\Operations\FedExShipmentCancelService::class)
            ->cancel($store, $other, $shipment);
    }

    public function test_cancel_after_reconnect_uses_replacement_successor(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Cancel After Reconnect');
        $item = $order->items->first();

        // Retire the purchasing account first so the active-store key can move to the successor.
        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_DISABLED,
            'status' => CarrierAccount::STATUS_DISABLED,
            'fedex_active_store_key' => null,
            'credentials_encrypted' => null,
            'provider_account_number_encrypted' => null,
            'provider_account_number' => null,
            'disconnected_at' => now(),
            'capabilities' => array_merge((array) $account->capabilities, [
                'labels' => false,
                'tracking' => false,
                'rates' => false,
            ]),
        ])->save();

        $successor = CarrierAccount::query()->create(array_merge(
            CarrierAccount::ownershipAttributesForFedExIntegratorProvider(),
            [
                'store_id' => $store->id,
                'carrier_id' => $account->carrier_id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'display_name' => 'Reconnected Model A FedEx',
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
        $successor->setCredentials([
            'customer_key' => 'child-key-successor',
            'customer_password' => 'child-secret-successor',
        ]);
        $successor->setFedExAccountNumber('700257037');
        $successor->save();

        $account->forceFill([
            'replaced_at' => now(),
            'replaced_by_carrier_account_id' => $successor->id,
        ])->save();

        $shipment = Shipment::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'shipment_number' => 'SHP-RECON-'.Str::upper(Str::random(4)),
            'origin_location_id' => $location->id,
            'carrier_account_id' => $account->id,
            'status' => Shipment::STATUS_LABEL_CREATED,
            'direction' => Shipment::DIRECTION_OUTBOUND,
            'tracking_number' => '794633334444',
            'package_count' => 1,
        ]);
        ShipmentItem::query()->create([
            'store_id' => $store->id,
            'shipment_id' => $shipment->id,
            'order_item_id' => $item->id,
            'quantity' => 1,
        ]);

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'cancel-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            '*/ship/v1/shipments/cancel' => Http::response([
                'transactionId' => 'cancel-ok',
                'output' => ['cancelledShipment' => true],
            ], 200),
        ]);

        $outcome = app(\App\Services\Carriers\FedEx\Operations\FedExShipmentCancelService::class)
            ->cancel($store, $successor->fresh(), $shipment->fresh());

        $this->assertTrue($outcome['result']->success);
        $this->assertSame(Shipment::STATUS_CANCELLED, $outcome['shipment']->status);
    }

    public function test_quote_binding_rejects_destination_country_mismatch(): void
    {
        [$store, $account, $location, $order] = $this->readyOrder('Quote Country Bind');
        $packages = $this->defaultPackages();
        $quote = $this->makeAccountQuote($store, $account, $order, $location, $packages);
        $summary = $quote->request_summary;
        $summary['destination_country'] = 'CA';
        $quote->forceFill(['request_summary' => $summary])->save();

        $this->expectException(ValidationException::class);
        app(FedExShipmentPurchaseService::class)->purchase(
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

        $product = \App\Models\Product::query()->create([
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
     * @return list<array<string, mixed>>
     */
    private function defaultPackages(): array
    {
        return [[
            'weight' => 1,
            'length' => 9,
            'width' => 6,
            'height' => 2,
            'weight_unit' => 'LB',
            'dimension_unit' => 'IN',
        ]];
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
    ): CarrierRateQuote {
        return CarrierRateQuote::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'order_id' => $order->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => $account->environment,
            'origin_postal_code' => $location->postal_code,
            'destination_postal_code' => $order->addresses->first()->postal_code ?? '38116',
            'service_code' => 'FEDEX_GROUND',
            'service_name' => 'FedEx Ground',
            'amount' => '12.34',
            'currency' => 'USD',
            'status' => CarrierRateQuote::STATUS_SUCCEEDED,
            'request_summary' => [
                'origin_location_id' => $location->id,
                'origin_country' => strtoupper((string) ($location->country_code ?: 'US')),
                'destination_country' => strtoupper((string) ($order->addresses->first()->country_code ?? 'US')),
                'destination_residential' => false,
                'ship_date' => now()->toDateString(),
                'packages' => $packages,
            ],
            'response_summary' => [
                'selected_rate_type' => 'ACCOUNT',
                'rate_type' => 'ACCOUNT',
            ],
        ]);
    }
}
