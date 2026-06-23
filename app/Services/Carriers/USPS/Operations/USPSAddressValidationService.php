<?php

namespace App\Services\Carriers\USPS\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\CarrierApiEventLogger;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\Carriers\USPS\Support\USPSHttpClient;

class USPSAddressValidationService
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSHttpClient $httpClient,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {}

    public function validateOriginLocation(
        Store $store,
        ?CarrierAccount $account,
        Location $location,
        string $accessToken,
    ): CarrierApiResult {
        $query = array_filter([
            'streetAddress' => $location->address_line1,
            'secondaryAddress' => $location->address_line2,
            'city' => $location->city,
            'state' => $location->state,
            'ZIPCode' => $this->normalizeZip($location->postal_code),
        ], fn ($value) => filled($value));

        $requestSummary = [
            'endpoint' => $this->config->addressValidationPath(),
            'environment' => $this->config->environment(),
            'city_present' => filled($location->city),
            'state_present' => filled($location->state),
            'postal_code_present' => filled($location->postal_code),
            'street_present' => filled($location->address_line1),
        ];

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_ADDRESS_VALIDATION,
            account: $account,
            requestSummary: $requestSummary,
            environment: $this->config->environment(),
        );

        $result = $this->httpClient->getJson(
            path: $this->config->addressValidationPath(),
            query: $query,
            bearerToken: $accessToken,
            requestSummary: $requestSummary,
        );

        if ($result->success) {
            $result = CarrierApiResult::success(
                data: $result->data,
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: $result->requestSummary,
                responseSummary: array_merge($result->responseSummary ?? [], [
                    'standardized_summary' => $this->standardizedSummary($result->data ?? []),
                ]),
            );
        }

        $this->eventLogger->complete($event, $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function standardizedSummary(array $data): array
    {
        $address = $data['address'] ?? $data;

        return array_filter([
            'street' => $address['streetAddress'] ?? $address['streetAddressAbbreviation'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'zip_code' => $address['ZIPCode'] ?? null,
            'zip_plus4' => $address['ZIPPlus4'] ?? null,
            'dpv_confirmation' => $data['additionalInfo']['DPVConfirmation'] ?? $address['DPVConfirmation'] ?? null,
            'corrections_present' => isset($data['corrections']) || isset($data['matches']),
        ], fn ($value) => $value !== null && $value !== false);
    }

    private function normalizeZip(?string $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        return substr(preg_replace('/\D+/', '', $postalCode), 0, 5) ?: null;
    }
}
