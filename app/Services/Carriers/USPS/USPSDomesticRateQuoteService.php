<?php

namespace App\Services\Carriers\USPS;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\CarrierRateQuote;
use App\Models\ShipmentPackage;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\CarrierApiEventLogger;
use App\Services\Carriers\DTO\CarrierApiResult;
use Illuminate\Support\Arr;

class USPSDomesticRateQuoteService
{
    public function __construct(
        private readonly USPSConfig $config,
        private readonly USPSHttpClient $httpClient,
        private readonly CarrierApiEventLogger $eventLogger,
    ) {
    }

    /**
     * @return array{result: CarrierApiResult, quote: CarrierRateQuote}
     */
    public function quotePackage(
        Store $store,
        CarrierAccount $account,
        ShipmentPackage $package,
        string $destinationPostalCode,
        string $accessToken,
        ?User $actor = null,
        ?string $mailClass = null,
    ): array {
        $originPostalCode = $this->resolveOriginPostalCode($package);
        $mailClass = $mailClass ?: $this->config->defaultMailClass();
        $priceType = $this->config->defaultPriceType();

        $payload = [
            'originZIPCode' => $originPostalCode,
            'destinationZIPCode' => $this->normalizeZip($destinationPostalCode),
            'weight' => (float) $package->weight_value,
            'length' => (float) ($package->length ?? 0),
            'width' => (float) ($package->width ?? 0),
            'height' => (float) ($package->height ?? 0),
            'mailClass' => $mailClass,
            'processingCategory' => 'MACHINABLE',
            'destinationEntryFacilityType' => 'NONE',
            'rateIndicator' => 'SP',
            'priceType' => $priceType,
            'mailingDate' => now()->toDateString(),
        ];

        if ($this->requiresCommercialAccount($priceType)) {
            $result = CarrierApiResult::failure(
                message: 'USPS commercial or EPS account pricing is not enabled in this phase. Use RETAIL test quotes only.',
                code: 'commercial_pricing_not_enabled',
                requestSummary: [
                    'endpoint' => $this->config->domesticBaseRatesPath(),
                    'price_type' => $priceType,
                    'mail_class' => $mailClass,
                ],
            );

            $quote = $this->persistQuote($store, $account, $package, $destinationPostalCode, $originPostalCode, $mailClass, $result, $actor);

            return ['result' => $result, 'quote' => $quote];
        }

        $requestSummary = [
            'endpoint' => $this->config->domesticBaseRatesPath(),
            'environment' => $this->config->environment(),
            'origin_postal_code' => $originPostalCode,
            'destination_postal_code' => $this->normalizeZip($destinationPostalCode),
            'mail_class' => $mailClass,
            'price_type' => $priceType,
            'weight' => (float) $package->weight_value,
            'package_id' => $package->id,
        ];

        $event = $this->eventLogger->start(
            store: $store,
            provider: CarrierAccount::PROVIDER_USPS,
            action: CarrierApiEvent::ACTION_DOMESTIC_RATE_QUOTE,
            account: $account,
            requestSummary: $requestSummary,
            environment: $this->config->environment(),
        );

        $result = $this->httpClient->postJson(
            path: $this->config->domesticBaseRatesPath(),
            payload: $payload,
            bearerToken: $accessToken,
            requestSummary: $requestSummary,
        );

        if ($result->success) {
            $parsed = $this->parseRateResponse($result->data ?? [], $mailClass);
            $result = CarrierApiResult::success(
                data: array_merge($result->data ?? [], ['parsed' => $parsed]),
                requestId: $result->requestId,
                durationMs: $result->durationMs,
                requestSummary: $result->requestSummary,
                responseSummary: array_merge($result->responseSummary ?? [], [
                    'service_code' => $parsed['service_code'] ?? null,
                    'amount' => $parsed['amount'] ?? null,
                    'zone' => $parsed['zone'] ?? null,
                    'warnings_count' => count($parsed['warnings'] ?? []),
                ]),
            );
        }

        $this->eventLogger->complete($event, $result);
        $quote = $this->persistQuote($store, $account, $package, $destinationPostalCode, $originPostalCode, $mailClass, $result, $actor);

        return ['result' => $result, 'quote' => $quote];
    }

    private function requiresCommercialAccount(string $priceType): bool
    {
        return strtoupper($priceType) === 'COMMERCIAL'
            || $this->config->platformLabelPurchaseEnabled();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function parseRateResponse(array $data, string $mailClass): array
    {
        $rate = Arr::get($data, 'rates.0', []);

        return [
            'service_code' => (string) ($rate['mailClass'] ?? $mailClass),
            'service_name' => (string) ($rate['description'] ?? $mailClass),
            'amount' => isset($rate['price']) ? (float) $rate['price'] : (isset($data['totalBasePrice']) ? (float) $data['totalBasePrice'] : null),
            'currency' => 'USD',
            'zone' => $rate['zone'] ?? null,
            'warnings' => $rate['warnings'] ?? [],
        ];
    }

    private function persistQuote(
        Store $store,
        CarrierAccount $account,
        ShipmentPackage $package,
        string $destinationPostalCode,
        ?string $originPostalCode,
        string $mailClass,
        CarrierApiResult $result,
        ?User $actor,
    ): CarrierRateQuote {
        $parsed = $result->success ? ($result->data['parsed'] ?? $this->parseRateResponse($result->data ?? [], $mailClass)) : [];

        return CarrierRateQuote::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'shipment_id' => $package->shipment_id,
            'order_id' => $package->order_id,
            'package_id' => $package->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => $account->environment,
            'origin_postal_code' => $originPostalCode,
            'destination_postal_code' => $this->normalizeZip($destinationPostalCode),
            'service_code' => $parsed['service_code'] ?? $mailClass,
            'service_name' => $parsed['service_name'] ?? null,
            'amount' => $parsed['amount'] ?? null,
            'currency' => 'USD',
            'status' => $result->success ? CarrierRateQuote::STATUS_SUCCEEDED : CarrierRateQuote::STATUS_FAILED,
            'request_summary' => $result->requestSummary,
            'response_summary' => $result->responseSummary,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'created_by' => $actor?->id,
        ]);
    }

    private function resolveOriginPostalCode(ShipmentPackage $package): ?string
    {
        $package->loadMissing('originLocation');

        if ($package->originLocation) {
            return $this->normalizeZip($package->originLocation->postal_code);
        }

        return null;
    }

    private function normalizeZip(?string $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        return substr(preg_replace('/\D+/', '', $postalCode), 0, 5) ?: null;
    }
}
