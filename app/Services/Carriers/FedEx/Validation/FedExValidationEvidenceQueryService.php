<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Database\Eloquent\Builder;

class FedExValidationEvidenceQueryService
{
    public function __construct(
        private readonly FedExValidationAuthorizationEvidenceRules $authorizationEvidenceRules,
        private readonly FedExShipEvidenceRules $shipEvidenceRules,
        private readonly FedExConfig $config,
    ) {}

    public function canonicalAuthorizationEvent(
        Store $store,
        CarrierAccount $account,
        string $scenarioKey,
        string $action,
        string $expectedGrantType,
    ): ?CarrierApiEvent {
        $candidates = $this->baseQuery($store, $account)
            ->where('scenario_key', $scenarioKey)
            ->where('action', $action)
            ->orderByDesc('id')
            ->get()
            ->reject(fn (CarrierApiEvent $event): bool => (bool) data_get($event->request_summary, 'cached')
                || (bool) data_get($event->response_summary, 'cached'));

        return $candidates->first(
            fn (CarrierApiEvent $event): bool => $this->authorizationEvidenceRules->satisfiesRequirements($event, $expectedGrantType),
        );
    }

    public function canonicalEvent(
        Store $store,
        CarrierAccount $account,
        string $scenarioKey,
        ?string $testCaseKey = null,
        ?string $labelFormat = null,
        ?string $mfaMethod = null,
        bool $requireSuccess = false,
    ): ?CarrierApiEvent {
        $query = $this->baseQuery($store, $account)
            ->where('scenario_key', $scenarioKey);

        if ($testCaseKey !== null) {
            $query->where('test_case_key', $testCaseKey);
        }

        if ($labelFormat !== null) {
            $query->where('label_format', strtoupper($labelFormat));
        }

        if ($mfaMethod !== null) {
            $query->where('mfa_method', strtolower($mfaMethod));
        }

        if ($requireSuccess) {
            $query->where('status', CarrierApiEvent::STATUS_SUCCEEDED)
                ->whereBetween('http_status', [200, 299]);
        }

        return $query->latest('id')->first();
    }

    public function latestByAction(
        Store $store,
        CarrierAccount $account,
        string $action,
        ?string $environment = null,
    ): ?CarrierApiEvent {
        $query = $this->baseQuery($store, $account)->where('action', $action);

        if ($environment !== null) {
            $query->where('environment', $environment);
        }

        return $query->latest('id')->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CarrierApiEvent>
     */
    public function registrationEvents(Store $store, CarrierAccount $account)
    {
        return $this->baseQuery($store, $account)
            ->whereNotNull('scenario_key')
            ->where('scenario_key', 'like', 'registration_%')
            ->orderBy('id')
            ->get();
    }

    public function eventById(Store $store, CarrierAccount $account, ?int $eventId): ?CarrierApiEvent
    {
        if ($eventId === null || $eventId <= 0) {
            return null;
        }

        return $this->baseQuery($store, $account)->whereKey($eventId)->first();
    }

    public function canonicalSuccessfulEvent(
        Store $store,
        CarrierAccount $account,
        string $scenarioKey,
        ?string $testCaseKey = null,
        ?string $labelFormat = null,
        ?string $mfaMethod = null,
    ): ?CarrierApiEvent {
        if ($scenarioKey === CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS) {
            return $this->canonicalRegistrationAddressEvent($store, $account);
        }

        return $this->firstCanonicalCandidate(
            $this->canonicalCandidates($store, $account, $scenarioKey, $testCaseKey, $labelFormat, $mfaMethod),
        );
    }

    public function canonicalRegistrationAddressEvent(Store $store, CarrierAccount $account): ?CarrierApiEvent
    {
        return $this->baseQuery($store, $account)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->orderByDesc('id')
            ->get()
            ->first(fn (CarrierApiEvent $event): bool => $this->isValidRegistrationAddressEvent($event));
    }

    public function latestRegistrationAddressAttempt(Store $store, CarrierAccount $account): ?CarrierApiEvent
    {
        return $this->baseQuery($store, $account)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->orderByDesc('id')
            ->get()
            ->first(fn (CarrierApiEvent $event): bool => $event->hasCompleteEvidence());
    }

    public function isValidRegistrationAddressEvent(CarrierApiEvent $event): bool
    {
        if ($event->scenario_key !== CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS) {
            return false;
        }

        if (! $event->hasCompleteEvidence()) {
            return false;
        }

        $httpStatus = (int) ($event->http_status ?? 0);
        if ($httpStatus < 200 || $httpStatus >= 300) {
            return false;
        }

        if ($event->isSuccessfulHttp()) {
            return true;
        }

        if ($event->error_code === 'registration_mfa_required') {
            return true;
        }

        if (data_get($event->response_summary, 'mfa_detected')) {
            return true;
        }

        if (data_get($event->response_summary, 'registered')) {
            return true;
        }

        return data_get($event->response_summary, 'credential_key_detected')
            && data_get($event->response_summary, 'credential_secret_detected');
    }

    public function isFinalExportableEvent(CarrierApiEvent $event): bool
    {
        if (! $event->hasCompleteEvidence()) {
            return false;
        }

        if ($event->scenario_key === CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS) {
            return $this->isValidRegistrationAddressEvent($event);
        }

        return $event->isSuccessfulHttp();
    }

    /**
     * @return array{
     *     validation_run_id: string,
     *     address_event: CarrierApiEvent,
     *     child_authorization_event: CarrierApiEvent
     * }|null
     */
    public function canonicalSwedenPassthroughRun(Store $store, CarrierAccount $account): ?array
    {
        $addressEvents = $this->baseQuery($store, $account)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (CarrierApiEvent $event): bool => $this->isValidSwedenPassthroughAddressEvent($event));

        foreach ($addressEvents as $addressEvent) {
            $runId = (string) data_get($addressEvent->request_summary, 'validation_run_id', '');
            if ($runId === '') {
                continue;
            }

            $childEvent = $this->baseQuery($store, $account)
                ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD)
                ->where('action', CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN)
                ->where('request_summary->validation_run_id', $runId)
                ->orderByDesc('id')
                ->get()
                ->first(fn (CarrierApiEvent $event): bool => $this->isValidSwedenPassthroughChildEvent($event));

            if ($childEvent !== null) {
                return [
                    'validation_run_id' => $runId,
                    'address_event' => $addressEvent,
                    'child_authorization_event' => $childEvent,
                ];
            }
        }

