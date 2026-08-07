<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Presenters\FedExMerchantCheckPresenter;
use App\Services\Carriers\FedEx\Support\FedExConfig;

class FedExAddressValidationService
{
    public function __construct(
        private readonly FedExConfig $config,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExOperationGuard $guard,
    ) {}

    /**
     * @param  array<string, mixed>  $addressInput
     * @return array{
     *     result: CarrierApiResult,
     *     presentation: array<string, mixed>,
     *     normalized: array<string, mixed>|null,
     *     suggestions: list<array<string, mixed>>
     * }
     */
    public function validateAddress(
        Store $store,
        CarrierAccount $account,
        array $addressInput,
        bool $enforceProductionGuard = false,
    ): array {
        if ($enforceProductionGuard) {
            $this->guard->assertAccountForOperation(
                $store,
                $account,
                FedExOperationGuard::CAPABILITY_ADDRESS_VALIDATION,
            );
        } else {
            $this->apiClient->assertFedExApiAccount($account, $store);
        }

        $streetLines = array_values(array_filter([
            trim((string) ($addressInput['address_line1'] ?? '')),
            trim((string) ($addressInput['address_line2'] ?? '')),
        ]));

        if ($streetLines === []) {
            $result = CarrierApiResult::failure(
                message: FedExSafeExceptionMapper::merchantMessage('missing_address', 'Address line 1 is required.'),
                code: 'missing_address',
                requestSummary: ['local_validation' => true],
            );

            return [
                'result' => $result,
                'presentation' => FedExMerchantCheckPresenter::addressValidation(null),
                'normalized' => null,
                'suggestions' => [],
            ];
        }

        $countryCode = strtoupper(trim((string) ($addressInput['country_code'] ?? 'US')));

        if ($enforceProductionGuard) {
            // Address validation itself supports many countries per FedEx docs;
            // production merchant ops for Steps 3–4 stay US/CA (origin=destination country gate).
            $this->guard->assertOriginDestinationAllowed($countryCode, $countryCode, $account->environment);
        }

        $endpoint = $this->config->addressValidationPath();
        $customerTransactionId = $this->guard->idempotencyKey(
            $store,
            $account,
            'address_validation',
            implode(':', [
                $countryCode,
                strtoupper(trim((string) ($addressInput['state'] ?? ''))),
                trim((string) ($addressInput['postal_code'] ?? '')),
                md5(implode('|', $streetLines)),
            ]),
        );

        $requestSummary = array_merge(
            $this->apiClient->baseRequestSummary($account, $endpoint),
            [
                'action' => CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION,
                'operation' => $enforceProductionGuard ? 'merchant_address_validation' : 'diagnostic_address_validation',
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
                'customer_transaction_id' => $customerTransactionId,
                'platform_fallback_used' => false,
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
            context: $enforceProductionGuard
                ? null
                : new FedExValidationEventContext(scenarioKey: 'address_validation'),
            customerTransactionId: $customerTransactionId,
        );

        $presentation = FedExMerchantCheckPresenter::addressValidation($result->data, $countryCode);
        $suggestions = is_array($presentation['resolved_addresses'] ?? null)
            ? $presentation['resolved_addresses']
            : [];
        $normalized = $this->preferredNormalizedAddress($suggestions, $addressInput, $countryCode);

        if ($result->success) {
            $responseSummary = array_merge($result->responseSummary ?? [], [
                'resolved_address_count' => $presentation['resolved_count'],
                'matching_suggestion_count' => $presentation['matching_count'],
                'ignored_suggestion_count' => $presentation['ignored_suggestion_count'],
                'ignored_country_codes' => $presentation['ignored_country_codes'],
                'classification' => $normalized['classification'] ?? null,
                'residential' => $normalized['residential'] ?? null,
            ]);

            $result = $result->copyWith(responseSummary: $responseSummary);
        } elseif ($result->errorMessage) {
            $result = CarrierApiResult::failure(
                message: FedExSafeExceptionMapper::merchantMessage(
                    $result->errorCode,
                    $result->errorMessage,
                    (int) data_get($result->responseSummary, 'http_status') ?: null,
                ),
                code: $result->errorCode,
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: $result->requestSummary,
                responseSummary: $result->responseSummary,
                evidence: $result->evidence,
            );
        }

        return [
            'result' => $result,
            'presentation' => $presentation,
            'normalized' => $normalized,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $suggestions
     * @param  array<string, mixed>  $addressInput
     * @return array<string, mixed>|null
     */
    private function preferredNormalizedAddress(array $suggestions, array $addressInput, string $countryCode): ?array
    {
        $best = $suggestions[0] ?? null;
        if (! is_array($best)) {
            return null;
        }

        $classification = strtoupper((string) ($best['classification'] ?? ''));
        $residentialAttr = $best['residential'] ?? null;
        $isResidential = is_bool($residentialAttr)
            ? $residentialAttr
            : in_array($classification, ['RESIDENTIAL', 'MIXED'], true);

        return [
            'address_line1' => $best['street'] ?? ($addressInput['address_line1'] ?? null),
            'address_line2' => $addressInput['address_line2'] ?? null,
            'city' => $best['city'] ?? ($addressInput['city'] ?? null),
            'state' => $best['state'] ?? ($addressInput['state'] ?? null),
            'postal_code' => $best['postal_code'] ?? ($addressInput['postal_code'] ?? null),
            'country_code' => $best['country_code'] ?? $countryCode,
            'classification' => $classification !== '' ? $classification : null,
            'residential' => $isResidential,
            'source' => 'fedex_address_validation',
            'review_required' => true,
        ];
    }
}
