<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\CarrierRateQuote;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6CarrierOriginReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureUspsPlatform(true);
    }

    public function test_us_location_with_complete_address_is_carrier_ready(): void
    {
        [, $store] = $this->ownerStore('Ready Location Store');
        $location = $this->readyLocation($store);

        $readiness = app(CarrierOriginReadinessService::class)->assess($location);

        $this->assertTrue($readiness->ready);
        $this->assertSame('38118', $readiness->originZip5);
        $this->assertSame('ready', $readiness->status);
    }

    public function test_location_missing_state_and_postal_is_not_carrier_ready(): void
    {
        [$owner, $store] = $this->ownerStore('Missing Fields Store');
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

        $readiness = app(CarrierOriginReadinessService::class)->assess($location);

        $this->assertFalse($readiness->ready);
        $this->assertContains('State', $readiness->missingFields);
        $this->assertContains('ZIP code', $readiness->missingFields);
    }

    public function test_country_code_united_states_normalizes_to_us_on_save(): void
    {
        [$owner, $store] = $this->ownerStore('Normalize Country Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.store'), [
                'name' => 'US warehouse',
                'type' => Location::TYPE_WAREHOUSE,
                'address_line1' => '100 Warehouse Rd',
                'city' => 'Allen',
                'state' => 'TX',
                'postal_code' => '75002',
                'country_code' => 'United States',
                'fulfills_online_orders' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $location = Location::query()->where('store_id', $store->id)->where('name', 'US warehouse')->first();
        $this->assertNotNull($location);
        $this->assertSame('US', $location->country_code);
    }

    public function test_invalid_country_code_un_is_rejected_on_save(): void
    {
        [$owner, $store] = $this->ownerStore('Invalid Country Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.store'), [
                'name' => 'Bad country warehouse',
                'type' => Location::TYPE_WAREHOUSE,
                'address_line1' => '100 Warehouse Rd',
                'city' => 'Allen',
                'state' => 'TX',
                'postal_code' => '75002',
                'country_code' => 'UN',
                'fulfills_online_orders' => 1,
            ])
            ->assertSessionHasErrors(['country_code']);
    }

    public function test_service_countries_save_and_read_as_arrays(): void
    {
        [$owner, $store] = $this->ownerStore('Service Countries Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.store'), [
                'name' => 'Routing warehouse',
                'type' => Location::TYPE_WAREHOUSE,
                'address_line1' => '100 Warehouse Rd',
                'city' => 'Allen',
                'state' => 'TX',
                'postal_code' => '75002',
                'country_code' => 'US',
                'service_countries' => 'US, CA',
                'service_regions' => 'TX, CA',
                'service_postal_patterns' => '75002, 606*',
                'fulfills_online_orders' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $location = Location::query()->where('store_id', $store->id)->where('name', 'Routing warehouse')->firstOrFail();
        $this->assertSame(['US', 'CA'], $location->service_countries);
        $this->assertSame(['TX', 'CA'], $location->service_regions);
        $this->assertSame(['75002', '606*'], $location->service_postal_patterns);
    }

    public function test_usps_quote_with_missing_origin_zip_fails_locally_with_friendly_message(): void
    {
        [$owner, $store] = $this->ownerStore('Local Origin Failure Store');
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'No ZIP warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Memphis',
            'state' => 'TN',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
        $account = $this->createUspsAccount($store);
        $account->markConnected();

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), [
                'origin_location_id' => $location->id,
                'destination_postal_code' => '90210',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['usps']);

        $this->assertDatabaseHas('carrier_rate_quotes', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'status' => CarrierRateQuote::STATUS_FAILED,
            'error_code' => 'origin_not_ready',
        ]);
    }

    public function test_usps_quote_with_missing_origin_zip_does_not_call_usps_api(): void
    {
        [$owner, $store] = $this->ownerStore('No USPS API Store');
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'No ZIP warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Memphis',
            'state' => 'TN',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
        $account = $this->createUspsAccount($store);
        $account->markConnected();

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), [
                'origin_location_id' => $location->id,
                'destination_postal_code' => '90210',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
                'carrier_account_id' => $account->id,
            ]);

        Http::assertNothingSent();
        $this->assertSame(0, CarrierApiEvent::query()->where('action', CarrierApiEvent::ACTION_DOMESTIC_RATE_QUOTE)->count());
    }

    public function test_usps_quote_with_ready_origin_sends_origin_zip_code_as_five_digit_string(): void
    {
        [$owner, $store] = $this->ownerStore('Ready Origin Quote Store');
        $location = $this->readyLocation($store, '75002-5212');
        $account = $this->createUspsAccount($store);
        $account->markConnected();

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            $url = $request->url();

            if (str_contains($url, '/oauth2/v3/token')) {
                return Http::response([
                    'access_token' => 'usps-test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/prices/v3/base-rates/search')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'rates' => [[
                        'mailClass' => 'USPS_GROUND_ADVANTAGE',
                        'description' => 'USPS Ground Advantage',
                        'price' => 8.75,
                    ]],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected USPS URL in test: '.$url]]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), [
                'origin_location_id' => $location->id,
                'destination_postal_code' => '90210',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('75002', $capturedPayload['originZIPCode'] ?? null);
    }

    public function test_shipping_page_shows_origin_readiness_state(): void
    {
        [$owner, $store] = $this->ownerStore('Shipping Readiness UI Store');
        $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Fulfillment locations')
            ->assertSeeText('Fulfillment origin')
            ->assertSeeText('Carrier-ready');
    }

    public function test_shipping_page_blocks_quote_when_no_origin_is_ready(): void
    {
        [$owner, $store] = $this->ownerStore('No Ready Origin UI Store');
        Location::query()->create([
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
        $this->createUspsAccount($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Set up a carrier-ready US fulfillment origin before USPS testing.')
            ->assertSee('Get USPS test quote', false);
    }

    public function test_carrier_account_default_origin_dropdown_displays_normalized_address(): void
    {
        [$owner, $store] = $this->ownerStore('Dropdown Label Store');
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main location',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '738 Fawn Valley Dr',
            'city' => 'Allen',
            'state' => 'TX',
            'postal_code' => '75002',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', 'usps'))
            ->assertOk()
            ->assertSeeText('Main location')
            ->assertSeeText('738 FAWN VALLEY DR, ALLEN, TX 75002, US');
    }

    public function test_store_business_address_copy_does_not_claim_to_be_carrier_origin(): void
    {
        [$owner, $store] = $this->ownerStore('Copy Separation Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('your store business address');
    }

    public function test_store_a_cannot_use_store_b_location_as_carrier_origin(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Store A Origin');
        [$ownerB, $storeB] = $this->ownerStore('Store B Origin');
        $otherLocation = $this->readyLocation($storeB);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.carrier-accounts.usps.store'), [
                'display_name' => 'USPS testing account',
                'environment' => 'testing',
                'default_origin_location_id' => $otherLocation->id,
            ])
            ->assertSessionHasErrors(['default_origin_location_id']);
    }

    public function test_store_a_cannot_use_store_b_usps_account_for_quotes(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Store A Quote');
        [$ownerB, $storeB] = $this->ownerStore('Store B Quote');
        $originA = $this->readyLocation($storeA);
        $accountB = $this->createUspsAccount($storeB);
        $accountB->markConnected();

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.usps.test-package-quote'), [
                'origin_location_id' => $originA->id,
                'destination_postal_code' => '90210',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
                'carrier_account_id' => $accountB->id,
            ])
            ->assertSessionHasErrors(['carrier_account_id']);
    }

    public function test_carrier_linked_location_cannot_be_saved_in_non_ready_state(): void
    {
        [$owner, $store] = $this->ownerStore('Carrier Linked Location Store');
        $location = $this->readyLocation($store);
        $this->createUspsAccount($store, defaultOriginLocationId: $location->id);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.locations.update', $location), [
                'name' => $location->name,
                'type' => $location->type,
                'address_line1' => $location->address_line1,
                'city' => $location->city,
                'state' => null,
                'postal_code' => null,
                'country_code' => 'US',
                'fulfills_online_orders' => 1,
            ])
            ->assertSessionHasErrors(['address_line1']);
    }

    private function readyLocation(Store $store, string $postalCode = '38118'): Location
    {
        return Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => $postalCode,
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
    }

    private function createUspsAccount(Store $store, ?int $defaultOriginLocationId = null): CarrierAccount
    {
        $usps = Carrier::query()->where('code', 'usps')->firstOrFail();
        $settings = [];

        if ($defaultOriginLocationId !== null) {
            $settings['default_origin_location_id'] = $defaultOriginLocationId;
        }

        return CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $usps->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => 'USPS testing account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_PLATFORM,
            'billing_owner' => CarrierAccount::BILLING_OWNER_PLATFORM,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'settings' => $settings,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => false,
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

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
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
