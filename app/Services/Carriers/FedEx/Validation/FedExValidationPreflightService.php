<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;

class FedExValidationPreflightService
{
    public const SCHEMA_VERSION = '1.0';

    public function __construct(
        private readonly FedExValidationScopeService $scopeService,
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
        private readonly FedExValidationEvidenceSanitizer $sanitizer,
        private readonly FedExValidationAuthorizationEvidenceRules $authorizationEvidenceRules,
    ) {}

    /**
     * @param  list<string>|null  $scopes
     * @return array<string, mixed>
     */
    public function assess(Store $store, CarrierAccount $account, ?array $scopes = null): array
    {
        $scopes = $this->scopeService->resolveRequiredScopes($scopes);
        $checks = [];
        $blockers = [];
        $warnings = [];

        foreach ($this->requiredDocumentChecks($store, $account) as $check) {
            $checks[] = $check;
            if ($check['required'] && $check['status'] !== 'passed') {
                $blockers[] = $check;
            }
        }

        foreach ($this->authorizationChecks($store, $account) as $check) {
            $checks[] = $check;
            if ($check['required'] && $check['status'] !== 'passed') {
                $blockers[] = $check;
            }
        }

        foreach ($this->registrationChecks($store, $account) as $check) {
            $checks[] = $check;
            if ($check['required'] && ! in_array($check['status'], ['passed', 'not_required'], true)) {
                $blockers[] = $check;
            }
        }

        foreach ($this->swedenPassthroughChecks($store, $account) as $check) {
            $checks[] = $check;
            if ($check['required'] && $check['status'] !== 'passed') {
                $blockers[] = $check;
            }
        }

        if (in_array(FedExValidationScopeService::SCOPE_ADDRESS_VALIDATION, $scopes, true)) {
            $addressCheck = $this->apiCheck(
                'address_validation',
                'Address validation',
                $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'address_validation'),
            );
            $checks[] = $addressCheck;
            if ($addressCheck['required'] && $addressCheck['status'] !== 'passed') {
                $blockers[] = $addressCheck;
            }
        }

        if (in_array(FedExValidationScopeService::SCOPE_SERVICE_AVAILABILITY, $scopes, true)) {
            $serviceCheck = $this->apiCheck(
                'service_availability',
                'Service availability',
                $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'service_availability'),
            );
            $checks[] = $serviceCheck;
            if ($serviceCheck['required'] && $serviceCheck['status'] !== 'passed') {
                $blockers[] = $serviceCheck;
            }
        }

        if (in_array(FedExValidationScopeService::SCOPE_COMPREHENSIVE_RATES, $scopes, true)) {
            $rateEvent = $this->evidenceQuery->latestCompleteEvent($store, $account, 'rate_quote');

            $rateCheck = $this->rateQuoteCheck($rateEvent);
            $checks[] = $rateCheck;
            if ($rateCheck['required'] && $rateCheck['status'] !== 'passed') {
                $blockers[] = $rateCheck;
            }
        }

        if (in_array(FedExValidationScopeService::SCOPE_SHIP, $scopes, true)) {
            foreach (FedExValidationScenarioCatalog::lockedShipScenarios() as $testCaseKey => $meta) {
                foreach ($this->shipScenarioChecks($store, $account, $testCaseKey, $meta) as $check) {
                    $checks[] = $check;
                    if ($check['required'] && $check['status'] !== 'passed') {
                        $blockers[] = $check;
                    }
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
            if ($trackingCheck['status'] !== 'passed') {
                $blockers[] = $trackingCheck;
            }

            $screenshotCheck = $this->trackingScreenshotCheck($store, $account, $trackingEvent);
            $checks[] = $screenshotCheck;
            if ($screenshotCheck['status'] !== 'passed') {
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
            if ($cancelCheck['status'] !== 'passed') {
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
            if ($tradeCheck['status'] !== 'passed') {
                $blockers[] = $tradeCheck;
            }
        }

        $requiredChecks = collect($checks)->where('required', true);
        $completed = $requiredChecks->where('status', 'passed')->count();
        $total = $requiredChecks->count();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'ready' => $blockers === [],
            'completed_count' => $completed,
            'total_count' => $total,
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'checks' => $checks,
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

        $event = $this->evidenceQuery->canonicalShipLabelEvent(
            $store,
            $account,
            $scenarioKey,
            testCaseKey: $testCaseKey,
            labelFormat: $labelFormat,
        );

        $checks[] = [
            'key' => $scenarioKey.'_event',
            'category' => 'ship',
            'label' => $testCaseKey.' ship label API',
            'required' => true,
            'status' => $this->eventEvidenceStatus($event, requireSuccess: true),
            'explanation' => $event
                ? 'Canonical successful ship label event recorded.'
                : 'Run the locked '.$testCaseKey.' label button in the validation workspace. Ship validate alone does not create labels.',
            'event_id' => $event?->id,
        ];

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
                $event,
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
                $event,
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
                'explanation' => $scanArtifact
                    ? 'Printed scan uploaded.'
                    : 'Upload a 600 DPI or higher printed scan for package '.$sequence.'.',
                'artifact_id' => $scanArtifact?->id,
            ];
        }

        return $checks;
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

    private function rateQuoteCheck(?CarrierApiEvent $event): array
    {
        if ($event === null) {
            return [
                'key' => 'rate_quote',
                'category' => 'api',
                'label' => 'Comprehensive Rate Quote',
                'required' => true,
                'status' => 'not_tested',
                'explanation' => 'Run the rate quote test before final export.',
                'event_id' => null,
            ];
        }

        if ((int) $event->http_status === 403 || $event->error_code === 'fedex_authorization_blocked') {
            return [
                'key' => 'rate_quote',
                'category' => 'api',
                'label' => 'Comprehensive Rate Quote',
                'required' => true,
                'status' => 'blocked',
                'explanation' => 'Blocked — FedEx entitlement pending. FedEx sandbox entitlement blocker (HTTP 403). Final export remains blocked until FedEx enables Comprehensive Rates for this account.',
                'event_id' => $event->id,
            ];
        }

        return [
            'key' => 'rate_quote',
            'category' => 'api',
            'label' => 'Comprehensive Rate Quote',
            'required' => true,
            'status' => $this->eventEvidenceStatus($event, requireSuccess: true),
            'explanation' => $event->isSuccessfulHttp()
                ? 'Successful rate quote evidence recorded.'
                : 'Rate quote must return HTTP 2xx with complete evidence before final export.',
            'event_id' => $event->id,
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

        if (! in_array((string) mime_content_type((string) $path), ['application/pdf', 'image/png'], true)) {
            return 'failed';
        }

        if ((int) ($artifact->scan_dpi ?? 0) < 600) {
            return 'incomplete';
        }

        return 'passed';
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
}
