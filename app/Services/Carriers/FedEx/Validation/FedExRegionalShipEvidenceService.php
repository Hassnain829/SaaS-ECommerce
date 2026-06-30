<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;

class FedExRegionalShipEvidenceService
{
    public function __construct(
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
        private readonly FedExShipEvidenceRules $evidenceRules,
        private readonly FedExShipFixtureResolver $fixtureResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function regionSummary(Store $store, CarrierAccount $account, string $region): array
    {
        $cases = FedExGlobalShipCaseCatalog::casesByRegion()[strtoupper($region)] ?? [];
        $statuses = [];

        foreach ($cases as $case) {
            $key = (string) ($case['case_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $statuses[$key] = $this->workspaceStatus($store, $account, $region, $key);
        }

        $labelPass = collect($statuses)->filter(fn (array $status): bool => ($status['transaction_status'] ?? '') === 'passed')->count();
        $scanPass = collect($statuses)->filter(function (array $status): bool {
            [$generated, $expected] = array_pad(explode(' of ', (string) ($status['printed_scans'] ?? '0 of 0')), 2, '0');

            return (int) trim($generated) >= (int) trim($expected) && (int) trim($expected) > 0;
        })->count();

        $representatives = FedExGlobalShipCaseCatalog::transactionRepresentatives($region);

        return [
            'region' => strtoupper($region),
            'region_label' => $this->regionLabel($region),
            'required_cases' => count($cases),
            'labels_passed' => $labelPass,
            'scans_passed' => $scanPass,
            'transaction_representatives' => $representatives,
            'case_statuses' => $statuses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaceStatus(Store $store, CarrierAccount $account, string $region, string $testCaseKey): array
    {
        $meta = FedExValidationScenarioCatalog::globalShipScenarios()[$testCaseKey] ?? [];
        $canonicalRun = $this->evidenceQuery->canonicalGlobalShipRun($store, $account, $region, $testCaseKey);
        $scenarioKey = (string) ($meta['scenario_key'] ?? FedExValidationScenarioCatalog::globalScenarioKey($testCaseKey));
        $labelFormat = (string) ($meta['label_format'] ?? $this->fixtureResolver->lockedLabelFormat($testCaseKey));

        $latestAttempt = $this->evidenceQuery->latestGlobalShipLabelAttempt(
            $store,
            $account,
            $region,
            $scenarioKey,
            $testCaseKey,
            $labelFormat,
        );

        $canonicalEvent = $canonicalRun['event'] ?? null;
        $displayEvent = $canonicalEvent ?? $latestAttempt;
        $expected = $this->evidenceRules->expectedMetadata($testCaseKey);
        $validation = $canonicalRun['validation'] ?? null;

        $generatedCount = count($canonicalRun['generated_labels'] ?? []);
        $scanCount = count($canonicalRun['printed_scans'] ?? []);
        $expectedPackages = (int) $expected['expected_package_count'];

        $caseDef = collect(FedExGlobalShipCaseCatalog::casesByRegion()[strtoupper($region)] ?? [])
            ->firstWhere('case_key', $testCaseKey);

        return [
            'test_case_key' => $testCaseKey,
            'validation_region' => strtoupper($region),
            'label' => (string) ($this->fixtureResolver->fixture($testCaseKey)['label'] ?? $testCaseKey),
            'expected_service_type' => $expected['expected_service_type'],
            'expected_label_format' => $expected['expected_label_format'],
            'expected_package_count' => $expectedPackages,
            'transaction_representative' => (bool) ($caseDef['transaction_representative'] ?? false),
            'latest_attempt_event_id' => $latestAttempt?->id,
            'canonical_event_id' => $canonicalEvent?->id,
            'display_event_id' => $displayEvent?->id,
            'transaction_status' => $this->transactionStatus($canonicalEvent, $latestAttempt),
            'service_match_status' => ($validation['response_service_matches'] ?? false) ? 'passed' : ($displayEvent ? 'failed' : 'not_tested'),
            'response_service_type' => $validation['response_service_type'] ?? data_get($displayEvent?->response_summary, 'response_service_type'),
            'generated_labels' => $generatedCount.' of '.$expectedPackages,
            'printed_scans' => $scanCount.' of '.$expectedPackages,
            'generated_label_artifacts' => $canonicalRun['generated_labels'] ?? [],
            'printed_scan_artifacts' => $canonicalRun['printed_scans'] ?? [],
            'printing_instructions' => $this->printingInstructions($testCaseKey),
        ];
    }

    private function transactionStatus(?CarrierApiEvent $canonical, ?CarrierApiEvent $latest): string
    {
        if ($canonical !== null) {
            return 'passed';
        }

        return $latest === null ? 'not_tested' : 'failed';
    }

    private function regionLabel(string $region): string
    {
        return match (strtoupper($region)) {
            FedExGlobalShipCaseCatalog::REGION_CA => 'Canada',
            FedExGlobalShipCaseCatalog::REGION_LAC => 'Latin America & Caribbean',
            FedExGlobalShipCaseCatalog::REGION_AMEA => 'AMEA',
            FedExGlobalShipCaseCatalog::REGION_EU => 'Europe',
            default => strtoupper($region),
        };
    }

    private function printingInstructions(string $testCaseKey): string
    {
        return match ($testCaseKey) {
            'IntegratorCA02' => 'Print this PNG label on a laser printer at actual size. Scan the printed paper at 600 DPI or higher — not the downloaded API file.',
            'IntegratorCA05' => 'Print this ZPLII label on a compatible Zebra thermal printer. Scan the printed label at 600 DPI or higher.',
            default => 'Print the downloaded label before scanning. Use actual size / no scaling on laser stock where applicable.',
        };
    }
}
