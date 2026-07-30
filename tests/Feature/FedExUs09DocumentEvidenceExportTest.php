<?php

namespace Tests\Feature;

use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Validation\FedExUs09EtdFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\FedExShipTestEvidenceFactory;

class FedExUs09DocumentEvidenceExportTest extends Phase6FedExShipValidationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.ship_evidence_enabled' => true,
        ]);
    }

    public function test_final_export_copies_document_case_commercial_invoice_from_package_when_ship_omits_it(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx US09 Document Export Store');
        $event = $this->seedUs09DocumentShipWithoutReturnedInvoice($store->id, $account);

        $fixturePath = app(FedExUs09EtdFixtureService::class)->documentCommercialInvoiceAbsolutePath();
        $this->assertNotNull($fixturePath);
        $this->assertFileExists($fixturePath);

        $exporter = app(FedExValidationEvidenceExporter::class);
        $directory = storage_path('app/fedex-validation/'.$store->id.'/us09-document-export');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        $this->invokeExporterMethod($exporter, 'copyUs09ValidationDocuments', [
            $directory,
            $event,
            'final',
            'IntegratorUS09_DOCUMENT',
        ]);

        $ciPath = $directory.'/Commercial_Invoice.pdf';
        $this->assertFileExists($ciPath);
        $this->assertGreaterThan(0, filesize($ciPath));
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($ciPath));
        $this->assertSame(hash_file('sha256', $fixturePath), hash_file('sha256', $ciPath));
    }

    public function test_final_export_blocks_image_case_when_commercial_invoice_missing(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx US09 Image Export Block Store');
        $event = $this->seedUs09DocumentShipWithoutReturnedInvoice(
            $store->id,
            $account,
            testCaseKey: 'IntegratorUS09_IMAGE',
            scenarioKey: 'ship_us09_image_pdf',
        );

        $exporter = app(FedExValidationEvidenceExporter::class);
        $directory = storage_path('app/fedex-validation/'.$store->id.'/us09-image-export-blocked');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        try {
            $this->invokeExporterMethod($exporter, 'copyUs09ValidationDocuments', [
                $directory,
                $event,
                'final',
                'IntegratorUS09_IMAGE',
            ]);
            $this->fail('Expected final IMAGE export to block without ship-returned Commercial Invoice.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('IntegratorUS09_IMAGE', $exception->getMessage());
            $this->assertStringContainsString('Commercial Invoice', $exception->getMessage());
        }
    }

    private function seedUs09DocumentShipWithoutReturnedInvoice(
        int $storeId,
        $account,
        string $testCaseKey = 'IntegratorUS09_DOCUMENT',
        string $scenarioKey = 'ship_us09_document_pdf',
    ): CarrierApiEvent {
        $bodies = FedExShipTestEvidenceFactory::eventBodies($account, $testCaseKey);
        if ($testCaseKey === 'IntegratorUS09_DOCUMENT') {
            $bodies['request']['requestedShipment']['shipmentSpecialServices']['etdDetail']['attachedDocuments'][0]['documentId'] = 'DOC1234567890';
        }

        $pdf = FedExShipTestEvidenceFactory::validPdfBinary();
        $relative = 'fedex-validation/'.$storeId.'/labels/us09-doc-export-label.pdf';
        File::ensureDirectoryExists(dirname(storage_path('app/'.$relative)));
        File::put(storage_path('app/'.$relative), $pdf);

        $event = CarrierApiEvent::query()->create([
            'store_id' => $storeId,
            'carrier_account_id' => $account->id,
            'registration_session_id' => $account->registration_session_id,
            'provider' => 'fedex',
            'environment' => $account->environment,
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => 'PDF',
            'validation_region' => 'US',
            'endpoint' => '/ship/v1/shipments',
            'http_method' => 'POST',
            'http_status' => 200,
            'status' => 'succeeded',
            'request_summary' => ['test_case' => $testCaseKey],
            'response_summary' => [
                'http_status' => 200,
                'canonical_ready' => true,
            ],
            'request_body_encrypted' => $bodies['request'],
            'response_body_encrypted' => $bodies['response'],
            'fedex_transaction_id' => 'us09-doc-export-tx',
        ]);

        FedExValidationArtifact::query()->create([
            'store_id' => $storeId,
            'carrier_account_id' => $account->id,
            'registration_session_id' => $account->registration_session_id,
            'carrier_api_event_id' => $event->id,
            'environment' => $account->environment,
            'artifact_type' => 'ship_label_pdf',
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => 'PDF',
            'package_sequence' => 1,
            'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
            'label' => $testCaseKey.' label',
            'original_filename' => 'us09-doc-export-label.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
            'file_path' => $relative,
        ]);

        return $event;
    }

    private function invokeExporterMethod(FedExValidationEvidenceExporter $exporter, string $method, array $args): mixed
    {
        $reflection = new ReflectionClass($exporter);
        $fn = $reflection->getMethod($method);
        $fn->setAccessible(true);

        return $fn->invokeArgs($exporter, $args);
    }
}
