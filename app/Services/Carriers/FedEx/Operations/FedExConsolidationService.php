<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExAuthorizationClassifier;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExLabelArtifactValidator;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * IntegratorUS10 Consolidation / IPD workflow.
 *
 * Live network I/O is gated behind allowLive=false by default. Open consolidations
 * must only be created during the final evidence run.
 */
class FedExConsolidationService
{
    public const MAX_CONFIRM_RESULTS_ATTEMPTS = 5;

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExConsolidationFixtureService $fixtures,
        private readonly FedExConsolidationPayloadFactory $payloadFactory,
        private readonly FedExConsolidationEvidenceRules $evidenceRules,
    ) {}

    /**
     * Prepare local payloads for all US10 steps without network I/O.
     *
     * @return array{
     *   create: array<string, mixed>,
     *   add_shipments: list<array<string, mixed>>,
     *   confirm: array<string, mixed>,
     *   confirm_results: array<string, mixed>,
     *   paths: array<string, string>,
     *   account_configured: bool
     * }
     */
    public function prepareLocalChain(): array
    {
        $createFixture = $this->fixtures->fixture('IntegratorUS10_CREATE_CONSOLIDATION');
        $createPayload = $this->payloadFactory->buildCreateConsolidation($createFixture);

        $addPayloads = [];
        foreach ($this->fixtures->addShipmentKeys() as $key) {
            $addPayloads[] = $this->payloadFactory->buildAddShipment($this->fixtures->fixture($key));
        }

        $confirmFixture = $this->fixtures->fixture('IntegratorUS10_CONFIRM_CONSOLIDATION');
        $confirmPayload = $this->payloadFactory->buildConfirmConsolidation($confirmFixture);

        $resultsFixture = $this->fixtures->fixture('IntegratorUS10_CONFIRM_RESULTS');
        $resultsPayload = $this->payloadFactory->buildConfirmResults($resultsFixture);

        return [
            'create' => $createPayload,
            'add_shipments' => $addPayloads,
            'confirm' => $confirmPayload,
            'confirm_results' => $resultsPayload,
            'paths' => [
                'create' => $this->config->consolidationCreatePath(),
                'shipment' => $this->config->consolidationShipmentPath(),
                'confirm' => $this->config->consolidationConfirmPath(),
                'confirm_results' => $this->config->consolidationConfirmResultsPath(),
            ],
            'account_configured' => $this->fixtures->consolidationAccountNumber() !== '',
        ];
    }

    /**
     * Execute the full Create → 6× Add → Confirm → Confirm Results chain.
     *
     * @return array<string, mixed>
     */
    public function execute(Store $store, CarrierAccount $account, bool $allowLive = false, ?User $actor = null): array
    {
        abort_unless(
            $allowLive,
            422,
            'US10 Consolidation is deferred until the final evidence run. Local prepare-only mode is active (allowLive=false).'
        );

        abort_unless(
            $this->config->us10LiveRunEnabled(),
            422,
            $this->config->us10ExclusionNote(),
        );

        $this->apiClient->assertFedExApiAccount($account);

        $consolidationAccount = $this->fixtures->consolidationAccountNumber();
        abort_unless(
            $consolidationAccount !== '',
            422,
            'FEDEX_VALIDATION_US10_CONSOLIDATION_ACCOUNT is required for IntegratorUS10. Do not substitute parcel, Ground Economy, or Freight accounts.'
        );

        $shipperTin = trim((string) config('carriers.fedex.validation_us10_shipper_tin', ''));
        abort_unless(
            $shipperTin !== '',
            422,
            'FEDEX_VALIDATION_US10_SHIPPER_TIN is required for IntegratorUS10. Placeholder TIN values are not allowed.'
        );

        $steps = [];
        $completedSteps = [];

        $createFixture = $this->fixtures->fixture('IntegratorUS10_CREATE_CONSOLIDATION');
        $createPayload = $this->payloadFactory->buildCreateConsolidation($createFixture);
        $this->assertNoHistoricalIdentifiers($createPayload);
        $createValidation = $this->evidenceRules->validateCreateRequest($createPayload);
        abort_unless($createValidation['valid'], 422, 'US10 create payload failed evidence rules: '.implode(', ', $createValidation['reasons']));

        $createResult = $this->post(
            $store,
            $account,
            $this->config->consolidationCreatePath(),
            $createPayload,
            $createFixture,
        );
        $steps['create'] = [
            'result' => $createResult,
            'payload' => $createPayload,
            'path' => $this->config->consolidationCreatePath(),
        ];

        if (! $createResult->success) {
            return $this->halted($steps, 'create_failed', null, $completedSteps, $actor);
        }
        $completedSteps[] = 'create';

        $consolidationKey = $this->extractConsolidationKey($createResult->data ?? []);
        if ($consolidationKey === null) {
            return $this->halted($steps, 'missing_consolidation_key', null, $completedSteps, $actor);
        }

        $this->assertDynamicKey($consolidationKey);
        $steps['consolidation_key'] = $consolidationKey;

        $shipTimestamp = $this->payloadFactory->defaultShipTimestamp();

        foreach ($this->fixtures->addShipmentKeys() as $index => $testCaseKey) {
            $fixture = $this->fixtures->withConsolidationKey(
                $this->fixtures->fixture($testCaseKey),
                $consolidationKey,
            );
            $payload = $this->payloadFactory->buildAddShipment($fixture, $shipTimestamp);
            $this->assertNoHistoricalIdentifiers($payload);
            $validation = $this->evidenceRules->validateAddShipmentRequest($payload, $index + 1);
            abort_unless($validation['valid'], 422, $testCaseKey.' payload failed evidence rules: '.implode(', ', $validation['reasons']));

            $result = $this->post(
                $store,
                $account,
                $this->config->consolidationShipmentPath(),
                $payload,
                $fixture,
            );

            $steps['add_shipments'][$testCaseKey] = [
                'result' => $result,
                'payload' => $payload,
                'path' => $this->config->consolidationShipmentPath(),
            ];

            if (! $result->success) {
                return $this->halted($steps, 'add_shipment_failed', $testCaseKey, $completedSteps, $actor);
            }
            $completedSteps[] = $testCaseKey;
        }

        $confirmFixture = $this->fixtures->withConsolidationKey(
            $this->fixtures->fixture('IntegratorUS10_CONFIRM_CONSOLIDATION'),
            $consolidationKey,
        );
        $confirmPayload = $this->payloadFactory->buildConfirmConsolidation($confirmFixture);
        $this->assertNoHistoricalIdentifiers($confirmPayload);
        $confirmValidation = $this->evidenceRules->validateConfirmRequest($confirmPayload);
        abort_unless($confirmValidation['valid'], 422, 'US10 confirm payload failed evidence rules: '.implode(', ', $confirmValidation['reasons']));

        $confirmResult = $this->post(
            $store,
            $account,
            $this->config->consolidationConfirmPath(),
            $confirmPayload,
            $confirmFixture,
        );
        $steps['confirm'] = [
            'result' => $confirmResult,
            'payload' => $confirmPayload,
            'path' => $this->config->consolidationConfirmPath(),
        ];

        if (! $confirmResult->success) {
            return $this->halted($steps, 'confirm_failed', null, $completedSteps, $actor);
        }
        $completedSteps[] = 'confirm';

        $jobId = $this->extractJobId($confirmResult->data ?? []);
        if ($jobId === null || $jobId === '') {
            return $this->halted($steps, 'missing_job_id', null, $completedSteps, $actor);
        }

        $this->assertDynamicJobId($jobId);
        $steps['job_id'] = $jobId;

        $resultsFixture = $this->fixtures->withJobId(
            $this->fixtures->fixture('IntegratorUS10_CONFIRM_RESULTS'),
            $jobId,
        );
        $resultsPayload = $this->payloadFactory->buildConfirmResults($resultsFixture);
        $this->assertNoHistoricalIdentifiers($resultsPayload);

        $poll = $this->pollConfirmResults($store, $account, $resultsFixture, $resultsPayload);
        $steps['confirm_results'] = $poll;

        $success = (bool) ($poll['success'] ?? false);
        if ($success) {
            $completedSteps[] = 'confirm_results';
        }

        $labelArtifacts = [];
        $documentArtifacts = [];
        if ($success) {
            $confirmEvent = $this->resolveEventFromResult($store, $account, $poll['result'] ?? null);
            [$labelArtifacts, $documentArtifacts] = $this->persistConfirmResultsArtifacts(
                $store,
                $account,
                is_array($poll['result']?->data ?? null) ? $poll['result']->data : [],
                $poll['result'] ?? null,
                $confirmEvent,
                $actor,
            );
        }

        [$evidenceReady, $evidenceReasons] = $this->evaluateEvidenceReady(
            $success,
            $completedSteps,
            $labelArtifacts,
            $documentArtifacts,
        );

        return [
            'success' => $success,
            'halted_reason' => $success ? null : 'confirm_results_incomplete',
            'failed_step' => $success ? null : 'confirm_results',
            'failed_shipment' => null,
            'steps' => $steps,
            'completed_steps' => $completedSteps,
            'consolidation_key' => $consolidationKey,
            'job_id' => $jobId,
            'event_ids' => $this->collectEventIds($steps),
            'label_artifacts' => $labelArtifacts,
            'document_artifacts' => $documentArtifacts,
            'label_count' => count($labelArtifacts),
            'document_count' => count($documentArtifacts),
            'evidence_ready' => $evidenceReady,
            'evidence_reasons' => $evidenceReasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $fixture
     */
    private function post(
        Store $store,
        CarrierAccount $account,
        string $path,
        array $payload,
        array $fixture,
    ): CarrierApiResult {
        $context = new FedExValidationEventContext(
            scenarioKey: (string) ($fixture['scenario_key'] ?? ''),
            testCaseKey: (string) ($fixture['key'] ?? ''),
            validationRegion: 'US',
        );

        $customerTransactionId = (string) ($fixture['customer_transaction_id'] ?? $fixture['key'] ?? '');

        return FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_CONSOLIDATION,
                path: $path,
                payload: $payload,
                requestSummary: [
                    'endpoint' => $path,
                    'api_family' => 'consolidation',
                    'operation' => $fixture['operation'] ?? null,
                    'scenario_key' => $fixture['scenario_key'] ?? null,
                    'test_case' => $fixture['key'] ?? null,
                    'customer_transaction_id' => $customerTransactionId,
                    'shipment_sequence' => $fixture['shipment_sequence'] ?? null,
                ],
                context: $context,
                headers: $customerTransactionId !== ''
                    ? ['x-customer-transaction-id' => $customerTransactionId]
                    : [],
            ),
            $path,
        );
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function pollConfirmResults(
        Store $store,
        CarrierAccount $account,
        array $fixture,
        array $payload,
    ): array {
        $attempts = [];
        $finalResult = null;
        $finalPayload = $payload;

        for ($attempt = 1; $attempt <= self::MAX_CONFIRM_RESULTS_ATTEMPTS; $attempt++) {
            $result = $this->post(
                $store,
                $account,
                $this->config->consolidationConfirmResultsPath(),
                $payload,
                array_merge($fixture, [
                    'customer_transaction_id' => (string) ($fixture['customer_transaction_id'] ?? 'IntegratorUS10_Confirm Results')
                        .($attempt > 1 ? '_poll_'.$attempt : ''),
                ]),
            );

            $attempts[] = [
                'attempt' => $attempt,
                'result' => $result,
                'payload' => $payload,
                'path' => $this->config->consolidationConfirmResultsPath(),
                'status' => $this->confirmResultsStatus($result->data ?? []),
            ];
            $finalResult = $result;

            if (! $result->success) {
                break;
            }

            $status = $this->confirmResultsStatus($result->data ?? []);
            if ($this->isConfirmResultsTerminal($status)) {
                break;
            }
        }

        return [
            'success' => $finalResult?->success === true && $this->isConfirmResultsSuccessful($finalResult->data ?? []),
            'attempts' => $attempts,
            'result' => $finalResult,
            'payload' => $finalPayload,
            'path' => $this->config->consolidationConfirmResultsPath(),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{type: string, index: string, date: string}|null
     */
    public function extractConsolidationKey(array $body): ?array
    {
        $key = data_get($body, 'output.consolidationKey')
            ?? data_get($body, 'consolidationKey')
            ?? data_get($body, 'output.transactionShipments.0.consolidationKey');

        if (! is_array($key)) {
            return null;
        }

        $type = (string) ($key['type'] ?? '');
        $index = (string) ($key['index'] ?? '');
        $date = (string) ($key['date'] ?? '');

        if ($type === '' || $index === '' || $date === '') {
            return null;
        }

        return [
            'type' => $type,
            'index' => $index,
            'date' => $date,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function extractJobId(array $body): ?string
    {
        $jobId = data_get($body, 'output.jobId')
            ?? data_get($body, 'jobId')
            ?? data_get($body, 'output.transactionShipments.0.jobId');

        return filled($jobId) ? (string) $jobId : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function confirmResultsStatus(array $body): string
    {
        $status = data_get($body, 'output.status')
            ?? data_get($body, 'status')
            ?? data_get($body, 'output.jobStatus')
            ?? data_get($body, 'jobStatus');

        return strtoupper((string) ($status ?? ''));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function isConfirmResultsSuccessful(array $body): bool
    {
        $status = $this->confirmResultsStatus($body);

        if (in_array($status, ['COMPLETED', 'COMPLETE', 'SUCCESS', 'READY'], true)) {
            return true;
        }

        // Some successful replies omit status but include completed consolidation detail / documents.
        return is_array(data_get($body, 'output.completedConsolidationDetail'))
            || is_array(data_get($body, 'output.transactionShipments'))
            || filled(data_get($body, 'output.masterTrackingNumber'));
    }

    public function isConfirmResultsTerminal(string $status): bool
    {
        return in_array($status, [
            '',
            'COMPLETED',
            'COMPLETE',
            'SUCCESS',
            'READY',
            'ERROR',
            'FAILED',
            'FAILURE',
        ], true);
    }

    /**
     * @param  array{type: string, index: string, date: string}  $key
     */
    private function assertDynamicKey(array $key): void
    {
        if (
            $key['index'] === FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_INDEX
            || $key['date'] === FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_DATE
        ) {
            throw new HttpException(422, 'Live US10 flow must not use the historical workbook consolidation key.');
        }
    }

    private function assertDynamicJobId(string $jobId): void
    {
        if ($jobId === FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_JOB_ID) {
            throw new HttpException(422, 'Live US10 flow must not use the historical workbook jobId.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoHistoricalIdentifiers(array $payload): void
    {
        $encoded = json_encode($payload) ?: '';

        if (str_contains($encoded, FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_INDEX)) {
            throw new HttpException(422, 'Historical workbook consolidation index must not appear in live US10 requests.');
        }

        if (str_contains($encoded, FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_JOB_ID)) {
            throw new HttpException(422, 'Historical workbook jobId must not appear in live US10 requests.');
        }
    }

    /**
     * @param  array<string, mixed>  $steps
     * @param  list<string>  $completedSteps
     * @return array<string, mixed>
     */
    private function halted(
        array $steps,
        string $reason,
        ?string $failedShipment = null,
        array $completedSteps = [],
        ?User $actor = null,
    ): array {
        $failedStep = match ($reason) {
            'create_failed', 'missing_consolidation_key' => 'create',
            'add_shipment_failed' => $failedShipment ?? 'add_shipment',
            'confirm_failed', 'missing_job_id' => 'confirm',
            default => $reason,
        };

        return [
            'success' => false,
            'halted_reason' => $reason,
            'failed_step' => $failedStep,
            'failed_shipment' => $failedShipment,
            'steps' => $steps,
            'completed_steps' => $completedSteps,
            'consolidation_key' => $steps['consolidation_key'] ?? null,
            'job_id' => $steps['job_id'] ?? null,
            'event_ids' => $this->collectEventIds($steps),
            'label_artifacts' => [],
            'document_artifacts' => [],
            'label_count' => 0,
            'document_count' => 0,
            'evidence_ready' => false,
            'evidence_reasons' => array_values(array_unique(array_filter([
                'api_not_successful',
                $reason,
            ]))),
        ];
    }

    /**
     * @param  array<string, mixed>  $steps
     * @return list<int>
     */
    private function collectEventIds(array $steps): array
    {
        $ids = [];

        $push = static function (?CarrierApiResult $result) use (&$ids): void {
            $eventId = data_get($result?->responseSummary, 'carrier_api_event_id');
            if (is_numeric($eventId)) {
                $ids[] = (int) $eventId;
            }
        };

        if (isset($steps['create']['result']) && $steps['create']['result'] instanceof CarrierApiResult) {
            $push($steps['create']['result']);
        }

        foreach ($steps['add_shipments'] ?? [] as $row) {
            if (($row['result'] ?? null) instanceof CarrierApiResult) {
                $push($row['result']);
            }
        }

        if (isset($steps['confirm']['result']) && $steps['confirm']['result'] instanceof CarrierApiResult) {
            $push($steps['confirm']['result']);
        }

        foreach ($steps['confirm_results']['attempts'] ?? [] as $attempt) {
            if (($attempt['result'] ?? null) instanceof CarrierApiResult) {
                $push($attempt['result']);
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolveEventFromResult(Store $store, CarrierAccount $account, ?CarrierApiResult $result): ?CarrierApiEvent
    {
        if ($result === null) {
            return null;
        }

        $eventId = data_get($result->responseSummary, 'carrier_api_event_id');
        if (! is_numeric($eventId)) {
            return null;
        }

        return CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->whereKey((int) $eventId)
            ->first();
    }

    /**
     * Persist any encoded labels/documents found under confirm-results output.
     *
     * @param  array<string, mixed>  $body
     * @return array{0: list<FedExValidationArtifact>, 1: list<FedExValidationArtifact>}
     */
    private function persistConfirmResultsArtifacts(
        Store $store,
        CarrierAccount $account,
        array $body,
        ?CarrierApiResult $result,
        ?CarrierApiEvent $event,
        ?User $actor,
    ): array {
        $found = $this->extractEncodedDocuments($body);
        $labelArtifacts = [];
        $documentArtifacts = [];
        $transactionId = data_get($result?->responseSummary, 'fedex_transaction_id');
        $scenarioKey = 'consolidation_us10_confirm_results';
        $testCaseKey = 'IntegratorUS10_CONFIRM_RESULTS';
        $labelSequence = 0;

        foreach ($found as $index => $document) {
            $encoded = $document['encoded'] ?? null;
            if (! is_string($encoded) || $encoded === '') {
                continue;
            }

            $binary = base64_decode($encoded, true);
            if ($binary === false || $binary === '') {
                continue;
            }

            $isLabel = (bool) ($document['is_label'] ?? false);
            $isCci = (bool) ($document['is_cci'] ?? false);
            $contentType = strtoupper((string) ($document['content_type'] ?? ''));

            if (! $isLabel && ! $isCci && $contentType === '') {
                // Persist unknown encoded documents as validation documents when they look like PDFs/labels.
                if (str_starts_with($binary, '%PDF')) {
                    $isCci = false;
                } elseif (str_contains($binary, '^XA')) {
                    $isLabel = true;
                } else {
                    continue;
                }
            }

            if ($isLabel) {
                $labelSequence++;
                $format = str_contains($binary, '^XA') ? 'ZPLII' : (str_starts_with($binary, "\x89PNG") ? 'PNG' : 'PDF');
                $extension = match ($format) {
                    'ZPLII' => 'zpl',
                    'PNG' => 'png',
                    default => 'pdf',
                };
                $relativeDir = "fedex-validation/{$store->id}/labels";
                $filename = 'us10-child-label-'.$labelSequence.'-'.now()->format('YmdHis').'.'.$extension;
                $relativePath = $relativeDir.'/'.$filename;
                $absolutePath = storage_path('app/'.$relativePath);
                File::ensureDirectoryExists(dirname($absolutePath));
                File::put($absolutePath, $binary);

                if (! FedExLabelArtifactValidator::isValid($absolutePath, $format)) {
                    File::delete($absolutePath);

                    continue;
                }

                $labelArtifacts[] = FedExValidationArtifact::query()->create([
                    'store_id' => $store->id,
                    'carrier_account_id' => $account->id,
                    'registration_session_id' => $account->registration_session_id,
                    'carrier_api_event_id' => $event?->id,
                    'environment' => $account->environment,
                    'artifact_type' => 'ship_label_'.strtolower($format),
                    'scenario_key' => $scenarioKey,
                    'test_case_key' => $testCaseKey,
                    'label_format' => $format,
                    'package_sequence' => $labelSequence,
                    'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
                    'label' => 'IntegratorUS10 · child label '.$labelSequence,
                    'original_filename' => $filename,
                    'mime_type' => match ($extension) {
                        'zpl' => 'application/zpl',
                        'png' => 'image/png',
                        default => 'application/pdf',
                    },
                    'file_size' => strlen($binary),
                    'sha256' => hash('sha256', $binary),
                    'file_path' => $relativePath,
                    'request_summary_json' => ['api_family' => 'consolidation', 'operation' => 'confirm_results'],
                    'response_summary_json' => array_filter([
                        'http_status' => data_get($result?->responseSummary, 'http_status'),
                        'fedex_transaction_id' => $transactionId,
                        'content_type' => $contentType !== '' ? $contentType : null,
                        'label_saved' => true,
                    ]),
                    'metadata_json' => [
                        'content_type' => $contentType !== '' ? $contentType : null,
                        'api_family' => 'consolidation',
                    ],
                    'fedex_transaction_id' => is_string($transactionId) ? $transactionId : null,
                    'created_by' => $actor?->id,
                ]);

                continue;
            }

            if (! str_starts_with($binary, '%PDF')) {
                continue;
            }

            $type = $isCci || str_contains($contentType, 'CONSOLIDATION') || str_contains($contentType, 'COMMERCIAL_INVOICE')
                ? 'consolidation_commercial_invoice'
                : 'consolidation_document';
            $relativeDir = "fedex-validation/{$store->id}/documents";
            $filename = 'us10-'.$type.'-'.($index + 1).'-'.now()->format('YmdHis').'.pdf';
            $relativePath = $relativeDir.'/'.$filename;
            $absolutePath = storage_path('app/'.$relativePath);
            File::ensureDirectoryExists(dirname($absolutePath));
            File::put($absolutePath, $binary);

            $documentArtifacts[] = FedExValidationArtifact::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'registration_session_id' => $account->registration_session_id,
                'carrier_api_event_id' => $event?->id,
                'environment' => $account->environment,
                'artifact_type' => $type,
                'scenario_key' => $scenarioKey,
                'test_case_key' => $testCaseKey,
                'label_format' => 'PDF',
                'package_sequence' => null,
                'artifact_role' => FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT,
                'label' => 'IntegratorUS10 · '.($type === 'consolidation_commercial_invoice' ? 'Consolidated Commercial Invoice' : 'Consolidation document'),
                'original_filename' => $filename,
                'mime_type' => 'application/pdf',
                'file_size' => strlen($binary),
                'sha256' => hash('sha256', $binary),
                'file_path' => $relativePath,
                'request_summary_json' => ['api_family' => 'consolidation', 'operation' => 'confirm_results'],
                'response_summary_json' => array_filter([
                    'http_status' => data_get($result?->responseSummary, 'http_status'),
                    'fedex_transaction_id' => $transactionId,
                    'content_type' => $contentType !== '' ? $contentType : null,
                    'document_saved' => true,
                ]),
                'metadata_json' => [
                    'content_type' => $contentType !== '' ? $contentType : null,
                    'api_family' => 'consolidation',
                    'is_cci' => $type === 'consolidation_commercial_invoice',
                ],
                'fedex_transaction_id' => is_string($transactionId) ? $transactionId : null,
                'created_by' => $actor?->id,
            ]);
        }

        return [$labelArtifacts, $documentArtifacts];
    }

    /**
     * Walk confirm-results output for encoded labels and documents.
     *
     * @param  array<string, mixed>  $body
     * @return list<array{encoded: string, content_type: ?string, is_label: bool, is_cci: bool}>
     */
    private function extractEncodedDocuments(array $body): array
    {
        $found = [];

        $walk = function (mixed $node, string $path = '') use (&$walk, &$found): void {
            if (! is_array($node)) {
                return;
            }

            $encoded = $node['encodedLabel'] ?? $node['encodedlabel'] ?? null;
            if (is_string($encoded) && $encoded !== '') {
                $contentType = strtoupper((string) ($node['contentType'] ?? $node['docType'] ?? $node['type'] ?? ''));
                $isLabel = $contentType === 'LABEL'
                    || str_contains($contentType, 'LABEL')
                    || str_ends_with($path, 'packageDocuments')
                    || str_contains($path, 'pieceResponses');
                $isCci = str_contains($contentType, 'CONSOLIDATION_COMMERCIAL_INVOICE')
                    || $contentType === 'CONSOLIDATED_COMMERCIAL_INVOICE'
                    || (str_contains($contentType, 'COMMERCIAL_INVOICE') && ! $isLabel);

                $found[] = [
                    'encoded' => $encoded,
                    'content_type' => $contentType !== '' ? $contentType : null,
                    'is_label' => $isLabel && ! $isCci,
                    'is_cci' => $isCci,
                ];
            }

            foreach ($node as $key => $child) {
                if (is_array($child)) {
                    $walk($child, $path === '' ? (string) $key : $path.'.'.$key);
                }
            }
        };

        $walk($body);

        return $found;
    }

    /**
     * @param  list<FedExValidationArtifact>  $labelArtifacts
     * @param  list<FedExValidationArtifact>  $documentArtifacts
     * @param  list<string>  $completedSteps
     * @return array{0: bool, 1: list<string>}
     */
    private function evaluateEvidenceReady(
        bool $success,
        array $completedSteps,
        array $labelArtifacts,
        array $documentArtifacts,
    ): array {
        $reasons = [];

        if (! $success) {
            $reasons[] = 'api_not_successful';
        }

        $expectedSteps = array_merge(
            ['create'],
            $this->fixtures->addShipmentKeys(),
            ['confirm', 'confirm_results'],
        );
        foreach ($expectedSteps as $step) {
            if (! in_array($step, $completedSteps, true)) {
                $reasons[] = 'us10_step_incomplete_'.$step;
            }
        }

        $expectedLabelCount = count($this->fixtures->addShipmentKeys());
        $validLabels = array_values(array_filter(
            $labelArtifacts,
            static function (FedExValidationArtifact $artifact): bool {
                if ($artifact->artifact_role !== FedExValidationArtifact::ROLE_GENERATED_LABEL) {
                    return false;
                }
                $path = $artifact->absolutePath();
                if ($path === null || ! is_file($path) || filesize($path) <= 0) {
                    return false;
                }
                if (! filled($artifact->sha256) || hash_file('sha256', $path) !== (string) $artifact->sha256) {
                    return false;
                }

                return FedExLabelArtifactValidator::isValid(
                    $path,
                    strtoupper((string) ($artifact->label_format ?: 'PDF')),
                );
            },
        ));
        if (count($validLabels) < $expectedLabelCount) {
            $reasons[] = 'us10_child_labels_missing';
            $reasons[] = 'us10_child_labels_expected_'.$expectedLabelCount.'_found_'.count($validLabels);
        }

        $cci = collect($documentArtifacts)->first(
            static function (FedExValidationArtifact $artifact): bool {
                if ($artifact->artifact_type !== 'consolidation_commercial_invoice'
                    || $artifact->artifact_role !== FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT) {
                    return false;
                }
                $path = $artifact->absolutePath();
                if ($path === null || ! is_file($path) || filesize($path) <= 0) {
                    return false;
                }
                if (! filled($artifact->sha256) || hash_file('sha256', $path) !== (string) $artifact->sha256) {
                    return false;
                }

                return str_starts_with((string) file_get_contents($path), '%PDF');
            }
        );
        if ($cci === null) {
            $reasons[] = 'us10_cci_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [$reasons === [], $reasons];
    }
}
