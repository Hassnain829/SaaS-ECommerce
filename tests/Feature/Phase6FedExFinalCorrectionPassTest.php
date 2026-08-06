<?php

namespace Tests\Feature;

use App\Console\Commands\FedExProductionPreflightCommand;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExFinalCorrectionPassTest extends TestCase
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
            'carriers.fedex.validation_mode_enabled' => false,
            'carriers.fedex.integrator_production_enabled' => false,
        ]);
    }

    public function test_verify_requires_exact_active_key_and_does_not_assign_key(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Verify Key Guard');
        $account->forceFill(['fedex_active_store_key' => null])->save();

        $result = app(FedExMerchantConnectionLifecycleService::class)->verify($account->fresh());

        $this->assertFalse($result->success);
        $this->assertSame('fedex_active_key_mismatch', $result->errorCode);
        $this->assertNull($account->fresh()->fedex_active_store_key);
    }

    public function test_verify_success_keeps_capabilities_disabled(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Verify Success Caps');

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200),
        ]);

        $result = app(FedExMerchantConnectionLifecycleService::class)->verify($account->fresh());

        $this->assertTrue($result->success);
        $account->refresh();
        $this->assertNotNull($account->last_verified_at);
        $this->assertFalse((bool) data_get($account->capabilities, 'rates'));
        $this->assertFalse((bool) data_get($account->capabilities, 'labels'));
        $this->assertFalse((bool) $account->enabled_for_checkout);
        $this->assertSame(
            CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, 'sandbox'),
            $account->fedex_active_store_key
        );
    }

    public function test_disconnect_is_idempotent_and_clears_secrets_after_commit_cache(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Disconnect Idempotent');
        $cacheKey = app(FedExIntegratorChildOAuthService::class)->tokenCacheKey($account);
        Cache::put($cacheKey, ['access_token' => 'cached', 'token_type' => 'bearer', 'expires_in' => 3600], 600);

        $lifecycle = app(FedExMerchantConnectionLifecycleService::class);
        $first = $lifecycle->disconnect($account->fresh());
        $disconnectedAt = $account->fresh()->disconnected_at;
        $this->assertFalse($first['idempotent']);
        $this->assertNull($account->fresh()->fedex_active_store_key);
        $this->assertNull($account->fresh()->provider_account_number_encrypted);
        $this->assertSame('1073', $account->fresh()->provider_account_last4);
        $this->assertFalse($account->fresh()->hasLegacyFedExChildCredentials());
        $this->assertNull(Cache::get($cacheKey));

        $second = $lifecycle->disconnect($account->fresh());
        $this->assertTrue($second['idempotent']);
        $this->assertTrue($disconnectedAt->equalTo($account->fresh()->disconnected_at));
    }

    public function test_resume_from_child_oauth_failed_without_registration_call(): void
    {
        [$owner, $store, $account, $session] = $this->resumableSession('Resume Failed');

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'resumed-token', 'expires_in' => 3600], 200);
            }

            return Http::response(['errors' => [['message' => 'no registration']]], 500);
        });

        $session = app(FedExMerchantConnectionLifecycleService::class)
            ->resumeChildOAuthVerification($store, $session);

        $this->assertSame(CarrierAccountRegistrationSession::STATUS_REGISTERED, $session->status);
        $this->assertTrue($account->fresh()->isConnected());
        Http::assertSentCount(1);
    }

    public function test_generic_disable_and_destroy_blocked_for_model_a(): void
    {
        [$owner, $store, $account] = $this->connectedAccount('Model A Bypass');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.disable', $account))
            ->assertStatus(422);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('settings.shipping.carrier-accounts.destroy', $account))
            ->assertStatus(422);

        $this->assertDatabaseHas('carrier_accounts', ['id' => $account->id]);
    }

    public function test_legacy_model_b_routes_gated_by_config(): void
    {
        config([
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.developer_mode_enabled' => false,
        ]);
        $this->assertFalse(app(FedExConfig::class)->modelBRoutesEnabled());

        $source = file_get_contents(base_path('routes/fedex.php'));
        $this->assertStringContainsString('modelBRoutesEnabled()', $source);
    }

    public function test_validation_routes_gated_by_config_method(): void
    {
        config(['carriers.fedex.validation_mode_enabled' => false]);
        $this->assertFalse(app(FedExConfig::class)->validationRoutesEnabled());

        config(['carriers.fedex.validation_mode_enabled' => true]);
        $this->assertTrue(app(FedExConfig::class)->validationRoutesEnabled());

        $source = file_get_contents(base_path('routes/carriers.php'));
        $this->assertStringContainsString('validationRoutesEnabled()', $source);
    }

    public function test_route_files_have_no_utf8_bom(): void
    {
        foreach (['routes/carriers.php', 'routes/fedex-validation.php'] as $relative) {
            $bytes = file_get_contents(base_path($relative), false, null, 0, 3);
            $this->assertNotSame("\xEF\xBB\xBF", $bytes, $relative.' still has BOM');
        }
    }

    public function test_production_preflight_fails_without_live_config_and_prints_no_secrets(): void
    {
        config([
            'carriers.fedex.live.client_id' => '',
            'carriers.fedex.live.client_secret' => '',
            'carriers.fedex.integrator_production_enabled' => false,
            'carriers.fedex.model_b_developer_fallback_enabled' => true,
            'carriers.fedex.developer_mode_enabled' => true,
            'carriers.fedex.validation_mode_enabled' => true,
        ]);

        $exit = Artisan::call('fedex:production-preflight');
        $output = Artisan::output();

        $this->assertSame(FedExProductionPreflightCommand::FAILURE, $exit);
        $this->assertStringNotContainsString('parent-secret', $output);
        $this->assertStringNotContainsString('fake-live-secret', $output);
        $this->assertStringContainsString('FAIL', $output);
    }

    public function test_production_preflight_passes_with_safe_fake_config(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.developer_mode_enabled' => false,
            'carriers.fedex.validation_mode_enabled' => false,
            'carriers.fedex.sandbox_allow_platform_fallback' => false,
            'carriers.fedex.integrator_production_enabled' => true,
            'carriers.fedex.live.base_url' => 'https://apis.fedex.com',
            'carriers.fedex.live.client_id' => 'fake-live-id',
            'carriers.fedex.live.client_secret' => 'fake-live-secret',
            'carriers.fedex.live_allowed_countries' => 'US,CA',
        ]);

        $exit = Artisan::call('fedex:production-preflight');
        $output = Artisan::output();

        $this->assertSame(FedExProductionPreflightCommand::SUCCESS, $exit);
        $this->assertStringNotContainsString('fake-live-id', $output);
        $this->assertStringNotContainsString('fake-live-secret', $output);
        $this->assertStringContainsString('PASS', $output);
    }

    public function test_lifecycle_migration_down_uses_custom_fk_name(): void
    {
        $source = file_get_contents(database_path(
            'migrations/2026_08_06_020000_add_fedex_connection_lifecycle_columns.php'
        ));
        $this->assertStringContainsString('dropForeignCompat($table, self::REPLACED_BY_FK', $source);
        $this->assertStringContainsString('$table->dropForeign($fkName)', $source);
        $this->assertStringContainsString('ca_replaced_by_carrier_account_fk', $source);
        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'disconnected_at'));
        $this->assertTrue(Schema::hasColumn('carrier_accounts', 'replaced_by_carrier_account_id'));
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
     * @return array{0: User, 1: Store, 2: CarrierAccount, 3: CarrierAccountRegistrationSession}
     */
    private function resumableSession(string $name): array
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
            'status' => CarrierAccountRegistrationSession::STATUS_CHILD_OAUTH_FAILED,
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
