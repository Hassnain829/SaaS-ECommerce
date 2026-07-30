<?php

namespace Tests\Feature;

use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Operations\FedExFreightLtlResponseParser;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\FedExShipTestEvidenceFactory;
use ZipArchive;

class FedExUs08EvidenceExportTest extends Phase6FedExShipValidationTest
{
    private const FREIGHT_ACCOUNT = '631234540';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.ship_evidence_enabled' => true,
            'carriers.fedex.validation_us08_enabled' => true,
            'carriers.fedex.freight_ltl_api_enabled' => true,
            'carriers.fedex.validation_us08_freight_account' => self::FREIGHT_ACCOUNT,
            'carriers.fedex.freight_ltl_ship_path' => '/ship/v1/freight/shipments',
        ]);
    }

    public function test_diagnostic_zip_contains_us08_bol_and_commercial_invoice(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx US08 Diagnostic Export Store');
        $this->seedUs08FreightEvidence($store, $account, withCommercialInvoice: true);

        $zipPath = app(FedExValidationEvidenceExporter::class)->exportDiagnostic($store, $account);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));

        $root = 'FedEx_Integrator_Validation_BaasPlatformFedExSandbox';
        $bol = $zip->getFromName("{$root}/07c_ship_us08_zplii/documents/Straight_Bill_of_Lading.pdf");
        $ci = $zip->getFromName("{$root}/07c_ship_us08_zplii/documents/Commercial_Invoice.pdf");
        $summary = json_decode((string) $zip->getFromName("{$root}/07c_ship_us08_zplii/result_summary.json"), true);
        $zip->close();

        $this->assertNotFalse($bol);
        $this->assertNotFalse($ci);
        $this->assertNotSame('', $bol);
        $this->assertStringStartsWith('%PDF', (string) $bol);
        $this->assertStringStartsWith('%PDF', (string) $ci);
        $this->assertSame('FEDEX_FREIGHT_PRIORITY', $summary['request_service_type'] ?? null);
        $this->assertSame('/ship/v1/freight/shipments', $summary['endpoint'] ?? null);
        $this->assertTrue((bool) ($summary['freight_bol_present'] ?? false));
        $this->assertArrayNotHasKey('encoded_label', $summary);
        $this->assertStringNotContainsString('%PDF', json_encode($summary));
    }

    public function test_final_mode_copies_us08_bol_and_blocks_when_missing(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx US08 Final Export Store');
        $event = $this->seedUs08FreightEvidence($store, $account, withCommercialInvoice: true);

        $exporter = app(FedExValidationEvidenceExporter::class);
        $directory = storage_path('app/fedex-validation/'.$store->id.'/us08-final-scenario');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        $preflight = [
            'checks' => [
                [
                    'key' => 'ship_us08_zplii_event',
                    'status' => 'passed',
                    'required' => true,
                    'event_id' => $event->id,
                ],
                [
                    'key' => 'ship_us08_zplii_bol',
                    'status' => 'passed',
                    'required' => true,
                    'category' => 'ship',
                ],
            ],
        ];

        $this->invokeExporterMethod($exporter, 'exportLockedShipScenario', [
            $directory,
            $store,
            $account,
            'IntegratorUS08',
            $preflight,
            false,
            'final',
        ]);

        $bolPath = $directory.'/documents/Straight_Bill_of_Lading.pdf';
        $ciPath = $directory.'/documents/Commercial_Invoice.pdf';
        $this->assertFileExists($bolPath);
        $this->assertFileExists($ciPath);
        $this->assertGreaterThan(0, filesize($bolPath));
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($bolPath));

        $bolArtifact = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_type', 'freight_bill_of_lading')
            ->firstOrFail();
        $this->assertSame($bolArtifact->sha256, hash_file('sha256', $bolPath));

        // Simulate a final submission tree and verify integrity accepts the copied BOL.
        $bundleDir = storage_path('app/fedex-validation/'.$store->id.'/us08-final-bundle');
        File::deleteDirectory($bundleDir);
        File::ensureDirectoryExists($bundleDir.'/07c_ship_us08_zplii/documents');
        File::copy($bolPath, $bundleDir.'/07c_ship_us08_zplii/documents/Straight_Bill_of_Lading.pdf');
        $this->invokeExporterMethod($exporter, 'verifyFinalExportIntegrity', [$bundleDir, $preflight]);

        // Corrupt/missing BOL must block final copy.
        File::delete($bolArtifact->absolutePath());
        $blockedDir = storage_path('app/fedex-validation/'.$store->id.'/us08-final-blocked');
        File::deleteDirectory($blockedDir);
        File::ensureDirectoryExists($blockedDir);

        try {
            $this->invokeExporterMethod($exporter, 'exportLockedShipScenario', [
                $blockedDir,
                $store,
                $account,
                'IntegratorUS08',
                $preflight,
                false,
                'final',
            ]);
            $this->fail('Expected final export to block when BOL binary is missing.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('Straight Bill of Lading', $exception->getMessage());
        }
    }

    public function test_final_integrity_fails_when_expected_bol_was_not_copied(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx US08 Integrity Store');
        $exporter = app(FedExValidationEvidenceExporter::class);
        $bundleDir = storage_path('app/fedex-validation/'.$store->id.'/us08-integrity-bundle');
        File::deleteDirectory($bundleDir);
        File::ensureDirectoryExists($bundleDir.'/07c_ship_us08_zplii/documents');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Straight_Bill_of_Lading.pdf');

        $this->invokeExporterMethod($exporter, 'verifyFinalExportIntegrity', [
            $bundleDir,
            [
                'checks' => [
                    [
                        'key' => 'ship_us08_zplii_event',
                        'status' => 'passed',
                        'required' => true,
                    ],
                    [
                        'key' => 'ship_us08_zplii_bol',
                        'status' => 'passed',
                        'required' => true,
                        'category' => 'ship',
                    ],
                ],
            ],
        ]);
    }

    private function seedUs08FreightEvidence($store, $account, bool $withCommercialInvoice = false): CarrierApiEvent
    {
        $account->forceFill(['provider_account_number' => self::FREIGHT_ACCOUNT])->save();

        Http::fake(function ($request) use ($withCommercialInvoice) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'child-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/ship/v1/freight/shipments')) {
                $docs = [[
                    'contentType' => FedExFreightLtlResponseParser::CONTENT_TYPE_BOL,
                    'docType' => 'PDF',
                    'imageType' => 'PDF',
                    'encodedLabel' => base64_encode(FedExShipTestEvidenceFactory::validPdfBinary()),
                ]];
                if ($withCommercialInvoice) {
                    $docs[] = [
                        'contentType' => FedExFreightLtlResponseParser::CONTENT_TYPE_COMMERCIAL_INVOICE,
                        'docType' => 'PDF',
                        'imageType' => 'PDF',
                        'encodedLabel' => base64_encode("%PDF-1.4\nci\n%%EOF"),
                    ];
                }

                return Http::response([
                    'transactionId' => 'fedex-freight-us08-export',
                    'output' => [
                        'transactionShipments' => [[
                            'serviceType' => 'FEDEX_FREIGHT_PRIORITY',
                            'masterTrackingNumber' => '794612345678',
                            'pieceResponses' => [[
                                'packageSequenceNumber' => 1,
                                'trackingNumber' => '794612345678',
                                'packageDocuments' => [[
                                    'contentType' => 'LABEL',
                                    'docType' => 'ZPLII',
                                    'imageType' => 'ZPLII',
                                    'encodedLabel' => base64_encode(FedExShipTestEvidenceFactory::validZplBinary()),
                                ]],
                            ]],
                            'shipmentDocuments' => $docs,
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['code' => 'NOT.FOUND']]], 404);
        });

        app(\App\Services\Carriers\FedEx\Operations\FedExFreightLtlService::class)->createShipment(
            store: $store,
            account: $account,
            testCaseKey: 'IntegratorUS08',
        );

        return CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('test_case_key', 'IntegratorUS08')
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * @param  list<mixed>  $args
     */
    private function invokeExporterMethod(FedExValidationEvidenceExporter $exporter, string $method, array $args): mixed
    {
        $reflection = new ReflectionClass($exporter);
        $refMethod = $reflection->getMethod($method);
        $refMethod->setAccessible(true);

        return $refMethod->invokeArgs($exporter, $args);
    }
}
