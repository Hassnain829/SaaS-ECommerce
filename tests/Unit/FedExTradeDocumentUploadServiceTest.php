<?php

namespace Tests\Unit;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadPayloadFactory;
use App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
            'carriers.fedex.trade_documents_upload_document_path' => '/documents/v1/etds/upload',
        ]);
    }

    public function test_confirmed_document_upload_path_and_content_type(): void
    {
        $config = app(FedExConfig::class);
        $service = app(FedExTradeDocumentUploadService::class);
        $factory = app(FedExTradeDocumentUploadPayloadFactory::class);

        $this->assertSame('https://documentapitest.prod.fedex.com/sandbox', $config->documentApiBaseUrl('sandbox'));
        $this->assertSame('/documents/v1/etds/upload', $service->documentUploadPath());

        $document = $factory->buildDocumentUpload([
            'upload' => [
                'absolute_path' => $this->makeTempPdf(),
                'filename' => 'commercial_invoice.pdf',
                'ship_document_type' => 'COMMERCIAL_INVOICE',
                'workflow_name' => 'ETDPreShipment',
                'origin_country_code' => 'US',
                'destination_country_code' => 'CA',
                'carrier_code' => 'FDXE',
            ],
        ]);

        $this->assertSame('multipart/form-data', $document['content_type']);
        $this->assertSame(['document', 'attachment'], data_get($document, 'redacted_multipart.field_order'));
        $this->assertSame('FDXE', data_get($document, 'redacted_multipart.document.carrierCode'));
        $this->assertSame('[OMITTED_BINARY]', data_get($document, 'redacted_multipart.attachment.bytes'));
    }

    public function test_rejects_tiny_pdf(): void
    {
        $factory = app(FedExTradeDocumentUploadPayloadFactory::class);
        $tinyPdf = sys_get_temp_dir().DIRECTORY_SEPARATOR.'etd-tiny-'.Str::random(6).'.pdf';
        file_put_contents($tinyPdf, '%PDF-1.4 tiny %%EOF');

        try {
            $factory->buildDocumentUpload([
                'upload' => [
                    'absolute_path' => $tinyPdf,
                    'filename' => 'commercial_invoice.pdf',
                    'ship_document_type' => 'COMMERCIAL_INVOICE',
                    'workflow_name' => 'ETDPreShipment',
                    'origin_country_code' => 'US',
                    'destination_country_code' => 'IT',
                    'carrier_code' => 'FDXE',
                ],
            ]);
            $this->fail('tiny PDF should be rejected');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_execute_parses_doc_id_with_http_fake(): void
    {
        $account = $this->makeAccountWithChildCredentials();
        $this->mock(FedExIntegratorChildOAuthService::class, function ($mock) {
            $mock->shouldReceive('fetchTokenResult')->andReturn(CarrierApiResult::success([
                'access_token' => 'test-access-token-etd',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], requestSummary: [], responseSummary: ['http_status' => 200]));
        });

        $capturedBodies = [];
        Http::fake(function (Request $request) use (&$capturedBodies) {
            $capturedBodies[] = $request->body();

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
        $docPrepared = $this->prepareDocumentUpload([
            'absolute_path' => $this->makeTempPdf(),
            'filename' => 'commercial_invoice.pdf',
        ]);
        $docResult = $service->executePreparedUpload($account->store, $account, $docPrepared, allowLive: true);
        $this->assertTrue($docResult['result']->success);
        $this->assertSame('DOCID1234567890', $docResult['returned_document_id']);
        $this->assertSame('production_commercial_invoice', $docResult['event']->scenario_key);
        $this->assertSame(CarrierApiEvent::ACTION_FEDEX_TRADE_DOCUMENTS_UPLOAD, $docResult['event']->action);
        $masked = (string) data_get($docResult['event']->request_summary, 'returned_document_id', '');
        $this->assertNotSame('DOCID1234567890', $masked);
        $this->assertTrue($masked === '' || str_contains(strtoupper($masked), 'REDACTED'));
        $this->assertSame(
            'DOCID1234567890',
            FedExTradeDocumentUploadService::resolveStoredDocumentId($docResult['event'])
        );
        $this->assertSame('[REDACTED]', data_get($docResult['event']->response_body_encrypted, 'output.meta.docId'));
        $this->assertSame('DOCID1234567890', data_get($docResult['event']->response_body_encrypted, '_operator_secrets.document_id'));

        $encodedEvent = json_encode($docResult['event']->request_body_encrypted).json_encode($docResult['event']->request_summary);
        $this->assertStringNotContainsString('test-access-token-etd', $encodedEvent);

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
        $prepared = $this->prepareDocumentUpload([
            'absolute_path' => $this->makeTempPdf(),
            'filename' => 'commercial_invoice.pdf',
        ]);

        try {
            app(FedExTradeDocumentUploadService::class)->executePreparedUpload($account->store, $account, $prepared, allowLive: false);
            $this->fail('expected gate');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        Http::assertNothingSent();
    }

    public function test_live_account_never_falls_back_to_sandbox_document_host(): void
    {
        config([
            'carriers.fedex.document_api_live_base_url' => 'https://documentapi.prod.fedex.com',
            'carriers.fedex.document_api_sandbox_base_url' => 'https://documentapitest.prod.fedex.com/sandbox',
        ]);

        $account = $this->makeAccountWithChildCredentials();
        $account->forceFill(['environment' => CarrierAccount::ENVIRONMENT_LIVE])->save();

        $oauth = \Mockery::mock(FedExIntegratorChildOAuthService::class);
        $oauth->shouldReceive('fetchTokenResult')->andReturn(CarrierApiResult::success(
            data: ['access_token' => 'live-doc-token', 'token_type' => 'bearer', 'expires_in' => 3600],
            requestSummary: [],
            responseSummary: ['http_status' => 200],
        ));
        $this->app->instance(FedExIntegratorChildOAuthService::class, $oauth);

        Http::fake(function (Request $request) {
            $this->assertStringStartsWith('https://documentapi.prod.fedex.com/', $request->url());
            $this->assertStringNotContainsString('documentapitest', $request->url());

            return Http::response([
                'output' => ['meta' => ['docId' => 'LIVE-DOC-1']],
            ], 201);
        });

        $prepared = $this->prepareDocumentUpload([
            'absolute_path' => $this->makeTempPdf(),
            'filename' => 'commercial_invoice.pdf',
        ]);
        $prepared['endpoint_host'] = 'https://documentapitest.prod.fedex.com/sandbox';

        $result = app(FedExTradeDocumentUploadService::class)->executePreparedUpload(
            $account->store,
            $account->fresh(),
            $prepared,
            allowLive: true,
        );

        $this->assertTrue($result['result']->success);
        $this->assertSame('https://documentapi.prod.fedex.com', data_get($result['event']->request_summary, 'endpoint_host'));
    }

    public function test_production_etd_upload_rejects_non_authoritative_relative_file_paths(): void
    {
        $factory = app(FedExTradeDocumentUploadPayloadFactory::class);

        try {
            $factory->buildDocumentUpload([
                'upload' => [
                    'relative_path' => 'resources/some-fixture/commercial_invoice.pdf',
                    'filename' => 'commercial_invoice.pdf',
                    'ship_document_type' => 'COMMERCIAL_INVOICE',
                    'workflow_name' => 'ETDPreShipment',
                    'origin_country_code' => 'US',
                    'destination_country_code' => 'CA',
                ],
            ]);
            $this->fail('Relative fixture paths must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    /**
     * @param  array<string, mixed>  $upload
     * @return array<string, mixed>
     */
    private function prepareDocumentUpload(array $upload): array
    {
        $built = app(FedExTradeDocumentUploadPayloadFactory::class)->buildDocumentUpload([
            'upload' => array_merge([
                'ship_document_type' => 'COMMERCIAL_INVOICE',
                'workflow_name' => 'ETDPreShipment',
                'origin_country_code' => 'US',
                'destination_country_code' => 'CA',
                'carrier_code' => 'FDXE',
            ], $upload),
        ]);

        $host = app(FedExTradeDocumentUploadService::class)->documentApiSandboxBaseUrl();

        return [
            'case_key' => 'production_etd',
            'upload_mode' => 'document',
            'scenario_key' => 'production_commercial_invoice',
            'endpoint_host' => $host,
            'endpoint_path' => (string) $built['endpoint_path'],
            'content_type' => (string) $built['content_type'],
            'document_type' => 'COMMERCIAL_INVOICE',
            'original_filename' => (string) ($built['attachment']['filename'] ?? ''),
            'mime_type' => (string) ($built['attachment']['mime_type'] ?? ''),
            'document_json' => $built['document_json'],
            'attachment_absolute_path' => (string) ($built['attachment']['absolute_path'] ?? ''),
            'redacted_multipart' => $built['redacted_multipart'],
            'request_summary' => [
                'case_key' => 'production_etd',
                'upload_mode' => 'document',
                'scenario_key' => 'production_commercial_invoice',
            ],
        ];
    }

    private function makeTempPdf(int $minBytes = 1200): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'etd-pdf-'.bin2hex(random_bytes(4)).'.pdf';
        $padding = str_repeat('Commercial invoice content for FedEx ETD upload. ', max(1, (int) ceil($minBytes / 50)));
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\nstream\n{$padding}\nendstream\n%%EOF\n");

        return $path;
    }

    private function makeAccountWithChildCredentials(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'ETD Upload Store',
            'slug' => 'etd-upload-'.Str::lower(Str::random(6)),
        ]);

        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => Carrier::query()->where('code', 'fedex')->value('id'),
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx ETD',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'status' => CarrierAccount::STATUS_ENABLED,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'provider_account_number' => '700257037',
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $account->setCredentials([
            'customer_key' => 'child-key',
            'customer_password' => 'child-secret',
        ]);
        $account->setFedExAccountNumber('700257037');
        $account->save();

        return $account->fresh();
    }
}
