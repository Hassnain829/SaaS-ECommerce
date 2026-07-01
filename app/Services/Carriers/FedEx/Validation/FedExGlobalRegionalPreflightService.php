<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\FedExValidationArtifact;
use App\Models\Store;

class FedExGlobalRegionalPreflightService
{
    public function __construct(
        private readonly FedExRegionalShipEvidenceService $regionalShipEvidence,
        private readonly FedExValidationRegionalAccountService $regionalAccountService,
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assessCanada(Store $store, CarrierAccount $account): array
    {
        $region = FedExGlobalShipCaseCatalog::REGION_CA;
        $summary = $this->regionalShipEvidence->regionSummary($store, $account, $region);
        $accounts = $this->regionalAccountService->workspaceSummary($store, $account, $region);
        $representatives = FedExGlobalShipCaseCatalog::transactionRepresentatives($region);
        $checks = [];

        foreach (FedExGlobalShipCaseCatalog::casesByRegion()[$region] ?? [] as $case) {
            $key = (string) ($case['case_key'] ?? '');
            $scenarioKey = FedExValidationScenarioCatalog::globalScenarioKey($key);
            $meta = FedExValidationScenarioCatalog::globalShipScenarios()[$key] ?? [];
            $labelFormat = (string) ($meta['label_format'] ?? '');
            $canonicalRun = $this->evidenceQuery->canonicalGlobalShipRun($store, $account, $region, $key);
            $event = $canonicalRun['event'] ?? null;
            $latestAttempt = $this->evidenceQuery->latestGlobalShipLabelAttempt(
                $store,
                $account,
                $region,
                $scenarioKey,
                $key,
                $labelFormat,
            );

            $checks[] = [
                'key' => strtolower($scenarioKey).'_event',
                'label' => $key.' ship label',
                'required' => true,
                'status' => $this->canonicalEventStatus($event, $latestAttempt),
                'event_id' => $event?->id,
                'region' => $region,
            ];

            $expectedPackages = (int) ($case['expected_packages'] ?? 1);
            for ($sequence = 1; $sequence <= $expectedPackages; $sequence++) {
                $scanArtifact = $this->artifactForSequence($canonicalRun['printed_scans'] ?? [], $sequence);

                $checks[] = [
                    'key' => strtolower($scenarioKey).'_scan_'.$sequence,
                    'label' => $key.' printed scan package '.$sequence,
                    'required' => true,
                    'status' => $scanArtifact !== null ? 'passed' : 'missing',
                    'event_id' => $event?->id,
                    'artifact_id' => $scanArtifact?->id,
                    'region' => $region,
                ];
            }
        }

        foreach ($representatives as $format => $caseKey) {
            if ($caseKey === null) {
                continue;
            }

            $representativeRun = $this->evidenceQuery->canonicalGlobalShipRun($store, $account, $region, $caseKey);
            $representativeEvent = $representativeRun['event'] ?? null;

            $checks[] = [
                'key' => 'ca_transaction_representative_'.strtolower($format),
                'label' => 'Canada '.$format.' transaction representative ('.$caseKey.')',
                'required' => true,
                'status' => $representativeEvent !== null ? 'passed' : 'missing',
                'event_id' => $representativeEvent?->id,
                'representative_case' => $caseKey,
                'region' => $region,
            ];
        }

        $checks[] = [
            'key' => 'ca_regional_accounts_ready',
            'label' => 'Canada regional validation accounts prepared',
            'required' => true,
            'status' => ($accounts['total_accounts'] ?? 0) >= 2 ? 'passed' : 'missing',
            'region' => $region,
        ];

        $blockers = collect($checks)->filter(fn (array $check): bool => $check['required'] && $check['status'] !== 'passed')->values()->all();

        return [
            'region' => $region,
            'summary' => $summary,
            'accounts' => $accounts,
            'checks' => $checks,
            'passed' => count($checks) - count($blockers),
            'total' => count($checks),
            'blockers' => $blockers,
            'submission_ready' => $blockers === [],
        ];
    }

    private function canonicalEventStatus(?\App\Models\CarrierApiEvent $canonical, ?\App\Models\CarrierApiEvent $latest): string
    {
        if ($canonical !== null) {
            return 'passed';
        }

        return $latest === null ? 'missing' : 'failed';
    }

    /**
     * @param  list<FedExValidationArtifact>  $artifacts
     */
    private function artifactForSequence(array $artifacts, int $sequence): ?FedExValidationArtifact
    {
        foreach ($artifacts as $artifact) {
            if ((int) $artifact->package_sequence === $sequence) {
                return $artifact;
            }
        }

        return null;
    }
}
