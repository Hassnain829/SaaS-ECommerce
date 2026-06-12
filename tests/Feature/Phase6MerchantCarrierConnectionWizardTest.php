<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\USPS\USPSConfig;
use App\Support\CarrierAccountStatusPresenter;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6MerchantCarrierConnectionWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureUspsPlatform(true);
    }

    public function test_owner_can_open_carrier_connection_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('Wizard Owner Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertOk()
            ->assertSeeText('Connect carrier account')
            ->assertSeeText('USPS')
            ->assertSeeText('FedEx')
            ->assertSeeText('Manual / Local delivery');
    }

    public function test_staff_cannot_open_carrier_connection_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('Wizard Staff Store');
        $staff = $this->staffUser('wizard-staff@example.test');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertForbidden();
    }

    public function test_ups_and_dhl_do_not_show_fake_connection_forms(): void
    {
        [$owner, $store] = $this->ownerStore('Deferred Carrier Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', 'ups'))
            ->assertRedirect(route('shipping.carriers.connect.index'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertSeeText('Coming later')
            ->assertDontSee('client secret')
            ->assertDontSee('.env');
    }

    public function test_usps_platform_account_is_labeled_platform_testing_not_merchant_owned(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Ownership Label Store');
        $location = $this->readyLocation($store);
        $account = $this->createUspsPlatformAccount($store, $location->id);
        $account->markConnected(CarrierAccount::ownershipAttributesForUspsPlatformTesting()['capabilities']);

        $presenter = CarrierAccountStatusPresenter::for($account->fresh());

        $this->assertSame('Platform testing connection', $presenter->ownershipLabel());
        $this->assertFalse($account->isMerchantOwned());
        $this->assertTrue($account->isPlatformTesting());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Platform testing connection')
            ->assertDontSee('Merchant-owned connected');
    }

    public function test_manual_account_is_labeled_manual_local_delivery(): void
    {
        [$owner, $store] = $this->ownerStore('Manual Label Store');
        $manualCarrier = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();

        $account = $store->carrierAccounts()->create([
            'carrier_id' => $manualCarrier->id,
            'provider' => CarrierAccount::PROVIDER_MANUAL,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'Local courier',
            'connection_type' => CarrierAccount::CONNECTION_MANUAL,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
            'status' => CarrierAccount::STATUS_ENABLED,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            ...CarrierAccount::ownershipAttributesForManual(),
        ]);

        $this->assertSame('Manual/local delivery', CarrierAccountStatusPresenter::for($account)->ownershipLabel());
    }

    public function test_wizard_usps_flow_creates_platform_testing_account_with_origin(): void
    {
        [$owner, $store] = $this->ownerStore('Wizard USPS Flow Store');
        $location = $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.origin', 'usps'), [
                'origin_location_id' => $location->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.ownership', 'usps'), [
                'origin_location_id' => $location->id,
                'ownership_mode' => CarrierAccount::OWNERSHIP_PLATFORM_TESTING,
                'display_name' => 'USPS platform testing',
            ])
            ->assertRedirect();

        $account = CarrierAccount::query()->where('store_id', $store->id)->where('provider', CarrierAccount::PROVIDER_USPS)->first();
        $this->assertNotNull($account);
        $this->assertSame(CarrierAccount::OWNERSHIP_PLATFORM_TESTING, $account->ownership_mode);
        $this->assertSame($location->id, $account->defaultOriginLocationId());
        $this->assertFalse($account->supportsLabels());
        $this->assertFalse($account->supportsTracking());
        $this->assertFalse($account->supportsPickup());
    }

    public function test_store_a_cannot_use_store_b_location_in_wizard(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Wizard Store A');
        [, $storeB] = $this->ownerStore('Wizard Store B');
        $locationB = $this->readyLocation($storeB);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('shipping.carriers.connect.origin', 'usps'), [
                'origin_location_id' => $locationB->id,
            ])
            ->assertSessionHasErrors(['origin_location_id']);
    }

    public function test_incomplete_origin_shows_setup_required_message(): void
    {
        [$owner, $store] = $this->ownerStore('Incomplete Origin Wizard Store');
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Incomplete warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Memphis',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.origin', 'usps'), [
                'origin_location_id' => $location->id,
            ])
            ->assertSessionHasErrors(['origin_location_id']);
    }

    public function test_shipping_page_shows_connect_carrier_account_action(): void
    {
        [$owner, $store] = $this->ownerStore('Shipping Connect CTA Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Connect carrier')
            ->assertSeeText('Add manual/local delivery')
            ->assertSee(route('shipping.carriers.connect.index'), false);
    }

    public function test_shipping_page_does_not_show_raw_carrier_account_form(): void
    {
        [$owner, $store] = $this->ownerStore('No Raw Form Store');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'));

        $response->assertOk()
            ->assertSeeText('Connect carrier')
            ->assertDontSee('Add carrier account', false)
            ->assertDontSee('name="connection_type"', false)
            ->assertDontSee('settings/shipping/carrier-accounts', false);
    }

    public function test_legacy_carrier_account_create_route_redirects_to_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('Legacy Route Redirect Store');
        $manualCarrier = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.store'), [
                'carrier_id' => $manualCarrier->id,
                'display_name' => 'Should not be created',
                'connection_type' => CarrierAccount::CONNECTION_MANUAL,
                'status' => CarrierAccount::STATUS_ENABLED,
            ])
            ->assertRedirect(route('shipping.carriers.connect.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('carrier_accounts', [
            'store_id' => $store->id,
            'display_name' => 'Should not be created',
        ]);
    }

    public function test_staff_cannot_use_legacy_carrier_account_create_route(): void
    {
        [$owner, $store] = $this->ownerStore('Legacy Route Staff Store');
        $staff = $this->staffUser('legacy-staff@example.test');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $manualCarrier = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.store'), [
                'carrier_id' => $manualCarrier->id,
                'display_name' => 'Blocked account',
                'connection_type' => CarrierAccount::CONNECTION_MANUAL,
                'status' => CarrierAccount::STATUS_ENABLED,
            ])
            ->assertForbidden();
    }

    public function test_manual_local_delivery_setup_through_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('Manual Wizard Setup Store');
        $location = $this->readyLocation($store);
        $manualCarrier = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', 'manual'))
            ->assertOk()
            ->assertSeeText('Manual / Local delivery');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.origin', 'manual'), [
                'origin_location_id' => $location->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.ownership', 'manual'), [
                'origin_location_id' => $location->id,
                'ownership_mode' => CarrierAccount::OWNERSHIP_MANUAL,
                'carrier_id' => $manualCarrier->id,
                'display_name' => 'Local courier',
                'supported_countries' => 'US',
                'enabled_for_checkout' => '1',
            ])
            ->assertRedirect();

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->where('display_name', 'Local courier')
            ->firstOrFail();

        $this->assertSame(CarrierAccount::OWNERSHIP_MANUAL, $account->ownership_mode);
        $this->assertSame(CarrierAccount::PROVIDER_MANUAL, $account->provider);
        $this->assertFalse($account->supportsLabels());
        $this->assertFalse($account->supportsTracking());
        $this->assertTrue($account->enabled_for_checkout);
        $this->assertSame(['US'], $account->supported_countries);
        $this->assertSame('Manual/local delivery', CarrierAccountStatusPresenter::for($account)->ownershipLabel());
    }

    public function test_merchant_ui_does_not_expose_secret_wording(): void
    {
        [$owner, $store] = $this->ownerStore('Secret Wording Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertDontSee('USPS_')
            ->assertDontSee('FEDEX_')
            ->assertDontSee('client secret')
            ->assertDontSee('OAuth token')
            ->assertDontSee('payload')
            ->assertDontSee('.env');
    }

    public function test_usps_wizard_test_still_runs_connection_test(): void
    {
        [$owner, $store] = $this->ownerStore('Wizard USPS Test Store');
        $location = $this->readyLocation($store);
        $account = $this->createUspsPlatformAccount($store, $location->id);

        Http::fake([
            'https://apis-tem.usps.com/oauth2/v3/token' => Http::response([
                'access_token' => 'usps-test-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'usps'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    private function createUspsPlatformAccount(Store $store, ?int $originLocationId = null): CarrierAccount
    {
        $usps = Carrier::query()->where('code', 'usps')->firstOrFail();

        return $store->carrierAccounts()->create([
            'carrier_id' => $usps->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => 'USPS platform testing',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_PLATFORM,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'default_origin_location_id' => $originLocationId,
            'settings' => $originLocationId ? ['default_origin_location_id' => $originLocationId] : [],
            ...CarrierAccount::ownershipAttributesForUspsPlatformTesting(),
        ]);
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

    private function staffUser(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }

    private function configureUspsPlatform(bool $configured): void
    {
        config([
            'carriers.usps.enabled' => $configured,
            'carriers.usps.environment' => 'testing',
            'carriers.usps.base_url' => 'https://apis-tem.usps.com',
            'carriers.usps.consumer_key' => $configured ? 'test-usps-consumer-key' : '',
            'carriers.usps.consumer_secret' => $configured ? 'test-usps-consumer-secret' : '',
            'carriers.usps.oauth_path' => '/oauth2/v3/token',
            'carriers.usps.address_validation_path' => '/addresses/v3/address',
            'carriers.usps.domestic_base_rates_path' => '/prices/v3/base-rates/search',
            'carriers.usps.default_mail_class' => 'USPS_GROUND_ADVANTAGE',
            'carriers.usps.default_price_type' => 'RETAIL',
            'carriers.usps.labels_enabled' => false,
            'carriers.usps.platform_label_purchase' => false,
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
