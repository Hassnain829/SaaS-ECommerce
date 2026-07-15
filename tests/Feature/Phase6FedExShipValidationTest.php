<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExAuthorizationClassifier;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class Phase6FedExShipValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExModelA();
    }

    public function test_integrator_account_sees_ship_validation_tools(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Ship UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertOk()
            ->assertSeeText('FedEx approval tools')
            ->assertSeeText('Sandbox diagnostics')
            ->assertSeeText('One-click sandbox check')
            ->assertSeeText('Sandbox ship checks')
            ->assertDontSeeText('integrator_child')
            ->assertDontSeeText('Validation capability status')
            ->assertSee('name="use_baseline"', false)
            ->assertSee(route('settings.shipping.carrier-accounts.fedex.test-ship-validate', $account), false);
    }

    public function test_ship_validate_records_event_and_returns_success(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Ship Validate Store');

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/ship/v1/shipments/packages/validate')) {
                return Http::response([
                    'transactionId' => 'fedex-ship-validate-1',
                    'output' => ['alerts' => []],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL', 'code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-ship-validate', $account), [
                'test_case' => 'IntegratorUS02',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_VALIDATE)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(CarrierApiEvent::STATUS_SUCCEEDED, $event->status);
        $this->assertSame('IntegratorUS02', data_get($event->request_summary, 'test_case'));
        $this->assertSame('ZPLII', data_get($event->request_summary, 'label_format'));
        $this->assertTrue((bool) data_get($event->request_summary, 'ship_validation_only'));
        $this->assertDatabaseCount('fedex_validation_artifacts', 0);
    }

    public function test_ship_validate_payload_includes_locked_zplii_label_specification(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Ship Validate Payload Store');
        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/ship/v1/shipments/packages/validate')) {
                $capturedPayload = $request->data();

                return Http::response([
                    'transactionId' => 'fedex-ship-validate-payload-1',
                    'output' => ['alerts' => []],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL', 'code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-ship-validate', $account), [
                'test_case' => 'IntegratorUS02',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']));

        $this->assertIsArray($capturedPayload);
        $this->assertSame('ZPLII', data_get($capturedPayload, 'requestedShipment.labelSpecification.imageType'));
        $this->assertStringNotContainsString('child-secret', json_encode($capturedPayload));
    }

    public function test_ship_label_generation_creates_artifact_when_enabled(): void
    {
        config([
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.ship_evidence_enabled' => false,
        ]);

        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Label Artifact Store');

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/ship/v1/shipments') && ! str_contains($url, '/packages/validate')) {
                return Http::response([
                    'transactionId' => 'fedex-ship-label-1',
                    'output' => [
                        'transactionShipments' => [[
                            'serviceType' => 'PRIORITY_OVERNIGHT',
                            'masterTrackingNumber' => '794612345678',
                            'pieceResponses' => [[
                                'packageSequenceNumber' => 1,
                                'trackingNumber' => '794612345678',
                                'packageDocuments' => [[
                                    'docType' => 'LABEL',
                                    'imageType' => 'ZPLII',
                                    'encodedLabel' => base64_encode('^XA^FO50,50^FDTest^FS^XZ'),
                                ]],
                            ]],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL', 'code' => 'NOT.FOUND']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-ship-label', $account), [
                'test_case' => 'IntegratorUS02',
                'label_format' => 'ZPLII',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $artifact = \App\Models\FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('artifact_type', 'ship_label_zplii')
            ->first();

        $this->assertNotNull($artifact);
        $this->assertTrue(File::exists(storage_path('app/'.$artifact->file_path)));

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $encoded = json_encode($event->request_summary);
        $this->assertStringNotContainsString('child-secret', (string) $encoded);
        $this->assertStringNotContainsString('child-token', (string) $encoded);
        $this->assertNull(data_get($event->request_summary, 'ship_validation_only'));
    }

    public function test_ship_validate_403_is_treated_as_authorization_blocked(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Ship Blocked Store');

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/ship/v1/shipments/packages/validate')) {
                return Http::response([
                    'errors' => [['code' => 'FORBIDDEN.ERROR', 'message' => 'Not authorized']],
                ], 403);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-ship-validate', $account), [
                'test_case' => 'IntegratorUS02',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']))
            ->assertSessionHas('success');

        $result = $response->getSession()->get('fedex_test_result');
        $this->assertSame('fedex_authorization_blocked', $result['result_kind'] ?? null);
        $this->assertNotEmpty($result['support_summary'] ?? null);
    }

    public function test_label_generation_disabled_returns_local_validation_failure(): void
    {
        config([
            'carriers.fedex.ship_sandbox_label_generation_enabled' => false,
            'carriers.fedex.ship_evidence_enabled' => false,
        ]);

        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Label Disabled Store');

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test-ship-label', $account), [
                'test_case' => 'IntegratorUS02',
                'label_format' => 'ZPLII',
            ])
            ->assertRedirect(route('shippingAutomation', ['tab' => 'carriers']));

        $this->assertDatabaseMissing('carrier_api_events', [
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
        ]);
    }

    public function test_validation_evidence_export_has_no_placeholder_json(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Export No Placeholder Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'scenario_key' => CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE,
            'status' => CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 403,
            'error_code' => 'fedex_comprehensive_rate_blocked_entitlement',
            'endpoint' => '/rate/v1/comprehensiverates/quotes',
            'http_method' => 'POST',
            'request_body_encrypted' => ['rateRequestControlParameters' => ['returnTransitTimes' => true]],
            'response_body_encrypted' => ['errors' => [['code' => 'FORBIDDEN.ERROR', 'message' => 'Not authorized']]],
            'request_summary' => ['endpoint' => '/rate/v1/comprehensiverates/quotes', 'test_quote_only' => true],
            'response_summary' => ['http_status' => 403, 'access_state' => 'blocked_entitlement', 'fedex_error_code' => 'FORBIDDEN.ERROR'],
            'error_message' => 'Comprehensive Rates access blocked',
        ]);

        $zipPath = app(FedExValidationEvidenceExporter::class)->export(
            store: $store,
            account: $account,
            region: 'US',
            environment: CarrierAccount::ENVIRONMENT_SANDBOX,
        );

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $rateQuoteJson = (string) $zip->getFromName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/04_comprehensive_rates/response.json');
        $zip->close();

        $this->assertStringNotContainsString('placeholder', $rateQuoteJson);
        $this->assertStringContainsString('403', $rateQuoteJson);
        $this->assertStringNotContainsString('child-secret', $rateQuoteJson);
        $this->assertStringNotContainsString('child-key', $rateQuoteJson);
    }

    public function test_ship_payload_factory_builds_redacted_structure_without_secrets(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Payload Factory Store');
        $fixture = app(FedExShipTestCaseFixtureService::class)->fixture('IntegratorUS02');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'ZPLII');

        $this->assertSame('PRIORITY_OVERNIGHT', data_get($payload, 'requestedShipment.serviceType'));
        $this->assertSame('700257037', data_get($payload, 'accountNumber.value'));
        $this->assertSame('ZPLII', data_get($payload, 'requestedShipment.labelSpecification.imageType'));
        $this->assertStringNotContainsString('child-secret', json_encode($payload));
    }

    public function test_authorization_classifier_maps_rate_and_ship_403(): void
    {
        $result = CarrierApiResult::failure(
            message: 'Forbidden',
            code: 'FORBIDDEN.ERROR',
            responseSummary: ['http_status' => 403],
        );

        $classified = FedExAuthorizationClassifier::applyBlockedClassification($result, '/rate/v1/rates/quotes');
        $this->assertSame('fedex_authorization_blocked', $classified->errorCode);

        $shipClassified = FedExAuthorizationClassifier::applyBlockedClassification($result, '/ship/v1/shipments');
        $this->assertSame('fedex_authorization_blocked', $shipClassified->errorCode);
    }

    private function configureFedExModelA(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.default_connection_model' => 'integrator_provider',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => 'platform-client-id',
            'carriers.fedex.sandbox.client_secret' => 'platform-client-secret',
            'carriers.fedex.ship_sandbox_label_generation_enabled' => false,
            'carriers.fedex.ship_evidence_enabled' => false,
        ]);
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    protected function integratorAccountFixture(string $name): array
    {
        [$owner, $store] = $this->ownerStore($name);
        $location = $this->readyLocation($store);
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

    private function readyLocation(Store $store): Location
    {
        return Location::query()->create([
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
}
