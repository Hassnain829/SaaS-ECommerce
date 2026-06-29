<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceQueryService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughService;
use App\Services\Carriers\FedEx\Validation\FedExValidationSwedenPassthroughSupport;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class Phase6FedExSwedenPassthroughTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExModelA();
    }

    public function test_one_click_sweden_passthrough_runs_parent_registration_and_child_oauth(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Sweden Happy Path Store');
        $this->fakeSwedenPassthroughResponses();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.sweden-passthrough', $account))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('success');

        Http::assertSentCount(3);

        $address = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS)
            ->latest('id')
            ->first();

        $child = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD)
            ->latest('id')
            ->first();

        $this->assertNotNull($address);
        $this->assertNotNull($child);
        $this->assertTrue($address->isSuccessfulHttp());
        $this->assertTrue($child->isSuccessfulHttp());
        $this->assertSame(
            data_get($address->request_summary, 'validation_run_id'),
            data_get($child->request_summary, 'validation_run_id'),
        );
        $this->assertTrue((bool) data_get($address->response_summary, 'child_credentials_detected'));
        $this->assertFalse((bool) data_get($address->response_summary, 'mfa_detected'));
    }

    public function test_sweden_passthrough_does_not_mutate_existing_carrier_account(): void
    {
        [, , $account] = $this->integratorAccountFixture('Sweden No Mutation Store');
        $beforeCredentials = $account->fresh()->credentials();
        $beforeAccountNumber = $account->provider_account_number;
        $beforeCount = CarrierAccount::query()->count();
        $beforeStatus = $account->connection_status;

        $this->fakeSwedenPassthroughResponses();
        app(FedExValidationSwedenPassthroughService::class)->run($account->store, $account->fresh());

        $account->refresh();
        $this->assertSame($beforeCredentials, $account->credentials());
        $this->assertSame($beforeAccountNumber, $account->provider_account_number);
        $this->assertSame($beforeStatus, $account->connection_status);
        $this->assertSame($beforeCount, CarrierAccount::query()->count());
    }

    public function test_sweden_passthrough_child_credentials_are_not_cached(): void
    {
        [, , $account] = $this->integratorAccountFixture('Sweden Cache Store');
        $this->fakeSwedenPassthroughResponses();

        app(FedExValidationSwedenPassthroughService::class)->run($account->store, $account);

        $childEvent = CarrierApiEvent::query()
            ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD)
            ->latest('id')
            ->firstOrFail();

        $this->assertFalse((bool) data_get($childEvent->request_summary, 'cached'));
        $this->assertFalse((bool) data_get($childEvent->response_summary, 'cached'));
        $this->assertStringNotContainsString('ephemeral-child-key', json_encode($childEvent->request_summary));
        $this->assertStringNotContainsString('ephemeral-child-secret', json_encode($childEvent->response_summary));
    }

    public function test_mfa_returned_fails_without_child_oauth_and_shows_required_message(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Sweden MFA Returned Store');
        $this->fakeSwedenPassthroughResponses(withMfa: true);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.sweden-passthrough', $account))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('error', FedExValidationSwedenPassthroughSupport::FAILURE_MESSAGE);

        $this->assertNull(
            CarrierApiEvent::query()
                ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD)
                ->first()
        );

        Http::assertSentCount(2);
    }

    public function test_run_correlation_does_not_mix_unpaired_events(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Sweden Correlation Store');

        $runA = (string) Str::uuid();
        $runB = (string) Str::uuid();

        $this->createSwedenAddressEvent($store, $account, $runA, succeeded: true);
        $this->createSwedenChildEvent($store, $account, $runA, succeeded: false);
        $this->createSwedenAddressEvent($store, $account, $runB, succeeded: false);
        $this->createSwedenChildEvent($store, $account, 'orphan-run-id', succeeded: true);

        $this->assertNull(app(FedExValidationEvidenceQueryService::class)->canonicalSwedenPassthroughRun($store, $account));

        $runC = (string) Str::uuid();
        $this->createSwedenAddressEvent($store, $account, $runC, succeeded: true);
        $this->createSwedenChildEvent($store, $account, $runC, succeeded: true);

        $paired = app(FedExValidationEvidenceQueryService::class)->canonicalSwedenPassthroughRun($store, $account);
        $this->assertNotNull($paired);
        $this->assertSame($runC, $paired['validation_run_id']);
    }

    public function test_workspace_shows_sweden_card_without_credential_fields(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Sweden Workspace Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Run Sweden MFA Passthrough')
            ->assertSeeText('****9268')
            ->assertSeeText('Sweden MFA Passthrough')
            ->assertDontSeeText('604849268')
            ->assertDontSeeText('child_secret')
            ->assertDontSeeText('client_secret');
    }

    public function test_screenshot_upload_requires_successful_paired_run(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Sweden Screenshot Gate Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.sweden-screenshots.upload', $account), [
                'address_screenshot' => UploadedFile::fake()->create('address.png', 100, 'image/png'),
                'child_authorization_screenshot' => UploadedFile::fake()->create('child.png', 100, 'image/png'),
            ])
            ->assertStatus(422);
    }

    public function test_diagnostic_export_includes_sweden_passthrough_folder(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Sweden Export Store');
        $this->fakeSwedenPassthroughResponses();
        app(FedExValidationSwedenPassthroughService::class)->run($account->store, $account);

        $zipPath = app(FedExValidationEvidenceExporter::class)->exportDiagnostic($store, $account);
        $zip = new ZipArchive;
        $zip->open($zipPath);

        $prefix = 'FedEx_Integrator_Validation_BaasPlatformFedExSandbox/01_registration_mfa/12_sweden_mfa_passthrough/';
        $this->assertNotFalse($zip->locateName($prefix.'01_address_validation/request.json'));
        $this->assertNotFalse($zip->locateName($prefix.'02_child_authorization/request.json'));
        $this->assertNotFalse($zip->locateName($prefix.'result_summary.json'));

        $zip->close();
    }

    public function test_exported_sweden_evidence_redacts_child_secrets(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Sweden Sanitizer Store');
        $this->fakeSwedenPassthroughResponses();
        app(FedExValidationSwedenPassthroughService::class)->run($account->store, $account);

        $address = CarrierApiEvent::query()
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS)
            ->firstOrFail();
        $child = CarrierApiEvent::query()
            ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD)
            ->firstOrFail();

        $sanitizer = app(FedExValidationEvidenceSanitizer::class);
        $addressResponse = $sanitizer->sanitize($address->response_body_encrypted);
        $childRequest = $sanitizer->sanitize($child->request_body_encrypted);
        $childResponse = $sanitizer->sanitize($child->response_body_encrypted);

        $this->assertSame('[REDACTED]', data_get($addressResponse, 'output.child_Key'));
        $this->assertSame('csp_credentials', data_get($childRequest, 'grant_type'));
        $this->assertSame('[REDACTED]', data_get($childRequest, 'child_key'));
        $this->assertSame('[REDACTED]', data_get($childResponse, 'access_token'));
        $this->assertSame('bearer', data_get($childResponse, 'token_type'));
    }

    public function test_sweden_fixture_parses_workbook_parenthetical_address(): void
    {
        $service = app(\App\Services\Carriers\FedEx\Validation\FedExTestCaseFixtureService::class);
        $method = new \ReflectionMethod($service, 'parseSwedenParentheticalAddress');
        $method->setAccessible(true);

        $parsed = $method->invoke(
            $service,
            'sweden: test account# 604849268 (HAGAGATAN 1, VI, STOCKHOLM, 11349, SE)',
        );

        $this->assertSame('HAGAGATAN 1, VI', $parsed['address_line1']);
        $this->assertSame('STOCKHOLM', $parsed['city']);
        $this->assertSame('11349', $parsed['postal_code']);
        $this->assertSame('SE', $parsed['country_code']);
        $this->assertArrayNotHasKey('state', $parsed);
    }

    public function test_sweden_fixture_merges_legacy_env_state_into_street_line(): void
    {
        config([
            'carriers.fedex.validation.sweden.account_number' => '604849268',
            'carriers.fedex.validation.sweden.customer_name' => 'Unique Customer Name',
            'carriers.fedex.validation.sweden.address_line1' => 'HAGAGATAN 1',
            'carriers.fedex.validation.sweden.state' => 'VI',
            'carriers.fedex.validation.sweden.city' => 'STOCKHOLM',
            'carriers.fedex.validation.sweden.postal_code' => '11349',
            'carriers.fedex.validation.sweden.country_code' => 'SE',
        ]);
        Cache::forget('fedex.integrator.test_case_fixtures');

        $fixtures = app(\App\Services\Carriers\FedEx\Validation\FedExTestCaseFixtureService::class)
            ->swedenMfaPassthroughAccount();

        $this->assertSame('HAGAGATAN 1, VI', $fixtures['address_line1']);
        $this->assertSame('', $fixtures['state']);
    }

    private function fakeSwedenPassthroughResponses(bool $withMfa = false, bool $withoutChildCredentials = false): void
    {
        Http::fake(function ($request) use ($withMfa, $withoutChildCredentials) {
            if (str_contains($request->url(), '/oauth/token')) {
                $grantType = $request->data()['grant_type'] ?? null;

                return Http::response([
                    'access_token' => $grantType === 'csp_credentials' ? 'sweden-child-token' : 'sweden-parent-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                    'scope' => 'CXS-TP',
                ], 200);
            }

            if (str_contains($request->url(), '/registration/v2/accounts')) {
                $output = $withMfa
                    ? ['mfaOptions' => [['method' => 'SMS']], 'child_Key' => 'ephemeral-child-key', 'childSecret' => 'ephemeral-child-secret']
                    : ($withoutChildCredentials
                        ? ['accountNumber' => '1234569268']
                        : ['child_Key' => 'ephemeral-child-key', 'childSecret' => 'ephemeral-child-secret']);

                return Http::response(['output' => $output], 200);
            }

            return Http::response([], 404);
        });
    }

    private function createSwedenAddressEvent(Store $store, CarrierAccount $account, string $runId, bool $succeeded): CarrierApiEvent
    {
        return CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'scenario_key' => CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS,
            'status' => $succeeded ? CarrierApiEvent::STATUS_SUCCEEDED : CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => $succeeded ? 200 : 422,
            'http_method' => 'POST',
            'endpoint' => '/registration/v2/accounts',
            'request_summary' => [
                'validation_run_id' => $runId,
                'validation_case' => FedExValidationSwedenPassthroughSupport::VALIDATION_CASE,
                'case_key' => FedExValidationSwedenPassthroughSupport::CASE_KEY,
                'country_code' => 'SE',
                'account_last4' => '9268',
            ],
            'response_summary' => [
                'validation_run_id' => $runId,
                'child_credentials_detected' => $succeeded,
                'mfa_detected' => false,
            ],
            'request_body_encrypted' => ['sample' => true],
            'response_body_encrypted' => ['output' => ['child_Key' => '[REDACTED]']],
        ]);
    }

    private function createSwedenChildEvent(Store $store, CarrierAccount $account, string $runId, bool $succeeded): CarrierApiEvent
    {
        return CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            'scenario_key' => CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD,
            'status' => $succeeded ? CarrierApiEvent::STATUS_SUCCEEDED : CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => $succeeded ? 200 : 401,
            'http_method' => 'POST',
            'endpoint' => '/oauth/token',
            'request_summary' => [
                'validation_run_id' => $runId,
                'endpoint' => '/oauth/token',
            ],
            'response_summary' => ['validation_run_id' => $runId],
            'request_body_encrypted' => ['grant_type' => 'csp_credentials'],
            'response_body_encrypted' => ['token_type' => 'bearer', 'expires_in' => 3600],
        ]);
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
            'carriers.fedex.sandbox.account_registration_path' => '/registration/v2/accounts',
            'carriers.fedex.validation.sweden.account_number' => '604849268',
            'carriers.fedex.validation.sweden.customer_name' => 'Sweden Validation Customer',
            'carriers.fedex.validation.sweden.address_line1' => 'Testgatan 1',
            'carriers.fedex.validation.sweden.city' => 'STOCKHOLM',
            'carriers.fedex.validation.sweden.postal_code' => '11122',
            'carriers.fedex.validation.sweden.country_code' => 'SE',
        ]);

        Cache::forget('fedex.integrator.test_case_fixtures');
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function integratorAccountFixture(string $name): array
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

        $account->setCredentials([
            'customer_key' => 'existing-child-key',
            'customer_password' => 'existing-child-secret',
        ]);
        $account->save();

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
