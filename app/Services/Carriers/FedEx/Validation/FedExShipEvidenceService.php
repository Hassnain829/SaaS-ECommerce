<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Store;

class FedExShipEvidenceService
{
    public function __construct(
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
        private readonly FedExShipEvidenceRules $evidenceRules,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function canonicalRun(Store $store, CarrierAccount $account, string $testCaseKey): ?array
    {
        return $this->evidenceQuery->canonicalShipRun($store, $account, $testCaseKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaceStatus(Store $store, CarrierAccount $account, string $testCaseKey): array
    {
        $meta = FedExValidationScenarioCatalog::lockedShipScenarios()[$testCaseKey];
        $canonicalRun = $this->canonicalRun($store, $account, $testCaseKey);
        $latestAttempt = $this->evidenceQuery->latestShipLabelAttempt(
            $store,
            $account,
            (string) $meta['scenario_key'],
            testCaseKey: $testCaseKey,
            labelFormat: (string) $meta['label_format'],
        );

        $canonicalEvent = $canonicalRun['event'] ?? null;
        $displayEvent = $canonicalEvent ?? $latestAttempt;
        $expected = $this->evidenceRules->expectedMetadata($testCaseKey);
        $validation = $canonicalRun['validation'] ?? null;

        $generatedCount = count($canonicalRun['generated_labels'] ?? []);
        $scanCount = count($canonicalRun['printed_scans'] ?? []);
        $expectedPackages = (int) $expected['expected_package_count'];

        return [
            'test_case_key' => $testCaseKey,
            'label' => $this->fixtureServiceLabel($testCaseKey),
            'expected_service_type' => $expected['expected_service_type'],
            'expected_label_format' => $expected['expected_label_format'],
            'expected_package_count' => $expectedPackages,
            'latest_attempt_event_id' => $latestAttempt?->id,
            'canonical_event_id' => $canonicalEvent?->id,
            'display_event_id' => $displayEvent?->id,
            'transaction_status' => $this->transactionStatus($canonicalEvent, $latestAttempt),
            'service_match_status' => ($validation['response_service_matches'] ?? false) ? 'passed' : ($displayEvent ? 'failed' : 'not_tested'),
            'response_service_type' => $validation['response_service_type'] ?? data_get($displayEvent?->response_summary, 'response_service_type'),
            'generated_labels' => $generatedCount.' of '.$expectedPackages,
            'printed_scans' => $scanCount.' of '.$expectedPackages,
            'artifact_integrity_status' => ($validation['artifact_integrity_passed'] ?? false) ? 'passed' : ($canonicalEvent ? 'failed' : 'not_tested'),
            'mps_correlation_status' => $testCaseKey === 'IntegratorUS05'
                ? (($validation['mps_correlation_passed'] ?? false) ? 'passed' : ($canonicalEvent ? 'failed' : 'not_tested'))
                : null,
            'package_sequences' => $validation['package_sequences'] ?? [],
            'generated_label_artifacts' => $canonicalRun['generated_labels'] ?? [],
            'printed_scan_artifacts' => $canonicalRun['printed_scans'] ?? [],
            'validation' => $validation,
            'printing_instructions' => $this->printingInstructions($testCaseKey),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportResultSummary(?CarrierApiEvent $event, string $testCaseKey, bool $diagnosticOnly = false): array
    {
        $expected = $this->evidenceRules->expectedMetadata($testCaseKey);
        $defaultEndpoint = $testCaseKey === 'IntegratorUS08'
            ? '/ship/v1/freight/shipments'
            : '/ship/v1/shipments';

        if ($event === null) {
            return [
                'test_case' => $testCaseKey,
                'scenario_key' => $expected['scenario_key'],
                'endpoint' => $defaultEndpoint,
                'status' => 'missing',
                'submission_ready' => false,
            ];
        }

        $request = is_array($event->request_body_encrypted) ? $event->request_body_encrypted : [];
        $validation = array_merge(
            $this->evidenceRules->validateRequest($event, $testCaseKey),
            ['response' => $this->evidenceRules->validateResponse($event, $testCaseKey)],
        );

        $artifacts = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_GENERATED_LABEL)
            ->orderBy('package_sequence')
            ->get()
            ->all();

        $documentArtifacts = [];
        if ($testCaseKey === 'IntegratorUS08') {
            $documentArtifacts = FedExValidationArtifact::query()
                ->where('carrier_api_event_id', $event->id)
                ->where('artifact_role', FedExValidationArtifact::ROLE_VALIDATION_DOCUMENT)
                ->whereIn('artifact_type', ['freight_bill_of_lading', 'freight_commercial_invoice'])
                ->orderBy('id')
                ->get()
                ->all();
        }

        $artifactValidation = $this->evidenceRules->validateGeneratedArtifacts(
            $event,
            $testCaseKey,
            $artifacts,
            $documentArtifacts,
        );
        $scans = FedExValidationArtifact::query()
            ->where('carrier_api_event_id', $event->id)
            ->where('artifact_role', FedExValidationArtifact::ROLE_PRINTED_SCAN)
            ->count();

        $responseValidation = $validation['response'];
        $parsed = $responseValidation['parsed'] ?? [];

        $requestServiceType = $testCaseKey === 'IntegratorUS08'
            ? data_get($request, 'freightRequestedShipment.serviceType')
            : data_get($request, 'requestedShipment.serviceType');

        $summary = [
            'test_case' => $testCaseKey,
            'scenario_key' => $expected['scenario_key'],
            'endpoint' => (string) ($event->endpoint ?? $defaultEndpoint),
            'http_status' => $event->http_status,
            'expected_service_type' => $expected['expected_service_type'],
            'request_service_type' => $requestServiceType,
            'response_service_type' => $parsed['service_type'] ?? null,
            'service_matches' => $responseValidation['valid'] ?? false,
            'payment_type' => $expected['payment_type'],
            'label_format' => $expected['expected_label_format'],
            'label_stock_type' => $expected['label_stock_type'],
            'expected_package_count' => $expected['expected_package_count'],
            'generated_label_count' => count($artifacts),
            'printed_scan_count' => $scans,
            'event_id' => $event->id,
            'submission_ready' => ! $diagnosticOnly
                && ($validation['valid'] ?? false)
                && ($responseValidation['valid'] ?? false)
                && ($artifactValidation['valid'] ?? false)
                && $scans >= (int) $expected['expected_package_count'],
        ];

        if ($testCaseKey === 'IntegratorUS08') {
            $summary['generated_document_count'] = count($documentArtifacts);
            $summary['freight_bol_present'] = collect($documentArtifacts)->contains(
                static fn (FedExValidationArtifact $artifact): bool => $artifact->artifact_type === 'freight_bill_of_lading'
            );
            $summary['freight_commercial_invoice_present'] = collect($documentArtifacts)->contains(
                static fn (FedExValidationArtifact $artifact): bool => $artifact->artifact_type === 'freight_commercial_invoice'
            );
        }

        if ($testCaseKey === 'IntegratorUS05') {
            $summary['mps'] = true;
            $summary['package_sequences'] = array_keys($parsed['labels'] ?? []);
            $summary['mps_correlation_passed'] = ($responseValidation['valid'] ?? false) && ($artifactValidation['valid'] ?? false);
        }

        return $summary;
    }

    private function transactionStatus(?CarrierApiEvent $canonical, ?CarrierApiEvent $latest): string
    {
        if ($canonical !== null) {
            return 'passed';
        }

        if ($latest === null) {
            return 'not_tested';
        }

        return $latest->isSuccessfulHttp() ? 'failed' : 'failed';
    }

    private function fixtureServiceLabel(string $testCaseKey): string
    {
        return (string) (app(FedExShipFixtureResolver::class)->fixture($testCaseKey)['label'] ?? $testCaseKey);
    }

    private function printingInstructions(string $testCaseKey): string
    {
        return match ($testCaseKey) {
            'IntegratorUS01' => 'Print the alcohol PDF label on a laser printer at actual size / 100%. Do not scale or fit-to-page.',
            'IntegratorUS02' => 'Print this ZPLII file on a compatible Zebra thermal printer at the printer\'s native label size. Do not convert it using an online renderer.',
            'IntegratorUS03' => 'Print the international PDF label and commercial invoice documents at actual size / 100%. Do not scale or fit-to-page.',
            'IntegratorUS04' => 'Print on a laser printer at actual size / 100%. Do not scale, fit, enlarge or shrink. Do not use the API PNG itself as the scanned submission.',
            'IntegratorUS05' => 'Print each package PDF label separately on a laser printer. Use actual size / no scaling. Package 1 and package 2 must both be printed and scanned.',
            'IntegratorUS06' => 'Print the Return Manager PDF return label at actual size / 100%. Do not scale or fit-to-page.',
            'IntegratorUS07' => 'Print the Ground Economy PDF label at actual size / 100%. Do not scale or fit-to-page.',
            'IntegratorUS08' => 'Print the Freight LTL ZPLII handling-unit label on a compatible Zebra thermal printer. Also retain the Bill of Lading PDF when generated.',
            'IntegratorUS09_IMAGE' => 'Print the international ETD PDF label (image letterhead/signature) at actual size / 100%. Do not scale or fit-to-page.',
            'IntegratorUS09_DOCUMENT' => 'Print the international ETD PDF label (uploaded commercial invoice) at actual size / 100%. Do not scale or fit-to-page.',
            default => 'Print the generated label at actual size before scanning.',
        };
    }
}
