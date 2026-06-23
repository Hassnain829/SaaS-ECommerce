<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;

class FedExValidationStatusPresenter
{
    public function __construct(
        private readonly FedExValidationPreflightService $preflight,
        private readonly FedExValidationEvidenceQueryService $evidenceQuery,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function capabilityMatrix(Store $store, CarrierAccount $account): array
    {
        $assessment = $this->preflight->assess($store, $account);
        $checksByKey = collect($assessment['checks'] ?? [])->keyBy('key');

        return [
            'connection_model' => $account->connection_model,
            'credentials_mode' => $account->usesFedExIntegratorProvider() ? 'integrator_child' : 'merchant_developer',
            'readiness' => [
                'ready' => (bool) ($assessment['ready'] ?? false),
                'completed_count' => (int) ($assessment['completed_count'] ?? 0),
                'total_count' => (int) ($assessment['total_count'] ?? 0),
                'percentage' => (int) ($assessment['percentage'] ?? 0),
            ],
            'registration' => $this->aggregateRegistrationStatus($checksByKey),
            'address_validation' => $this->checkStatus($checksByKey->get('address_validation'), 'address_validation', $store, $account, CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION),
            'service_availability' => $this->checkStatus($checksByKey->get('service_availability'), 'service_availability', $store, $account, CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY),
            'rate_quote' => $this->rateQuoteStatus($checksByKey->get('rate_quote'), $store, $account),
            'ship_validate' => $this->shipValidateStatus($store, $account),
            'ship_label_zpl' => $this->lockedShipLabelStatus($checksByKey, 'IntegratorUS02', 'ship_us02_zplii', $store, $account),
            'ship_label_png' => $this->lockedShipLabelStatus($checksByKey, 'IntegratorUS04', 'ship_us04_png', $store, $account),
            'ship_label_pdf' => $this->lockedShipLabelStatus($checksByKey, 'IntegratorUS05', 'ship_us05_pdf_mps', $store, $account),
            'tracking' => $this->checkStatus($checksByKey->get('tracking'), 'tracking', $store, $account, CarrierApiEvent::ACTION_FEDEX_BASIC_INTEGRATED_VISIBILITY),
            'blockers' => $assessment['blockers'] ?? [],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $checksByKey
     * @return array<string, mixed>
     */
    private function aggregateRegistrationStatus(\Illuminate\Support\Collection $checksByKey): array
    {
        $registrationChecks = $checksByKey->filter(fn (array $check): bool => ($check['category'] ?? '') === 'registration_mfa');

        if ($registrationChecks->isEmpty()) {
            return ['status' => 'not_started', 'label' => 'Not started', 'detail' => null];
        }

        if ($registrationChecks->every(fn (array $check): bool => ($check['status'] ?? '') === 'passed')) {
            return ['status' => 'passed', 'label' => 'Registration evidence complete', 'detail' => null];
        }

        $missing = $registrationChecks->where('status', '!=', 'passed')->count();

        return [
            'status' => 'in_progress',
            'label' => 'Registration evidence incomplete',
            'detail' => $missing.' required registration/MFA scenario(s) still need successful evidence.',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $check
     * @return array<string, mixed>
     */
    private function checkStatus(?array $check, string $scenarioKey, Store $store, CarrierAccount $account, string $action): array
    {
        $event = $this->evidenceQuery->canonicalEvent($store, $account, $scenarioKey)
            ?? $this->evidenceQuery->latestByAction($store, $account, $action);

        return $this->mapCheckAndEvent($check, $event);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $checksByKey
     * @return array<string, mixed>
     */
    private function lockedShipLabelStatus(\Illuminate\Support\Collection $checksByKey, string $testCaseKey, string $scenarioKey, Store $store, CarrierAccount $account): array
    {
        $meta = FedExValidationScenarioCatalog::lockedShipScenarios()[$testCaseKey] ?? [];
        $labelFormat = (string) ($meta['label_format'] ?? '');
        $expectedPackages = (int) ($meta['expected_packages'] ?? 1);
        $eventCheck = $checksByKey->get($scenarioKey.'_event');

        $event = $this->evidenceQuery->canonicalShipLabelEvent(
            $store,
            $account,
            $scenarioKey,
            testCaseKey: $testCaseKey,
            labelFormat: $labelFormat,
        );

        $allPassed = ($eventCheck['status'] ?? '') === 'passed';

        for ($sequence = 1; $sequence <= $expectedPackages; $sequence++) {
            $labelCheck = $checksByKey->get($scenarioKey.'_label_'.$sequence);
            $scanCheck = $checksByKey->get($scenarioKey.'_scan_'.$sequence);

            if (($labelCheck['status'] ?? '') !== 'passed' || ($scanCheck['status'] ?? '') !== 'passed') {
                $allPassed = false;
            }
        }

        if ($allPassed) {
            return [
                'status' => 'passed',
                'label' => $testCaseKey.' complete',
                'http_status' => $event?->http_status ?? data_get($event?->response_summary, 'http_status'),
                'detail' => null,
            ];
        }

        if ($event !== null && ! $event->isSuccessfulHttp()) {
            return $this->mapCheckAndEvent($eventCheck, $event);
        }

        $labelCheck = $checksByKey->get($scenarioKey.'_label_1');
        $scanCheck = $checksByKey->get($scenarioKey.'_scan_1');

        return [
            'status' => match ($eventCheck['status'] ?? 'missing') {
                'passed' => 'in_progress',
                default => 'not_run',
            },
            'label' => $testCaseKey.' ('.$labelFormat.')',
            'http_status' => $event?->http_status,
            'detail' => collect([$eventCheck])
                ->merge(collect(range(1, $expectedPackages))->flatMap(fn (int $sequence): array => [
                    $checksByKey->get($scenarioKey.'_label_'.$sequence),
                    $checksByKey->get($scenarioKey.'_scan_'.$sequence),
                ]))
                ->filter(fn (?array $item): bool => is_array($item) && ($item['status'] ?? '') !== 'passed')
                ->pluck('explanation')
                ->filter()
                ->first(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rateQuoteStatus(?array $check, Store $store, CarrierAccount $account): array
    {
        $status = $this->checkStatus($check, 'rate_quote', $store, $account, CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE);

        if ($status['status'] === 'blocked') {
            $status['label'] = 'Blocked — entitlement pending';
            $status['detail'] = 'FedEx sandbox child credentials are not authorized for Comprehensive Rates in this environment. This is a FedEx entitlement blocker, not a local payload issue.';
        }

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function shipValidateStatus(Store $store, CarrierAccount $account): array
    {
        $event = $this->evidenceQuery->latestByAction($store, $account, CarrierApiEvent::ACTION_FEDEX_SHIP_VALIDATE);

        return $this->mapCheckAndEvent(null, $event);
    }

    /**
     * @param  array<string, mixed>|null  $check
     * @return array<string, mixed>
     */
    private function mapCheckAndEvent(?array $check, ?CarrierApiEvent $event): array
    {
        if ($event === null) {
            return [
                'status' => 'not_run',
                'label' => 'Not run',
                'http_status' => null,
                'detail' => $check['explanation'] ?? null,
            ];
        }

        $httpStatus = (int) ($event->http_status ?? data_get($event->response_summary, 'http_status'));
        $blocked = (bool) data_get($event->response_summary, 'authorization_blocked')
            || $event->error_code === 'fedex_authorization_blocked'
            || $httpStatus === 403;

        if ($event->status === CarrierApiEvent::STATUS_SUCCEEDED && $event->isSuccessfulHttp()) {
            return [
                'status' => 'passed',
                'label' => 'Passed',
                'http_status' => $httpStatus ?: 200,
                'detail' => null,
            ];
        }

        if ($blocked) {
            return [
                'status' => 'blocked',
                'label' => 'FedEx authorization blocked',
                'http_status' => $httpStatus ?: 403,
                'detail' => $event->error_message,
            ];
        }

        return [
            'status' => ($check['status'] ?? '') === 'passed' ? 'passed' : 'failed',
            'label' => ($check['status'] ?? '') === 'passed' ? 'Passed' : 'Failed',
            'http_status' => $httpStatus ?: null,
            'detail' => $event->error_message ?? ($check['explanation'] ?? null),
        ];
    }
}
