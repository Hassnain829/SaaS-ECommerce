<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\DeliverySetupStatusService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryCorrectivePass4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.usps.merchant_visible' => false,
            'carriers.usps.merchant_connection_enabled' => false,
            'carriers.fedex.checkout_rates_enabled' => true,
        ]);
    }

    public function test_completed_merchant_leaves_wizard_for_checkout_options_editor(): void
    {
        [$owner, $store] = $this->ownerStore('Pass4 Checkout Editor Store');
        $location = $store->locations()->orderByDesc('is_default')->orderBy('id')->first();
        $locationAttributes = [
            'name' => 'Main warehouse',
            'type' => 'warehouse',
            'address_line1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ];
        if ($location !== null) {
            $location->update($locationAttributes);
        } else {
            Location::query()->create(['store_id' => $store->id, ...$locationAttributes]);
        }
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard delivery',
            'code' => 'standard-pass4',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 8,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.delivery-option'))
            ->assertRedirect(route('settings.delivery.checkout-options'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.checkout-options'))
            ->assertOk()
            ->assertSeeText('Checkout shipping')
            ->assertDontSeeText('Current step')
            ->assertDontSeeText('Ship from')
            ->assertDontSeeText('Delivery setup');
    }

    public function test_hub_uses_prototype_status_and_grouped_fedex_row(): void
    {
        [$owner, $store] = $this->ownerStore('Pass4 Hub Prototype Store');
        $this->readyLocation($store);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();
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
            'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ACCOUNT,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'fedex_integrator_account' => true,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'enabled_for_checkout' => true,
            'capabilities' => ['checkout_rates' => true, 'labels' => true, 'tracking' => true],
        ]);

        foreach (['FEDEX_GROUND' => 'FedEx Ground', 'GROUND_HOME_DELIVERY' => 'FedEx Home Delivery'] as $code => $name) {
            ShippingMethod::query()->create([
                'store_id' => $store->id,
                'shipping_zone_id' => $zone->id,
                'carrier_account_id' => $account->id,
                'name' => $name,
                'code' => strtolower($code).'-hub',
                'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
                'carrier_service_code' => $code,
                'carrier_service_name' => $name,
                'is_active' => true,
                'enabled_for_checkout' => true,
                'sort_order' => 0,
            ]);
        }

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('FedEx live rates')
            ->assertSeeText('2 services')
            ->assertDontSeeText('SETTINGS · DELIVERY')
            ->assertDontSee('class="delivery-hub-hero"', false)
            ->assertSee('class="dh-option-row"', false)
            ->assertSee('class="dh-cap-grid"', false);
    }

    public function test_add_delivery_option_button_includes_zone_id(): void
    {
        [$owner, $store] = $this->ownerStore('Pass4 Zone Prefill Store');
        $this->readyLocation($store);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Texas local',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSee('data-zone-id="'.$zone->id.'"', false);
    }

    public function test_fedex_only_missing_weights_block_ready(): void
    {
        [$owner, $store] = $this->ownerStore('Pass4 Weight Block Store');
        $this->readyLocation($store);
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
            'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ACCOUNT,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'fedex_integrator_account' => true,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'enabled_for_checkout' => true,
            'capabilities' => ['checkout_rates' => true, 'labels' => true, 'tracking' => true],
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground-weight',
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_service_code' => 'FEDEX_GROUND',
            'carrier_service_name' => 'FedEx Ground',
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Heavy box',
            'slug' => 'heavy-box-'.Str::lower(Str::random(4)),
            'status' => true,
            'requires_shipping' => true,
            'meta' => [],
        ]);

        $assessment = app(DeliverySetupStatusService::class)->assess(
            $store,
            $store->locations()->get(),
            $store->shippingZones()->get(),
            $store->shippingMethods()->with(['shippingZone', 'carrierAccount'])->get(),
            collect([$account]),
            null,
        );

        $this->assertFalse($assessment['is_ready']);
        $this->assertTrue(collect($assessment['health_items'])->contains(
            fn (array $item): bool => ($item['id'] ?? '') === 'products_missing_shipping_weight'
                && ($item['severity'] ?? '') === 'error'
        ));
    }

    public function test_usps_manage_is_gated_when_merchant_hidden(): void
    {
        [$owner, $store] = $this->ownerStore('Pass4 USPS Gate Store');
        $usps = Carrier::query()->where('code', 'usps')->firstOrFail();
        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $usps->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'display_name' => 'USPS account',
            'ownership_mode' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
            'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ACCOUNT,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_CONNECTED,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.manage', $account))
            ->assertNotFound();
    }

    public function test_legacy_advanced_tab_does_not_open_support_console(): void
    {
        [$owner, $store] = $this->ownerStore('Pass4 Legacy Tab Store');

        $location = (string) $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'advanced']))
            ->headers
            ->get('Location');

        $this->assertStringEndsWith('#delivery-troubleshooting', $location);
        $this->assertStringNotContainsString('support=', $location);
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
            'slug' => Str::slug($name).'-'.Str::random(5),
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        return [$owner, $store];
    }

    private function readyLocation(Store $store): Location
    {
        $location = $store->locations()->orderByDesc('is_default')->orderBy('id')->first();
        $attributes = [
            'name' => 'Main warehouse',
            'type' => 'warehouse',
            'address_line1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ];

        if ($location !== null) {
            $location->update($attributes);

            return $location->fresh();
        }

        return Location::query()->create([
            'store_id' => $store->id,
            ...$attributes,
        ]);
    }
}
