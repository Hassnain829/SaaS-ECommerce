<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\Store;

class FedExGlobalRegionalPreflightService
{
    public function __construct(
        private readonly FedExRegionalShipEvidenceService $regionalShipEvidence,
        private readonly FedExValidationRegionalAccountService $regionalAccountService,
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
            $status = $summary['case_statuses'][$key] ?? [];

            $checks[] = [
                'key' => strtolower($scenarioKey).'_event',
                'label' => $key.' ship label',
                'required' => true,
                'status' => ($status['transaction_status'] ?? '') === 'passed' ? 'passed' : (($status['display_event_id'] ?? null) ? 'failed' : 'missing'),
                'region' => $region,
            ];

            $expectedPackages = (int) ($case['expected_packages'] ?? 1);
            for ($i = 1; $i <= $expectedPackages; $i++) {
                $scanCount = count($status['printed_scan_artifacts'] ?? []);
                $checks[] = [
                    'key' => strtolower($scenarioKey).'_scan_'.$i,
                    'label' => $key.' printed scan package '.$i,
                    'required' => true,
                    'status' => $scanCount >= $i ? 'passed' : 'missing',
                    'region' => $region,
                ];
            }
        }

        foreach ($representatives as $format => $caseKey) {
            if ($caseKey === null) {
                continue;
            }

            $caseStatus = $summary['case_statuses'][$caseKey] ?? [];
            $checks[] = [
                'key' => 'ca_transaction_representative_'.strtolower($format),
                'label' => 'Canada '.$format.' transaction representative ('.$caseKey.')',
                'required' => true,
                'status' => ($caseStatus['transaction_status'] ?? '') === 'passed' ? 'passed' : 'missing',
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
}
