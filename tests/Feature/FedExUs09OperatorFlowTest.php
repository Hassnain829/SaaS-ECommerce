<?php

namespace Tests\Feature;

use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Validation\FedExUs09EtdFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceQueryService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\FedExShipTestEvidenceFactory;
use Tests\Support\FedExUs09TempAssetFactory;

class FedExUs09OperatorFlowTest extends Phase6FedExShipValidationTest
{
    private string $letterheadPath;

    private string $signaturePath;

    private string $documentPath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.ship_evidence_enabled' => true,
            'carriers.fedex.document_api_sandbox_base_url' => 'https://documentapitest.prod.fedex.com/sandbox',
            'carriers.fedex.trade_documents_upload_image_path' => '/documents/v1/lhsimages/upload',
            'carriers.fedex.trade_documents_upload_document_path' => '/documents/v1/etds/upload',
        ]);

        File::ensureDirectoryExists(base_path('resources/fedex-validation/us09'));
        $this->letterheadPath = base_path('resources/fedex-validation/us09/signature3.png');
        $this->signaturePath = base_path('resources/fedex-validation/us09/signature2.png');
        $this->documentPath = base_path('resources/fedex-validation/us09/commercial_invoice.pdf');

        File::copy(FedExUs09TempAssetFactory::png(80, 20), $this->letterheadPath);
        File::copy(FedExUs09TempAssetFactory::png(120, 20), $this->signaturePath);
        File::copy(FedExUs09TempAssetFactory::pdf(1200), $this->documentPath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->letterheadPath, $this->signaturePath, $this->documentPath] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_workspace_shows_dedicated_us09_cards_not_generic_parcel_route(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US09 Workspace Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('IntegratorUS09 — ETD Image')
            ->assertSeeText('IntegratorUS09 — ETD Document')
            ->assertSeeText('Generated label & printed scan')
            ->assertSeeText('Upload printed scan')
            ->assertSee(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.letterhead', $account), false)
            ->assertSee(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.image', $account), false)
            ->assertDontSee(
                route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [$account, 'testCaseKey' => 'IntegratorUS09_IMAGE']),
                false,
            )
            ->assertDontSee(
                route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [$account, 'testCaseKey' => 'IntegratorUS09_DOCUMENT']),
                false,
            );
    }

    public function test_us09_upload_confirmation_checkbox_required(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US09 Confirm Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.letterhead', $account))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHasErrors('confirm_us09_upload');

        Http::assertNothingSent();
    }

    public function test_generic_parcel_ship_route_rejects_us09(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US09 Parcel Reject Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.ship', [
                'carrierAccount' => $account,
                'testCaseKey' => 'IntegratorUS09_IMAGE',
            ]))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_letterhead_and_signature_uploads_then_image_ship_persists_label(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US09 Image Flow Store');
        $pdf = FedExShipTestEvidenceFactory::validPdfBinary();
        $shipPayload = null;

        Http::fake(function ($request) use (&$shipPayload, $pdf) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-us09', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/documents/v1/lhsimages/upload')) {
                $body = (string) $request->body();
                $index = str_contains($body, 'IMAGE_2') || str_contains($body, 'SIGNATURE') ? 'IMAGE_2' : 'IMAGE_1';

                return Http::response([
                    'output' => ['meta' => ['imageIndex' => $index]],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/shipments') && ! str_contains($url, 'validate') && ! str_contains($url, 'cancel')) {
                $shipPayload = $request->data();

                return Http::response([
                    'transactionId' => 'us09-image-ship',
                    'output' => [
                        'transactionShipments' => [[
                            'serviceType' => 'FEDEX_INTERNATIONAL_PRIORITY',
                            'masterTrackingNumber' => '794600000009',
                            'pieceResponses' => [[
                                'packageSequenceNumber' => 1,
                                'trackingNumber' => '794600000009',
                                'packageDocuments' => [[
                                    'docType' => 'LABEL',
                                    'imageType' => 'PDF',
                                    'encodedLabel' => base64_encode($pdf),
                                ]],
                            ]],
                            'shipmentDocuments' => [[
                                'contentType' => 'COMMERCIAL_INVOICE',
                                'docType' => 'PDF',
                                'imageType' => 'PDF',
                                'encodedLabel' => base64_encode($pdf),
                            ]],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.letterhead', $account), [
                'confirm_us09_upload' => '1',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('success');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.signature', $account), [
                'confirm_us09_upload' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $letterhead = app(FedExValidationEvidenceQueryService::class)->canonicalSuccessfulEvent(
            $store,
            $account,
            FedExUs09EtdFixtureService::UPLOAD_SCENARIO_LETTERHEAD,
        );
        $signature = app(FedExValidationEvidenceQueryService::class)->canonicalSuccessfulEvent(
            $store,
            $account,
            FedExUs09EtdFixtureService::UPLOAD_SCENARIO_SIGNATURE,
        );
        $this->assertNotNull($letterhead);
        $this->assertNotNull($signature);
        $this->assertSame('IMAGE_1', data_get($letterhead->request_summary, 'returned_image_index'));
        $this->assertSame('IMAGE_2', data_get($signature->request_summary, 'returned_image_index'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.image', $account), [
                'confirm_us09_ship' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('FEDEX_INTERNATIONAL_PRIORITY', data_get($shipPayload, 'requestedShipment.serviceType'));
        $this->assertContains('ELECTRONIC_TRADE_DOCUMENTS', (array) data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.specialServiceTypes'));
        $this->assertSame(['COMMERCIAL_INVOICE'], data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.requestedDocumentTypes'));
        $this->assertNull(data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.attachedDocuments'));

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('test_case_key', 'IntegratorUS09_IMAGE')
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->latest('id')
            ->first();
        $this->assertNotNull($event);

        $label = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_GENERATED_LABEL)
            ->first();
        $this->assertNotNull($label);
        $this->assertFileExists((string) $label->absolutePath());

        $invoice = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
            ->where('artifact_type', 'commercial_invoice')
            ->where('test_case_key', 'IntegratorUS09_IMAGE')
            ->first();
        $this->assertNotNull($invoice);
        $this->assertFileExists((string) $invoice->absolutePath());
    }

    public function test_document_upload_injects_doc_id_into_ship_without_hardcoding(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US09 Document Flow Store');
        $docId = 'LIVE-US09-DOC-'.bin2hex(random_bytes(4));
        $pdf = FedExShipTestEvidenceFactory::validPdfBinary();
        $shipPayload = null;

        Http::fake(function ($request) use (&$shipPayload, $docId, $pdf) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-us09', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/documents/v1/etds/upload')) {
                return Http::response(['output' => ['docId' => $docId]], 200);
            }

            if (str_contains($url, '/ship/v1/shipments') && ! str_contains($url, 'validate')) {
                $shipPayload = $request->data();

                return Http::response([
                    'transactionId' => 'us09-doc-ship',
                    'output' => [
                        'transactionShipments' => [[
                            'serviceType' => 'FEDEX_INTERNATIONAL_PRIORITY',
                            'masterTrackingNumber' => '794600000010',
                            'pieceResponses' => [[
                                'packageSequenceNumber' => 1,
                                'trackingNumber' => '794600000010',
                                'packageDocuments' => [[
                                    'docType' => 'LABEL',
                                    'imageType' => 'PDF',
                                    'encodedLabel' => base64_encode($pdf),
                                ]],
                            ]],
                            'shipmentDocuments' => [[
                                'contentType' => 'COMMERCIAL_INVOICE',
                                'docType' => 'PDF',
                                'imageType' => 'PDF',
                                'encodedLabel' => base64_encode($pdf),
                            ]],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.document', $account), [
                'confirm_us09_upload' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $upload = app(FedExValidationEvidenceQueryService::class)->canonicalSuccessfulEvent(
            $store,
            $account,
            FedExUs09EtdFixtureService::UPLOAD_SCENARIO_DOCUMENT,
        );
        $this->assertNotNull($upload);
        $maskedDocId = (string) data_get($upload->request_summary, 'returned_document_id', '');
        $this->assertNotSame($docId, $maskedDocId);
        $this->assertTrue(
            $maskedDocId === '' || str_contains(strtoupper($maskedDocId), 'REDACTED'),
            'Expected returned_document_id summary to be redacted, got: '.$maskedDocId
        );
        $this->assertStringNotContainsString($docId, json_encode($upload->request_summary) ?: '');
        $this->assertStringNotContainsString($docId, json_encode($upload->response_summary) ?: '');
        $this->assertSame($docId, \App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadService::resolveStoredDocumentId($upload));
        $this->assertStringNotContainsString($docId, (string) session('success'));

        $sanitized = app(\App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer::class)
            ->sanitize($upload->response_body_encrypted);
        $this->assertSame('[REDACTED]', data_get($sanitized, '_operator_secrets'));
        $this->assertStringNotContainsString($docId, json_encode($sanitized) ?: '');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.document', $account), [
                'confirm_us09_ship' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($docId, data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.attachedDocuments.0.documentId'));
        $this->assertNotSame('{{US09_DOCUMENT_ID}}', data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.attachedDocuments.0.documentId'));
        $this->assertSame('COMMERCIAL_INVOICE', data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.attachedDocuments.0.documentType'));
        $this->assertNull(data_get($shipPayload, 'requestedShipment.shipmentSpecialServices.etdDetail.requestedDocumentTypes'));

        $shipEvent = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('test_case_key', 'IntegratorUS09_DOCUMENT')
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->latest('id')
            ->first();
        $this->assertNotNull($shipEvent);

        $docInvoice = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $shipEvent->id)
            ->where('artifact_type', 'commercial_invoice')
            ->where('test_case_key', 'IntegratorUS09_DOCUMENT')
            ->first();
        $this->assertNotNull($docInvoice);

        $imageLeak = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $shipEvent->id)
            ->where('test_case_key', 'IntegratorUS09_IMAGE')
            ->count();
        $this->assertSame(0, $imageLeak);
    }

    public function test_us09_missing_commercial_invoice_is_not_evidence_ready(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US09 Incomplete Docs Store');
        $pdf = FedExShipTestEvidenceFactory::validPdfBinary();

        Http::fake(function ($request) use ($pdf) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-us09', 'expires_in' => 3600], 200);
            }

            if (str_contains($url, '/documents/v1/lhsimages/upload')) {
                $body = (string) $request->body();
                $index = str_contains($body, 'IMAGE_2') || str_contains($body, 'SIGNATURE') ? 'IMAGE_2' : 'IMAGE_1';

                return Http::response([
                    'output' => ['meta' => ['imageIndex' => $index]],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/shipments') && ! str_contains($url, 'validate')) {
                return Http::response([
                    'transactionId' => 'us09-image-incomplete',
                    'output' => [
                        'transactionShipments' => [[
                            'serviceType' => 'FEDEX_INTERNATIONAL_PRIORITY',
                            'masterTrackingNumber' => '794600000011',
                            'pieceResponses' => [[
                                'packageSequenceNumber' => 1,
                                'trackingNumber' => '794600000011',
                                'packageDocuments' => [[
                                    'docType' => 'LABEL',
                                    'imageType' => 'PDF',
                                    'encodedLabel' => base64_encode($pdf),
                                ]],
                            ]],
                        ]],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        foreach ([
            'settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.letterhead',
            'settings.shipping.carrier-accounts.fedex.validation.run.us09.upload.signature',
        ] as $routeName) {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->post(route($routeName, $account), ['confirm_us09_upload' => '1'])
                ->assertRedirect();
        }

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.image', $account), [
                'confirm_us09_ship' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $flash = (string) session('warning');
        $this->assertStringContainsString('incomplete', strtolower($flash));
    }

    public function test_image_ship_blocked_without_uploads(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx US09 Ship Gate Store');
        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.us09.ship.image', $account), [
                'confirm_us09_ship' => '1',
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }
}
