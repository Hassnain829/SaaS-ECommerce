<?php

namespace App\Services\Carriers\FedEx;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;

class FedExAddressValidationService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
    ) {}

    /**
     * @param  array<string, mixed>  $addressInput
     * @return array{result: CarrierApiResult, presentation: array<string, mixed>}
     */
    public function validateAddress(Store $store, CarrierAccount $account, array $addressInput): array
    {
        $this->apiClient->assertMerchantCredentialsAccount($account);

        $streetLines = array_values(array_filter([
            trim((string) ($addressInput['address_line1'] ?? '')),
            trim((string) ($addressInput['address_line2'] ?? '')),
        ]));

        if ($streetLines === []) {
            $result = CarrierApiResult::failure(
                message: 'Address line 1 is required.',
                code: 'missing_address',
                requestSummary: ['local_validation' => true],
            );

            return ['result' => $result, 'presentation' => FedExMerchantCheckPresenter::addressValidation(null)];
        }

        $countryCode = strtoupper(trim((string) ($addressInput['country_code'] ?? 'US')));
        $endpoint = $this->config->addressValidationPath();

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
                'requested_country' => $countryCode,
                'requested_state' => strtoupper(trim((string) ($addressInput['state'] ?? ''))) ?: null,
                'requested_postal_code' => trim((string) ($addressInput['postal_code'] ?? '')) ?: null,
                'requested_city' => trim((string) ($addressInput['city'] ?? '')) ?: null,
                'destination_country' => $countryCode,
                'destination_state' => strtoupper(trim((string) ($addressInput['state'] ?? ''))) ?: null,
                'destination_postal_code' => trim((string) ($addressInput['postal_code'] ?? '')) ?: null,
                'destination_city' => trim((string) ($addressInput['city'] ?? '')) ?: null,
                'residential' => array_key_exists('residential', $addressInput)
                    ? (bool) $addressInput['residential']
                    : null,
            ],
        );

        $payload = [
            'addressesToValidate' => [
                [
                    'address' => array_filter([
                        'streetLines' => $streetLines,
                        'city' => trim((string) ($addressInput['city'] ?? '')) ?: null,
                        'stateOrProvinceCode' => strtoupper(trim((string) ($addressInput['state'] ?? ''))) ?: null,
                        'postalCode' => trim((string) ($addressInput['postal_code'] ?? '')) ?: null,
                        'countryCode' => $countryCode,
                    ]),
                ],
            ],
        ];

        $result = $this->apiClient->postJson(
            store: $store,
            account: $account,
            action: CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
            path: $endpoint,
            payload: $payload,
            requestSummary: $requestSummary,
            context: new FedExValidationEventContext(scenarioKey: 'address_validation'),
        );

        $presentation = FedExMerchantCheckPresenter::addressValidation($result->data, $countryCode);

        if ($result->success) {
            $responseSummary = array_merge($result->responseSummary ?? [], [
                'resolved_address_count' => $presentation['resolved_count'],
                'matching_suggestion_count' => $presentation['matching_count'],
                'ignored_suggestion_count' => $presentation['ignored_suggestion_count'],
                'ignored_country_codes' => $presentation['ignored_country_codes'],
            ]);

            $result = $result->copyWith(responseSummary: $responseSummary);
        }

        return ['result' => $result, 'presentation' => $presentation];
    }
}
