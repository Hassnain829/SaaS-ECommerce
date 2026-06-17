<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\FedExMerchantCheckPresenter;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExMerchantApiChecksTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ACCOUNT_NUMBER = '510087240';

    private const TEST_CLIENT_ID = 'l7a1b2c3d4e5f678901234567890abcd';

    private const TEST_CLIENT_SECRET = 'test-merchant-fedex-secret-key-value';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExFeature(true);
    }

    public function test_fedex_testing_tools_render_for_merchant_credentials_account(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Tools UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertOk()
            ->assertSeeText('FedEx testing tools')
            ->assertSeeText('Address check')
            ->assertSeeText('Service availability check')
            ->assertSeeText('Rate quote test')
            ->assertSee(route('settings.shipping.carrier-accounts.fedex.test-address', $account), false)
            ->assertSee(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), false)
            ->assertSee(route('settings.shipping.carrier-accounts.fedex.test-rate-quote', $account), false);
    }

    public function test_legacy_integrator_account_does_not_show_merchant_testing_tools(): void
    {
        [$owner, $store] = $this->ownerStore('Legacy FedEx Tools Store');
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'Legacy FedEx',
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertOk()
            ->assertDontSeeText('FedEx testing tools');
    }

    public function test_address_check_uses_merchant_oauth_and_logs_redacted_event(): void
    {
        [$owner, $store, $account] = $this->merchantFedExFixture('FedEx Address Check Store');
        $registrationCalled = false;

        Http::fake(function ($request) use (&$registrationCalled) {
            $url = $request->url();

            if (str_contains($url, '/registration/')) {
                $registrationCalled = true;
            }

            if (str_contains($url, '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-address-test-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/address/v1/addresses/resolve')) {
                return Http::response([
                    'transactionId' => 'fedex-address-txn-1',
                    'output' => [
                        'resolvedAddresses' => [[
                            'streetLines' => ['100 MAIN ST'],
                            'city' => 'MEMPHIS',
                            'stateOrProvinceCode' => 'TN',
                            'postalCode' => '38118',
                            'countryCode' => 'US',
                            'classification' => 'BUSINESS',
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-address', $account), [
                'address_line1' => '100 Main St',
                'city' => 'Memphis',
                'state' => 'TN',
                'postal_code' => '38118',
                'country_code' => 'US',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('fedex_test_result')
            ->assertSessionHas('success');

        $this->assertFalse($registrationCalled);

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(CarrierApiEvent::STATUS_SUCCEEDED, $event->status);
        $this->assertStringNotContainsString(self::TEST_CLIENT_SECRET, json_encode($event->request_summary));
        $this->assertStringNotContainsString(self::TEST_CLIENT_ID, json_encode($event->request_summary));
        $this->assertStringNotContainsString(self::TEST_ACCOUNT_NUMBER, json_encode($event->request_summary));
        $this->assertStringNotContainsString('fedex-address-test-token', json_encode($event->response_summary));
    }

    public function test_service_availability_presenter_handles_package_and_service_type_arrays(): void
    {
        $presentation = FedExMerchantCheckPresenter::serviceAvailability([
            'output' => [
                'packageOptions' => [[
                    'packageType' => ['key' => 'YOUR_PACKAGING', 'displayText' => 'Your Packaging'],
                    'serviceType' => ['key' => 'FEDEX_2_DAY', 'displayText' => 'FedEx 2Day'],
                    'serviceCategory' => 'parcel',
                    'operatingOrgCodes' => ['FXE'],
                ], [
                    'packageType' => ['key' => 'FEDEX_ENVELOPE', 'displayText' => 'FedEx Envelope'],
                    'serviceType' => ['key' => 'FEDEX_2_DAY', 'displayText' => 'FedEx 2Day'],
                ]],
            ],
        ]);

        $this->assertSame(2, $presentation['service_count']);
        $this->assertSame(2, $presentation['package_type_count']);
        $this->assertSame('FEDEX_2_DAY', $presentation['services'][0]['service_type']);
        $this->assertSame('FedEx 2Day', $presentation['services'][0]['service_name']);
        $this->assertSame('YOUR_PACKAGING', $presentation['services'][0]['packaging_type']);
        $this->assertSame('Your Packaging', $presentation['services'][0]['packaging_name']);
        $this->assertSame('FEDEX_ENVELOPE', $presentation['package_types'][1]['package_type']);
    }

    public function test_service_availability_presenter_deduplicates_package_types(): void
    {
        $presentation = FedExMerchantCheckPresenter::serviceAvailability([
            'output' => [
                'packageOptions' => [
                    [
                        'packageType' => ['key' => 'YOUR_PACKAGING', 'displayText' => 'Your Packaging'],
                        'serviceType' => ['key' => 'FEDEX_GROUND', 'displayText' => 'FedEx Ground'],
                    ],
                    [
                        'packageType' => ['key' => 'YOUR_PACKAGING', 'displayText' => 'Your Packaging'],
                        'serviceType' => ['key' => 'FEDEX_EXPRESS_SAVER', 'displayText' => 'FedEx Express Saver'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, $presentation['service_count']);
        $this->assertSame(1, $presentation['package_type_count']);
    }

    public function test_service_availability_check_uses_merchant_oauth(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Service Availability Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-service-test-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions' => Http::response([
                'transactionId' => 'fedex-service-txn-1',
                'output' => [
                    'packageOptions' => [[
                        'packageType' => ['key' => 'YOUR_PACKAGING', 'displayText' => 'Your Packaging'],
                        'serviceType' => ['key' => 'FEDEX_GROUND', 'displayText' => 'FedEx Ground'],
                    ]],
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_successful_service_availability_stores_compact_response_summary(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Compact Log Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-service-test-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions' => Http::response([
                'transactionId' => 'fedex-service-txn-2',
                'output' => [
                    'packageOptions' => array_map(fn (int $index): array => [
                        'packageType' => ['key' => 'YOUR_PACKAGING', 'displayText' => 'Your Packaging'],
                        'serviceType' => ['key' => 'FEDEX_GROUND_'.$index, 'displayText' => 'FedEx Ground '.$index],
                    ], range(1, 12)),
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY)
            ->latest('id')
            ->firstOrFail();

        $encoded = json_encode($event->response_summary);
        $this->assertStringNotContainsString('packageOptions', $encoded);
        $this->assertSame(12, data_get($event->response_summary, 'output_summary.service_count'));
        $this->assertCount(10, data_get($event->response_summary, 'output_summary.service_samples'));
    }

    public function test_rate_quote_http_403_shows_merchant_friendly_authorization_message(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Rate Quote 403 Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-rate-test-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/rate/v1/rates/quotes' => Http::response([
                'errors' => [['code' => 'FORBIDDEN', 'message' => 'We could not authorize your credentials.']],
            ], 403),
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-rate-quote', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
            ]);

        $response->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHasErrors(['fedex'])
            ->assertSessionHas('fedex_test_result');

        $message = session('errors')->first('fedex');
        $this->assertStringContainsString('Rates and Transit Times API product', $message);
        $this->assertStringNotContainsString('Array to string conversion', $message);
    }

    public function test_service_availability_check_uses_merchant_oauth_legacy_shape(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Service Availability Legacy Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-service-test-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions' => Http::response([
                'transactionId' => 'fedex-service-txn-legacy',
                'output' => [
                    'packageOptions' => [[
                        'packageType' => 'YOUR_PACKAGING',
                        'serviceOptions' => [[
                            'serviceType' => 'FEDEX_GROUND',
                            'serviceName' => 'FedEx Ground',
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');
    }

    public function test_rate_quote_check_uses_merchant_oauth_and_does_not_enable_capabilities(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Rate Quote Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-rate-test-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/rate/v1/rates/quotes' => Http::response([
                'transactionId' => 'fedex-rate-txn-1',
                'output' => [
                    'rateReplyDetails' => [[
                        'serviceType' => 'FEDEX_GROUND',
                        'serviceName' => 'FedEx Ground',
                        'ratedShipmentDetails' => [[
                            'totalNetCharge' => ['amount' => 12.45, 'currency' => 'USD'],
                        ]],
                    ]],
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-rate-quote', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertFalse($account->supportsLabels());
        $this->assertFalse($account->enabled_for_checkout);
        $this->assertFalse(data_get($account->capabilities, 'checkout_rates', true));
        $this->assertFalse(data_get($account->capabilities, 'rates', true));

        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_failed_fedex_api_response_shows_merchant_friendly_error(): void
    {
        [$owner, $store, $account] = $this->merchantFedExFixture('FedEx Failed Address Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-fail-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/address/v1/addresses/resolve' => Http::response([
                'errors' => [['code' => 'INVALID.INPUT.EXCEPTION', 'message' => 'Address could not be validated.']],
            ], 400),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-address', $account), [
                'address_line1' => '100 Main St',
                'city' => 'Memphis',
                'state' => 'TN',
                'postal_code' => '38118',
                'country_code' => 'US',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHasErrors(['fedex']);
    }

    public function test_store_a_cannot_test_store_b_fedex_account(): void
    {
        [$ownerA, $storeA, $accountA] = $this->merchantFedExFixture('Store A FedEx');
        [$ownerB, $storeB] = $this->ownerStore('Store B FedEx');

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeB->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-address', $accountA), [
                'address_line1' => '100 Main St',
                'city' => 'Memphis',
                'state' => 'TN',
                'postal_code' => '38118',
                'country_code' => 'US',
            ])
            ->assertNotFound();
    }

    public function test_staff_cannot_run_fedex_testing_tools(): void
    {
        [$owner, $store, $account] = $this->merchantFedExFixture('FedEx Staff Tools Store');
        $staff = $this->staffUser('fedex-staff-tools@example.test');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-address', $account), [
                'address_line1' => '100 Main St',
                'city' => 'Memphis',
                'state' => 'TN',
                'postal_code' => '38118',
                'country_code' => 'US',
            ])
            ->assertForbidden();
    }

    public function test_existing_fedex_connection_check_still_works(): void
    {
        [$owner, $store, $account] = $this->merchantFedExFixture('FedEx Connection Still Works Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-connection-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount, 3: Location}
     */
    private function merchantFedExFixture(string $name): array
    {
        [$owner, $store] = $this->ownerStore($name);
        $location = $this->readyLocation($store);
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'Main FedEx account',
            'provider_account_number' => self::TEST_ACCOUNT_NUMBER,
            'ownership_mode' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
            'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ENCRYPTED,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'settings' => ['default_origin_location_id' => $location->id],
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'checkout_rates' => false,
            ],
        ]);

        $account->setCredentials([
            'client_id' => self::TEST_CLIENT_ID,
            'client_secret' => self::TEST_CLIENT_SECRET,
        ]);
        $account->save();

        return [$owner, $store, $account, $location];
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

    private function configureFedExFeature(bool $enabled): void
    {
        config([
            'carriers.fedex.enabled' => $enabled,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.live.base_url' => 'https://apis.fedex.com',
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
