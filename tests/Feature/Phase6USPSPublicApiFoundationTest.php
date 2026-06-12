<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\CarrierRateQuote;
use App\Models\Location;
use App\Models\Order;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\USPS\USPSConfig;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6USPSPublicApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureUspsPlatform(true);
    }

    public function test_shipping_page_renders_usps_section(): void
    {
        [$owner, $store] = $this->ownerStore('USPS UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('USPS testing tools')
            ->assertSeeText('Connect USPS for testing');
    }

    public function test_missing_usps_config_shows_friendly_setup_required_message(): void
    {
        $this->configureUspsPlatform(false);
        [$owner, $store] = $this->ownerStore('USPS Missing Config Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.store'), $this->uspsPayload())
            ->assertRedirect()
            ->assertSessionHasErrors(['usps']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('USPS testing connection is not available on this platform environment yet');
    }

    public function test_usps_oauth_token_success_marks_account_connected(): void
    {
        [$owner, $store] = $this->ownerStore('USPS OAuth Success Store');
        $account = $this->createUspsAccount($store);
        $this->fakeUspsOAuthOnly();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->isConnected());
        $this->assertNotNull($account->last_verified_at);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_usps_oauth_token_failure_marks_account_failed_and_logs_safe_event(): void
    {
        [$owner, $store, $order] = $this->orderFixture('USPS OAuth Failure Store');
        $account = $this->createUspsAccount($store);

        Http::fake([
            'https://apis-tem.usps.com/oauth2/v3/token' => Http::response([
                'error' => [
                    'code' => 'invalid_client',
                    'message' => 'Invalid consumer credentials',
                ],
            ], 401),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['usps']);

        $account->refresh();
        $order->refresh();

        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $account->connection_status);
        $this->assertSame(Order::query()->find($order->id)?->fulfillment_status, $order->fulfillment_status);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_OAUTH_TOKEN)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(CarrierApiEvent::STATUS_FAILED, $event->status);
        $this->assertStringNotContainsString('test-usps-consumer-key', json_encode($event->request_summary));
        $this->assertStringNotContainsString('test-usps-consumer-secret', json_encode($event->response_summary));
        $this->assertStringNotContainsString('usps-test-access-token', json_encode($event->response_summary));
    }

    public function test_carrier_api_events_never_contain_consumer_key_secret_or_token(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Event Mask Store');
        $location = $this->createOriginLocation($store);
        $account = $this->createUspsAccount($store, defaultOriginLocationId: $location->id);
        $this->fakeUspsHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.test', $account))
            ->assertRedirect();

        $events = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('provider', CarrierAccount::PROVIDER_USPS)
            ->get();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $encoded = json_encode($event->request_summary).' '.json_encode($event->response_summary);
            $this->assertStringNotContainsString('test-usps-consumer-key', $encoded);
            $this->assertStringNotContainsString('test-usps-consumer-secret', $encoded);
            $this->assertStringNotContainsString('usps-test-access-token', $encoded);
        }

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertDontSee('test-usps-consumer-key')
            ->assertDontSee('test-usps-consumer-secret');
    }

    public function test_address_validation_uses_bearer_token_and_returns_standardized_summary(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Address Validation Store');
        $location = $this->createOriginLocation($store);
        $account = $this->createUspsAccount($store, defaultOriginLocationId: $location->id);

        $addressAuthorization = null;

        Http::fake(function ($request) use (&$addressAuthorization) {
            $url = $request->url();

            if (str_contains($url, '/oauth2/v3/token')) {
                return Http::response([
                    'access_token' => 'usps-test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/addresses/v3/address')) {
                $addressAuthorization = $request->header('Authorization');

                return Http::response([
                    'address' => [
                        'streetAddress' => '100 Warehouse Rd',
                        'city' => 'Memphis',
                        'state' => 'TN',
                        'ZIPCode' => '38118',
                        'ZIPPlus4' => '1234',
                    ],
                    'additionalInfo' => ['DPVConfirmation' => 'Y'],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected USPS URL in test: '.$url]]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Bearer usps-test-access-token', $addressAuthorization[0] ?? null);

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ADDRESS_VALIDATION)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(CarrierApiEvent::STATUS_SUCCEEDED, $event->status);
        $this->assertSame('38118', data_get($event->response_summary, 'standardized_summary.zip_code'));
        $this->assertSame('Y', data_get($event->response_summary, 'standardized_summary.dpv_confirmation'));
    }

    public function test_address_validation_failure_is_logged_safely(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Address Failure Store');
        $location = $this->createOriginLocation($store);
        $account = $this->createUspsAccount($store, defaultOriginLocationId: $location->id);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth2/v3/token')) {
                return Http::response([
                    'access_token' => 'usps-test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/addresses/v3/address')) {
                return Http::response([
                    'errors' => [[
                        'code' => 'ADDRESS.NOT.FOUND',
                        'message' => 'Address could not be validated',
                    ]],
                ], 422);
            }

            return Http::response(['errors' => [['message' => 'Unexpected USPS URL in test: '.$url]]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->isConnected());

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ADDRESS_VALIDATION)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(CarrierApiEvent::STATUS_FAILED, $event->status);
        $this->assertSame(422, data_get($event->response_summary, 'http_status'));
        $this->assertStringNotContainsString('usps-test-access-token', json_encode($event->response_summary));
    }

    public function test_package_builder_validates_positive_weight_and_dimensions(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Package Validation Store');
        $location = $this->createOriginLocation($store);
        $account = $this->createUspsAccount($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account, [
                'weight_value' => 0,
            ]))
            ->assertSessionHasErrors(['weight_value']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account, [
                'length' => -1,
            ]))
            ->assertSessionHasErrors(['length']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account, [
                'width' => 0,
            ]))
            ->assertSessionHasErrors(['width']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account, [
                'height' => -0.5,
            ]))
            ->assertSessionHasErrors(['height']);
    }

    public function test_usps_domestic_quote_request_sends_origin_destination_zip_and_package_data(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Quote Payload Store');
        $location = $this->createOriginLocation($store, postalCode: '38118');
        $account = $this->createUspsAccount($store);
        $account->markConnected();

        $capturedPayload = null;
        $quoteAuthorization = null;

        Http::fake(function ($request) use (&$capturedPayload, &$quoteAuthorization) {
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
                $quoteAuthorization = $request->header('Authorization');

                return Http::response([
                    'rates' => [[
                        'mailClass' => 'USPS_GROUND_ADVANTAGE',
                        'description' => 'USPS Ground Advantage',
                        'price' => 8.75,
                        'zone' => '4',
                    ]],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected USPS URL in test: '.$url]]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account, [
                'destination_postal_code' => '90210-1234',
                'weight_value' => 2.5,
                'length' => 10,
                'width' => 8,
                'height' => 4,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertIsArray($capturedPayload);
        $this->assertSame('38118', $capturedPayload['originZIPCode'] ?? null);
        $this->assertSame('90210', $capturedPayload['destinationZIPCode'] ?? null);
        $this->assertSame(2.5, (float) ($capturedPayload['weight'] ?? 0));
        $this->assertSame(10.0, (float) ($capturedPayload['length'] ?? 0));
        $this->assertSame(8.0, (float) ($capturedPayload['width'] ?? 0));
        $this->assertSame(4.0, (float) ($capturedPayload['height'] ?? 0));
        $this->assertSame('Bearer usps-test-access-token', $quoteAuthorization[0] ?? null);
    }

    public function test_rate_quote_success_creates_carrier_rate_quotes_record(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Quote Success Store');
        $location = $this->createOriginLocation($store);
        $account = $this->createUspsAccount($store);
        $account->markConnected();
        $this->fakeUspsHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('carrier_rate_quotes', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'origin_postal_code' => '38118',
            'destination_postal_code' => '90210',
            'status' => CarrierRateQuote::STATUS_SUCCEEDED,
        ]);

        $quote = CarrierRateQuote::query()->where('store_id', $store->id)->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertSame('8.75', number_format((float) $quote->amount, 2, '.', ''));
    }

    public function test_quote_failure_creates_failed_quote_record_without_corrupting_shipment_or_order(): void
    {
        [$owner, $store, $order] = $this->orderFixture('USPS Quote Failure Store');
        $location = $this->createOriginLocation($store);
        $account = $this->createUspsAccount($store);
        $account->markConnected();

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth2/v3/token')) {
                return Http::response([
                    'access_token' => 'usps-test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/prices/v3/base-rates/search')) {
                return Http::response([
                    'errors' => [[
                        'code' => 'RATE.NOT.AVAILABLE',
                        'message' => 'No rate available for package',
                    ]],
                ], 422);
            }

            return Http::response(['errors' => [['message' => 'Unexpected USPS URL in test: '.$url]]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['usps']);

        $order->refresh();

        $this->assertSame('unfulfilled', $order->fulfillment_status);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());
        $this->assertDatabaseHas('carrier_rate_quotes', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'status' => CarrierRateQuote::STATUS_FAILED,
        ]);
    }

    public function test_store_a_cannot_use_store_b_usps_account_package_order_or_location(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('USPS Store A');
        [$ownerB, $storeB] = $this->ownerStore('USPS Store B');
        $this->attach($storeA, $ownerB, Store::ROLE_MANAGER);

        $otherLocation = Location::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Store B warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '200 Other St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '73301',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.carrier-accounts.usps.store'), array_merge($this->uspsPayload(), [
                'default_origin_location_id' => $otherLocation->id,
            ]))
            ->assertSessionHasErrors(['default_origin_location_id']);

        $accountB = $this->createUspsAccount($storeB);
        $accountA = $this->createUspsAccount($storeA);
        $locationA = $this->createOriginLocation($storeA);

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.carrier-accounts.usps.test', $accountB))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($locationA, $accountB))
            ->assertSessionHasErrors(['carrier_account_id']);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($otherLocation, $accountA, [
                'origin_location_id' => $otherLocation->id,
            ]))
            ->assertSessionHasErrors(['origin_location_id']);
    }

    public function test_staff_cannot_configure_usps(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Staff Block Store');
        $staff = $this->merchant('usps-staff@example.test');
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.store'), $this->uspsPayload())
            ->assertForbidden();

        $this->assertSame(0, CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'usps')->count());

        $account = $this->createUspsAccount($store);
        $location = $this->createOriginLocation($store);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.test', $account))
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account))
            ->assertForbidden();
    }

    public function test_labels_controls_are_not_visible(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Labels Hidden Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('USPS testing tools')
            ->assertDontSeeText('Buy label')
            ->assertDontSeeText('Generate label')
            ->assertDontSeeText('Purchase label')
            ->assertDontSeeText('Print label');
    }

    public function test_eps_payment_authorization_is_not_called_in_this_phase(): void
    {
        [$owner, $store] = $this->ownerStore('USPS EPS Block Store');
        $location = $this->createOriginLocation($store);
        $account = $this->createUspsAccount($store);
        $account->markConnected();

        config(['carriers.usps.platform_label_purchase' => true]);

        $httpCalls = [];

        Http::fake(function ($request) use (&$httpCalls) {
            $httpCalls[] = $request->url();

            if (str_contains($request->url(), '/oauth2/v3/token')) {
                return Http::response([
                    'access_token' => 'usps-test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Should not reach USPS rates in EPS phase']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps.test-package-quote'), $this->uspsQuotePayload($location, $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['usps']);

        $this->assertTrue(collect($httpCalls)->every(
            fn (string $url): bool => ! str_contains(strtolower($url), 'payment')
                && ! str_contains(strtolower($url), 'eps')
                && ! str_contains($url, '/prices/v3/base-rates/search')
        ));

        $this->assertDatabaseHas('carrier_rate_quotes', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'status' => CarrierRateQuote::STATUS_FAILED,
            'error_code' => 'commercial_pricing_not_enabled',
        ]);
    }

    public function test_production_cannot_use_tem_base_url_accidentally(): void
    {
        $this->app['env'] = 'production';
        config([
            'carriers.usps.enabled' => true,
            'carriers.usps.base_url' => USPSConfig::TEM_BASE_URL,
            'carriers.usps.consumer_key' => 'test-usps-consumer-key',
            'carriers.usps.consumer_secret' => 'test-usps-consumer-secret',
        ]);

        $config = app(USPSConfig::class);
        $this->assertFalse($config->allowsConfiguredBaseUrl());
        $this->assertFalse($config->isConfigured());

        [$owner, $store] = $this->ownerStore('USPS Production TEM Block Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.store'), $this->uspsPayload())
            ->assertRedirect()
            ->assertSessionHasErrors(['usps']);
    }

    public function test_owner_can_create_usps_testing_carrier_account(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Create Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.usps.store'), $this->uspsPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->where('provider', CarrierAccount::PROVIDER_USPS)
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('USPS testing account', $account->display_name);
        $this->assertSame(CarrierAccount::CONNECTION_SETUP_REQUIRED, $account->connection_status);
        $this->assertSame(CarrierAccount::ENVIRONMENT_TESTING, $account->environment);
        $this->assertSame(CarrierAccount::CONNECTION_MODE_USPS_PLATFORM, $account->connection_mode);
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

    /**
     * @return array{0: User, 1: Store, 2: Order}
     */
    private function orderFixture(string $storeName): array
    {
        [$owner, $store] = $this->ownerStore($storeName);
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#USPS'.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'customer_email' => 'buyer@example.test',
            'subtotal' => 20,
            'total' => 20,
            'grand_total' => 20,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);

        return [$owner, $store, $order];
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

    private function createOriginLocation(Store $store, string $postalCode = '38118'): Location
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

    /**
     * @return array<string, mixed>
     */
    private function uspsPayload(): array
    {
        return [
            'display_name' => 'USPS testing account',
            'environment' => 'testing',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uspsQuotePayload(Location $origin, CarrierAccount $account, array $overrides = []): array
    {
        return array_merge([
            'origin_location_id' => $origin->id,
            'destination_postal_code' => '90210',
            'weight_value' => 1,
            'length' => 9,
            'width' => 6,
            'height' => 2,
            'carrier_account_id' => $account->id,
        ], $overrides);
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

    private function fakeUspsHappyPath(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth2/v3/token')) {
                return Http::response([
                    'access_token' => 'usps-test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/addresses/v3/address')) {
                return Http::response([
                    'address' => [
                        'streetAddress' => '100 Warehouse Rd',
                        'city' => 'Memphis',
                        'state' => 'TN',
                        'ZIPCode' => '38118',
                        'ZIPPlus4' => '1234',
                    ],
                    'additionalInfo' => ['DPVConfirmation' => 'Y'],
                ], 200);
            }

            if (str_contains($url, '/prices/v3/base-rates/search')) {
                return Http::response([
                    'rates' => [[
                        'mailClass' => 'USPS_GROUND_ADVANTAGE',
                        'description' => 'USPS Ground Advantage',
                        'price' => 8.75,
                        'zone' => '4',
                    ]],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected USPS URL in test: '.$url]]], 500);
        });
    }

    private function fakeUspsOAuthOnly(): void
    {
        Http::fake([
            'https://apis-tem.usps.com/oauth2/v3/token' => Http::response([
                'access_token' => 'usps-test-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
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
