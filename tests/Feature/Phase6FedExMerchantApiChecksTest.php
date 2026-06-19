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
use App\Services\Carriers\FedEx\FedExMerchantCredentialsOAuthService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_address_presenter_filters_mismatched_country_suggestions(): void
    {
        $presentation = FedExMerchantCheckPresenter::addressValidation([
            'output' => [
                'resolvedAddresses' => [[
                    'streetLines' => ['AV PROVIDENCIA 123'],
                    'city' => 'Región Metropolitana de Santia',
                    'stateOrProvinceCode' => 'RM',
                    'postalCode' => '7500000',
                    'countryCode' => 'CL',
                ]],
            ],
        ], 'US');

        $this->assertSame([], $presentation['resolved_addresses']);
        $this->assertCount(1, $presentation['ignored_suggestions']);
        $this->assertSame('CL', $presentation['ignored_country_codes'][0] ?? null);
        $this->assertNotEmpty($presentation['warnings']);
    }

    public function test_address_presenter_keeps_matching_country_suggestions(): void
    {
        $presentation = FedExMerchantCheckPresenter::addressValidation([
            'output' => [
                'resolvedAddresses' => [[
                    'streetLines' => ['100 MAIN ST'],
                    'city' => 'MEMPHIS',
                    'stateOrProvinceCode' => 'TN',
                    'postalCode' => '38118',
                    'countryCode' => 'US',
                ]],
            ],
        ], 'US');

        $this->assertCount(1, $presentation['resolved_addresses']);
        $this->assertSame([], $presentation['ignored_suggestions']);
        $this->assertSame('US', $presentation['resolved_addresses'][0]['country_code']);
    }

    public function test_rate_quote_requires_destination_city_for_us(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Rate Quote Validation Store');

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-rate-quote', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '60601',
                'destination_state' => 'IL',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHasErrors(['destination_city']);

        Http::assertNothingSent();
    }

    public function test_rate_quote_payload_includes_destination_city_state_service_and_packaging(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Rate Quote Payload Store');
        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-rate-test-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/rate/v1/rates/quotes')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'fedex-rate-txn-payload',
                    'output' => ['rateReplyDetails' => []],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-rate-quote', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '60601',
                'destination_state' => 'IL',
                'destination_city' => 'CHICAGO',
                'service_type' => 'FEDEX_2_DAY',
                'packaging_type' => 'YOUR_PACKAGING',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $recipient = $capturedPayload['requestedShipment']['recipient']['address'] ?? [];
        $this->assertSame('IL', $recipient['stateOrProvinceCode'] ?? null);
        $this->assertSame('CHICAGO', $recipient['city'] ?? null);
        $this->assertSame('FEDEX_2_DAY', $capturedPayload['requestedShipment']['serviceType'] ?? null);
        $this->assertSame('YOUR_PACKAGING', $capturedPayload['requestedShipment']['packagingType'] ?? null);

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('IL', data_get($event->request_summary, 'destination_state'));
        $this->assertSame('CHICAGO', data_get($event->request_summary, 'destination_city'));
        $this->assertSame('FEDEX_2_DAY', data_get($event->request_summary, 'service_type'));
        $this->assertSame('YOUR_PACKAGING', data_get($event->request_summary, 'packaging_type'));
        $this->assertTrue(data_get($event->request_summary, 'test_quote_only'));
        $this->assertTrue(data_get($event->request_summary, 'auth_header_present'));
        $this->assertSame('Bearer', data_get($event->request_summary, 'auth_scheme'));
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
                'destination_city' => 'Allen',
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
                'destination_city' => 'Allen',
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

    public function test_service_availability_http_500_returns_friendly_message_without_disconnecting_account(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Service Availability 500 Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-service-test-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions' => Http::response(null, 500, [
                'Content-Type' => 'text/html',
                'x-customer-transaction-id' => 'fedex-service-txn-500',
            ]),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
                'destination_city' => 'Allen',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('fedex_test_result')
            ->assertSessionDoesntHaveErrors(['fedex']);

        $result = session('fedex_test_result');
        $this->assertFalse($result['success']);
        $this->assertSame('fedex_api', $result['failure_kind']);
        $this->assertStringContainsString('temporary service-availability error', $result['message']);
        $this->assertStringContainsString('credentials are connected', $result['message']);
        $this->assertSame(500, data_get($result, 'response_summary.http_status'));
        $this->assertSame('fedex-service-txn-500', data_get($result, 'response_summary.fedex_transaction_id'));

        $account->refresh();
        $this->assertSame(CarrierAccount::CONNECTION_CONNECTED, $account->connection_status);
        $this->assertSame(CarrierAccount::STATUS_ENABLED, $account->status);

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(CarrierApiEvent::STATUS_FAILED, $event->status);
        $this->assertStringNotContainsString('packageOptions', json_encode($event->response_summary));
    }

    public function test_missing_us_destination_state_fails_validation_before_calling_fedex(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Service Availability Validation Store');
        $availabilityCalled = false;

        Http::fake(function ($request) use (&$availabilityCalled) {
            if (str_contains($request->url(), '/availability/v1/packageandserviceoptions')) {
                $availabilityCalled = true;
            }

            return Http::response(['errors' => [['message' => 'Should not be called']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_city' => 'Allen',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHasErrors(['destination_state']);

        $this->assertFalse($availabilityCalled);
        $this->assertDatabaseMissing('carrier_api_events', [
            'store_id' => $store->id,
            'action' => CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY,
        ]);
    }

    public function test_service_availability_includes_destination_city_in_fedex_request_when_provided(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Service Availability City Store');
        $capturedCity = null;

        Http::fake(function ($request) use (&$capturedCity) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-service-test-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/availability/v1/packageandserviceoptions')) {
                $payload = $request->data();
                $capturedCity = data_get($payload, 'requestedShipment.recipients.0.address.city');

                return Http::response([
                    'transactionId' => 'fedex-service-txn-city',
                    'output' => [
                        'packageOptions' => [[
                            'packageType' => ['key' => 'YOUR_PACKAGING', 'displayText' => 'Your Packaging'],
                            'serviceType' => ['key' => 'FEDEX_GROUND', 'displayText' => 'FedEx Ground'],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
                'destination_city' => 'Allen',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $this->assertSame('Allen', $capturedCity);
    }

    public function test_service_availability_sends_authorization_bearer_header(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Service Availability Auth Header Store');
        $authorizationHeader = null;

        Http::fake(function ($request) use (&$authorizationHeader) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'Bearer fedex-service-test-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/availability/v1/packageandserviceoptions')) {
                $authorizationHeader = $request->header('Authorization')[0] ?? null;

                return Http::response([
                    'transactionId' => 'fedex-service-txn-auth',
                    'output' => [
                        'packageOptions' => [[
                            'packageType' => ['key' => 'YOUR_PACKAGING', 'displayText' => 'Your Packaging'],
                            'serviceType' => ['key' => 'FEDEX_GROUND', 'displayText' => 'FedEx Ground'],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
                'destination_city' => 'Allen',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $this->assertSame('Bearer fedex-service-test-token', $authorizationHeader);
        $this->assertStringNotContainsString('Bearer Bearer', (string) $authorizationHeader);
    }

    public function test_service_availability_refreshes_token_and_retries_once_on_401(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Service Availability 401 Retry Store');
        $oauthAttempts = 0;
        $availabilityAttempts = 0;
        $authorizationHeaders = [];

        Http::fake(function ($request) use (&$oauthAttempts, &$availabilityAttempts, &$authorizationHeaders) {
            if (str_contains($request->url(), '/oauth/token')) {
                $oauthAttempts++;

                return Http::response([
                    'access_token' => $oauthAttempts === 1 ? 'stale-fedex-token' : 'fresh-fedex-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/availability/v1/packageandserviceoptions')) {
                $availabilityAttempts++;
                $authorizationHeaders[] = $request->header('Authorization')[0] ?? null;

                if ($availabilityAttempts === 1) {
                    return Http::response([
                        'errors' => [['code' => 'NOT.AUTHORIZED', 'message' => 'Invalid token.']],
                    ], 401);
                }

                return Http::response([
                    'transactionId' => 'fedex-service-txn-retry',
                    'output' => [
                        'packageOptions' => [[
                            'packageType' => ['key' => 'YOUR_PACKAGING', 'displayText' => 'Your Packaging'],
                            'serviceType' => ['key' => 'FEDEX_GROUND', 'displayText' => 'FedEx Ground'],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
                'destination_city' => 'Allen',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $this->assertSame(2, $oauthAttempts);
        $this->assertSame(2, $availabilityAttempts);
        $this->assertSame(['Bearer stale-fedex-token', 'Bearer fresh-fedex-token'], $authorizationHeaders);

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(CarrierApiEvent::STATUS_SUCCEEDED, $event->status);
        $this->assertTrue((bool) data_get($event->request_summary, 'token_refreshed_after_401'));
        $this->assertTrue((bool) data_get($event->request_summary, 'auth_header_present'));
        $this->assertSame('Bearer', data_get($event->request_summary, 'auth_scheme'));
    }

    public function test_service_availability_second_401_returns_friendly_failure_without_disconnecting_account(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Service Availability 401 Final Store');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-rejected-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/availability/v1/packageandserviceoptions')) {
                return Http::response([
                    'errors' => [['code' => 'NOT.AUTHORIZED', 'message' => 'Invalid token.']],
                ], 401);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-service-availability', $account), [
                'origin_location_id' => $location->id,
                'destination_country' => 'US',
                'destination_postal_code' => '75002',
                'destination_state' => 'TX',
                'destination_city' => 'Allen',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('fedex_test_result')
            ->assertSessionDoesntHaveErrors(['fedex']);

        $result = session('fedex_test_result');
        $this->assertFalse($result['success']);
        $this->assertSame('fedex_api', $result['failure_kind']);
        $this->assertStringContainsString('FedEx rejected the OAuth token for this request', $result['message']);
        $this->assertTrue((bool) data_get($result, 'request_summary.token_refreshed_after_401'));
        $this->assertTrue((bool) data_get($result, 'response_summary.token_refreshed_after_401'));
        $this->assertSame(401, data_get($result, 'response_summary.http_status'));

        $account->refresh();
        $this->assertSame(CarrierAccount::CONNECTION_CONNECTED, $account->connection_status);
        $this->assertSame(CarrierAccount::STATUS_ENABLED, $account->status);
    }

    public function test_merchant_token_cache_key_is_account_environment_and_client_specific(): void
    {
        Cache::flush();

        [$owner, $store, $accountA, $location] = $this->merchantFedExFixture('FedEx Token Cache Store A');
        $accountB = $this->secondMerchantFedExAccount($store, $location, 'fedex-token-cache-b');
        $oauthService = app(FedExMerchantCredentialsOAuthService::class);

        $this->assertNotSame(
            $oauthService->tokenCacheKey($accountA),
            $oauthService->tokenCacheKey($accountB),
        );

        $oauthAttempts = 0;

        Http::fake(function () use (&$oauthAttempts) {
            $oauthAttempts++;

            return Http::response([
                'access_token' => 'token-'.$oauthAttempts,
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200);
        });

        $oauthService->fetchTokenResult($accountA);
        $oauthService->fetchTokenResult($accountA);
        $oauthService->fetchTokenResult($accountB);
        $oauthService->fetchTokenResult($accountB);

        $this->assertSame(2, $oauthAttempts);
    }

    public function test_merchant_api_event_summaries_do_not_contain_secret_or_access_token(): void
    {
        [$owner, $store, $account, $location] = $this->merchantFedExFixture('FedEx Redacted Auth Summary Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => self::TEST_CLIENT_SECRET.'-access-token-value',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions' => Http::response([
                'transactionId' => 'fedex-service-txn-redacted',
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
                'destination_city' => 'Allen',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $events = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->whereIn('action', [
                CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
                CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY,
            ])
            ->get();

        $encoded = json_encode($events->pluck('request_summary')->merge($events->pluck('response_summary')));

        $this->assertStringNotContainsString(self::TEST_CLIENT_SECRET, $encoded);
        $this->assertStringNotContainsString(self::TEST_CLIENT_ID, $encoded);
        $this->assertStringNotContainsString(self::TEST_CLIENT_SECRET.'-access-token-value', $encoded);
        $this->assertStringNotContainsString(self::TEST_ACCOUNT_NUMBER, $encoded);
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
                'destination_city' => 'Allen',
                'weight_value' => 1,
                'length' => 9,
                'width' => 6,
                'height' => 2,
            ]);

        $response->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success')
            ->assertSessionHas('fedex_test_result');

        $result = $response->getSession()->get('fedex_test_result');
        $this->assertSame('fedex_authorization_blocked', $result['result_kind'] ?? null);
        $this->assertStringContainsString('FedEx authorization blocked', (string) ($result['message'] ?? ''));
        $this->assertStringContainsString('Comprehensive Rates', (string) ($result['message'] ?? ''));
        $this->assertNotEmpty($result['support_summary'] ?? null);
        $this->assertStringNotContainsString('Array to string conversion', (string) ($result['message'] ?? ''));

        $account->refresh();
        $this->assertSame(CarrierAccount::CONNECTION_CONNECTED, $account->connection_status);
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
                'destination_city' => 'Allen',
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
                'destination_city' => 'Allen',
                'service_type' => 'FEDEX_GROUND',
                'packaging_type' => 'YOUR_PACKAGING',
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

    private function secondMerchantFedExAccount(Store $store, Location $location, string $emailLocalPart): CarrierAccount
    {
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'Secondary FedEx account',
            'provider_account_number' => '740561073',
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
            'client_id' => 'm9z8y7x6w5v4u3t2s1r0q9p8o7n6m5l4',
            'client_secret' => 'secondary-merchant-fedex-secret-key-value',
        ]);
        $account->save();

        return $account;
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
            'carriers.fedex.model_b_developer_fallback_enabled' => true,
            'carriers.fedex.default_connection_model' => 'merchant_developer',
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
