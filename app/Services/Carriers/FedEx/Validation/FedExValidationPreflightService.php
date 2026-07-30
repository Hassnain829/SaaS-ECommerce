<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExLabelArtifactValidator;

class FedExValidationPreflightService
{
    public const SCHEMA_VERSION = '1.0';

    public function __construct(
        private readonly FedExValidationScopeService $scopeService,
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
        private readonly FedExValidationEvidenceSanitizer $sanitizer,
        private readonly FedExValidationAuthorizationEvidenceRules $authorizationEvidenceRules,
        private readonly FedExHostedEulaEvidenceService $hostedEulaEvidence,
        private readonly FedExComprehensiveRateEvidenceService $comprehensiveRateEvidence,
        private readonly FedExShipEvidenceRules $shipEvidenceRules,
        private readonly FedExConsolidationEvidenceRules $consolidationEvidenceRules,
        private readonly Preflight\GlobalShipCheckProvider $globalShipCheckProvider,
        private readonly FedExBrandComplianceService $brandCompliance,
        private readonly FedExCapabilityEvidenceService $capabilityEvidence,
    ) {}

    /**
     * @param  list<string>|null  $scopes
     * @return array<string, mixed>
     */
    public function assess(Store $store, CarrierAccount $account, ?array $scopes = null, bool $includePackageEight = false): array
    {
        $scopes = $this->scopeService->resolveRequiredScopes($scopes);
        $checks = [];
        $blockers = [];
        $warnings = [];

        foreach ($this->requiredDocumentChecks($store, $account) as $check) {
            $checks[] = $check;
            if ($this->isBlockingCheck($check)) {
                $blockers[] = $check;
            }
        }

        foreach ($this->authorizationChecks($store, $account) as $check) {
            $checks[] = $check;
            if ($this->isBlockingCheck($check)) {
                $blockers[] = $check;
            }
        }

        foreach ($this->registrationChecks($store, $account) as $check) {
            $checks[] = $check;
            if ($this->isBlockingCheck($check)) {
                $blockers[] = $check;
            }
        }

        foreach ($this->swedenPassthroughChecks($store, $account) as $check) {
            $checks[] = $check;
            if ($this->isBlockingCheck($check)) {
                $blockers[] = $check;
            }
        }

        foreach ($this->hostedEulaChecks($account) as $check) {
            $checks[] = $check;
            if ($this->isBlockingCheck($check)) {
                $blockers[] = $check;
            }
        }

        if (in_array(FedExValidationScopeService::SCOPE_ADDRESS_VALIDATION, $scopes, true)) {
            $canonicalAddress = $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'address_validation');
            $addressCheck = $this->apiCheck(
                'address_validation',
                'Address validation',
                $canonicalAddress ?? $this->evidenceQuery->latestCompleteEvent($store, $account, 'address_validation'),
            );
            $checks[] = $addressCheck;
            if ($this->isBlockingCheck($addressCheck)) {
                $blockers[] = $addressCheck;
            }
        }

