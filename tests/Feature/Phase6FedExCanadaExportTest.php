<?php

namespace Tests\Feature;

use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use App\Services\Carriers\FedEx\Validation\FedExGlobalRegionalPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExGlobalShipCaseCatalog;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use Illuminate\Support\Facades\File;
use Tests\Support\FedExShipTestEvidenceFactory;
use ZipArchive;

class Phase6FedExCanadaExportTest extends Phase6FedExCanadaShipValidationTest
{
    public function test_canada_preflight_checks_include_canonical_event_and_artifact_ids(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx Canada Preflight IDs Store');

        $event = $this->seedCanadaShipEvent($store, $account, 'IntegratorCA01', 'ship_ca01_pdf', 'PDF');
        $this->seedCanadaLabelArtifact($store, $account, $event, 'IntegratorCA01', 'ship_ca01_pdf', 'PDF', 1);
        $scan = $this->seedCanadaScanArtifact($store, $account, $event, 'IntegratorCA01', 'ship_ca01_pdf', 'PDF', 1);

        $checks = app(FedExGlobalRegionalPreflightService::class)->assessCanada($store, $account)['checks'];
        $eventCheck = collect($checks)->firstWhere('key', 'ship_ca01_pdf_event');
        $scanCheck = collect($checks)->firstWhere('key', 'ship_ca01_pdf_scan_1');
        $pdfRepresentative = collect($checks)->firstWhere('key', 'ca_transaction_representative_pdf');

        $this->assertSame('passed', $eventCheck['status'] ?? null);
        $this->assertSame($event->id, $eventCheck['event_id'] ?? null);
        $this->assertSame('passed', $scanCheck['status'] ?? null);
        $this->assertSame($scan->id, $scanCheck['artifact_id'] ?? null);
        $this->assertSame($event->id, $scanCheck['event_id'] ?? null);
        $this->assertSame('passed', $pdfRepresentative['status'] ?? null);
        $this->assertSame($event->id, $pdfRepresentative['event_id'] ?? null);
    }

    public function test_diagnostic_export_includes_canada_territory_evidence(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx Canada Export Store');

        $event = $this->seedCanadaShipEvent($store, $account, 'IntegratorCA01', 'ship_ca01_pdf', 'PDF');
        $this->seedCanadaLabelArtifact($store, $account, $event, 'IntegratorCA01', 'ship_ca01_pdf', 'PDF', 1);
        $this->seedCanadaScanArtifact($store, $account, $event, 'IntegratorCA01', 'ship_ca01_pdf', 'PDF', 1);

        $zipPath = app(FedExValidationEvidenceExporter::class)->exportDiagnostic($store, $account);
        $zip = new ZipArchive;
        $zip->open($zipPath);

        $root = 'FedEx_Integrator_Validation_BaasPlatformFedExSandbox';
        $this->assertNotFalse($zip->getFromName($root.'/08_global_territories/ca/integratorca01/request.json'));
        $this->assertNotFalse($zip->getFromName($root.'/08_global_territories/ca/integratorca01/response.json'));
        $this->assertNotFalse($zip->getFromName($root.'/08_global_territories/ca/integratorca01/result_summary.json'));
        $this->assertNotFalse($zip->getFromName($root.'/08_global_territories/scope_notes/americas_scope.json'));
        $this->assertNotFalse($zip->getFromName($root.'/08_global_territories/ca/integratorca01/generated/package-1.pdf'));
        $this->assertNotFalse($zip->getFromName($root.'/08_global_territories/ca/integratorca01/printed_scans/package-1.pdf'));

        $index = json_decode((string) $zip->getFromName($root.'/evidence-index.json'), true);
        $readme = (string) $zip->getFromName($root.'/README.md');
        $zip->close();

        $this->assertSame('US + CA (Americas)', $index['region'] ?? null);
        $this->assertSame(['US', 'CA'], $index['validation_regions'] ?? null);
        $this->assertStringContainsString('US + CA (Americas)', $readme);

        $caEventCheck = collect($index['requirements'] ?? [])->firstWhere('key', 'ship_ca01_pdf_event');
        $this->assertSame($event->id, $caEventCheck['event_id'] ?? null);
    }

    private function seedCanadaShipEvent(
        Store $store,
        $account,
        string $testCaseKey,
        string $scenarioKey,
        string $labelFormat,
    ): CarrierApiEvent {
        $bodies = FedExShipTestEvidenceFactory::eventBodies($account, $testCaseKey);

        return CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => $account->provider,
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => $labelFormat,
            'validation_region' => FedExGlobalShipCaseCatalog::REGION_CA,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => $account->environment,
            'http_status' => 200,
            'http_method' => 'POST',
            'endpoint' => '/ship/v1/shipments',
            'request_body_encrypted' => $bodies['request'],
            'response_body_encrypted' => $bodies['response'],
        ]);
    }

    private function seedCanadaLabelArtifact(
        Store $store,
        $account,
        CarrierApiEvent $event,
        string $testCaseKey,
        string $scenarioKey,
        string $labelFormat,
        int $packageSequence,
    ): FedExValidationArtifact {
        $relativePath = "fedex-validation/{$store->id}/labels/test-{$testCaseKey}-{$packageSequence}.".strtolower($labelFormat === 'ZPLII' ? 'zpl' : ($labelFormat === 'PNG' ? 'png' : 'pdf'));
        $absolutePath = storage_path('app/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        $binary = match ($labelFormat) {
            'PNG' => FedExShipTestEvidenceFactory::validPngBinary(),
            'ZPLII', 'ZPL' => FedExShipTestEvidenceFactory::validZplBinary(),
            default => FedExShipTestEvidenceFactory::validPdfBinary(),
        };
        File::put($absolutePath, $binary);

        return FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $event->id,
            'environment' => $account->environment,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => $labelFormat,
            'package_sequence' => $packageSequence,
            'artifact_type' => 'ship_label_'.strtolower($labelFormat),
            'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
            'label' => $testCaseKey.' package '.$packageSequence,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
        ]);
    }

    private function seedCanadaScanArtifact(
        Store $store,
        $account,
        CarrierApiEvent $event,
        string $testCaseKey,
        string $scenarioKey,
        string $labelFormat,
        int $packageSequence,
    ): FedExValidationArtifact {
        $relativePath = "fedex-validation/{$store->id}/uploads/scan-{$testCaseKey}-{$packageSequence}.pdf";
        $absolutePath = storage_path('app/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        $binary = FedExShipTestEvidenceFactory::validPdfBinary().'-scan';
        File::put($absolutePath, $binary);

        return FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $event->id,
            'environment' => $account->environment,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => $labelFormat,
            'package_sequence' => $packageSequence,
            'artifact_type' => 'printed_scan_'.$testCaseKey.'_'.$packageSequence,
            'artifact_role' => FedExValidationArtifact::ROLE_PRINTED_SCAN,
            'label' => 'Scan '.$testCaseKey.' package '.$packageSequence,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($binary),
            'sha256' => hash('sha256', $binary),
            'scan_dpi' => 600,
            'metadata_json' => [
                'printed_scan_attestation' => true,
                'scan_dpi_claimed' => 600,
                'scan_dpi_source' => 'user_attested',
            ],
        ]);
    }
}