        return null;
    }

    public function latestSwedenPassthroughAttempt(Store $store, CarrierAccount $account): ?array
    {
        $addressEvent = $this->baseQuery($store, $account)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_SWEDEN_PASSTHROUGH_ADDRESS)
            ->orderByDesc('id')
            ->first();

        if ($addressEvent === null) {
            return null;
        }

        $runId = (string) data_get($addressEvent->request_summary, 'validation_run_id', '');

        $childEvent = $runId === ''
            ? null
            : $this->baseQuery($store, $account)
                ->where('scenario_key', CarrierApiEvent::SCENARIO_AUTHORIZATION_SWEDEN_PASSTHROUGH_CHILD)
                ->where('request_summary->validation_run_id', $runId)
                ->orderByDesc('id')
                ->first();

        return [
            'validation_run_id' => $runId !== '' ? $runId : null,
            'address_event' => $addressEvent,
            'child_authorization_event' => $childEvent,
        ];
    }

    private function isValidSwedenPassthroughAddressEvent(CarrierApiEvent $event): bool
    {
        if (! $event->hasCompleteEvidence() || ! $event->isSuccessfulHttp()) {
            return false;
        }

        if ($event->status !== CarrierApiEvent::STATUS_SUCCEEDED) {
            return false;
        }

        if ((string) data_get($event->request_summary, 'case_key') !== FedExValidationSwedenPassthroughSupport::CASE_KEY) {
            return false;
        }

        if (strtoupper((string) data_get($event->request_summary, 'country_code', '')) !== 'SE') {
            return false;
        }

        if (! data_get($event->response_summary, 'child_credentials_detected')) {
            return false;
        }

        if (data_get($event->response_summary, 'mfa_detected')) {
            return false;
        }

        return filled(data_get($event->request_summary, 'validation_run_id'));
    }

    private function isValidSwedenPassthroughChildEvent(CarrierApiEvent $event): bool
    {
        return app(FedExValidationAuthorizationEvidenceRules::class)
            ->satisfiesRequirements($event, 'csp_credentials');
    }

    public function canonicalShipLabelEvent(
        Store $store,
        CarrierAccount $account,
        string $scenarioKey,
        ?string $testCaseKey = null,
        ?string $labelFormat = null,
    ): ?CarrierApiEvent {
        if ($testCaseKey === null) {
            return $this->firstCanonicalCandidate(
                $this->canonicalCandidates($store, $account, $scenarioKey, null, $labelFormat, null)
                    ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL),
            );
        }

        $run = $this->canonicalShipRun($store, $account, $testCaseKey);

        if ($run === null) {
            $region = app(FedExShipFixtureResolver::class)->regionForCase($testCaseKey);
            if ($region !== null && $region !== 'US') {
                $run = $this->canonicalGlobalShipRun($store, $account, $region, $testCaseKey);
            }
        }

        return $run['event'] ?? null;
    }

    /**
     * @return array{
     *     event: ?CarrierApiEvent,
     *     generated_labels: list<\App\Models\FedExValidationArtifact>,
     *     printed_scans: list<\App\Models\FedExValidationArtifact>,
     *     validation: array<string, mixed>
     * }|null
     */
    public function canonicalShipRun(Store $store, CarrierAccount $account, string $testCaseKey): ?array
    {
        $meta = FedExValidationScenarioCatalog::lockedShipScenarios()[$testCaseKey] ?? null;
        if ($meta === null) {
            return null;
        }

        return $this->buildCanonicalShipRun($store, $account, $testCaseKey, $meta, null);
    }

    /**
     * @return array{
     *     event: CarrierApiEvent,
     *     generated_labels: list<\App\Models\FedExValidationArtifact>,
     *     printed_scans: list<\App\Models\FedExValidationArtifact>,
     *     validation: array<string, mixed>
     * }|null
     */
    public function canonicalGlobalShipRun(
        Store $store,
        CarrierAccount $account,
        string $region,
        string $testCaseKey,
    ): ?array {
        $meta = FedExValidationScenarioCatalog::globalShipScenarios()[$testCaseKey] ?? null;
        if ($meta === null || strtoupper((string) ($meta['validation_region'] ?? '')) !== strtoupper($region)) {
            return null;
        }

        return $this->buildCanonicalShipRun($store, $account, $testCaseKey, $meta, strtoupper($region));
    }

    public function latestGlobalShipLabelAttempt(
        Store $store,
        CarrierAccount $account,
        string $region,
        string $scenarioKey,
        ?string $testCaseKey = null,
        ?string $labelFormat = null,
    ): ?CarrierApiEvent {
        return $this->canonicalCandidates($store, $account, $scenarioKey, $testCaseKey, $labelFormat, null, strtoupper($region))
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->first(fn (CarrierApiEvent $event): bool => $event->hasCompleteEvidence());
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *     event: CarrierApiEvent,
     *     generated_labels: list<\App\Models\FedExValidationArtifact>,
     *     printed_scans: list<\App\Models\FedExValidationArtifact>,
     *     validation: array<string, mixed>
     * }|null
     */
    private function buildCanonicalShipRun(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        array $meta,
        ?string $validationRegion,
    ): ?array {
        $scenarioKey = (string) $meta['scenario_key'];
        $labelFormat = (string) $meta['label_format'];

        $candidates = $this->canonicalCandidates(
            $store,
            $account,
            $scenarioKey,
            $testCaseKey,
            $labelFormat,
            null,
            $validationRegion,
        )->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL);

        foreach ($candidates as $event) {
            if (! $this->shipEvidenceRules->isValidEventForTestCase($event, $testCaseKey)) {
                continue;
            }

            $generatedLabels = \App\Models\FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('carrier_api_event_id', $event->id)
                ->where('artifact_role', \App\Models\FedExValidationArtifact::ROLE_GENERATED_LABEL)
                ->orderBy('package_sequence')
                ->get()
                ->filter(fn (\App\Models\FedExValidationArtifact $artifact): bool => $this->artifactIntegrityValid($artifact))
                ->values()
                ->all();

            $documentArtifacts = [];
            if ($testCaseKey === 'IntegratorUS08') {
                $documentArtifacts = \App\Models\FedExValidationArtifact::query()
                    ->where('store_id', $store->id)
                    ->where('carrier_account_id', $account->id)
                    ->where('carrier_api_event_id', $event->id)
                    ->where('artifact_role', \App\Models\FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
                    ->whereIn('artifact_type', ['freight_bill_of_lading', 'freight_commercial_invoice'])
                    ->orderBy('id')
                    ->get()
                    ->all();
            }

            $artifactValidation = $this->shipEvidenceRules->validateGeneratedArtifacts(
                $event,
                $testCaseKey,
                $generatedLabels,
                $documentArtifacts,
            );
            if (! $artifactValidation['valid']) {
                continue;
            }

            $printedScans = \App\Models\FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('carrier_api_event_id', $event->id)
                ->where('artifact_role', \App\Models\FedExValidationArtifact::ROLE_PRINTED_SCAN)
                ->orderBy('package_sequence')
                ->get()
                ->filter(fn (\App\Models\FedExValidationArtifact $artifact): bool => $this->scanArtifactValid($artifact))
                ->values()
                ->all();

            $responseValidation = $this->shipEvidenceRules->validateResponse($event, $testCaseKey);
            $requestValidation = $this->shipEvidenceRules->validateRequest($event, $testCaseKey);

            return [
                'event' => $event,
                'generated_labels' => $generatedLabels,
                'printed_scans' => $printedScans,
                'validation' => [
                    'request_valid' => $requestValidation['valid'],
                    'response_valid' => $responseValidation['valid'],
                    'response_service_matches' => $responseValidation['valid'],
                    'response_service_type' => data_get($responseValidation, 'parsed.service_type'),
                    'artifact_integrity_passed' => $artifactValidation['valid'],
                    'mps_correlation_passed' => $testCaseKey === 'IntegratorUS05'
                        ? $responseValidation['valid'] && $artifactValidation['valid']
                        : null,
                    'package_sequences' => array_keys($responseValidation['parsed']['labels'] ?? []),
                    'reasons' => array_values(array_unique(array_merge(
                        $requestValidation['reasons'],
                        $responseValidation['reasons'],
                        $artifactValidation['reasons'],
                    ))),
                ],
            ];
        }

        return null;
    }

    public function latestShipLabelAttempt(
        Store $store,
        CarrierAccount $account,
        string $scenarioKey,
        ?string $testCaseKey = null,
        ?string $labelFormat = null,
    ): ?CarrierApiEvent {
        return $this->canonicalCandidates($store, $account, $scenarioKey, $testCaseKey, $labelFormat, null)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->first(fn (CarrierApiEvent $event): bool => $event->hasCompleteEvidence());
    }

    private function artifactIntegrityValid(?\App\Models\FedExValidationArtifact $artifact): bool
    {
        if ($artifact === null || ! filled($artifact->file_path) || ! filled($artifact->sha256)) {
            return false;
        }

        $path = $artifact->absolutePath();
        if ($path === null || ! is_file($path)) {
            return false;
        }

        return hash_file('sha256', $path) === (string) $artifact->sha256;
    }

    private function scanArtifactValid(?\App\Models\FedExValidationArtifact $artifact): bool
    {
        if (! $this->artifactIntegrityValid($artifact)) {
            return false;
        }

        $path = (string) $artifact->absolutePath();
        $scanValidation = FedExLabelArtifactValidator::validateScan($path, (int) ($artifact->scan_dpi ?? 0));

        return $scanValidation['valid']
            && (bool) data_get($artifact->metadata_json, 'printed_scan_attestation', false);
    }

    /**
     * @return \Illuminate\Support\Collection<int, CarrierApiEvent>
     */
    private function canonicalCandidates(
        Store $store,
        CarrierAccount $account,
        string $scenarioKey,
        ?string $testCaseKey,
        ?string $labelFormat,
        ?string $mfaMethod,
        ?string $validationRegion = null,
    ) {
        return $this->baseQuery($store, $account)
            ->where('scenario_key', $scenarioKey)
            ->when($testCaseKey !== null, fn (Builder $q) => $q->where('test_case_key', $testCaseKey))
            ->when($labelFormat !== null, fn (Builder $q) => $q->where('label_format', strtoupper($labelFormat)))
            ->when($mfaMethod !== null, fn (Builder $q) => $q->where('mfa_method', strtolower($mfaMethod)))
            ->when($validationRegion !== null, fn (Builder $q) => $q->where('validation_region', strtoupper($validationRegion)))
            ->when($validationRegion === null && $this->isUsLockedShipTestCase($testCaseKey), function (Builder $q): void {
                $q->where(function (Builder $inner): void {
                    $inner->whereNull('validation_region')->orWhere('validation_region', 'US');
                });
            })
            ->orderByDesc('id')
            ->get();
    }

    private function isUsLockedShipTestCase(?string $testCaseKey): bool
    {
        return $testCaseKey !== null
            && array_key_exists($testCaseKey, FedExValidationScenarioCatalog::lockedShipScenarios());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CarrierApiEvent>  $candidates
     */
    private function firstCanonicalCandidate($candidates): ?CarrierApiEvent
    {
        // Prefer true successes. Never fall back to failed/incomplete events that merely have
        // request/response bodies (e.g. US09 HTTP 201 upload that failed local docId parsing).
        return $candidates->first(
            fn (CarrierApiEvent $event): bool => $event->hasCompleteEvidence() && $event->isSuccessfulHttp()
        );
    }

    /**
     * Canonical US09 commercial-invoice upload with a recoverable encrypted document id.
     */
    public function canonicalUs09DocumentUploadEvent(Store $store, CarrierAccount $account): ?CarrierApiEvent
    {
        $candidates = $this->canonicalCandidates(
            $store,
            $account,
            FedExUs09EtdFixtureService::UPLOAD_SCENARIO_DOCUMENT,
            testCaseKey: null,
            labelFormat: null,
            mfaMethod: null,
        );

        return $candidates->first(function (CarrierApiEvent $event): bool {
            if (! $event->hasCompleteEvidence() || ! $event->isSuccessfulHttp()) {
                return false;
            }

            $documentId = \App\Services\Carriers\FedEx\Operations\FedExTradeDocumentUploadService::resolveStoredDocumentId($event);

            return is_string($documentId) && $documentId !== '' && $documentId !== '{{US09_DOCUMENT_ID}}';
        });
    }

    public function latestCompleteEvent(
        Store $store,
        CarrierAccount $account,
        string $scenarioKey,
        ?string $testCaseKey = null,
        ?string $labelFormat = null,
        ?string $mfaMethod = null,
    ): ?CarrierApiEvent {
        return $this->baseQuery($store, $account)
            ->where('scenario_key', $scenarioKey)
            ->when($testCaseKey !== null, fn (Builder $q) => $q->where('test_case_key', $testCaseKey))
            ->when($labelFormat !== null, fn (Builder $q) => $q->where('label_format', strtoupper($labelFormat)))
            ->when($mfaMethod !== null, fn (Builder $q) => $q->where('mfa_method', strtolower($mfaMethod)))
            ->orderByDesc('id')
            ->get()
            ->first(fn (CarrierApiEvent $event): bool => $event->hasCompleteEvidence());
    }

    public function canonicalComprehensiveRateEvent(Store $store, CarrierAccount $account): ?CarrierApiEvent
    {
        return $this->baseQuery($store, $account)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE)
            ->orderByDesc('id')
            ->get()
            ->first(fn (CarrierApiEvent $event): bool => $this->isValidComprehensiveRateSuccessEvent($event));
    }

    public function latestComprehensiveRateAccessBlocker(Store $store, CarrierAccount $account): ?CarrierApiEvent
    {
        return $this->baseQuery($store, $account)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_RATE_COMPREHENSIVE_QUOTE)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE)
            ->orderByDesc('id')
            ->get()
            ->first(fn (CarrierApiEvent $event): bool => $this->isValidComprehensiveRateBlockerEvent($event));
    }

    private function isValidComprehensiveRateSuccessEvent(CarrierApiEvent $event): bool
    {
        if (! $this->isValidComprehensiveRateEventBase($event)) {
            return false;
        }

        if (! $event->isSuccessfulHttp() || $event->status !== CarrierApiEvent::STATUS_SUCCEEDED) {
            return false;
        }

        $amount = data_get($event->response_summary, 'amount');
        $currency = data_get($event->response_summary, 'currency');

        return is_numeric($amount) && filled($currency);
    }

    private function isValidComprehensiveRateBlockerEvent(CarrierApiEvent $event): bool
    {
        if (! $this->isValidComprehensiveRateEventBase($event)) {
            return false;
        }

        return (int) $event->http_status === 403
            || in_array((string) $event->error_code, [
                'fedex_comprehensive_rate_blocked_entitlement',
                'fedex_comprehensive_rate_blocked_access',
            ], true);
    }

    private function isValidComprehensiveRateEventBase(CarrierApiEvent $event): bool
    {
        if (! $event->hasCompleteEvidence()) {
            return false;
        }

        if (strtoupper((string) $event->http_method) !== 'POST') {
            return false;
        }

        $endpoint = '/'.ltrim((string) ($event->endpoint ?? data_get($event->request_summary, 'endpoint', '')), '/');

        return $endpoint === FedExConfig::COMPREHENSIVE_RATE_QUOTE_PATH;
    }

    private function baseQuery(Store $store, CarrierAccount $account): Builder
    {
        $query = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('provider', CarrierAccount::PROVIDER_FEDEX);

        $sessionId = $account->registration_session_id;

        return $query->where(function (Builder $scoped) use ($account, $sessionId): void {
            $scoped->where('carrier_account_id', $account->id);

            if ($sessionId !== null) {
                $scoped->orWhere(function (Builder $sessionScoped) use ($sessionId): void {
                    $sessionScoped->where('registration_session_id', $sessionId)
                        ->whereNull('carrier_account_id');
                });
            }
        });
    }
}
