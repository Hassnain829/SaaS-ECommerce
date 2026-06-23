<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;

class FedExTradeDocumentsUploadService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
    ) {}

    /**
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>}
     */
    public function uploadTradeDocument(
        Store $store,
        CarrierAccount $account,
        string $trackingNumber,
        string $documentType = 'COMMERCIAL_INVOICE',
    ): array {
        $endpoint = $this->config->tradeDocumentsUploadPath();

        if ($endpoint === null) {
            $result = CarrierApiResult::failure(
                message: 'FedEx Trade Documents upload path is not configured. Set FEDEX_TRADE_DOCUMENTS_UPLOAD_PATH from the FedEx Developer Portal before running this validation step.',
                code: 'trade_documents_path_not_configured',
                requestSummary: [
                    'local_validation' => true,
                    'tracking_number_last4' => strlen(trim($trackingNumber)) >= 4 ? substr(trim($trackingNumber), -4) : null,
                ],
            );

            return [
                'result' => $result,
                'presentation' => FedExMerchantCheckPresenter::tradeDocuments($result, $trackingNumber),
            ];
        }

        $trackingNumber = trim($trackingNumber);
        $payload = [
            'workflowName' => 'ETDPreshipment',
            'carrierCode' => 'FDXE',
            'originCountryCode' => 'US',
            'destinationCountryCode' => 'US',
            'trackingNumber' => $trackingNumber,
            'documentType' => strtoupper($documentType),
        ];

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'tracking_number_last4' => strlen($trackingNumber) >= 4 ? substr($trackingNumber, -4) : null,
                'document_type' => strtoupper($documentType),
            ],
        );

        $result = FedExAuthorizationClassifier::applyBlockedClassification(
            $this->apiClient->postJson(
                store: $store,
                account: $account,
                action: CarrierApiEvent::ACTION_FEDEX_TRADE_DOCUMENTS_UPLOAD,
                path: $endpoint,
                payload: $payload,
                requestSummary: $requestSummary,
                context: new FedExValidationEventContext(scenarioKey: 'trade_documents_upload'),
            ),
            $endpoint,
        );

        return [
            'result' => $result,
            'presentation' => FedExMerchantCheckPresenter::tradeDocuments($result, $trackingNumber),
        ];
    }
}
