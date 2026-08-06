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
use App\Services\Carriers\FedEx\Connection\FedExMerchantConnectionLifecycleService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExLiveMerchantConnectionTest extends TestCase
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

    public function test_manage_page_shows_connected_model_a_account(): void
    {
        [$owner, $store, $account] = $this->connectedAccountFixture('FedEx Manage Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.manage', $account))
            ->assertOk()
            ->assertSeeText('Manage FedEx connection')
            ->assertSeeText($account->maskedAccountNumber())
            ->assertSeeText('Verify connection')
            ->assertSeeText('Reconnect FedEx')
            ->assertSeeText('Disconnect FedEx account');
    }

    public function test_verify_connection_marks_account_connected(): void
    {
        [$owner, $store, $account] = $this->connectedAccountFixture('FedEx Verify Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'child-verify-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.verify', $account))
            ->assertRedirect(route('settings.shipping.fedex-integrator.manage', $account))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->isConnected());
        $this->assertNotNull($account->last_verified_at);
    }

    public function test_disconnect_clears_credentials_and_active_key_but_keeps_last4(): void
    {
        [$owner, $store, $account] = $this->connectedAccountFixture('FedEx Disconnect Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.disconnect', $account))
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']));

        $account->refresh();
        $this->assertSame(CarrierAccount::CONNECTION_DISABLED, $account->connection_status);
        $this->assertNull($account->fedex_active_store_key);
        $this->assertNull($account->credentials_encrypted);
        $this->assertFalse($account->hasLegacyFedExChildCredentials());
        $this->assertSame('7037', $account->provider_account_last4);
        $this->assertNotNull($account->disconnected_at);
        $this->assertSame('*****7037', $account->maskedAccountNumber());
    }

    public function test_reconnect_starts_session_linked_to_existing_account(): void
    {
        [$owner, $store, $account] = $this->connectedAccountFixture('FedEx Reconnect Start Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.reconnect', $account))
            ->assertRedirect();

        $session = CarrierAccountRegistrationSession::query()
            ->where('store_id', $store->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($session);
        $this->assertSame((int) $account->id, (int) $session->replacing_carrier_account_id);
        $this->assertSame(CarrierAccountRegistrationSession::STATUS_EULA_REQUIRED, $session->status);
        $this->assertTrue($account->fresh()->isConnected());
        $this->assertNotNull($account->fresh()->fedex_active_store_key);
    }

    public function test_reconnect_completion_replaces_old_active_account(): void
    {
        [$owner, $store, $account] = $this->connectedAccountFixture('FedEx Reconnect Replace Store');
        $session = app(FedExMerchantConnectionLifecycleService::class)->beginReconnect(
            $store,
            $owner,
            $account,
            (int) $account->default_origin_location_id,
        );
        $this->completeEulaScroll($session);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.eula.accept', $session), [
                'read_and_accept_eula' => '1',
                'document_hash' => app(\App\Services\Carriers\FedEx\Connection\FedExEulaService::class)->hash(),
            ])
            ->assertRedirect();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                if (($request->data()['grant_type'] ?? null) === 'csp_credentials') {
                    return Http::response(['access_token' => 'new-child-token', 'expires_in' => 3600], 200);
                }

                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'reconnect-txn',
                    'output' => [
                        'child_Key' => 'new-child-key',
                        'childSecret' => 'new-child-secret',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('740561073'))
            ->assertRedirect(route('settings.shipping.fedex-integrator.success', $session));

        $session->refresh();
        $newAccount = CarrierAccount::query()->findOrFail($session->carrier_account_id);
        $account->refresh();

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);
        $this->assertTrue($newAccount->isConnected());
        $this->assertSame(
            CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, CarrierAccount::ENVIRONMENT_SANDBOX),
            $newAccount->fedex_active_store_key,
        );
        $this->assertNull($account->fedex_active_store_key);
        $this->assertNotNull($account->replaced_at);
        $this->assertSame((int) $newAccount->id, (int) $account->replaced_by_carrier_account_id);
        $this->assertFalse($account->hasLegacyFedExChildCredentials());
        $this->assertSame(CarrierAccount::CONNECTION_DISABLED, $account->connection_status);
    }

    public function test_no_plaintext_account_number_after_connection(): void
    {
        [$owner, $store, $location] = $this->fixtureParts('FedEx No Plaintext Store');
        $session = $this->createSession($store, $owner, $location, CarrierAccountRegistrationSession::STATUS_EULA_ACCEPTED);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                if (($request->data()['grant_type'] ?? null) === 'csp_credentials') {
                    return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
                }

                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'plain-txn',
                    'output' => [
                        'child_Key' => 'child-key',
                        'childSecret' => 'child-secret',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.account.submit', $session), $this->accountPayload('700257037'))
            ->assertRedirect();

        $account = CarrierAccount::query()->findOrFail($session->fresh()->carrier_account_id);
        $this->assertNull($account->provider_account_number);
        $this->assertSame('700257037', $account->fedExAccountNumber());
        $this->assertStringNotContainsString('700257037', json_encode($account->toArray()));
        $this->assertStringNotContainsString('child-secret', json_encode($account->toArray()));
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function connectedAccountFixture(string $name): array
    {
        [$owner, $store, $location] = $this->fixtureParts($name);
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'Connected FedEx',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
            'settings' => ['default_origin_location_id' => $location->id],
            'last_verified_at' => now(),
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
        $account->setFedExAccountNumber('700257037');
        $account->setCredentials([
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
        ]);
        $account->assignFedExActiveStoreKey();
        $account->save();

        return [$owner, $store, $account->refresh()];
    }

    /**
     * @return array{0: User, 1: Store, 2: Location}
     */
    private function fixtureParts(string $name): array
    {
        [$owner, $store] = $this->ownerStore($name);
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

        return [$owner, $store, $location];
    }

    private function createSession(Store $store, User $owner, Location $location, string $status): CarrierAccountRegistrationSession
    {
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

    private function completeEulaScroll(CarrierAccountRegistrationSession $session): void
    {
        app(\App\Services\Carriers\FedEx\Connection\FedExIntegratorRegistrationOrchestrator::class)->markEulaScrollComplete(
            $session,
            app(\App\Services\Carriers\FedEx\Connection\FedExEulaService::class)->hash(),
            app(\App\Services\Carriers\FedEx\Connection\FedExEulaService::class)->expectedPages(),
        );
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
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
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

        return [$owner, $store];
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
