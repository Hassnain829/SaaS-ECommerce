<?php

namespace App\Services\Carriers\Core;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\DTO\FedExValidationEventContext;
use App\Services\Carriers\FedEx\Validation\FedExSensitiveFieldClassifier;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;

class CarrierApiEventLogger
{
    public function __construct(
        private readonly FedExValidationEvidenceSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>|null  $requestSummary
     */
    public function start(
        Store $store,
        string $provider,
        string $action,
        ?CarrierAccount $account = null,
        ?array $requestSummary = null,
        string $environment = 'sandbox',
        ?FedExValidationEventContext $context = null,
    ): CarrierApiEvent {
        return CarrierApiEvent::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_account_id' => $account?->id,
            'provider' => $provider,
            'environment' => $environment,
            'action' => $action,
            'status' => CarrierApiEvent::STATUS_STARTED,
            'request_summary' => $this->maskSummary($requestSummary),
        ], $context?->toEventAttributes() ?? []));
    }

    public function complete(CarrierApiEvent $event, CarrierApiResult $result): CarrierApiEvent
    {
        $evidence = $result->evidence;
        $httpStatus = $evidence?->httpStatus ?? data_get($result->responseSummary, 'http_status');
        $transactionId = $evidence?->fedexTransactionId ?? data_get($result->responseSummary, 'fedex_transaction_id');

        $payload = [
            'status' => $result->success ? CarrierApiEvent::STATUS_SUCCEEDED : CarrierApiEvent::STATUS_FAILED,
            'request_id' => $result->requestId,
            'duration_ms' => $result->durationMs,
            'request_summary' => $this->maskSummary($result->requestSummary ?? $event->request_summary),
            'response_summary' => $this->maskSummary($result->responseSummary),
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'http_status' => is_numeric($httpStatus) ? (int) $httpStatus : null,
            'fedex_transaction_id' => is_string($transactionId) ? $transactionId : null,
        ];

        if ($evidence !== null) {
            $payload = array_merge($payload, [
                'endpoint' => $evidence->endpoint,
                'http_method' => $evidence->httpMethod,
                'request_headers_encrypted' => $this->sanitizer->sanitizeHeaders($evidence->requestHeaders),
                'request_body_encrypted' => $this->sanitizer->sanitize($evidence->requestBody),
                'response_headers_encrypted' => $this->sanitizer->sanitizeHeaders($evidence->responseHeaders),
                'response_body_encrypted' => $this->sanitizer->sanitize($evidence->responseBody),
                'evidence_recorded_at' => now(),
            ]);
        }

        $event->update($payload);

        return $event->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>|null
     */
    public function maskSummary(?array $summary): ?array
    {
        if ($summary === null) {
            return null;
        }

        $masked = [];

        foreach ($summary as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (is_array($value)) {
                $masked[$key] = $this->maskSummary($value);

                continue;
            }

            if ($this->isSensitiveKey($normalizedKey) && ! str_ends_with($normalizedKey, '_last4')) {
                $masked[$key] = '[redacted]';

                continue;
            }

            if ($normalizedKey === 'account_number' || $normalizedKey === 'provider_account_number') {
                $masked[$key] = $this->maskAccountNumber((string) $value);

                continue;
            }

            if (in_array($normalizedKey, ['email', 'phone', 'authorization'], true)) {
                $masked[$key] = '[redacted]';

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    private function isSensitiveKey(string $key): bool
    {
        if (FedExSensitiveFieldClassifier::isSensitiveKey($key)) {
            return true;
        }

        $normalizedKey = FedExSensitiveFieldClassifier::normalizeKey($key);

        return in_array($normalizedKey, [
            'clientid',
            'consumerkey',
            'consumersecret',
        ], true);
    }

    private function maskAccountNumber(string $number): string
    {
        if ($number === '') {
            return '—';
        }

        if (strlen($number) <= 4) {
            return str_repeat('*', strlen($number));
        }

        return str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4);
    }
}
