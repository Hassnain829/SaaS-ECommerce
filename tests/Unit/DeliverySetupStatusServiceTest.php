<?php

namespace Tests\Unit;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\Delivery\DeliverySetupStatusService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliverySetupStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
    }

    private function service(): DeliverySetupStatusService
    {
        return app(DeliverySetupStatusService::class);
    }

    public function test_assess_flags_missing_ship_from_and_delivery_configuration(): void
    {
        $store = $this->store();
        $service = $this->service();

        $result = $service->assess(
            $store,
            collect(),
            collect(),
            collect(),
            collect(),
            TaxSetting::query()->updateOrCreate(
                ['store_id' => $store->id],
                [
                    'enabled' => false,
                    'prices_include_tax' => false,
                    'default_product_taxable' => true,
                    'shipping_taxable' => false,
                    'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
                    'settings_version' => 1,
                ],
            ),
        );

        $this->assertFalse($result['is_ready']);
        $this->assertSame('missing', $result['ship_from']['status']);
        $this->assertSame('Tax is off', $result['tax_summary']['title']);
        $this->assertTrue(collect($result['health_items'])->contains(fn (array $item): bool => $item['id'] === 'ship_from_missing'));
        $this->assertTrue(collect($result['health_items'])->contains(fn (array $item): bool => $item['id'] === 'delivery_area_missing'));
    }

    public function test_assess_flags_active_option_hidden_from_checkout(): void
    {
        $store = $this->store();
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main location',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '123 Main St',
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
            'regions' => [],
            'postal_patterns' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard delivery',
            'code' => 'standard-delivery',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'is_active' => true,
            'enabled_for_checkout' => false,
            'sort_order' => 0,
        ]);

        $service = $this->service();
        $result = $service->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            collect([$method]),
            collect(),
            null,
        );

        $this->assertFalse($result['is_ready']);
        $this->assertTrue(collect($result['health_items'])->contains(
            fn (array $item): bool => $item['id'] === 'delivery_option_active_hidden_'.$method->id
        ));
    }

    public function test_assess_marks_ready_store_as_ready(): void
    {
        $store = $this->store();
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main location',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '123 Main St',
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
            'regions' => [],
            'postal_patterns' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard delivery',
            'code' => 'standard-delivery',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $taxSetting = TaxSetting::query()->updateOrCreate(
            ['store_id' => $store->id],
            [
                'enabled' => true,
                'prices_include_tax' => false,
                'default_product_taxable' => true,
                'shipping_taxable' => false,
                'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
                'settings_version' => 1,
            ],
        );

        $service = $this->service();
        $result = $service->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            collect([$method]),
            collect(),
            $taxSetting,
        );

        $this->assertTrue($result['is_ready']);
        $this->assertSame('Tax is added at checkout', $result['tax_summary']['title']);
    }

    public function test_assess_ready_uses_configuration_checks_not_sample_destination(): void
    {
        $store = $this->store();
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main location',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $texasZone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Texas only',
            'countries' => ['US'],
            'regions' => ['TX'],
            'postal_patterns' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $californiaZone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'California',
            'countries' => ['US'],
            'regions' => ['CA'],
            'postal_patterns' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $californiaZone->id,
            'name' => 'California standard',
            'code' => 'california-standard',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 8,
            'min_order_amount' => 150,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $result = $this->service()->assess(
            $store,
            collect([$location]),
            collect([$texasZone, $californiaZone]),
            collect([$method]),
            collect(),
            null,
        );

        $this->assertTrue($result['is_ready']);
        $this->assertFalse(collect($result['health_items'])->contains(
            fn (array $item): bool => $item['id'] === 'delivery_option_not_configuration_ready'
        ));
    }

    public function test_assess_not_ready_when_linked_provider_is_disabled(): void
    {
        [$store, $location, $zone, $account] = $this->configuredStoreWithProvider(
            status: CarrierAccount::STATUS_DISABLED,
            enabledForCheckout: true,
            connectionStatus: CarrierAccount::CONNECTION_CONNECTED,
            manual: true,
        );

        $method = $this->checkoutMethod($store, $zone, $account);

        $result = $this->service()->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            collect([$method]),
            collect([$account]),
            null,
        );

        $this->assertFalse($result['is_ready']);
    }

    public function test_assess_not_ready_when_linked_provider_is_not_checkout_enabled(): void
    {
        [$store, $location, $zone, $account] = $this->configuredStoreWithProvider(
            status: CarrierAccount::STATUS_ENABLED,
            enabledForCheckout: false,
            connectionStatus: CarrierAccount::CONNECTION_CONNECTED,
            manual: true,
        );

        $method = $this->checkoutMethod($store, $zone, $account);

        $result = $this->service()->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            collect([$method]),
            collect([$account]),
            null,
        );

        $this->assertFalse($result['is_ready']);
    }

    public function test_assess_ready_when_linked_manual_provider_is_valid(): void
    {
        [$store, $location, $zone, $account] = $this->configuredStoreWithProvider(
            status: CarrierAccount::STATUS_ENABLED,
            enabledForCheckout: true,
            connectionStatus: CarrierAccount::CONNECTION_CONNECTED,
            manual: true,
        );

        $method = $this->checkoutMethod($store, $zone, $account);

        $result = $this->service()->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            collect([$method]),
            collect([$account]),
            null,
        );

        $this->assertTrue($result['is_ready']);
    }

    public function test_assess_ready_when_linked_api_provider_is_connected(): void
    {
        [$store, $location, $zone, $account] = $this->configuredStoreWithProvider(
            status: CarrierAccount::STATUS_ENABLED,
            enabledForCheckout: true,
            connectionStatus: CarrierAccount::CONNECTION_CONNECTED,
            manual: false,
        );

        $method = $this->checkoutMethod($store, $zone, $account);

        $result = $this->service()->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            collect([$method]),
            collect([$account]),
            null,
        );

        $this->assertTrue($result['is_ready']);
    }

    /**
     * @return array{0: Store, 1: Location, 2: ShippingZone, 3: CarrierAccount}
     */
    private function configuredStoreWithProvider(
        string $status,
        bool $enabledForCheckout,
        string $connectionStatus,
        bool $manual,
    ): array {
        $store = $this->store();
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main location',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '123 Main St',
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
            'regions' => [],
            'postal_patterns' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        if ($manual) {
            $manualCarrier = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();
            $account = CarrierAccount::query()->create([
                'store_id' => $store->id,
                'carrier_id' => $manualCarrier->id,
                'provider' => CarrierAccount::PROVIDER_MANUAL,
                'display_name' => 'Manual delivery',
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'connection_type' => CarrierAccount::CONNECTION_MANUAL,
                'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
                'status' => $status,
                'connection_status' => $connectionStatus,
                'enabled_for_checkout' => $enabledForCheckout,
                ...CarrierAccount::ownershipAttributesForManual(),
            ]);
        } else {
            $fedExCarrier = Carrier::query()->where('code', 'fedex')->firstOrFail();
            $account = CarrierAccount::query()->create([
                'store_id' => $store->id,
                'carrier_id' => $fedExCarrier->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'display_name' => 'FedEx account',
                'ownership_mode' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
                'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ENCRYPTED,
                'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS,
                'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'connection_status' => $connectionStatus,
                'status' => $status,
                'enabled_for_checkout' => $enabledForCheckout,
            ]);
        }

        return [$store, $location, $zone, $account];
    }

    private function checkoutMethod(Store $store, ShippingZone $zone, CarrierAccount $account): ShippingMethod
    {
        return ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'Provider-linked delivery',
            'code' => 'provider-linked-'.Str::random(4),
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 6.5,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);
    }

    private function store(): Store
    {
        $owner = User::factory()->create();

        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Delivery Status Store',
            'slug' => Str::slug('Delivery Status Store').'-'.Str::random(4),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }
}
