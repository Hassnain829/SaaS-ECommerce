<?php

namespace Tests\Feature;

use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\FedExShipTestEvidenceFactory;

class FedExValidationEvidenceExporterUs10Test extends Phase6FedExShipValidationTest
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.validation_us10_enabled' => true,
        ]);
    }

    public function test_us10_export_copies_all_child_labels_and_cci(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx US10 Export Store');
        $event = $this->seedUs10ConfirmResultsEvidence($store, $account, labelCount: 6, withCci: true);

        $exporter = app(FedExValidationEvidenceExporter::class);
        $directory = storage_path('app/fedex-validation/'.$store->id.'/us10-export');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        $labelsCopied = $this->invokeExporterMethod($exporter, 'copyUs10ChildLabels', [
            $directory.'/labels',
            $event,
        ]);
        $cciCopied = $this->invokeExporterMethod($exporter, 'copyUs10ConsolidatedCommercialInvoice', [
            $directory.'/documents',
            $event,
        ]);

        $this->assertSame(6, $labelsCopied);
        $this->assertTrue($cciCopied);

        for ($i = 1; $i <= 6; $i++) {
            $path = $directory.'/labels/child-label-'.$i.'.pdf';
            $this->assertFileExists($path);
            $this->assertGreaterThan(0, filesize($path));
        }

        $cciPath = $directory.'/documents/Consolidated_Commercial_Invoice.pdf';
        $this->assertFileExists($cciPath);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($cciPath));

        $cciArtifact = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_type', 'consolidation_commercial_invoice')
            ->firstOrFail();
        $this->assertSame($cciArtifact->sha256, hash_file('sha256', $cciPath));
    }

    public function test_us10_final_export_blocks_when_labels_missing(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx US10 Export Block Store');
        $this->seedUs10ConfirmResultsEvidence($store, $account, labelCount: 2, withCci: true);

        $exporter = app(FedExValidationEvidenceExporter::class);
        $directory = storage_path('app/fedex-validation/'.$store->id.'/us10-export-blocked');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        // Diagnostic mode still copies what exists; final mode must fail when expected labels are incomplete.
        $this->invokeExporterMethod($exporter, 'exportUs10Consolidation', [
            $directory,
            $store,
            $account,
            ['checks' => []],
            false,
            'diagnostic',
        ]);
        $this->assertLessThan(6, count(glob($directory.'/labels/*') ?: []));

        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        try {
            $this->invokeExporterMethod($exporter, 'exportUs10Consolidation', [
                $directory,
                $store,
                $account,
                ['checks' => []],
                false,
                'final',
            ]);
            $this->fail('Expected final US10 export to block when child labels are incomplete.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('child labels', $exception->getMessage());
        }
    }

    public function test_us10_final_export_is_waived_when_excluded(): void
    {
        config(['carriers.fedex.validation_us10_enabled' => false]);
        [, $store, $account] = $this->integratorAccountFixture('FedEx US10 Export Excluded Store');

        $exporter = app(FedExValidationEvidenceExporter::class);
        $directory = storage_path('app/fedex-validation/'.$store->id.'/us10-export-excluded');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        $this->invokeExporterMethod($exporter, 'exportUs10Consolidation', [
            $directory,
            $store,
            $account,
            ['checks' => []],
            false,
            'final',
        ]);

        $this->assertFileExists($directory.'/excluded.json');
        $readme = json_decode((string) file_get_contents($directory.'/README.json'), true);
        $this->assertSame('excluded', $readme['status'] ?? null);
        $this->assertStringContainsString('Consolidation API is not a supported capability', (string) ($readme['note'] ?? ''));
    }

    /**
     * @return CarrierApiEvent
     */
    private function seedUs10ConfirmResultsEvidence(
        $store,
        $account,
        int $labelCount,
        bool $withCci,
    ): CarrierApiEvent {
        $labelPdf = FedExShipTestEvidenceFactory::validPdfBinary();
        $cciPdf = "%PDF-1.4\nus10-cci\n%%EOF";

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
            'request_body_encrypted' => [
                'jobId' => 'LIVE-JOB-EXPORT',
                'accountNumber' => ['value' => '510087100'],
            ],
            'response_body_encrypted' => ['output' => ['status' => 'COMPLETED']],
            'request_summary' => ['operation' => 'confirm_results'],
            'response_summary' => ['http_status' => 200],
        ]);

        for ($i = 1; $i <= $labelCount; $i++) {
            $relative = "fedex-validation/{$store->id}/labels/us10-export-{$i}.pdf";
            $absolute = storage_path('app/'.$relative);
            File::ensureDirectoryExists(dirname($absolute));
            File::put($absolute, $labelPdf);

            FedExValidationArtifact::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'carrier_api_event_id' => $event->id,
                'environment' => 'sandbox',
                'artifact_type' => 'ship_label_pdf',
                'scenario_key' => 'consolidation_us10_confirm_results',
                'test_case_key' => 'IntegratorUS10_CONFIRM_RESULTS',
                'label_format' => 'PDF',
                'package_sequence' => $i,
                'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
                'label' => 'child '.$i,
                'original_filename' => "us10-export-{$i}.pdf",
                'mime_type' => 'application/pdf',
                'file_size' => strlen($labelPdf),
                'sha256' => hash('sha256', $labelPdf),
                'file_path' => $relative,
            ]);
        }

        if ($withCci) {
            $relative = "fedex-validation/{$store->id}/documents/us10-export-cci.pdf";
            $absolute = storage_path('app/'.$relative);
            File::ensureDirectoryExists(dirname($absolute));
            File::put($absolute, $cciPdf);

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
                'original_filename' => 'us10-export-cci.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => strlen($cciPdf),
                'sha256' => hash('sha256', $cciPdf),
                'file_path' => $relative,
            ]);
        }

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
