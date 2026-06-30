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
use App\Services\Carriers\FedEx\Presenters\FedExMerchantCheckPresenter;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExSaturdayDeliveryShipDateResolver;
use App\Services\Carriers\FedEx\Validation\FedExShipEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExShipFixtureResolver;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FedExShipValidationService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExShipFixtureResolver $fixtureResolver,
        private readonly FedExShipPayloadFactory $payloadFactory,
        private readonly FedExShipResponseParser $responseParser,
        private readonly FedExShipEvidenceRules $evidenceRules,
        private readonly FedExSaturdayDeliveryShipDateResolver $saturdayDeliveryShipDateResolver,
    ) {}

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

        $fixture = $this->fixtureResolver->fixture($testCaseKey);
        $endpoint = $this->config->shipValidatePath($account->environment);
        $labelFormat = strtoupper((string) ($overrides['label_format'] ?? $fixture['label_format'] ?? 'PDF'));
        $shipDateOverride = $this->shipDateOverridesForFixture($store, $account, $fixture, $labelFormat);
        $payload = $this->payloadFactory->buildShipmentPayload($account, $fixture, $labelFormat, array_merge($overrides, $shipDateOverride));
        $context = $this->eventContext($fixture, $testCaseKey, $labelFormat);
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
                'scenario_key' => $fixture['scenario_key'] ?? null,
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
                context: $context,
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
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>, artifacts: list<FedExValidationArtifact>}
     */
    public function createSandboxLabel(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        ?string $labelFormat = null,
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
                    'label_format' => strtoupper((string) $labelFormat),
                ],
            );

            return [
                'result' => $result,
                'presentation' => FedExMerchantCheckPresenter::shipLabel($result, null, $testCaseKey, (string) $labelFormat),
                'artifacts' => [],
            ];
        }

        $fixture = $this->fixtureResolver->fixture($testCaseKey);
        $labelFormat = strtoupper(trim($labelFormat ?? $this->fixtureResolver->lockedLabelFormat($testCaseKey)));
        abort_unless(
            strtoupper((string) ($fixture['label_format'] ?? '')) === $labelFormat,
            422,
            'This validation scenario requires '.$fixture['label_format'].' labels. Arbitrary format pairing is not allowed.',
        );

        $endpoint = $this->config->shipCreatePath($account->environment);
        $shipDateOverride = $this->shipDateOverridesForFixture($store, $account, $fixture, $labelFormat);
        $payload = $this->payloadFactory->buildShipmentPayload($account, $fixture, $labelFormat, array_merge($overrides, $shipDateOverride));
        $context = $this->eventContext($fixture, $testCaseKey, $labelFormat);
        $requestSummary = $this->buildRequestSummary($account, $endpoint, $testCaseKey, $fixture, array_merge($overrides, $shipDateOverride, [
            'label_format' => $labelFormat,
            'scenario_key' => $fixture['scenario_key'] ?? null,
            'fixture_version' => $fixture['fixture_version'] ?? null,
            'ship_date' => data_get($payload, 'requestedShipment.shipDatestamp'),
        ]));

        $result = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
                path: $endpoint,
                payload: $payload,
                requestSummary: $requestSummary,
                context: $context,
            ),
            $endpoint,
        );

        $artifacts = [];

        if ($result->success) {
            $eventId = data_get($result->responseSummary, 'carrier_api_event_id');
            $event = is_numeric($eventId)
                ? CarrierApiEvent::query()
                    ->where('store_id', $store->id)
                    ->where('carrier_account_id', $account->id)
                    ->whereKey((int) $eventId)
                    ->first()
                : CarrierApiEvent::query()
                    ->where('store_id', $store->id)
                    ->where('carrier_account_id', $account->id)
                    ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
                    ->where('test_case_key', $testCaseKey)
                    ->where('scenario_key', (string) ($fixture['scenario_key'] ?? ''))
                    ->where('label_format', strtoupper($labelFormat))
                    ->latest('id')
                    ->first();

            $parsed = $this->responseParser->parse(is_array($result->data) ? $result->data : null);
            $responseValidation = $event !== null
                ? $this->evidenceRules->validateResponse($event, $testCaseKey)
                : ['valid' => false, 'reasons' => ['missing_event'], 'parsed' => $parsed];

            if ($event !== null) {
                $event->forceFill([
                    'response_summary' => array_merge(
                        is_array($event->response_summary) ? $event->response_summary : [],
                        $this->buildShipResponseSummary($testCaseKey, $labelFormat, $fixture, $parsed, $responseValidation),
                    ),
                ])->save();
            }

            if ($responseValidation['valid'] ?? false) {
                $artifacts = $this->persistLabelArtifacts(
                    store: $store,
                    account: $account,
                    testCaseKey: $testCaseKey,
                    labelFormat: $labelFormat,
                    fixture: $fixture,
                    result: $result,
                    parsed: $parsed,
                    requestSummary: $requestSummary,
                    actor: $actor,
                    event: $event,
                );
            }
        }

        return [
            'result' => $result,
            'presentation' => FedExMerchantCheckPresenter::shipLabel($result, $artifacts[0] ?? null, $testCaseKey, $labelFormat),
            'artifacts' => $artifacts,
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
                context: new FedExValidationEventContext(scenarioKey: 'ship_cancel'),
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
                'expected_service_type' => $fixture['expected_service_type'] ?? $fixture['service_type'] ?? null,
                'packaging_type' => $fixture['packaging_type'] ?? null,
                'package_count' => count($fixture['packages'] ?? []),
                'fixture_version' => $overrides['fixture_version'] ?? ($fixture['fixture_version'] ?? null),
                'ship_date' => $overrides['ship_date'] ?? null,
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
     * @param  array<string, mixed>  $fixture
     */
    private function eventContext(array $fixture, string $testCaseKey, string $labelFormat): FedExValidationEventContext
    {
        return new FedExValidationEventContext(
            registrationSessionId: null,
            scenarioKey: (string) ($fixture['scenario_key'] ?? FedExValidationScenarioCatalog::scenarioKeyForTestCase($testCaseKey)),
            testCaseKey: $testCaseKey,
            labelFormat: strtoupper($labelFormat),
            packageCount: count($fixture['packages'] ?? []),
            validationRegion: (string) ($fixture['validation_region'] ?? 'US'),
        );
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $responseValidation
     * @return array<string, mixed>
     */
    private function buildShipResponseSummary(
        string $testCaseKey,
        string $labelFormat,
        array $fixture,
        array $parsed,
        array $responseValidation,
    ): array {
        $expected = $this->evidenceRules->expectedMetadata($testCaseKey);

        return [
            'expected_service_type' => $expected['expected_service_type'],
            'request_service_type' => $fixture['service_type'] ?? null,
            'response_service_type' => $parsed['service_type'] ?? null,
            'service_matches' => (bool) ($responseValidation['valid'] ?? false),
            'expected_label_format' => strtoupper($labelFormat),
            'response_label_format' => strtoupper((string) (collect($parsed['labels'])->first()['image_type'] ?? $labelFormat)),
            'label_format_matches' => collect($parsed['labels'])->every(
                fn (array $label): bool => strtoupper((string) ($label['image_type'] ?? $labelFormat)) === strtoupper($labelFormat)
            ),
            'expected_package_count' => (int) $expected['expected_package_count'],
            'response_label_count' => count($parsed['labels']),
            'package_count_matches' => count($parsed['labels']) === (int) $expected['expected_package_count'],
            'master_tracking_number_last4' => $parsed['master_tracking_number_last4'] ?? null,
            'package_sequences' => array_keys($parsed['labels']),
            'validation_reasons' => $responseValidation['reasons'] ?? [],
            'canonical_ready' => (bool) ($responseValidation['valid'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $requestSummary
     * @param  array<string, mixed>  $parsed
     * @return list<FedExValidationArtifact>
     */
    private function persistLabelArtifacts(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        string $labelFormat,
        array $fixture,
        CarrierApiResult $result,
        array $parsed,
        array $requestSummary,
        ?User $actor,
        ?CarrierApiEvent $event,
    ): array {
        $artifacts = [];
        $transactionId = data_get($result->responseSummary, 'fedex_transaction_id');
        $extension = match (strtoupper($labelFormat)) {
            'PNG' => 'png',
            'ZPL', 'ZPLII' => 'zpl',
            default => 'pdf',
        };

        foreach ($parsed['labels'] as $packageSequence => $document) {
            $encoded = $document['encoded_label'] ?? null;
            if (! is_string($encoded) || $encoded === '') {
                continue;
            }

            $binary = base64_decode($encoded, true);
            if ($binary === false || $binary === '') {
                continue;
            }

            $relativeDir = "fedex-validation/{$store->id}/labels";
            $filename = Str::slug($testCaseKey).'-pkg'.$packageSequence.'-'.strtolower($labelFormat).'-'.now()->format('YmdHis').'.'.$extension;
            $relativePath = $relativeDir.'/'.$filename;
            $absolutePath = storage_path('app/'.$relativePath);
            File::ensureDirectoryExists(dirname($absolutePath));
            File::put($absolutePath, $binary);

            if (! \App\Services\Carriers\FedEx\Validation\FedExLabelArtifactValidator::isValid($absolutePath, $labelFormat)) {
                File::delete($absolutePath);

                continue;
            }

            $trackingNumber = $document['tracking_number'] ?? null;

            $artifacts[] = FedExValidationArtifact::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'registration_session_id' => $account->registration_session_id,
                'carrier_api_event_id' => $event?->id,
                'environment' => $account->environment,
                'artifact_type' => 'ship_label_'.strtolower($labelFormat),
                'scenario_key' => (string) ($fixture['scenario_key'] ?? null),
                'test_case_key' => $testCaseKey,
                'label_format' => strtoupper($labelFormat),
                'package_sequence' => (int) $packageSequence,
                'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
                'label' => $testCaseKey.' · package '.$packageSequence.' · '.strtoupper($labelFormat),
                'original_filename' => $filename,
                'mime_type' => match ($extension) {
                    'png' => 'image/png',
                    'zpl' => 'application/zpl',
                    default => 'application/pdf',
                },
                'file_size' => strlen($binary),
                'sha256' => hash('sha256', $binary),
                'file_path' => $relativePath,
                'request_summary_json' => $requestSummary,
                'response_summary_json' => array_filter([
                    'http_status' => data_get($result->responseSummary, 'http_status'),
                    'fedex_transaction_id' => $transactionId,
                    'tracking_number_last4' => $document['tracking_number_last4'] ?? null,
                    'master_tracking_number_last4' => $parsed['master_tracking_number_last4'] ?? null,
                    'label_saved' => true,
                    'label_format' => strtoupper($labelFormat),
                    'package_sequence' => (int) $packageSequence,
                    'binary_validation_status' => 'passed',
                    'service_type' => $fixture['service_type'] ?? null,
                    'label_stock_type' => $fixture['label_stock_type'] ?? null,
                    'expected_package_count' => count($fixture['packages'] ?? []),
                ]),
                'metadata_json' => [
                    'document_type' => $document['document_type'] ?? 'LABEL',
                    'image_type' => $document['image_type'] ?? strtoupper($labelFormat),
                ],
                'fedex_transaction_id' => is_string($transactionId) ? $transactionId : null,
                'created_by' => $actor?->id,
            ]);
        }

        return $artifacts;
    }

    /**
     * @deprecated use FedExShipResponseParser via persistLabelArtifacts()
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function extractPackageDocuments(array $data): array
    {
        $documents = [];

        foreach ((array) data_get($data, 'output.transactionShipments', []) as $shipment) {
            if (! is_array($shipment)) {
                continue;
            }

            foreach ((array) ($shipment['pieceResponses'] ?? []) as $index => $piece) {
                if (! is_array($piece)) {
                    continue;
                }

                $sequence = (int) ($piece['packageSequenceNumber'] ?? ($index + 1));

                foreach ((array) ($piece['packageDocuments'] ?? []) as $document) {
                    if (! is_array($document)) {
                        continue;
                    }

                    $documents[$sequence] = array_merge($document, [
                        'trackingNumber' => $piece['trackingNumber'] ?? ($shipment['masterTrackingNumber'] ?? null),
                    ]);
                }
            }
        }

        ksort($documents);

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @deprecated retained for backward compatibility in tests
     */
    private function extractEncodedLabel(array $data): ?string
    {
        $documents = $this->extractPackageDocuments($data);

        foreach ($documents as $document) {
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

    /**
     * @param  array<string, mixed>  $fixture
     * @return array{ship_date?: string}
     */
    private function shipDateOverridesForFixture(
        Store $store,
        CarrierAccount $account,
        array $fixture,
        string $labelFormat,
    ): array {
        return match ($fixture['ship_date_strategy'] ?? null) {
            'next_valid_friday' => ['ship_date' => $this->fixtureResolver->nextValidFriday()],
            'saturday_delivery_friday' => [
                'ship_date' => $this->saturdayDeliveryShipDateResolver->resolve($store, $account, $fixture, $labelFormat),
            ],
            default => [],
        };
    }
}
