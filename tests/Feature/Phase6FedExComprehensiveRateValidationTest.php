<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExComprehensiveRateAccessClassifier;
use App\Services\Carriers\FedEx\Operations\FedExComprehensiveRatePayloadFactory;
use App\Services\Carriers\FedEx\Operations\FedExComprehensiveRateResponseParser;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExComprehensiveRateEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScopeService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class Phase6FedExComprehensiveRateValidationTest extends TestCase
{
    use RefreshDatabase;

    private ?string $capturedRateUrl = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExModelA();
    }

    public function test_validation_run_posts_to_comprehensive_rates_endpoint_not_legacy_rate_endpoint(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Comprehensive Endpoint Store');
        $this->fakeFedExHttp(successRate: true);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.comprehensive-rate', $account))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account));

        $this->assertNotNull($this->capturedRateUrl);
        $this->assertStringContainsString('/rate/v1/comprehensiverates/quotes', (string) $this->capturedRateUrl);
        $this->assertStringNotContainsString('/rate/v1/rates/quotes', (string) $this->capturedRateUrl);

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH, $event->endpoint);
    }

    public function test_payload_factory_builds_required_comprehensive_rate_structure(): void
    {
        [, , $account] = $this->integratorAccountFixture('Payload Factory Store');
        $fixture = app(FedExTestCaseFixtureService::class)->comprehensiveRateQuoteCase();
        $payload = app(FedExComprehensiveRatePayloadFactory::class)->build($account, $fixture, now()->toDateString());

        $this->assertArrayHasKey('accountNumber', $payload);
        $this->assertArrayHasKey('rateRequestControlParameters', $payload);
        $this->assertArrayHasKey('requestedShipment', $payload);
        $this->assertSame('700257037', data_get($payload, 'accountNumber.value'));
        $this->assertNotEmpty(data_get($payload, 'requestedShipment.shipper.address.postalCode'));
        $this->assertNotEmpty(data_get($payload, 'requestedShipment.recipient.address.postalCode'));
        $this->assertSame(['ACCOUNT', 'LIST'], data_get($payload, 'requestedShipment.rateRequestType'));
    }

    public function test_parser_selects_expected_service_and_account_rate(): void
    {
        $parser = app(FedExComprehensiveRateResponseParser::class);
        $parsed = $parser->parse([
            'output' => [
                'rateReplyDetails' => [
                    [
                        'serviceType' => 'FEDEX_GROUND',
                        'serviceName' => 'FedEx Ground',
                        'ratedShipmentDetails' => [
                            ['rateType' => 'LIST', 'totalNetCharge' => 22.11, 'shipmentRateDetail' => ['currency' => 'USD']],
                        ],
                    ],
                    [
                        'serviceType' => 'PRIORITY_OVERNIGHT',
                        'serviceName' => 'FedEx Priority Overnight',
                        'ratedShipmentDetails' => [
                            ['rateType' => 'LIST', 'totalNetCharge' => 99.99, 'shipmentRateDetail' => ['currency' => 'USD']],
                            ['rateType' => 'ACCOUNT', 'totalNetCharge' => 18.42, 'shipmentRateDetail' => ['currency' => 'USD']],
                        ],
                    ],
                ],
            ],
        ], 'PRIORITY_OVERNIGHT', 'ACCOUNT');

        $this->assertSame('PRIORITY_OVERNIGHT', $parsed['service_type']);
        $this->assertSame('ACCOUNT', $parsed['rate_type']);
        $this->assertSame('USD', $parsed['currency']);
        $this->assertSame('18.42', $parsed['amount']);
        $this->assertCount(3, $parsed['available_rates']);
    }

    public function test_legacy_rate_endpoint_event_is_not_canonical_comprehensive_evidence(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Legacy Rate Reject Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'scenario_key' => 'rate_quote',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'http_method' => 'POST',
            'endpoint' => '/rate/v1/rates/quotes',
            'request_body_encrypted' => ['requestedShipment' => []],
            'response_body_encrypted' => ['output' => ['rateReplyDetails' => []]],
            'response_summary' => ['amount' => '10.00', 'currency' => 'USD'],
        ]);

        $this->assertNull(app(FedExComprehensiveRateEvidenceService::class)->canonicalEvent($store, $account));

        $check = collect(app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_COMPREHENSIVE_RATES],
        )['checks'])->firstWhere('key', 'comprehensive_rate_transaction');

        $this->assertSame('not_tested', $check['status']);
    }

    public function test_ui_match_fails_when_summary_amount_does_not_match_parsed_response(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('UI Mismatch Store');
        $event = $this->seedSuccessfulComprehensiveRateEvent($store, $account, '18.42');
        $event->update([
            'response_summary' => array_merge($event->response_summary ?? [], [
                'amount' => '18.43',
                'currency' => 'USD',
                'service_type' => 'PRIORITY_OVERNIGHT',
                'rate_type' => 'ACCOUNT',
            ]),
        ]);

        $check = app(FedExComprehensiveRateEvidenceService::class)->uiMatchCheck($event->fresh());
        $this->assertSame('failed', $check['status']);
    }

    public function test_screenshot_upload_rejected_before_successful_rate(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Screenshot Reject Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.comprehensive-rate-screenshot.upload', $account), [
                'screenshot' => UploadedFile::fake()->create('rate-result.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_diagnostic_export_includes_comprehensive_rate_folder(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Comprehensive Export Store');
        $event = $this->seedSuccessfulComprehensiveRateEvent($store, $account, '18.42');

        $relativePath = "fedex-validation/{$store->id}/uploads/comprehensive-rate.png";
        $absolutePath = storage_path('app/'.$relativePath);
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($absolutePath));
        \Illuminate\Support\Facades\File::put($absolutePath, 'png-bytes');

        FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $event->id,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'scenario_key' => CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE,
            'artifact_type' => FedExValidationArtifact::TYPE_COMPREHENSIVE_RATE_RESULT_UI,
            'artifact_role' => FedExValidationArtifact::ROLE_COMPREHENSIVE_RATE_SCREENSHOT,
            'label' => 'Comprehensive rate screenshot',
            'file_path' => $relativePath,
            'mime_type' => 'image/png',
            'file_size' => 9,
            'sha256' => hash('sha256', 'png-bytes'),
            'metadata_json' => ['currency' => 'USD', 'amount' => '18.42'],
        ]);

        $zipPath = app(FedExValidationEvidenceExporter::class)->exportDiagnostic($store, $account);
        $zip = new ZipArchive;
        $zip->open($zipPath);

        $this->assertNotFalse($zip->locateName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/04_comprehensive_rates/request.json'));
        $this->assertNotFalse($zip->locateName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/04_comprehensive_rates/response.json'));
        $this->assertNotFalse($zip->locateName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/04_comprehensive_rates/result_summary.json'));
    }

    public function test_access_classifier_distinguishes_entitlement_and_authentication_failures(): void
    {
        $classifier = app(FedExComprehensiveRateAccessClassifier::class);

        $entitlement = $classifier->classify(403, ['errors' => [['code' => 'FORBIDDEN.ERROR', 'message' => 'Not authorized']]], FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH);
        $auth = $classifier->classify(401, ['errors' => [['code' => 'NOT.AUTHORIZED', 'message' => 'Invalid token']]], FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH);
        $invalid = $classifier->classify(400, ['errors' => [['code' => 'REQUEST.MISMATCH', 'message' => 'Bad payload']]], FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH);

        $this->assertSame(FedExComprehensiveRateAccessClassifier::STATE_BLOCKED_ENTITLEMENT, $entitlement['access_state']);
        $this->assertSame(FedExComprehensiveRateAccessClassifier::STATE_FAILED_AUTHENTICATION, $auth['access_state']);
        $this->assertSame(FedExComprehensiveRateAccessClassifier::STATE_FAILED_INVALID_REQUEST, $invalid['access_state']);
    }

    private function configureFedExModelA(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.comprehensive_rate_quote_path' => FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH,
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => 'fedex-parent-client',
            'carriers.fedex.sandbox.client_secret' => 'fedex-parent-secret',
        ]);
    }

    private function fakeFedExHttp(bool $successRate): void
    {
        Http::fake(function ($request) use ($successRate) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-test', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/rate/v1/comprehensiverates/quotes')) {
                $this->capturedRateUrl = $request->url();

                if (! $successRate) {
                    return Http::response(['errors' => [['code' => 'FORBIDDEN.ERROR', 'message' => 'Forbidden']]], 403);
                }

                return Http::response([
                    'transactionId' => 'comprehensive-rate-txn-1',
                    'output' => [
                        'rateReplyDetails' => [[
                            'serviceType' => 'PRIORITY_OVERNIGHT',
                            'serviceName' => 'FedEx Priority Overnight',
                            'ratedShipmentDetails' => [[
                                'rateType' => 'ACCOUNT',
                                'totalNetCharge' => 18.42,
                                'shipmentRateDetail' => ['currency' => 'USD'],
                            ]],
                        ]],
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/rate/v1/rates/quotes')) {
                $this->fail('Legacy rate endpoint must not be called during comprehensive validation.');
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL: '.$request->url()]]], 404);
        });
    }

    private function seedSuccessfulComprehensiveRateEvent(Store $store, CarrierAccount $account, string $amount): CarrierApiEvent
    {
        return CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'scenario_key' => CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE,
            'test_case_key' => FedExTestCaseFixtureService::COMPREHENSIVE_RATE_CASE_KEY,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'http_method' => 'POST',
            'endpoint' => FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH,
            'request_body_encrypted' => [
                'accountNumber' => ['value' => '*****7037'],
                'rateRequestControlParameters' => ['returnTransitTimes' => true],
                'requestedShipment' => ['pickupType' => 'DROPOFF_AT_FEDEX_LOCATION'],
            ],
            'response_body_encrypted' => [
                'output' => [
                    'rateReplyDetails' => [[
                        'serviceType' => 'PRIORITY_OVERNIGHT',
                        'serviceName' => 'FedEx Priority Overnight',
                        'ratedShipmentDetails' => [[
                            'rateType' => 'ACCOUNT',
                            'totalNetCharge' => (float) $amount,
                            'shipmentRateDetail' => ['currency' => 'USD'],
                        ]],
                    ]],
                ],
            ],
            'response_summary' => [
                'service_type' => 'PRIORITY_OVERNIGHT',
                'rate_type' => 'ACCOUNT',
                'currency' => 'USD',
                'amount' => $amount,
                'ui_amount' => $amount,
                'ui_currency' => 'USD',
                'ui_matches_response' => true,
                'access_state' => FedExComprehensiveRateAccessClassifier::STATE_PASSED,
            ],
        ]);
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
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
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
