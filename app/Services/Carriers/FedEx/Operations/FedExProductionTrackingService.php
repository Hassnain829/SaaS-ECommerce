<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Shipment;
use App\Models\Store;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Auth\FedExAuthorizationClassifier;
use App\Services\Carriers\FedEx\DTO\FedExApiEventContext;
use App\Services\Carriers\FedEx\Support\FedExConfig;

/**
 * Production Basic Integrated Visibility tracking for merchant shipments.
 */
final class FedExProductionTrackingService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExOperationGuard $guard,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExTrackingResponseParser $trackingParser,
    ) {}

    /**
     * @return array{
     *     result: CarrierApiResult,
     *     timeline: list<array<string, mixed>>,
     *     status: ?string,
     *     estimated_delivery: ?string,
     *     delivered_at: ?string,
     *     exception: ?string,
     *     merchant_message: string
     * }
     */
    public function track(Store $store, CarrierAccount $account, string $trackingNumber): array
    {
        $this->guard->assertAccountForOperation($store, $account, FedExOperationGuard::CAPABILITY_TRACKING);

        $trackingNumber = trim($trackingNumber);
        abort_unless($trackingNumber !== '', 422, 'A tracking number is required.');

        $endpoint = $this->config->basicIntegratedVisibilityPath();
        if ($endpoint === null) {
            $result = CarrierApiResult::failure(
                message: 'FedEx tracking path is not configured.',
                code: 'tracking_path_not_configured',
            );

            return [
                'result' => $result,
                'timeline' => [],
                'status' => null,
                'estimated_delivery' => null,
                'delivered_at' => null,
                'exception' => null,
                'merchant_message' => 'FedEx tracking is not configured yet.',
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

        $customerTxn = $this->guard->idempotencyKey(
            $store,
            $account,
            'track',
            $trackingNumber.'|'.now()->format('YmdHi'),
        );

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => CarrierApiEvent::ACTION_FEDEX_BASIC_INTEGRATED_VISIBILITY,
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
                context: new FedExApiEventContext(scenarioKey: 'production_tracking'),
                customerTransactionId: $customerTxn,
            ),
            $endpoint,
        );

        $parsed = $this->trackingParser->parse(is_array($result->data) ? $result->data : null);
        $httpStatus = (int) data_get($result->responseSummary, 'http_status');

        return [
            'result' => $result,
            'timeline' => $parsed['timeline'],
            'status' => $parsed['status'],
            'estimated_delivery' => $parsed['estimated_delivery'],
            'delivered_at' => $parsed['delivered_at'],
            'exception' => $parsed['exception'],
            'merchant_message' => $result->success
                ? 'Tracking updated.'
                : FedExSafeExceptionMapper::merchantMessage(
                    $result->errorCode,
                    $result->errorMessage,
                    $httpStatus > 0 ? $httpStatus : null,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trackShipment(Store $store, CarrierAccount $account, Shipment $shipment): array
    {
        abort_unless((int) $shipment->store_id === (int) $store->id, 404);
        $trackingNumber = trim((string) $shipment->tracking_number);
        abort_unless($trackingNumber !== '', 422, 'This shipment has no tracking number yet.');

        return $this->track($store, $account, $trackingNumber);
    }
}
