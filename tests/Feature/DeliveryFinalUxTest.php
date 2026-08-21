<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryFinalUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.usps.merchant_visible' => false,
            'carriers.fedex.checkout_rates_enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
        ]);
    }

    public function test_never_completed_store_sees_setup_journey_only(): void
    {
        [$owner, $store] = $this->ownerStore('Final UX New Store');
        $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Set up delivery')
            ->assertSeeText('Continue setup')
            ->assertSeeText('FedEx is optional')
            ->assertDontSeeText('Needs attention')
            ->assertDontSee('id="delivery-areas"', false)
            ->assertDontSee('id="delivery-fedex"', false)
            ->assertDontSee('id="packages"', false)
            ->assertDontSee('id="delivery-troubleshooting"', false)
            ->assertDontSee('data-open-drawer="method-add"', false);
    }

    public function test_completed_ready_store_sees_management_hub_not_setup_hero(): void
    {
        [$owner, $store] = $this->ownerStore('Final UX Ready Store');
        $this->readyLocation($store);
        $zone = $this->makeZone($store);
        $this->makeFixedMethod($store, $zone);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Ready')
            ->assertSeeText('Delivery areas & options')
            ->assertSeeText('Shipping origin')
            ->assertSeeText('Troubleshooting')
            ->assertDontSeeText('Continue setup')
            ->assertDontSee('class="dh-setup-hero"', false)
            ->assertDontSeeText('USPS')
            ->assertDontSeeText('DHL')
            ->assertDontSeeText('UPS');
    }

    public function test_completed_broken_store_sees_hub_needs_attention_not_onboarding(): void
    {
        [$owner, $store] = $this->ownerStore('Final UX Broken Store');
        $this->readyLocation($store);
        $this->makeZone($store);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Needs attention')
            ->assertSeeText('Delivery areas & options')
            ->assertDontSee('class="dh-setup-hero"', false)
            ->assertSeeText('Continue setup');
    }

    public function test_fedex_disconnected_is_optional_when_fixed_delivery_exists(): void
    {
        [$owner, $store] = $this->ownerStore('Final UX Optional FedEx Store');
        $this->readyLocation($store);
        $zone = $this->makeZone($store);
        $this->makeFixedMethod($store, $zone);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Optional')
            ->assertSeeText('Not connected')
            ->assertSeeText('Connect FedEx')
            ->assertSeeText('Ready');
    }

    public function test_disabled_method_remains_visible_and_toggle_reenables(): void
    {
        [$owner, $store] = $this->ownerStore('Final UX Toggle Method Store');
        $this->readyLocation($store);
        $zone = $this->makeZone($store);
        $method = $this->makeFixedMethod($store, $zone, available: false);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Standard delivery')
            ->assertSee('data-toggle-kind="method"', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('settings.shipping.methods.availability', $method), [
                'available' => true,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'available' => true]);

        $method->refresh();
        $this->assertTrue($method->is_active);
        $this->assertTrue($method->enabled_for_checkout);
    }

    public function test_area_off_preserves_child_methods(): void
    {
        [$owner, $store] = $this->ownerStore('Final UX Zone Toggle Store');
        $this->readyLocation($store);
        $zone = $this->makeZone($store);
        $method = $this->makeFixedMethod($store, $zone);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('settings.shipping.zones.availability', $zone), [
                'available' => false,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'available' => false]);

        $zone->refresh();
        $this->assertFalse($zone->is_active);
        $this->assertDatabaseHas('shipping_methods', [
            'id' => $method->id,
            'shipping_zone_id' => $zone->id,
            'is_active' => true,
            'enabled_for_checkout' => true,
        ]);
    }

    public function test_fedex_group_toggle_preserves_selected_services(): void
    {
        [$owner, $store] = $this->ownerStore('Final UX FedEx Group Store');
        $this->readyLocation($store);
        $zone = $this->makeZone($store);
        $account = $this->makeFedExAccount($store);
        $ground = $this->makeFedExMethod($store, $zone, $account, 'FEDEX_GROUND', 'FedEx Ground');
        $home = $this->makeFedExMethod($store, $zone, $account, 'GROUND_HOME_DELIVERY', 'FedEx Home Delivery');
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('settings.shipping.zones.fedex-live-rates.availability', $zone), [
                'available' => false,
            ])
            ->assertOk();

        $ground->refresh();
        $home->refresh();
        $this->assertTrue($ground->is_active);
        $this->assertFalse($ground->enabled_for_checkout);
        $this->assertTrue($home->is_active);
        $this->assertFalse($home->enabled_for_checkout);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('settings.shipping.zones.fedex-live-rates.availability', $zone), [
                'available' => true,
            ])
            ->assertOk();

        $ground->refresh();
        $home->refresh();
        $this->assertTrue($ground->enabled_for_checkout);
        $this->assertTrue($home->enabled_for_checkout);
    }

    public function test_cross_store_toggle_is_denied(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Final UX Store A');
        [$ownerB, $storeB] = $this->ownerStore('Final UX Store B');
        $this->readyLocation($storeA);
        $this->readyLocation($storeB);
        $zoneA = $this->makeZone($storeA);
        $zoneB = $this->makeZone($storeB, 'Canada');

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patchJson(route('settings.shipping.zones.availability', $zoneB), [
                'available' => false,
            ])
            ->assertNotFound();

        $this->assertTrue($zoneB->fresh()->is_active);
        $this->assertTrue($zoneA->fresh()->is_active);
    }

    public function test_side_drawer_markup_is_not_centered_modal(): void
    {
        [$owner, $store] = $this->ownerStore('Final UX Drawer Store');
        $this->readyLocation($store);
        $this->makeZone($store);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $html = (string) $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('shipping-side-drawer', $html);
        $this->assertStringContainsString('shipping-drawer-fedex-services', $html);
        $this->assertStringNotContainsString('align-items:center', $html);
        $this->assertStringContainsString('Entire country', $html);
        $this->assertStringContainsString('Available for customers', $html);
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
        return Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main location',
            'type' => 'warehouse',
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Allen',
            'state' => 'TX',
            'postal_code' => '75002',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
    }

    private function makeZone(Store $store, string $name = 'United States'): ShippingZone
    {
        return ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function makeFixedMethod(Store $store, ShippingZone $zone, bool $available = true): ShippingMethod
    {
        return ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard delivery',
            'code' => 'standard-'.Str::lower(Str::random(4)),
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 8,
            'estimated_min_days' => 3,
            'estimated_max_days' => 5,
            'is_active' => $available,
            'enabled_for_checkout' => $available,
            'sort_order' => 0,
        ]);
    }

    private function makeFedExAccount(Store $store): CarrierAccount
    {
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        return CarrierAccount::query()->create([
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
    }

    private function makeFedExMethod(
        Store $store,
        ShippingZone $zone,
        CarrierAccount $account,
        string $code,
        string $name,
    ): ShippingMethod {
        return ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => $name,
            'code' => strtolower($code).'-'.Str::lower(Str::random(3)),
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_service_code' => $code,
            'carrier_service_name' => $name,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);
    }
}
