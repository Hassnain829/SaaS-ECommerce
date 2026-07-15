<?php

namespace App\Services\Carriers\FedEx\Presenters;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceQueryService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;

class FedExValidationWorkspaceCardPresenter
{
    public function __construct(
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $preflight
     * @return list<array<string, mixed>>
     */
    public function cards(Store $store, CarrierAccount $account, array $preflight): array
    {
        $checksByKey = collect($preflight['checks'] ?? [])->keyBy('key');

        $cards = [];

        foreach (FedExValidationScenarioCatalog::registrationScenarios() as $scenarioKey => $meta) {
            $check = $checksByKey->get($scenarioKey);
            $event = $this->eventFromCheck($store, $account, $check, $scenarioKey, mfaMethod: $meta['mfa_method']);

            $cards[] = $this->buildCard(
                key: $scenarioKey,
                label: $meta['label'],
                baseline: 'FedEx registration/MFA workbook step',
                check: $check,
                event: $event,
            );
        }

        foreach ([
            'address_validation' => 'IntegratorUS02 recipient baseline address',
            'service_availability' => 'Default origin to IntegratorUS02 destination',
            'rate_quote' => 'Comprehensive rate quote baseline package',
            'tracking' => 'Basic Integrated Visibility with includeDetailedScans=true',
            'ship_cancel' => 'Cancel a sandbox shipment by tracking number',
            'trade_documents' => 'Upload trade document for a sandbox shipment',
        ] as $key => $baseline) {
            if (! $checksByKey->has($key)) {
                continue;
            }

            $scenarioKey = $key === 'trade_documents' ? 'trade_documents_upload' : ($key === 'tracking' ? 'basic_integrated_visibility' : $key);
            $cards[] = $this->buildCard(
                key: $key,
                label: (string) ($checksByKey->get($key)['label'] ?? $key),
                baseline: $baseline,
                check: $checksByKey->get($key),
                event: $this->eventFromCheck($store, $account, $checksByKey->get($key), $scenarioKey),
            );
        }

        foreach (FedExValidationScenarioCatalog::requiredLockedShipScenarios() as $testCaseKey => $meta) {
            $scenarioKey = (string) $meta['scenario_key'];
            $eventCheck = $checksByKey->get($scenarioKey.'_event');
            $event = $this->eventFromCheck($store, $account, $eventCheck, $scenarioKey, testCaseKey: $testCaseKey, labelFormat: (string) $meta['label_format']);

            $cards[] = array_merge($this->buildCard(
                key: $scenarioKey.'_event',
                label: $testCaseKey.' locked ship label',
                baseline: $testCaseKey.' · '.$meta['label_format'].' · '.$meta['label_stock_type'].' · '.$meta['expected_packages'].' package(s)',
                check: $eventCheck,
                event: $event,
            ), [
                'artifacts' => $this->artifactSummaries($checksByKey, $scenarioKey, (int) $meta['expected_packages']),
            ]);
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>|null  $check
     * @return array<string, mixed>
     */
    private function buildCard(string $key, string $label, string $baseline, ?array $check, ?CarrierApiEvent $event): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'baseline' => $baseline,
            'status' => (string) ($check['status'] ?? 'not_tested'),
            'explanation' => (string) ($check['explanation'] ?? ''),
            'event_id' => $event?->id ?? ($check['event_id'] ?? null),
            'http_status' => $event?->http_status,
            'fedex_transaction_id' => $event?->fedex_transaction_id,
            'endpoint' => $event?->endpoint,
            'http_method' => $event?->http_method,
            'has_request_json' => $event !== null && $event->hasCompleteEvidence() && filled($event->request_body_encrypted),
            'has_response_json' => $event !== null && $event->hasCompleteEvidence() && filled($event->response_body_encrypted),
            'executed_at' => $event?->evidence_recorded_at?->toIso8601String() ?? $event?->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $checksByKey
     * @return list<array<string, mixed>>
     */
    private function artifactSummaries(\Illuminate\Support\Collection $checksByKey, string $scenarioKey, int $expectedPackages): array
    {
        $artifacts = [];

        for ($sequence = 1; $sequence <= $expectedPackages; $sequence++) {
            foreach (['label', 'scan'] as $type) {
                $check = $checksByKey->get($scenarioKey.'_'.$type.'_'.$sequence);
                if ($check === null) {
                    continue;
                }

                $artifacts[] = [
                    'type' => $type,
                    'package_sequence' => $sequence,
                    'status' => $check['status'] ?? 'incomplete',
                    'artifact_id' => $check['artifact_id'] ?? null,
                ];
            }
        }

        return $artifacts;
    }

    /**
     * @param  array<string, mixed>|null  $check
     */
    private function eventFromCheck(
        Store $store,
        CarrierAccount $account,
        ?array $check,
        string $scenarioKey,
        ?string $testCaseKey = null,
        ?string $labelFormat = null,
        ?string $mfaMethod = null,
    ): ?CarrierApiEvent {
        $eventId = $check['event_id'] ?? null;
        if ($eventId) {
            return $this->evidenceQuery->eventById($store, $account, (int) $eventId);
        }

        if ($testCaseKey !== null && $labelFormat !== null) {
            return $this->evidenceQuery->canonicalShipLabelEvent(
                $store,
                $account,
                $scenarioKey,
                testCaseKey: $testCaseKey,
                labelFormat: $labelFormat,
            );
        }

        return $this->evidenceQuery->canonicalSuccessfulEvent(
            $store,
            $account,
            $scenarioKey,
            testCaseKey: $testCaseKey,
            labelFormat: $labelFormat,
            mfaMethod: $mfaMethod,
        );
    }
}
