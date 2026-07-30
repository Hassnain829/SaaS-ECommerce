<?php

namespace Tests\Feature;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Role;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6ShippingDeliveryUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.usps.enabled' => true,
            'carriers.usps.sandbox.base_url' => 'https://apis-tem.usps.com',
            'carriers.usps.sandbox.client_id' => 'test-usps-client',
            'carriers.usps.sandbox.client_secret' => 'test-usps-secret',
        ]);
    }

    public function test_shipping_page_renders_delivery_setup_hub(): void
    {
        [$owner, $store] = $this->ownerStore('Shipping UX Overview Store');
        $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Delivery setup')
            ->assertSeeText('Where do you ship from?')
            ->assertSeeText('Where do you deliver?')
            ->assertSeeText('Connect delivery provider')
            ->assertSee(route('shipping.carriers.connect.index'), false)
            ->assertSee('id="delivery-advanced-panel"', false);
    }

    public function test_legacy_shipping_tab_query_still_opens_advanced_view(): void
    {
        [$owner, $store] = $this->ownerStore('Shipping UX Legacy Tab Store');
        $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'zones']))
            ->assertOk()
            ->assertSeeText('Delivery areas');
    }

    public function test_shipping_page_separates_fedex_usps_and_manual_sections(): void
    {
        [$owner, $store] = $this->ownerStore('Shipping UX Carriers Store');
        $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'advanced']))
            ->assertOk()
            ->assertSeeText('FedEx Merchant Account')
            ->assertSeeText('USPS Merchant Account')
            ->assertSeeText('Manual / Local Delivery')
            ->assertSeeText('Connect FedEx account');
    }

    public function test_fedex_merchant_card_masks_secrets_and_collapses_diagnostics(): void
    {
        [$owner, $store] = $this->ownerStore('Shipping UX FedEx Mask Store');
        $location = $this->readyLocation($store);

        $fedExCarrier = \App\Models\Carrier::query()->where('code', 'fedex')->first();

        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedExCarrier?->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'Main FedEx account',
            'provider_account_number' => '510087240',
            'ownership_mode' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
            'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ENCRYPTED,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'settings' => ['default_origin_location_id' => $location->id],
        ]);
        $account->setCredentials([
            'client_id' => 'l7a1b2c3d4e5f678901234567890abcd',
            'client_secret' => 'super-secret-fedex-value',
        ]);
        $account->save();

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'));

        $response->assertOk()
            ->assertSeeText('Merchant-owned')
            ->assertSeeText('Billing handled by merchant')
            ->assertSee($account->maskedAccountNumber(), false)
            ->assertSee($account->maskedMerchantClientId(), false)
            ->assertSeeText('View technical details')
            ->assertDontSee('510087240', false)
            ->assertDontSee('l7a1b2c3d4e5f678901234567890abcd', false)
            ->assertDontSee('super-secret-fedex-value', false);

        $html = (string) $response->getContent();
        $this->assertStringContainsString('id="shipping-drawer-zone"', $html);
        $this->assertStringContainsString('shipping-drawer shipping-drawer-modal hidden', $html);
        $this->assertStringNotContainsString('<details open', $html);
    }

    public function test_zone_and_method_forms_use_hidden_drawers_not_inline_forms(): void
    {
        [$owner, $store] = $this->ownerStore('Shipping UX Drawer Store');
        $this->readyLocation($store);
        ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'));

        $response->assertOk()
            ->assertSee('data-open-drawer="zone-add"', false)
            ->assertSee('data-open-drawer="method-add"', false)
            ->assertSee('id="zone-drawer-form"', false)
            ->assertSee('id="method-drawer-form"', false);

        $html = (string) $response->getContent();
        $this->assertStringContainsString('id="shipping-drawer-method"', $html);
        $this->assertStringContainsString('shipping-drawer shipping-drawer-modal hidden', $html);
    }

    public function test_connect_carrier_wizard_still_renders(): void
    {
        [$owner, $store] = $this->ownerStore('Shipping UX Wizard Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertOk()
            ->assertSeeText('Connect carrier account')
            ->assertSeeText('Connect FedEx account');
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = $this->merchant(Str::slug($name).'-owner@example.test');
        $store = $this->store($owner, $name);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        return [$owner, $store];
    }

    private function readyLocation(Store $store): Location
    {
        return Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);
    }
}
