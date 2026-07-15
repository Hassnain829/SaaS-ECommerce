<?php

namespace App\Http\Controllers\Carrier\Validation;

use App\Http\Controllers\Carrier\Validation\Concerns\ResolvesFedExValidationAccount;
use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Presenters\FedExValidationStatusPresenter;
use App\Services\Carriers\FedEx\Presenters\FedExValidationWorkspaceCardPresenter;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExComprehensiveRateEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExHostedEulaEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExShipEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceQueryService;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScopeService;
use App\Models\FedExValidationSubmissionSnapshot;
use App\Services\Carriers\FedEx\Validation\FedExBrandComplianceService;
use App\Services\Carriers\FedEx\Validation\FedExFinalSubmissionService;
use App\Services\Carriers\FedEx\Validation\FedExGlobalRegionalPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExGlobalShipCaseCatalog;
use App\Services\Carriers\FedEx\Validation\FedExRegionalShipEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExValidationRegionalAccountService;
use App\Services\Carriers\FedEx\Validation\FedExUs09EtdFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FedExValidationWorkspaceController extends Controller
{
    use ResolvesFedExValidationAccount;

    public function show(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $config,
        FedExValidationPreflightService $preflight,
        FedExValidationStatusPresenter $statusPresenter,
        FedExValidationScopeService $scopeService,
        FedExValidationWorkspaceCardPresenter $cardPresenter,
        FedExShipTestCaseFixtureService $fixtureService,
        FedExTestCaseFixtureService $testCaseFixtures,
        FedExValidationEvidenceQueryService $evidenceQuery,
        FedExHostedEulaEvidenceService $hostedEulaEvidence,
        FedExComprehensiveRateEvidenceService $comprehensiveRateEvidence,
        FedExShipEvidenceService $shipEvidenceService,
        FedExRegionalShipEvidenceService $regionalShipEvidence,
        FedExValidationRegionalAccountService $regionalAccountService,
        FedExGlobalRegionalPreflightService $globalRegionalPreflight,
        FedExFinalSubmissionService $finalSubmission,
        FedExBrandComplianceService $brandCompliance,
    ): View {
        $account = $this->resolveFedExValidationAccount($request, $carrierAccount, $config);
        $store = $account->store;

        $assessment = $preflight->assess($store, $account);
        $finalAssessment = $preflight->assess($store, $account, null, includePackageEight: true);
        $latestSnapshot = FedExValidationSubmissionSnapshot::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('status', FedExValidationSubmissionSnapshot::STATUS_READY)
            ->whereNull('invalidated_at')
            ->latest('id')
            ->first();
        $swedenFixture = $testCaseFixtures->swedenMfaPassthroughAccount();
        $canonicalSwedenRun = $evidenceQuery->canonicalSwedenPassthroughRun($store, $account);
        $latestSwedenAttempt = $evidenceQuery->latestSwedenPassthroughAttempt($store, $account);

        return view('user_view.fedex_validation.workspace', [
            'selectedStore' => $store,
            'account' => $account,
            'preflight' => $assessment,
            'finalPreflight' => $finalAssessment,
            'finalReadinessGroups' => $finalSubmission->groupedReadiness($store, $account),
            'latestFinalSnapshot' => $latestSnapshot,
            'brandComplianceStatus' => $brandCompliance->workspaceStatus(),
            'capabilityMatrix' => $statusPresenter->capabilityMatrix($store, $account),
            'requiredScopes' => $scopeService->resolveRequiredScopes(),
            'lockedShipScenarios' => \App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog::lockedShipScenarios(),
            'us08ValidationEnabled' => app(\App\Services\Carriers\FedEx\Support\FedExConfig::class)->us08LiveRunEnabled(),
            'freightLtlApiEnabled' => app(\App\Services\Carriers\FedEx\Support\FedExConfig::class)->freightLtlApiEnabled(),
            'us08ExclusionNote' => app(\App\Services\Carriers\FedEx\Support\FedExConfig::class)->us08ExclusionNote(),
            'freightLtlApiDisabledMessage' => app(\App\Services\Carriers\FedEx\Support\FedExConfig::class)->us08ExclusionNote(),
            'us10ValidationEnabled' => app(\App\Services\Carriers\FedEx\Support\FedExConfig::class)->us10LiveRunEnabled(),
            'us10ExclusionNote' => app(\App\Services\Carriers\FedEx\Support\FedExConfig::class)->us10ExclusionNote(),
            'checksByKey' => collect($assessment['checks'] ?? [])->keyBy('key'),
            'trackingNumbers' => $this->trackingNumbersFromShipEvents($store, $account),
            'trackingConfigured' => filled($config->basicIntegratedVisibilityPath()),
            'shipCancelRequired' => $scopeService->shipCancelRequired(),
            'tradeDocumentsRequired' => $scopeService->tradeDocumentsRequired(),
            'tradeDocumentsConfigured' => filled($config->tradeDocumentsUploadPath()),
            'validationCards' => $cardPresenter->cards($store, $account, $assessment),
            'invoiceEndpointConfigured' => $config->mfaInvoiceValidationPath() !== null,
            'pinGenerationEndpointConfigured' => $config->mfaPinGenerationPath() !== null,
            'pinValidationEndpointConfigured' => $config->mfaPinValidationPath() !== null,
            'mfaInvoicePrefill' => $fixtureService->mfaInvoice(),
            'sandboxAccountEnding' => $this->maskedSandboxAccountEnding($account),
            'swedenPassthroughAvailable' => $swedenFixture !== null,
            'swedenAccountLast4' => $swedenFixture !== null
                ? ($swedenFixture['account_last4'] ?? substr(preg_replace('/\D+/', '', (string) ($swedenFixture['account_number'] ?? '')) ?: '9268', -4))
                : '9268',
            'swedenPassthroughStatus' => $this->swedenPassthroughStatus($canonicalSwedenRun, $latestSwedenAttempt, $assessment),
            'swedenScreenshotsUploadAllowed' => $canonicalSwedenRun !== null,
            'hostedEulaStatus' => $hostedEulaEvidence->workspaceStatus($account),
            'comprehensiveRateStatus' => $comprehensiveRateEvidence->workspaceStatus($store, $account),
            'lockedShipStatuses' => collect($fixtureService->testCaseKeys())
                ->mapWithKeys(fn (string $testCaseKey): array => [
                    $testCaseKey => $shipEvidenceService->workspaceStatus($store, $account, $testCaseKey),
                ])
                ->all(),
            'us09Status' => $this->us09WorkspaceStatus($store, $account, $evidenceQuery, $shipEvidenceService, $assessment),
            'us10Status' => $this->us10WorkspaceStatus($store, $account, $evidenceQuery, $assessment),
            'canadaRegionalSummary' => $regionalShipEvidence->regionSummary($store, $account, FedExGlobalShipCaseCatalog::REGION_CA),
            'canadaRegionalAccounts' => $regionalAccountService->workspaceSummary($store, $account, FedExGlobalShipCaseCatalog::REGION_CA),
            'canadaRegionalPreflight' => $globalRegionalPreflight->assessCanada($store, $account),
            'globalShipScenarios' => FedExValidationScenarioCatalog::globalShipScenariosForRegion(FedExGlobalShipCaseCatalog::REGION_CA),
        ]);
    }

    /**
     * @param  array<string, mixed>  $assessment
     * @return array<string, mixed>
     */
    private function us09WorkspaceStatus(
        \App\Models\Store $store,
        CarrierAccount $account,
        FedExValidationEvidenceQueryService $evidenceQuery,
        FedExShipEvidenceService $shipEvidenceService,
        array $assessment,
    ): array {
        $checks = collect($assessment['checks'] ?? [])->keyBy('key');
        $letterhead = $evidenceQuery->canonicalSuccessfulEvent($store, $account, FedExUs09EtdFixtureService::UPLOAD_SCENARIO_LETTERHEAD);
        $signature = $evidenceQuery->canonicalSuccessfulEvent($store, $account, FedExUs09EtdFixtureService::UPLOAD_SCENARIO_SIGNATURE);
        $document = $evidenceQuery->canonicalSuccessfulEvent($store, $account, FedExUs09EtdFixtureService::UPLOAD_SCENARIO_DOCUMENT);

        $base = base_path('resources/fedex-validation/us09');
        $assets = [
            'letterhead' => is_file($base.'/signature3.png'),
            'signature' => is_file($base.'/signature2.png'),
            'document' => is_file($base.'/commercial_invoice.pdf'),
        ];

        $imageShip = $shipEvidenceService->workspaceStatus($store, $account, 'IntegratorUS09_IMAGE');
        $documentShip = $shipEvidenceService->workspaceStatus($store, $account, 'IntegratorUS09_DOCUMENT');

        return [
            'letterhead_uploaded' => $letterhead !== null,
            'signature_uploaded' => $signature !== null,
            'document_uploaded' => $document !== null,
            'image_ship_ready' => $letterhead !== null && $signature !== null,
            'document_ship_ready' => $document !== null,
            'assets_present' => $assets['letterhead'] && $assets['signature'] && $assets['document'],
            'assets' => $assets,
            'letterhead_check' => $checks->get('upload_us09_image_letterhead_event')['status'] ?? 'not_tested',
            'signature_check' => $checks->get('upload_us09_image_signature_event')['status'] ?? 'not_tested',
            'document_check' => $checks->get('upload_us09_document_event')['status'] ?? 'not_tested',
            'image_ship' => $imageShip,
            'document_ship' => $documentShip,
            'image_next_action' => $this->us09NextAction(
                assetsReady: ($assets['letterhead'] ?? false) && ($assets['signature'] ?? false),
                uploadsReady: $letterhead !== null && $signature !== null,
                shipStatus: $imageShip,
                missingAssetMessage: 'Place signature3.png and signature2.png under resources/fedex-validation/us09/, then upload letterhead and signature.',
                uploadMessage: 'Upload letterhead and signature, then create the image ETD shipment.',
            ),
            'document_next_action' => $this->us09NextAction(
                assetsReady: (bool) ($assets['document'] ?? false),
                uploadsReady: $document !== null,
                shipStatus: $documentShip,
                missingAssetMessage: 'Place commercial_invoice.pdf under resources/fedex-validation/us09/, then upload it.',
                uploadMessage: 'Upload the commercial invoice, then create the document ETD shipment.',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $shipStatus
     */
    private function us09NextAction(
        bool $assetsReady,
        bool $uploadsReady,
        array $shipStatus,
        string $missingAssetMessage,
        string $uploadMessage,
    ): string {
        $transaction = (string) ($shipStatus['transaction_status'] ?? 'not_tested');
        $printedCount = count($shipStatus['printed_scan_artifacts'] ?? []);
        $expectedPackages = (int) ($shipStatus['expected_package_count'] ?? 1);

        // If shipment + scans already exist, do not nag about local workbook assets.
        if ($transaction === 'passed' && $printedCount >= $expectedPackages) {
            return 'US09 label evidence for this path looks complete.';
        }

        if ($transaction === 'passed' && $printedCount < $expectedPackages) {
            return 'Download the generated label, print it, scan the printed paper at 600 DPI+, then upload the scan below (PDF, PNG, or JPG).';
        }

        if (! $assetsReady) {
            return $missingAssetMessage;
        }

        if (! $uploadsReady) {
            return $uploadMessage;
        }

        if ($transaction !== 'passed') {
            return 'Create the ETD shipment, then download the generated label.';
        }

        return 'US09 label evidence for this path looks complete.';
    }

    /**
     * @param  array<string, mixed>  $assessment
     * @return array<string, mixed>
     */
    private function us10WorkspaceStatus(
        \App\Models\Store $store,
        CarrierAccount $account,
        FedExValidationEvidenceQueryService $evidenceQuery,
        array $assessment,
    ): array {
        $checks = collect($assessment['checks'] ?? [])->keyBy('key');
        $rawAccount = trim((string) config('carriers.fedex.validation_us10_consolidation_account', ''));
        $accountConfigured = $rawAccount !== '';
        $tinConfigured = trim((string) config('carriers.fedex.validation_us10_shipper_tin', '')) !== '';
        $usesWorkbookThirdPartyAsRoot = $rawAccount === FedExConsolidationFixtureService::WORKBOOK_THIRD_PARTY_ACCOUNT;
        $completed = [];
        $steps = [];

        foreach (FedExValidationScenarioCatalog::lockedConsolidationScenarios() as $testCaseKey => $meta) {
            $scenarioKey = (string) $meta['scenario_key'];
            $event = $evidenceQuery->canonicalSuccessfulEvent($store, $account, $scenarioKey, $testCaseKey);
            $passed = $event !== null;
            if ($passed) {
                $completed[] = $testCaseKey;
            }

            $steps[] = [
                'test_case_key' => $testCaseKey,
                'label' => match ((string) ($meta['operation'] ?? '')) {
                    'create' => 'Create consolidation',
                    'add_shipment' => 'Add shipment '.(string) ($meta['shipment_sequence'] ?? ''),
                    'confirm' => 'Confirm consolidation',
                    'confirm_results' => 'Confirm results',
                    default => str($testCaseKey)->after('IntegratorUS10_')->replace('_', ' ')->title()->toString(),
                },
                'status' => $passed ? 'passed' : 'not_tested',
            ];
        }

        $confirmResultsEvent = $evidenceQuery->canonicalSuccessfulEvent(
            $store,
            $account,
            'consolidation_us10_confirm_results',
            'IntegratorUS10_CONFIRM_RESULTS',
        );

        $childLabels = [];
        $cciArtifact = null;
        if ($confirmResultsEvent !== null) {
            $childLabels = FedExValidationArtifact::query()
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

        $lastFailure = $this->us10LastFailure($store, $account);
        $chainComplete = count($completed) === count(FedExValidationScenarioCatalog::lockedConsolidationScenarios());
        $labelsPassed = ($checks->get('consolidation_us10_child_labels')['status'] ?? '') === 'passed';
        $cciPassed = ($checks->get('consolidation_us10_cci')['status'] ?? '') === 'passed';

        return [
            'excluded' => ! FedExValidationScenarioCatalog::isConsolidationEnabled(),
            'exclusion_note' => FedExValidationScenarioCatalog::us10ExclusionNote(),
            'account_configured' => $accountConfigured,
            'tin_configured' => $tinConfigured,
            'account_last4' => $accountConfigured
                ? '****'.substr(preg_replace('/\D+/', '', $rawAccount) ?: $rawAccount, -4)
                : null,
            'uses_workbook_third_party_as_root' => $usesWorkbookThirdPartyAsRoot,
            'workbook_third_party_last4' => '****'.substr(FedExConsolidationFixtureService::WORKBOOK_THIRD_PARTY_ACCOUNT, -4),
            'account_check' => $checks->get('consolidation_us10_account_configured')['status'] ?? 'not_tested',
            'tin_check' => $checks->get('consolidation_us10_shipper_tin_configured')['status'] ?? 'not_tested',
            'completed_steps' => $completed,
            'steps' => $steps,
            'completed_count' => count($completed),
            'required_count' => count(FedExValidationScenarioCatalog::lockedConsolidationScenarios()),
            'child_labels_check' => $checks->get('consolidation_us10_child_labels')['status'] ?? 'not_tested',
            'cci_check' => $checks->get('consolidation_us10_cci')['status'] ?? 'not_tested',
            'child_label_artifacts' => $childLabels,
            'cci_artifact' => $cciArtifact,
            'last_failure' => $lastFailure,
            'do_not_retry' => (bool) ($lastFailure['do_not_retry'] ?? false) && ! $chainComplete,
            'ready_to_run' => FedExValidationScenarioCatalog::isConsolidationEnabled()
                && $accountConfigured
                && $tinConfigured
                && ! $usesWorkbookThirdPartyAsRoot,
            'next_action' => ! FedExValidationScenarioCatalog::isConsolidationEnabled()
                ? FedExValidationScenarioCatalog::us10ExclusionNote()
                : $this->us10NextAction(
                    $accountConfigured,
                    $tinConfigured,
                    $usesWorkbookThirdPartyAsRoot,
                    $chainComplete,
                    $labelsPassed,
                    $cciPassed,
                    $lastFailure,
                ),
        ];
    }

    /**
     * @return array{failed_step: ?string, http_status: ?int, error_code: ?string, error_message: ?string, do_not_retry: bool}|null
     */
    private function us10LastFailure(\App\Models\Store $store, CarrierAccount $account): ?array
    {
        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_CONSOLIDATION)
            ->where(function ($query): void {
                $query->where('status', '!=', CarrierApiEvent::STATUS_SUCCEEDED)
                    ->orWhereNull('http_status')
                    ->orWhere('http_status', '<', 200)
                    ->orWhere('http_status', '>=', 300);
            })
            ->latest('id')
            ->first();

        if ($event === null) {
            return null;
        }

        $httpStatus = is_numeric($event->http_status) ? (int) $event->http_status : null;
        $errorCode = data_get($event->response_summary, 'errors.0.code')
            ?? data_get($event->response_summary, 'error_code')
            ?? data_get($event->response_body_encrypted, 'errors.0.code');
        $errorMessage = $event->error_message
            ?? data_get($event->response_summary, 'errors.0.message')
            ?? data_get($event->response_body_encrypted, 'errors.0.message');

        if (is_string($errorMessage)) {
            $errorMessage = trim($errorMessage);
            if (strlen($errorMessage) > 220) {
                $errorMessage = substr($errorMessage, 0, 217).'...';
            }
        } else {
            $errorMessage = null;
        }

        $failedStep = match ((string) ($event->scenario_key ?? '')) {
            'consolidation_us10_create' => 'create',
            'consolidation_us10_confirm' => 'confirm',
            'consolidation_us10_confirm_results' => 'confirm_results',
            default => str_starts_with((string) $event->scenario_key, 'consolidation_us10_add_shipment_')
                ? str_replace('consolidation_us10_', '', (string) $event->scenario_key)
                : 'unknown',
        };

        return [
            'failed_step' => $failedStep,
            'http_status' => $httpStatus,
            'error_code' => is_string($errorCode) ? $errorCode : null,
            'error_message' => $errorMessage,
            'do_not_retry' => $httpStatus === 403 || $errorCode === 'FORBIDDEN.ERROR',
        ];
    }

    /**
     * @param  array{failed_step: ?string, http_status: ?int, error_code: ?string, error_message: ?string, do_not_retry: bool}|null  $lastFailure
     */
    private function us10NextAction(
        bool $accountConfigured,
        bool $tinConfigured,
        bool $usesWorkbookThirdPartyAsRoot,
        bool $chainComplete,
        bool $labelsPassed,
        bool $cciPassed,
        ?array $lastFailure,
    ): string {
        if (! $accountConfigured) {
            return 'Set FEDEX_VALIDATION_US10_CONSOLIDATION_ACCOUNT in .env to the US Test Account from the workbook Test Account Numbers tab (700257037), then reload this page.';
        }

        if (! $tinConfigured) {
            return 'Set FEDEX_VALIDATION_US10_SHIPPER_TIN in .env to the workbook shipper TIN (59165821389), then reload this page.';
        }

        if ($usesWorkbookThirdPartyAsRoot) {
            return 'FEDEX_VALIDATION_US10_CONSOLIDATION_ACCOUNT is set to the workbook THIRD_PARTY billing value (****6789). That value stays in soldTo / payor fields automatically. Set the env account to the US Test Account from Test Account Numbers (700257037) — the account linked to your Integrator child credentials.';
        }

        if (($lastFailure['do_not_retry'] ?? false) && ! $chainComplete) {
            return 'FedEx rejected Consolidation credentials or entitlement (HTTP 403). Confirm the env account matches your OAuth-linked US Test Account and that Consolidation / IPD is available for this Integrator project, then run once. Do not retry blindly.';
        }

        if (! $chainComplete) {
            return 'Confirm the acknowledgment, then run the Consolidation chain once (Create → 6 Add Shipments → Confirm → Confirm Results).';
        }

        if (! $labelsPassed || ! $cciPassed) {
            return 'Chain events exist, but child labels or the Consolidated Commercial Invoice are incomplete. Re-check Confirm Results artifacts below before exporting.';
        }

        return 'US10 Consolidation evidence looks complete.';
    }

    /**
     * @param  array{validation_run_id: string, address_event: CarrierApiEvent, child_authorization_event: CarrierApiEvent}|null  $canonicalRun
     * @param  array{validation_run_id: ?string, address_event: CarrierApiEvent, child_authorization_event: ?CarrierApiEvent}|null  $latestAttempt
     * @param  array<string, mixed>  $assessment
     * @return array<string, string>
     */
    private function swedenPassthroughStatus(?array $canonicalRun, ?array $latestAttempt, array $assessment): array
    {
        $attemptAddress = $latestAttempt['address_event'] ?? null;
        $attemptChild = $latestAttempt['child_authorization_event'] ?? null;
        $checks = collect($assessment['checks'] ?? [])->keyBy('key');

        $addressStatus = 'Not tested';
        if ($canonicalRun !== null) {
            $addressStatus = 'Passed';
        } elseif ($attemptAddress !== null) {
            $addressStatus = 'Failed';
        }

        $childCredentials = 'No';
        if ($canonicalRun !== null || ($attemptAddress !== null && data_get($attemptAddress->response_summary, 'child_credentials_detected'))) {
            $childCredentials = 'Yes';
        }

        $mfaStatus = 'Not tested';
        if ($canonicalRun !== null) {
            $mfaStatus = 'Bypassed';
        } elseif ($attemptAddress !== null) {
            $mfaStatus = data_get($attemptAddress->response_summary, 'mfa_detected') ? 'Unexpected' : 'Bypassed';
        }

        $childAuthStatus = 'Not tested';
        if ($canonicalRun !== null) {
            $childAuthStatus = 'Passed';
        } elseif ($attemptChild !== null) {
            $childAuthStatus = 'Failed';
        }

        $addressScreenshotPassed = ($checks->get('sweden_passthrough_address_screenshot')['status'] ?? '') === 'passed';
        $childScreenshotPassed = ($checks->get('sweden_passthrough_child_authorization_screenshot')['status'] ?? '') === 'passed';
        $screenshots = ($addressScreenshotPassed && $childScreenshotPassed) ? 'Complete' : 'Missing';

        return [
            'address_validation' => $addressStatus,
            'child_credentials_returned' => $childCredentials,
            'mfa_challenge' => $mfaStatus,
            'direct_child_authorization' => $childAuthStatus,
            'screenshots' => $screenshots,
        ];
    }

    private function maskedSandboxAccountEnding(CarrierAccount $account): string
    {
        $masked = $account->maskedAccountNumber();

        if ($masked !== '—' && str_contains($masked, '*')) {
            return $masked;
        }

        $number = (string) ($account->provider_account_number ?? '');

        return strlen($number) >= 4 ? '****'.substr($number, -4) : '****';
    }

    /**
     * @return list<string>
     */
    private function trackingNumbersFromShipEvents(\App\Models\Store $store, CarrierAccount $account): array
    {
        return CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL)
            ->where('status', CarrierApiEvent::STATUS_SUCCEEDED)
            ->whereBetween('http_status', [200, 299])
            ->orderByDesc('id')
            ->get()
            ->flatMap(function (CarrierApiEvent $event): array {
                $numbers = [];
                $body = $event->response_body_encrypted ?? [];

                foreach ((array) data_get($body, 'output.transactionShipments', []) as $shipment) {
                    if (is_string($master = data_get($shipment, 'masterTrackingNumber')) && $master !== '') {
                        $numbers[] = $master;
                    }

                    foreach ((array) data_get($shipment, 'pieceResponses', []) as $piece) {
                        if (is_string($tracking = data_get($piece, 'trackingNumber')) && $tracking !== '') {
                            $numbers[] = $tracking;
                        }
                    }
                }

                return $numbers;
            })
            ->unique()
            ->values()
            ->all();
    }
}
