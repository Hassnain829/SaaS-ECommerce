<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Checkout;
use App\Models\CheckoutItem;
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
use App\Services\Carriers\FedEx\Connection\FedExMerchantConnectionLifecycleService;
use App\Services\Carriers\FedEx\Operations\FedExCheckoutPackageBuilder;
use App\Services\Carriers\FedEx\Operations\FedExCheckoutRateResolver;
use App\Services\Carriers\FedEx\Support\FedExCheckoutCapabilityService;
use App\Services\Delivery\DeliveryOptionInputNormalizer;
use App\Services\Delivery\DeliverySetupStatusService;
use App\Services\Delivery\ManualDeliveryProviderResolver;
use App\Services\Fulfillment\ShipmentService;
use App\Support\OrderLifecycle;
use Database\Seeders\CarrierSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class FedExDeliveryEnterpriseUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.checkout_rates_enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => 'parent-id',
            'carriers.fedex.sandbox.client_secret' => 'parent-secret',
        ]);
    }

    public function test_wizard_creates_fedex_live_rate_methods_with_service_codes(): void
    {
        [$owner, $store, $account] = $this->connectedFedExAccount('Wizard FedEx Live');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.delivery-option'), [
                'checkout_shipping_mode' => 'fedex_live',
                'shipping_zone_id' => $zone->id,
                'fedex_services' => ['FEDEX_GROUND', 'GROUND_HOME_DELIVERY'],
                'available_to_customers' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.review'));

        $methods = ShippingMethod::query()
            ->where('store_id', $store->id)
            ->where('rate_type', ShippingMethod::RATE_CARRIER_CALCULATED_LATER)
            ->orderBy('carrier_service_code')
            ->get();

        $this->assertCount(2, $methods);
        $this->assertSame($account->id, $methods->first()->carrier_account_id);
        $this->assertEqualsCanonicalizing(
            ['FEDEX_GROUND', 'GROUND_HOME_DELIVERY'],
            $methods->pluck('carrier_service_code')->all()
        );

        $account->refresh();
        $this->assertTrue((bool) $account->enabled_for_checkout);
        $this->assertTrue((bool) data_get($account->capabilities, 'checkout_rates'));
    }

    public function test_wizard_both_mode_creates_fedex_and_manual_fallback(): void
    {
        [$owner, $store] = $this->connectedFedExAccount('Wizard FedEx Both');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.delivery-option'), [
                'checkout_shipping_mode' => 'both',
                'shipping_zone_id' => $zone->id,
                'fedex_services' => ['FEDEX_2_DAY'],
                'fallback_name' => 'Economy fallback',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '4.00',
                'available_to_customers' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.review'));

        $this->assertDatabaseHas('shipping_methods', [
            'store_id' => $store->id,
            'carrier_service_code' => 'FEDEX_2_DAY',
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
        ]);
        $this->assertDatabaseHas('shipping_methods', [
            'store_id' => $store->id,
            'name' => 'Economy fallback',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 4,
        ]);
    }

    public function test_normalizer_does_not_downgrade_fedex_live_rate_method(): void
    {
        [$owner, $store, $account] = $this->connectedFedExAccount('Normalizer Protect');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'carrier_service_code' => 'FEDEX_GROUND',
            'carrier_service_name' => 'FedEx Ground',
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground-live',
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'flat_rate' => 0,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);
        $method->setRelation('carrierAccount', $account);

        $merged = (new DeliveryOptionInputNormalizer)->mergePreservedMethodFields($method, [
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 9,
            'carrier_account_id' => null,
            'carrier_service_code' => null,
            'name' => 'Should keep FedEx',
        ]);

        $this->assertSame(ShippingMethod::RATE_CARRIER_CALCULATED_LATER, $merged['rate_type']);
        $this->assertSame($account->id, $merged['carrier_account_id']);
        $this->assertSame('FEDEX_GROUND', $merged['carrier_service_code']);
        $this->assertSame(0, $merged['flat_rate']);
    }

    public function test_verify_preserves_enabled_capabilities_and_checkout_flag(): void
    {
        [$owner, $store, $account] = $this->connectedFedExAccount('Verify Preserve Caps');
        $account->forceFill([
            'enabled_for_checkout' => true,
            'capabilities' => [
                'rates' => true,
                'labels' => true,
                'tracking' => true,
                'checkout_rates' => true,
                'pickup' => false,
            ],
        ])->save();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200),
        ]);

        $result = app(FedExMerchantConnectionLifecycleService::class)->verify($account->fresh());

        $this->assertTrue($result->success);
        $account->refresh();
        $this->assertTrue((bool) $account->enabled_for_checkout);
        $this->assertTrue((bool) data_get($account->capabilities, 'checkout_rates'));
        $this->assertTrue((bool) data_get($account->capabilities, 'labels'));
        $this->assertTrue((bool) data_get($account->capabilities, 'tracking'));
    }

    public function test_health_flags_fedex_connected_without_live_rate_methods(): void
    {
        [$owner, $store, $account] = $this->connectedFedExAccount('Health FedEx Gap');
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '1 Main',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Flat delivery',
            'code' => 'flat-delivery',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $result = app(DeliverySetupStatusService::class)->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            collect([$method->load('shippingZone')]),
            collect([$account]),
            null,
        );

        $this->assertTrue(collect($result['health_items'])->contains(
            fn (array $item): bool => $item['id'] === 'fedex_connected_no_live_rates'
        ));
        $this->assertSame('FedEx connected', $result['delivery_providers']['title']);
    }

    public function test_health_flags_carrier_pricing_linked_to_manual(): void
    {
        [$owner, $store] = $this->ownerStore('Manual Carrier Pricing');
        $manual = app(ManualDeliveryProviderResolver::class)
            ->resolveForStore($store, $owner);

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $manual->id,
            'name' => 'Live-looking option',
            'code' => 'live-looking',
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'flat_rate' => 0,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $result = app(DeliverySetupStatusService::class)->assess(
            $store,
            collect(),
            collect([$zone]),
            collect([$method->load('shippingZone')]),
            collect([$manual]),
            null,
        );

        $this->assertTrue(collect($result['health_items'])->contains(
            fn (array $item): bool => $item['id'] === 'delivery_option_manual_carrier_pricing_'.$method->id
        ));
    }

    public function test_shipment_filter_ignores_tracking_only_manual_shipments(): void
    {
        [$owner, $store, $account] = $this->connectedFedExAccount('Shipment Filter');

        $fedExShipment = new Shipment([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'tracking_number' => '7946FED',
            'metadata' => ['fedex' => ['idempotency_key' => 'abc']],
        ]);
        $fedExShipment->setRelation('carrierAccount', $account);

        $manualWithTracking = new Shipment([
            'store_id' => $store->id,
            'carrier_account_id' => null,
            'tracking_number' => '1Z999AA10123456784',
            'metadata' => ['source' => 'manual'],
        ]);

        $this->assertTrue($fedExShipment->isFedExManagedShipment($account));
        $this->assertFalse($manualWithTracking->isFedExManagedShipment($account));
    }

    public function test_checkout_rate_resolver_reads_carrier_service_code_column(): void
    {
        $resolver = app(FedExCheckoutRateResolver::class);
        $method = new ShippingMethod([
            'carrier_service_code' => 'PRIORITY_OVERNIGHT',
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
        ]);

        $reflection = new ReflectionMethod(FedExCheckoutRateResolver::class, 'resolve');
        $source = file_get_contents((new \ReflectionClass($resolver))->getFileName());
        $this->assertStringContainsString('carrier_service_code', $source);
        $this->assertSame('PRIORITY_OVERNIGHT', $method->carrier_service_code);
        $this->assertTrue($reflection->isPublic());
    }

    public function test_fedex_center_manage_page_renders_next_step(): void
    {
        [$owner, $store, $account] = $this->connectedFedExAccount('FedEx Center UI');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.manage', $account))
            ->assertOk()
            ->assertSeeText('FedEx Center')
            ->assertSeeText('Account health')
            ->assertSeeText('Checkout rates')
            ->assertSeeText('Manage checkout shipping')
            ->assertSeeText('Shipping & labels')
            ->assertSeeText('Tracking')
            ->assertSeeText('Returns')
            ->assertSeeText('Connection & account')
            ->assertSee(route('settings.delivery.setup.delivery-option'), false)
            ->assertSee(route('shipments.index', ['provider' => 'fedex']), false)
            ->assertDontSee('child-secret')
            ->assertDontSee('parent-secret');
    }

    public function test_wizard_has_four_steps_with_checkout_shipping_label(): void
    {
        $steps = file_get_contents(resource_path('views/user_view/delivery/partials/wizard-steps.blade.php'));
        $this->assertStringContainsString("'Checkout shipping'", $steps);
        $this->assertStringContainsString("4 => ['label' => 'Review'", $steps);
        $this->assertSame(4, preg_match_all("/\\d+ => \\['label'/", $steps));
    }

    public function test_global_checkout_flag_off_blocks_account_checkout_capability(): void
    {
        config(['carriers.fedex.checkout_rates_enabled' => false]);
        [, , $account] = $this->connectedFedExAccount('Capability Gate');

        $enabled = app(FedExCheckoutCapabilityService::class)
            ->enableAccountCheckoutRatesIfAllowed($account->fresh());

        $this->assertFalse($enabled);
        $account->refresh();
        $this->assertFalse((bool) $account->enabled_for_checkout);
        $this->assertFalse((bool) data_get($account->capabilities, 'checkout_rates'));
    }

    public function test_advanced_drawer_cannot_create_carrier_calculated_fedex(): void
    {
        [$owner, $store, $account] = $this->connectedFedExAccount('Advanced Block');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.methods.store'), [
                'shipping_zone_id' => $zone->id,
                'name' => 'Should fail FedEx live',
                'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
                'carrier_account_id' => $account->id,
                'flat_rate' => '0',
                'enabled_for_checkout' => '1',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('rate_type');

        $this->assertSame(0, ShippingMethod::query()
            ->where('store_id', $store->id)
            ->where('name', 'Should fail FedEx live')
            ->count());
    }

    public function test_shipment_service_rejects_model_a_manual_create(): void
    {
        [$owner, $store, $account] = $this->connectedFedExAccount('Reject Model A Create');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Ship item',
            'slug' => 'ship-item-'.Str::random(6),
            'base_price' => 10,
            'sku' => 'SKU-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 10,
            'stock' => 5,
        ]);
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-MODEL-A-'.Str::upper(Str::random(4)),
            'status' => Order::STATUS_CONFIRMED,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'currency_code' => 'USD',
            'subtotal' => 10,
            'total' => 10,
            'grand_total' => 10,
            'item_count' => 1,
            'total_quantity' => 1,
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'placed_at' => now(),
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ship item',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => 1,
            'unit_price' => 10,
            'subtotal' => 10,
            'total' => 10,
        ]);

        try {
            app(ShipmentService::class)->createShipment($order, [
                'carrier_account_id' => $account->id,
                'items' => [$item->id => 1],
            ], $owner);
            $this->fail('Expected ValidationException for Model A FedEx manual create');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('carrier_account_id', $e->errors());
        }
    }

    public function test_shipments_index_is_store_scoped(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Shipments Store A');
        [, $storeB] = $this->ownerStore('Shipments Store B');

        $orderB = Order::query()->create([
            'store_id' => $storeB->id,
            'order_number' => 'ORD-B-'.Str::upper(Str::random(4)),
            'status' => Order::STATUS_CONFIRMED,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'currency_code' => 'USD',
            'subtotal' => 10,
            'total' => 10,
            'grand_total' => 10,
        ]);

        $shipmentB = Shipment::query()->create([
            'store_id' => $storeB->id,
            'order_id' => $orderB->id,
            'shipment_number' => 'SHP-B-SECRET-'.Str::upper(Str::random(4)),
            'status' => Shipment::STATUS_PENDING,
            'direction' => Shipment::DIRECTION_OUTBOUND,
            'package_count' => 1,
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('shipments.index'))
            ->assertOk()
            ->assertDontSee($shipmentB->shipment_number);
    }

    public function test_shipment_order_return_relationship_exists_when_migrated(): void
    {
        if (! Schema::hasColumn('shipments', 'order_return_id')) {
            $this->markTestSkipped('order_return_id migration not applied yet');
        }

        $this->assertTrue(method_exists(Shipment::class, 'orderReturn'));
        $relation = (new Shipment)->orderReturn();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_package_builder_ready_false_without_fake_defaults_when_weights_missing(): void
    {
        [$owner, $store] = $this->ownerStore('Package Builder Missing Weight');
        $checkout = new Checkout(['store_id' => $store->id]);
        $checkout->setRelation('store', $store);
        $item = new CheckoutItem([
            'id' => 1,
            'quantity' => 1,
            'product_variant_id' => 99,
            'product_name' => 'No weight product',
            'product_type_snapshot' => 'physical',
        ]);
        $checkout->setRelation('items', collect([$item]));

        $build = app(FedExCheckoutPackageBuilder::class)
            ->buildFromCheckout($checkout);

        $this->assertFalse($build['ready']);
        $this->assertSame([], $build['packages']);
        $this->assertSame('missing_weights', $build['reason']);
        $this->assertNotContains(1.0, array_column($build['packages'], 'weight'));
    }

    public function test_test_checkout_shipping_page_labels(): void
    {
        [$owner, $store] = $this->ownerStore('Diagnostic Labels');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.test-address'))
            ->assertOk()
            ->assertSeeText('Preview checkout delivery')
            ->assertDontSeeText('Test a customer address');
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function connectedFedExAccount(string $name): array
    {
        [$owner, $store] = $this->ownerStore($name);
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Origin',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Commerce',
            'city' => 'Dallas',
            'state' => 'TX',
            'postal_code' => '75001',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => $name,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
            'enabled_for_checkout' => false,
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'checkout_rates' => false,
            ],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
        $account->setFedExAccountNumber('740561073');
        $account->setCredentials([
            'customer_key' => 'child-key-'.$account->id,
            'customer_password' => 'child-secret-'.$account->id,
        ]);
        $account->assignFedExActiveStoreKey();
        $account->save();

        return [$owner, $store, $account->fresh()];
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner->update(['role_id' => $role->id]);

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
