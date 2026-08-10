<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Services\Carriers\FedEx\Support\Branding\FedExBrandComplianceService;

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
     *     transit_days: ?int,
     *     delivery_date: ?string,
     *     surcharges: list<array{type:?string, description:?string, amount:?string, currency:?string}>,
     *     duties_and_taxes: list<array<string, mixed>>,
     *     available_rates: list<array<string, mixed>>
     * }
     */
    public function parse(
        ?array $data,
        ?string $expectedServiceType = null,
        ?string $expectedRateType = 'ACCOUNT',
        bool $allowFallbackToAnyRate = false,
    ): array {
        $availableRates = [];
        $selected = null;
        $selectedPath = null;

        foreach ((array) data_get($data, 'output.rateReplyDetails', []) as $detailIndex => $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $serviceType = $this->stringValue($detail['serviceType'] ?? null);
            $serviceName = app(FedExBrandComplianceService::class)->registeredDisplayName(
                $this->stringValue($detail['serviceName'] ?? null) ?: $serviceType
            );
            $transit = $this->extractTransit($detail);

            foreach ((array) ($detail['ratedShipmentDetails'] ?? []) as $ratedIndex => $rated) {
                if (! is_array($rated)) {
                    continue;
                }

                $rateType = $this->stringValue($rated['rateType'] ?? null);
                [$amount, $currency] = $this->normalizeMoney(
                    $rated['totalNetCharge'] ?? null,
                    data_get($rated, 'shipmentRateDetail.currency'),
                );
                $surcharges = $this->extractSurcharges($rated);
                $duties = $this->extractDutiesAndTaxes($rated);

                $entry = array_filter([
                    'service_type' => $serviceType,
                    'service_name' => $serviceName,
                    'rate_type' => $rateType,
                    'currency' => $currency,
                    'amount' => $amount,
                    'transit_days' => $transit['transit_days'],
                    'delivery_date' => $transit['delivery_date'],
                    'surcharges' => $surcharges,
                    'duties_and_taxes' => $duties,
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

        // Validation/diagnostics may fall back to any rate. Negotiated production paths must not.
        if ($selected === null && $allowFallbackToAnyRate && $availableRates !== []) {
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
            'transit_days' => isset($selected['transit_days']) ? (int) $selected['transit_days'] : null,
            'delivery_date' => $selected['delivery_date'] ?? null,
            'surcharges' => is_array($selected['surcharges'] ?? null) ? $selected['surcharges'] : [],
            'duties_and_taxes' => is_array($selected['duties_and_taxes'] ?? null) ? $selected['duties_and_taxes'] : [],
            'available_rates' => $availableRates,
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{transit_days: ?int, delivery_date: ?string}
     */
    private function extractTransit(array $detail): array
    {
        $commit = is_array($detail['commit'] ?? null) ? $detail['commit'] : [];
        $days = data_get($commit, 'transitDays')
            ?? data_get($commit, 'derivedDestinationDetail.transitDays')
            ?? data_get($detail, 'operationalDetail.transitTime');

        $delivery = data_get($commit, 'dateDetail.dayFormat')
            ?? data_get($commit, 'dateDetail.dayCms')
            ?? data_get($detail, 'deliveryTimestamp');

        $transitDays = is_numeric($days) ? (int) $days : null;
        if ($transitDays === null && is_string($days) && preg_match('/(\d+)/', $days, $m)) {
            $transitDays = (int) $m[1];
        }

        return [
            'transit_days' => $transitDays,
            'delivery_date' => $this->stringValue($delivery),
        ];
    }

    /**
     * @param  array<string, mixed>  $rated
     * @return list<array{type:?string, description:?string, amount:?string, currency:?string}>
     */
    private function extractSurcharges(array $rated): array
    {
        $rows = [];
        $list = data_get($rated, 'shipmentRateDetail.surCharges')
            ?? data_get($rated, 'shipmentRateDetail.surcharges')
            ?? [];

        foreach ((array) $list as $surcharge) {
            if (! is_array($surcharge)) {
                continue;
            }

            [$amount, $currency] = $this->normalizeMoney(
                $surcharge['amount'] ?? null,
                $surcharge['currency'] ?? data_get($rated, 'shipmentRateDetail.currency'),
            );

            $rows[] = array_filter([
                'type' => $this->stringValue($surcharge['type'] ?? $surcharge['surchargeType'] ?? null),
                'description' => $this->stringValue($surcharge['description'] ?? null),
                'amount' => $amount,
                'currency' => $currency,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $rated
     * @return list<array<string, mixed>>
     */
    private function extractDutiesAndTaxes(array $rated): array
    {
        $rows = [];
        foreach ((array) data_get($rated, 'shipmentRateDetail.totalDutiesAndTaxes', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $rows[] = $entry;
        }

        $total = data_get($rated, 'totalDutiesTaxesAndFees')
            ?? data_get($rated, 'shipmentRateDetail.totalDutiesAndTaxes');
        if ($rows === [] && $total !== null) {
            [$amount, $currency] = $this->normalizeMoney($total, data_get($rated, 'shipmentRateDetail.currency'));
            if ($amount !== null) {
                $rows[] = array_filter([
                    'amount' => $amount,
                    'currency' => $currency,
                    'type' => 'DUTIES_AND_TAXES',
                ]);
            }
        }

        return $rows;
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
