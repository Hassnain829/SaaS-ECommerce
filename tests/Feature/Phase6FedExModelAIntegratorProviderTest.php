<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class Phase6FedExModelAIntegratorProviderTest extends TestCase
{
    use RefreshDatabase;

    private const PLATFORM_CLIENT_ID = 'platform-fedex-client-id-test';

    private const PLATFORM_CLIENT_SECRET = 'platform-fedex-client-secret-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExModelA();
        $this->configureOfficialEula();
    }

    public function test_merchant_sees_fedex_model_a_connect_button_by_default(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Model A UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertOk()
            ->assertSeeText('Connect FedEx account')
            ->assertSee(route('settings.shipping.fedex-integrator.start'), false);
    }

    public function test_model_b_button_hidden_unless_developer_fallback_enabled(): void
    {
        config(['carriers.fedex.model_b_developer_fallback_enabled' => false]);
        [$owner, $store] = $this->ownerStore('FedEx Model B Hidden Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', 'fedex'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.start'));
    }

    public function test_origin_selection_creates_registration_session(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Origin Session Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.origin'), [
                'origin_location_id' => $location->id,
                'environment' => 'sandbox',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('carrier_account_registration_sessions', [
            'store_id' => $store->id,
            'origin_location_id' => $location->id,
            'status' => CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
        ]);
    }

    public function test_eula_acceptance_stores_user_date_and_version(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx EULA Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED);
        $this->completeEulaScroll($session);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.eula.accept', $session), [
                'read_and_accept_eula' => '1',
                'document_hash' => app(\App\Services\Carriers\FedEx\Connection\FedExEulaService::class)->hash(),
            ])
            ->assertRedirect(route('settings.shipping.fedex-integrator.account', $session));

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED, $session->status);
        $this->assertNotNull($session->eula_accepted_at);
        $this->assertSame($owner->id, $session->eula_accepted_by);
        $this->assertSame('FedEx Form No. 2002382 v 4 June 2024 Rev', $session->eula_version);
        $this->assertNotNull($session->eula_document_hash);
    }

    public function test_eula_page_requires_scroll_completion_before_acceptance(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Short EULA Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.eula', $session))
            ->assertOk()
            ->assertDontSee('initFedExEulaViewer', false)
            ->assertSee('data-fedex-eula-config', false)
            ->assertSee('I accept', false)
            ->assertSee('read_and_accept_eula', false)
            ->assertSee('Print / Save EULA evidence', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.eula.accept', $session), [
                'read_and_accept_eula' => '1',
                'document_hash' => app(\App\Services\Carriers\FedEx\Connection\FedExEulaService::class)->hash(),
            ])
            ->assertSessionHasErrors('eula');

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED, $session->status);
    }

    public function test_account_details_validation_rejects_invalid_account_number(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Invalid Account Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('12345'))
            ->assertSessionHasErrors(['provider_account_number']);
    }

    public function test_successful_mocked_registration_stores_encrypted_child_credentials_and_creates_account(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Registration Success Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'platform-parent-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/registration/v2/address/keysgeneration' => Http::response([
                'transactionId' => 'fedex-reg-txn-1',
                'output' => [
                    'child_Key' => 'child-key-value-123',
                    'childSecret' => 'child-secret-value-456',
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);
        $this->assertNotNull($session->carrier_account_id);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.success', $session))
            ->assertOk()
            ->assertSeeText('Direct Child Authorization completed')
            ->assertSeeText('PIN, SMS, phone, and invoice verification were skipped');

        $this->assertTrue(data_get($session->response_summary_json, 'credential_key_detected'));
        $this->assertTrue(data_get($session->response_summary_json, 'credential_secret_detected'));
        $this->assertFalse(data_get($session->response_summary_json, 'mfa_detected'));

        $account = CarrierAccount::query()->findOrFail($session->carrier_account_id);
        $this->assertTrue($account->usesFedExIntegratorProvider());
        $this->assertTrue($account->hasLegacyFedExChildCredentials());
        $this->assertSame('700257037', $account->provider_account_number);
        $this->assertStringNotContainsString('child-secret-value-456', json_encode($account->toArray()));
    }

    public function test_registered_session_without_credential_evidence_hides_direct_child_authorization(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx No Credential Evidence Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_REGISTERED);
        $session->forceFill([
            'mfa_method' => null,
            'response_summary_json' => [
                'credential_key_detected' => false,
                'credential_secret_detected' => false,
                'mfa_detected' => false,
            ],
        ])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.success', $session))
            ->assertOk()
            ->assertDontSeeText('Direct Child Authorization completed');
    }

    public function test_registration_payload_uses_nested_account_number_value_shape(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Registration Payload Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'platform-parent-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'fedex-reg-payload-txn-1',
                    'output' => [
                        'child_Key' => 'child-key-value-123',
                        'childSecret' => 'child-secret-value-456',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $payload = array_merge($this->accountPayload('700257037'), [
            'city' => 'new york',
            'state' => 'ny',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $payload)
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $this->assertIsArray($capturedPayload);
        $this->assertSame('RTC Test Company', $capturedPayload['customerName'] ?? null);
        $this->assertSame(['value' => '700257037'], $capturedPayload['accountNumber'] ?? null);
        $this->assertSame(['15 W 18TH ST FL 7'], $capturedPayload['address']['streetLines'] ?? null);
        $this->assertSame('NEW YORK', $capturedPayload['address']['city'] ?? null);
        $this->assertSame('NY', $capturedPayload['address']['stateOrProvinceCode'] ?? null);
        $this->assertSame('100114624', $capturedPayload['address']['postalCode'] ?? null);
        $this->assertSame('US', $capturedPayload['address']['countryCode'] ?? null);
        $this->assertArrayNotHasKey('residential', $capturedPayload['address'] ?? []);

        $session->refresh();
        $summaryJson = json_encode($session->request_summary_json ?? []);
        $this->assertStringNotContainsString('700257037', $summaryJson);
        $this->assertStringNotContainsString('merchant@example.test', $summaryJson);
        $this->assertStringNotContainsString('9012633035', $summaryJson);
        $this->assertStringNotContainsString('platform-parent-token', $summaryJson);
        $this->assertStringNotContainsString('child-secret-value-456', $summaryJson);
        $this->assertSame('object_value', data_get($session->request_summary_json, 'account_number_shape'));
        $this->assertSame('7037', data_get($session->request_summary_json, 'account_number_last4'));
        $this->assertSame('10011-4624', data_get($session->request_summary_json, 'postal_code_input'));
        $this->assertSame('100114624', data_get($session->request_summary_json, 'postal_code_sent'));
        $this->assertSame(9, data_get($session->request_summary_json, 'postal_code_digits_len'));
        $this->assertFalse(data_get($session->request_summary_json, 'residential_sent'));
        $this->assertSame('omit', data_get($session->request_summary_json, 'residential_mode'));
    }

    public function test_registration_payload_preserves_nine_digit_postal_from_hyphenated_input(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Postal Hyphen Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'fedex-reg-postal-hyphen',
                    'output' => ['child_Key' => 'child-key', 'childSecret' => 'child-secret'],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $payload = array_merge($this->accountPayload('700257037'), [
            'company_name' => 'Unique Customer Name',
            'postal_code' => '10011-4624',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $payload)
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $this->assertSame('Unique Customer Name', $capturedPayload['customerName'] ?? null);
        $this->assertSame('100114624', $capturedPayload['address']['postalCode'] ?? null);
    }

    public function test_registration_validator_preserves_raw_nine_digit_postal_for_registration(): void
    {
        $validator = app(\App\Services\Carriers\FedEx\Connection\FedExRegistrationInputValidator::class);
        $result = $validator->validate([
            'provider_account_number' => '700257037',
            'company_name' => 'Unique Customer Name',
            'address_line1' => '15 W 18TH ST FL 7',
            'city' => 'NEW YORK',
            'state' => 'NY',
            'postal_code' => '100114624',
            'country_code' => 'US',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('10011-4624', $result['normalized']['postal_code']);
        $this->assertSame('100114624', $result['normalized']['registration_postal_code_raw']);
    }

    public function test_model_a_connection_check_uses_child_credential_oauth_not_merchant_developer_credentials(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Child OAuth Store');
        $oauthMode = null;

        Http::fake(function ($request) use (&$oauthMode) {
            if (str_contains($request->url(), '/oauth/token')) {
                $body = $request->data();
                $oauthMode = $body['grant_type'] ?? null;

                return Http::response([
                    'access_token' => 'child-oauth-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect();

        $this->assertSame('csp_credentials', $oauthMode);
    }

    public function test_registration_failed_state_shows_transaction_id_in_session_summary(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Registration Failed Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'platform-parent-token',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/registration/v2/address/keysgeneration' => Http::response([
                'transactionId' => 'fedex-reg-txn-fail',
                'errors' => [['code' => 'INVALID.INPUT.EXCEPTION', 'message' => 'Rejected']],
            ], 422),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.account', $session))
            ->assertSessionHas('_old_input.provider_account_number', '700257037')
            ->assertSessionHas('_old_input.company_name', 'RTC Test Company')
            ->assertSessionHas('_old_input.postal_code', '100114624');

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_FAILED, $session->status);
        $this->assertSame('fedex-reg-txn-fail', $session->fedex_transaction_id);
        $this->assertSame(
            \App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE,
            $session->last_error_message,
        );
        $this->assertStringContainsString(
            'match your FedEx account records',
            (string) data_get($session->response_summary_json, 'technical_error_message'),
        );
    }

    public function test_registration_mfa_response_moves_session_to_method_selection(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx MFA Required Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'platform-parent-token',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/registration/v2/address/keysgeneration' => Http::response(
                $this->mfaRegistrationResponse(),
                200
            ),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.mfa', $session));

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_MFA_METHOD_REQUIRED, $session->status);
        $this->assertNull($session->last_error_code);
        $this->assertNull($session->last_error_message);
        $this->assertSame('fedex-reg-mfa-txn-1', $session->fedex_transaction_id);
        $this->assertNotEmpty($session->mfa_options_json);
        $this->assertTrue($session->hasAccountAuthToken());
        $this->assertSame('fedex-account-auth-token-test', $session->accountAuthToken());
        $this->assertSame('***-***-3021', $session->mfa_destination_masked);
        $this->assertEqualsCanonicalizing(
            ['sms', 'email', 'call', 'invoice'],
            array_column($session->mfa_options_json ?? [], 'raw_key'),
        );
        $this->assertTrue(data_get($session->response_summary_json, 'mfa_detected'));
        $this->assertTrue(data_get($session->response_summary_json, 'account_auth_token_detected'));
        $this->assertFalse(data_get($session->response_summary_json, 'credential_key_detected'));
        $this->assertFalse(data_get($session->response_summary_json, 'credential_secret_detected'));

        $rawToken = DB::table('carrier_account_registration_sessions')
            ->where('id', $session->id)
            ->value('fedex_account_auth_token_encrypted');
        $this->assertNotSame('fedex-account-auth-token-test', $rawToken);

        $summaryJson = json_encode($session->response_summary_json);
        $this->assertStringNotContainsString('700257037', $summaryJson);
        $this->assertStringNotContainsString('fedex-account-auth-token-test', $summaryJson);
    }

    public function test_credential_registration_sandbox_mfa_shape_parses_secure_code_and_invoice(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Sandbox MFA Shape Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'platform-parent-token',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/registration/v2/address/keysgeneration' => Http::response(
                $this->mfaRegistrationResponse(),
                200
            ),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.mfa', $session));

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_MFA_METHOD_REQUIRED, $session->status);
        $this->assertNotSame('account_auth_token_missing', $session->last_error_code);
        $this->assertNull($session->last_error_message);

        $methods = collect($session->mfa_options_json ?? [])->keyBy('raw_key');
        $this->assertTrue($methods->has('sms'));
        $this->assertTrue($methods->has('email'));
        $this->assertTrue($methods->has('call'));
        $this->assertTrue($methods->has('invoice'));
        $this->assertSame('SMS', $methods->get('sms')['method']);
        $this->assertSame('EMAIL', $methods->get('email')['method']);
        $this->assertSame('PHONE', $methods->get('call')['method']);
        $this->assertSame('INVOICE', $methods->get('invoice')['method']);
        $this->assertSame('***-***-3021', $methods->get('sms')['destination_masked']);
    }

    public function test_registration_mfa_without_account_auth_token_fails_controlled(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx MFA Missing Token Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'platform-parent-token',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/registration/v2/address/keysgeneration' => Http::response([
                'transactionId' => 'fedex-reg-mfa-no-token',
                'output' => [
                    'mfaOptions' => [
                        [
                            'mfaRequired' => true,
                            'phoneNumber' => '***-***-3021',
                            'options' => [
                                'secureCode' => ['SMS', 'EMAIL'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.account', $session))
            ->assertSessionHasErrors(['registration']);

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_FAILED, $session->status);
        $this->assertSame('account_auth_token_missing', $session->last_error_code);
        $this->assertSame(
            \App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE,
            $session->last_error_message,
        );
        $this->assertStringContainsString(
            'accountAuthToken',
            (string) data_get($session->response_summary_json, 'technical_error_message'),
        );
    }

    public function test_pin_generation_includes_account_auth_token_header(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx PIN Generation Header Store');
        $session = $this->createMfaReadySession($store, $owner, $location);
        $pinGenerationHeaders = null;

        Http::fake(function ($request) use (&$pinGenerationHeaders) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/customerkeys/pingeneration')) {
                $pinGenerationHeaders = $request->headers();

                return Http::response(['transactionId' => 'fedex-pin-gen-txn-1'], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->configureMfaPaths();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.mfa-method', $session), ['mfa_method' => 'email'])
            ->assertRedirect(route('settings.shipping.fedex-integrator.mfa', $session));

        $this->assertNotNull($pinGenerationHeaders);
        $this->assertSame('fedex-account-auth-token-test', $pinGenerationHeaders['accountAuthToken'][0] ?? null);
        $this->assertStringContainsString('Bearer', $pinGenerationHeaders['Authorization'][0] ?? '');
    }

    public function test_pin_validation_includes_account_auth_token_header(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx PIN Validation Header Store');
        $session = $this->createMfaReadySession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_PIN_PENDING, 'email');
        $pinValidationHeaders = null;

        Http::fake(function ($request) use (&$pinValidationHeaders) {
            if (str_contains($request->url(), '/oauth/token')) {
                $grantType = $request->data()['grant_type'] ?? null;

                if ($grantType === 'csp_credentials') {
                    return Http::response(['access_token' => 'child-oauth-token', 'expires_in' => 3600], 200);
                }

                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/pin/keysgeneration')) {
                $pinValidationHeaders = $request->headers();

                return Http::response([
                    'transactionId' => 'fedex-pin-validate-txn-1',
                    'output' => [
                        'childKey' => 'child-key-after-pin',
                        'childSecret' => 'child-secret-after-pin',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->configureMfaPaths();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.verify-pin', $session), ['pin' => '123456'])
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $this->assertNotNull($pinValidationHeaders);
        $this->assertSame('fedex-account-auth-token-test', $pinValidationHeaders['accountAuthToken'][0] ?? null);

        $session->refresh();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.success', $session))
            ->assertOk()
            ->assertDontSeeText('Direct Child Authorization completed');
    }

    public function test_invoice_validation_includes_account_auth_token_header(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Invoice Validation Header Store');
        $session = $this->createMfaReadySession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_INVOICE_PENDING, 'invoice');
        $invoiceValidationHeaders = null;

        Http::fake(function ($request) use (&$invoiceValidationHeaders) {
            if (str_contains($request->url(), '/oauth/token')) {
                $grantType = $request->data()['grant_type'] ?? null;

                if ($grantType === 'csp_credentials') {
                    return Http::response(['access_token' => 'child-oauth-token', 'expires_in' => 3600], 200);
                }

                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/invoice/keysgeneration')) {
                $invoiceValidationHeaders = $request->headers();

                return Http::response([
                    'transactionId' => 'fedex-invoice-validate-txn-1',
                    'output' => [
                        'childKey' => 'child-key-after-invoice',
                        'childSecret' => 'child-secret-after-invoice',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->configureMfaPaths();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.verify-invoice', $session), [
                'invoice_number' => 'INV-1001',
                'invoice_date' => '2026-01-15',
                'invoice_currency' => 'USD',
                'invoice_amount' => '125.50',
            ])
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $this->assertNotNull($invoiceValidationHeaders);
        $this->assertSame('fedex-account-auth-token-test', $invoiceValidationHeaders['accountAuthToken'][0] ?? null);
    }

    public function test_failed_session_cannot_continue_mfa_with_friendly_redirect(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Failed MFA Session Store');
        $session = $this->createMfaReadySession($store, $owner, $location);
        $session->forceFill([
            'status' => CarrierAccountRegistrationSession::STATUS_FAILED,
            'last_error_message' => 'FedEx registration failed earlier.',
            'last_error_code' => 'registration_failed',
        ])->save();

        $this->configureMfaPaths();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.mfa-method', $session), ['mfa_method' => 'email'])
            ->assertRedirect(route('settings.shipping.fedex-integrator.account', $session))
            ->assertSessionHasErrors(['registration']);

        Http::assertNothingSent();
    }

    public function test_registration_nested_credentials_complete_without_mfa(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Nested Credentials Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                $grantType = $request->data()['grant_type'] ?? null;

                if ($grantType === 'csp_credentials') {
                    return Http::response([
                        'access_token' => 'child-oauth-token',
                        'expires_in' => 3600,
                    ], 200);
                }

                return Http::response([
                    'access_token' => 'platform-parent-token',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'fedex-reg-nested-txn-1',
                    'output' => [
                        'credentials' => [
                            'childKey' => 'nested-child-key',
                            'customerSecret' => 'nested-child-secret',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);
        $this->assertTrue(data_get($session->response_summary_json, 'credential_key_detected'));
        $this->assertTrue(data_get($session->response_summary_json, 'credential_secret_detected'));
        $this->assertFalse(data_get($session->response_summary_json, 'mfa_detected'));
        $this->assertStringNotContainsString('nested-child-secret', json_encode($session->response_summary_json));
    }

    public function test_registration_without_credentials_or_mfa_uses_incomplete_message(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Incomplete Registration Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'platform-parent-token',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/registration/v2/address/keysgeneration' => Http::response([
                'transactionId' => 'fedex-reg-empty-output',
                'output' => ['status' => 'PENDING'],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.account', $session));

        $session->refresh();
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_FAILED, $session->status);
        $this->assertSame('registration_incomplete', $session->last_error_code);
        $this->assertSame(
            \App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE,
            $session->last_error_message,
        );
        $this->assertStringContainsString(
            'did not return child credentials or MFA options',
            (string) data_get($session->response_summary_json, 'technical_error_message'),
        );
    }

    public function test_store_isolation_blocks_foreign_registration_session(): void
    {
        [$ownerA, $storeA, $locationA] = $this->fixtureParts('Store A FedEx Model A');
        [$ownerB, $storeB] = $this->ownerStore('Store B FedEx Model A');
        $session = $this->createSession($storeA, $ownerA, $locationA, CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED);

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeB->id])
            ->get(route('settings.shipping.fedex-integrator.eula', $session))
            ->assertNotFound();
    }

    public function test_cancelled_session_cannot_be_completed(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Cancel Session Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $session->forceFill(['status' => CarrierAccountRegistrationSession::STATUS_CANCELLED])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertStatus(410);
    }

    public function test_fedex_validation_evidence_export_creates_zip_with_redacted_json(): void
    {
        config(['carriers.fedex.validation_mode_enabled' => true]);
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Export Store');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation-export', $account));

        $response->assertOk();
        $zipPath = $response->baseResponse->getFile()->getPathname();
        $this->assertTrue(File::exists($zipPath));

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $readme = $zip->getFromName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/README.md');
        $preflight = $zip->getFromName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/preflight-report.json');
        $zip->close();

        $this->assertStringContainsString('FedEx Integrator Validation Evidence Bundle', (string) $readme);
        $this->assertStringContainsString('INCOMPLETE', (string) $readme);
        $this->assertStringNotContainsString('child-secret', (string) $readme);
        $this->assertSame('1.0', json_decode((string) $preflight, true)['schema_version'] ?? null);
    }

    public function test_live_mode_disabled_unless_production_flag_and_credentials_exist(): void
    {
        $config = app(\App\Services\Carriers\FedEx\Support\FedExConfig::class);
        config([
            'carriers.fedex.integrator_production_enabled' => false,
            'carriers.fedex.live.client_id' => '',
            'carriers.fedex.live.client_secret' => '',
        ]);

        $this->assertFalse($config->productionEnabled());
        $this->assertFalse($config->allowsIntegratorEnvironment('live'));
    }

    public function test_child_token_cache_key_is_account_specific(): void
    {
        [$owner, $store, $accountA] = $this->integratorAccountFixture('FedEx Cache A');
        $accountB = $this->secondIntegratorAccount($store, 'child-key-b', 'child-secret-b');

        $service = app(FedExIntegratorChildOAuthService::class);
        $this->assertNotSame($service->tokenCacheKey($accountA), $service->tokenCacheKey($accountB));
    }

    public function test_existing_usps_sandbox_tools_still_render(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Still Renders Store');
        config(['carriers.usps.enabled' => true, 'carriers.usps.consumer_key' => 'usps-key', 'carriers.usps.consumer_secret' => 'usps-secret']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertOk()
            ->assertSeeText('USPS Merchant Account');
    }

    /**
     * @return array<string, string>
     */
    private function accountPayload(string $accountNumber): array
    {
        return [
            'provider_account_number' => $accountNumber,
            'company_name' => 'RTC Test Company',
            'contact_name' => 'James Weston',
            'email' => 'merchant@example.test',
            'phone' => '9012633035',
            'address_line1' => '15 W 18TH ST FL 7',
            'city' => 'NEW YORK',
            'state' => 'NY',
            'postal_code' => '100114624',
            'country_code' => 'US',
        ];
    }

    private function configureFedExModelA(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.default_connection_model' => 'integrator_provider',
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.developer_mode_enabled' => false,
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => self::PLATFORM_CLIENT_ID,
            'carriers.fedex.sandbox.client_secret' => self::PLATFORM_CLIENT_SECRET,
        ]);
    }

    private function configureOfficialEula(): void
    {
        $path = base_path('resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf');

        config([
            'carriers.fedex.integrator_eula_path' => 'resources/legal/fedex/FedEx_Standard_End_User_License_Agreement_EULA_for_Hosted_3rd_party_solutions.pdf',
            'carriers.fedex.integrator_eula_version' => 'FedEx Form No. 2002382 v 4 June 2024 Rev',
            'carriers.fedex.integrator_eula_form_number' => '2002382',
            'carriers.fedex.integrator_eula_expected_pages' => 11,
            'carriers.fedex.integrator_eula_sha256' => is_file($path) ? hash_file('sha256', $path) : str_repeat('a', 64),
        ]);
    }

    private function completeEulaScroll(CarrierAccountRegistrationSession $session): void
    {
        app(\App\Services\Carriers\FedEx\Connection\FedExIntegratorRegistrationOrchestrator::class)->markEulaScrollComplete(
            $session,
            app(\App\Services\Carriers\FedEx\Connection\FedExEulaService::class)->hash(),
            app(\App\Services\Carriers\FedEx\Connection\FedExEulaService::class)->expectedPages(),
        );
    }

    private function configureMfaPaths(): void
    {
        config([
            'carriers.fedex.mfa_pin_generation_path' => '/registration/v2/customerkeys/pingeneration',
            'carriers.fedex.mfa_pin_validation_path' => '/registration/v2/pin/keysgeneration',
            'carriers.fedex.mfa_invoice_validation_path' => '/registration/v2/invoice/keysgeneration',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mfaRegistrationResponse(): array
    {
        return [
            'transactionId' => 'fedex-reg-mfa-txn-1',
            'output' => [
                'mfaOptions' => [
                    [
                        'accountAuthToken' => 'fedex-account-auth-token-test',
                        'mfaRequired' => true,
                        'phoneNumber' => '***-***-3021',
                        'options' => [
                            'invoice' => 'INVOICE',
                            'secureCode' => ['SMS', 'EMAIL', 'CALL'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createMfaReadySession(
        Store $store,
        User $owner,
        Location $location,
        string $status = CarrierAccountRegistrationSession::STATUS_MFA_METHOD_REQUIRED,
        ?string $mfaMethod = null,
    ): CarrierAccountRegistrationSession {
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $session->setAccountNumber('700257037');
        $session->setAccountAuthToken('fedex-account-auth-token-test');
        $session->forceFill([
            'account_name' => 'RTC Test Company',
            'registration_address_json' => $this->accountPayload('700257037'),
            'fedex_transaction_id' => 'fedex-reg-mfa-txn-1',
            'status' => $status,
            'mfa_method' => $mfaMethod,
            'mfa_options_json' => [
                ['method' => 'EMAIL', 'label' => 'Email PIN', 'destination_masked' => '***-***-3021', 'raw_key' => 'email'],
                ['method' => 'SMS', 'label' => 'SMS PIN', 'destination_masked' => '***-***-3021', 'raw_key' => 'sms'],
                ['method' => 'PHONE', 'label' => 'Phone call PIN', 'destination_masked' => '***-***-3021', 'raw_key' => 'call'],
                ['method' => 'INVOICE', 'label' => 'Invoice verification', 'destination_masked' => null, 'raw_key' => 'invoice'],
            ],
            'mfa_destination_masked' => '***-***-3021',
        ])->save();

        return $session->refresh();
    }

    /**
     * @return array{0: User, 1: Store, 2: Location}
     */
    private function fixtureParts(string $name): array
    {
        [$owner, $store] = $this->ownerStore($name);
        $location = $this->readyLocation($store);

        return [$owner, $store, $location];
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function integratorAccountFixture(string $name): array
    {
        [$owner, $store, $location] = $this->fixtureParts($name);
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx integrator account',
            'provider_account_number' => '700257037',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
            'settings' => ['default_origin_location_id' => $location->id],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $account->setCredentials([
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
        ]);
        $account->save();

        return [$owner, $store, $account];
    }

    private function secondIntegratorAccount(Store $store, string $key, string $secret): CarrierAccount
    {
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'Second FedEx integrator account',
            'provider_account_number' => '740561073',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
        $account->setCredentials(['customer_key' => $key, 'customer_password' => $secret]);
        $account->save();

        return $account;
    }

    private function createSession(
        Store $store,
        User $owner,
        Location $location,
        string $status,
    ): CarrierAccountRegistrationSession {
        return CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'status' => $status,
            'origin_location_id' => $location->id,
            'eula_version' => 'FedEx Form No. 2002382 v 4 June 2024 Rev',
            'created_by' => $owner->id,
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
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        return [$owner, $store];
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }
}
