<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;

class FedExBasicIntegratedVisibilityService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
    ) {}

    /**
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>}
     */
    public function trackShipment(Store $store, CarrierAccount $account, string $trackingNumber): array
    {
        $trackingNumber = trim($trackingNumber);
        $endpoint = $this->config->basicIntegratedVisibilityPath();

        if ($endpoint === null) {
            $result = CarrierApiResult::failure(
                message: 'FedEx Basic Integrated Visibility path is not configured. Set FEDEX_BASIC_INTEGRATED_VISIBILITY_PATH from the FedEx Developer Portal before running tracking validation.',
                code: 'tracking_path_not_configured',
                requestSummary: [
                    'local_validation' => true,
                    'tracking_number_last4' => strlen($trackingNumber) >= 4 ? substr($trackingNumber, -4) : null,
                ],
            );

            return [
                'result' => $result,
                'presentation' => FedExMerchantCheckPresenter::tracking($result, $trackingNumber),
            ];
        }

        $payload = [
            'trackingInfo' => [[
                'trackingNumberInfo' => [
                    'trackingNumber' => $trackingNumber,
                ],
            ]],
            'includeDetailedScans' => true,
        ];

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'tracking_number_last4' => strlen($trackingNumber) >= 4 ? substr($trackingNumber, -4) : null,
            ],
        );

        $result = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_BASIC_INTEGRATED_VISIBILITY,
                path: $endpoint,
                payload: $payload,
                requestSummary: $requestSummary,
                context: new FedExValidationEventContext(scenarioKey: 'basic_integrated_visibility'),
            ),
            $endpoint,
        );

        return [
            'result' => $result,
            'presentation' => FedExMerchantCheckPresenter::tracking($result, $trackingNumber),
        ];
    }
}
