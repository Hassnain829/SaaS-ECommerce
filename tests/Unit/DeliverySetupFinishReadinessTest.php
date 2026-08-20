<?php

namespace Tests\Unit;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\DeliverySetupStatusService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliverySetupFinishReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
    }

    public function test_ready_with_fallback_even_when_incomplete_fedex_method_exists(): void
    {
        $store = $this->store();
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main location',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Main',
            'city' => 'Allen',
            'state' => 'TX',
            'postal_code' => '75002',
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

        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx account',
            'ownership_mode' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
            'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ENCRYPTED,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'enabled_for_checkout' => true,
            'capabilities' => ['rates' => true, 'checkout_rates' => true, 'labels' => true],
        ]);

        // Incomplete FedEx option (missing service) should not block finish when fallback is ready.
        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground',
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_service_code' => null,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard fallback',
            'code' => 'standard-fallback',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 7.5,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 1,
        ]);

        // Abandoned inactive+hidden option should not block finish.
        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Old unused option',
            'code' => 'old-unused',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 1,
            'is_active' => false,
            'enabled_for_checkout' => false,
            'sort_order' => 99,
        ]);

        $result = app(DeliverySetupStatusService::class)->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            $store->shippingMethods()->with('shippingZone')->get(),
            collect([$account]),
            null,
        );

        $this->assertTrue($result['is_ready']);
        $this->assertFalse(collect($result['health_items'])->contains(
            fn (array $item): bool => ($item['severity'] ?? '') === 'error'
        ));
    }

    private function store(): Store
    {
        $user = User::factory()->create();

        return Store::query()->create([
            'user_id' => $user->id,
            'name' => 'Finish Ready Store',
            'slug' => 'finish-ready-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }
}
