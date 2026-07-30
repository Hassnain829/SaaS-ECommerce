<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExShipFixtureResolver;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExUs09EtdFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExUs09EvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FedExUs09TempAssetFactory;
use Tests\TestCase;

class FedExUs09DocumentFixtureTest extends TestCase
{
    use RefreshDatabase;

    private const PARCEL_ACCOUNT = '700257037';

    private const SAMPLE_DOC_ID = 'TEST_US09_DOC_ID_ABC123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.document_api_sandbox_base_url' => 'https://documentapitest.prod.fedex.com/sandbox',
            'carriers.fedex.trade_documents_upload_image_path' => '/documents/v1/lhsimages/upload',
            'carriers.fedex.trade_documents_upload_document_path' => '/documents/v1/etds/upload',
        ]);
    }

    public function test_us09_document_fixture_upload_and_ship_match_workbook(): void
    {
        $account = $this->makeAccount();
        $fixtures = app(FedExUs09EtdFixtureService::class);
        $pdf = $this->makeTempPdf();
        $fixture = $fixtures->withUploadedDocumentId(
            $fixtures->fixture('IntegratorUS09_DOCUMENT'),
            self::SAMPLE_DOC_ID,
        );
        $upload = app(FedExTradeDocumentUploadService::class)->prepareDocumentUpload([
            'absolute_path' => $pdf,
            'filename' => 'commercial_invoice.pdf',
        ]);
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'PDF');
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($payload);

        $this->assertSame(['upload_us09_document'], FedExValidationScenarioCatalog::lockedShipScenarios()['IntegratorUS09_DOCUMENT']['upload_scenario_keys']);
        $this->assertSame('/documents/v1/etds/upload', app(FedExConfig::class)->tradeDocumentsUploadDocumentPath());
        $this->assertSame('COMMERCIAL_INVOICE', $upload['document_type']);
        $this->assertSame('FDXE', data_get($upload, 'redacted_multipart.document.carrierCode'));
        $this->assertSame('ETDPreshipment', data_get($upload, 'redacted_multipart.document.workflowName'));
        $this->assertSame('IT', data_get($upload, 'redacted_multipart.document.meta.destinationCountryCode'));
        $this->assertSame(['document', 'attachment'], data_get($upload, 'redacted_multipart.field_order'));

        $shipment = data_get($payload, 'requestedShipment');
        $this->assertContains('ELECTRONIC_TRADE_DOCUMENTS', (array) data_get($shipment, 'shipmentSpecialServices.specialServiceTypes'));
        $this->assertSame(self::SAMPLE_DOC_ID, data_get($shipment, 'shipmentSpecialServices.etdDetail.attachedDocuments.0.documentId'));
        $this->assertSame('CommercialInvoice', data_get($shipment, 'shipmentSpecialServices.etdDetail.attachedDocuments.0.description'));
        $this->assertArrayNotHasKey('requestedDocumentTypes', (array) data_get($shipment, 'shipmentSpecialServices.etdDetail', []));
        $this->assertSame([], (array) data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages', []));

        $this->assertSame('[REDACTED]', data_get($sanitized, 'requestedShipment.shipmentSpecialServices.etdDetail.attachedDocuments.0.documentId'));
        $export = app(FedExUs09EvidenceRules::class)->validateSanitizedExport(
            is_array($sanitized) ? $sanitized : [],
            'IntegratorUS09_DOCUMENT',
        );
        $this->assertTrue($export['valid'], implode(',', $export['reasons']));
    }

    public function test_image_and_document_scenarios_remain_separate(): void
    {
        $account = $this->makeAccount();
        $fixtures = app(FedExUs09EtdFixtureService::class);
        $imagePayload = app(FedExShipPayloadFactory::class)->buildShipmentPayload(
            $account,
            $fixtures->fixture('IntegratorUS09_IMAGE'),
            'PDF',
        );
        $documentPayload = app(FedExShipPayloadFactory::class)->buildShipmentPayload(
            $account,
            $fixtures->withUploadedDocumentId($fixtures->fixture('IntegratorUS09_DOCUMENT'), self::SAMPLE_DOC_ID),
            'PDF',
        );
        $rules = app(FedExUs09EvidenceRules::class);

        $imageAsDocument = $rules->validateShipRequest($imagePayload, 'IntegratorUS09_DOCUMENT');
        $this->assertFalse($imageAsDocument['valid']);

        $documentAsImage = $rules->validateShipRequest($documentPayload, 'IntegratorUS09_IMAGE');
        $this->assertFalse($documentAsImage['valid']);
        $this->assertContains('us09_image_letterhead_reference_missing', $documentAsImage['reasons']);
    }

    public function test_preflight_requires_document_upload_separately(): void
    {
        $account = $this->makeAccount();
        $assessment = app(FedExValidationPreflightService::class)->assess($account->store, $account);
        $uploadCheck = collect($assessment['checks'])->firstWhere('key', 'upload_us09_document_event');
        $labelCheck = collect($assessment['checks'])->firstWhere('key', 'ship_us09_document_pdf_event');

        $this->assertNotNull($uploadCheck);
        $this->assertSame('not_tested', $uploadCheck['status']);
        $this->assertNotNull($labelCheck);
        $this->assertSame('not_tested', $labelCheck['status']);
    }

    public function test_us01_through_us08_remain_unchanged(): void
    {
        $account = $this->makeAccount();
        $factory = app(FedExShipPayloadFactory::class);
        $fixtures = app(FedExShipTestCaseFixtureService::class);

        foreach (['IntegratorUS01', 'IntegratorUS02', 'IntegratorUS03', 'IntegratorUS04', 'IntegratorUS05', 'IntegratorUS06', 'IntegratorUS07'] as $key) {
            $payload = $factory->buildShipmentPayload($account, $fixtures->fixture($key), app(FedExShipFixtureResolver::class)->lockedLabelFormat($key));
            $this->assertArrayHasKey('requestedShipment', $payload);
            $this->assertNotContains('ELECTRONIC_TRADE_DOCUMENTS', (array) data_get($payload, 'requestedShipment.shipmentSpecialServices.specialServiceTypes', []));
            if ($key === 'IntegratorUS03') {
                $this->assertSame('Dictionaries ', data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.description'));
                $this->assertArrayNotHasKey('postalCode', (array) data_get($payload, 'requestedShipment.customsClearanceDetail.dutiesPayment.payor.responsibleParty.address', []));
            }
        }
    }

    private function makeTempPdf(): string
    {
        return FedExUs09TempAssetFactory::pdf();
    }

    private function makeAccount(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US09 Document Store',
            'slug' => 'us09-document-'.Str::random(6),
            'user_id' => $owner->id,
        ]);
        $fedEx = \App\Models\Carrier::query()->where('code', 'fedex')->firstOrFail();

        return CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'connection_type' => 'manual',
            'status' => 'enabled',
            'provider_account_number' => self::PARCEL_ACCOUNT,
            'display_name' => 'US09 Document Account',
            'created_by' => $owner->id,
        ]);
    }
}
