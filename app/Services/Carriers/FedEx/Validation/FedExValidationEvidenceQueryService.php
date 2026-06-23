<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;

class FedExValidationEvidenceQueryService
{
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
