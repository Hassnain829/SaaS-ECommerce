<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Connection\FedExMerchantConnectionLifecycleService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase6FedExLifecycleSafetyPassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => 'parent-id',
            'carriers.fedex.sandbox.client_secret' => 'parent-secret',
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.developer_mode_enabled' => false,
            'carriers.fedex.integrator_production_enabled' => false,
        ]);
    }

    public function test_replace_active_account_throws_logic_exception(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Logic Replace Out');
        [$owner2, $store2, $incoming] = $this->connectedAccount('Logic Replace In');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Atomic FedEx Model A replacement is owned by FedExIntegratorRegistrationOrchestrator');

        app(FedExMerchantConnectionLifecycleService::class)->replaceActiveAccount($incoming, $outgoing);
    }

    public function test_replaced_by_relationships(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Rel Out');
        [$incoming] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Rel In');

        $outgoing->forceFill([
            'replaced_at' => now(),
            'replaced_by_carrier_account_id' => $incoming->id,
            'disconnected_at' => now(),
            'fedex_active_store_key' => null,
        ])->save();

        $this->assertTrue($outgoing->fresh()->replacedByCarrierAccount->is($incoming));
        $this->assertTrue($incoming->fresh()->replacedCarrierAccounts->contains(fn ($a) => (int) $a->id === (int) $outgoing->id));
    }

    public function test_resume_from_each_resumable_status(): void
    {
        foreach ([
            CarrierAccountRegistrationSession::STATUS_CREDENTIALS_ISSUED,
            CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_VERIFYING,
            CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
        ] as $status) {
            [$owner, $store, $account, $session] = $this->resumableSession('Resume '.$status, $status);
            $this->fakeChildOAuthSuccess();

            $session = app(FedExMerchantConnectionLifecycleService::class)
                ->resumeChildOAuthVerification($store, $session);

            $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);
            $this->assertTrue($account->fresh()->isConnected());
        }
    }

    public function test_repeated_resume_is_idempotent(): void
    {
        [$owner, $store, $account, $session] = $this->resumableSession('Resume Idem');
        $this->fakeChildOAuthSuccess();
        $lifecycle = app(FedExMerchantConnectionLifecycleService::class);

        $first = $lifecycle->resumeChildOAuthVerification($store, $session);
        $accountId = (int) $first->carrier_account_id;
        $second = $lifecycle->resumeChildOAuthVerification($store, $first->fresh());

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $second->status);
        $this->assertSame($accountId, (int) $second->carrier_account_id);
        $this->assertSame(1, CarrierAccount::query()->where('registration_session_id', $session->id)->count());
        Http::assertSentCount(1);
    }

    public function test_resume_already_registered_connected_is_noop(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Resume Already');
        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'purpose' => CarrierAccountRegistrationSession::PURPOSE_CONNECTION,
            'status' => CarrierAccountRegistrationSession::STATUS_REGISTERED,
            'origin_location_id' => $account->default_origin_location_id,
            'carrier_account_id' => $account->id,
            'created_by' => $owner->id,
            'completed_at' => now(),
        ]);
        $account->forceFill(['registration_session_id' => $session->id])->save();

        Http::fake();
        $result = app(FedExMerchantConnectionLifecycleService::class)
            ->resumeChildOAuthVerification($store, $session->fresh());

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $result->status);
        Http::assertNothingSent();
    }

    public function test_resume_rejects_cross_store_wrong_provider_and_model(): void
    {
        [$owner, $store, $account, $session] = $this->resumableSession('Resume Guards');
        $other = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Other',
            'slug' => 'other-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($other, $session);
            $this->fail('Expected cross-store abort');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $session->forceFill(['provider' => 'ups'])->save();
        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session->fresh());
            $this->fail('Expected wrong provider abort');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $session->forceFill([
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'connection_model' => 'merchant_developer',
        ])->save();
        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session->fresh());
            $this->fail('Expected wrong model abort');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_resume_rejects_pairing_environment_and_missing_credentials(): void
    {
        [$owner, $store, $account, $session] = $this->resumableSession('Resume Pair');

        $account->forceFill(['registration_session_id' => null])->save();
        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session->fresh());
            $this->fail('Expected pairing abort');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $account->forceFill([
            'registration_session_id' => $session->id,
            'environment' => CarrierAccount::ENVIRONMENT_LIVE,
        ])->save();
        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session->fresh());
            $this->fail('Expected environment abort');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $account->forceFill([
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'credentials_encrypted' => null,
        ])->save();
        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session->fresh());
            $this->fail('Expected missing credentials abort');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_verify_transient_preserves_connected_and_key(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Verify Transient');
        $key = $account->fedex_active_store_key;

        Http::fake([
            '*/oauth/token' => Http::response(['errors' => [['message' => 'SERVICE.UNAVAILABLE.RAW']]], 503),
        ]);

        $result = app(FedExMerchantConnectionLifecycleService::class)->verify($account->fresh());

        $this->assertFalse($result->success);
        $this->assertSame('fedex_verify_transient', $result->errorCode);
        $account->refresh();
        $this->assertTrue($account->isConnected());
        $this->assertSame($key, $account->fedex_active_store_key);
        $this->assertTrue($account->hasLegacyFedExChildCredentials());
        $this->assertStringNotContainsString('SERVICE.UNAVAILABLE.RAW', (string) $account->last_error_message);
        $this->assertFalse((bool) data_get($account->capabilities, 'rates'));
    }

    public function test_verify_invalid_credentials_preserves_active_key(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Verify Invalid');
        $key = $account->fedex_active_store_key;

        Http::fake([
            '*/oauth/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'secret-leak'], 401),
        ]);

        $result = app(FedExMerchantConnectionLifecycleService::class)->verify($account->fresh());

        $this->assertSame('child_credentials_invalid', $result->errorCode);
        $account->refresh();
        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $account->connection_status);
        $this->assertSame($key, $account->fedex_active_store_key);
        $this->assertStringNotContainsString('secret-leak', (string) $account->last_error_message);
        $this->assertFalse((bool) $account->enabled_for_checkout);
    }

    public function test_verify_never_assigns_key_and_blocks_pending(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Verify Pending');
        $account->forceFill([
            'fedex_active_store_key' => null,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
        ])->save();

        $result = app(FedExMerchantConnectionLifecycleService::class)->verify($account->fresh());
        $this->assertSame('fedex_active_key_mismatch', $result->errorCode);
        $this->assertNull($account->fresh()->fedex_active_store_key);
    }

    public function test_disconnect_preserves_evidence_and_clears_capabilities(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Disconnect Evidence');
        $account->forceFill([
            'eula_accepted_at' => now(),
            'eula_version' => '1.0',
            'eula_document_hash' => 'abc',
            'provider_account_number' => 'SHOULD_CLEAR',
        ])->save();

        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'purpose' => CarrierAccountRegistrationSession::PURPOSE_CONNECTION,
            'status' => CarrierAccountRegistrationSession::STATUS_REGISTERED,
            'origin_location_id' => $account->default_origin_location_id,
            'carrier_account_id' => $account->id,
            'created_by' => $owner->id,
            'account_auth_token_encrypted' => 'enc-token',
            'pin_encrypted' => 'enc-pin',
            'fedex_transaction_id' => 'txn-keep',
        ]);

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'status' => 'success',
            'environment' => 'sandbox',
        ]);

        $cacheKey = app(FedExIntegratorChildOAuthService::class)->tokenCacheKey($account);
        Cache::put($cacheKey, ['access_token' => 'cached'], 600);

        $lifecycle = app(FedExMerchantConnectionLifecycleService::class);
        $first = $lifecycle->disconnect($account->fresh());
        $disconnectedAt = $account->fresh()->disconnected_at;

        $account->refresh();
        $this->assertFalse($first['idempotent']);
        $this->assertNull($account->fedex_active_store_key);
        $this->assertNull($account->credentials_encrypted);
        $this->assertNull($account->provider_account_number_encrypted);
        $this->assertNull($account->provider_account_number);
        $this->assertSame('1073', $account->provider_account_last4);
        $this->assertNotNull($account->eula_accepted_at);
        $this->assertSame('1.0', $account->eula_version);
        $this->assertTrue(CarrierAccountRegistrationSession::query()->whereKey($session->id)->exists());
        $this->assertSame('txn-keep', $session->fresh()->fedex_transaction_id);
        $this->assertNull($session->fresh()->account_auth_token_encrypted);
        $this->assertSame(1, CarrierApiEvent::query()->where('carrier_account_id', $account->id)->count());
        $this->assertFalse((bool) data_get($account->capabilities, 'rates'));
        $this->assertFalse((bool) data_get($account->capabilities, 'labels'));
        $this->assertFalse((bool) data_get($account->capabilities, 'tracking'));
        $this->assertFalse((bool) data_get($account->capabilities, 'pickup'));
        $this->assertFalse((bool) data_get($account->capabilities, 'checkout_rates'));
        $this->assertFalse((bool) $account->enabled_for_checkout);
        $this->assertNull(Cache::get($cacheKey));

        $second = $lifecycle->disconnect($account->fresh());
        $this->assertTrue($second['idempotent']);
        $this->assertTrue($disconnectedAt->equalTo($account->fresh()->disconnected_at));
    }

    public function test_reconnect_preserves_outgoing_until_activation_and_rolls_back_on_incoming_damage(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Atomic Out');
        $outgoingKey = $outgoing->fedex_active_store_key;
        $cacheKey = app(FedExIntegratorChildOAuthService::class)->tokenCacheKey($outgoing);
        Cache::put($cacheKey, ['access_token' => 'outgoing-token'], 600);

        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Atomic In');

        // Incoming disconnected before Transaction B — resume must refuse and leave outgoing untouched.
        $incoming->forceFill(['disconnected_at' => now()])->save();

        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session);
            $this->fail('Expected abort for disconnected incoming');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $outgoing->refresh();
        $this->assertSame($outgoingKey, $outgoing->fedex_active_store_key);
        $this->assertNull($outgoing->replaced_at);
        $this->assertNull($outgoing->disconnected_at);
        $this->assertTrue($outgoing->hasLegacyFedExChildCredentials());
        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_reconnect_incoming_missing_credentials_leaves_outgoing_unchanged(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Cred Out');
        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Cred In');
        $incoming->forceFill(['credentials_encrypted' => null])->save();

        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session);
            $this->fail('Expected abort for missing credentials');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $outgoing->refresh();
        $this->assertNotNull($outgoing->fedex_active_store_key);
        $this->assertNull($outgoing->replaced_at);
        $this->assertTrue($outgoing->isConnected());
    }

    public function test_reconnect_oauth_failure_leaves_outgoing_unchanged(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect OAuth Fail Out');
        $key = $outgoing->fedex_active_store_key;
        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect OAuth Fail In');

        Http::fake([
            '*/oauth/token' => Http::response(['error' => 'server_error'], 500),
        ]);

        $session = app(FedExMerchantConnectionLifecycleService::class)
            ->resumeChildOAuthVerification($store, $session);

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED, $session->status);
        $outgoing->refresh();
        $this->assertSame($key, $outgoing->fedex_active_store_key);
        $this->assertNull($outgoing->replaced_at);
        $this->assertTrue($outgoing->isConnected());
        $this->assertTrue($outgoing->hasLegacyFedExChildCredentials());
        $this->assertFalse($incoming->fresh()->isConnected());
    }

    public function test_reconnect_outgoing_key_mismatch_and_stale_target_fail_safely(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Key Out');
        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Key In');
        $outgoing->forceFill(['fedex_active_store_key' => 'store:999:fedex:sandbox'])->save();

        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session->fresh());
            $this->fail('Expected abort for outgoing key mismatch');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $outgoing->refresh();
        $this->assertNull($outgoing->replaced_at);
        $this->assertTrue($outgoing->isConnected());
        $this->assertNull($incoming->fresh()->replaced_at);
    }

    public function test_successful_reconnect_repeated_resume_is_idempotent(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Resume Idem Out');
        $outgoing->forceFill([
            'eula_accepted_at' => now(),
            'eula_version' => 'eula-1',
        ])->save();
        $eventCountBefore = CarrierApiEvent::query()->where('store_id', $store->id)->count();

        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Resume Idem In');
        $this->fakeChildOAuthSuccess();
        $lifecycle = app(FedExMerchantConnectionLifecycleService::class);

        $session = $lifecycle->resumeChildOAuthVerification($store, $session);
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);

        $accountCount = CarrierAccount::query()->where('store_id', $store->id)->count();
        $expectedKey = CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, 'sandbox');
        $incoming->refresh();
        $outgoing->refresh();
        $this->assertSame($expectedKey, $incoming->fedex_active_store_key);
        $this->assertNotNull($outgoing->replaced_at);
        $this->assertNull($outgoing->fedex_active_store_key);

        Http::fake(); // any further OAuth would fail this assertion
        $again = $lifecycle->resumeChildOAuthVerification($store, $session->fresh());

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $again->status);
        Http::assertNothingSent();
        $this->assertSame($accountCount, CarrierAccount::query()->where('store_id', $store->id)->count());
        $this->assertSame($expectedKey, $incoming->fresh()->fedex_active_store_key);
        $this->assertTrue($incoming->fresh()->isConnected());
        $this->assertNotNull($outgoing->fresh()->replaced_at);
        $this->assertNotNull($outgoing->fresh()->disconnected_at);
        $this->assertNull($outgoing->fresh()->fedex_active_store_key);
        $this->assertSame(CarrierAccount::CONNECTION_DISABLED, $outgoing->fresh()->connection_status);
        $this->assertSame(
            $eventCountBefore + 1,
            CarrierApiEvent::query()->where('store_id', $store->id)->count(),
            'Repeated resume must not create another registration event'
        );
    }

    public function test_registered_resume_rejects_incorrect_non_empty_active_key(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Resume Bad Key');
        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'purpose' => CarrierAccountRegistrationSession::PURPOSE_CONNECTION,
            'status' => CarrierAccountRegistrationSession::STATUS_REGISTERED,
            'origin_location_id' => $account->default_origin_location_id,
            'carrier_account_id' => $account->id,
            'created_by' => $owner->id,
            'completed_at' => now(),
        ]);
        $account->forceFill([
            'registration_session_id' => $session->id,
            'fedex_active_store_key' => 'store:999:fedex:sandbox',
        ])->save();
        $badKey = $account->fresh()->fedex_active_store_key;

        Http::fake();
        try {
            app(FedExMerchantConnectionLifecycleService::class)
                ->resumeChildOAuthVerification($store, $session->fresh());
            $this->fail('Expected 422 for incorrect active key');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        Http::assertNothingSent();
        $this->assertSame($badKey, $account->fresh()->fedex_active_store_key);
    }

    public function test_registered_reconnect_resume_ignores_replaced_outgoing(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Done Out');
        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Done In');
        $this->fakeChildOAuthSuccess();
        $lifecycle = app(FedExMerchantConnectionLifecycleService::class);
        $session = $lifecycle->resumeChildOAuthVerification($store, $session);

        $outgoing->refresh();
        $this->assertNull($outgoing->fedex_active_store_key);
        $this->assertNotNull($outgoing->replaced_at);
        $this->assertNotNull($outgoing->disconnected_at);

        Http::fake();
        $again = $lifecycle->resumeChildOAuthVerification($store, $session->fresh());
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $again->status);
        Http::assertNothingSent();
        $this->assertSame(
            CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, 'sandbox'),
            $incoming->fresh()->fedex_active_store_key
        );
    }

    public function test_non_registered_reconnect_resume_still_requires_outgoing_active_key(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Need Key Out');
        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Need Key In');
        $outgoing->forceFill(['fedex_active_store_key' => null])->save();

        Http::fake();
        try {
            app(FedExMerchantConnectionLifecycleService::class)
                ->resumeChildOAuthVerification($store, $session->fresh());
            $this->fail('Expected 422 when outgoing no longer owns active key');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        Http::assertNothingSent();
        $this->assertNull($outgoing->fresh()->replaced_at);
        $this->assertNull($incoming->fresh()->fedex_active_store_key);
    }

    public function test_successful_reconnect_transfers_key_and_clears_outgoing_secrets_after_commit_cache(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Success Out');
        $outgoing->forceFill([
            'eula_accepted_at' => now(),
            'eula_version' => 'eula-1',
            'provider_account_number' => 'PLAINTEXT_SHOULD_CLEAR',
        ])->save();
        $cacheKey = app(FedExIntegratorChildOAuthService::class)->tokenCacheKey($outgoing);
        Cache::put($cacheKey, ['access_token' => 'old'], 600);
        $event = CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $outgoing->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'status' => 'success',
            'environment' => 'sandbox',
        ]);

        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Success In');
        $this->assertSame((int) $outgoing->id, (int) $session->replacing_carrier_account_id);
        $this->assertTrue($outgoing->fresh()->isConnected());

        $this->fakeChildOAuthSuccess();
        $session = app(FedExMerchantConnectionLifecycleService::class)
            ->resumeChildOAuthVerification($store, $session);

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);
        $outgoing->refresh();
        $incoming->refresh();

        $expectedKey = CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, 'sandbox');
        $this->assertSame($expectedKey, $incoming->fedex_active_store_key);
        $this->assertNull($outgoing->fedex_active_store_key);
        $this->assertNotNull($outgoing->replaced_at);
        $this->assertSame((int) $incoming->id, (int) $outgoing->replaced_by_carrier_account_id);
        $this->assertFalse($outgoing->hasLegacyFedExChildCredentials());
        $this->assertNull($outgoing->provider_account_number_encrypted);
        $this->assertNull($outgoing->provider_account_number);
        $this->assertSame('1073', $outgoing->provider_account_last4);
        $this->assertNotNull($outgoing->eula_accepted_at);
        $this->assertTrue(CarrierApiEvent::query()->whereKey($event->id)->exists());
        $this->assertNull(Cache::get($cacheKey));
        $this->assertFalse((bool) data_get($incoming->capabilities, 'rates'));
    }

    public function test_activation_failure_after_retire_rolls_back_outgoing_and_skips_cache_clear(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Rollback Out');
        $key = $outgoing->fedex_active_store_key;
        $cacheKey = app(FedExIntegratorChildOAuthService::class)->tokenCacheKey($outgoing);
        Cache::put($cacheKey, ['access_token' => 'keep-me'], 600);

        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Rollback In');
        $incomingId = (int) $incoming->id;
        $outgoingId = (int) $outgoing->id;

        CarrierAccount::updating(function (CarrierAccount $model) use ($outgoingId, $incomingId): void {
            if ((int) $model->id === $outgoingId && $model->isDirty('replaced_at') && $model->replaced_at !== null) {
                DB::table('carrier_accounts')->where('id', $incomingId)->update([
                    'credentials_encrypted' => null,
                ]);
            }
        });

        $this->fakeChildOAuthSuccess();

        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session);
            $this->fail('Expected ValidationException from rolled-back activation');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $outgoing->refresh();
        $this->assertSame($key, $outgoing->fedex_active_store_key);
        $this->assertNull($outgoing->replaced_at);
        $this->assertNull($outgoing->disconnected_at);
        $this->assertTrue($outgoing->isConnected());
        $this->assertTrue($outgoing->hasLegacyFedExChildCredentials());
        $this->assertNotNull(Cache::get($cacheKey), 'Rolled-back replacement must not invalidate outgoing token cache');
    }

    public function test_unique_key_race_rolls_back_outgoing_retirement(): void
    {
        [$owner, $store, $outgoing] = $this->connectedAccount('Reconnect Unique Out');
        $key = $outgoing->fedex_active_store_key;
        [$incoming, $session] = $this->setupIncomingForReconnect($store, $owner, $outgoing, 'Reconnect Unique In');
        $incomingId = (int) $incoming->id;

        CarrierAccount::updating(function (CarrierAccount $model) use ($incomingId, $key): void {
            if ((int) $model->id === $incomingId && $model->fedex_active_store_key === $key) {
                throw new UniqueConstraintViolationException(
                    DB::connection()->getName(),
                    'update carrier_accounts set fedex_active_store_key = ?',
                    [$key],
                    new \Exception('unique')
                );
            }
        });

        $this->fakeChildOAuthSuccess();

        try {
            app(FedExMerchantConnectionLifecycleService::class)->resumeChildOAuthVerification($store, $session);
            $this->fail('Expected ValidationException from unique-key race');
        } catch (ValidationException) {
            // expected
        }

        $outgoing->refresh();
        $this->assertSame($key, $outgoing->fedex_active_store_key);
        $this->assertNull($outgoing->replaced_at);
        $this->assertTrue($outgoing->isConnected());
    }

    private function fakeChildOAuthSuccess(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200),
        ]);
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function connectedAccount(string $name): array
    {
        [$owner, $store, $location] = $this->fixture($name);
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => $name,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
            'enabled_for_checkout' => false,
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'checkout_rates' => false,
            ],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
        $account->setFedExAccountNumber('740561073');
        $account->setCredentials([
            'customer_key' => 'child-key-'.$account->id,
            'customer_password' => 'child-secret-'.$account->id,
        ]);
        $account->assignFedExActiveStoreKey();
        $account->save();

        return [$owner, $store, $account->fresh()];
    }

    /**
     * @return array{0: CarrierAccount, 1: CarrierAccountRegistrationSession}
     */
    private function setupIncomingForReconnect(Store $store, User $owner, CarrierAccount $outgoing, string $name): array
    {
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $incoming = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => $name,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'default_origin_location_id' => $outgoing->default_origin_location_id,
            'enabled_for_checkout' => false,
            'capabilities' => [
                'rates' => false,
                'labels' => false,
                'tracking' => false,
                'pickup' => false,
                'checkout_rates' => false,
            ],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
        $incoming->setFedExAccountNumber('740561073');
        $incoming->setCredentials([
            'customer_key' => 'new-child-key-'.$incoming->id,
            'customer_password' => 'new-child-secret-'.$incoming->id,
        ]);
        $incoming->save();

        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'purpose' => CarrierAccountRegistrationSession::PURPOSE_CONNECTION,
            'status' => CarrierAccountRegistrationSession::STATUS_CREDENTIALS_ISSUED,
            'origin_location_id' => $outgoing->default_origin_location_id,
            'carrier_account_id' => $incoming->id,
            'replacing_carrier_account_id' => $outgoing->id,
            'created_by' => $owner->id,
            'account_last4' => '1073',
        ]);
        $incoming->forceFill(['registration_session_id' => $session->id])->save();

        return [$incoming->fresh(), $session->fresh()];
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount, 3: CarrierAccountRegistrationSession}
     */
    private function resumableSession(string $name, string $status = CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED): array
    {
        [$owner, $store, $account] = $this->connectedAccount($name);
        $account->forceFill([
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'fedex_active_store_key' => null,
        ])->save();

        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'purpose' => CarrierAccountRegistrationSession::PURPOSE_CONNECTION,
            'status' => $status,
            'origin_location_id' => $account->default_origin_location_id,
            'carrier_account_id' => $account->id,
            'created_by' => $owner->id,
            'account_last4' => '1073',
        ]);
        $account->forceFill(['registration_session_id' => $session->id])->save();

        return [$owner, $store, $account->fresh(), $session->fresh()];
    }

    /**
     * @return array{0: User, 1: Store, 2: Location}
     */
    private function fixture(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => $role->id,
        ]);
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
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Main',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'is_default' => true,
            'is_fulfillment_origin' => true,
        ]);

        return [$owner, $store, $location];
    }
}
