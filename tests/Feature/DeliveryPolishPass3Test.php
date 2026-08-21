<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingPackagePreset;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryPolishPass3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
    }

    public function test_legacy_tab_query_redirects_to_hub_anchor(): void
    {
        [$owner, $store] = $this->ownerStore('Pass3 Tab Redirect Store');

        $location = (string) $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'zones']))
            ->headers
            ->get('Location');

        $this->assertNotSame('', $location);
        $this->assertStringEndsWith('#delivery-areas', $location);
        $this->assertStringNotContainsString('tab=', $location);
    }

    public function test_legacy_advanced_tab_redirects_to_support_panel(): void
    {
        [$owner, $store] = $this->ownerStore('Pass3 Advanced Redirect Store');

        $location = (string) $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'advanced']))
            ->headers
            ->get('Location');

        $this->assertStringEndsWith('#delivery-troubleshooting', $location);
        $this->assertStringNotContainsString('support=', $location);
    }

    public function test_drawer_available_at_checkout_syncs_both_backend_flags(): void
    {
        [$owner, $store] = $this->ownerStore('Pass3 Availability Store');
        $this->readyLocation($store);
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
                'name' => 'Standard',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '6.00',
                'available_to_customers' => '1',
            ])
            ->assertRedirect();

        $method = ShippingMethod::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertTrue($method->is_active);
        $this->assertTrue($method->enabled_for_checkout);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.shipping.methods.update', $method), [
                'shipping_zone_id' => $zone->id,
                'name' => 'Standard',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '6.00',
                'available_to_customers' => '0',
            ])
            ->assertRedirect();

        $method->refresh();
        $this->assertFalse($method->is_active);
        $this->assertFalse($method->enabled_for_checkout);
    }

    public function test_packages_section_hidden_without_live_rate_or_label_need(): void
    {
        [$owner, $store] = $this->ownerStore('Pass3 Packages Hidden Store');
        $this->readyLocation($store);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Orphan preset',
            'length' => 10,
            'width' => 8,
            'height' => 4,
            'dimension_unit' => 'IN',
            'weight_value' => 1,
            'weight_unit' => 'LB',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertDontSee('id="packages"', false)
            ->assertSeeText('Preview checkout delivery')
            ->assertDontSeeText('Show at checkout')
            ->assertSeeText('Available at checkout');
    }

    public function test_packages_section_shown_when_fedex_live_option_exists(): void
    {
        [$owner, $store] = $this->ownerStore('Pass3 Packages Shown Store');
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

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground-live',
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_service_code' => 'FEDEX_GROUND',
            'carrier_service_name' => 'FedEx Ground',
            'flat_rate' => 0,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSee('id="packages"', false);
    }

    public function test_review_no_longer_shows_tax_card(): void
    {
        [$owner, $store] = $this->ownerStore('Pass3 Review Store');
        $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.review'))
            ->assertOk()
            ->assertDontSeeText('Checkout tax (read-only)')
            ->assertSeeText('Preview checkout delivery');
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
        ]);
    }
}