        if (in_array(FedExValidationScopeService::SCOPE_SERVICE_AVAILABILITY, $scopes, true)) {
            $canonicalService = $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'service_availability');
            $serviceCheck = $this->apiCheck(
                'service_availability',
                'Service availability',
                $canonicalService ?? $this->evidenceQuery->latestCompleteEvent($store, $account, 'service_availability'),
            );
            $checks[] = $serviceCheck;
            if ($this->isBlockingCheck($serviceCheck)) {
                $blockers[] = $serviceCheck;
            }
        }

        if (in_array(FedExValidationScopeService::SCOPE_COMPREHENSIVE_RATES, $scopes, true)) {
            $canonicalRate = $this->comprehensiveRateEvidence->canonicalEvent($store, $account);
            $blockerRate = $canonicalRate === null
                ? $this->comprehensiveRateEvidence->latestAccessBlocker($store, $account)
                : null;

            foreach ([
                $this->comprehensiveRateEvidence->transactionCheck($canonicalRate, $blockerRate),
                $this->comprehensiveRateEvidence->uiMatchCheck($canonicalRate),
                $this->comprehensiveRateEvidence->screenshotCheck($store, $account, $canonicalRate),
            ] as $rateCheck) {
                $checks[] = $rateCheck;
                if ($this->isBlockingCheck($rateCheck)) {
                    $blockers[] = $rateCheck;
                }
            }
        }

        if (in_array(FedExValidationScopeService::SCOPE_SHIP, $scopes, true)) {
            foreach (FedExValidationScenarioCatalog::requiredLockedShipScenarios() as $testCaseKey => $meta) {
                foreach ($this->shipScenarioChecks($store, $account, $testCaseKey, $meta) as $check) {
                    $checks[] = $check;
                    if ($this->isBlockingCheck($check)) {
                        $blockers[] = $check;
                    }
                }
            }

            if (! FedExValidationScenarioCatalog::isShipScenarioEnabled('IntegratorUS08')) {
                $checks[] = [
                    'key' => 'ship_us08_zplii_excluded',
                    'category' => 'ship',
                    'label' => 'IntegratorUS08 Freight LTL (excluded)',
                    'required' => false,
                    'status' => 'passed',
                    'explanation' => FedExValidationScenarioCatalog::us08ExclusionNote()
                        .' Historical Freight events remain stored for audit and do not block final readiness.',
                    'event_id' => null,
                ];
            }

            if (! FedExValidationScenarioCatalog::isConsolidationEnabled()) {
                $checks[] = [
                    'key' => 'consolidation_us10_excluded',
                    'category' => 'ship',
                    'label' => 'IntegratorUS10 Consolidation / IPD (excluded)',
                    'required' => false,
                    'status' => 'passed',
                    'explanation' => FedExValidationScenarioCatalog::us10ExclusionNote()
                        .' Historical Consolidation events remain stored for audit and do not block final readiness.',
                    'event_id' => null,
                ];
            } else {
                foreach ($this->consolidationScenarioChecks($store, $account) as $check) {
                    $checks[] = $check;
                    if ($this->isBlockingCheck($check)) {
                        $blockers[] = $check;
                    }
                }
            }
        }

        foreach ($this->globalShipCheckProvider->checks($store, $account) as $check) {
            $checks[] = $check;
            if ($this->isBlockingCheck($check)) {
                $blockers[] = $check;
            }
        }

        if ($includePackageEight) {
            foreach ($this->brandCompliance->preflightChecks() as $check) {
                $checks[] = $check;
                if ($this->isBlockingCheck($check)) {
                    $blockers[] = $check;
                }
            }

            foreach ($this->capabilityEvidence->preflightChecks($store, $account) as $check) {
                $checks[] = $check;
                if ($this->isBlockingCheck($check)) {
                    $blockers[] = $check;
                }
            }
        }

        if ($this->scopeService->trackingRequired($scopes)) {
            $trackingEvent = $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'basic_integrated_visibility');
            $trackingCheck = $this->apiCheck(
                'tracking',
                'Basic Integrated Visibility / Tracking',
                $trackingEvent,
            );
            $checks[] = $trackingCheck;
            if ($this->isBlockingCheck($trackingCheck)) {
                $blockers[] = $trackingCheck;
            }

            $screenshotCheck = $this->trackingScreenshotCheck($store, $account, $trackingEvent);
            $checks[] = $screenshotCheck;
            if ($this->isBlockingCheck($screenshotCheck)) {
                $blockers[] = $screenshotCheck;
            }
        }

        if ($this->scopeService->shipCancelRequired($scopes)) {
            $cancelCheck = $this->apiCheck(
                'ship_cancel',
                'Shipment cancellation',
                $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'ship_cancel'),
            );
            $checks[] = $cancelCheck;
            if ($this->isBlockingCheck($cancelCheck)) {
                $blockers[] = $cancelCheck;
            }
        }

        if ($this->scopeService->tradeDocumentsRequired($scopes)) {
            $tradeCheck = $this->apiCheck(
                'trade_documents',
                'Trade Documents upload',
                $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'trade_documents_upload'),
            );
            $checks[] = $tradeCheck;
            if ($this->isBlockingCheck($tradeCheck)) {
                $blockers[] = $tradeCheck;
            }
        }

        $requiredChecks = collect($checks)->where('required', true);
        $completed = $requiredChecks->whereIn('status', ['passed', 'not_required'])->count();
        $total = $requiredChecks->count();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'ready' => $blockers === [],
            'completed_count' => $completed,
            'total_count' => $total,
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'checks' => $checks,
            'preflight_hash' => hash('sha256', json_encode($checks)),
            'canonical_event_ids' => collect($checks)
                ->filter(fn (array $check): bool => filled($check['event_id'] ?? null))
                ->mapWithKeys(fn (array $check): array => [(string) $check['key'] => (int) $check['event_id']])
                ->all(),
            'blockers' => array_values(array_map(fn (array $check): array => [
                'key' => $check['key'],
                'label' => $check['label'],
                'status' => $check['status'],
                'explanation' => $check['explanation'],
            ], $blockers)),
            'warnings' => $warnings,
            'generated_at' => now()->toIso8601String(),
            'selected_scopes' => $scopes,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requiredDocumentChecks(Store $store, CarrierAccount $account): array
    {
        $documents = [
            FedExValidationArtifact::DOC_COVER_SHEET => 'Integrator Validation Cover Sheet',
            FedExValidationArtifact::DOC_PIW => 'Product Information Worksheet',
            FedExValidationArtifact::DOC_CUSTOMER_SCREENSHOTS => 'Customer-facing screenshots PDF',
        ];

        $checks = [];

        foreach ($documents as $type => $label) {
            $artifact = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('artifact_type', $type)
                ->latest('id')
                ->first();

            $checks[] = [
                'key' => 'document_'.$type,
                'category' => 'documents',
                'label' => $label,
                'required' => true,
                'status' => $this->documentStatus($artifact),
                'explanation' => $artifact ? 'Document uploaded.' : 'Upload the required PDF in the validation workspace.',
                'artifact_id' => $artifact?->id,
            ];
        }

        return $checks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function authorizationChecks(Store $store, CarrierAccount $account): array
    {
        $checks = [];

        foreach (FedExValidationScenarioCatalog::authorizationScenarios() as $scenarioKey => $meta) {
            $event = $this->evidenceQuery->canonicalAuthorizationEvent(
                $store,
                $account,
                $scenarioKey,
                (string) $meta['action'],
                (string) $meta['grant_type'],
            );

            if ($event === null) {
                $latest = $this->evidenceQuery->latestCompleteEvent($store, $account, $scenarioKey);
                if ($latest !== null && $latest->action === $meta['action']) {
                    $event = $latest;
                }
            }

            $checks[] = [
                'key' => $scenarioKey,
                'category' => 'authorization',
                'label' => (string) $meta['label'],
                'required' => true,
                'status' => $this->authorizationEvidenceStatus($event, (string) $meta['grant_type']),
                'explanation' => $event
                    ? 'Latest authorization evidence recorded for this scenario.'
                    : 'Run Parent + Child Authorization in the validation workspace.',
                'event_id' => $event?->id,
            ];
        }

        return $checks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrationChecks(Store $store, CarrierAccount $account): array
    {
        $checks = [];

        foreach (FedExValidationScenarioCatalog::registrationScenarios() as $scenarioKey => $meta) {
            if ($scenarioKey === 'registration_address_validation') {
                $canonical = $this->evidenceQuery->canonicalRegistrationAddressEvent($store, $account);
                $latest = $this->evidenceQuery->latestRegistrationAddressAttempt($store, $account);

                $checks[] = [
                    'key' => $scenarioKey,
                    'category' => 'registration_mfa',
                    'label' => $meta['label'],
                    'required' => true,
                    'status' => $this->registrationAddressEvidenceStatus($canonical, $latest),
                    'explanation' => $canonical
                        ? 'FedEx accepted the registration address and returned MFA options or child credentials.'
                        : ($latest
                            ? 'Latest registration address attempt did not qualify as canonical evidence. Use Run Registration Address Validation.'
                            : 'Run Registration Address Validation from the validation workspace.'),
                    'event_id' => ($canonical ?? $latest)?->id,
                ];

                continue;
            }

            $event = $this->evidenceQuery->canonicalSuccessfulEvent(
                $store,
                $account,
                $scenarioKey,
                mfaMethod: $meta['mfa_method'],
            );

            $checks[] = [
                'key' => $scenarioKey,
                'category' => 'registration_mfa',
                'label' => $meta['label'],
                'required' => true,
                'status' => $this->eventEvidenceStatus($event, requireSuccess: true),
                'explanation' => $event
                    ? 'Latest event recorded for this scenario.'
                    : 'Run the registration/MFA step and record evidence before final export.',
                'event_id' => $event?->id,
            ];
        }

        return $checks;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private function shipScenarioChecks(Store $store, CarrierAccount $account, string $testCaseKey, array $meta): array
    {
        $scenarioKey = (string) $meta['scenario_key'];
        $labelFormat = (string) $meta['label_format'];
        $expectedPackages = (int) $meta['expected_packages'];
        $checks = [];

        if (filled($meta['upload_scenario_key'] ?? null) || filled($meta['upload_scenario_keys'] ?? null)) {
            $uploadKeys = array_values(array_unique(array_filter(array_merge(
                (array) ($meta['upload_scenario_keys'] ?? []),
                filled($meta['upload_scenario_key'] ?? null) ? [(string) $meta['upload_scenario_key']] : [],
            ))));

            foreach ($uploadKeys as $uploadScenarioKey) {
                $uploadEvent = $uploadScenarioKey === 'upload_us09_document'
                    ? $this->evidenceQuery->canonicalUs09DocumentUploadEvent($store, $account)
                    : $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, $uploadScenarioKey);
                $label = match ($uploadScenarioKey) {
                    'upload_us09_image_letterhead' => $testCaseKey.' letterhead image upload',
                    'upload_us09_image_signature' => $testCaseKey.' signature image upload',
                    'upload_us09_document' => $testCaseKey.' commercial invoice upload',
                    default => $testCaseKey.' trade-document upload',
                };
                $checks[] = [
                    'key' => $uploadScenarioKey.'_event',
                    'category' => 'ship',
                    'label' => $label,
                    'required' => true,
                    'status' => $uploadEvent ? 'passed' : 'not_tested',
                    'explanation' => $uploadEvent
                        ? 'Canonical successful Trade Documents upload event recorded ('.$uploadScenarioKey.').'
                        : 'Complete the locked '.$uploadScenarioKey.' upload before creating the ETD label. Each upload is independent; one image upload cannot satisfy both letterhead and signature.',
                    'event_id' => $uploadEvent?->id,
                ];
            }
        }

        $event = $this->evidenceQuery->canonicalShipLabelEvent(
            $store,
            $account,
            $scenarioKey,
            testCaseKey: $testCaseKey,
            labelFormat: $labelFormat,
        );

        $latestAttempt = $event === null
            ? $this->evidenceQuery->latestShipLabelAttempt(
                $store,
                $account,
                $scenarioKey,
                testCaseKey: $testCaseKey,
                labelFormat: $labelFormat,
            )
            : null;

        $artifactSourceEvent = $event ?? (
            $latestAttempt !== null
            && $latestAttempt->hasCompleteEvidence()
            && $latestAttempt->isSuccessfulHttp()
                ? $latestAttempt
                : null
        );

        $eventStatus = $event !== null
            ? $this->shipEventEvidenceStatus($event, $testCaseKey)
            : ($latestAttempt !== null ? 'incomplete' : 'not_tested');

        $checks[] = [
            'key' => $scenarioKey.'_event',
            'category' => 'ship',
            'label' => $testCaseKey.' ship label API',
            'required' => true,
            'status' => $eventStatus,
            'explanation' => $event
                ? 'Canonical successful ship label event recorded.'
                : ($latestAttempt !== null
                    ? 'A '.$testCaseKey.' ship attempt exists but is not yet complete canonical evidence (label, documents, or response checks still need attention).'
                    : 'Run the locked '.$testCaseKey.' label button in the validation workspace. Ship validate alone does not create labels.'),
            'event_id' => $event?->id ?? $latestAttempt?->id,
        ];

        if ($event !== null) {
            $sanitizedRequest = $this->sanitizer->sanitize(
                is_array($event->request_body_encrypted) ? $event->request_body_encrypted : []
            );
            $exportValidation = $this->shipEvidenceRules->validateSanitizedExport(
                is_array($sanitizedRequest) ? $sanitizedRequest : [],
                $testCaseKey,
            );

            $checks[] = [
                'key' => $scenarioKey.'_export_payload',
                'category' => 'ship',
                'label' => $testCaseKey.' exported payment and special-service fields',
                'required' => true,
                'status' => $exportValidation['valid'] ? 'passed' : 'failed',
                'explanation' => $exportValidation['valid']
                    ? 'Sanitized export preserves paymentType and required special services while redacting account numbers.'
                    : 'Export payload failed validation: '.implode(', ', $exportValidation['reasons']).'. Re-run the locked ship case and regenerate the evidence bundle.',
                'event_id' => $event->id,
            ];
        } elseif ($latestAttempt !== null) {
            $sanitizedRequest = $this->sanitizer->sanitize(
                is_array($latestAttempt->request_body_encrypted) ? $latestAttempt->request_body_encrypted : []
            );
            $exportValidation = $this->shipEvidenceRules->validateSanitizedExport(
                is_array($sanitizedRequest) ? $sanitizedRequest : [],
                $testCaseKey,
            );

            $checks[] = [
                'key' => $scenarioKey.'_export_payload',
                'category' => 'ship',
                'label' => $testCaseKey.' exported payment and special-service fields',
                'required' => true,
                'status' => $exportValidation['valid'] ? 'passed' : 'failed',
                'explanation' => $exportValidation['valid']
                    ? 'Sanitized export preserves paymentType and required special services while redacting account numbers.'
                    : 'Export payload failed validation: '.implode(', ', $exportValidation['reasons']).'. Re-run the locked ship case and regenerate the evidence bundle.',
                'event_id' => $latestAttempt->id,
            ];
        }

        if ($event !== null && $this->hasDuplicateArtifactsForEvent($store, $account, $event, FedExValidationArtifact::ROLE_GENERATED_LABEL)) {
            $checks[] = [
                'key' => $scenarioKey.'_label_duplicates',
                'category' => 'ship',
                'label' => $testCaseKey.' duplicate generated labels',
                'required' => true,
                'status' => 'failed',
                'explanation' => 'Duplicate generated label artifacts exist for the same package sequence.',
                'event_id' => $event->id,
            ];
        }

        if ($event !== null && $this->hasDuplicateArtifactsForEvent($store, $account, $event, FedExValidationArtifact::ROLE_PRINTED_SCAN)) {
            $checks[] = [
                'key' => $scenarioKey.'_scan_duplicates',
                'category' => 'ship',
                'label' => $testCaseKey.' duplicate printed scans',
                'required' => true,
                'status' => 'failed',
                'explanation' => 'Duplicate printed scan artifacts exist for the same package sequence.',
                'event_id' => $event->id,
            ];
        }

        for ($sequence = 1; $sequence <= $expectedPackages; $sequence++) {
            $labelArtifact = $this->findArtifact(
                $store,
                $account,
                $artifactSourceEvent,
                $scenarioKey,
                $testCaseKey,
                $labelFormat,
                FedExValidationArtifact::ROLE_GENERATED_LABEL,
                $sequence,
            );

            $checks[] = [
                'key' => $scenarioKey.'_label_'.$sequence,
                'category' => 'ship',
                'label' => $testCaseKey.' generated label package '.$sequence,
                'required' => true,
                'status' => $this->labelArtifactStatus($labelArtifact, $labelFormat),
                'explanation' => $labelArtifact
                    ? 'Generated label artifact saved.'
                    : 'Generate and save the package '.$sequence.' label for '.$testCaseKey.'.',
                'artifact_id' => $labelArtifact?->id,
            ];

            $scanArtifact = $this->findArtifact(
                $store,
                $account,
                $artifactSourceEvent,
                $scenarioKey,
                $testCaseKey,
                $labelFormat,
                FedExValidationArtifact::ROLE_PRINTED_SCAN,
                $sequence,
            );

            $checks[] = [
                'key' => $scenarioKey.'_scan_'.$sequence,
                'category' => 'ship',
                'label' => $testCaseKey.' printed scan package '.$sequence,
                'required' => true,
                'status' => $this->scanArtifactStatus($scanArtifact),
                'explanation' => $this->scanArtifactExplanation($scanArtifact, $sequence),
                'artifact_id' => $scanArtifact?->id,
            ];
        }

        if ($testCaseKey === 'IntegratorUS08') {
            $bolArtifact = null;
            if ($artifactSourceEvent !== null) {
                $bolArtifact = FedExValidationArtifact::query()
                    ->where('store_id', $store->id)
                    ->where('carrier_account_id', $account->id)
                    ->where('carrier_api_event_id', $artifactSourceEvent->id)
                    ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
                    ->where('artifact_type', 'freight_bill_of_lading')
                    ->latest('id')
                    ->first();
            }

            $bolPath = $bolArtifact?->absolutePath();
            $bolValid = $bolPath !== null
                && is_file($bolPath)
                && filesize($bolPath) > 0
                && str_starts_with((string) file_get_contents($bolPath), '%PDF');

            $checks[] = [
                'key' => $scenarioKey.'_bol',
                'category' => 'ship',
                'label' => $testCaseKey.' Straight Bill of Lading',
                'required' => true,
                'status' => $bolValid ? 'passed' : 'incomplete',
                'explanation' => $bolValid
                    ? 'Freight Straight Bill of Lading PDF saved.'
                    : 'Create IntegratorUS08 Freight shipment and retain the Straight Bill of Lading PDF artifact.',
                'artifact_id' => $bolArtifact?->id,
            ];
        }

        return $checks;
    }

    /**
     * IntegratorUS10 — each consolidation step and document requirement is independent.
     *
     * @return list<array<string, mixed>>
     */
    private function consolidationScenarioChecks(Store $store, CarrierAccount $account): array
    {
        $checks = [];
        $accountConfigured = trim((string) config('carriers.fedex.validation_us10_consolidation_account', '')) !== '';

        $checks[] = [
            'key' => 'consolidation_us10_account_configured',
            'category' => 'ship',
            'label' => 'IntegratorUS10 consolidation account configured',
            'required' => true,
            'status' => $accountConfigured ? 'passed' : 'not_tested',
            'explanation' => $accountConfigured
                ? 'FEDEX_VALIDATION_US10_CONSOLIDATION_ACCOUNT is configured (value never printed).'
                : 'Set FEDEX_VALIDATION_US10_CONSOLIDATION_ACCOUNT to the Consolidation/IPD-enabled workbook account. Do not substitute parcel, Ground Economy, or Freight accounts.',
            'event_id' => null,
        ];

        foreach (FedExValidationScenarioCatalog::lockedConsolidationScenarios() as $testCaseKey => $meta) {
            $scenarioKey = (string) $meta['scenario_key'];
            $operation = (string) ($meta['operation'] ?? 'create');
            $event = $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, $scenarioKey, $testCaseKey);

            $checks[] = [
                'key' => $scenarioKey.'_event',
                'category' => 'ship',
                'label' => $testCaseKey.' Consolidation API',
                'required' => true,
                'status' => $event ? 'passed' : 'not_tested',
                'explanation' => $event
                    ? 'Canonical successful Consolidation event recorded for '.$scenarioKey.'.'
                    : 'Run the full US10 Consolidation chain during the final evidence run. One Add Shipment event cannot satisfy another shipment requirement.',
                'event_id' => $event?->id,
            ];

            if ($event !== null) {
                $sanitizedRequest = $this->sanitizer->sanitize(
                    is_array($event->request_body_encrypted) ? $event->request_body_encrypted : []
                );
                $exportValidation = $this->consolidationEvidenceRules->validateSanitizedExport(
                    is_array($sanitizedRequest) ? $sanitizedRequest : [],
                    $operation,
                );

                $checks[] = [
                    'key' => $scenarioKey.'_export_payload',
                    'category' => 'ship',
                    'label' => $testCaseKey.' sanitized Consolidation export',
                    'required' => true,
                    'status' => $exportValidation['valid'] ? 'passed' : 'failed',
                    'explanation' => $exportValidation['valid']
                        ? 'Sanitized export preserves consolidation/payment/commodity structure while redacting accounts, TINs, jobId, and sensitive indexes.'
                        : 'Export payload failed validation: '.implode(', ', $exportValidation['reasons']).'.',
                    'event_id' => $event->id,
                ];
            }
        }

        $confirmResultsEvent = $this->evidenceQuery->canonicalSuccessfulEvent(
            $store,
            $account,
            'consolidation_us10_confirm_results',
            'IntegratorUS10_CONFIRM_RESULTS',
        );

        $expectedLabelCount = count(app(FedExConsolidationFixtureService::class)->addShipmentKeys());
        $labelArtifacts = [];
        $cciArtifact = null;

        if ($confirmResultsEvent !== null) {
            $labelArtifacts = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('carrier_api_event_id', $confirmResultsEvent->id)
                ->where('artifact_role', FedExValidationArtifact::ROLE_GENERATED_LABEL)
                ->where('test_case_key', 'IntegratorUS10_CONFIRM_RESULTS')
                ->orderBy('package_sequence')
                ->get()
                ->all();

            $cciArtifact = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('carrier_api_event_id', $confirmResultsEvent->id)
                ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
                ->where('artifact_type', 'consolidation_commercial_invoice')
                ->latest('id')
                ->first();
        }

        $validLabels = array_values(array_filter(
            $labelArtifacts,
            fn (FedExValidationArtifact $artifact): bool => $this->us10LabelArtifactValid($artifact),
        ));
        $labelsStatus = 'not_tested';
        $labelsExplanation = 'Preserve all child labels returned by Confirm Consolidation Results in 08_ship_us10_ipd/labels/.';
        if ($confirmResultsEvent === null) {
            $labelsStatus = 'not_tested';
        } elseif (count($validLabels) < $expectedLabelCount) {
            $labelsStatus = 'incomplete';
            $labelsExplanation = 'Expected '.$expectedLabelCount.' valid child labels linked to the Confirm Results event; found '.count($validLabels).'.';
        } else {
            $labelsStatus = 'passed';
            $labelsExplanation = 'All '.$expectedLabelCount.' child labels are present and valid.';
        }

        $checks[] = [
            'key' => 'consolidation_us10_child_labels',
            'category' => 'ship',
            'label' => 'IntegratorUS10 child labels',
            'required' => true,
            'status' => $labelsStatus,
            'explanation' => $labelsExplanation,
            'event_id' => $confirmResultsEvent?->id,
            'artifact_count' => count($validLabels),
            'expected_count' => $expectedLabelCount,
        ];

        $cciPath = $cciArtifact?->absolutePath();
        $cciValid = $cciPath !== null
            && is_file($cciPath)
            && filesize($cciPath) > 0
            && filled($cciArtifact->sha256)
            && hash_file('sha256', $cciPath) === (string) $cciArtifact->sha256
            && str_starts_with((string) file_get_contents($cciPath), '%PDF');

        $cciStatus = 'not_tested';
        $cciExplanation = 'Preserve the Consolidated Commercial Invoice from Confirm Results in 08_ship_us10_ipd/documents/.';
        if ($confirmResultsEvent === null) {
            $cciStatus = 'not_tested';
        } elseif (! $cciValid) {
            $cciStatus = 'incomplete';
            $cciExplanation = 'Consolidated Commercial Invoice artifact is missing, empty, corrupt, or not linked to the Confirm Results event.';
        } else {
            $cciStatus = 'passed';
            $cciExplanation = 'Consolidated Commercial Invoice PDF is present and valid.';
        }

        $checks[] = [
            'key' => 'consolidation_us10_cci',
            'category' => 'ship',
            'label' => 'IntegratorUS10 Consolidated Commercial Invoice',
            'required' => true,
            'status' => $cciStatus,
            'explanation' => $cciExplanation,
            'event_id' => $confirmResultsEvent?->id,
            'artifact_id' => $cciArtifact?->id,
        ];

        $tinConfigured = trim((string) config('carriers.fedex.validation_us10_shipper_tin', '')) !== '';
        $checks[] = [
            'key' => 'consolidation_us10_shipper_tin_configured',
            'category' => 'ship',
            'label' => 'IntegratorUS10 shipper TIN configured',
            'required' => true,
            'status' => $tinConfigured ? 'passed' : 'not_tested',
            'explanation' => $tinConfigured
                ? 'FEDEX_VALIDATION_US10_SHIPPER_TIN is configured (value never printed).'
                : 'Set FEDEX_VALIDATION_US10_SHIPPER_TIN. Placeholder TIN values are not allowed.',
            'event_id' => null,
        ];

        return $checks;
    }

    private function us10LabelArtifactValid(FedExValidationArtifact $artifact): bool
    {
        $path = $artifact->absolutePath();
        if ($path === null || ! is_file($path) || filesize($path) <= 0) {
            return false;
        }

        if (! filled($artifact->sha256) || hash_file('sha256', $path) !== (string) $artifact->sha256) {
            return false;
        }

        $format = strtoupper((string) ($artifact->label_format ?: 'PDF'));

        return FedExLabelArtifactValidator::isValid($path, $format);
    }

    private function apiCheck(string $key, string $label, ?CarrierApiEvent $event): array
    {
        return [
            'key' => $key,
            'category' => 'api',
            'label' => $label,
            'required' => true,
            'status' => $this->eventEvidenceStatus($event, requireSuccess: true),
            'explanation' => $event
                ? 'API evidence recorded.'
                : 'Run the '.$label.' tool and record evidence.',
            'event_id' => $event?->id,
        ];
    }

    private function authorizationEvidenceStatus(?CarrierApiEvent $event, string $expectedGrantType): string
    {
        if ($event === null) {
            return 'not_tested';
        }

        if ($this->authorizationEvidenceRules->satisfiesRequirements($event, $expectedGrantType)) {
            return 'passed';
        }

        if ($this->isCachedAuthorizationEvent($event)) {
            return 'incomplete';
        }

        if (! $event->hasCompleteEvidence()) {
            return 'incomplete';
        }

        if ($event->status !== CarrierApiEvent::STATUS_SUCCEEDED || ! $event->isSuccessfulHttp()) {
            return 'failed';
        }

        return 'incomplete';
    }

    private function isCachedAuthorizationEvent(CarrierApiEvent $event): bool
    {
        return (bool) data_get($event->request_summary, 'cached')
            || (bool) data_get($event->response_summary, 'cached');
    }

    private function shipEventEvidenceStatus(?CarrierApiEvent $event, string $testCaseKey): string
    {
        if ($event === null) {
            return 'not_tested';
        }

        if ($this->shipEvidenceRules->isValidEventForTestCase($event, $testCaseKey)) {
            return 'passed';
        }

        if (! $event->hasCompleteEvidence()) {
            return 'incomplete';
        }

        if ((int) $event->http_status === 403) {
            return 'blocked';
        }

        return $event->isSuccessfulHttp() ? 'failed' : 'failed';
    }

    private function eventEvidenceStatus(?CarrierApiEvent $event, bool $requireSuccess = false): string
    {
        if ($event === null) {
            return 'not_tested';
        }

        if (! $event->hasCompleteEvidence()) {
            return 'incomplete';
        }

        if ((int) $event->http_status === 403) {
            return 'blocked';
        }

        if ($requireSuccess && ! $event->isSuccessfulHttp()) {
            return 'failed';
        }

        return $event->isSuccessfulHttp() ? 'passed' : 'failed';
    }

    private function registrationAddressEvidenceStatus(?CarrierApiEvent $canonical, ?CarrierApiEvent $latest): string
    {
        if ($canonical !== null) {
            return 'passed';
        }

        if ($latest === null) {
            return 'not_tested';
        }

        if (! $latest->hasCompleteEvidence()) {
            return 'incomplete';
        }

        if ((int) $latest->http_status === 403) {
            return 'blocked';
        }

        return 'failed';
    }

    private function documentStatus(?FedExValidationArtifact $artifact): string
    {
        if (! $this->artifactIntegrityValid($artifact)) {
            return 'incomplete';
        }

        $path = $artifact->absolutePath();

        return str_starts_with((string) mime_content_type((string) $path), 'application/pdf') ? 'passed' : 'failed';
    }

    private function findArtifact(
        Store $store,
        CarrierAccount $account,
        ?CarrierApiEvent $event,
        string $scenarioKey,
        string $testCaseKey,
        string $labelFormat,
        string $role,
        int $packageSequence,
    ): ?FedExValidationArtifact {
        if ($event === null) {
            return null;
        }

        $artifact = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('carrier_api_event_id', $event->id)
            ->where('scenario_key', $scenarioKey)
            ->where('test_case_key', $testCaseKey)
            ->where('label_format', strtoupper($labelFormat))
            ->where('artifact_role', $role)
            ->where('package_sequence', $packageSequence)
            ->latest('id')
            ->first();

        return $this->artifactIntegrityValid($artifact) ? $artifact : null;
    }

    private function hasDuplicateArtifactsForEvent(
        Store $store,
        CarrierAccount $account,
        CarrierApiEvent $event,
        string $role,
    ): bool {
        $counts = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', $role)
            ->selectRaw('package_sequence, COUNT(*) as total')
            ->groupBy('package_sequence')
            ->pluck('total', 'package_sequence');

        return $counts->contains(fn (int $total): bool => $total > 1);
    }

    private function artifactIntegrityValid(?FedExValidationArtifact $artifact): bool
    {
        if ($artifact === null || ! filled($artifact->file_path) || ! filled($artifact->sha256)) {
            return false;
        }

        $path = $artifact->absolutePath();
        if ($path === null || ! is_file($path)) {
            return false;
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return false;
        }

        return hash_file('sha256', $path) === (string) $artifact->sha256;
    }

    private function labelArtifactStatus(?FedExValidationArtifact $artifact, string $expectedFormat): string
    {
        if ($artifact === null) {
            return 'incomplete';
        }

        if (! $this->artifactIntegrityValid($artifact)) {
            return 'incomplete';
        }

        $path = $artifact->absolutePath();

        return FedExLabelArtifactValidator::isValid((string) $path, $expectedFormat) ? 'passed' : 'failed';
    }

    private function scanArtifactStatus(?FedExValidationArtifact $artifact): string
    {
        if ($artifact === null) {
            return 'incomplete';
        }

        if (! $this->artifactIntegrityValid($artifact)) {
            return 'incomplete';
        }

        $path = $artifact->absolutePath();
        $mime = is_string($path) && is_file($path)
            ? (string) mime_content_type($path)
            : (string) ($artifact->mime_type ?? '');

        // Match upload validation: PDF, PNG, JPG/JPEG are accepted for printed scans.
        if (! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'], true)) {
            return 'failed';
        }

        if ((int) ($artifact->scan_dpi ?? 0) < 600) {
            return 'incomplete';
        }

        if (! (bool) data_get($artifact->metadata_json, 'printed_scan_attestation', false)) {
            return 'incomplete';
        }

        $scanValidation = FedExLabelArtifactValidator::validateScan((string) $path, (int) $artifact->scan_dpi);
        if (! ($scanValidation['valid'] ?? false)) {
            return 'failed';
        }

        return 'passed';
    }

    /**
     * Human-readable explanation for a printed-scan check.
     */
    private function scanArtifactExplanation(?FedExValidationArtifact $artifact, int $sequence): string
    {
        if ($artifact === null) {
            return 'Upload a 600 DPI or higher printed scan for package '.$sequence.'.';
        }

        if (! $this->artifactIntegrityValid($artifact)) {
            return 'Printed scan file is missing, empty, or failed integrity checks. Re-upload the scanned print.';
        }

        $path = $artifact->absolutePath();
        $mime = is_string($path) && is_file($path)
            ? (string) mime_content_type($path)
            : (string) ($artifact->mime_type ?? '');

        if (! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'], true)) {
            return 'Printed scan must be a PDF, PNG, or JPG/JPEG file.';
        }

        if ((int) ($artifact->scan_dpi ?? 0) < 600) {
            return 'Printed scan DPI must be 600 or higher. Re-upload with the scanner DPI set correctly.';
        }

        if (! (bool) data_get($artifact->metadata_json, 'printed_scan_attestation', false)) {
            return 'Printed scan attestation is missing. Re-upload and confirm the physical-print attestation checkbox.';
        }

        $scanValidation = FedExLabelArtifactValidator::validateScan((string) $path, (int) $artifact->scan_dpi);
        if (! ($scanValidation['valid'] ?? false)) {
            $reason = (string) ($scanValidation['reason'] ?? 'validation_failed');

            return match ($reason) {
                'detected_dpi_below_minimum' => 'Uploaded scan image DPI metadata is below 600. Re-scan the printed label at 600 DPI or higher.',
                'claimed_dpi_below_minimum' => 'Claimed scan DPI is below 600. Re-upload with DPI set to at least 600.',
                'unsupported_mime' => 'Printed scan must be a PDF, PNG, or JPG/JPEG file.',
                'empty_file' => 'Printed scan file is empty. Re-upload the scan.',
                default => 'Printed scan failed validation ('.$reason.'). Re-upload a 600 DPI+ scan of the printed label.',
            };
        }

        return 'Printed scan uploaded and accepted (600 DPI+ attested).';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hostedEulaChecks(CarrierAccount $account): array
    {
        $document = $this->hostedEulaEvidence->documentCheck();
        $acceptance = $this->hostedEulaEvidence->accountAcceptanceCheck($account);
        $fullUi = $this->hostedEulaEvidence->findEulaArtifact($account, FedExValidationArtifact::TYPE_EULA_FULL_UI_EVIDENCE);
        $confirmation = $this->hostedEulaEvidence->findEulaArtifact($account, FedExValidationArtifact::TYPE_EULA_ACCEPTANCE_CONFIRMATION);

        return [
            [
                'key' => 'hosted_eula_document',
                'category' => 'registration_mfa',
                'label' => 'Hosted FedEx EULA document',
                'required' => true,
                'status' => $document['status'],
                'explanation' => (string) $document['explanation'],
            ],
            [
                'key' => 'hosted_eula_acceptance',
                'category' => 'registration_mfa',
                'label' => 'Hosted FedEx EULA acceptance',
                'required' => true,
                'status' => match ($acceptance['status']) {
                    'passed' => 'passed',
                    'outdated' => 'outdated',
                    default => 'incomplete',
                },
                'explanation' => (string) $acceptance['explanation'],
            ],
            [
                'key' => 'hosted_eula_full_ui_evidence',
                'category' => 'registration_mfa',
                'label' => 'Hosted EULA full UI evidence',
                'required' => true,
                'status' => $this->eulaArtifactStatus($fullUi, ['application/pdf']),
                'explanation' => $fullUi
                    ? 'Full hosted EULA UI evidence uploaded.'
                    : 'Upload the multi-page PDF created from Print / Save EULA evidence.',
                'artifact_id' => $fullUi?->id,
            ],
            [
                'key' => 'hosted_eula_acceptance_confirmation',
                'category' => 'registration_mfa',
                'label' => 'Hosted EULA acceptance confirmation',
                'required' => true,
                'status' => $this->eulaArtifactStatus($confirmation, ['application/pdf', 'image/png', 'image/jpeg']),
                'explanation' => $confirmation
                    ? 'Acceptance confirmation screenshot uploaded.'
                    : 'Upload a screenshot or PDF showing successful current-document acceptance.',
                'artifact_id' => $confirmation?->id,
            ],
        ];
    }

    /**
     * @param  list<string>  $allowedMimeTypes
     */
    private function eulaArtifactStatus(?FedExValidationArtifact $artifact, array $allowedMimeTypes): string
    {
        if ($artifact === null) {
            return 'incomplete';
        }

        if (! $this->artifactIntegrityValid($artifact)) {
            return 'incomplete';
        }

        $path = $artifact->absolutePath();
        $mime = (string) mime_content_type((string) $path);

        return in_array($mime, $allowedMimeTypes, true) ? 'passed' : 'failed';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function swedenPassthroughChecks(Store $store, CarrierAccount $account): array
    {
        $pairedRun = $this->evidenceQuery->canonicalSwedenPassthroughRun($store, $account);
        $addressEvent = $pairedRun['address_event'] ?? null;
        $childEvent = $pairedRun['child_authorization_event'] ?? null;

        $checks = [
            [
                'key' => 'sweden_passthrough_address',
                'category' => 'registration_mfa',
                'label' => 'Sweden passthrough address validation',
                'required' => true,
                'status' => $addressEvent !== null ? 'passed' : 'not_tested',
                'explanation' => $addressEvent
                    ? 'Canonical Sweden passthrough address evidence recorded with child credentials and no MFA.'
                    : 'Run Sweden MFA Passthrough in the validation workspace.',
                'event_id' => $addressEvent?->id,
            ],
            [
                'key' => 'sweden_passthrough_child_authorization',
                'category' => 'registration_mfa',
                'label' => 'Sweden passthrough child authorization',
                'required' => true,
                'status' => $childEvent !== null ? 'passed' : 'not_tested',
                'explanation' => $childEvent
                    ? 'Direct child authorization recorded for the same validation run.'
                    : 'Complete Sweden MFA Passthrough to record direct child authorization evidence.',
                'event_id' => $childEvent?->id,
            ],
        ];

        foreach ([
            'sweden_passthrough_address_screenshot' => [
                'type' => FedExValidationArtifact::TYPE_SWEDEN_PASSTHROUGH_ADDRESS_SCREENSHOT,
                'label' => 'Sweden passthrough address screenshot',
                'event' => $addressEvent,
            ],
            'sweden_passthrough_child_authorization_screenshot' => [
                'type' => FedExValidationArtifact::TYPE_SWEDEN_PASSTHROUGH_CHILD_AUTH_SCREENSHOT,
                'label' => 'Sweden passthrough child authorization screenshot',
                'event' => $childEvent,
            ],
        ] as $key => $meta) {
            $artifact = $this->findSwedenScreenshotArtifact($store, $account, (string) $meta['type'], $pairedRun);
            $checks[] = [
                'key' => $key,
                'category' => 'registration_mfa',
                'label' => (string) $meta['label'],
                'required' => true,
                'status' => $this->artifactIntegrityValid($artifact) ? 'passed' : 'incomplete',
                'explanation' => $this->artifactIntegrityValid($artifact)
                    ? 'Screenshot uploaded and linked to the canonical Sweden passthrough run.'
                    : 'Upload Sweden passthrough screenshots after a successful paired run.',
                'artifact_id' => $artifact?->id,
                'event_id' => $meta['event']?->id,
            ];
        }

        return $checks;
    }

    /**
     * @param  array{validation_run_id?: string, address_event?: CarrierApiEvent, child_authorization_event?: CarrierApiEvent}|null  $pairedRun
     */
    private function findSwedenScreenshotArtifact(
        Store $store,
        CarrierAccount $account,
        string $artifactType,
        ?array $pairedRun,
    ): ?FedExValidationArtifact {
        if ($pairedRun === null) {
            return null;
        }

        $runId = (string) ($pairedRun['validation_run_id'] ?? '');

        $artifact = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('artifact_type', $artifactType)
            ->where('artifact_role', FedExValidationArtifact::ROLE_SWEDEN_PASSTHROUGH_SCREENSHOT)
            ->when($runId !== '', fn ($query) => $query->where('metadata_json->validation_run_id', $runId))
            ->latest('id')
            ->first();

        return $this->artifactIntegrityValid($artifact) ? $artifact : null;
    }

    private function trackingScreenshotCheck(Store $store, CarrierAccount $account, ?CarrierApiEvent $trackingEvent): array
    {
        $artifact = $trackingEvent === null ? null : FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('carrier_api_event_id', $trackingEvent->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_TRACKING_SCREENSHOT)
            ->latest('id')
            ->first();

        $status = $this->artifactIntegrityValid($artifact) ? 'passed' : 'incomplete';

        return [
            'key' => 'tracking_screenshot',
            'category' => 'tracking',
            'label' => 'Customer-facing tracking screenshot',
            'required' => true,
            'status' => $status,
            'explanation' => $status === 'passed'
                ? 'Tracking screenshot uploaded and linked to the canonical tracking event.'
                : 'Upload a customer-facing tracking screenshot linked to the successful tracking run.',
            'artifact_id' => $artifact?->id,
            'event_id' => $trackingEvent?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $check
     */
    private function isBlockingCheck(array $check): bool
    {
        if (! ($check['required'] ?? false)) {
            return false;
        }

        return ! in_array((string) ($check['status'] ?? ''), [
            'passed',
            'not_required',
            'not_applicable',
            'waived_confirmed',
        ], true);
    }
}
