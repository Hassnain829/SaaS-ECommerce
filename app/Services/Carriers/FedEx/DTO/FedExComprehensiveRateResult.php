<?php

namespace App\Services\Carriers\FedEx\DTO;

final class FedExComprehensiveRateResult
{
    /**
     * @param  list<array<string, mixed>>  $availableRates
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?int $httpStatus,
        public readonly ?string $transactionId,
        public readonly ?string $serviceType,
        public readonly ?string $serviceName,
        public readonly ?string $rateType,
        public readonly ?string $currency,
        public readonly ?string $amount,
        public readonly ?string $responseAmountPath,
        public readonly array $availableRates,
        public readonly array $errors,
        public readonly ?string $accessState,
        public readonly ?int $eventId,
        public readonly ?string $fedexErrorCode = null,
        public readonly ?string $fedexErrorMessage = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toResponseSummary(): array
    {
        return array_filter([
            'fixture_case_key' => 'comprehensive_rate_baseline',
            'service_type' => $this->serviceType,
            'service_name' => $this->serviceName,
            'rate_type' => $this->rateType,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'response_amount_path' => $this->responseAmountPath,
            'rates_found' => count($this->availableRates),
            'access_state' => $this->accessState,
            'fedex_error_code' => $this->fedexErrorCode,
            'fedex_error_message' => $this->fedexErrorMessage,
            'ui_amount' => $this->amount,
            'ui_currency' => $this->currency,
            'ui_matches_response' => $this->successful && $this->amount !== null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
