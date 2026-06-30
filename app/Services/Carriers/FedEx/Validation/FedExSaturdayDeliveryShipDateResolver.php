<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Operations\FedExMerchantApiClient;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Support\FedExConfig;

/**
 * Finds a Friday ship date FedEx accepts with SATURDAY_DELIVERY.
 *
 * Sandbox often rejects the immediate next Friday (ORGORDEST.SPECIALSERVICES.NOTALLOWED)
 * while accepting a later Friday inside FedEx's near-future ship-date window.
 */
final class FedExSaturdayDeliveryShipDateResolver
{
    private const MAX_CANDIDATE_WEEKS = 4;

    public function __construct(
        private readonly FedExShipTestCaseFixtureService $fixtureService,
        private readonly FedExShipPayloadFactory $payloadFactory,
        private readonly FedExMerchantApiClient $apiClient,
        private readonly FedExConfig $config,
    ) {}

    /**
     * @param  array<string, mixed>  $fixture
     */
    public function resolve(
        Store $store,
        CarrierAccount $account,
        array $fixture,
        string $labelFormat,
    ): string {
        $heuristic = $this->fixtureService->nextSaturdayDeliveryFriday();

        if (! $this->config->allowsShipLabelGeneration($account->environment)) {
            return $heuristic;
        }

        foreach ($this->fixtureService->saturdayDeliveryFridayCandidates() as $shipDate) {
            if ($this->passesSaturdayDeliveryValidate($store, $account, $fixture, $labelFormat, $shipDate)) {
                return $shipDate;
            }
        }

        return $heuristic;
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function passesSaturdayDeliveryValidate(
        Store $store,
        CarrierAccount $account,
        array $fixture,
        string $labelFormat,
        string $shipDate,
    ): bool {
        $payload = $this->payloadFactory->buildShipmentPayload(
            $account,
            $fixture,
            $labelFormat,
            ['ship_date' => $shipDate],
        );

        $result = $this->apiClient->postJson(
            store: $store,
            account: $account,
            action: CarrierApiEvent::ACTION_FEDEX_SHIP_VALIDATE,
            path: $this->config->shipValidatePath($account->environment),
            payload: $payload,
            requestSummary: [
                'saturday_delivery_ship_date_probe' => true,
                'ship_date' => $shipDate,
                'test_case' => $fixture['key'] ?? null,
            ],
            context: new FedExValidationEventContext(
                scenarioKey: (string) ($fixture['scenario_key'] ?? 'saturday_delivery_ship_date_probe'),
            ),
        );

        return $result->success;
    }
}
