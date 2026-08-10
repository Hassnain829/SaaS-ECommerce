<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExIntegratorChildOAuthService;
use App\Services\Carriers\FedEx\Auth\FedExMerchantCredentialsOAuthService;
use App\Services\Carriers\FedEx\DTO\FedExApiEvidenceData;
use App\Services\Carriers\FedEx\DTO\FedExApiEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Support\FedExHttpClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Production Trade Documents Upload multipart execution (commercial invoice / ETD document only).
 */
class FedExTradeDocumentUploadService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExIntegratorChildOAuthService $integratorChildOAuthService,
        private readonly FedExMerchantCredentialsOAuthService $merchantOAuthService,
        private readonly CarrierApiEventLogger $eventLogger,
        private readonly FedExMerchantApiClient $apiClient,
    ) {}

    /**
     * Execute a prepared multipart document upload. Default allowLive=false blocks before any network I/O.
     *
     * @param  array<string, mixed>  $prepared
     * @return array{
     *   result: CarrierApiResult,
     *   event: CarrierApiEvent|null,
     *   returned_document_id: string|null,
     *   prepared: array<string, mixed>
     * }
     */
    public function executePreparedUpload(
        Store $store,
        CarrierAccount $account,
        array $prepared,
        bool $allowLive = false,
    ): array {
        abort_unless($allowLive, 422, 'Trade document upload is blocked until live execution is explicitly allowed.');

        $this->apiClient->assertFedExApiAccount($account);
        $account->loadMissing('store');

        $environment = $this->config->environment($account->environment);
        $credentialsMode = $account->usesFedExIntegratorProvider() ? 'integrator_child' : 'merchant_developer';
        $path = (string) ($prepared['endpoint_path'] ?? '');
        $preparedHost = rtrim((string) ($prepared['endpoint_host'] ?? ''), '/');
        $environmentHost = rtrim($this->config->documentApiBaseUrl($account->environment), '/');
        // Never send a live merchant account to the sandbox document host.
        $host = $preparedHost;
        if ($host === ''
            || (
                $this->config->environment($account->environment) === 'live'
                && str_contains(strtolower($host), 'documentapitest')
            )
        ) {
            $host = $environmentHost;
        }
        $url = $host.$path;
        $scenarioKey = (string) ($prepared['scenario_key'] ?? '');
        $caseKey = (string) ($prepared['case_key'] ?? '');
        $documentJson = $prepared['document_json'] ?? null;
        $attachmentPath = (string) ($prepared['attachment_absolute_path'] ?? '');
        $filename = (string) ($prepared['original_filename'] ?? 'attachment');
        $mimeType = (string) ($prepared['mime_type'] ?? 'application/octet-stream');

        abort_unless(is_array($documentJson), 422, 'Prepared trade document upload is missing document JSON.');
        abort_unless($attachmentPath !== '' && is_file($attachmentPath), 422, 'Prepared trade document upload is missing attachment file.');

        $requestSummary = array_merge($prepared['request_summary'] ?? [], [
            'case_key' => $caseKey,
            'upload_mode' => 'document',
            'scenario_key' => $scenarioKey,
            'document_type' => $prepared['document_type'] ?? null,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => (int) filesize($attachmentPath),
            'endpoint_host' => $host,
            'endpoint_path' => $path,
            'content_type' => 'multipart/form-data',
            'field_order' => ['document', 'attachment'],
            'credentials_mode' => $credentialsMode,
        ]);

        $oauthEvent = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            account: $account,
            requestSummary: $this->apiClient->oauthRequestSummary($account, $environment, $credentialsMode),
            environment: $account->environment,
        );

        $oauthResult = $account->usesFedExIntegratorProvider()
            ? $this->integratorChildOAuthService->fetchTokenResult($account, fresh: false)
            : $this->merchantOAuthService->fetchTokenResult($account, fresh: false);

        $this->eventLogger->complete($oauthEvent, $oauthResult);

        if (! $oauthResult->success) {
            $failed = CarrierApiResult::failure(
                message: $oauthResult->errorMessage ?? 'FedEx authentication failed. Run the connection check first.',
                code: $oauthResult->errorCode ?? 'oauth_failed',
                requestSummary: array_merge($requestSummary, ['oauth_failed' => true]),
                responseSummary: $oauthResult->responseSummary,
            );

            return [
                'result' => $failed,
                'event' => null,
                'returned_document_id' => null,
                'prepared' => $prepared,
            ];
        }

        $accessToken = FedExHttpClient::normalizeBearerToken((string) ($oauthResult->data['access_token'] ?? ''));
        if ($accessToken === null || $accessToken === '') {
            $failed = CarrierApiResult::failure(
                message: 'FedEx authentication did not return an access token.',
                code: 'missing_access_token',
                requestSummary: $requestSummary,
            );

            return [
                'result' => $failed,
                'event' => null,
                'returned_document_id' => null,
                'prepared' => $prepared,
            ];
        }

        $customerTransactionId = (string) Str::uuid();
        $apiEvent = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_FEDEX,
            action: CarrierApiEvent::ACTION_FEDEX_TRADE_DOCUMENTS_UPLOAD,
            account: $account,
            requestSummary: $requestSummary,
            environment: $account->environment,
            context: new FedExApiEventContext(
                scenarioKey: $scenarioKey,
                testCaseKey: $caseKey,
            ),
        );

        $started = microtime(true);
        $binary = (string) file_get_contents($attachmentPath);
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'x-customer-transaction-id' => $customerTransactionId,
            ])
            ->attach(
                'document',
                json_encode($documentJson, JSON_THROW_ON_ERROR),
                'document.json',
                ['Content-Type' => 'application/json']
            )
            ->attach('attachment', $binary, $filename, ['Content-Type' => $mimeType])
            ->post($url);
        unset($binary);

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $responseBody = $response->json();
        if (! is_array($responseBody)) {
            $responseBody = ['raw' => '[non_json_response]'];
        }

        $fedexTransactionId = $response->header('x-fedex-transactionid')
            ?: $response->header('x-customer-transaction-id')
            ?: $customerTransactionId;

        $returnedDocumentId = $this->extractDocumentId($responseBody);

        $success = $response->successful();
        $errorMessage = null;
        $errorCode = null;

        if ($success && ($returnedDocumentId === null || $returnedDocumentId === '')) {
            $success = false;
            $errorCode = 'document_id_missing';
            $errorMessage = 'FedEx document upload succeeded but did not return docId.';
        }

        if (! $success && $errorMessage === null) {
            $errorCode = (string) ($response->status() ?: 'upload_failed');
            $errorMessage = 'FedEx Trade Documents upload failed with HTTP '.$response->status().'.';
        }

        $responseSummary = array_filter([
            'http_status' => $response->status(),
            'fedex_transaction_id' => $fedexTransactionId,
            'returned_document_id' => $returnedDocumentId !== null
                ? '[REDACTED_ID_LAST4:'.substr($returnedDocumentId, -4).']'
                : null,
            'upload_mode' => 'document',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $evidence = new FedExApiEvidenceData(
            endpoint: $url,
            httpMethod: 'POST',
            requestHeaders: [
                'Content-Type' => 'multipart/form-data',
                'x-customer-transaction-id' => $customerTransactionId,
                'Authorization' => 'Bearer [REDACTED]',
            ],
            requestBody: $prepared['redacted_multipart'] ?? [
                'document' => $documentJson,
                'attachment' => [
                    'filename' => $filename,
                    'contentType' => $mimeType,
                    'bytes' => '[OMITTED_BINARY]',
                    'size_bytes' => (int) filesize($attachmentPath),
                ],
            ],
            responseHeaders: [
                'x-customer-transaction-id' => $fedexTransactionId,
            ],
            responseBody: $this->redactResponseBody($responseBody),
            httpStatus: $response->status(),
            fedexTransactionId: is_string($fedexTransactionId) ? $fedexTransactionId : null,
        );

        $result = $success
            ? CarrierApiResult::success(
                data: [
                    'returned_document_id_present' => $returnedDocumentId !== null,
                ],
                requestId: $customerTransactionId,
                durationMs: $durationMs,
                requestSummary: array_merge($requestSummary, [
                    'http_status' => $response->status(),
                    'fedex_transaction_id' => $fedexTransactionId,
                    'returned_document_id' => $returnedDocumentId !== null
                        ? '[REDACTED_ID_LAST4:'.substr($returnedDocumentId, -4).']'
                        : null,
                    'returned_document_id_present' => $returnedDocumentId !== null,
                ]),
                responseSummary: $responseSummary,
                evidence: $evidence,
            )
            : CarrierApiResult::failure(
                message: $errorMessage ?? 'FedEx Trade Documents upload failed.',
                code: $errorCode,
                requestId: $customerTransactionId,
                durationMs: $durationMs,
                requestSummary: array_merge($requestSummary, [
                    'http_status' => $response->status(),
                    'fedex_transaction_id' => $fedexTransactionId,
                ]),
                responseSummary: $responseSummary,
                evidence: $evidence,
            );

        $completedEvent = $this->eventLogger->complete($apiEvent, $result);

        if ($success && $returnedDocumentId !== null && $completedEvent !== null) {
            $this->storeEncryptedDocumentId($completedEvent, $returnedDocumentId);
            $completedEvent->refresh();
        }

        return [
            'result' => $result,
            'event' => $completedEvent,
            'returned_document_id' => $returnedDocumentId,
            'prepared' => $prepared,
        ];
    }

    public static function resolveStoredDocumentId(CarrierApiEvent $event): ?string
    {
        $body = is_array($event->response_body_encrypted) ? $event->response_body_encrypted : [];
        $documentId = data_get($body, '_operator_secrets.document_id');

        return is_string($documentId) && $documentId !== '' ? $documentId : null;
    }

    private function storeEncryptedDocumentId(CarrierApiEvent $event, string $documentId): void
    {
        $body = is_array($event->response_body_encrypted) ? $event->response_body_encrypted : [];
        $secrets = is_array($body['_operator_secrets'] ?? null) ? $body['_operator_secrets'] : [];
        $secrets['document_id'] = $documentId;
        $body['_operator_secrets'] = $secrets;

        $event->forceFill([
            'response_body_encrypted' => $body,
        ])->save();
    }

    public function documentUploadPath(): string
    {
        return $this->config->tradeDocumentsUploadDocumentPath();
    }

    public function documentApiSandboxBaseUrl(): string
    {
        return $this->config->documentApiBaseUrl('sandbox');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function extractDocumentId(array $body): ?string
    {
        $candidates = [
            data_get($body, 'output.meta.docId'),
            data_get($body, 'meta.docId'),
            data_get($body, 'output.docId'),
            data_get($body, 'docId'),
            data_get($body, 'output.meta.documentId'),
            data_get($body, 'output.documentId'),
            data_get($body, 'documentId'),
            data_get($body, 'output.uploadedDocuments.0.docId'),
            data_get($body, 'uploadedDocuments.0.docId'),
            data_get($body, 'output.documentResponses.0.docId'),
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate) && ! str_starts_with(strtoupper((string) $candidate), '[REDACTED')) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function redactResponseBody(array $body): array
    {
        $redacted = $body;

        foreach (['docId', 'documentId'] as $key) {
            if (isset($redacted[$key]) && is_string($redacted[$key])) {
                $redacted[$key] = '[REDACTED]';
            }
            if (isset($redacted['output'][$key]) && is_string($redacted['output'][$key])) {
                $redacted['output'][$key] = '[REDACTED]';
            }
            if (isset($redacted['meta'][$key]) && is_string($redacted['meta'][$key])) {
                $redacted['meta'][$key] = '[REDACTED]';
            }
            if (isset($redacted['output']['meta'][$key]) && is_string($redacted['output']['meta'][$key])) {
                $redacted['output']['meta'][$key] = '[REDACTED]';
            }
        }

        if (isset($redacted['output']['uploadedDocuments']) && is_array($redacted['output']['uploadedDocuments'])) {
            foreach ($redacted['output']['uploadedDocuments'] as $index => $row) {
                if (is_array($row) && isset($row['docId'])) {
                    $redacted['output']['uploadedDocuments'][$index]['docId'] = '[REDACTED]';
                }
            }
        }

        return $redacted;
    }
}
