<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Order;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Connection\FedExAccountRegistrationService;
use App\Services\Carriers\FedEx\Support\FedExCarrierProvider;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExSandboxCarrierFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExPlatform(true);
    }

    public function test_owner_can_create_fedex_sandbox_carrier_account(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Owner Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), $this->fedExPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->first();

        $this->assertNotNull($account);
        $this->assertSame('FedEx sandbox account', $account->display_name);
        $this->assertSame(CarrierAccount::CONNECTION_SETUP_REQUIRED, $account->connection_status);
        $this->assertSame('510087240', $account->provider_account_number);
    }

    public function test_staff_cannot_create_fedex_carrier_account(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Staff Block Store');
        $staff = $this->merchant('fedex-staff@example.test');
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), $this->fedExPayload())
            ->assertForbidden();

        $this->assertSame(0, CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->count());
    }

    public function test_cross_store_default_origin_location_is_rejected(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('FedEx Store A Origin');
        [, $storeB] = $this->ownerStore('FedEx Store B Origin');
        $otherLocation = Location::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Other warehouse',
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
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), array_merge($this->fedExPayload(), [
                'default_origin_location_id' => $otherLocation->id,
            ]))
            ->assertSessionHasErrors(['default_origin_location_id']);

        $this->assertSame(0, CarrierAccount::query()->where('store_id', $storeA->id)->where('provider', 'fedex')->count());
    }

    public function test_cross_store_carrier_account_test_connection_is_blocked(): void
    {
        [$ownerB, $storeB] = $this->ownerStore('FedEx Cross Test B');
        [, $storeA] = $this->ownerStore('FedEx Cross Test A');
        $this->attach($storeA, $ownerB, Store::ROLE_MANAGER);
        $account = $this->createFedExAccount($storeB);

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertForbidden();
    }

    public function test_fedex_credentials_are_encrypted_and_not_exposed(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Encrypt Store');
        $account = $this->createFedExAccount($store);

        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $array = $account->toArray();

        $this->assertArrayNotHasKey('credentials_encrypted', $array);
        $this->assertTrue($account->hasMerchantCredentials());
        $this->assertNotSame('sandbox-child-key', (string) ($array['customer_password'] ?? ''));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertDontSee('sandbox-child-key')
            ->assertDontSee('sandbox-child-password');
    }

    public function test_missing_platform_fedex_config_returns_friendly_message(): void
    {
        $this->configureFedExPlatform(false);
        [$owner, $store] = $this->ownerStore('FedEx Missing Config Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), $this->fedExPayload())
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('FedEx sandbox connection is not available on this platform environment yet');
    }

    public function test_fedex_account_registration_success_stores_encrypted_child_credentials(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Registration Store');
        $account = $this->createFedExAccount($store);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $credentials = $account->credentials();

        $this->assertSame('sandbox-child-key', $credentials['customer_key']);
        $this->assertSame('sandbox-child-password', $credentials['customer_password']);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => 'fedex',
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_fedex_oauth_token_success_marks_account_connected(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx OAuth Success Store');
        $account = $this->createFedExAccount($store, withCredentials: true);
        $this->fakeFedExOAuthOnly();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->isConnected());
        $this->assertNotNull($account->last_verified_at);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_fedex_oauth_failure_marks_account_failed_without_corrupting_order_state(): void
    {
        [$owner, $store, $order] = $this->orderFixture('FedEx Failure Store');
        $account = $this->createFedExAccount($store, withCredentials: true);

        Http::fake(function ($request) {
            if (! str_contains($request->url(), '/oauth/token')) {
                return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
            }

            if (str_contains((string) $request->body(), 'child_key')) {
                return Http::response([
                    'errors' => [['code' => 'AUTH.FAILED', 'message' => 'Invalid credentials']],
                ], 401);
            }

            return Http::response([
                'access_token' => 'fedex-test-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex']);

        $account->refresh();
        $order->refresh();

        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $account->connection_status);
        $this->assertSame(Order::query()->find($order->id)?->fulfillment_status, $order->fulfillment_status);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());
    }

    public function test_carrier_api_events_mask_sensitive_request_and_response_data(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Event Mask Store');
        $account = $this->createFedExAccount($store);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect();

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('/registration/v2/address/keysgeneration', data_get($event->request_summary, 'endpoint'));
        $this->assertSame(200, data_get($event->response_summary, 'http_status'));
        $this->assertStringNotContainsString('sandbox-child-password', json_encode($event->request_summary));
        $this->assertStringNotContainsString('sandbox-child-key', json_encode($event->response_summary));
        $this->assertStringNotContainsString('/irc/v2/customerkeys', json_encode($event->request_summary));
        $this->assertSame(9, data_get($event->request_summary, 'account_number_digits_len'));
        $this->assertSame('7240', data_get($event->request_summary, 'account_number_last4'));
        $this->assertStringNotContainsString('510087240', json_encode($event->request_summary));
    }

    public function test_invalid_account_number_fails_locally_before_fedex_api_call(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Invalid Account Local Fail');
        $account = $this->createFedExAccount($store);
        $account->update(['provider_account_number' => '12345']);

        $registrationCalled = false;

        Http::fake(function ($request) use (&$registrationCalled) {
            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $registrationCalled = true;

                return Http::response(['errors' => [['message' => 'Should not be called']]], 500);
            }

            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex']);

        $this->assertFalse($registrationCalled);

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->first();

        $this->assertNull($registrationEvent);

        $oauthEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN)
            ->first();

        $this->assertNotNull($oauthEvent);
        $this->assertSame(CarrierApiEvent::STATUS_SUCCEEDED, $oauthEvent->status);
    }

    public function test_empty_account_number_after_normalization_fails_locally_before_fedex_api_call(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Empty Account Local Fail');
        $account = $this->createFedExAccount($store);
        $account->update([
            'provider_account_number' => 'abc',
            'settings' => array_merge($account->settings ?? [], [
                'registration' => array_merge($account->registrationDetails(), [
                    'provider_account_number' => 'xyz',
                ]),
            ]),
        ]);

        $registrationCalled = false;

        Http::fake(function ($request) use (&$registrationCalled) {
            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $registrationCalled = true;

                return Http::response(['errors' => [['message' => 'Should not be called']]], 500);
            }

            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex']);

        $this->assertFalse($registrationCalled);
    }

    public function test_registration_uses_provider_account_number_as_root_account_number(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Root Account Number Store');
        $account = $this->createFedExAccount($store);
        $account->update(['provider_account_number' => '208851499']);

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'txn-registration-success-002',
                    'output' => [
                        'child_Key' => 'sandbox-child-key',
                        'child_secret' => 'sandbox-child-password',
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertIsArray($capturedPayload);
        $this->assertSame('208851499', data_get($capturedPayload, 'accountNumber.value'));
        $this->assertArrayHasKey('customerName', $capturedPayload);
        $this->assertArrayHasKey('address', $capturedPayload);
        $this->assertArrayNotHasKey('account_number', $capturedPayload);
        $this->assertArrayNotHasKey('provider_account_number', $capturedPayload);
        $this->assertStringNotContainsString('*', (string) data_get($capturedPayload, 'accountNumber.value'));
    }

    public function test_registration_falls_back_to_settings_registration_provider_account_number(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Settings Fallback Store');
        $account = $this->createFedExAccount($store);
        $account->update([
            'provider_account_number' => null,
            'settings' => [
                'registration' => array_merge($this->fedExPayload(), [
                    'provider_account_number' => '208-851-499',
                ]),
            ],
        ]);

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'txn-registration-success-003',
                    'output' => [
                        'child_Key' => 'sandbox-child-key',
                        'child_secret' => 'sandbox-child-password',
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('208851499', data_get($capturedPayload, 'accountNumber.value'));
    }

    public function test_fedex_http_400_with_valid_nine_digit_account_marks_failed_not_connected(): void
    {
        [$owner, $store, $order] = $this->orderFixture('FedEx HTTP 400 Store');
        $account = $this->createFedExAccount($store);
        $account->update(['provider_account_number' => '208851499']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'txn-registration-400-001',
                    'errors' => [[
                        'code' => 'ACCOUNT.NUMBER.INVALID',
                        'message' => 'Account Number cannot be invalid or null.',
                    ]],
                ], 400);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'fedex' => 'FedEx rejected the account registration details. Confirm the 9-digit account number, account owner name, and billing address exactly match FedEx records.',
            ])
            ->assertSessionHas('fedex_connection_message', 'FedEx platform credentials are valid, but account registration failed.');

        $account->refresh();
        $order->refresh();

        $this->assertFalse($account->isConnected());
        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $account->connection_status);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->first();

        $this->assertNotNull($registrationEvent);
        $this->assertSame(CarrierApiEvent::STATUS_FAILED, $registrationEvent->status);
        $this->assertSame(400, data_get($registrationEvent->response_summary, 'http_status'));
        $this->assertSame(9, data_get($registrationEvent->request_summary, 'account_number_digits_len'));
        $this->assertSame('1499', data_get($registrationEvent->request_summary, 'account_number_last4'));
        $this->assertStringNotContainsString('208851499', json_encode($registrationEvent->request_summary));
    }

    public function test_default_registration_endpoint_is_v2_not_deprecated_customerkeys(): void
    {
        $path = app(FedExConfig::class)->accountRegistrationPath(CarrierAccount::ENVIRONMENT_SANDBOX);

        $this->assertSame('/registration/v2/address/keysgeneration', $path);
        $this->assertStringNotContainsString('/irc/v2/customerkeys', $path);
        $this->assertStringNotContainsString('/registration/v1/', $path);
        $this->assertFalse(app(FedExConfig::class)->isDeprecatedRegistrationPath($path));
        $this->assertTrue(app(FedExConfig::class)->isDeprecatedRegistrationPath('/irc/v2/customerkeys'));
    }

    public function test_platform_oauth_success_is_logged_separately_from_registration(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Platform OAuth Log Store');
        $account = $this->createFedExAccount($store);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_registration_failure_after_platform_oauth_marks_failed_not_connected(): void
    {
        [$owner, $store, $order] = $this->orderFixture('FedEx Registration Fail Store');
        $account = $this->createFedExAccount($store);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'txn-registration-failed-001',
                    'errors' => [[
                        'code' => 'NOT.FOUND.ERROR',
                        'message' => 'The resource you requested is no longer available. Please modify your request and try again.',
                    ]],
                ], 404);
            }

            return Http::response(['errors' => [['message' => 'Unexpected FedEx URL in test']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex'])
            ->assertSessionHas('fedex_connection_message', 'FedEx platform credentials are valid, but account registration failed.');

        $account->refresh();
        $order->refresh();

        $this->assertFalse($account->isConnected());
        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $account->connection_status);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->first();

        $this->assertNotNull($registrationEvent);
        $this->assertSame(CarrierApiEvent::STATUS_FAILED, $registrationEvent->status);
        $this->assertSame('/registration/v2/address/keysgeneration', data_get($registrationEvent->request_summary, 'endpoint'));
        $this->assertSame(404, data_get($registrationEvent->response_summary, 'http_status'));
        $this->assertStringNotContainsString('fedex-test-access-token', json_encode($registrationEvent->response_summary));
    }

    public function test_ui_does_not_show_fake_label_live_rate_or_pickup_buttons(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx UI Store');
        $this->createFedExAccount($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('FedEx Merchant Account')
            ->assertSeeText('Sandbox')
            ->assertDontSeeText('Buy label')
            ->assertDontSeeText('Generate label')
            ->assertDontSeeText('Live rates')
            ->assertDontSeeText('Pickup scheduling')
            ->assertDontSeeText('Production enabled');
    }

    public function test_live_environment_cannot_be_enabled_in_this_phase(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Live Block Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), array_merge($this->fedExPayload(), [
                'environment' => 'live',
            ]))
            ->assertSessionHasErrors(['environment']);
    }

    public function test_residential_flag_is_saved_on_fedex_account_create(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Residential Save Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), array_merge($this->fedExPayload(), [
                'residential' => '1',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->firstOrFail();

        $this->assertTrue((bool) data_get($account->settings, 'registration.residential'));
    }

    public function test_default_residential_false_when_not_checked(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Residential Default Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), $this->fedExPayload())
            ->assertRedirect();

        $account = CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->firstOrFail();

        $this->assertFalse((bool) data_get($account->settings, 'registration.residential'));
    }

    public function test_default_registration_payload_omits_address_residential(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Omit Residential Store');
        $account = $this->createFedExAccount($store, residential: true);

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'txn-registration-omit-residential-001',
                    'output' => [
                        'child_Key' => 'sandbox-child-key',
                        'child_secret' => 'sandbox-child-password',
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertArrayNotHasKey('residential', $capturedPayload['address'] ?? []);
        $this->assertEqualsCanonicalizing(
            ['streetLines', 'city', 'stateOrProvinceCode', 'postalCode', 'countryCode'],
            array_keys($capturedPayload['address'] ?? [])
        );

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->firstOrFail();

        $this->assertTrue(data_get($registrationEvent->request_summary, 'residential_setting'));
        $this->assertFalse(data_get($registrationEvent->request_summary, 'residential_sent'));
        $this->assertSame('omit', data_get($registrationEvent->request_summary, 'residential_mode'));
        $this->assertNotContains('residential', data_get($registrationEvent->request_summary, 'address_keys', []));
    }

    public function test_diagnostic_boolean_mode_sends_address_residential_as_boolean(): void
    {
        config(['carriers.fedex.account_registration_residential_mode' => 'boolean']);

        [$owner, $store] = $this->ownerStore('FedEx Residential Boolean Mode Store');
        $account = $this->createFedExAccount($store, residential: true);

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'txn-registration-boolean-residential-001',
                    'output' => [
                        'child_Key' => 'sandbox-child-key',
                        'child_secret' => 'sandbox-child-password',
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect();

        $this->assertTrue($capturedPayload['address']['residential']);
        $this->assertIsBool($capturedPayload['address']['residential']);

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->firstOrFail();

        $this->assertTrue(data_get($registrationEvent->request_summary, 'residential_sent'));
        $this->assertSame('boolean', data_get($registrationEvent->request_summary, 'residential_mode'));
    }

    public function test_diagnostic_string_mode_sends_address_residential_as_string(): void
    {
        config(['carriers.fedex.account_registration_residential_mode' => 'string']);

        [$owner, $store] = $this->ownerStore('FedEx Residential String Mode Store');
        $account = $this->createFedExAccount($store, residential: true);

        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'txn-registration-string-residential-001',
                    'output' => [
                        'child_Key' => 'sandbox-child-key',
                        'child_secret' => 'sandbox-child-password',
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect();

        $this->assertSame('true', $capturedPayload['address']['residential']);
        $this->assertIsString($capturedPayload['address']['residential']);

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->firstOrFail();

        $this->assertTrue(data_get($registrationEvent->request_summary, 'residential_sent'));
        $this->assertSame('string', data_get($registrationEvent->request_summary, 'residential_mode'));
    }

    public function test_fedex_http_422_stores_sanitized_error_metadata(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx HTTP 422 Store');
        $account = $this->createFedExAccount($store);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'txn-registration-422-001',
                    'errors' => [[
                        'code' => 'INVALID.INPUT.EXCEPTION',
                        'message' => 'Invalid field value in the input',
                        'field' => 'address.residential',
                        'path' => '/address/residential',
                        'parameterList' => ['residential'],
                    ]],
                ], 422);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'fedex' => 'FedEx rejected Credential Registration. This account may require FedEx support or Integrator enablement.',
            ]);

        $account->refresh();
        $this->assertSame(CarrierAccount::CONNECTION_BLOCKED_BY_FEDEX, $account->connection_status);
        $this->assertFalse($account->isConnected());

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->firstOrFail();

        $this->assertSame(CarrierApiEvent::STATUS_FAILED, $registrationEvent->status);
        $this->assertSame(422, data_get($registrationEvent->response_summary, 'http_status'));
        $this->assertSame('INVALID.INPUT.EXCEPTION', data_get($registrationEvent->response_summary, 'errors.0.code'));
        $this->assertSame('Invalid field value in the input', data_get($registrationEvent->response_summary, 'errors.0.message'));
        $this->assertSame('address.residential', data_get($registrationEvent->response_summary, 'errors.0.field'));
        $this->assertSame('/address/residential', data_get($registrationEvent->response_summary, 'errors.0.path'));
        $this->assertSame(['residential'], data_get($registrationEvent->response_summary, 'errors.0.parameterList'));
        $this->assertStringNotContainsString('510087240', json_encode($registrationEvent->request_summary));
        $this->assertStringNotContainsString('fedex-test-access-token', json_encode($registrationEvent->response_summary));
    }

    public function test_fedex_debug_panel_and_export_only_appear_in_local_testing(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Debug Panel Store');
        $account = $this->createFedExAccount($store);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Legacy FedEx integrator registration diagnostic')
            ->assertSeeText('Export legacy registration diagnostic');

        $debugResponse = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.debug-payload', $account))
            ->assertOk()
            ->assertJsonFragment(['account_last4' => '7240', 'country_code' => 'US'])
            ->assertJsonStructure([
                'exported_at',
                'carrier',
                'endpoint',
                'account_last4',
                'country_code',
                'state_code',
                'postal_code',
                'note',
                'oauth_note',
            ]);

        $this->assertArrayNotHasKey('accountNumber', $debugResponse->json());
        $this->assertArrayNotHasKey('customerName', $debugResponse->json());
    }

    public function test_legacy_fedex_store_rejects_invalid_country_un(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Legacy UN Store');

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), array_merge($this->fedExPayload(), [
                'country_code' => 'UN',
            ]))
            ->assertSessionHasErrors(['country_code']);

        Http::assertNothingSent();
    }

    public function test_fedex_debug_payload_is_blocked_outside_local_testing(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Debug Block Store');
        $account = $this->createFedExAccount($store);

        $this->app['env'] = 'production';

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.debug-payload', $account))
            ->assertNotFound();
    }

    public function test_debug_registration_payload_helper_only_works_in_local_testing(): void
    {
        $account = $this->createFedExAccount($this->ownerStore('FedEx Debug Helper Store')[1], residential: true);
        $service = app(FedExAccountRegistrationService::class);

        $payload = $service->debugRegistrationPayload($account);

        $this->assertSame('510087240', data_get($payload, 'accountNumber.value'));
        $this->assertArrayNotHasKey('residential', $payload['address'] ?? []);

        $redacted = $service->redactedRegistrationPayload($account);
        $this->assertSame('*****7240', data_get($redacted, 'accountNumber.value'));

        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $service->debugRegistrationPayload($account);
    }

    public function test_existing_fedex_account_residential_setting_can_be_updated(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Residential Update Store');
        $account = $this->createFedExAccount($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.registration.update', $account), [
                'residential' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();

        $this->assertTrue((bool) data_get($account->settings, 'registration.residential'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Residential setting: true');
    }

    public function test_fedex_http_422_after_residential_omitted_marks_blocked_by_fedex_not_connected(): void
    {
        [$owner, $store, $order] = $this->orderFixture('FedEx 422 Omit Residential Store');
        $account = $this->createFedExAccount($store, residential: true);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'txn-registration-422-omit-001',
                    'errors' => [[
                        'code' => 'INVALID.INPUT.EXCEPTION',
                        'message' => 'Invalid field value in the input',
                    ]],
                ], 422);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex']);

        $account->refresh();
        $order->refresh();

        $this->assertFalse($account->isConnected());
        $this->assertSame(CarrierAccount::CONNECTION_BLOCKED_BY_FEDEX, $account->connection_status);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->firstOrFail();

        $this->assertFalse(data_get($registrationEvent->request_summary, 'residential_sent'));
        $this->assertSame('omit', data_get($registrationEvent->request_summary, 'residential_mode'));
    }

    public function test_production_cannot_enable_sandbox_platform_fallback(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Fallback Production Block');
        $account = $this->createFedExAccount($store);

        $this->app['env'] = 'production';
        config(['carriers.fedex.sandbox_allow_platform_fallback' => true]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.sandbox-platform-fallback', $account))
            ->assertNotFound();
    }

    public function test_local_testing_can_enable_sandbox_platform_fallback_when_env_flag_true(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Fallback Enable Store');
        $account = $this->createFedExAccount($store);

        config(['carriers.fedex.sandbox_allow_platform_fallback' => true]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.sandbox-platform-fallback', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();

        $this->assertTrue($account->usesSandboxPlatformFallback());
    }

    public function test_sandbox_platform_fallback_uses_platform_oauth_only_without_child_credentials(): void
    {
        config(['carriers.fedex.sandbox_allow_platform_fallback' => true]);

        [$owner, $store] = $this->ownerStore('FedEx Fallback OAuth Only Store');
        $account = $this->createFedExAccount($store);
        $account->update([
            'settings' => array_merge($account->settings ?? [], [
                'sandbox_platform_fallback' => true,
            ]),
        ]);

        $registrationCalled = false;

        Http::fake(function ($request) use (&$registrationCalled) {
            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $registrationCalled = true;

                return Http::response(['errors' => [['message' => 'Should not be called']]], 500);
            }

            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('fedex_connection_status', 'sandbox_platform_fallback');

        $this->assertFalse($registrationCalled);

        $account->refresh();

        $this->assertFalse($account->isConnected());
        $this->assertSame(CarrierAccount::CONNECTION_SANDBOX_PLATFORM_FALLBACK, $account->connection_status);
        $this->assertFalse($account->hasMerchantCredentials());
        $this->assertTrue(app(FedExCarrierProvider::class)->supportsRates($account));
        $this->assertDatabaseMissing('carrier_api_events', [
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
        ]);
    }

    public function test_sandbox_platform_fallback_is_not_merchant_owned_connected(): void
    {
        config(['carriers.fedex.sandbox_allow_platform_fallback' => true]);

        [$owner, $store] = $this->ownerStore('FedEx Fallback Not Connected Store');
        $account = $this->createFedExAccount($store, withCredentials: true);
        $account->update([
            'settings' => array_merge($account->settings ?? [], [
                'sandbox_platform_fallback' => true,
            ]),
        ]);

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-test-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();

        $this->assertFalse($account->isConnected());
        $this->assertTrue($account->isSandboxPlatformFallback());
        $this->assertFalse($account->hasMerchantCredentials());
    }

    public function test_manual_carrier_accounts_still_work(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Manual Still Works');
        $manual = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();
        $location = Location::query()->create([
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
                'carrier_id' => $manual->id,
                'display_name' => 'Main manual delivery',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('carrier_accounts', [
            'store_id' => $store->id,
            'display_name' => 'Main manual delivery',
            'provider' => CarrierAccount::PROVIDER_MANUAL,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
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

    /**
     * @return array{0: User, 1: Store, 2: Order}
     */
    private function orderFixture(string $storeName): array
    {
        [$owner, $store] = $this->ownerStore($storeName);
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#FX'.fake()->unique()->numberBetween(1000, 9999),
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

    private function createFedExAccount(Store $store, bool $withCredentials = false, bool $residential = false): CarrierAccount
    {
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'FedEx sandbox account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'provider_account_number' => '510087240',
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'settings' => [
                'registration' => [
                    'company_name' => 'Acme Test Co',
                    'contact_name' => 'Jane Merchant',
                    'address_line1' => '100 Test Lane',
                    'city' => 'Memphis',
                    'state' => 'TN',
                    'postal_code' => '38118',
                    'country_code' => 'US',
                    'phone' => '+19015550100',
                    'email' => 'fedex.test@example.test',
                    'provider_account_number' => '510087240',
                    'residential' => $residential,
                ],
            ],
            'enabled_for_checkout' => false,
        ]);

        if ($withCredentials) {
            $account->setCredentials([
                'customer_key' => 'sandbox-child-key',
                'customer_password' => 'sandbox-child-password',
            ]);
            $account->save();
        }

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    private function fedExPayload(): array
    {
        return [
            'display_name' => 'FedEx sandbox account',
            'environment' => 'sandbox',
            'provider_account_number' => '510087240',
            'company_name' => 'Acme Test Co',
            'contact_name' => 'Jane Merchant',
            'address_line1' => '100 Test Lane',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'phone' => '+19015550100',
            'email' => 'fedex.test@example.test',
        ];
    }

    private function configureFedExPlatform(bool $configured): void
    {
        config([
            'carriers.fedex.enabled' => $configured,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => $configured ? 'test-fedex-client-id' : '',
            'carriers.fedex.sandbox.client_secret' => $configured ? 'test-fedex-client-secret' : '',
            'carriers.fedex.sandbox.account_registration_path' => '/registration/v2/address/keysgeneration',
            'carriers.fedex.account_registration_residential_mode' => 'omit',
            'carriers.fedex.sandbox_allow_platform_fallback' => false,
        ]);
    }

    private function fakeFedExHappyPath(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'txn-registration-success-001',
                    'output' => [
                        'child_Key' => 'sandbox-child-key',
                        'child_secret' => 'sandbox-child-password',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected FedEx URL in test: '.$url]]], 500);
        });
    }

    private function fakeFedExOAuthOnly(): void
    {
        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-test-access-token',
                'token_type' => 'bearer',
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
