<?php

namespace App\Services\Carriers\FedEx\Presenters;

use App\Services\Carriers\Core\DTO\CarrierApiResult;
use App\Services\Carriers\FedEx\Validation\FedExBrandComplianceService;

final class FedExMerchantCheckPresenter
{
    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public static function addressValidation(?array $data, ?string $requestedCountryCode = null): array
    {
        if (! is_array($data)) {
            return self::emptyAddressPresentation();
        }

        $requestedCountry = self::normalizeCountryCode($requestedCountryCode);
        $resolved = [];
        $ignored = [];
        $messages = [];

        foreach (data_get($data, 'output.resolvedAddresses', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $row = self::normalizeResolvedAddressRow($entry);
            $resolvedCountry = self::normalizeCountryCode($row['country_code'] ?? null);

            if ($requestedCountry !== null && $resolvedCountry !== null && $resolvedCountry !== $requestedCountry) {
                $ignored[] = array_merge($row, [
                    'ignored_reason' => 'country_mismatch',
                ]);

                continue;
            }

            $resolved[] = $row;

            foreach ((array) ($entry['customerMessages'] ?? []) as $message) {
                if (is_array($message) && filled($message['code'] ?? null)) {
                    $messages[] = self::displayValue($message['message'] ?? $message['code']);
                }
            }
        }

        foreach ((array) data_get($data, 'output.alerts', []) as $alert) {
            if (is_array($alert) && filled($alert['message'] ?? null)) {
                $messages[] = self::displayValue($alert['message']);
            }
        }

        $warnings = [];
        $ignoredCountryCodes = self::collectIgnoredCountryCodes($ignored);

        if ($ignored !== []) {
            $warnings[] = 'FedEx returned a suggestion outside the requested country; ignored for validation evidence.';
        }

        if ($resolved === [] && $ignored !== []) {
            $warnings[] = 'FedEx connected successfully but did not return a country-matching resolved address.';
        }

        return [
            'resolved_addresses' => $resolved,
            'ignored_suggestions' => $ignored,
            'warnings' => self::dedupeStrings($warnings),
            'messages' => self::dedupeStrings($messages),
            'resolved_count' => count($resolved) + count($ignored),
            'matching_count' => count($resolved),
            'ignored_suggestion_count' => count($ignored),
            'ignored_country_codes' => $ignoredCountryCodes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyAddressPresentation(): array
    {
        return [
            'resolved_addresses' => [],
            'ignored_suggestions' => [],
            'warnings' => [],
            'messages' => [],
            'resolved_count' => 0,
            'matching_count' => 0,
            'ignored_suggestion_count' => 0,
            'ignored_country_codes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private static function normalizeResolvedAddressRow(array $entry): array
    {
        $address = is_array($entry['streetLinesToken'] ?? null)
            ? implode(' ', $entry['streetLinesToken'])
            : null;

        return array_filter([
            'street' => $address ?: implode(', ', array_filter((array) ($entry['streetLines'] ?? []))),
            'city' => self::displayValue($entry['city'] ?? data_get($entry, 'cityToken.0')),
            'state' => self::displayValue($entry['stateOrProvinceCode'] ?? null),
            'postal_code' => self::displayValue($entry['postalCode'] ?? null),
            'country_code' => self::displayValue($entry['countryCode'] ?? null),
            'classification' => self::displayValue(data_get($entry, 'classification')),
            'residential' => data_get($entry, 'attributes.residential'),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $ignored
     * @return array<int, string>
     */
    private static function collectIgnoredCountryCodes(array $ignored): array
    {
        $codes = [];

        foreach ($ignored as $row) {
            $code = self::normalizeCountryCode($row['country_code'] ?? null);
            if ($code !== null) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private static function normalizeCountryCode(mixed $value): ?string
    {
        $code = self::displayValue($value);

        if ($code === null) {
            return null;
        }

        $code = strtoupper(trim($code));

        return strlen($code) === 2 ? $code : null;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public static function serviceAvailability(?array $data): array
    {
        $services = [];
        $packageTypes = [];

        foreach ((array) data_get($data, 'output.packageOptions', []) as $option) {
            if (! is_array($option)) {
                continue;
            }

            self::appendPackageType($packageTypes, $option['packageType'] ?? null);
            self::appendServiceFromOption($services, $option);
        }

        foreach ((array) data_get($data, 'output.options', []) as $option) {
            if (! is_array($option)) {
                continue;
            }

            self::appendPackageType($packageTypes, $option['packageType'] ?? null);
            self::appendServiceFromOption($services, $option);
        }

        $services = self::dedupeServices($services);
        $packageTypes = self::dedupePackageTypes($packageTypes);

        return [
            'services' => $services,
            'package_types' => $packageTypes,
            'service_count' => count($services),
            'package_type_count' => count($packageTypes),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public static function rateQuote(?array $data): array
    {
        $rates = [];

        foreach ((array) data_get($data, 'output.rateReplyDetails', []) as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $serviceType = self::keyValue($detail['serviceType'] ?? null) ?: self::displayValue($detail['serviceType'] ?? null);
            $serviceName = self::brandedDisplay(
                self::displayValue($detail['serviceName'] ?? null) ?: $serviceType
            );

            foreach ((array) ($detail['ratedShipmentDetails'] ?? []) as $rated) {
                if (! is_array($rated)) {
                    continue;
                }

                $totalCharge = data_get($rated, 'totalNetCharge');
                $currency = is_array($totalCharge) ? ($totalCharge['currency'] ?? null) : null;
                $amount = is_array($totalCharge) ? ($totalCharge['amount'] ?? null) : null;

                $rates[] = array_filter([
                    'service_type' => $serviceType,
                    'service_name' => $serviceName,
                    'amount' => $amount,
                    'currency' => $currency,
                    'transit_days' => data_get($detail, 'commit.dateDetail.dayFormat'),
                    'delivery_date' => data_get($detail, 'commit.dateDetail.dayOfWeek'),
                ]);
            }
        }

        return [
            'rates' => $rates,
            'rate_count' => count($rates),
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    public static function compactOutputSummary(array $output): array
    {
        if (array_key_exists('packageOptions', $output) || array_key_exists('options', $output)) {
            return self::compactServiceAvailabilitySummary($output);
        }

        if (array_key_exists('rateReplyDetails', $output)) {
            return self::compactRateQuoteSummary($output);
        }

        if (array_key_exists('resolvedAddresses', $output)) {
            return self::compactAddressValidationSummary($output);
        }

        if (array_key_exists('transactionShipments', $output)) {
            return self::compactShipSummary($output);
        }

        return ['output_keys' => array_values(array_keys($output))];
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    public static function compactServiceAvailabilitySummary(array $output): array
    {
        $presentation = self::serviceAvailability(['output' => $output]);

        return [
            'package_options_count' => count((array) ($output['packageOptions'] ?? [])) + count((array) ($output['options'] ?? [])),
            'service_count' => $presentation['service_count'],
            'package_type_count' => $presentation['package_type_count'],
            'service_samples' => self::sampleLines($presentation['services'], fn (array $service): string => trim(
                (string) ($service['service_name'] ?? $service['service_type'] ?? 'Service')
                .(($service['packaging_type'] ?? null) ? ' · '.($service['packaging_type']) : '')
            )),
            'package_samples' => self::sampleLines($presentation['package_types'], fn (array $package): string => trim(
                (string) ($package['package_name'] ?? $package['package_type'] ?? 'Package')
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    public static function compactRateQuoteSummary(array $output): array
    {
        $presentation = self::rateQuote(['output' => $output]);
        $currencySamples = [];

        foreach ($presentation['rates'] as $rate) {
            if (! is_array($rate)) {
                continue;
            }

            $currency = (string) ($rate['currency'] ?? '');
            if ($currency !== '' && ! in_array($currency, $currencySamples, true)) {
                $currencySamples[] = $currency;
            }
        }

        return [
            'rate_reply_count' => count((array) ($output['rateReplyDetails'] ?? [])),
            'rate_count' => $presentation['rate_count'],
            'service_samples' => self::sampleLines($presentation['rates'], fn (array $rate): string => trim(
                (string) ($rate['service_name'] ?? $rate['service_type'] ?? 'Service')
                .(($rate['amount'] ?? null) !== null ? ' · '.($rate['currency'] ?? 'USD').' '.$rate['amount'] : '')
            )),
            'currency_samples' => array_slice($currencySamples, 0, 10),
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    public static function compactAddressValidationSummary(array $output, ?string $requestedCountryCode = null): array
    {
        $presentation = self::addressValidation(['output' => $output], $requestedCountryCode);

        return [
            'resolved_address_count' => $presentation['resolved_count'],
            'matching_suggestion_count' => $presentation['matching_count'],
            'ignored_suggestion_count' => $presentation['ignored_suggestion_count'],
            'ignored_country_codes' => $presentation['ignored_country_codes'],
            'message_samples' => array_slice($presentation['messages'], 0, 10),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     */
    private static function appendServiceFromOption(array &$services, array $option): void
    {
        $packagingType = self::keyValue($option['packageType'] ?? null);
        $packagingName = self::displayValue($option['packageType'] ?? null);

        if (filled($option['serviceType'] ?? null)) {
            $services[] = self::normalizeServiceRow(
                serviceType: $option['serviceType'],
                serviceName: $option['serviceName'] ?? null,
                packagingType: $packagingType,
                packagingName: $packagingName,
                option: $option,
            );
        }

        foreach ((array) ($option['serviceOptions'] ?? []) as $service) {
            if (! is_array($service)) {
                continue;
            }

            $services[] = self::normalizeServiceRow(
                serviceType: $service['serviceType'] ?? null,
                serviceName: $service['serviceName'] ?? null,
                packagingType: $packagingType,
                packagingName: $packagingName,
                option: array_merge($option, $service),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $packageTypes
     */
    private static function appendPackageType(array &$packageTypes, mixed $packageType): void
    {
        $code = self::keyValue($packageType);
        if ($code === null) {
            return;
        }

        $packageTypes[] = array_filter([
            'package_type' => $code,
            'package_name' => self::displayValue($packageType) ?: $code,
        ]);
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>
     */
    private static function normalizeServiceRow(
        mixed $serviceType,
        mixed $serviceName,
        ?string $packagingType,
        ?string $packagingName,
        array $option,
    ): array {
        $serviceTypeCode = self::keyValue($serviceType) ?: self::displayValue($serviceType);
        $serviceDisplay = self::brandedDisplay(
            self::displayValue($serviceName) ?: self::displayValue($serviceType) ?: $serviceTypeCode
        );

        return array_filter([
            'service_type' => $serviceTypeCode,
            'service_name' => $serviceDisplay,
            'packaging_type' => $packagingType,
            'packaging_name' => self::brandedDisplay($packagingName),
            'service_category' => self::displayValue($option['serviceCategory'] ?? null),
            'operating_org_codes' => self::normalizeStringList($option['operatingOrgCodes'] ?? null),
            'max_weight_allowed' => self::displayValue($option['maxWeightAllowed'] ?? null),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @return array<int, array<string, mixed>>
     */
    private static function dedupeServices(array $services): array
    {
        $seen = [];
        $deduped = [];

        foreach ($services as $service) {
            $key = ($service['service_type'] ?? '').'|'.($service['packaging_type'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $service;
        }

        return $deduped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $packageTypes
     * @return array<int, array<string, mixed>>
     */
    private static function dedupePackageTypes(array $packageTypes): array
    {
        $seen = [];
        $deduped = [];

        foreach ($packageTypes as $package) {
            $key = (string) ($package['package_type'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $package;
        }

        return $deduped;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private static function dedupeStrings(array $values): array
    {
        $deduped = [];

        foreach ($values as $value) {
            $string = self::displayValue($value);
            if ($string === null || in_array($string, $deduped, true)) {
                continue;
            }

            $deduped[] = $string;
        }

        return $deduped;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  callable(array<string, mixed>): string  $formatter
     * @return array<int, string>
     */
    private static function sampleLines(array $items, callable $formatter, int $limit = 10): array
    {
        $samples = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $line = trim($formatter($item));
            if ($line === '' || in_array($line, $samples, true)) {
                continue;
            }

            $samples[] = $line;

            if (count($samples) >= $limit) {
                break;
            }
        }

        return $samples;
    }

    /**
     * @return array<int, string>|null
     */
    private static function normalizeStringList(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            return null;
        }

        $normalized = [];

        foreach ($value as $item) {
            $string = self::displayValue($item);
            if ($string !== null) {
                $normalized[] = $string;
            }
        }

        return $normalized === [] ? null : array_values(array_unique($normalized));
    }

    private static function textValue(mixed $value, string $prefer = 'displayText'): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string !== '' ? $string : null;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ([$prefer, 'displayText', 'key', 'code', 'name', 'value'] as $key) {
            if (filled($value[$key] ?? null) && is_scalar($value[$key])) {
                $string = trim((string) $value[$key]);

                if ($string !== '') {
                    return $string;
                }
            }
        }

        return null;
    }

    private static function keyValue(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach (['key', 'code', 'value'] as $key) {
                if (filled($value[$key] ?? null) && is_scalar($value[$key])) {
                    return trim((string) $value[$key]);
                }
            }
        }

        return self::textValue($value, 'key');
    }

    private static function displayValue(mixed $value): ?string
    {
        $text = self::textValue($value, 'displayText');

        if ($text === null) {
            return null;
        }

        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');

            return is_string($converted) && $converted !== '' ? $converted : $text;
        }

        return $text;
    }

    private static function brandedDisplay(?string $value): ?string
    {
        return app(FedExBrandComplianceService::class)->registeredDisplayName($value);
    }

    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed>
     */
    public static function compactShipSummary(array $output): array
    {
        $shipments = (array) ($output['transactionShipments'] ?? []);
        $trackingSamples = [];
        $labelFormats = [];

        foreach ($shipments as $shipment) {
            if (! is_array($shipment)) {
                continue;
            }

            $master = $shipment['masterTrackingNumber'] ?? null;
            if (is_string($master) && $master !== '') {
                $trackingSamples[] = strlen($master) >= 4 ? '****'.substr($master, -4) : '****';
            }

            foreach ((array) ($shipment['pieceResponses'] ?? []) as $piece) {
                if (! is_array($piece)) {
                    continue;
                }

                foreach ((array) ($piece['packageDocuments'] ?? []) as $document) {
                    if (! is_array($document)) {
                        continue;
                    }

                    if (filled($document['docType'] ?? null)) {
                        $labelFormats[] = (string) $document['docType'];
                    }
                }
            }
        }

        return [
            'shipment_count' => count($shipments),
            'tracking_samples' => self::dedupeStrings($trackingSamples),
            'label_document_types' => self::dedupeStrings($labelFormats),
            'label_payload_redacted' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    public static function shipValidation(CarrierApiResult $result, array $fixture, string $testCaseKey): array
    {
        return [
            'test_case' => $testCaseKey,
            'test_case_label' => $fixture['label'] ?? $testCaseKey,
            'service_type' => $fixture['service_type'] ?? null,
            'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            'validated' => $result->success,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function shipLabel(
        CarrierApiResult $result,
        ?\App\Models\FedExValidationArtifact $artifact,
        string $testCaseKey,
        string $labelFormat,
    ): array {
        return [
            'test_case' => $testCaseKey,
            'label_format' => strtoupper($labelFormat),
            'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            'label_saved' => $artifact !== null,
            'artifact_label' => $artifact?->label,
            'artifact_type' => $artifact?->artifact_type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function shipCancel(CarrierApiResult $result, string $trackingNumber): array
    {
        return [
            'tracking_number_last4' => strlen($trackingNumber) >= 4 ? substr($trackingNumber, -4) : null,
            'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            'cancelled' => $result->success,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function tracking(CarrierApiResult $result, string $trackingNumber): array
    {
        return [
            'tracking_number_last4' => strlen($trackingNumber) >= 4 ? substr($trackingNumber, -4) : null,
            'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            'tracked' => $result->success,
            'path_configured' => $result->errorCode !== 'tracking_path_not_configured',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function tradeDocuments(CarrierApiResult $result, string $trackingNumber): array
    {
        return [
            'tracking_number_last4' => strlen($trackingNumber) >= 4 ? substr($trackingNumber, -4) : null,
            'authorization_blocked' => $result->errorCode === 'fedex_authorization_blocked',
            'uploaded' => $result->success,
            'path_configured' => $result->errorCode !== 'trade_documents_path_not_configured',
        ];
    }
}
