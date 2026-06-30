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
        return $this->firstCanonicalCandidate(
            $this->canonicalCandidates($store, $account, $scenarioKey, $testCaseKey, $labelFormat, $mfaMethod),
        );
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
        return $this->firstCanonicalCandidate(
            $this->canonicalCandidates($store, $account, $scenarioKey, $testCaseKey, $labelFormat, null)
                ->where('action', CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL),
        );
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
    ) {
        return $this->baseQuery($store, $account)
            ->where('scenario_key', $scenarioKey)
            ->when($testCaseKey !== null, fn (Builder $q) => $q->where('test_case_key', $testCaseKey))
            ->when($labelFormat !== null, fn (Builder $q) => $q->where('label_format', strtoupper($labelFormat)))
            ->when($mfaMethod !== null, fn (Builder $q) => $q->where('mfa_method', strtolower($mfaMethod)))
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CarrierApiEvent>  $candidates
     */
    private function firstCanonicalCandidate($candidates): ?CarrierApiEvent
    {
        return $candidates->first(fn (CarrierApiEvent $event): bool => $event->hasCompleteEvidence() && $event->isSuccessfulHttp())
            ?? $candidates->first(fn (CarrierApiEvent $event): bool => $event->hasCompleteEvidence());
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
