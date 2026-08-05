<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Connection\FedExIntegratorRegistrationOrchestrator;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class Phase6FedExConnectionSecurityIdempotencyTest extends TestCase
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

    public function test_repeated_direct_registration_completion_creates_one_carrier_account(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Idempotent Direct Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        $this->fakeSuccessfulRegistrationHttp();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload())
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $session->refresh();
        $firstAccountId = $session->carrier_account_id;
        $this->assertNotNull($firstAccountId);
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);

        // Re-enter finalization with the same child-credential success payload.
        $result = CarrierApiResult::success(
            data: [
                'output' => [
                    'child_Key' => 'child-key-value-123',
                    'childSecret' => 'child-secret-value-456',
                ],
            ],
            requestSummary: ['endpoint' => '/registration/v2/address/keysgeneration'],
            responseSummary: [
                'fedex_transaction_id' => 'fedex-reg-txn-replay',
                'credential_key_detected' => true,
                'credential_secret_detected' => true,
            ],
        );

        $account = $this->invokeFinalize($session, $result);

        $this->assertSame($firstAccountId, $account->id);
        $this->assertSame(1, CarrierAccount::query()->where('registration_session_id', $session->id)->count());
        $this->assertSame(1, CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->count());
    }

    public function test_repeated_mfa_completion_creates_one_carrier_account(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Idempotent MFA Store');
        $session = $this->createMfaReadySession(
            $store,
            $owner,
            $location,
            CarrierAccountRegistrationSession::STATUS_PIN_PENDING,
            'email'
        );

        $this->configureMfaPaths();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                $grantType = $request->data()['grant_type'] ?? null;
                if ($grantType === 'csp_credentials') {
                    return Http::response(['access_token' => 'child-oauth-token', 'expires_in' => 3600], 200);
                }

                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/pin/keysgeneration')) {
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

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.verify-pin', $session), ['pin' => '123456'])
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $session->refresh();
        $firstAccountId = $session->carrier_account_id;

        $result = CarrierApiResult::success(
            data: [
                'output' => [
                    'childKey' => 'child-key-after-pin',
                    'childSecret' => 'child-secret-after-pin',
                ],
            ],
            requestSummary: ['endpoint' => '/registration/v2/pin/keysgeneration'],
            responseSummary: [
                'fedex_transaction_id' => 'fedex-pin-validate-txn-replay',
                'credential_key_detected' => true,
                'credential_secret_detected' => true,
            ],
        );

        $account = $this->invokeFinalize($session, $result);

        $this->assertSame($firstAccountId, $account->id);
        $this->assertSame(1, CarrierAccount::query()->where('registration_session_id', $session->id)->count());
    }

    public function test_registration_session_id_has_unique_database_constraint(): void
    {
        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'registration_session_id'));

        [$owner, $store, $location] = $this->fixtureParts('FedEx Unique Session Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_CREDENTIALS_ISSUED);
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $first = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'First',
            'registration_session_id' => $session->id,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'status' => CarrierAccount::STATUS_ENABLED,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
        $first->setFedExAccountNumber('700257037');
        $first->save();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'Second',
            'registration_session_id' => $session->id,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'status' => CarrierAccount::STATUS_ENABLED,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
    }

    public function test_credentials_issued_is_durable_before_oauth_and_oauth_runs_outside_transaction(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Durable Credentials Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $session->setAccountNumber('700257037');
        $session->forceFill([
            'account_name' => 'Durable Account',
            'eula_accepted_at' => now(),
            'eula_accepted_by' => $owner->id,
            'eula_document_hash' => 'hash',
            'registration_address_json' => $this->registrationAddress(),
        ])->save();

        $child = [
            'customer_key' => 'child-key-durable',
            'customer_password' => 'child-secret-durable',
        ];
        $result = CarrierApiResult::success(
            data: [
                'output' => [
                    'child_Key' => $child['customer_key'],
                    'childSecret' => $child['customer_password'],
                ],
            ],
            requestSummary: ['endpoint' => '/registration/v2/address/keysgeneration'],
            responseSummary: [
                'fedex_transaction_id' => 'durable-txn',
                'credential_key_detected' => true,
                'credential_secret_detected' => true,
            ],
        );

        $accountAfterA = $this->invokePersistIssuedChildCredentials($session, $result, $child);
        $session->refresh();

        $this->assertSame(
            CarrierAccountRegistrationSession::STATUS_CREDENTIALS_ISSUED,
            $session->status
        );
        $this->assertTrue($accountAfterA->hasLegacyFedExChildCredentials());
        $this->assertNull($accountAfterA->fedex_active_store_key);
        $ambientTransactionLevel = DB::transactionLevel();

        $observedTransactionLevel = null;
        $this->mock(FedExIntegratorChildOAuthService::class, function ($mock) use (&$observedTransactionLevel) {
            $mock->shouldReceive('fetchTokenResult')
                ->once()
                ->andReturnUsing(function () use (&$observedTransactionLevel) {
                    $observedTransactionLevel = DB::transactionLevel();

                    return CarrierApiResult::success(
                        data: ['access_token' => 'child-token', 'expires_in' => 3600],
                        requestSummary: ['credentials_mode' => 'integrator_child'],
                        responseSummary: ['http_status' => 200],
                    );
                });
        });

        $account = $this->invokeFinalize($session->fresh(), $result);

        $this->assertSame(
            $ambientTransactionLevel,
            $observedTransactionLevel,
            'Child OAuth must not run inside an additional database transaction'
        );
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->fresh()->status);
        $this->assertTrue($account->isConnected());
        $this->assertSame($accountAfterA->id, $account->id);
    }

    public function test_crash_after_credentials_persistence_leaves_recoverable_state(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Crash Recovery Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $session->setAccountNumber('700257037');
        $session->forceFill([
            'account_name' => 'Crash Account',
            'eula_accepted_at' => now(),
            'eula_accepted_by' => $owner->id,
            'eula_document_hash' => 'hash',
            'registration_address_json' => $this->registrationAddress(),
        ])->save();

        $this->mock(FedExIntegratorChildOAuthService::class, function ($mock) {
            $mock->shouldReceive('fetchTokenResult')
                ->once()
                ->andThrow(new \RuntimeException('simulated process crash during child OAuth'));
        });

        $result = CarrierApiResult::success(
            data: [
                'output' => [
                    'child_Key' => 'child-key-crash',
                    'childSecret' => 'child-secret-crash',
                ],
            ],
            requestSummary: ['endpoint' => '/registration/v2/address/keysgeneration'],
            responseSummary: [
                'fedex_transaction_id' => 'crash-txn',
                'credential_key_detected' => true,
                'credential_secret_detected' => true,
            ],
        );

        try {
            $this->invokeFinalize($session, $result);
            $this->fail('Expected crash exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated process crash during child OAuth', $e->getMessage());
        }

        $session->refresh();
        $account = CarrierAccount::query()->findOrFail($session->carrier_account_id);

        $this->assertSame(
            CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_VERIFYING,
            $session->status
        );
        $this->assertNull($session->completed_at);
        $this->assertSame('crash-txn', $session->fedex_transaction_id);
        $this->assertTrue($account->hasLegacyFedExChildCredentials());
        $this->assertNull($account->fedex_active_store_key);
        $this->assertSame('700257037', $account->fedExAccountNumber());
    }

    public function test_oauth_success_activates_exactly_one_account_with_active_store_key(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Activate One Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        $this->fakeSuccessfulRegistrationHttp();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload())
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $session->refresh();
        $account = CarrierAccount::query()->findOrFail($session->carrier_account_id);
        $expectedKey = CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, CarrierAccount::ENVIRONMENT_SANDBOX);

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertTrue($account->isConnected());
        $this->assertSame($expectedKey, $account->fedex_active_store_key);
        $this->assertSame(1, CarrierAccount::query()->where('fedex_active_store_key', $expectedKey)->count());
    }

    public function test_oauth_failure_leaves_no_active_store_key(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx OAuth Fail Key Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                if (($request->data()['grant_type'] ?? null) === 'csp_credentials') {
                    return Http::response([
                        'errors' => [['code' => 'INVALID.CREDENTIALS', 'message' => 'Child authorization failed']],
                    ], 401);
                }

                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'oauth-fail-txn',
                    'output' => [
                        'childKey' => 'issued-child-key',
                        'childSecret' => 'issued-child-secret',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload())
            ->assertSessionHasErrors('registration');

        $session->refresh();
        $account = CarrierAccount::query()->findOrFail($session->carrier_account_id);

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED, $session->status);
        $this->assertNull($account->fedex_active_store_key);
        $this->assertNull($session->completed_at);
        $this->assertNotNull($session->fedex_transaction_id);
        $this->assertNotNull($session->response_summary_json);
    }

    public function test_existing_active_account_is_not_replaced(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx No Replace Store');
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $existing = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'Existing active',
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
        $existing->setFedExAccountNumber('111111111');
        $existing->setCredentials(['customer_key' => 'existing-key', 'customer_password' => 'existing-secret']);
        $existing->assignFedExActiveStoreKey();
        $existing->save();

        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $this->fakeSuccessfulRegistrationHttp();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload())
            ->assertSessionHasErrors('registration');

        $session->refresh();
        $newAccount = CarrierAccount::query()->findOrFail($session->carrier_account_id);

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED, $session->status);
        $this->assertSame('fedex_active_account_exists', $session->last_error_code);
        $this->assertNull($session->completed_at);
        $this->assertNull($newAccount->fedex_active_store_key);
        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $newAccount->connection_status);

        $existing->refresh();
        $this->assertTrue($existing->isConnected());
        $this->assertSame(
            CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, CarrierAccount::ENVIRONMENT_SANDBOX),
            $existing->fedex_active_store_key
        );
    }

    public function test_duplicate_concurrent_activation_is_blocked_by_unique_active_key(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Concurrent Active Key Store');
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $key = CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, CarrierAccount::ENVIRONMENT_SANDBOX);

        $first = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'Active one',
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'fedex_active_store_key' => $key,
            'default_origin_location_id' => $location->id,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
        $first->setFedExAccountNumber('222222222');
        $first->save();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'Active two',
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'fedex_active_store_key' => $key,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
    }

    public function test_model_a_account_number_is_encrypted_masked_and_absent_from_plaintext(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Encrypt Number Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $this->fakeSuccessfulRegistrationHttp();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect();

        $account = CarrierAccount::query()->findOrFail($session->fresh()->carrier_account_id);
        $raw = DB::table('carrier_accounts')->where('id', $account->id)->first();

        $this->assertNull($raw->provider_account_number);
        $this->assertNotNull($raw->provider_account_number_encrypted);
        $this->assertNotSame('700257037', $raw->provider_account_number_encrypted);
        $this->assertSame('7037', $raw->provider_account_last4);
        $this->assertSame('700257037', $account->fedExAccountNumber());
        $this->assertSame('*****7037', $account->maskedAccountNumber());
        $this->assertStringNotContainsString('700257037', json_encode($account->toArray()));
        $this->assertArrayNotHasKey('provider_account_number_encrypted', $account->toArray());
    }

    public function test_transient_session_secrets_cleared_while_sanitized_evidence_remains(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx Clear Secrets Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);
        $this->fakeSuccessfulRegistrationHttp();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload())
            ->assertRedirect();

        $session->refresh();

        $this->assertNull($session->accountAuthToken());
        $this->assertNull($session->account_auth_token_expires_at);
        $this->assertNull($session->childCredentials());
        $this->assertNull($session->mfa_options_json);
        $this->assertNull($session->mfa_destination_masked);
        $this->assertNull($session->mfa_expires_at);

        $this->assertNotNull($session->fedex_transaction_id);
        $this->assertNotNull($session->request_summary_json);
        $this->assertNotNull($session->response_summary_json);
        $this->assertNotNull($session->carrier_account_id);
        $this->assertSame('FedEx Form No. 2002382 v 4 June 2024 Rev', $session->eula_version);
        $this->assertArrayNotHasKey('provider_account_number', $session->registrationAddress());
    }

    /**
     * Expose finalization for focused security/idempotency assertions without HTTP replay of the full wizard.
     */
    private function invokeFinalize(
        CarrierAccountRegistrationSession $session,
        CarrierApiResult $result,
    ): CarrierAccount {
        $orchestrator = app(FedExIntegratorRegistrationOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'finalizeRegistrationWithChildCredentials');

        return $method->invoke($orchestrator, $session, $result);
    }

    /**
     * @param  array{customer_key: string, customer_password: string}  $child
     */
    private function invokePersistIssuedChildCredentials(
        CarrierAccountRegistrationSession $session,
        CarrierApiResult $result,
        array $child,
    ): CarrierAccount {
        $orchestrator = app(FedExIntegratorRegistrationOrchestrator::class);
        $method = new ReflectionMethod($orchestrator, 'persistIssuedChildCredentials');

        return $method->invoke($orchestrator, $session, $result, $child);
    }

    private function fakeSuccessfulRegistrationHttp(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                if (($request->data()['grant_type'] ?? null) === 'csp_credentials') {
                    return Http::response([
                        'access_token' => 'child-oauth-token',
                        'token_type' => 'bearer',
                        'expires_in' => 3600,
                    ], 200);
                }

                return Http::response([
                    'access_token' => 'platform-parent-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'fedex-reg-txn-1',
                    'output' => [
                        'child_Key' => 'child-key-value-123',
                        'childSecret' => 'child-secret-value-456',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });
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

    private function createMfaReadySession(
        Store $store,
        User $owner,
        Location $location,
        string $status = CarrierAccountRegistrationSession::STATUS_MFA_METHOD_REQUIRED,
        ?string $method = null,
    ): CarrierAccountRegistrationSession {
        $session = $this->createSession($store, $owner, $location, $status);
        $session->setAccountNumber('700257037');
        $session->setAccountAuthToken('fedex-account-auth-token-test', now()->addHour());
        $session->forceFill([
            'account_name' => 'MFA Account',
            'eula_accepted_at' => now(),
            'eula_accepted_by' => $owner->id,
            'eula_document_hash' => 'hash',
            'registration_address_json' => $this->registrationAddress(),
            'mfa_method' => $method,
            'mfa_options_json' => [
                ['method' => 'EMAIL', 'label' => 'Email PIN', 'destination_masked' => '***-***-3021', 'raw_key' => 'email'],
            ],
            'mfa_destination_masked' => '***-***-3021',
            'mfa_expires_at' => now()->addMinutes(30),
        ])->save();

        return $session->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(string $accountNumber = '700257037'): array
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

    /**
     * @return array<string, mixed>
     */
    private function registrationAddress(): array
    {
        return [
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

    private function configureMfaPaths(): void
    {
        config([
            'carriers.fedex.mfa_pin_generation_path' => '/registration/v2/customerkeys/pingeneration',
            'carriers.fedex.mfa_pin_validation_path' => '/registration/v2/pin/keysgeneration',
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
}
