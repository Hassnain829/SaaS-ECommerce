<?php

namespace App\Services\Carriers\FedEx\Operations;

final class FedExComprehensiveRateResponseParser
{
    /**
     * @param  array<string, mixed>|null  $data
     * @return array{
     *     service_type: ?string,
     *     service_name: ?string,
     *     rate_type: ?string,
     *     currency: ?string,
     *     amount: ?string,
     *     response_amount_path: ?string,
     *     available_rates: list<array<string, mixed>>
     * }
     */
    public function parse(?array $data, ?string $expectedServiceType = null, ?string $expectedRateType = 'ACCOUNT'): array
    {
        $availableRates = [];
        $selected = null;
        $selectedPath = null;

        foreach ((array) data_get($data, 'output.rateReplyDetails', []) as $detailIndex => $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $serviceType = $this->stringValue($detail['serviceType'] ?? null);
            $serviceName = $this->stringValue($detail['serviceName'] ?? null) ?: $serviceType;

            foreach ((array) ($detail['ratedShipmentDetails'] ?? []) as $ratedIndex => $rated) {
                if (! is_array($rated)) {
                    continue;
                }

                $rateType = $this->stringValue($rated['rateType'] ?? null);
                [$amount, $currency] = $this->normalizeMoney(
                    $rated['totalNetCharge'] ?? null,
                    data_get($rated, 'shipmentRateDetail.currency'),
                );

                $entry = array_filter([
                    'service_type' => $serviceType,
                    'service_name' => $serviceName,
                    'rate_type' => $rateType,
                    'currency' => $currency,
                    'amount' => $amount,
                    'response_amount_path' => "output.rateReplyDetails[{$detailIndex}].ratedShipmentDetails[{$ratedIndex}].totalNetCharge",
                ], static fn (mixed $value): bool => $value !== null && $value !== '');

                $availableRates[] = $entry;

                if ($selected !== null) {
                    continue;
                }

                if ($expectedServiceType !== null && strtoupper($serviceType ?? '') !== strtoupper($expectedServiceType)) {
                    continue;
                }

                if ($expectedRateType !== null && strtoupper($rateType ?? '') !== strtoupper($expectedRateType)) {
                    continue;
                }

                if ($amount === null || $currency === null) {
                    continue;
                }

                $selected = $entry;
                $selectedPath = $entry['response_amount_path'] ?? null;
            }
        }

        if ($selected === null && $availableRates !== []) {
            $selected = $availableRates[0];
            $selectedPath = $selected['response_amount_path'] ?? null;
        }

        return [
            'service_type' => $selected['service_type'] ?? null,
            'service_name' => $selected['service_name'] ?? null,
            'rate_type' => $selected['rate_type'] ?? null,
            'currency' => $selected['currency'] ?? null,
            'amount' => $selected['amount'] ?? null,
            'response_amount_path' => $selectedPath,
            'available_rates' => $availableRates,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeMoney(mixed $charge, mixed $fallbackCurrency): array
    {
        if (is_array($charge)) {
            $amount = $charge['amount'] ?? null;
            $currency = $charge['currency'] ?? $fallbackCurrency;

            return [$this->decimalString($amount), $this->stringValue($currency)];
        }

        if (is_numeric($charge)) {
            return [$this->decimalString($charge), $this->stringValue($fallbackCurrency)];
        }

        return [null, $this->stringValue($fallbackCurrency)];
    }

    private function decimalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = $value['value'] ?? $value['code'] ?? null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
