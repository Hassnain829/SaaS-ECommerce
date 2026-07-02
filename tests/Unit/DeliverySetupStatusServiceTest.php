<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\Delivery\DeliverySetupStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliverySetupStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assess_flags_missing_ship_from_and_delivery_configuration(): void
    {
        $store = $this->store();
        $service = new DeliverySetupStatusService;

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

        $service = new DeliverySetupStatusService;
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

        $service = new DeliverySetupStatusService;
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
