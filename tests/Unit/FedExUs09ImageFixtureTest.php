<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadPayloadFactory;
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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FedExUs09TempAssetFactory;
use Tests\TestCase;

class FedExUs09ImageFixtureTest extends TestCase
{
    use RefreshDatabase;

    private const PARCEL_ACCOUNT = '700257037';

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

    public function test_us09_image_ship_uses_requested_document_types_and_provided_image_type(): void
    {
        $account = $this->makeAccount();
        $fixture = app(FedExUs09EtdFixtureService::class)->fixture('IntegratorUS09_IMAGE');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'PDF');
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($payload);

        $this->assertSame(
            ['upload_us09_image_letterhead', 'upload_us09_image_signature'],
            FedExValidationScenarioCatalog::lockedShipScenarios()['IntegratorUS09_IMAGE']['upload_scenario_keys']
        );

        $shipment = data_get($payload, 'requestedShipment');
        $this->assertContains('ELECTRONIC_TRADE_DOCUMENTS', (array) data_get($shipment, 'shipmentSpecialServices.specialServiceTypes'));
        $this->assertSame(['COMMERCIAL_INVOICE'], data_get($shipment, 'shipmentSpecialServices.etdDetail.requestedDocumentTypes'));
        $this->assertArrayNotHasKey('attachedDocuments', (array) data_get($shipment, 'shipmentSpecialServices.etdDetail', []));
        $this->assertSame('IMAGE_1', data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages.0.id'));
        $this->assertSame('LETTER_HEAD', data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages.0.type'));
        $this->assertSame('LETTER_HEAD', data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages.0.providedImageType'));
        $this->assertSame('IMAGE_2', data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages.1.id'));
        $this->assertSame('SIGNATURE', data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages.1.type'));
        $this->assertSame('SIGNATURE', data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.customerImageUsages.1.providedImageType'));

        $export = app(FedExUs09EvidenceRules::class)->validateSanitizedExport(
            is_array($sanitized) ? $sanitized : [],
            'IntegratorUS09_IMAGE',
        );
        $this->assertTrue($export['valid'], implode(',', $export['reasons']));
    }

    public function test_preflight_requires_separate_letterhead_and_signature_uploads(): void
    {
        $account = $this->makeAccount();
        $assessment = app(FedExValidationPreflightService::class)->assess($account->store, $account);
        $letterhead = collect($assessment['checks'])->firstWhere('key', 'upload_us09_image_letterhead_event');
        $signature = collect($assessment['checks'])->firstWhere('key', 'upload_us09_image_signature_event');
        $legacy = collect($assessment['checks'])->firstWhere('key', 'upload_us09_image_event');
        $label = collect($assessment['checks'])->firstWhere('key', 'ship_us09_image_pdf_event');

        $this->assertNotNull($letterhead);
        $this->assertNotNull($signature);
        $this->assertNull($legacy);
        $this->assertSame('not_tested', $letterhead['status']);
        $this->assertSame('not_tested', $signature['status']);
        $this->assertSame('not_tested', $label['status']);
        $this->assertTrue((bool) $letterhead['required']);
        $this->assertTrue((bool) $signature['required']);
    }

    public function test_letterhead_and_signature_upload_evidence_are_independent(): void
    {
        $rules = app(FedExUs09EvidenceRules::class);
        $letterheadPath = $this->makeTempPng(40, 10);
        $signaturePath = $this->makeTempPng(40, 10);

        $letterhead = app(FedExTradeDocumentUploadService::class)->prepareImageUpload('letterhead', [
            'absolute_path' => $letterheadPath,
            'filename' => 'signature3.png',
        ]);
        $signature = app(FedExTradeDocumentUploadService::class)->prepareImageUpload('signature', [
            'absolute_path' => $signaturePath,
            'filename' => 'signature2.png',
        ]);

        $this->assertSame('upload_us09_image_letterhead', $letterhead['scenario_key']);
        $this->assertSame('LETTERHEAD', $letterhead['image_type']);
        $this->assertSame('IMAGE_1', $letterhead['image_index']);
        $this->assertSame('upload_us09_image_signature', $signature['scenario_key']);
        $this->assertSame('SIGNATURE', $signature['image_type']);
        $this->assertSame('IMAGE_2', $signature['image_index']);

        $letterheadOk = $rules->validateUploadEvidence(array_merge($letterhead, [
            'returned_image_index' => 'IMAGE_1',
        ]), 'upload_us09_image_letterhead');
        $this->assertTrue($letterheadOk['valid'], implode(',', $letterheadOk['reasons']));

        $signatureAsLetterhead = $rules->validateUploadEvidence(array_merge($signature, [
            'returned_image_index' => 'IMAGE_2',
        ]), 'upload_us09_image_letterhead');
        $this->assertFalse($signatureAsLetterhead['valid']);
        $this->assertNotEmpty(array_intersect($signatureAsLetterhead['reasons'], [
            'us09_letterhead_image_type_mismatch',
            'us09_letterhead_image_index_mismatch',
        ]));

        $missingImage1 = $rules->validateUploadEvidence(array_merge($letterhead, [
            'image_index' => 'IMAGE_2',
            'returned_image_index' => 'IMAGE_2',
        ]), 'upload_us09_image_letterhead');
        $this->assertContains('us09_letterhead_image_index_mismatch', $missingImage1['reasons']);

        $missingImage2 = $rules->validateUploadEvidence(array_merge($signature, [
            'image_index' => 'IMAGE_1',
            'returned_image_index' => 'IMAGE_1',
        ]), 'upload_us09_image_signature');
        $this->assertContains('us09_signature_image_index_mismatch', $missingImage2['reasons']);
    }

    public function test_live_upload_blocked_when_allow_live_false(): void
    {
        $account = $this->makeAccount();
        $path = $this->makeTempPng(40, 10);
        $prepared = app(FedExTradeDocumentUploadService::class)->prepareImageUpload('letterhead', [
            'absolute_path' => $path,
            'filename' => 'signature3.png',
        ]);

        Http::fake();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(FedExTradeDocumentUploadService::class)->executePreparedUpload($account->store, $account, $prepared, allowLive: false);
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
        }
    }

    private function makeTempPng(int $width, int $height): string
    {
        return FedExUs09TempAssetFactory::png($width, $height);
    }

    private function makeAccount(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US09 Image Store',
            'slug' => 'us09-image-'.Str::random(6),
            'user_id' => $owner->id,
        ]);
        $fedEx = \App\Models\Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'connection_type' => 'manual',
            'status' => 'enabled',
            'provider_account_number' => self::PARCEL_ACCOUNT,
            'display_name' => 'US09 Image Account',
            'created_by' => $owner->id,
        ]);
        $account->setCredentials([
            'customer_key' => 'child-key-us09',
            'customer_password' => 'child-secret-us09',
        ]);
        $account->save();

        return $account->fresh();
    }
}
