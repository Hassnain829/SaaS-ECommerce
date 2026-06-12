<?php

namespace App\Services\Carriers;

use App\Models\CarrierApiEvent;
use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\DTO\CarrierApiResult;

class CarrierApiEventLogger
{
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
    ): CarrierApiEvent {
        return CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account?->id,
            'provider' => $provider,
            'environment' => $environment,
            'action' => $action,
            'status' => CarrierApiEvent::STATUS_STARTED,
            'request_summary' => $this->maskSummary($requestSummary),
        ]);
    }

    public function complete(CarrierApiEvent $event, CarrierApiResult $result): CarrierApiEvent
    {
        $event->update([
            'status' => $result->success ? CarrierApiEvent::STATUS_SUCCEEDED : CarrierApiEvent::STATUS_FAILED,
            'request_id' => $result->requestId,
            'duration_ms' => $result->durationMs,
            'request_summary' => $this->maskSummary($result->requestSummary ?? $event->request_summary),
            'response_summary' => $this->maskSummary($result->responseSummary),
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
        ]);

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

            if ($this->isSensitiveKey($normalizedKey)) {
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
        return str_contains($key, 'secret')
            || str_contains($key, 'password')
            || str_contains($key, 'token')
            || str_contains($key, 'authorization')
            || str_contains($key, 'client_id')
            || str_contains($key, 'consumer_key')
            || str_contains($key, 'consumer_secret')
            || str_contains($key, 'customer_key')
            || str_contains($key, 'child_key')
            || str_contains($key, 'child_secret')
            || str_contains($key, 'crid')
            || str_contains($key, 'master_mid')
            || str_contains($key, 'labeler_mid')
            || str_contains($key, 'access_token')
            || str_contains($key, 'refresh_token');
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
