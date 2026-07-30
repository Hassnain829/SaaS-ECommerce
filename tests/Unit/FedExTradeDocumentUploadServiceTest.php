<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadPayloadFactory;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExUs09EtdFixtureService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FedExUs09TempAssetFactory;
use Tests\TestCase;

class FedExTradeDocumentUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.document_api_sandbox_base_url' => 'https://documentapitest.prod.fedex.com/sandbox',
            'carriers.fedex.trade_documents_upload_image_path' => '/documents/v1/lhsimages/upload',
            'carriers.fedex.trade_documents_upload_document_path' => '/documents/v1/etds/upload',
        ]);
    }

    public function test_confirmed_upload_paths_and_content_types(): void
    {
        $config = app(FedExConfig::class);
        $service = app(FedExTradeDocumentUploadService::class);
        $letterhead = $this->makeTempPng(50, 20);
        $pdf = $this->makeTempPdf();

        $this->assertSame('https://documentapitest.prod.fedex.com/sandbox', $config->documentApiBaseUrl('sandbox'));
        $this->assertSame('/documents/v1/lhsimages/upload', $service->imageUploadPath());
        $this->assertSame('/documents/v1/etds/upload', $service->documentUploadPath());

        $image = $service->prepareImageUpload('letterhead', [
            'absolute_path' => $letterhead,
            'filename' => 'signature3.png',
        ]);
        $document = $service->prepareDocumentUpload([
            'absolute_path' => $pdf,
            'filename' => 'commercial_invoice.pdf',
        ]);

        $this->assertSame('multipart/form-data', $image['content_type']);
        $this->assertSame('multipart/form-data', $document['content_type']);
        $this->assertSame(['document', 'attachment'], data_get($image, 'redacted_multipart.field_order'));
        $this->assertSame(['document', 'attachment'], data_get($document, 'redacted_multipart.field_order'));
        $this->assertSame('FDXE', data_get($document, 'redacted_multipart.document.carrierCode'));
        $this->assertSame('upload_us09_image_letterhead', $image['scenario_key']);
        $this->assertSame('upload_us09_document', $document['scenario_key']);
    }

    public function test_rejects_placeholder_one_by_one_png_and_tiny_pdf(): void
    {
        $factory = app(FedExTradeDocumentUploadPayloadFactory::class);
        $oneByOne = $this->makeTempPng(1, 1);
        $tinyPdf = sys_get_temp_dir().DIRECTORY_SEPARATOR.'us09-tiny-'.Str::random(6).'.pdf';
        file_put_contents($tinyPdf, '%PDF-1.4 tiny %%EOF');

        try {
            $factory->buildImageUpload([
                'upload' => [
                    'absolute_path' => $oneByOne,
                    'filename' => 'signature3.png',
                    'image_type' => 'LETTERHEAD',
                    'image_index' => 'IMAGE_1',
                    'reference_id' => 'x',
                    'workflow_name' => 'LetterheadSignature',
                ],
            ]);
            $this->fail('1x1 PNG should be rejected');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        try {
            $factory->buildDocumentUpload([
                'upload' => [
                    'absolute_path' => $tinyPdf,
                    'filename' => 'commercial_invoice.pdf',
                    'ship_document_type' => 'COMMERCIAL_INVOICE',
                    'workflow_name' => 'ETDPreshipment',
                    'origin_country_code' => 'US',
                    'destination_country_code' => 'IT',
                    'carrier_code' => 'FDXE',
                ],
            ]);
            $this->fail('tiny PDF should be rejected');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $validImage = $factory->buildImageUpload([
            'upload' => [
                'absolute_path' => $this->makeTempPng(80, 20),
                'filename' => 'signature3.png',
                'image_type' => 'LETTERHEAD',
                'image_index' => 'IMAGE_1',
                'reference_id' => 'x',
                'workflow_name' => 'LetterheadSignature',
            ],
        ]);
        $validPdf = $factory->buildDocumentUpload([
            'upload' => [
                'absolute_path' => $this->makeTempPdf(),
                'filename' => 'commercial_invoice.pdf',
                'ship_document_type' => 'COMMERCIAL_INVOICE',
                'workflow_name' => 'ETDPreshipment',
                'origin_country_code' => 'US',
                'destination_country_code' => 'IT',
                'carrier_code' => 'FDXE',
            ],
        ]);
        $this->assertSame('[OMITTED_BINARY]', data_get($validImage, 'redacted_multipart.attachment.bytes'));
        $this->assertSame('[OMITTED_BINARY]', data_get($validPdf, 'redacted_multipart.attachment.bytes'));
    }

    public function test_execute_parses_image_index_and_doc_id_with_http_fake(): void
    {
        $account = $this->makeAccountWithChildCredentials();
        $this->mock(FedExIntegratorChildOAuthService::class, function ($mock) {
            $mock->shouldReceive('fetchTokenResult')->andReturn(CarrierApiResult::success([
                'access_token' => 'test-access-token-us09',
                'expires_in' => 3600,
                'token_type' => 'bearer',
            ]));
        });

        $capturedBodies = [];
        Http::fake(function (Request $request) use (&$capturedBodies) {
            $capturedBodies[] = $request->body();
            $url = $request->url();
            if (str_contains($url, '/documents/v1/lhsimages/upload')) {
                return Http::response([
                    'output' => ['meta' => ['imageIndex' => 'IMAGE_1']],
                ], 200, ['x-customer-transaction-id' => 'txn-image-1']);
            }

            return Http::response([
                'output' => [
                    'meta' => [
                        'documentType' => 'CI',
                        'docId' => 'DOCID1234567890',
                        'folderId' => 'preShipment',
                    ],
                ],
                'customerTransactionId' => 'txn-doc-1',
            ], 201, ['x-customer-transaction-id' => 'txn-doc-1']);
        });

        $service = app(FedExTradeDocumentUploadService::class);
        $imagePrepared = $service->prepareImageUpload('letterhead', [
            'absolute_path' => $this->makeTempPng(60, 15),
            'filename' => 'signature3.png',
        ]);
        $imageResult = $service->executePreparedUpload($account->store, $account, $imagePrepared, allowLive: true);

        $this->assertTrue($imageResult['result']->success);
        $this->assertSame('IMAGE_1', $imageResult['returned_image_index']);
        $this->assertNotNull($imageResult['event']);
        $this->assertSame(CarrierApiEvent::ACTION_FEDEX_TRADE_DOCUMENTS_UPLOAD, $imageResult['event']->action);
        $this->assertSame('upload_us09_image_letterhead', $imageResult['event']->scenario_key);
        $this->assertSame('[OMITTED_BINARY]', data_get($imageResult['event']->request_body_encrypted, 'attachment.bytes'));
        $encodedEvent = json_encode($imageResult['event']->request_body_encrypted).json_encode($imageResult['event']->request_summary);
        $this->assertStringNotContainsString('test-access-token-us09', $encodedEvent);
        $this->assertStringNotContainsString('Bearer test-access-token', $encodedEvent);

        $docPrepared = $service->prepareDocumentUpload([
            'absolute_path' => $this->makeTempPdf(),
            'filename' => 'commercial_invoice.pdf',
        ]);
        $docResult = $service->executePreparedUpload($account->store, $account, $docPrepared, allowLive: true);
        $this->assertTrue($docResult['result']->success);
        $this->assertSame('DOCID1234567890', $docResult['returned_document_id']);
        $this->assertSame('upload_us09_document', $docResult['event']->scenario_key);
        $masked = (string) data_get($docResult['event']->request_summary, 'returned_document_id', '');
        $this->assertNotSame('DOCID1234567890', $masked);
        $this->assertTrue($masked === '' || str_contains(strtoupper($masked), 'REDACTED'));
        $this->assertSame(
            'DOCID1234567890',
            \App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadService::resolveStoredDocumentId($docResult['event'])
        );
        $this->assertSame('[REDACTED]', data_get($docResult['event']->response_body_encrypted, 'output.meta.docId'));
        $this->assertSame('DOCID1234567890', data_get($docResult['event']->response_body_encrypted, '_operator_secrets.document_id'));

        $this->assertNotEmpty($capturedBodies);
        foreach ($capturedBodies as $body) {
            $documentPos = strpos($body, 'name="document"');
            $attachmentPos = strpos($body, 'name="attachment"');
            $this->assertNotFalse($documentPos);
            $this->assertNotFalse($attachmentPos);
            $this->assertLessThan($attachmentPos, $documentPos);
        }
    }

    public function test_allow_live_false_makes_no_http_request(): void
    {
        $account = $this->makeAccountWithChildCredentials();
        Http::fake();
        $prepared = app(FedExTradeDocumentUploadService::class)->prepareImageUpload('letterhead', [
            'absolute_path' => $this->makeTempPng(40, 10),
            'filename' => 'signature3.png',
        ]);

        try {
            app(FedExTradeDocumentUploadService::class)->executePreparedUpload($account->store, $account, $prepared, allowLive: false);
            $this->fail('expected gate');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        Http::assertNothingSent();
    }

    public function test_missing_repository_assets_block_prepare(): void
    {
        $path = base_path('resources/fedex-validation/us09/signature3.png');
        $backup = null;
        if (is_file($path)) {
            $backup = $path.'.testbak';
            rename($path, $backup);
        }

        try {
            app(FedExTradeDocumentUploadService::class)->prepareImageUpload('letterhead');
            $this->fail('Missing repository letterhead asset should block prepare.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        } finally {
            if (is_string($backup) && is_file($backup)) {
                rename($backup, $path);
            }
        }
    }

    private function makeTempPng(int $width, int $height): string
    {
        return FedExUs09TempAssetFactory::png($width, $height);
    }

    private function makeTempPdf(): string
    {
        return FedExUs09TempAssetFactory::pdf();
    }

    private function makeAccountWithChildCredentials(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US09 Upload Store',
            'slug' => 'us09-upload-'.Str::random(6),
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
            'provider_account_number' => '700257037',
            'display_name' => 'US09 Upload Account',
            'created_by' => $owner->id,
        ]);
        $account->setCredentials([
            'customer_key' => 'child-key-us09-upload',
            'customer_password' => 'child-secret-us09-upload',
        ]);
        $account->save();

        return $account->fresh();
    }
}
