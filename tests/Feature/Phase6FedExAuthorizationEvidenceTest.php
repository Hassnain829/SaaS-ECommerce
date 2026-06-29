<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Validation\FedExValidationAuthorizationEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class Phase6FedExAuthorizationEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExModelA();
    }

    public function test_one_click_authorization_runs_parent_then_child_and_records_two_events(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Auth Evidence Store');
        $this->fakeOAuthResponses();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.authorization', $account))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('success');

        $parent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_PARENT)
            ->latest('id')
            ->first();

        $child = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_CHILD)
            ->latest('id')
            ->first();

        $this->assertNotNull($parent);
        $this->assertNotNull($child);
        $this->assertSame(CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN, $parent->action);
        $this->assertSame(CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN, $child->action);
        $this->assertTrue($parent->isSuccessfulHttp());
        $this->assertTrue($child->isSuccessfulHttp());
        $this->assertTrue($parent->hasCompleteEvidence());
        $this->assertTrue($child->hasCompleteEvidence());

        Http::assertSentCount(2);
    }

    public function test_cached_tokens_are_bypassed_for_validation_authorization_runs(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Auth Cache Bypass Store');
        $this->fakeOAuthResponses();

        Cache::put('fedex:sandbox:platform', [
            'access_token' => 'cached-platform-token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ], 3600);

        Cache::put(app(FedExIntegratorChildOAuthService::class)->tokenCacheKey($account), [
            'access_token' => 'cached-child-token',
            'token_type' => 'bearer',
            'expires_in' => 3600,
        ], 3600);

        app(FedExValidationAuthorizationEvidenceService::class)->runBoth($account);

        Http::assertSentCount(2);
    }

    public function test_parent_failure_prevents_child_authorization_run(): void
    {
        [, , $account] = $this->integratorAccountFixture('Auth Parent Fail Store');

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'errors' => [['code' => 'AUTH.FAILED', 'message' => 'Invalid credentials']],
            ], 401),
        ]);

        $outcome = app(FedExValidationAuthorizationEvidenceService::class)->runBoth($account);

        $this->assertNull($outcome['child']);
        $this->assertSame('Parent authorization failed. Child authorization was not run because the parent authorization failed.', $outcome['message']);
        $this->assertSame(1, CarrierApiEvent::query()->where('carrier_account_id', $account->id)->count());
    }

    public function test_missing_child_credentials_blocks_authorization_run(): void
    {
        [, , $account] = $this->integratorAccountFixture('Auth Missing Child Store', withChildCredentials: false);

        $outcome = app(FedExValidationAuthorizationEvidenceService::class)->runBoth($account);

        $this->assertTrue($outcome['blocked']);
        $this->assertStringContainsString('Child credentials are not available', (string) $outcome['message']);
        $this->assertSame(0, CarrierApiEvent::query()->where('carrier_account_id', $account->id)->count());
    }

    public function test_exported_parent_and_child_request_bodies_redact_secrets_but_preserve_grant_type(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Auth Export Redaction Store');
        $this->fakeOAuthResponses();
        app(FedExValidationAuthorizationEvidenceService::class)->runBoth($account);

        $parent = CarrierApiEvent::query()
            ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_PARENT)
            ->firstOrFail();
        $child = CarrierApiEvent::query()
            ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_CHILD)
            ->firstOrFail();

        $sanitizer = app(FedExValidationEvidenceSanitizer::class);
        $parentRequest = $sanitizer->sanitize($parent->request_body_encrypted);
        $childRequest = $sanitizer->sanitize($child->request_body_encrypted);
        $parentResponse = $sanitizer->sanitize($parent->response_body_encrypted);

        $this->assertSame('client_credentials', data_get($parentRequest, 'grant_type'));
        $this->assertSame('[REDACTED]', data_get($parentRequest, 'client_id'));
        $this->assertSame('[REDACTED]', data_get($parentRequest, 'client_secret'));
        $this->assertNull(data_get($parentRequest, 'child_key'));
        $this->assertNull(data_get($parentRequest, 'child_secret'));

        $this->assertSame('csp_credentials', data_get($childRequest, 'grant_type'));
        $this->assertSame('[REDACTED]', data_get($childRequest, 'client_id'));
        $this->assertSame('[REDACTED]', data_get($childRequest, 'client_secret'));
        $this->assertSame('[REDACTED]', data_get($childRequest, 'child_key'));
        $this->assertSame('[REDACTED]', data_get($childRequest, 'child_secret'));

        $this->assertSame('[REDACTED]', data_get($parentResponse, 'access_token'));
        $this->assertSame('bearer', data_get($parentResponse, 'token_type'));
        $this->assertSame(3600, data_get($parentResponse, 'expires_in'));
    }

    public function test_preflight_requires_fresh_non_cached_authorization_evidence(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Auth Preflight Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
            'scenario_key' => CarrierApiEvent::SCENARIO_AUTHORIZATION_PARENT,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'http_method' => 'POST',
            'endpoint' => '/oauth/token',
            'request_summary' => ['cached' => true],
            'response_summary' => ['cached' => true],
            'request_body_encrypted' => ['grant_type' => 'client_credentials'],
            'response_body_encrypted' => ['token_type' => 'bearer', 'expires_in' => 3600],
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);

        $this->assertSame('incomplete', collect($assessment['checks'])->firstWhere('key', 'authorization_parent')['status']);
        $this->assertSame('not_tested', collect($assessment['checks'])->firstWhere('key', 'authorization_child')['status']);
    }

    public function test_workspace_shows_authorization_card_without_credential_fields(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Auth Workspace UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Run Parent + Child Authorization')
            ->assertSeeText('Parent authorization')
            ->assertSeeText('Child authorization')
            ->assertDontSeeText('700257037')
            ->assertDontSeeText('client_secret')
            ->assertDontSeeText('child_secret');
    }

    public function test_final_export_includes_parent_and_child_authorization_folders(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Auth Final Export Store');
        $this->fakeOAuthResponses();
        app(FedExValidationAuthorizationEvidenceService::class)->runBoth($account);
        $this->seedMinimalValidationEvidence($store, $account);

        $zipPath = app(FedExValidationEvidenceExporter::class)->exportDiagnostic($store, $account);
        $zip = new ZipArchive;
        $zip->open($zipPath);

        $this->assertNotFalse($zip->locateName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/01_registration_mfa/01_parent_authorization/request.json'));
        $this->assertNotFalse($zip->locateName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/01_registration_mfa/02_child_authorization/request.json'));

        $zip->close();
    }

    private function fakeOAuthResponses(): void
    {
        Http::fake(function ($request) {
            if (! str_contains($request->url(), '/oauth/token')) {
                return Http::response([], 404);
            }

            $grantType = $request->data()['grant_type'] ?? null;

            return Http::response([
                'access_token' => $grantType === 'csp_credentials' ? 'child-access-token' : 'parent-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'scope' => 'CXS-TP',
            ], 200);
        });
    }

    private function seedMinimalValidationEvidence(Store $store, CarrierAccount $account): void
    {
        foreach (['address_validation', 'service_availability', 'rate_quote'] as $scenario) {
            CarrierApiEvent::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'action' => CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
                'scenario_key' => $scenario,
                'status' => CarrierApiEvent::STATUS_SUCCEEDED,
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'http_status' => 200,
                'http_method' => 'POST',
                'endpoint' => '/test',
                'request_body_encrypted' => ['sample' => true],
                'response_body_encrypted' => ['output' => []],
            ]);
        }
    }

    private function configureFedExModelA(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.default_connection_model' => 'integrator_provider',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => 'platform-client-id',
            'carriers.fedex.sandbox.client_secret' => 'platform-client-secret',
            'carriers.fedex.sandbox.oauth_path' => '/oauth/token',
        ]);
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function integratorAccountFixture(string $name, bool $withChildCredentials = true): array
    {
        [$owner, $store] = $this->ownerStore($name);
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '1751 THOMPSON ST',
            'city' => 'AURORA',
            'state' => 'OH',
            'postal_code' => '44202',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
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

        if ($withChildCredentials) {
            $account->setCredentials([
                'customer_key' => 'child-key-a',
                'customer_password' => 'child-secret-a',
            ]);
            $account->save();
        }

        return [$owner, $store, $account];
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
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
}
