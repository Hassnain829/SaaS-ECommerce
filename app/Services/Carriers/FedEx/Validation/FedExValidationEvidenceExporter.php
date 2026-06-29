<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\HttpException;
use ZipArchive;

class FedExValidationEvidenceExporter
{
    public const SCHEMA_VERSION = '1.0';

    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExValidationPreflightService $preflight,
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
        private readonly FedExValidationEvidenceSanitizer $sanitizer,
        private readonly FedExValidationScopeService $scopeService,
        private readonly FedExHostedEulaEvidenceService $hostedEulaEvidence,
        private readonly \App\Services\Carriers\FedEx\Connection\FedExEulaService $eulaService,
    ) {}

    public function exportDiagnostic(
        Store $store,
        ?CarrierAccount $account = null,
        ?CarrierAccountRegistrationSession $session = null,
        string $region = 'US',
        ?string $environment = null,
    ): string {
        $assessment = $account ? $this->preflight->assess($store, $account) : ['ready' => false, 'blockers' => []];

        return $this->buildZip(
            store: $store,
            account: $account,
            session: $session,
            region: $region,
            environment: $environment,
            mode: 'diagnostic',
            preflight: $assessment,
            includeFailedAttempts: true,
        );
    }

    public function exportFinal(
        Store $store,
        CarrierAccount $account,
        string $region = 'US',
        ?string $environment = null,
    ): string {
        $assessment = $this->preflight->assess($store, $account);

        if (! ($assessment['ready'] ?? false)) {
            throw new HttpException(422, 'Final FedEx validation export is blocked until preflight passes.');
        }

        return $this->buildZip(
            store: $store,
            account: $account,
            session: $account->latestRegistrationSession,
            region: $region,
            environment: $environment,
            mode: 'final',
            preflight: $assessment,
            includeFailedAttempts: false,
        );
    }

    /** @deprecated use exportDiagnostic() */
    public function export(
        Store $store,
        ?CarrierAccount $account = null,
        ?CarrierAccountRegistrationSession $session = null,
        string $region = 'US',
        ?string $environment = null,
    ): string {
        return $this->exportDiagnostic($store, $account, $session, $region, $environment);
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function buildZip(
        Store $store,
        ?CarrierAccount $account,
        ?CarrierAccountRegistrationSession $session,
        string $region,
        ?string $environment,
        string $mode,
        array $preflight,
        bool $includeFailedAttempts,
    ): string {
        abort_unless($account !== null, 422, 'A FedEx carrier account is required for validation export.');

        $environment = $this->config->environment($environment ?? $account->environment ?? CarrierAccount::ENVIRONMENT_SANDBOX);
        $scopes = $preflight['selected_scopes'] ?? $this->scopeService->resolveRequiredScopes();
        $timestamp = now()->format('Ymd_His');
        $root = 'FedEx_Integrator_Validation_BaasPlatformFedExSandbox';
        $bundleDir = storage_path("app/fedex-validation/{$store->id}/{$timestamp}/{$root}");

        File::ensureDirectoryExists($bundleDir.'/00_documents');
        File::ensureDirectoryExists($bundleDir.'/01_registration_mfa');
        File::ensureDirectoryExists($bundleDir.'/02_address_validation');
        File::ensureDirectoryExists($bundleDir.'/03_service_availability');
        File::ensureDirectoryExists($bundleDir.'/04_rates');
        File::ensureDirectoryExists($bundleDir.'/05_ship_us02_zplii/generated');
        File::ensureDirectoryExists($bundleDir.'/05_ship_us02_zplii/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/06_ship_us04_png/generated');
        File::ensureDirectoryExists($bundleDir.'/06_ship_us04_png/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/07_ship_us05_pdf_mps/generated');
        File::ensureDirectoryExists($bundleDir.'/07_ship_us05_pdf_mps/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/08_tracking');
        if ($this->scopeService->shipCancelRequired($scopes)) {
            File::ensureDirectoryExists($bundleDir.'/10_cancel_shipment');
        }
        if ($this->scopeService->tradeDocumentsRequired($scopes)) {
            File::ensureDirectoryExists($bundleDir.'/09_trade_documents');
        }

        $this->exportRequiredDocuments($bundleDir.'/00_documents', $store, $account);
        $this->exportRegistrationScenarios($bundleDir.'/01_registration_mfa', $store, $account, $preflight, $includeFailedAttempts);
        $this->exportScenarioEvent($bundleDir.'/02_address_validation', $this->resolveCheckEvent($store, $account, $preflight, 'address_validation', $includeFailedAttempts), $includeFailedAttempts, $mode);
        $this->exportScenarioEvent($bundleDir.'/03_service_availability', $this->resolveCheckEvent($store, $account, $preflight, 'service_availability', $includeFailedAttempts), $includeFailedAttempts, $mode);
        $this->exportScenarioEvent($bundleDir.'/04_rates', $this->resolveCheckEvent($store, $account, $preflight, 'rate_quote', true), true, $mode);
        $this->exportLockedShipScenario($bundleDir.'/05_ship_us02_zplii', $store, $account, 'IntegratorUS02', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/06_ship_us04_png', $store, $account, 'IntegratorUS04', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/07_ship_us05_pdf_mps', $store, $account, 'IntegratorUS05', $preflight, $includeFailedAttempts, $mode);

        if ($this->scopeService->trackingRequired($scopes)) {
            $this->exportScenarioEvent($bundleDir.'/08_tracking', $this->resolveCheckEvent($store, $account, $preflight, 'tracking', $includeFailedAttempts), $includeFailedAttempts, $mode);
            $this->copyTrackingScreenshot($bundleDir.'/08_tracking', $store, $account, $preflight);
        }

        if ($this->scopeService->shipCancelRequired($scopes)) {
            $this->exportScenarioEvent($bundleDir.'/10_cancel_shipment', $this->resolveCheckEvent($store, $account, $preflight, 'ship_cancel', $includeFailedAttempts), $includeFailedAttempts, $mode);
        }

        if ($this->scopeService->tradeDocumentsRequired($scopes)) {
            $this->exportScenarioEvent($bundleDir.'/09_trade_documents', $this->resolveCheckEvent($store, $account, $preflight, 'trade_documents', $includeFailedAttempts), $includeFailedAttempts, $mode);
        }

        $evidenceIndex = $this->buildEvidenceIndex($store, $account, $region, $environment, $preflight);
        $this->writeJson($bundleDir.'/evidence-index.json', $evidenceIndex);
        $this->writeJson($bundleDir.'/preflight-report.json', $preflight);
        $this->writeFile($bundleDir.'/README.md', $this->readme($store, $account, $environment, $region, $mode, $preflight));

        $knownSecrets = $this->knownSecretsForScan($account, $environment);
        $secretScan = $this->sanitizer->scanStagingDirectory($bundleDir, $knownSecrets);
        if ($secretScan !== []) {
            $details = collect($secretScan)
                ->map(fn (array $blocker): string => ($blocker['path'] ?? 'unknown').' ('.($blocker['reason'] ?? 'unknown').')')
                ->take(5)
                ->implode('; ');

            throw new HttpException(422, 'Export blocked: secret scan failed for staged validation bundle. '.$details);
        }

        $zipFilename = $mode === 'final'
            ? "fedex-validation-final-{$store->id}-{$timestamp}.zip"
            : "fedex-validation-diagnostic-{$store->id}-{$timestamp}.zip";
        $zipPath = storage_path("app/fedex-validation/{$store->id}/{$timestamp}/{$zipFilename}");
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (File::allFiles($bundleDir) as $file) {
            $zip->addFile($file->getPathname(), $root.'/'.str_replace('\\', '/', $file->getRelativePathname()));
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function resolveCheckEvent(
        Store $store,
        CarrierAccount $account,
        array $preflight,
        string $checkKey,
        bool $allowFallback,
    ): ?CarrierApiEvent {
        $eventId = collect($preflight['checks'] ?? [])->firstWhere('key', $checkKey)['event_id'] ?? null;
        if ($eventId) {
            return $this->evidenceQuery->eventById($store, $account, (int) $eventId);
        }

        if (! $allowFallback) {
            return null;
        }

        return match ($checkKey) {
            'address_validation' => $this->evidenceQuery->canonicalEvent($store, $account, 'address_validation'),
            'service_availability' => $this->evidenceQuery->canonicalEvent($store, $account, 'service_availability'),
            'rate_quote' => $this->evidenceQuery->canonicalEvent($store, $account, 'rate_quote'),
            'tracking' => $this->evidenceQuery->canonicalEvent($store, $account, 'basic_integrated_visibility'),
            'ship_cancel' => $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'ship_cancel'),
            'trade_documents' => $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, 'trade_documents_upload'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function exportRegistrationScenarios(string $directory, Store $store, CarrierAccount $account, array $preflight, bool $includeFailed): void
    {
        foreach (FedExValidationScenarioCatalog::authorizationScenarios() as $scenarioKey => $meta) {
            $eventId = collect($preflight['checks'] ?? [])->firstWhere('key', $scenarioKey)['event_id'] ?? null;
            $event = $eventId
                ? $this->evidenceQuery->eventById($store, $account, (int) $eventId)
                : $this->evidenceQuery->canonicalAuthorizationEvent(
                    $store,
                    $account,
                    $scenarioKey,
                    (string) $meta['action'],
                    (string) $meta['grant_type'],
                );

            $folder = $directory.'/'.(string) $meta['export_folder'];
            File::ensureDirectoryExists($folder);
            $this->exportScenarioEvent($folder, $event, $includeFailed, $includeFailed ? 'diagnostic' : 'final');
        }

        $order = 3;
        foreach (FedExValidationScenarioCatalog::registrationScenarios() as $scenarioKey => $meta) {
            $eventId = collect($preflight['checks'] ?? [])->firstWhere('key', $scenarioKey)['event_id'] ?? null;
            $event = $eventId
                ? $this->evidenceQuery->eventById($store, $account, (int) $eventId)
                : $this->evidenceQuery->canonicalEvent($store, $account, $scenarioKey, mfaMethod: $meta['mfa_method']);

            $folder = $directory.'/'.str_pad((string) $order, 2, '0', STR_PAD_LEFT).'_'.$scenarioKey;
            File::ensureDirectoryExists($folder);
            $this->exportScenarioEvent($folder, $event, $includeFailed, $includeFailed ? 'diagnostic' : 'final');
            $order++;
        }

        $this->exportSwedenPassthroughScenario($directory, $store, $account, $preflight, $includeFailed);
        $this->exportHostedEulaScenario($directory, $account, $preflight, $includeFailed);
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function exportHostedEulaScenario(
        string $registrationDirectory,
        CarrierAccount $account,
        array $preflight,
        bool $includeFailed,
    ): void {
        $folder = $registrationDirectory.'/'.FedExHostedEulaEvidenceService::EXPORT_FOLDER;
        File::ensureDirectoryExists($folder.'/screenshots');

        $documentCheck = collect($preflight['checks'] ?? [])->firstWhere('key', 'hosted_eula_document');
        $acceptanceCheck = collect($preflight['checks'] ?? [])->firstWhere('key', 'hosted_eula_acceptance');
        $documentPassed = ($documentCheck['status'] ?? '') === 'passed';
        $acceptancePassed = ($acceptanceCheck['status'] ?? '') === 'passed';
        $mode = $includeFailed ? 'diagnostic' : 'final';

        if ($documentPassed && $this->eulaService->isValid()) {
            $sourcePath = $this->eulaService->documentPath();
            File::copy($sourcePath, $folder.'/official_eula.pdf');
            $this->writeJson($folder.'/eula_document_metadata.json', $this->eulaService->metadata());
        } else {
            $this->writeJson($folder.'/eula_document_metadata.json', [
                'status' => 'missing_or_invalid',
                'metadata' => $this->eulaService->metadata(),
            ]);
        }

        $acceptance = $this->hostedEulaEvidence->accountAcceptanceCheck($account);
        $session = $acceptance['session'] ?? null;

        if ($acceptancePassed && $session !== null) {
            $this->writeJson($folder.'/eula_acceptance_record.json', [
                'status' => 'accepted',
                'eula_version' => $session->eula_version,
                'eula_document_hash' => $session->eula_document_hash,
                'all_pages_rendered' => (int) $session->eula_rendered_page_count === $this->eulaService->expectedPages(),
                'scroll_completed' => $session->eula_scrolled_at !== null,
                'read_acknowledged' => $session->eula_read_acknowledged_at !== null,
                'accepted_at' => $session->eula_accepted_at?->toIso8601String(),
                'button_label' => 'I accept',
            ]);
        } else {
            $this->writeJson($folder.'/eula_acceptance_record.json', [
                'status' => $acceptance['status'] ?? 'incomplete',
                'note' => $acceptance['explanation'] ?? 'Hosted EULA acceptance is incomplete.',
            ]);

            if ($mode === 'final') {
                throw new HttpException(422, 'Final export blocked: hosted EULA acceptance is incomplete or outdated.');
            }
        }

        foreach ([
            FedExValidationArtifact::TYPE_EULA_FULL_UI_EVIDENCE => '01_full_hosted_eula_ui.pdf',
            FedExValidationArtifact::TYPE_EULA_ACCEPTANCE_CONFIRMATION => '02_acceptance_confirmation',
        ] as $type => $basename) {
            $artifact = $this->hostedEulaEvidence->findEulaArtifact($account, $type);

            if ($artifact === null) {
                if ($mode === 'final') {
                    throw new HttpException(422, 'Final export blocked: hosted EULA screenshot evidence is incomplete.');
                }

                continue;
            }

            $path = $artifact->absolutePath();
            if ($path === null || ! is_file($path)) {
                if ($mode === 'final') {
                    throw new HttpException(422, 'Final export blocked: hosted EULA screenshot evidence file is missing.');
                }

                continue;
            }

            $extension = strtolower(pathinfo((string) $artifact->original_filename, PATHINFO_EXTENSION)
                ?: pathinfo($path, PATHINFO_EXTENSION)
                ?: 'bin');

            $target = $type === FedExValidationArtifact::TYPE_EULA_ACCEPTANCE_CONFIRMATION
                ? $folder.'/screenshots/'.$basename.'.'.$extension
                : $folder.'/screenshots/'.$basename;

            File::copy($path, $target);
        }
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function exportSwedenPassthroughScenario(
        string $registrationDirectory,
        Store $store,
        CarrierAccount $account,
        array $preflight,
        bool $includeFailed,
    ): void {
        $folder = $registrationDirectory.'/'.FedExValidationSwedenPassthroughSupport::EXPORT_FOLDER;
        $pairedRun = $this->evidenceQuery->canonicalSwedenPassthroughRun($store, $account);
        $mode = $includeFailed ? 'diagnostic' : 'final';

        if ($pairedRun === null) {
            if ($mode === 'final') {
                throw new HttpException(422, 'Final export blocked: Sweden MFA passthrough evidence is incomplete.');
            }

            File::ensureDirectoryExists($folder.'/01_address_validation');
            File::ensureDirectoryExists($folder.'/02_child_authorization');
            File::ensureDirectoryExists($folder.'/screenshots');
            $this->writeJson($folder.'/01_address_validation/request.json', ['status' => 'missing', 'note' => 'No canonical Sweden passthrough run recorded yet.']);
            $this->writeJson($folder.'/01_address_validation/response.json', ['status' => 'missing', 'note' => 'No canonical Sweden passthrough run recorded yet.']);
            $this->writeJson($folder.'/02_child_authorization/request.json', ['status' => 'missing', 'note' => 'No canonical Sweden passthrough run recorded yet.']);
            $this->writeJson($folder.'/02_child_authorization/response.json', ['status' => 'missing', 'note' => 'No canonical Sweden passthrough run recorded yet.']);
            $this->writeJson($folder.'/result_summary.json', ['status' => 'missing', 'note' => 'No canonical Sweden passthrough run recorded yet.']);

            return;
        }

        $addressEvent = $pairedRun['address_event'];
        $childEvent = $pairedRun['child_authorization_event'];

        File::ensureDirectoryExists($folder.'/01_address_validation');
        File::ensureDirectoryExists($folder.'/02_child_authorization');
        File::ensureDirectoryExists($folder.'/screenshots');

        $this->exportScenarioEvent($folder.'/01_address_validation', $addressEvent, false, $mode);
        $this->exportScenarioEvent($folder.'/02_child_authorization', $childEvent, false, $mode);

        $this->writeJson($folder.'/result_summary.json', [
            'case' => 'Sweden MFA Passthrough',
            'account_last4' => data_get($addressEvent->request_summary, 'account_last4', '9268'),
            'country_code' => strtoupper((string) data_get($addressEvent->request_summary, 'country_code', 'SE')),
            'child_credentials_detected' => (bool) data_get($addressEvent->response_summary, 'child_credentials_detected', false),
            'mfa_detected' => (bool) data_get($addressEvent->response_summary, 'mfa_detected', false),
            'pin_or_invoice_steps_executed' => (bool) data_get($addressEvent->response_summary, 'pin_or_invoice_steps_executed', false),
            'direct_child_authorization' => $childEvent->isSuccessfulHttp() ? 'passed' : 'failed',
            'validation_run_id' => $pairedRun['validation_run_id'],
        ]);

        $this->copySwedenPassthroughScreenshots($folder.'/screenshots', $store, $account, $pairedRun);
    }

    /**
     * @param  array{validation_run_id: string, address_event: CarrierApiEvent, child_authorization_event: CarrierApiEvent}  $pairedRun
     */
    private function copySwedenPassthroughScreenshots(string $directory, Store $store, CarrierAccount $account, array $pairedRun): void
    {
        File::ensureDirectoryExists($directory);
        $runId = $pairedRun['validation_run_id'];

        foreach ([
            FedExValidationArtifact::TYPE_SWEDEN_PASSTHROUGH_ADDRESS_SCREENSHOT => '01_passthrough_address_result',
            FedExValidationArtifact::TYPE_SWEDEN_PASSTHROUGH_CHILD_AUTH_SCREENSHOT => '02_direct_child_authorization_result',
        ] as $type => $basename) {
            $artifact = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('artifact_type', $type)
                ->where('artifact_role', FedExValidationArtifact::ROLE_SWEDEN_PASSTHROUGH_SCREENSHOT)
                ->where('metadata_json->validation_run_id', $runId)
                ->latest('id')
                ->first();

            $path = $artifact?->absolutePath();
            if ($path !== null && is_file($path) && filled($artifact->sha256) && hash_file('sha256', $path) === (string) $artifact->sha256) {
                File::copy($path, $directory.'/'.$basename.'.'.pathinfo($path, PATHINFO_EXTENSION));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function exportLockedShipScenario(
        string $directory,
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        array $preflight,
        bool $includeFailed,
        string $mode,
    ): void {
        $meta = FedExValidationScenarioCatalog::lockedShipScenarios()[$testCaseKey];
        $eventCheckKey = $meta['scenario_key'].'_event';
        $eventId = collect($preflight['checks'] ?? [])->firstWhere('key', $eventCheckKey)['event_id'] ?? null;
        $event = $eventId
            ? $this->evidenceQuery->eventById($store, $account, (int) $eventId)
            : $this->evidenceQuery->canonicalShipLabelEvent(
                $store,
                $account,
                (string) $meta['scenario_key'],
                testCaseKey: $testCaseKey,
                labelFormat: (string) $meta['label_format'],
            );

        $this->exportScenarioEvent($directory, $event, $includeFailed, $mode);
        if ($event !== null) {
            $this->copyArtifacts($directory.'/generated', $event, FedExValidationArtifact::ROLE_GENERATED_LABEL);
            $this->copyArtifacts($directory.'/printed_scans', $event, FedExValidationArtifact::ROLE_PRINTED_SCAN);
        }
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function copyTrackingScreenshot(string $directory, Store $store, CarrierAccount $account, array $preflight): void
    {
        $artifactId = collect($preflight['checks'] ?? [])->firstWhere('key', 'tracking_screenshot')['artifact_id'] ?? null;
        if (! $artifactId) {
            return;
        }

        $artifact = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->whereKey($artifactId)
            ->first();

        $path = $artifact?->absolutePath();
        if ($path !== null && is_file($path)) {
            File::copy($path, $directory.'/tracking-screenshot.'.pathinfo($path, PATHINFO_EXTENSION));
        }
    }

    private function exportScenarioEvent(string $directory, ?CarrierApiEvent $event, bool $allowIncomplete, string $mode): void
    {
        File::ensureDirectoryExists($directory);

        if ($event === null) {
            if ($mode === 'final') {
                throw new HttpException(422, 'Missing required evidence for '.$directory);
            }

            if (! $allowIncomplete) {
                throw new HttpException(422, 'Missing required evidence for '.$directory);
            }

            $this->writeJson($directory.'/request.json', ['status' => 'missing', 'note' => 'No recorded event for this scenario yet.']);
            $this->writeJson($directory.'/response.json', ['status' => 'missing', 'note' => 'No recorded event for this scenario yet.']);

            return;
        }

        if ($mode === 'final' && (! $event->hasCompleteEvidence() || ! $event->isSuccessfulHttp())) {
            throw new HttpException(422, 'Final export blocked: incomplete or unsuccessful evidence for '.$event->scenario_key);
        }

        $requestBody = $this->sanitizer->sanitize($event->request_body_encrypted ?? []);
        $responseBody = $this->sanitizer->sanitize($event->response_body_encrypted ?? []);

        if ($mode === 'final' && ($requestBody === [] || $requestBody === null || $responseBody === [] || $responseBody === null)) {
            throw new HttpException(422, 'Final export blocked: empty required JSON for '.$event->scenario_key);
        }

        $wrapper = [
            'schema_version' => self::SCHEMA_VERSION,
            'event_id' => $event->id,
            'scenario_key' => $event->scenario_key,
            'test_case' => $event->test_case_key,
            'environment' => $event->environment,
            'endpoint' => $event->endpoint,
            'method' => $event->http_method,
            'executed_at' => $event->evidence_recorded_at?->toIso8601String() ?? $event->created_at?->toIso8601String(),
            'http_status' => $event->http_status,
            'fedex_transaction_id' => $event->fedex_transaction_id,
            'request' => [
                'headers' => $this->sanitizer->sanitize($event->request_headers_encrypted ?? []),
                'body' => $requestBody,
            ],
            'response' => [
                'headers' => $this->sanitizer->sanitize($event->response_headers_encrypted ?? []),
                'body' => $responseBody,
            ],
        ];

        $this->writeJson($directory.'/request.json', ['request' => $wrapper['request'], 'meta' => array_diff_key($wrapper, ['request' => true, 'response' => true])]);
        $this->writeJson($directory.'/response.json', ['response' => $wrapper['response'], 'meta' => array_diff_key($wrapper, ['request' => true, 'response' => true])]);
    }

    private function copyArtifacts(string $directory, CarrierApiEvent $event, string $role): void
    {
        File::ensureDirectoryExists($directory);

        $artifacts = FedExValidationArtifact::query()
            ->where('store_id', $event->store_id)
            ->where('carrier_account_id', $event->carrier_account_id)
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', $role)
            ->orderBy('package_sequence')
            ->get();

        foreach ($artifacts as $artifact) {
            $path = $artifact->absolutePath();
            if ($path === null || ! is_file($path)) {
                continue;
            }

            if (! filled($artifact->sha256) || hash_file('sha256', $path) !== (string) $artifact->sha256) {
                continue;
            }

            $target = $directory.'/package-'.($artifact->package_sequence ?? 1).'.'.pathinfo($path, PATHINFO_EXTENSION);
            File::copy($path, $target);
        }
    }

    private function exportRequiredDocuments(string $directory, Store $store, CarrierAccount $account): void
    {
        $map = [
            FedExValidationArtifact::DOC_COVER_SHEET => 'Integrator_Validation_Cover_Sheet.pdf',
            FedExValidationArtifact::DOC_PIW => 'Product_Information_Worksheet.pdf',
            FedExValidationArtifact::DOC_CUSTOMER_SCREENSHOTS => 'Customer_Facing_Screenshots.pdf',
        ];

        foreach ($map as $type => $filename) {
            $artifact = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('artifact_type', $type)
                ->latest('id')
                ->first();

            if ($artifact?->absolutePath() && is_file($artifact->absolutePath())) {
                File::copy($artifact->absolutePath(), $directory.'/'.$filename);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $preflight
     * @return array<string, mixed>
     */
    private function buildEvidenceIndex(Store $store, CarrierAccount $account, string $region, string $environment, array $preflight): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'project' => config('app.name'),
            'store_id' => $store->id,
            'account_last4' => $account->maskedAccountNumber(),
            'region' => strtoupper($region),
            'environment' => $environment,
            'generated_at' => now()->toIso8601String(),
            'selected_scopes' => $preflight['selected_scopes'] ?? $this->scopeService->resolveRequiredScopes(),
            'requirements' => $preflight['checks'] ?? [],
            'canonical_event_ids' => $preflight['canonical_event_ids'] ?? [],
            'ready' => $preflight['ready'] ?? false,
        ];
    }

    /**
     * @return list<string>
     */
    private function knownSecretsForScan(CarrierAccount $account, string $environment): array
    {
        $credentials = $account->credentials();
        $secrets = array_filter([
            $credentials['customer_password'] ?? null,
            $credentials['customer_key'] ?? null,
            $this->config->parentClientSecret($environment),
        ]);

        return array_values(array_unique(array_filter(
            $secrets,
            fn (?string $secret): bool => is_string($secret) && strlen($secret) >= 12,
        )));
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function readme(Store $store, CarrierAccount $account, string $environment, string $region, string $mode, array $preflight): string
    {
        $lines = [
            '# FedEx Integrator Validation Evidence Bundle',
            '',
        ];

        if ($mode === 'diagnostic' || ! ($preflight['ready'] ?? false)) {
            $lines[] = 'INCOMPLETE — NOT READY FOR FEDEX SUBMISSION';
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            'Mode: '.$mode,
            'Store: '.$store->name.' (#'.$store->id.')',
            'Environment: '.$environment,
            'Region: '.$region,
            'Carrier account: '.$account->maskedAccountNumber(),
            '',
            'This bundle contains sanitized complete request/response JSON where recorded, plus private artifact copies.',
            'Secrets, tokens, child keys, PINs, and raw label Base64 are excluded from JSON files.',
            '',
            'Generated at: '.now()->toIso8601String(),
        ]);

        return implode("\n", $lines);
    }

    private function writeJson(string $path, mixed $payload): void
    {
        $this->writeFile($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function writeFile(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }
}
