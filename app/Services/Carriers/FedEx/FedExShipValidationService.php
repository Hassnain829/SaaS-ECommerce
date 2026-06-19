<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\DTO\CarrierApiResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FedExShipValidationService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExShipTestCaseFixtureService $fixtureService,
        private readonly FedExShipPayloadFactory $payloadFactory,
    ) {
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>}
     */
    public function validateShipment(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        array $overrides = [],
    ): array {
        $this->assertSandboxShipTools($account);

        $fixture = $this->fixtureService->fixture($testCaseKey);
        $endpoint = $this->config->shipValidatePath($account->environment);
        $labelFormat = strtoupper((string) ($overrides['label_format'] ?? 'PDF'));
        $payload = $this->payloadFactory->buildShipmentPayload($account, $fixture, $labelFormat, $overrides);
        $requestSummary = array_merge(
            $this->buildRequestSummary(
                $account,
                $endpoint,
                $testCaseKey,
                $fixture,
                array_merge($overrides, ['label_format' => $labelFormat]),
            ),
            [
                'label_format' => $labelFormat,
                'ship_validation_only' => true,
            ],
        );

        $result = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_SHIP_VALIDATE,
                path: $endpoint,
                payload: $payload,
                requestSummary: $requestSummary,
            ),
            $endpoint,
        );

        return [
            'result' => $result,
            'presentation' => FedExMerchantCheckPresenter::shipValidation($result, $fixture, $testCaseKey),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>, artifact: ?FedExValidationArtifact}
     */
    public function createSandboxLabel(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        string $labelFormat = 'PDF',
        array $overrides = [],
        ?User $actor = null,
    ): array {
        $this->assertSandboxShipTools($account);

        if (! $this->config->allowsShipLabelGeneration($account->environment)) {
            $result = CarrierApiResult::failure(
                message: 'Sandbox label generation is disabled. Enable FEDEX_SHIP_SANDBOX_LABEL_GENERATION_ENABLED or FEDEX_SHIP_EVIDENCE_ENABLED in your environment configuration.',
                code: 'ship_label_disabled',
                requestSummary: [
                    'local_validation' => true,
                    'test_case' => $testCaseKey,
                    'label_format' => strtoupper($labelFormat),
                ],
            );

            return [
                'result' => $result,
                'presentation' => FedExMerchantCheckPresenter::shipLabel($result, null, $testCaseKey, $labelFormat),
                'artifact' => null,
            ];
        }

        $fixture = $this->fixtureService->fixture($testCaseKey);
        $endpoint = $this->config->shipCreatePath($account->environment);
        $labelFormat = strtoupper(trim($labelFormat));
        $payload = $this->payloadFactory->buildShipmentPayload($account, $fixture, $labelFormat, $overrides);
        $requestSummary = $this->buildRequestSummary($account, $endpoint, $testCaseKey, $fixture, array_merge($overrides, [
            'label_format' => $labelFormat,
        ]));

        $result = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
                path: $endpoint,
                payload: $payload,
                requestSummary: $requestSummary,
            ),
            $endpoint,
        );

        $artifact = null;

        if ($result->success) {
            $artifact = $this->persistLabelArtifact($store, $account, $testCaseKey, $labelFormat, $result, $requestSummary, $actor);
        }

        return [
            'result' => $result,
            'presentation' => FedExMerchantCheckPresenter::shipLabel($result, $artifact, $testCaseKey, $labelFormat),
            'artifact' => $artifact,
        ];
    }

    /**
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>}
     */
    public function cancelShipment(
        Store $store,
        CarrierAccount $account,
        string $trackingNumber,
    ): array {
        $this->assertSandboxShipTools($account);

        $trackingNumber = trim($trackingNumber);
        $endpoint = $this->config->shipCancelPath($account->environment);
        $payload = [
            'accountNumber' => [
                'value' => (string) $account->provider_account_number,
            ],
            'trackingNumber' => $trackingNumber,
        ];

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CANCEL,
                'tracking_number_last4' => strlen($trackingNumber) >= 4 ? substr($trackingNumber, -4) : null,
            ],
        );

        $result = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_SHIP_CANCEL,
                path: $endpoint,
                payload: $payload,
                requestSummary: $requestSummary,
            ),
            $endpoint,
        );

        return [
            'result' => $result,
            'presentation' => FedExMerchantCheckPresenter::shipCancel($result, $trackingNumber),
        ];
    }

    private function assertSandboxShipTools(CarrierAccount $account): void
    {
        abort_unless($account->usesFedExIntegratorProvider(), 422, 'Ship validation tools require a FedEx Integrator Provider account.');

        if ($this->config->environment($account->environment) === CarrierAccount::ENVIRONMENT_LIVE) {
            abort_unless(
                $this->config->productionEnabled() && $this->config->shipEvidenceEnabled(),
                422,
                'Live FedEx ship tools are disabled until production integrator access is explicitly enabled.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function buildRequestSummary(
        CarrierAccount $account,
        string $endpoint,
        string $testCaseKey,
        array $fixture,
        array $overrides = [],
    ): array {
        return array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => str_contains($endpoint, 'validate')
                    ? CarrierApiEvent::ACTION_FEDEX_SHIP_VALIDATE
                    : CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
                'test_case' => $testCaseKey,
                'service_type' => $fixture['service_type'] ?? null,
                'packaging_type' => $fixture['packaging_type'] ?? null,
                'package_count' => count($fixture['packages'] ?? []),
                'shipper_city' => data_get($fixture, 'shipper.city'),
                'shipper_state' => data_get($fixture, 'shipper.state'),
                'shipper_postal_code' => data_get($fixture, 'shipper.postal_code'),
                'shipper_country' => data_get($fixture, 'shipper.country_code'),
                'recipient_city' => data_get($fixture, 'recipient.city'),
                'recipient_state' => data_get($fixture, 'recipient.state'),
                'recipient_postal_code' => data_get($fixture, 'recipient.postal_code'),
                'recipient_country' => data_get($fixture, 'recipient.country_code'),
                'recipient_residential' => (bool) data_get($fixture, 'recipient.residential', false),
                'label_format' => $overrides['label_format'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $requestSummary
     */
    private function persistLabelArtifact(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        string $labelFormat,
        CarrierApiResult $result,
        array $requestSummary,
        ?User $actor,
    ): ?FedExValidationArtifact {
        $encodedLabel = $this->extractEncodedLabel($result->data ?? []);
        $trackingNumber = $this->extractTrackingNumber($result->data ?? []);
        $transactionId = data_get($result->responseSummary, 'fedex_transaction_id');

        if ($encodedLabel === null) {
            return null;
        }

        $extension = match (strtoupper($labelFormat)) {
            'PNG' => 'png',
            'ZPL', 'ZPLII' => 'zpl',
            default => 'pdf',
        };

        $relativeDir = "fedex-validation/{$store->id}/labels";
        $filename = Str::slug($testCaseKey).'-'.strtolower($labelFormat).'-'.now()->format('YmdHis').'.'.$extension;
        $relativePath = $relativeDir.'/'.$filename;
        $absolutePath = storage_path('app/'.$relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, base64_decode($encodedLabel, true) ?: '');

        return FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'environment' => $account->environment,
            'artifact_type' => 'ship_label_'.strtolower($labelFormat),
            'label' => $testCaseKey.' · '.strtoupper($labelFormat),
            'file_path' => $relativePath,
            'request_summary_json' => $requestSummary,
            'response_summary_json' => array_filter([
                'http_status' => data_get($result->responseSummary, 'http_status'),
                'fedex_transaction_id' => $transactionId,
                'tracking_number_last4' => $trackingNumber !== null && strlen($trackingNumber) >= 4
                    ? substr($trackingNumber, -4)
                    : null,
                'label_saved' => true,
                'label_format' => strtoupper($labelFormat),
            ]),
            'fedex_transaction_id' => is_string($transactionId) ? $transactionId : null,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractEncodedLabel(array $data): ?string
    {
        $documents = data_get($data, 'output.transactionShipments.0.pieceResponses.0.packageDocuments', []);

        if (! is_array($documents)) {
            return null;
        }

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $encoded = $document['encodedLabel'] ?? null;

            if (is_string($encoded) && $encoded !== '') {
                return $encoded;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractTrackingNumber(array $data): ?string
    {
        $tracking = data_get($data, 'output.transactionShipments.0.masterTrackingNumber')
            ?? data_get($data, 'output.transactionShipments.0.pieceResponses.0.trackingNumber');

        return is_string($tracking) && $tracking !== '' ? $tracking : null;
    }
}
