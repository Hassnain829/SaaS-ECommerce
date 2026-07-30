<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\FedExValidationSubmissionSnapshot;
use App\Models\Store;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExBrandComplianceService;
use App\Services\Carriers\FedEx\Validation\FedExCapabilityEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExGlobalShipCaseCatalog;
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
        private readonly FedExComprehensiveRateEvidenceService $comprehensiveRateEvidence,
        private readonly FedExShipEvidenceService $shipEvidenceService,
        private readonly \App\Services\Carriers\FedEx\Connection\FedExEulaService $eulaService,
        private readonly FedExBrandComplianceService $brandCompliance,
        private readonly FedExCapabilityEvidenceService $capabilityEvidence,
        private readonly FedExFeedbackMatrixBuilder $feedbackMatrix,
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

    public function exportFinalSubmission(
        Store $store,
        CarrierAccount $account,
        FedExValidationSubmissionSnapshot $snapshot,
        string $caseReference,
        string $timestamp,
    ): string {
        abort_unless($snapshot->isReady(), 422, 'Snapshot is not ready for final export.');

        $manifest = is_array($snapshot->snapshot_manifest_json) ? $snapshot->snapshot_manifest_json : [];
        $preflight = [
            'ready' => true,
            'checks' => $manifest['checks'] ?? [],
            'blockers' => $manifest['blockers'] ?? [],
            'selected_scopes' => $manifest['selected_scopes'] ?? $this->scopeService->resolveRequiredScopes(),
            'canonical_event_ids' => is_array($snapshot->evidence_ids_json) ? $snapshot->evidence_ids_json : [],
            'preflight_hash' => $snapshot->preflight_hash,
            'snapshot_id' => $snapshot->id,
        ];

        $environment = $this->config->environment($account->environment ?? CarrierAccount::ENVIRONMENT_SANDBOX);
        $root = 'FedEx_Validation_Submission_'.$caseReference.'_'.$timestamp;
        $bundleDir = storage_path("app/fedex-validation/{$store->id}/{$timestamp}/{$root}");

        foreach ([
            '00_submission_documents',
            '01_registration_mfa',
            '02_address_validation',
            '03_service_availability',
            '04_comprehensive_rates',
            '05a_ship_us01_pdf/generated',
            '05a_ship_us01_pdf/printed_scans',
            '05_ship_us02_zplii/generated',
            '05_ship_us02_zplii/printed_scans',
            '05c_ship_us03_pdf/generated',
            '05c_ship_us03_pdf/printed_scans',
            '06_ship_us04_png/generated',
            '06_ship_us04_png/printed_scans',
            '07_ship_us05_pdf_mps/generated',
            '07_ship_us05_pdf_mps/printed_scans',
            '07a_ship_us06_pdf/generated',
            '07a_ship_us06_pdf/printed_scans',
            '07b_ship_us07_pdf/generated',
            '07b_ship_us07_pdf/printed_scans',
            '07c_ship_us08_zplii/generated',
            '07c_ship_us08_zplii/printed_scans',
            '07c_ship_us08_zplii/documents',
            '07d_ship_us09_image_pdf/generated',
            '07d_ship_us09_image_pdf/printed_scans',
            '07d_ship_us09_image_pdf/uploads/letterhead',
            '07d_ship_us09_image_pdf/uploads/signature',
            '07e_ship_us09_document_pdf/generated',
            '07e_ship_us09_document_pdf/printed_scans',
            '07e_ship_us09_document_pdf/uploads',
            '08_ship_us10_ipd/01_create_consolidation',
            '08_ship_us10_ipd/02_add_shipment_1',
            '08_ship_us10_ipd/03_add_shipment_2',
            '08_ship_us10_ipd/04_add_shipment_3',
            '08_ship_us10_ipd/05_add_shipment_4',
            '08_ship_us10_ipd/06_add_shipment_5',
            '08_ship_us10_ipd/07_add_shipment_6',
            '08_ship_us10_ipd/08_confirm',
            '08_ship_us10_ipd/09_confirm_results',
            '08_ship_us10_ipd/labels',
            '08_ship_us10_ipd/documents',
            '08_global_territories/ca',
            '08_global_territories/scope_notes',
            '09_tracking',
            '10_branding_and_capabilities/screenshots',
            '11_written_confirmations',
            '12_customer_screenshots',
        ] as $dir) {
            File::ensureDirectoryExists($bundleDir.'/'.$dir);
        }

        $this->exportFinalSubmissionDocuments($bundleDir.'/00_submission_documents', $store, $account);
        $this->exportRegistrationScenarios($bundleDir.'/01_registration_mfa', $store, $account, $preflight, false);
        $this->exportScenarioEvent($bundleDir.'/02_address_validation', $this->resolveCheckEvent($store, $account, $preflight, 'address_validation', false), false, 'final');
        $this->exportScenarioEvent($bundleDir.'/03_service_availability', $this->resolveCheckEvent($store, $account, $preflight, 'service_availability', false), false, 'final');
        $this->exportComprehensiveRates($bundleDir.'/04_comprehensive_rates', $store, $account, $preflight, 'final');
        $this->exportLockedShipScenario($bundleDir.'/05a_ship_us01_pdf', $store, $account, 'IntegratorUS01', $preflight, false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/05_ship_us02_zplii', $store, $account, 'IntegratorUS02', $preflight, false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/05c_ship_us03_pdf', $store, $account, 'IntegratorUS03', $preflight, false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/06_ship_us04_png', $store, $account, 'IntegratorUS04', $preflight, false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/07_ship_us05_pdf_mps', $store, $account, 'IntegratorUS05', $preflight, false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/07a_ship_us06_pdf', $store, $account, 'IntegratorUS06', $preflight, false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/07b_ship_us07_pdf', $store, $account, 'IntegratorUS07', $preflight, false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/07c_ship_us08_zplii', $store, $account, 'IntegratorUS08', $preflight, false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/07d_ship_us09_image_pdf', $store, $account, 'IntegratorUS09_IMAGE', $preflight, false, 'final');
        $this->exportUs09Upload($bundleDir.'/07d_ship_us09_image_pdf/uploads/letterhead', $store, $account, 'upload_us09_image_letterhead', false, 'final');
        $this->exportUs09Upload($bundleDir.'/07d_ship_us09_image_pdf/uploads/signature', $store, $account, 'upload_us09_image_signature', false, 'final');
        $this->exportLockedShipScenario($bundleDir.'/07e_ship_us09_document_pdf', $store, $account, 'IntegratorUS09_DOCUMENT', $preflight, false, 'final');
        $this->exportUs09Upload($bundleDir.'/07e_ship_us09_document_pdf/uploads', $store, $account, 'upload_us09_document', false, 'final');
        $this->exportUs10Consolidation($bundleDir.'/08_ship_us10_ipd', $store, $account, $preflight, false, 'final');
        $this->exportGlobalCanadaTerritories($bundleDir.'/08_global_territories/ca', $store, $account, $preflight, 'final');
        $this->writeJson($bundleDir.'/08_global_territories/scope_notes/americas_scope.json', [
            'scope' => 'Americas — US and Canada only',
            'lac' => 'not_applicable',
            'amea' => 'not_applicable',
            'europe_etd' => 'not_applicable',
            'us08_freight_ltl' => 'excluded',
            'us10_consolidation_ipd' => FedExValidationScenarioCatalog::isConsolidationEnabled() ? 'in_scope' : 'excluded',
            'note' => 'LAC, AMEA, and Europe+ETD excluded per Integrator Validation Cover Sheet.',
            'us08_exclusion_note' => FedExValidationScenarioCatalog::us08ExclusionNote(),
            'us10_exclusion_note' => FedExValidationScenarioCatalog::us10ExclusionNote(),
        ]);

        if ($this->scopeService->trackingRequired($preflight['selected_scopes'] ?? null)) {
            $this->exportScenarioEvent($bundleDir.'/09_tracking', $this->resolveCheckEvent($store, $account, $preflight, 'tracking', false), false, 'final');
            $this->copyTrackingScreenshot($bundleDir.'/09_tracking', $store, $account, $preflight);
        }

        $this->exportBrandingAndCapabilities($bundleDir.'/10_branding_and_capabilities', $store, $account);
        $this->exportWrittenConfirmations($bundleDir.'/11_written_confirmations', $store, $account);
        $this->copyCustomerScreenshotsPdf($bundleDir.'/12_customer_screenshots', $store, $account);

        $this->verifyFinalExportIntegrity($bundleDir, $preflight);

        $matrixRows = $this->feedbackMatrix->build($store, $account, $preflight);
        $this->writeJson($bundleDir.'/FEDEX_FEEDBACK_RESPONSE_MATRIX.json', $matrixRows);
        $this->writeFile($bundleDir.'/FEDEX_FEEDBACK_RESPONSE_MATRIX.csv', $this->feedbackMatrixCsv($matrixRows));
        $this->writeJson($bundleDir.'/FINAL_PREFLIGHT_REPORT.json', $preflight);
        $this->writeFile($bundleDir.'/README_FINAL_SUBMISSION.txt', $this->finalSubmissionReadme($store, $account, $environment, $snapshot, $preflight));
        $this->writeFile($bundleDir.'/FEDEX_SUBMISSION_EMAIL_DRAFT.txt', $this->submissionEmailDraft($caseReference, $snapshot, $root));

        $fileManifest = $this->buildFileManifest($bundleDir, $root);
        $this->writeJson($bundleDir.'/FILE_MANIFEST_SHA256.json', $fileManifest);

        $knownSecrets = $this->knownSecretsForScan($account, $environment);
        $secretScan = $this->sanitizer->scanStagingDirectory($bundleDir, $knownSecrets);
        if ($secretScan !== []) {
            throw new HttpException(422, 'Final export blocked: secret scan failed.');
        }

        $zipFilename = $root.'.zip';
        $zipPath = storage_path("app/fedex-validation/{$store->id}/{$timestamp}/{$zipFilename}");
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (File::allFiles($bundleDir) as $file) {
            $zip->addFile($file->getPathname(), $root.'/'.str_replace('\\', '/', $file->getRelativePathname()));
        }

        $zip->close();
        $this->verifyZipAgainstManifest($zipPath, $fileManifest, $root);

        return $zipPath;
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
        File::ensureDirectoryExists($bundleDir.'/04_comprehensive_rates');
        File::ensureDirectoryExists($bundleDir.'/05a_ship_us01_pdf/generated');
        File::ensureDirectoryExists($bundleDir.'/05a_ship_us01_pdf/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/05_ship_us02_zplii/generated');
        File::ensureDirectoryExists($bundleDir.'/05_ship_us02_zplii/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/05c_ship_us03_pdf/generated');
        File::ensureDirectoryExists($bundleDir.'/05c_ship_us03_pdf/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/06_ship_us04_png/generated');
        File::ensureDirectoryExists($bundleDir.'/06_ship_us04_png/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/07_ship_us05_pdf_mps/generated');
        File::ensureDirectoryExists($bundleDir.'/07_ship_us05_pdf_mps/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/07a_ship_us06_pdf/generated');
        File::ensureDirectoryExists($bundleDir.'/07a_ship_us06_pdf/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/07b_ship_us07_pdf/generated');
        File::ensureDirectoryExists($bundleDir.'/07b_ship_us07_pdf/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/07c_ship_us08_zplii/generated');
        File::ensureDirectoryExists($bundleDir.'/07c_ship_us08_zplii/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/07c_ship_us08_zplii/documents');
        File::ensureDirectoryExists($bundleDir.'/07d_ship_us09_image_pdf/generated');
        File::ensureDirectoryExists($bundleDir.'/07d_ship_us09_image_pdf/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/07d_ship_us09_image_pdf/uploads/letterhead');
        File::ensureDirectoryExists($bundleDir.'/07d_ship_us09_image_pdf/uploads/signature');
        File::ensureDirectoryExists($bundleDir.'/07e_ship_us09_document_pdf/generated');
        File::ensureDirectoryExists($bundleDir.'/07e_ship_us09_document_pdf/printed_scans');
        File::ensureDirectoryExists($bundleDir.'/07e_ship_us09_document_pdf/uploads');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/01_create_consolidation');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/02_add_shipment_1');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/03_add_shipment_2');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/04_add_shipment_3');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/05_add_shipment_4');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/06_add_shipment_5');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/07_add_shipment_6');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/08_confirm');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/09_confirm_results');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/labels');
        File::ensureDirectoryExists($bundleDir.'/08_ship_us10_ipd/documents');
        File::ensureDirectoryExists($bundleDir.'/08_global_territories/ca');
        File::ensureDirectoryExists($bundleDir.'/08_global_territories/scope_notes');
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
        $this->exportComprehensiveRates($bundleDir.'/04_comprehensive_rates', $store, $account, $preflight, $mode);
        $this->exportLockedShipScenario($bundleDir.'/05a_ship_us01_pdf', $store, $account, 'IntegratorUS01', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/05_ship_us02_zplii', $store, $account, 'IntegratorUS02', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/05c_ship_us03_pdf', $store, $account, 'IntegratorUS03', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/06_ship_us04_png', $store, $account, 'IntegratorUS04', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/07_ship_us05_pdf_mps', $store, $account, 'IntegratorUS05', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/07a_ship_us06_pdf', $store, $account, 'IntegratorUS06', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/07b_ship_us07_pdf', $store, $account, 'IntegratorUS07', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/07c_ship_us08_zplii', $store, $account, 'IntegratorUS08', $preflight, $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/07d_ship_us09_image_pdf', $store, $account, 'IntegratorUS09_IMAGE', $preflight, $includeFailedAttempts, $mode);
        $this->exportUs09Upload($bundleDir.'/07d_ship_us09_image_pdf/uploads/letterhead', $store, $account, 'upload_us09_image_letterhead', $includeFailedAttempts, $mode);
        $this->exportUs09Upload($bundleDir.'/07d_ship_us09_image_pdf/uploads/signature', $store, $account, 'upload_us09_image_signature', $includeFailedAttempts, $mode);
        $this->exportLockedShipScenario($bundleDir.'/07e_ship_us09_document_pdf', $store, $account, 'IntegratorUS09_DOCUMENT', $preflight, $includeFailedAttempts, $mode);
        $this->exportUs09Upload($bundleDir.'/07e_ship_us09_document_pdf/uploads', $store, $account, 'upload_us09_document', $includeFailedAttempts, $mode);
        $this->exportUs10Consolidation($bundleDir.'/08_ship_us10_ipd', $store, $account, $preflight, $includeFailedAttempts, $mode);
        $this->exportGlobalCanadaTerritories($bundleDir.'/08_global_territories/ca', $store, $account, $preflight, $mode);
        $this->writeJson($bundleDir.'/08_global_territories/scope_notes/americas_scope.json', [
            'scope' => 'Americas — US and Canada only',
            'lac' => 'not_applicable',
            'amea' => 'not_applicable',
            'europe_etd' => 'not_applicable',
            'us08_freight_ltl' => 'excluded',
            'us10_consolidation_ipd' => FedExValidationScenarioCatalog::isConsolidationEnabled() ? 'in_scope' : 'excluded',
            'note' => 'LAC, AMEA, and Europe+ETD excluded per Integrator Validation Cover Sheet.',
            'us08_exclusion_note' => FedExValidationScenarioCatalog::us08ExclusionNote(),
            'us10_exclusion_note' => FedExValidationScenarioCatalog::us10ExclusionNote(),
        ]);

        if ($mode === 'final') {
            $this->verifyFinalExportIntegrity($bundleDir, $preflight);
        }

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
            'comprehensive_rate_transaction' => $this->comprehensiveRateEvidence->canonicalEvent($store, $account),
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
                : ($scenarioKey === 'registration_address_validation'
                    ? $this->evidenceQuery->canonicalRegistrationAddressEvent($store, $account)
                    : $this->evidenceQuery->canonicalEvent($store, $account, $scenarioKey, mfaMethod: $meta['mfa_method']));

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
     * Export US09 Trade Documents upload event (redacted; no binary).
     */
    private function exportUs09Upload(
        string $directory,
        Store $store,
        CarrierAccount $account,
        string $uploadScenarioKey,
        bool $includeFailed,
        string $mode,
    ): void {
        File::ensureDirectoryExists($directory);
        $event = $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, $uploadScenarioKey);
        if ($event === null && $includeFailed) {
            $event = CarrierApiEvent::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('scenario_key', $uploadScenarioKey)
                ->latest('id')
                ->first();
        }

        if ($event === null) {
            $this->writeJson($directory.'/upload_pending.json', [
                'scenario_key' => $uploadScenarioKey,
                'status' => 'not_tested',
                'note' => 'US09 upload deferred until final evidence run.',
            ]);

            return;
        }

        $this->exportScenarioEvent($directory, $event, $includeFailed || $mode !== 'final', $mode);
    }

    /**
     * Export IntegratorUS10 Consolidation evidence into separate step folders.
     *
     * @param  array<string, mixed>  $preflight
     */
    private function exportUs10Consolidation(
        string $directory,
        Store $store,
        CarrierAccount $account,
        array $preflight,
        bool $includeFailed,
        string $mode,
    ): void {
        File::ensureDirectoryExists($directory.'/labels');
        File::ensureDirectoryExists($directory.'/documents');

        if (! FedExValidationScenarioCatalog::isConsolidationEnabled()) {
            $this->writeJson($directory.'/README.json', [
                'case' => 'IntegratorUS10',
                'api_family' => 'consolidation',
                'status' => 'excluded',
                'note' => FedExValidationScenarioCatalog::us10ExclusionNote(),
                'historical_events' => 'Failed historical IntegratorUS10 events remain stored for audit and are not required for final export.',
            ]);
            $this->writeJson($directory.'/excluded.json', [
                'test_case_key' => 'IntegratorUS10',
                'status' => 'excluded',
                'reason' => FedExValidationScenarioCatalog::us10ExclusionNote(),
            ]);

            return;
        }

        foreach (FedExValidationScenarioCatalog::lockedConsolidationScenarios() as $testCaseKey => $meta) {
            $exportFolder = (string) ($meta['export_folder'] ?? '');
            $relative = str_starts_with($exportFolder, '08_ship_us10_ipd/')
                ? substr($exportFolder, strlen('08_ship_us10_ipd/'))
                : (string) ($meta['scenario_key'] ?? $testCaseKey);
            $stepDir = $directory.'/'.$relative;
            File::ensureDirectoryExists($stepDir);

            $scenarioKey = (string) $meta['scenario_key'];
            $eventCheckKey = $scenarioKey.'_event';
            $eventId = collect($preflight['checks'] ?? [])->firstWhere('key', $eventCheckKey)['event_id'] ?? null;
            $event = $eventId
                ? $this->evidenceQuery->eventById($store, $account, (int) $eventId)
                : $this->evidenceQuery->canonicalSuccessfulEvent($store, $account, $scenarioKey, $testCaseKey);

            if ($event === null && $includeFailed) {
                $event = CarrierApiEvent::query()
                    ->where('store_id', $store->id)
                    ->where('carrier_account_id', $account->id)
                    ->where('scenario_key', $scenarioKey)
                    ->latest('id')
                    ->first();
            }

            if ($event === null) {
                $this->writeJson($stepDir.'/pending.json', [
                    'test_case_key' => $testCaseKey,
                    'scenario_key' => $scenarioKey,
                    'status' => 'not_tested',
                    'note' => 'US10 Consolidation step deferred until the final evidence run.',
                ]);

                continue;
            }

            $this->exportScenarioEvent($stepDir, $event, $includeFailed || $mode !== 'final', $mode);

            $sanitizedRequest = $this->sanitizer->sanitize(
                is_array($event->request_body_encrypted) ? $event->request_body_encrypted : []
            );
            $exportValidation = app(FedExConsolidationEvidenceRules::class)->validateSanitizedExport(
                is_array($sanitizedRequest) ? $sanitizedRequest : [],
                (string) ($meta['operation'] ?? 'create'),
            );

            if ($mode === 'final' && ! $exportValidation['valid']) {
                throw new HttpException(
                    422,
                    'Final export blocked: '.$testCaseKey.' sanitized consolidation request failed validation ('.implode(', ', $exportValidation['reasons']).').'
                );
            }

            $this->writeJson($stepDir.'/export_validation.json', $exportValidation);
        }

        $confirmResultsEvent = $this->evidenceQuery->canonicalSuccessfulEvent(
            $store,
            $account,
            'consolidation_us10_confirm_results',
            'IntegratorUS10_CONFIRM_RESULTS',
        );

        $expectedLabelCount = count(app(FedExConsolidationFixtureService::class)->addShipmentKeys());
        $labelsCopied = 0;
        $cciCopied = false;

        if ($confirmResultsEvent !== null) {
            $labelsCopied = $this->copyUs10ChildLabels($directory.'/labels', $confirmResultsEvent);
            $cciCopied = $this->copyUs10ConsolidatedCommercialInvoice($directory.'/documents', $confirmResultsEvent);
        }

        if ($mode === 'final') {
            if ($labelsCopied < $expectedLabelCount) {
                throw new HttpException(
                    422,
                    'Final export blocked: IntegratorUS10 expected '.$expectedLabelCount.' child labels under 08_ship_us10_ipd/labels/; copied '.$labelsCopied.'.'
                );
            }
            if (! $cciCopied) {
                throw new HttpException(
                    422,
                    'Final export blocked: IntegratorUS10 Consolidated Commercial Invoice was not copied into 08_ship_us10_ipd/documents/.'
                );
            }
        }

        $this->writeJson($directory.'/README.json', [
            'case' => 'IntegratorUS10',
            'api_family' => 'consolidation',
            'note' => 'Each create/add/confirm/results step is an independent evidence requirement. Labels and CCI belong under labels/ and documents/.',
            'child_labels_copied' => $labelsCopied,
            'expected_child_labels' => $expectedLabelCount,
            'cci_copied' => $cciCopied,
        ]);
    }

    private function copyUs10ChildLabels(string $directory, CarrierApiEvent $event): int
    {
        File::ensureDirectoryExists($directory);

        $artifacts = FedExValidationArtifact::query()
            ->where('store_id', $event->store_id)
            ->where('carrier_account_id', $event->carrier_account_id)
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_GENERATED_LABEL)
            ->where('test_case_key', 'IntegratorUS10_CONFIRM_RESULTS')
            ->orderBy('package_sequence')
            ->get();

        $copied = 0;
        foreach ($artifacts as $artifact) {
            $path = $artifact->absolutePath();
            if ($path === null || ! is_file($path) || filesize($path) <= 0) {
                continue;
            }
            if (! filled($artifact->sha256) || hash_file('sha256', $path) !== (string) $artifact->sha256) {
                continue;
            }
            if (! FedExLabelArtifactValidator::isValid($path, strtoupper((string) ($artifact->label_format ?: 'PDF')))) {
                continue;
            }

            $sequence = (int) ($artifact->package_sequence ?: ($copied + 1));
            $target = $directory.'/child-label-'.$sequence.'.'.pathinfo($path, PATHINFO_EXTENSION);
            File::copy($path, $target);
            $copied++;
        }

        return $copied;
    }

    private function copyUs10ConsolidatedCommercialInvoice(string $directory, CarrierApiEvent $event): bool
    {
        File::ensureDirectoryExists($directory);

        $artifact = FedExValidationArtifact::query()
            ->where('store_id', $event->store_id)
            ->where('carrier_account_id', $event->carrier_account_id)
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
            ->where('artifact_type', 'consolidation_commercial_invoice')
            ->latest('id')
            ->first();

        $path = $artifact?->absolutePath();
        if ($path === null || ! is_file($path) || filesize($path) <= 0) {
            return false;
        }
        if (! filled($artifact->sha256) || hash_file('sha256', $path) !== (string) $artifact->sha256) {
            return false;
        }
        if (! str_starts_with((string) file_get_contents($path), '%PDF')) {
            return false;
        }

        File::copy($path, $directory.'/Consolidated_Commercial_Invoice.pdf');

        return true;
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
        if ($testCaseKey === 'IntegratorUS08' && ! FedExValidationScenarioCatalog::isShipScenarioEnabled('IntegratorUS08')) {
            File::ensureDirectoryExists($directory.'/documents');
            $this->writeJson($directory.'/EXCLUDED.json', [
                'test_case_key' => 'IntegratorUS08',
                'api_family' => 'freight_ltl',
                'status' => 'excluded',
                'required_for_readiness' => false,
                'reason' => FedExValidationScenarioCatalog::us08ExclusionNote(),
                'historical_events' => 'Failed historical IntegratorUS08 events remain stored for audit and are not required for final export.',
            ]);
            // Keep DISABLED.json alias for older export consumers.
            $this->writeJson($directory.'/DISABLED.json', [
                'test_case_key' => 'IntegratorUS08',
                'api_family' => 'freight_ltl',
                'status' => 'excluded',
                'required_for_readiness' => false,
                'reason' => FedExValidationScenarioCatalog::us08ExclusionNote(),
            ]);

            return;
        }

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
            $sanitizedRequest = $this->sanitizer->sanitize(
                is_array($event->request_body_encrypted) ? $event->request_body_encrypted : []
            );
            $exportValidation = app(FedExShipEvidenceRules::class)->validateSanitizedExport(
                is_array($sanitizedRequest) ? $sanitizedRequest : [],
                $testCaseKey,
            );

            if ($mode === 'final' && ! $exportValidation['valid']) {
                throw new HttpException(
                    422,
                    'Final export blocked: '.$testCaseKey.' sanitized ship request failed validation ('.implode(', ', $exportValidation['reasons']).').'
                );
            }

            $this->writeJson(
                $directory.'/result_summary.json',
                $this->shipEvidenceService->exportResultSummary($event, $testCaseKey, $mode !== 'final'),
            );
            $this->copyArtifacts($directory.'/generated', $event, FedExValidationArtifact::ROLE_GENERATED_LABEL);
            $this->copyArtifacts($directory.'/printed_scans', $event, FedExValidationArtifact::ROLE_PRINTED_SCAN);

            if ($testCaseKey === 'IntegratorUS08') {
                $this->copyUs08ValidationDocuments($directory.'/documents', $event, $mode);
            }

            if (in_array($testCaseKey, ['IntegratorUS09_IMAGE', 'IntegratorUS09_DOCUMENT'], true)) {
                $this->copyUs09ValidationDocuments($directory.'/documents', $event, $mode, $testCaseKey);
            }
        } elseif ($testCaseKey === 'IntegratorUS08') {
            File::ensureDirectoryExists($directory.'/documents');
        } elseif (in_array($testCaseKey, ['IntegratorUS09_IMAGE', 'IntegratorUS09_DOCUMENT'], true)) {
            File::ensureDirectoryExists($directory.'/documents');
        }
    }

    /**
     * Copy commercial invoice / shipping documents for IntegratorUS09.
     *
     * IMAGE: FedEx returns a generated Commercial Invoice PDF on the ship response.
     * DOCUMENT: the Commercial Invoice is the ETD-uploaded PDF; ship often returns a label only.
     */
    private function copyUs09ValidationDocuments(
        string $directory,
        CarrierApiEvent $event,
        string $mode,
        string $testCaseKey,
    ): void {
        File::ensureDirectoryExists($directory);

        $artifacts = FedExValidationArtifact::query()
            ->where('store_id', $event->store_id)
            ->where('carrier_account_id', $event->carrier_account_id)
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
            ->where('test_case_key', $testCaseKey)
            ->orderBy('id')
            ->get();

        $copiedCommercialInvoice = false;
        $index = 0;

        foreach ($artifacts as $artifact) {
            $path = $artifact->absolutePath();
            if (! $this->isValidUs09CommercialInvoicePdf($path, $artifact->sha256)) {
                continue;
            }

            $index++;
            if ($artifact->artifact_type === 'commercial_invoice') {
                File::copy($path, $directory.'/Commercial_Invoice.pdf');
                $copiedCommercialInvoice = true;
            } else {
                File::copy($path, $directory.'/shipping-document-'.$index.'.pdf');
            }
        }

        if (! $copiedCommercialInvoice && $testCaseKey === 'IntegratorUS09_DOCUMENT') {
            $copiedCommercialInvoice = $this->copyUs09DocumentCaseCommercialInvoice(
                $directory,
                $event,
                $testCaseKey,
            );
        }

        if ($mode === 'final' && ! $copiedCommercialInvoice) {
            throw new HttpException(
                422,
                'Final export blocked: '.$testCaseKey.' Commercial Invoice was not copied into the submission documents directory.'
            );
        }
    }

    /**
     * For ETD document mode, copy the uploaded Commercial Invoice PDF when ship did not return one.
     */
    private function copyUs09DocumentCaseCommercialInvoice(
        string $directory,
        CarrierApiEvent $event,
        string $testCaseKey,
    ): bool {
        $stored = FedExValidationArtifact::query()
            ->where('store_id', $event->store_id)
            ->where('carrier_account_id', $event->carrier_account_id)
            ->where('test_case_key', $testCaseKey)
            ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
            ->where('artifact_type', 'commercial_invoice')
            ->orderByDesc('id')
            ->get();

        foreach ($stored as $artifact) {
            $path = $artifact->absolutePath();
            if (! $this->isValidUs09CommercialInvoicePdf($path, $artifact->sha256)) {
                continue;
            }

            File::copy($path, $directory.'/Commercial_Invoice.pdf');

            return true;
        }

        $fixturePath = app(FedExUs09EtdFixtureService::class)->documentCommercialInvoiceAbsolutePath();
        if ($fixturePath === null || ! $this->isValidUs09CommercialInvoicePdf($fixturePath, null)) {
            return false;
        }

        File::copy($fixturePath, $directory.'/Commercial_Invoice.pdf');

        return true;
    }

    private function isValidUs09CommercialInvoicePdf(?string $path, mixed $expectedSha256): bool
    {
        if ($path === null || ! is_file($path) || filesize($path) <= 0) {
            return false;
        }

        if (filled($expectedSha256) && hash_file('sha256', $path) !== (string) $expectedSha256) {
            return false;
        }

        return str_starts_with((string) file_get_contents($path), '%PDF');
    }

    /**
     * Copy Freight validation documents (BOL / commercial invoice) for IntegratorUS08.
     */
    private function copyUs08ValidationDocuments(string $directory, CarrierApiEvent $event, string $mode): void
    {
        File::ensureDirectoryExists($directory);

        $filenameMap = [
            'freight_bill_of_lading' => 'Straight_Bill_of_Lading.pdf',
            'freight_commercial_invoice' => 'Commercial_Invoice.pdf',
        ];

        $copied = [];

        $artifacts = FedExValidationArtifact::query()
            ->where('store_id', $event->store_id)
            ->where('carrier_account_id', $event->carrier_account_id)
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
            ->whereIn('artifact_type', array_keys($filenameMap))
            ->orderBy('id')
            ->get();

        foreach ($artifacts as $artifact) {
            $filename = $filenameMap[$artifact->artifact_type] ?? null;
            if ($filename === null) {
                continue;
            }

            $path = $artifact->absolutePath();
            if ($path === null || ! is_file($path) || filesize($path) <= 0) {
                continue;
            }

            if (! filled($artifact->sha256) || hash_file('sha256', $path) !== (string) $artifact->sha256) {
                continue;
            }

            File::copy($path, $directory.'/'.$filename);
            $copied[$artifact->artifact_type] = $directory.'/'.$filename;
        }

        if ($mode === 'final' && ! isset($copied['freight_bill_of_lading'])) {
            throw new HttpException(
                422,
                'Final export blocked: IntegratorUS08 Straight Bill of Lading was not copied into the submission documents directory.'
            );
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

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function exportComprehensiveRates(string $directory, Store $store, CarrierAccount $account, array $preflight, string $mode): void
    {
        File::ensureDirectoryExists($directory.'/screenshots');

        $eventId = collect($preflight['checks'] ?? [])->firstWhere('key', 'comprehensive_rate_transaction')['event_id'] ?? null;
        $canonical = $eventId
            ? $this->evidenceQuery->eventById($store, $account, (int) $eventId)
            : $this->comprehensiveRateEvidence->canonicalEvent($store, $account);

        $exportEvent = $canonical;
        if ($exportEvent === null) {
            $exportEvent = $this->comprehensiveRateEvidence->latestAccessBlocker($store, $account);
        }

        $this->exportScenarioEvent($directory, $exportEvent, true, $mode);
        $this->writeJson(
            $directory.'/result_summary.json',
            $this->comprehensiveRateEvidence->exportResultSummary($exportEvent, $exportEvent !== null && ! $exportEvent->isSuccessfulHttp()),
        );

        if ($canonical !== null) {
            $artifact = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('carrier_api_event_id', $canonical->id)
                ->where('artifact_role', FedExValidationArtifact::ROLE_COMPREHENSIVE_RATE_SCREENSHOT)
                ->where('artifact_type', FedExValidationArtifact::TYPE_COMPREHENSIVE_RATE_RESULT_UI)
                ->latest('id')
                ->first();

            $path = $artifact?->absolutePath();
            if ($path !== null && is_file($path) && filled($artifact->sha256) && hash_file('sha256', $path) === (string) $artifact->sha256) {
                File::copy($path, $directory.'/screenshots/01_customer_rate_result.'.pathinfo($path, PATHINFO_EXTENSION));
            }
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

        if ($mode === 'final' && ! $this->evidenceQuery->isFinalExportableEvent($event)) {
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
            'region' => $this->exportRegionLabel($region),
            'validation_regions' => ['US', 'CA'],
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
            'Region: '.$this->exportRegionLabel($region),
            'Carrier account: '.$account->maskedAccountNumber(),
            '',
            'This bundle contains sanitized complete request/response JSON where recorded, plus private artifact copies.',
            'Secrets, tokens, child keys, PINs, and raw label Base64 are excluded from JSON files.',
            '',
            FedExValidationScenarioCatalog::us08ExclusionNote(),
            FedExValidationScenarioCatalog::us10ExclusionNote(),
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

    private function exportFinalSubmissionDocuments(string $directory, Store $store, CarrierAccount $account): void
    {
        $map = [
            FedExValidationArtifact::DOC_COVER_SHEET => 'FedEx_Cover_Sheet.pdf',
            FedExValidationArtifact::DOC_PIW => 'Product_Integration_Worksheet.pdf',
            FedExValidationArtifact::DOC_CUSTOMER_SCREENSHOTS => 'Customer_Screenshots.pdf',
        ];

        foreach ($map as $type => $filename) {
            $artifact = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('artifact_type', $type)
                ->latest('id')
                ->first();

            $path = $artifact?->absolutePath();
            if ($path !== null && is_file($path)) {
                File::copy($path, $directory.'/'.$filename);
            } else {
                throw new HttpException(422, 'Final export blocked: missing required document '.$filename);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    /**
     * @param  array<string, mixed>  $preflight
     */
    private function exportGlobalCanadaTerritories(
        string $directory,
        Store $store,
        CarrierAccount $account,
        array $preflight,
        string $mode,
    ): void {
        foreach (FedExValidationScenarioCatalog::globalShipScenariosForRegion(FedExGlobalShipCaseCatalog::REGION_CA) as $testCaseKey => $meta) {
            $eventCheckKey = strtolower((string) ($meta['scenario_key'] ?? '')).'_event';
            $subdir = $directory.'/'.strtolower($testCaseKey);
            File::ensureDirectoryExists($subdir.'/generated');
            File::ensureDirectoryExists($subdir.'/printed_scans');

            $eventId = collect($preflight['checks'] ?? [])->firstWhere('key', $eventCheckKey)['event_id'] ?? null;
            $event = $eventId
                ? $this->evidenceQuery->eventById($store, $account, (int) $eventId)
                : ($this->evidenceQuery->canonicalGlobalShipRun(
                    $store,
                    $account,
                    FedExGlobalShipCaseCatalog::REGION_CA,
                    $testCaseKey,
                )['event'] ?? null);

            $allowIncomplete = $mode !== 'final';
            $this->exportScenarioEvent($subdir, $event, $allowIncomplete, $mode);
            if ($event !== null) {
                $this->writeJson($subdir.'/result_summary.json', $this->shipEvidenceService->exportResultSummary($event, $testCaseKey, false));
                $this->copyArtifacts($subdir.'/generated', $event, FedExValidationArtifact::ROLE_GENERATED_LABEL);
                $this->copyArtifacts($subdir.'/printed_scans', $event, FedExValidationArtifact::ROLE_PRINTED_SCAN);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function verifyFinalExportIntegrity(string $bundleDir, array $preflight): void
    {
        foreach ($preflight['checks'] ?? [] as $check) {
            if (($check['category'] ?? '') !== 'global_territories') {
                continue;
            }

            if (! ($check['required'] ?? false)) {
                continue;
            }

            if (($check['status'] ?? '') !== 'passed') {
                continue;
            }

            $this->verifyCanadaCheckExported($bundleDir, $check);
        }

        $this->verifyUs08BolExported($bundleDir, $preflight);
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function verifyUs08BolExported(string $bundleDir, array $preflight): void
    {
        if (! FedExValidationScenarioCatalog::isShipScenarioEnabled('IntegratorUS08')) {
            return;
        }

        $bolCheck = collect($preflight['checks'] ?? [])->firstWhere('key', 'ship_us08_zplii_bol');
        if ($bolCheck === null || ! ($bolCheck['required'] ?? false)) {
            return;
        }

        // Final submission expects the BOL whenever the US08 BOL check is required and has passed
        // (or the canonical US08 event check passed, which implies BOL evidence existed).
        $eventCheck = collect($preflight['checks'] ?? [])->firstWhere('key', 'ship_us08_zplii_event');
        $expectsBol = ($bolCheck['status'] ?? '') === 'passed'
            || ($eventCheck['status'] ?? '') === 'passed';

        if (! $expectsBol) {
            return;
        }

        $path = $bundleDir.'/07c_ship_us08_zplii/documents/Straight_Bill_of_Lading.pdf';
        if (! is_file($path) || filesize($path) <= 0) {
            throw new HttpException(
                422,
                'Final export integrity failed: missing Straight_Bill_of_Lading.pdf for IntegratorUS08.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $check
     */
    private function verifyCanadaCheckExported(string $bundleDir, array $check): void
    {
        $checkKey = (string) ($check['key'] ?? '');

        if ($checkKey === 'ca_regional_accounts_ready') {
            return;
        }

        $testCaseKey = $this->canadaTestCaseKeyFromCheck($checkKey);
        if ($testCaseKey === null) {
            return;
        }

        $caseDir = $bundleDir.'/08_global_territories/ca/'.strtolower($testCaseKey);

        if (str_ends_with($checkKey, '_event') || str_starts_with($checkKey, 'ca_transaction_representative_')) {
            foreach (['request.json', 'response.json', 'result_summary.json'] as $filename) {
                if (! is_file($caseDir.'/'.$filename)) {
                    throw new HttpException(422, 'Final export integrity failed: missing '.$filename.' for '.$checkKey);
                }
            }

            if (File::isEmptyDirectory($caseDir.'/generated')) {
                throw new HttpException(422, 'Final export integrity failed: missing generated labels for '.$checkKey);
            }

            return;
        }

        if (preg_match('/_scan_(\d+)$/', $checkKey, $matches) !== 1) {
            return;
        }

        $sequence = (int) $matches[1];
        $scanMatches = glob($caseDir.'/printed_scans/package-'.$sequence.'.*') ?: [];
        if ($scanMatches === []) {
            throw new HttpException(422, 'Final export integrity failed: missing printed scan package '.$sequence.' for '.$checkKey);
        }
    }

    private function canadaTestCaseKeyFromCheck(string $checkKey): ?string
    {
        if (preg_match('/^ship_ca(\d+)_(pdf|png|zplii)_/', $checkKey, $matches) === 1) {
            return 'IntegratorCA'.str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }

        if (str_starts_with($checkKey, 'ca_transaction_representative_')) {
            $format = strtoupper(str_replace('ca_transaction_representative_', '', $checkKey));

            return FedExGlobalShipCaseCatalog::transactionRepresentatives(FedExGlobalShipCaseCatalog::REGION_CA)[$format] ?? null;
        }

        return null;
    }

    private function exportRegionLabel(string $region): string
    {
        return strtoupper($region) === 'US' ? 'US + CA (Americas)' : strtoupper($region);
    }

    private function exportBrandingAndCapabilities(string $directory, Store $store, CarrierAccount $account): void
    {
        $metadata = $this->brandCompliance->logoMetadata();
        $this->writeJson($directory.'/logo_metadata.json', $metadata);
        $this->writeJson($directory.'/capability_registry.json', $this->capabilityEvidence->exportSummary());
        $this->writeFile($directory.'/legal_notice.txt', $this->brandCompliance->legalNotice()."\n");
        $this->writeJson($directory.'/branding_status.json', $this->brandCompliance->workspaceStatus());

        $screenshots = [
            FedExValidationArtifact::TYPE_FEDEX_BRANDING_UI_SCREENSHOT => '01_fedex_branding_legal',
            FedExValidationArtifact::TYPE_FEDEX_SERVICES_PACKAGING_SCREENSHOT => '02_services_packaging',
            FedExValidationArtifact::TYPE_FEDEX_SPECIAL_HANDLING_SCREENSHOT => '03_special_handling',
        ];

        foreach ($screenshots as $type => $basename) {
            $artifact = FedExValidationArtifact::query()
                ->where('store_id', $store->id)
                ->where('carrier_account_id', $account->id)
                ->where('artifact_type', $type)
                ->latest('id')
                ->first();

            $path = $artifact?->absolutePath();
            if ($path === null || ! is_file($path)) {
                throw new HttpException(422, 'Final export blocked: missing branding screenshot '.$basename);
            }

            File::copy($path, $directory.'/screenshots/'.$basename.'.'.pathinfo($path, PATHINFO_EXTENSION));
        }
    }

    private function exportWrittenConfirmations(string $directory, Store $store, CarrierAccount $account): void
    {
        $artifacts = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_FEDEX_WRITTEN_CONFIRMATION)
            ->orderBy('id')
            ->get();

        if ($artifacts->isEmpty()) {
            $this->writeJson($directory.'/written_confirmations_index.json', ['status' => 'none_uploaded']);

            return;
        }

        $index = [];
        foreach ($artifacts as $artifact) {
            $path = $artifact->absolutePath();
            if ($path === null || ! is_file($path)) {
                continue;
            }

            $target = $directory.'/confirmation_'.$artifact->id.'.'.pathinfo($path, PATHINFO_EXTENSION);
            File::copy($path, $target);
            $index[] = [
                'artifact_id' => $artifact->id,
                'sha256' => $artifact->sha256,
                'metadata' => $artifact->metadata_json,
            ];
        }

        $this->writeJson($directory.'/written_confirmations_index.json', $index);
    }

    private function copyCustomerScreenshotsPdf(string $directory, Store $store, CarrierAccount $account): void
    {
        $artifact = FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('artifact_type', FedExValidationArtifact::DOC_CUSTOMER_SCREENSHOTS)
            ->latest('id')
            ->first();

        $path = $artifact?->absolutePath();
        if ($path !== null && is_file($path)) {
            File::copy($path, $directory.'/Customer_Screenshots.pdf');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFileManifest(string $bundleDir, string $root): array
    {
        $entries = [];
        foreach (File::allFiles($bundleDir) as $file) {
            if ($file->getFilename() === 'FILE_MANIFEST_SHA256.json') {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $entries[] = [
                'path' => $root.'/'.$relative,
                'sha256' => hash_file('sha256', $file->getPathname()),
                'size' => $file->getSize(),
                'mime_type' => mime_content_type($file->getPathname()) ?: 'application/octet-stream',
                'source_type' => 'artifact',
            ];
        }

        usort($entries, fn (array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

        return [
            'generated_at' => now()->toIso8601String(),
            'file_count' => count($entries),
            'files' => $entries,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function verifyZipAgainstManifest(string $zipPath, array $manifest, string $root): void
    {
        $zip = new ZipArchive;
        $zip->open($zipPath);

        foreach ($manifest['files'] ?? [] as $entry) {
            $path = (string) ($entry['path'] ?? '');
            $expectedHash = (string) ($entry['sha256'] ?? '');
            $contents = $zip->getFromName($path);
            if ($contents === false) {
                $zip->close();
                throw new HttpException(422, 'Final export verification failed: missing '.$path);
            }

            if (! hash_equals($expectedHash, hash('sha256', $contents))) {
                $zip->close();
                throw new HttpException(422, 'Final export verification failed: hash mismatch for '.$path);
            }
        }

        $zip->close();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function feedbackMatrixCsv(array $rows): string
    {
        $lines = ['FedEx feedback item,Resolution status,Implementation package,Evidence folder,Notes'];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
                [
                    (string) ($row['fedex_feedback_item'] ?? ''),
                    (string) ($row['resolution_status'] ?? ''),
                    (string) ($row['implementation_package'] ?? ''),
                    (string) ($row['evidence_folder'] ?? ''),
                    (string) ($row['notes'] ?? ''),
                ],
            ));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $preflight
     */
    private function finalSubmissionReadme(
        Store $store,
        CarrierAccount $account,
        string $environment,
        FedExValidationSubmissionSnapshot $snapshot,
        array $preflight,
    ): string {
        return implode("\n", [
            'FINAL SUBMISSION SNAPSHOT',
            '',
            'Project: '.config('app.name'),
            'Store: '.$store->name,
            'Environment: '.$environment,
            'Root account: '.$account->maskedAccountNumber(),
            'Snapshot ID: '.$snapshot->id,
            'Case reference: '.($snapshot->case_reference ?: 'Americas'),
            'Created: '.($snapshot->finalized_at?->toIso8601String() ?? now()->toIso8601String()),
            'Preflight status: ready',
            'Logo hash: '.($snapshot->logo_sha256 ?: 'n/a'),
            'Capability registry: '.($snapshot->capability_registry_version ?: 'n/a'),
            'Manifest: FILE_MANIFEST_SHA256.json',
            'Feedback matrix: FEDEX_FEEDBACK_RESPONSE_MATRIX.json',
            '',
            FedExValidationScenarioCatalog::us08ExclusionNote(),
            FedExValidationScenarioCatalog::us10ExclusionNote(),
            '',
            'This package was generated from an immutable snapshot. No secrets or source code are included.',
        ]);
    }

    private function submissionEmailDraft(string $caseReference, FedExValidationSubmissionSnapshot $snapshot, string $zipRoot): string
    {
        return implode("\n", [
            'Subject: FedEx Integrator Validation — corrected submission',
            '',
            'Case reference: '.$caseReference,
            'Attached ZIP: '.$zipRoot.'.zip',
            'Snapshot ID: '.$snapshot->id,
            '',
            'Tracking evidence: previously approved where applicable.',
            'Americas scope: US + Canada only (LAC/AMEA/Europe excluded per Cover Sheet).',
            FedExValidationScenarioCatalog::us08ExclusionNote(),
            FedExValidationScenarioCatalog::us10ExclusionNote(),
            '',
            'Review this draft before sending through the FedEx case email thread.',
        ]);
    }
}
