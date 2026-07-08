<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\USPS\Connection\USPSMerchantConnectionService;
use App\Services\Carriers\USPS\Support\USPSMerchantWizard;
use Database\Seeders\CarrierSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6USPSMerchantOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureUspsMerchant(oauthEnabled: true);
    }

    public function test_oauth_start_uses_cop_link_for_first_time_authorization(): void
    {
        [$owner, $store] = $this->ownerStore('USPS COP Start Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
        ]);

        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->save();

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.oauth.start', $account));

        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('https://cop.usps.com/merchant-authorize', $target);
        $this->assertStringContainsString('state=', $target);
        $this->assertStringNotContainsString('user_id=49188300', $target);
    }

    public function test_oauth_start_uses_stored_subject_for_reauthorization(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Reauth Start Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
        ]);

        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->setMerchantOAuthSubjectId('usps-subject-abc123');
        $account->save();

        $this->fakeUspsOAuthHttp();

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.oauth.start', $account));

        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('oauth2/v3/authorize', $target);
        $this->assertStringContainsString('user_id=usps-subject-abc123', $target);
        $this->assertStringNotContainsString('user_id=49188300', $target);
    }

    public function test_oauth_callback_stores_subject_from_token_response(): void
    {
        [$owner, $store] = $this->ownerStore('USPS OAuth Subject Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
        ]);

        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->save();

        $state = 'test-oauth-state-'.Str::random(8);
        Cache::put('usps_merchant_oauth_state:'.$state, [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'user_id' => $owner->id,
        ], 900);

        $this->fakeUspsOAuthHttp(subjectId: 'usps-subject-from-token');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.oauth.callback', [
                'code' => 'usps-auth-code-123',
                'state' => $state,
            ]))
            ->assertRedirect(route('settings.shipping.usps-merchant.manage', $account));

        $account->refresh();
        $this->assertSame('usps-subject-from-token', $account->merchantOAuthSubjectId());
    }

    public function test_oauth_callback_exchanges_code_and_verifies_authorization(): void
    {
        [$owner, $store] = $this->ownerStore('USPS OAuth Callback Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
        ]);

        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->save();

        $state = 'test-oauth-state-'.Str::random(8);
        Cache::put('usps_merchant_oauth_state:'.$state, [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'user_id' => $owner->id,
        ], 900);

        $this->fakeUspsOAuthHttp(subjectId: 'usps-subject-from-token');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.oauth.callback', [
                'code' => 'usps-auth-code-123',
                'state' => $state,
            ]))
            ->assertRedirect(route('settings.shipping.usps-merchant.manage', $account))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertSame(CarrierAccount::USPS_AUTH_VERIFYING, $account->usps_authorization_status);
        $this->assertTrue($account->hasMerchantOAuthTokens());
        $this->assertNotNull($account->connection_context_json['usps_merchant']['oauth_authorization_verified_at'] ?? null);

        $this->assertDatabaseHas('carrier_api_events', [
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_USPS_AUTHORIZATION_VERIFY,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_start_or_resume_returns_existing_account_under_store_lock(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Start Resume Store');
        $location = $this->createOriginLocation($store);
        $service = app(USPSMerchantConnectionService::class);

        $first = $service->startOrResume($store, $owner, $location);
        $second = $service->startOrResume($store, $owner, $location);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CarrierAccount::query()
            ->where('store_id', $store->id)
            ->where('connection_mode', CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER)
            ->where('usps_authorization_status', '!=', CarrierAccount::USPS_AUTH_DISABLED)
            ->count());
    }

    public function test_second_active_usps_account_for_store_violates_unique_store_key(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Duplicate Store');
        $location = $this->createOriginLocation($store);
        $usps = Carrier::query()->where('code', 'usps')->firstOrFail();

        CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $usps->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => 'First USPS account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_SETUP_REQUIRED,
            'usps_active_store_key' => $store->id,
            'default_origin_location_id' => $location->id,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => false,
        ], CarrierAccount::ownershipAttributesForUspsMerchantLabelProvider()));

        $this->expectException(QueryException::class);

        CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $usps->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => 'Second USPS account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_SETUP_REQUIRED,
            'usps_active_store_key' => $store->id,
            'default_origin_location_id' => $location->id,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => false,
        ], CarrierAccount::ownershipAttributesForUspsMerchantLabelProvider()));
    }

    public function test_verify_fails_when_userinfo_does_not_include_mail_owners(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Verify Inconclusive Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
        ]);
        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->setMerchantOAuthTokens('merchant-access-token', 'merchant-refresh-token', 3600);
        $account->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth2-oidc/v3/userinfo')) {
                return Http::response(['sub' => 'merchant-user'], 200);
            }

            return Http::response([], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.verify', $account))
            ->assertRedirect()
            ->assertSessionHasErrors('usps');

        $account->refresh();
        $this->assertNull($account->connection_context_json['usps_merchant']['oauth_authorization_verified_at'] ?? null);
        $this->assertSame('verification_inconclusive', $account->last_error_code);
    }

    public function test_verify_without_oauth_enabled_returns_platform_pending_message(): void
    {
        $this->configureUspsMerchant(oauthEnabled: false);

        [$owner, $store] = $this->ownerStore('USPS Verify Pending Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
        ]);
        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.verify', $account))
            ->assertRedirect()
            ->assertSessionHasErrors('usps');
    }

    public function test_reauthorize_clears_oauth_tokens_subject_and_verification_flag(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Reauthorize OAuth Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
            'connection_context_json' => [
                'usps_merchant' => [
                    'completed_wizard_steps' => [
                        USPSMerchantWizard::STEP_ORIGIN,
                        USPSMerchantWizard::STEP_IDENTIFIERS,
                        USPSMerchantWizard::STEP_AUTHORIZATION,
                    ],
                    'oauth_authorization_verified_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->setMerchantOAuthTokens('token', 'refresh', 3600, 'usps-subject-abc123');
        $account->save();

        Http::fake([
            'https://apis-tem.usps.com/oauth2/v3/revoke' => Http::response([], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.reauthorize', $account))
            ->assertRedirect();

        $account->refresh();
        $this->assertSame(CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION, $account->usps_authorization_status);
        $this->assertFalse($account->hasMerchantOAuthTokens());
        $this->assertFalse($account->hasMerchantOAuthSubjectId());
        $this->assertNull($account->connection_context_json['usps_merchant']['oauth_authorization_verified_at'] ?? null);
    }

    public function test_disconnect_revokes_refresh_token_and_clears_active_store_key(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Disconnect Revoke Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_active_store_key' => null,
        ]);
        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->setMerchantOAuthTokens('token', 'refresh-token', 3600);
        $account->markUspsMerchantActiveForStore();
        $account->save();

        Http::fake([
            'https://apis-tem.usps.com/oauth2/v3/revoke' => Http::response([], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.disconnect', $account))
            ->assertRedirect();

        $account->refresh();
        $this->assertNull($account->usps_active_store_key);
        $this->assertDatabaseHas('carrier_api_events', [
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_USPS_OAUTH_REVOKE,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_oauth_routes_return_not_found_when_icd_not_confirmed(): void
    {
        $this->configureUspsMerchant(oauthEnabled: true, icdConfirmed: false);

        [$owner, $store] = $this->ownerStore('USPS OAuth ICD Gate Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store));
        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.oauth.start', $account))
            ->assertNotFound();
    }

    public function test_usps_identifiers_do_not_trigger_fedex_credential_mode(): void
    {
        [$owner, $store] = $this->ownerStore('USPS FedEx Collision Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store));
        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->save();

        $account->refresh();
        $this->assertSame(CarrierAccount::CREDENTIALS_USPS_MERCHANT_AUTHORIZATION, $account->credentials_source);
        $this->assertFalse($account->usesMerchantFedExDeveloperCredentials());
    }

    private function fakeUspsOAuthHttp(?string $subjectId = null): void
    {
        Http::fake(function ($request) use ($subjectId) {
            $url = $request->url();
            $payload = $request->data();

            if (str_contains($url, '/oauth2/v3/token')) {
                if (($payload['grant_type'] ?? '') === 'client_credentials') {
                    return Http::response([
                        'access_token' => 'platform-access-token',
                        'token_type' => 'Bearer',
                        'expires_in' => 3600,
                    ], 200);
                }

                return Http::response([
                    'access_token' => 'merchant-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                    'refresh_token' => 'merchant-refresh-token',
                    'sub' => $subjectId ?? 'usps-subject-from-token',
                ], 200);
            }

            if (str_contains($url, '/oauth2/v3/authorize')) {
                return Http::response('', 302, [
                    'Location' => $url,
                ]);
            }

            if (str_contains($url, '/oauth2-oidc/v3/userinfo')) {
                return Http::response([
                    'sub' => $subjectId ?? 'usps-subject-from-token',
                    'mail_owners' => [
                        [
                            'crid' => '49188300',
                            'mids' => ['903800001'],
                        ],
                    ],
                    'payment_accounts' => [
                        'accounts' => [
                            ['account_number' => '1000445839'],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
    }

    private function configureUspsMerchant(bool $oauthEnabled, bool $icdConfirmed = true): void
    {
        config([
            'carriers.usps.enabled' => true,
            'carriers.usps.merchant_connection_enabled' => true,
            'carriers.usps.merchant_oauth_enabled' => $oauthEnabled,
            'carriers.usps.merchant_oauth_icd_confirmed' => $icdConfirmed,
            'carriers.usps.merchant_cop_authorization_url' => 'https://cop.usps.com/merchant-authorize',
            'carriers.usps.merchant_oauth_allow_http_redirect' => true,
            'carriers.usps.environment' => 'testing',
            'carriers.usps.base_url' => 'https://apis-tem.usps.com',
            'carriers.usps.consumer_key' => 'test-usps-consumer-key',
            'carriers.usps.consumer_secret' => 'test-usps-consumer-secret',
            'carriers.usps.merchant_oauth_consumer_key' => 'test-usps-merchant-oauth-key',
            'carriers.usps.merchant_oauth_consumer_secret' => 'test-usps-merchant-oauth-secret',
            'carriers.usps.oauth_redirect_url' => 'http://localhost/settings/shipping/carriers/usps/oauth/callback',
            'app.url' => 'http://localhost',
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

    private function createOriginLocation(Store $store): Location
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
     * @param  array<string, mixed>  $overrides
     */
    private function createUspsMerchantAccount(Store $store, Location $location, array $overrides = []): CarrierAccount
    {
        $usps = Carrier::query()->where('code', 'usps')->firstOrFail();

        return CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $usps->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => 'Merchant USPS account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_PENDING_VALIDATION,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
            'usps_enrollment_status' => CarrierAccount::USPS_ENROLLMENT_NOT_STARTED,
            'usps_active_store_key' => $store->id,
            'default_origin_location_id' => $location->id,
            'connection_context_json' => [
                'usps_merchant' => [
                    'completed_wizard_steps' => [
                        USPSMerchantWizard::STEP_ORIGIN,
                        USPSMerchantWizard::STEP_IDENTIFIERS,
                    ],
                ],
            ],
            'supported_countries' => ['US'],
            'enabled_for_checkout' => false,
        ], CarrierAccount::ownershipAttributesForUspsMerchantLabelProvider(), $overrides));
    }
}
