<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Connection\FedExAccountRegistrationService;
use App\Services\Carriers\FedEx\Connection\FedExRegistrationEventLinker;
use App\Services\Carriers\FedEx\DTO\FedExApiEvidenceData;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Presenters\FedExValidationStatusPresenter;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use App\Services\Carriers\FedEx\Validation\FedExValidationScopeService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase6FedExValidationCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.validation_mode_enabled' => true,
        ]);
    }

    public function test_registration_normalization_preserves_evidence(): void
    {
        $evidence = new FedExApiEvidenceData(
            endpoint: '/registration/v1/accounts',
            httpMethod: 'POST',
            httpStatus: 200,
            requestHeaders: ['Content-Type' => 'application/json'],
            requestBody: ['accountNumber' => ['value' => '700257037']],
            responseHeaders: ['Content-Type' => 'application/json'],
            responseBody: ['output' => ['childCredentials' => []]],
            fedexTransactionId: 'fedex-reg-1',
        );

        $result = CarrierApiResult::success(
            data: ['output' => []],
            responseSummary: ['http_status' => 200],
            evidence: $evidence,
        );

        $service = app(FedExAccountRegistrationService::class);
        $method = new \ReflectionMethod($service, 'normalizeRegistrationResult');
        $method->setAccessible(true);

        [, , $account] = $this->integratorAccountFixture('Evidence Normalize Store');
        $normalized = $method->invoke($service, $result, $account, '700257037');

        $this->assertSame($evidence, $normalized->evidence);
        $this->assertSame('/registration/v1/accounts', $normalized->evidence?->endpoint);
        $this->assertSame('fedex-reg-1', $normalized->evidence?->fedexTransactionId);
    }

    public function test_registration_events_link_to_final_account(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Registration Link Store');
        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $account->carrier_id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'status' => 'completed',
        ]);
        $account->update(['registration_session_id' => $session->id]);

        $event = CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'registration_session_id' => $session->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'scenario_key' => 'registration_child_credentials_generated',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'request_body_encrypted' => ['accountNumber' => ['value' => '700257037']],
            'response_body_encrypted' => ['output' => []],
        ]);

        $linked = app(FedExRegistrationEventLinker::class)->linkSessionEventsToAccount($account, $session);

        $this->assertSame(1, $linked);
        $this->assertSame($account->id, $event->fresh()->carrier_account_id);
    }

    public function test_cross_store_registration_events_are_not_linked(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Registration Link Owner Store');
        [, $otherStore] = $this->ownerStore('Registration Link Other Store');

        $otherSession = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $otherStore->id,
            'carrier_id' => $account->carrier_id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'status' => 'completed',
        ]);

        CarrierApiEvent::query()->create([
            'store_id' => $otherStore->id,
            'registration_session_id' => $otherSession->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'scenario_key' => 'registration_child_credentials_generated',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
        ]);

        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $account->carrier_id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'status' => 'completed',
        ]);
        $account->update(['registration_session_id' => $session->id]);

        app(FedExRegistrationEventLinker::class)->linkSessionEventsToAccount($account, $session);

        $this->assertNull(CarrierApiEvent::query()->where('registration_session_id', $otherSession->id)->value('carrier_account_id'));
    }

    public function test_missing_address_validation_blocks_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Missing Address Store');

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_ADDRESS_VALIDATION],
        );

        $this->assertFalse($assessment['ready']);
        $this->assertSame('not_tested', collect($assessment['checks'])->firstWhere('key', 'address_validation')['status']);
    }

    public function test_failed_address_validation_blocks_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Failed Address Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
            'scenario_key' => 'address_validation',
            'status' => CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 400,
            'request_body_encrypted' => ['addressesToValidate' => []],
            'response_body_encrypted' => ['errors' => []],
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_ADDRESS_VALIDATION],
        );

        $this->assertFalse($assessment['ready']);
        $this->assertSame('failed', collect($assessment['checks'])->firstWhere('key', 'address_validation')['status']);
    }

    public function test_missing_service_availability_blocks_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Missing Service Store');

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_SERVICE_AVAILABILITY],
        );

        $this->assertFalse($assessment['ready']);
    }

    public function test_canonical_event_prefers_older_success_over_newer_failure(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Canonical Event Store');

        $success = CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
            'scenario_key' => 'address_validation',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'request_body_encrypted' => ['addressesToValidate' => [['address' => ['city' => 'SUCCESS']]]],
            'response_body_encrypted' => ['output' => ['resolvedAddresses' => []]],
        ]);

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
            'scenario_key' => 'address_validation',
            'status' => CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 500,
            'request_body_encrypted' => ['addressesToValidate' => [['address' => ['city' => 'FAILURE']]]],
            'response_body_encrypted' => ['errors' => []],
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_ADDRESS_VALIDATION],
        );

        $this->assertSame($success->id, collect($assessment['checks'])->firstWhere('key', 'address_validation')['event_id']);
    }

    public function test_scan_upload_without_matching_event_is_rejected(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Scan Reject Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.scans.upload', $account), [
                'test_case_key' => 'IntegratorUS02',
                'package_sequence' => 1,
                'scan_dpi' => 600,
                'scan' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_old_scan_cannot_satisfy_newer_ship_event(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Stale Scan Store');

        $oldEvent = $this->seedSuccessfulShipEvent($store, $account, 'IntegratorUS02', 'ship_us02_zplii', 'ZPLII', 1);
        $newEvent = $this->seedSuccessfulShipEvent($store, $account, 'IntegratorUS02', 'ship_us02_zplii', 'ZPLII', 1, secondRun: true);

        $this->seedScanArtifact($store, $account, $oldEvent, 'IntegratorUS02', 'ship_us02_zplii', 'ZPLII', 1);

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_SHIP],
        );

        $this->assertFalse($assessment['ready']);
        $this->assertSame('incomplete', collect($assessment['checks'])->firstWhere('key', 'ship_us02_zplii_scan_1')['status']);
        $this->assertSame($newEvent->id, collect($assessment['checks'])->firstWhere('key', 'ship_us02_zplii_event')['event_id']);
    }

    public function test_ship_validate_success_does_not_satisfy_label_event_check(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Ship Validate Only Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_VALIDATE,
            'scenario_key' => 'ship_us02_zplii',
            'test_case_key' => 'IntegratorUS02',
            'label_format' => 'ZPLII',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'request_body_encrypted' => ['requestedShipment' => ['serviceType' => 'PRIORITY_OVERNIGHT']],
            'response_body_encrypted' => ['output' => []],
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_SHIP],
        );

        $eventCheck = collect($assessment['checks'])->firstWhere('key', 'ship_us02_zplii_event');
        $this->assertSame('not_tested', $eventCheck['status']);
        $this->assertNull($eventCheck['event_id']);
    }

    public function test_us04_payload_preserves_home_delivery_premium_detail(): void
    {
        [, , $account] = $this->integratorAccountFixture('US04 Payload Store');
        $fixture = app(FedExShipTestCaseFixtureService::class)->fixture('IntegratorUS04');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'PNG');

        $this->assertSame(
            'EVENING',
            data_get($payload, 'requestedShipment.shipmentSpecialServices.homeDeliveryPremiumDetail.homedeliveryPremiumType'),
        );
        $this->assertContains('HOME_DELIVERY_PREMIUM', data_get($payload, 'requestedShipment.shipmentSpecialServices.specialServiceTypes'));
    }

    public function test_us05_payload_includes_total_package_count_and_recipient_payment(): void
    {
        [, , $account] = $this->integratorAccountFixture('US05 Payload Store');
        $fixture = app(FedExShipTestCaseFixtureService::class)->fixture('IntegratorUS05');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'PDF');

        $this->assertSame(2, data_get($payload, 'requestedShipment.totalPackageCount'));
        $this->assertSame('RECIPIENT', data_get($payload, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertSame('PDF', data_get($payload, 'requestedShipment.labelSpecification.imageType'));
    }

    public function test_validation_run_rejects_wrong_locked_format(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Wrong Format Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [$account, 'testCaseKey' => 'IntegratorUS02']), [
                'label_format' => 'PDF',
            ])
            ->assertStatus(422);
    }

    public function test_us05_status_requires_both_packages(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('US05 Status Store');
        $event = $this->seedSuccessfulShipEvent($store, $account, 'IntegratorUS05', 'ship_us05_pdf_mps', 'PDF', 1);
        $this->seedLabelArtifact($store, $account, $event, 'IntegratorUS05', 'ship_us05_pdf_mps', 'PDF', 1);
        $this->seedScanArtifact($store, $account, $event, 'IntegratorUS05', 'ship_us05_pdf_mps', 'PDF', 1);

        $status = app(FedExValidationStatusPresenter::class)->capabilityMatrix($store, $account);

        $this->assertNotSame('passed', $status['ship_label_pdf']['status']);
    }

    public function test_staging_directory_secret_scan_blocks_final_export(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Secret Scan Store');

        $this->seedCompleteValidationEvidenceExceptRate($store, $account);

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'scenario_key' => 'rate_quote',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'request_body_encrypted' => ['accountNumber' => ['value' => '700257037']],
            'response_body_encrypted' => ['output' => ['rateReplyDetails' => []]],
        ]);

        $exporter = app(FedExValidationEvidenceExporter::class);
        $sanitizer = app(FedExValidationEvidenceSanitizer::class);

        $staging = storage_path('app/fedex-validation/test-secret-scan');
        File::ensureDirectoryExists($staging);
        File::put($staging.'/request.json', json_encode(['note' => 'child-secret-a leaked']));
        $blockers = $sanitizer->scanStagingDirectory($staging, ['child-secret-a']);
        File::deleteDirectory($staging);

        $this->assertNotEmpty($blockers);
    }

    public function test_redacted_placeholder_does_not_block_secret_scan(): void
    {
        $sanitizer = app(FedExValidationEvidenceSanitizer::class);
        $blockers = $sanitizer->scanForSecrets(['Authorization' => '[REDACTED]'], ['child-secret-a']);

        $this->assertEmpty($blockers);
    }

    public function test_cancel_optional_does_not_block_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Cancel Optional Store');

        config(['carriers.fedex.validation_required_scopes' => [
            FedExValidationScopeService::SCOPE_COMPREHENSIVE_RATES,
        ]]);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);

        $this->assertFalse(collect($assessment['checks'])->contains(fn (array $check): bool => ($check['key'] ?? '') === 'ship_cancel'));
    }

    public function test_tracking_required_missing_blocks_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Tracking Required Store');

        config(['carriers.fedex.validation_required_scopes' => [
            FedExValidationScopeService::SCOPE_TRACKING,
        ]]);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);

        $this->assertFalse($assessment['ready']);
        $this->assertTrue(collect($assessment['blockers'])->contains(fn (array $blocker): bool => ($blocker['key'] ?? '') === 'tracking'));
    }

    public function test_workspace_shows_run_controls(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('Workspace Run Controls Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Baseline API runs')
            ->assertSeeText('Run Address Validation')
            ->assertSeeText('Run locked ZPLII label')
            ->assertSeeText('Evidence cards');
    }

    public function test_document_checksum_mismatch_blocks_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Document Checksum Store');

        $relativePath = "fedex-validation/{$store->id}/uploads/cover-sheet.pdf";
        $absolutePath = storage_path('app/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, '%PDF-1.4 cover');

        FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'artifact_type' => FedExValidationArtifact::DOC_COVER_SHEET,
            'artifact_role' => FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT,
            'label' => 'Cover Sheet',
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'sha256' => hash('sha256', 'wrong-content'),
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);

        $this->assertFalse($assessment['ready']);
        $this->assertSame('incomplete', collect($assessment['checks'])->firstWhere('key', 'document_'.FedExValidationArtifact::DOC_COVER_SHEET)['status']);
    }

    public function test_rate_403_blocked_message_is_entitlement_pending(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Rate 403 Message Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'scenario_key' => 'rate_quote',
            'status' => CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 403,
            'error_code' => 'fedex_authorization_blocked',
            'request_body_encrypted' => ['rateRequestControlParameters' => []],
            'response_body_encrypted' => ['errors' => [['code' => 'FORBIDDEN.ERROR']]],
        ]);

        $check = collect(app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_COMPREHENSIVE_RATES],
        )['checks'])->firstWhere('key', 'rate_quote');

        $this->assertSame('blocked', $check['status']);
        $this->assertStringContainsString('Blocked — FedEx entitlement pending', (string) $check['explanation']);
    }

    public function test_validation_workspace_invoice_mfa_run_records_evidence(): void
    {
        config([
            'carriers.fedex.mfa_invoice_validation_path' => '/registration/v2/invoice/keysgeneration',
            'carriers.fedex.sandbox.client_id' => 'fedex-parent-client',
            'carriers.fedex.sandbox.client_secret' => 'fedex-parent-secret',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
        ]);

        [$owner, $store, $account] = $this->integratorAccountFixture('Invoice MFA Evidence Store');

        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'provider' => CarrierAccountRegistrationSession::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccountRegistrationSession::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'status' => CarrierAccountRegistrationSession::STATUS_REGISTERED,
            'account_name' => 'FedEx US Validation Test Account',
            'registration_address_json' => [
                'provider_account_number' => '700257037',
                'company_name' => 'FedEx US Validation Test Account',
                'address_line1' => '15 W 18TH ST FL 7',
                'city' => 'NEW YORK',
                'state' => 'NY',
                'postal_code' => '100114624',
                'country_code' => 'US',
            ],
        ]);
        $session->setAccountNumber('700257037');
        $session->setAccountAuthToken('fedex-account-auth-token-test', now()->addHour());
        $session->save();

        $account->forceFill(['registration_session_id' => $session->id])->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'fedex-address-refresh-txn-1',
                    'output' => [
                        'mfaOptions' => [
                            [
                                'accountAuthToken' => 'fedex-account-auth-token-refreshed',
                                'expiresIn' => 3600,
                            ],
                        ],
                    ],
                ], 200);
            }

            if (str_contains($request->url(), '/registration/v2/invoice/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'fedex-invoice-validate-txn-1',
                    'output' => [
                        'childKey' => 'child-key-after-invoice',
                        'childSecret' => 'child-secret-after-invoice',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL: '.$request->url()]]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.mfa.invoice', $account), [
                'invoice_number' => '234562278',
                'invoice_date' => now()->subMonth()->format('Y-m-d'),
                'invoice_currency' => 'USD',
                'invoice_amount' => '234.00',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('success');

        $event = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_INVOICE)
            ->latest('id')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(200, $event->http_status);
        $this->assertSame('invoice', $event->mfa_method);
        $this->assertTrue($event->hasCompleteEvidence());

        $check = collect(app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_ACCOUNT_REGISTRATION],
        )['checks'])->firstWhere('key', 'registration_invoice_validation');

        $this->assertSame('passed', $check['status']);
    }

    public function test_all_mfa_registration_scenarios_are_tracked_separately(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('MFA Separate Store');
        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $account->carrier_id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'status' => 'completed',
        ]);

        foreach (FedExValidationScenarioCatalog::registrationScenarios() as $scenarioKey => $meta) {
            CarrierApiEvent::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'registration_session_id' => $session->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
                'scenario_key' => $scenarioKey,
                'mfa_method' => $meta['mfa_method'],
                'status' => CarrierApiEvent::STATUS_SUCCEEDED,
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'http_status' => 200,
                'request_body_encrypted' => ['step' => $scenarioKey],
                'response_body_encrypted' => ['output' => []],
            ]);
        }

        $checks = collect(app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_ACCOUNT_REGISTRATION],
        )['checks']);

        foreach (array_keys(FedExValidationScenarioCatalog::registrationScenarios()) as $scenarioKey) {
            $this->assertSame('passed', $checks->firstWhere('key', $scenarioKey)['status'], $scenarioKey);
        }
    }

    public function test_trade_documents_optional_does_not_block_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Trade Optional Store');

        config(['carriers.fedex.validation_required_scopes' => [FedExValidationScopeService::SCOPE_SHIP]]);

        $this->assertFalse(
            collect(app(FedExValidationPreflightService::class)->assess($store, $account)['checks'])
                ->contains(fn (array $check): bool => ($check['key'] ?? '') === 'trade_documents')
        );
    }

    public function test_diagnostic_export_rejects_staged_secret(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Diagnostic Secret Reject Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'scenario_key' => 'rate_quote',
            'status' => CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 403,
            'request_body_encrypted' => ['note' => 'child-secret-a leaked in request'],
            'response_body_encrypted' => ['errors' => []],
        ]);

        $this->expectException(HttpException::class);

        app(FedExValidationEvidenceExporter::class)->exportDiagnostic($store, $account);
    }

    private function seedCompleteValidationEvidenceExceptRate(Store $store, CarrierAccount $account): void
    {
        foreach (['address_validation', 'service_availability'] as $scenario) {
            CarrierApiEvent::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'action' => $scenario === 'address_validation'
                    ? CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION
                    : CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY,
                'scenario_key' => $scenario,
                'status' => CarrierApiEvent::STATUS_SUCCEEDED,
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'http_status' => 200,
                'request_body_encrypted' => ['baseline' => true],
                'response_body_encrypted' => ['output' => []],
            ]);
        }
    }

    private function seedSuccessfulShipEvent(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        string $scenarioKey,
        string $labelFormat,
        int $packageSequence,
        bool $secondRun = false,
    ): CarrierApiEvent {
        return CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => $labelFormat,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'request_body_encrypted' => ['requestedShipment' => ['serviceType' => 'FEDEX_GROUND']],
            'response_body_encrypted' => ['output' => ['transactionShipments' => []]],
            'created_at' => $secondRun ? now()->addMinute() : now(),
            'updated_at' => $secondRun ? now()->addMinute() : now(),
        ]);
    }

    private function seedLabelArtifact(
        Store $store,
        CarrierAccount $account,
        CarrierApiEvent $event,
        string $testCaseKey,
        string $scenarioKey,
        string $labelFormat,
        int $packageSequence,
    ): FedExValidationArtifact {
        $relativePath = "fedex-validation/{$store->id}/labels/test-{$testCaseKey}-{$packageSequence}.pdf";
        $absolutePath = storage_path('app/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, '%PDF-1.4 test');

        return FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $event->id,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => $labelFormat,
            'package_sequence' => $packageSequence,
            'artifact_type' => 'ship_label_'.strtolower($labelFormat),
            'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
            'label' => $testCaseKey.' package '.$packageSequence,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'sha256' => hash('sha256', '%PDF-1.4 test'),
        ]);
    }

    private function seedScanArtifact(
        Store $store,
        CarrierAccount $account,
        CarrierApiEvent $event,
        string $testCaseKey,
        string $scenarioKey,
        string $labelFormat,
        int $packageSequence,
    ): FedExValidationArtifact {
        $relativePath = "fedex-validation/{$store->id}/uploads/scan-{$testCaseKey}-{$packageSequence}.pdf";
        $absolutePath = storage_path('app/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, '%PDF-1.4 scan');

        return FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $event->id,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => $labelFormat,
            'package_sequence' => $packageSequence,
            'artifact_type' => 'printed_scan_'.$testCaseKey.'_'.$packageSequence,
            'artifact_role' => FedExValidationArtifact::ROLE_PRINTED_SCAN,
            'label' => 'Scan '.$testCaseKey.' package '.$packageSequence,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => 11,
            'sha256' => hash('sha256', '%PDF-1.4 scan'),
            'scan_dpi' => 600,
        ]);
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function integratorAccountFixture(string $name): array
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
