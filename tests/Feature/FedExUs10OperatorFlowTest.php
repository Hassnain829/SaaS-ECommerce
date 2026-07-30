<?php

namespace Tests\Feature;

use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;
use Illuminate\Support\Facades\Http;
use Tests\Support\FedExShipTestEvidenceFactory;

class FedExUs10OperatorFlowTest extends Phase6FedExShipValidationTest
{
    private const ACCOUNT = '510087100';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.ship_evidence_enabled' => true,
            'carriers.fedex.validation_us10_enabled' => true,
            'carriers.fedex.validation_us10_consolidation_account' => self::ACCOUNT,
            'carriers.fedex.validation_us10_shipper_tin' => 'TIN-US10',
            'carriers.fedex.consolidation_create_path' => '/ship/v1/consolidations',
            'carriers.fedex.consolidation_shipment_path' => '/ship/v1/consolidations/shipments',
            'carriers.fedex.consolidation_confirm_path' => '/ship/v1/consolidations/confirmations',
            'carriers.fedex.consolidation_confirm_results_path' => '/ship/v1/consolidations/confirmationresults',
        ]);
    }

    public function test_workspace_shows_excluded_notice_when_us10_disabled(): void
    {
        config(['carriers.fedex.validation_us10_enabled' => false]);
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US10 Disabled Workspace Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('IntegratorUS10 Consolidation / IPD is excluded because Consolidation API is not a supported capability of this application')
            ->assertDontSee('Run IntegratorUS10 Consolidation Chain', false);
    }

    public function test_us10_route_blocked_when_disabled(): void
    {
        config(['carriers.fedex.validation_us10_enabled' => false]);
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US10 Disabled Run Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation', $account), [
                'confirm_us10_consolidation' => '1',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_workspace_shows_us10_card_and_confirmation(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US10 Workspace Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('IntegratorUS10 — Consolidation / IPD')
            ->assertSeeText('I understand this creates a sandbox Consolidation / IPD workflow.')
            ->assertSeeText('Chain checklist')
            ->assertSeeText('Next step')
            ->assertSee(route('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation', $account), false);
    }

    public function test_us10_confirmation_checkbox_required(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US10 Confirm Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation', $account))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHasErrors('confirm_us10_consolidation');

        Http::assertNothingSent();
    }

    public function test_us10_chain_persists_labels_and_cci_and_returns_evidence_fields(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US10 Create Store');
        $labelPdf = FedExShipTestEvidenceFactory::validPdfBinary();
        $cciPdf = "%PDF-1.4\ncci-us10\n%%EOF";
        $shipmentCalls = 0;

        Http::fake(function ($request) use (&$shipmentCalls, $labelPdf, $cciPdf) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-us10', 'expires_in' => 3600], 200);
            }

            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/ship/v1/consolidations')
                && ! str_contains($url, 'shipments')
                && ! str_contains($url, 'confirmation')) {
                return Http::response([
                    'output' => [
                        'consolidationKey' => [
                            'type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION',
                            'index' => 'LIVE-INDEX-US10',
                            'date' => '2026-07-11',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/shipments')) {
                $shipmentCalls++;

                return Http::response(['output' => ['alerts' => []]], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmations')
                && ! str_contains($url, 'confirmationresults')) {
                return Http::response(['output' => ['jobId' => 'LIVE-JOB-US10']], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmationresults')) {
                $packageDocuments = [];
                for ($i = 1; $i <= 6; $i++) {
                    $packageDocuments[] = [
                        'contentType' => 'LABEL',
                        'imageType' => 'PDF',
                        'encodedLabel' => base64_encode($labelPdf),
                    ];
                }

                return Http::response([
                    'output' => [
                        'status' => 'COMPLETED',
                        'completedConsolidationDetail' => [
                            'masterTrackingNumber' => 'TRACK-MASTER-US10',
                            'packageDocuments' => $packageDocuments,
                            'consolidationDocuments' => [[
                                'contentType' => 'CONSOLIDATION_COMMERCIAL_INVOICE',
                                'docType' => 'PDF',
                                'encodedLabel' => base64_encode($cciPdf),
                            ]],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation', $account), [
                'confirm_us10_consolidation' => '1',
            ]);

        $response->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account));
        $this->assertTrue(session()->has('success') || session()->has('warning'));
        $this->assertSame(6, $shipmentCalls);

        $flash = (string) (session('success') ?? session('warning') ?? session('error') ?? '');
        $this->assertStringNotContainsString(self::ACCOUNT, $flash);
        $this->assertStringNotContainsString('LIVE-JOB-US10', $flash);
        $this->assertStringNotContainsString('LIVE-INDEX-US10', $flash);

        $events = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_CONSOLIDATION)
            ->get();
        $this->assertGreaterThanOrEqual(9, $events->count());

        $labelCount = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_GENERATED_LABEL)
            ->where('test_case_key', 'IntegratorUS10_CONFIRM_RESULTS')
            ->count();
        $this->assertSame(6, $labelCount);

        $cci = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('artifact_type', 'consolidation_commercial_invoice')
            ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
            ->first();
        $this->assertNotNull($cci);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents((string) $cci->absolutePath()));

        $assessment = app(\App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService::class)
            ->assess($store, $account);
        $labelsCheck = collect($assessment['checks'])->firstWhere('key', 'consolidation_us10_child_labels');
        $cciCheck = collect($assessment['checks'])->firstWhere('key', 'consolidation_us10_cci');
        $this->assertSame('passed', $labelsCheck['status'] ?? null);
        $this->assertSame(6, $labelsCheck['artifact_count'] ?? null);
        $this->assertSame('passed', $cciCheck['status'] ?? null);
    }

    public function test_us10_requires_dedicated_account_config(): void
    {
        config(['carriers.fedex.validation_us10_consolidation_account' => '']);
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US10 Missing Account Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation', $account), [
                'confirm_us10_consolidation' => '1',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_us10_requires_shipper_tin_before_network(): void
    {
        config(['carriers.fedex.validation_us10_shipper_tin' => '']);
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US10 Missing TIN Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Shipper TIN')
            ->assertSeeText('Missing');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation', $account), [
                'confirm_us10_consolidation' => '1',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_us10_preflight_fails_when_child_labels_incomplete(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx US10 Incomplete Labels Store');
        $labelPdf = FedExShipTestEvidenceFactory::validPdfBinary();
        $cciPdf = "%PDF-1.4\ncci\n%%EOF";

        $event = CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => \App\Models\CarrierAccount::PROVIDER_FEDEX,
            'environment' => 'sandbox',
            'action' => CarrierApiEvent::ACTION_FEDEX_CONSOLIDATION,
            'http_method' => 'POST',
            'endpoint' => '/ship/v1/consolidations/confirmationresults',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'http_status' => 200,
            'scenario_key' => 'consolidation_us10_confirm_results',
            'test_case_key' => 'IntegratorUS10_CONFIRM_RESULTS',
            'request_body_encrypted' => ['jobId' => 'x'],
            'response_body_encrypted' => ['output' => ['status' => 'COMPLETED']],
            'request_summary' => ['operation' => 'confirm_results'],
            'response_summary' => ['http_status' => 200],
        ]);

        $relative = "fedex-validation/{$store->id}/labels/us10-only-one.pdf";
        $absolute = storage_path('app/'.$relative);
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($absolute));
        \Illuminate\Support\Facades\File::put($absolute, $labelPdf);

        FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $event->id,
            'environment' => 'sandbox',
            'artifact_type' => 'ship_label_pdf',
            'scenario_key' => 'consolidation_us10_confirm_results',
            'test_case_key' => 'IntegratorUS10_CONFIRM_RESULTS',
            'label_format' => 'PDF',
            'package_sequence' => 1,
            'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
            'label' => 'one label',
            'original_filename' => 'us10-only-one.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($labelPdf),
            'sha256' => hash('sha256', $labelPdf),
            'file_path' => $relative,
        ]);

        $cciRelative = "fedex-validation/{$store->id}/documents/us10-cci.pdf";
        $cciAbsolute = storage_path('app/'.$cciRelative);
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($cciAbsolute));
        \Illuminate\Support\Facades\File::put($cciAbsolute, $cciPdf);

        FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $event->id,
            'environment' => 'sandbox',
            'artifact_type' => 'consolidation_commercial_invoice',
            'scenario_key' => 'consolidation_us10_confirm_results',
            'test_case_key' => 'IntegratorUS10_CONFIRM_RESULTS',
            'label_format' => 'PDF',
            'artifact_role' => FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT,
            'label' => 'cci',
            'original_filename' => 'us10-cci.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($cciPdf),
            'sha256' => hash('sha256', $cciPdf),
            'file_path' => $cciRelative,
        ]);

        $assessment = app(\App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService::class)
            ->assess($store, $account);
        $labelsCheck = collect($assessment['checks'])->firstWhere('key', 'consolidation_us10_child_labels');
        $cciCheck = collect($assessment['checks'])->firstWhere('key', 'consolidation_us10_cci');

        $this->assertSame('incomplete', $labelsCheck['status'] ?? null);
        $this->assertSame(1, $labelsCheck['artifact_count'] ?? null);
        $this->assertSame(6, $labelsCheck['expected_count'] ?? null);
        $this->assertSame('passed', $cciCheck['status'] ?? null);
    }

    public function test_us10_does_not_use_historical_workbook_identifiers(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US10 Dynamic Key Store');
        $captured = [];

        Http::fake(function ($request) use (&$captured) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-us10', 'expires_in' => 3600], 200);
            }

            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/ship/v1/consolidations')
                && ! str_contains($url, 'shipments')
                && ! str_contains($url, 'confirmation')) {
                return Http::response([
                    'output' => [
                        'consolidationKey' => [
                            'type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION',
                            'index' => 'DYN-999',
                            'date' => '2026-07-12',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/shipments')) {
                $captured[] = $request->data();

                return Http::response(['output' => []], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmations')
                && ! str_contains($url, 'confirmationresults')) {
                return Http::response(['output' => ['jobId' => 'DYN-JOB-1']], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmationresults')) {
                return Http::response([
                    'output' => [
                        'status' => 'COMPLETED',
                        'completedConsolidationDetail' => ['masterTrackingNumber' => 'M'],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us10.consolidation', $account), [
                'confirm_us10_consolidation' => '1',
            ])
            ->assertRedirect();

        $this->assertNotEmpty($captured);
        foreach ($captured as $payload) {
            $this->assertSame('DYN-999', data_get($payload, 'consolidationKey.index'));
            $this->assertNotSame(FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_INDEX, data_get($payload, 'consolidationKey.index'));
        }
    }
}
