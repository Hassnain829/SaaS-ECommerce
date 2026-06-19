<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\Store;

class FedExValidationStatusPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function capabilityMatrix(Store $store, CarrierAccount $account): array
    {
        $registrationSession = $account->registration_session_id
            ? CarrierAccountRegistrationSession::query()
                ->where('store_id', $store->id)
                ->whereKey($account->registration_session_id)
                ->first()
            : null;

        return [
            'connection_model' => $account->connection_model,
            'credentials_mode' => $account->usesFedExIntegratorProvider() ? 'integrator_child' : 'merchant_developer',
            'registration' => $this->registrationStatus($registrationSession, $account),
            'address_validation' => $this->eventStatus($store, $account, CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION),
            'service_availability' => $this->eventStatus($store, $account, CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY),
            'rate_quote' => $this->rateQuoteStatus($store, $account),
            'ship_validate' => $this->eventStatus($store, $account, CarrierApiEvent::ACTION_FEDEX_SHIP_VALIDATE),
            'ship_label_pdf' => $this->labelStatus($store, $account, 'ship_label_pdf'),
            'ship_label_png' => $this->labelStatus($store, $account, 'ship_label_png'),
            'ship_label_zpl' => $this->labelStatus($store, $account, 'ship_label_zpl'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationStatus(?CarrierAccountRegistrationSession $session, CarrierAccount $account): array
    {
        if ($account->connection_status === CarrierAccount::CONNECTION_CONNECTED) {
            return [
                'status' => 'passed',
                'label' => 'Registration complete',
                'detail' => 'Integrator child credentials stored.',
            ];
        }

        if ($session === null) {
            return [
                'status' => 'not_started',
                'label' => 'Not started',
                'detail' => null,
            ];
        }

        return [
            'status' => in_array($session->status, ['completed', 'connected'], true) ? 'passed' : 'in_progress',
            'label' => str($session->status)->replace('_', ' ')->title(),
            'detail' => $session->last_error_message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventStatus(Store $store, CarrierAccount $account, string $action): array
    {
        $event = $this->latestEvent($store, $account, $action);

        if ($event === null) {
            return [
                'status' => 'not_run',
                'label' => 'Not run',
                'http_status' => null,
                'detail' => null,
            ];
        }

        $httpStatus = (int) data_get($event->response_summary, 'http_status');
        $blocked = (bool) data_get($event->response_summary, 'authorization_blocked')
            || $event->error_message !== null && str_contains(strtolower((string) $event->error_message), 'not authorized');

        if ($event->status === CarrierApiEvent::STATUS_SUCCEEDED) {
            return [
                'status' => 'passed',
                'label' => 'Passed',
                'http_status' => $httpStatus ?: 200,
                'detail' => null,
            ];
        }

        if ($httpStatus === 403 || $blocked) {
            return [
                'status' => 'blocked',
                'label' => 'FedEx authorization blocked',
                'http_status' => 403,
                'detail' => $event->error_message,
            ];
        }

        return [
            'status' => 'failed',
            'label' => 'Failed',
            'http_status' => $httpStatus ?: null,
            'detail' => $event->error_message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rateQuoteStatus(Store $store, CarrierAccount $account): array
    {
        $status = $this->eventStatus($store, $account, CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE);

        if ($status['status'] === 'blocked') {
            $status['label'] = 'Blocked — entitlement pending';
            $status['detail'] = 'FedEx sandbox child credentials are not authorized for Comprehensive Rates in this environment. This is a FedEx entitlement blocker, not a local payload issue.';
        }

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function labelStatus(Store $store, CarrierAccount $account, string $artifactType): array
    {
        $action = CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL;
        $event = $this->latestEvent($store, $account, $action);

        $artifactExists = \App\Models\FedExValidationArtifact::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('artifact_type', $artifactType)
            ->exists();

        if ($artifactExists) {
            return [
                'status' => 'passed',
                'label' => 'Label saved',
                'http_status' => data_get($event?->response_summary, 'http_status'),
                'detail' => null,
            ];
        }

        if ($event === null) {
            return [
                'status' => 'not_run',
                'label' => 'Not run',
                'http_status' => null,
                'detail' => null,
            ];
        }

        return $this->eventStatus($store, $account, $action);
    }

    private function latestEvent(Store $store, CarrierAccount $account, string $action): ?CarrierApiEvent
    {
        return CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('action', $action)
            ->latest('id')
            ->first();
    }
}
