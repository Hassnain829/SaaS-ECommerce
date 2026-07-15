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
use App\Services\Carriers\FedEx\Validation\FedExFreightLtlEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExFreightLtlFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExLabelArtifactValidator;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Freight LTL create-shipment operation.
 *
 * Official Freight LTL has no documented validation-only endpoint. A successful
 * call to POST /ship/v1/freight/shipments creates the shipment/labels.
 */
class FedExFreightLtlService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExFreightLtlFixtureService $fixtureService,
        private readonly FedExFreightLtlPayloadFactory $payloadFactory,
        private readonly FedExFreightLtlEvidenceRules $evidenceRules,
        private readonly FedExFreightLtlResponseParser $responseParser,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{
     *   result: CarrierApiResult,
     *   payload: array<string, mixed>,
     *   label_artifacts: list<FedExValidationArtifact>,
     *   document_artifacts: list<FedExValidationArtifact>,
     *   parsed: array<string, mixed>,
     *   response_validation: array{valid: bool, reasons: list<string>, parsed?: array<string, mixed>},
     *   evidence_ready: bool,
     *   evidence_reasons: list<string>
     * }
     */
    public function createShipment(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        array $overrides = [],
        ?User $actor = null,
    ): array {
        abort_unless(in_array($testCaseKey, $this->fixtureService->testCaseKeys(), true), 404);
        abort_unless($testCaseKey === 'IntegratorUS08', 422, 'Only IntegratorUS08 is supported for Freight LTL validation.');
        abort_unless(
            $this->config->freightLtlApiEnabled(),
            422,
            $this->config->freightLtlApiDisabledMessage(),
        );
        abort_unless(
            $this->config->validationUs08Enabled(),
            422,
            'IntegratorUS08 Freight LTL validation is disabled.',
        );
        abort_unless($account->isSandbox(), 422, 'IntegratorUS08 Freight validation is sandbox-only.');
        abort_unless(
            $account->usesFedExIntegratorProvider(),
            422,
            'IntegratorUS08 requires the connected FedEx Integrator Provider (child credential) context.',
        );

        $fixture = $this->fixtureService->fixture($testCaseKey);
        $labelFormat = strtoupper((string) ($overrides['label_format'] ?? $fixture['label_format'] ?? 'ZPLII'));
        $endpoint = '/'.ltrim($this->config->freightLtlShipPath($account->environment), '/');
        $payload = $this->payloadFactory->buildShipmentPayload($account, $fixture, $labelFormat, $overrides);

        $this->assertLocalPreflight($account, $fixture, $endpoint, $labelFormat, $payload);

        $validation = $this->evidenceRules->validateRequest($payload);
        abort_unless($validation['valid'], 422, 'Freight LTL payload failed local evidence rules: '.implode(', ', $validation['reasons']));

        $freightAccount = (string) ($fixture['freight_account_number'] ?? $fixture['account_number'] ?? '');
        $freightAccountLast4 = strlen($freightAccount) >= 4 ? substr($freightAccount, -4) : null;

        $context = new FedExValidationEventContext(
            scenarioKey: (string) ($fixture['scenario_key'] ?? FedExValidationScenarioCatalog::scenarioKeyForTestCase($testCaseKey)),
            testCaseKey: $testCaseKey,
            labelFormat: $labelFormat,
            packageCount: (int) ($fixture['expected_package_count'] ?? 1),
            validationRegion: 'US',
        );

        $result = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
                path: $endpoint,
                payload: $payload,
                requestSummary: [
                    'endpoint' => $endpoint,
                    'test_case' => $testCaseKey,
                    'label_format' => $labelFormat,
                    'api_family' => 'freight_ltl',
                    'scenario_key' => $fixture['scenario_key'] ?? null,
                    'customer_transaction_id' => $testCaseKey,
                    'freight_account_last4' => $freightAccountLast4,
                    'oauth_context' => 'integrator_child',
                    'one_click' => true,
                ],
                context: $context,
            ),
            $endpoint,
        );

        $labelArtifacts = [];
        $documentArtifacts = [];
        $parsed = $this->responseParser->parse(is_array($result->data) ? $result->data : null);
        $responseValidation = [
            'valid' => false,
            'reasons' => ['api_not_successful'],
            'parsed' => $parsed,
        ];

        if ($result->success) {
            $event = $this->resolveEvent($store, $account, $result, $fixture, $testCaseKey, $labelFormat);
            $responseValidation = $this->evidenceRules->validateResponse($parsed, $event);

            if ($event !== null) {
                $event->forceFill([
                    'response_summary' => array_merge(
                        is_array($event->response_summary) ? $event->response_summary : [],
                        $this->safeResponseSummary($parsed, $responseValidation),
                    ),
                ])->save();
            }

            if ($responseValidation['valid']) {
                $labelArtifacts = $this->persistLabelArtifacts(
                    $store,
                    $account,
                    $testCaseKey,
                    $labelFormat,
                    $fixture,
                    $result,
                    $parsed,
                    $actor,
                    $event,
                );
                $documentArtifacts = $this->persistDocumentArtifacts(
                    $store,
                    $account,
                    $testCaseKey,
                    $fixture,
                    $result,
                    $parsed,
                    $actor,
                    $event,
                );

                if ($event !== null) {
                    $event->forceFill([
                        'response_summary' => array_merge(
                            is_array($event->response_summary) ? $event->response_summary : [],
                            [
                                'generated_label_count' => count($labelArtifacts),
                                'generated_document_count' => count($documentArtifacts),
                                'bol_present' => collect($documentArtifacts)->contains(
                                    fn (FedExValidationArtifact $a): bool => $a->artifact_type === 'freight_bill_of_lading'
                                ),
                            ],
                        ),
                    ])->save();
                }
            }
        }

        [$evidenceReady, $evidenceReasons] = $this->evaluateEvidenceReady(
            $result,
            $responseValidation,
            $labelArtifacts,
            $documentArtifacts,
        );

        return [
            'result' => $result,
            'payload' => $payload,
            'label_artifacts' => $labelArtifacts,
            'document_artifacts' => $documentArtifacts,
            'parsed' => $parsed,
            'response_validation' => $responseValidation,
            'evidence_ready' => $evidenceReady,
            'evidence_reasons' => $evidenceReasons,
        ];
    }

    /**
     * @param  array{valid: bool, reasons: list<string>}  $responseValidation
     * @param  list<FedExValidationArtifact>  $labelArtifacts
     * @param  list<FedExValidationArtifact>  $documentArtifacts
     * @return array{0: bool, 1: list<string>}
     */
    private function evaluateEvidenceReady(
        CarrierApiResult $result,
        array $responseValidation,
        array $labelArtifacts,
        array $documentArtifacts,
    ): array {
        $reasons = [];

        if (! $result->success) {
            $reasons[] = 'api_not_successful';
        }

        if (! ($responseValidation['valid'] ?? false)) {
            $reasons = array_merge($reasons, array_values($responseValidation['reasons'] ?? ['response_validation_failed']));
        }

        $validLabels = array_values(array_filter(
            $labelArtifacts,
            static function (FedExValidationArtifact $artifact): bool {
                if ($artifact->artifact_role !== FedExValidationArtifact::ROLE_GENERATED_LABEL) {
                    return false;
                }
                if (strtoupper((string) $artifact->label_format) !== 'ZPLII') {
                    return false;
                }
                if ((int) $artifact->package_sequence !== 1) {
                    return false;
                }
                $path = $artifact->absolutePath();

                return $path !== null && FedExLabelArtifactValidator::isValid($path, 'ZPLII');
            },
        ));

        if (count($validLabels) !== 1) {
            $reasons[] = 'us08_generated_label_not_ready';
        }

        $bol = collect($documentArtifacts)->first(
            static fn (FedExValidationArtifact $artifact): bool => $artifact->artifact_type === 'freight_bill_of_lading'
                && $artifact->artifact_role === FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT
        );

        if ($bol === null) {
            $reasons[] = 'us08_bol_artifact_missing';
        } else {
            $path = $bol->absolutePath();
            if ($path === null || ! is_file($path) || filesize($path) <= 0) {
                $reasons[] = 'us08_bol_artifact_empty_or_corrupt';
            } else {
                $contents = (string) file_get_contents($path);
                if (! str_starts_with($contents, '%PDF')) {
                    $reasons[] = 'us08_bol_artifact_empty_or_corrupt';
                }
            }
        }

        $reasons = array_values(array_unique($reasons));

        return [$reasons === [], $reasons];
    }

    /**
     * Local readiness gate — must run before any FedEx network I/O.
     *
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $payload
     */
    private function assertLocalPreflight(
        CarrierAccount $account,
        array $fixture,
        string $endpoint,
        string $labelFormat,
        array $payload,
    ): void {
        abort_unless(
            $endpoint === '/ship/v1/freight/shipments',
            422,
            'IntegratorUS08 must call /ship/v1/freight/shipments. Refusing to send a non-Freight endpoint.',
        );

        abort_unless(
            $labelFormat === 'ZPLII',
            422,
            'IntegratorUS08 requires ZPLII label format.',
        );

        $freightAccount = trim((string) (
            $fixture['freight_account_number']
            ?? $fixture['account_number']
            ?? config('carriers.fedex.validation_us08_freight_account', '')
        ));
        abort_unless(
            $freightAccount !== '',
            422,
            'FEDEX_VALIDATION_US08_FREIGHT_ACCOUNT is required before running IntegratorUS08. Configure the dedicated Freight LTL account from the workbook.',
        );

        $connectedLast4 = is_string($account->provider_account_number) && strlen((string) $account->provider_account_number) >= 4
            ? substr((string) $account->provider_account_number, -4)
            : null;
        $freightLast4 = strlen($freightAccount) >= 4 ? substr($freightAccount, -4) : null;

        // Freight account fields must all be the dedicated US08 freight account (not the parcel shipper account).
        $accountFields = [
            data_get($payload, 'accountNumber.value'),
            data_get($payload, 'freightRequestedShipment.freightShipmentDetail.fedExFreightAccountNumber.value'),
            data_get($payload, 'freightRequestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber.value'),
        ];
        foreach ($accountFields as $value) {
            abort_unless(
                is_string($value) && $value === $freightAccount,
                422,
                'IntegratorUS08 Freight account mismatch: every required Freight account field must use FEDEX_VALIDATION_US08_FREIGHT_ACCOUNT'
                .($freightLast4 ? ' (…'.$freightLast4.')' : '')
                .($connectedLast4 && $connectedLast4 !== $freightLast4
                    ? '. Connected Integrator parcel account (…'.$connectedLast4.') is OAuth-only and must not replace the Freight account.'
                    : '.'),
            );
        }

        abort_unless(
            ! array_key_exists('requestedShipment', $payload),
            422,
            'IntegratorUS08 must not reuse parcel requestedShipment fields.',
        );
        abort_unless(
            is_array(data_get($payload, 'freightRequestedShipment')),
            422,
            'IntegratorUS08 requires the freightRequestedShipment envelope.',
        );
        abort_unless(
            strtoupper((string) data_get($payload, 'freightRequestedShipment.serviceType')) === 'FEDEX_FREIGHT_PRIORITY',
            422,
            'IntegratorUS08 service type must be FEDEX_FREIGHT_PRIORITY.',
        );
        abort_unless(
            strtoupper((string) data_get($payload, 'freightRequestedShipment.labelSpecification.imageType')) === 'ZPLII',
            422,
            'IntegratorUS08 labelSpecification.imageType must be ZPLII.',
        );

        $docTypes = array_map(
            static fn (mixed $type): string => strtoupper((string) $type),
            (array) data_get($payload, 'freightRequestedShipment.shippingDocumentSpecification.shippingDocumentTypes', []),
        );
        abort_unless(
            in_array('FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING', $docTypes, true),
            422,
            'IntegratorUS08 must request FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING.',
        );

        $billing = data_get($payload, 'freightRequestedShipment.freightShipmentDetail.fedExFreightBillingContactAndAddress.address');
        abort_unless(is_array($billing), 422, 'IntegratorUS08 Freight billing/mailing address is missing.');
        abort_unless(
            (string) ($billing['city'] ?? '') === 'Harrison'
            && (string) ($billing['stateOrProvinceCode'] ?? '') === 'AR'
            && (string) ($billing['postalCode'] ?? '') === '72601'
            && strtoupper((string) ($billing['countryCode'] ?? '')) === 'US'
            && in_array('1202 Chalet Lane', array_map('strval', (array) ($billing['streetLines'] ?? [])), true),
            422,
            'IntegratorUS08 Freight billing/mailing address must match the workbook (1202 Chalet Lane, Harrison, AR 72601, US).',
        );

        abort_unless(
            (string) data_get($payload, 'freightRequestedShipment.freightShipmentDetail.role') === 'SHIPPER',
            422,
            'IntegratorUS08 freightShipmentDetail.role must be SHIPPER.',
        );
        abort_unless(
            strtoupper((string) data_get($payload, 'freightRequestedShipment.shippingChargesPayment.paymentType')) === 'RECIPIENT',
            422,
            'IntegratorUS08 transportation payment type must be RECIPIENT.',
        );
        abort_unless(
            (string) data_get($payload, 'freightRequestedShipment.shipper.contact.personName') === 'QCONFIG',
            422,
            'IntegratorUS08 shipper.personName must be QCONFIG per workbook.',
        );
        abort_unless(
            (string) data_get($payload, 'freightRequestedShipment.recipient.contact.personName') === 'F-413404',
            422,
            'IntegratorUS08 recipient.personName must be F-413404 per workbook.',
        );
        abort_unless(
            (string) data_get($payload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.id') === '10',
            422,
            'IntegratorUS08 freight line item id must be 10.',
        );
        abort_unless(
            (int) data_get($payload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.handlingUnits') === 1
            && (int) data_get($payload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.pieces') === 10,
            422,
            'IntegratorUS08 line item must use handlingUnits=1 and pieces=10.',
        );
        abort_unless(
            (string) data_get($payload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.purchaseOrderNumber') === '54321',
            422,
            'IntegratorUS08 purchaseOrderNumber must be 54321.',
        );
        abort_unless(
            ! array_key_exists('nmfcCode', (array) data_get($payload, 'freightRequestedShipment.freightShipmentDetail.lineItem.0', [])),
            422,
            'IntegratorUS08 must omit nmfcCode when the workbook leaves it blank (do not map purchaseOrderNumber to nmfcCode).',
        );
        abort_unless(
            (string) data_get($payload, 'freightRequestedShipment.requestedPackageLineItems.0.associatedFreightLineItems.0.id') === '10',
            422,
            'IntegratorUS08 associated freight line item id must be 10.',
        );
        abort_unless(
            data_get($payload, 'freightRequestedShipment.shippingDocumentSpecification.commercialInvoiceDetail.provideInstructions') === true,
            422,
            'IntegratorUS08 Commercial Invoice must include provideInstructions=true.',
        );
        abort_unless(
            data_get($payload, 'freightRequestedShipment.shippingDocumentSpecification.freightBillOfLadingDetail.format.provideInstructions') === true
            && strtoupper((string) data_get($payload, 'freightRequestedShipment.shippingDocumentSpecification.freightBillOfLadingDetail.format.dispositions.0.dispositionType')) === 'RETURNED',
            422,
            'IntegratorUS08 Bill of Lading must include provideInstructions=true and dispositionType=RETURNED.',
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array{valid: bool, reasons: list<string>}  $responseValidation
     * @return array<string, mixed>
     */
    private function safeResponseSummary(array $parsed, array $responseValidation): array
    {
        return [
            'api_family' => 'freight_ltl',
            'response_service_type' => $parsed['service_type'] ?? null,
            'generated_label_count' => count($parsed['labels'] ?? []),
            'generated_document_count' => count($parsed['documents'] ?? []),
            'bol_present' => (bool) ($parsed['bol_present'] ?? false),
            'commercial_invoice_present' => (bool) ($parsed['commercial_invoice_present'] ?? false),
            'tracking_number_count' => (int) ($parsed['tracking_number_count'] ?? 0),
            'master_tracking_number_last4' => $parsed['master_tracking_number_last4'] ?? null,
            'response_validation_passed' => $responseValidation['valid'],
            'response_validation_reasons' => $responseValidation['reasons'],
            'response_paths' => $parsed['response_paths'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function resolveEvent(
        Store $store,
        CarrierAccount $account,
        CarrierApiResult $result,
        array $fixture,
        string $testCaseKey,
        string $labelFormat,
    ): ?CarrierApiEvent {
        $eventId = data_get($result->responseSummary, 'carrier_api_event_id');

        if (is_numeric($eventId)) {
            return CarrierApiEvent::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->whereKey((int) $eventId)
                ->first();
        }

        return CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->where('test_case_key', $testCaseKey)
            ->where('scenario_key', (string) ($fixture['scenario_key'] ?? ''))
            ->where('label_format', strtoupper($labelFormat))
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $fixture
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
        ?User $actor,
        ?CarrierApiEvent $event,
    ): array {
        $artifacts = [];
        $transactionId = data_get($result->responseSummary, 'fedex_transaction_id');

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
            $filename = Str::slug($testCaseKey).'-pkg'.$packageSequence.'-zplii-'.now()->format('YmdHis').'.zpl';
            $relativePath = $relativeDir.'/'.$filename;
            $absolutePath = storage_path('app/'.$relativePath);
            File::ensureDirectoryExists(dirname($absolutePath));
            File::put($absolutePath, $binary);

            if (! FedExLabelArtifactValidator::isValid($absolutePath, $labelFormat)) {
                File::delete($absolutePath);

                continue;
            }

            $artifacts[] = FedExValidationArtifact::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'registration_session_id' => $account->registration_session_id,
                'carrier_api_event_id' => $event?->id,
                'environment' => $account->environment,
                'artifact_type' => 'ship_label_zplii',
                'scenario_key' => (string) ($fixture['scenario_key'] ?? 'ship_us08_zplii'),
                'test_case_key' => $testCaseKey,
                'label_format' => 'ZPLII',
                'package_sequence' => (int) $packageSequence,
                'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
                'label' => $testCaseKey.' · handling unit '.$packageSequence.' · ZPLII',
                'original_filename' => $filename,
                'mime_type' => 'application/zpl',
                'file_size' => strlen($binary),
                'sha256' => hash('sha256', $binary),
                'file_path' => $relativePath,
                'request_summary_json' => [
                    'api_family' => 'freight_ltl',
                    'test_case' => $testCaseKey,
                ],
                'response_summary_json' => array_filter([
                    'http_status' => data_get($result->responseSummary, 'http_status'),
                    'fedex_transaction_id' => $transactionId,
                    'tracking_number_last4' => $document['tracking_number_last4'] ?? null,
                    'master_tracking_number_last4' => $parsed['master_tracking_number_last4'] ?? null,
                    'label_saved' => true,
                    'label_format' => 'ZPLII',
                    'package_sequence' => (int) $packageSequence,
                    'service_type' => $parsed['service_type'] ?? null,
                    'response_path' => $document['response_path'] ?? null,
                ]),
                'metadata_json' => [
                    'document_type' => $document['document_type'] ?? 'LABEL',
                    'image_type' => $document['image_type'] ?? 'ZPLII',
                    'api_family' => 'freight_ltl',
                ],
                'fedex_transaction_id' => is_string($transactionId) ? $transactionId : null,
                'created_by' => $actor?->id,
            ]);
        }

        return $artifacts;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $parsed
     * @return list<FedExValidationArtifact>
     */
    private function persistDocumentArtifacts(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        array $fixture,
        CarrierApiResult $result,
        array $parsed,
        ?User $actor,
        ?CarrierApiEvent $event,
    ): array {
        $artifacts = [];
        $transactionId = data_get($result->responseSummary, 'fedex_transaction_id');

        foreach ($parsed['documents'] as $index => $document) {
            if (! ($document['is_bol'] ?? false) && ! ($document['is_commercial_invoice'] ?? false)) {
                continue;
            }

            $encoded = $document['encoded_label'] ?? null;
            if (! is_string($encoded) || $encoded === '') {
                continue;
            }

            $binary = base64_decode($encoded, true);
            if ($binary === false || $binary === '' || ! str_starts_with($binary, '%PDF')) {
                continue;
            }

            $type = ($document['is_bol'] ?? false) ? 'freight_bill_of_lading' : 'freight_commercial_invoice';
            $relativeDir = "fedex-validation/{$store->id}/documents";
            $filename = Str::slug($testCaseKey).'-'.$type.'-'.($index + 1).'-'.now()->format('YmdHis').'.pdf';
            $relativePath = $relativeDir.'/'.$filename;
            $absolutePath = storage_path('app/'.$relativePath);
            File::ensureDirectoryExists(dirname($absolutePath));
            File::put($absolutePath, $binary);

            $artifacts[] = FedExValidationArtifact::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'registration_session_id' => $account->registration_session_id,
                'carrier_api_event_id' => $event?->id,
                'environment' => $account->environment,
                'artifact_type' => $type,
                'scenario_key' => (string) ($fixture['scenario_key'] ?? 'ship_us08_zplii'),
                'test_case_key' => $testCaseKey,
                'label_format' => 'PDF',
                'package_sequence' => null,
                'artifact_role' => FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT,
                'label' => $testCaseKey.' · '.(($document['is_bol'] ?? false) ? 'Straight Bill of Lading' : 'Commercial Invoice'),
                'original_filename' => $filename,
                'mime_type' => 'application/pdf',
                'file_size' => strlen($binary),
                'sha256' => hash('sha256', $binary),
                'file_path' => $relativePath,
                'request_summary_json' => [
                    'api_family' => 'freight_ltl',
                    'test_case' => $testCaseKey,
                ],
                'response_summary_json' => array_filter([
                    'http_status' => data_get($result->responseSummary, 'http_status'),
                    'fedex_transaction_id' => $transactionId,
                    'document_saved' => true,
                    'content_type' => $document['content_type'] ?? null,
                    'response_path' => $document['response_path'] ?? null,
                ]),
                'metadata_json' => [
                    'document_type' => $document['document_type'] ?? null,
                    'content_type' => $document['content_type'] ?? null,
                    'image_type' => $document['image_type'] ?? 'PDF',
                    'api_family' => 'freight_ltl',
                    'is_bol' => (bool) ($document['is_bol'] ?? false),
                    'is_commercial_invoice' => (bool) ($document['is_commercial_invoice'] ?? false),
                ],
                'fedex_transaction_id' => is_string($transactionId) ? $transactionId : null,
                'created_by' => $actor?->id,
            ]);
        }

        return $artifacts;
    }
}
