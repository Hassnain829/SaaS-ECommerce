<?php

namespace App\Services\Carriers\FedEx\Validation;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Services\Carriers\FedEx\Operations\FedExShipResponseParser;
use App\Services\Carriers\FedEx\Support\FedExConfig;

final class FedExShipEvidenceRules
{
    public function __construct(
        private readonly FedExShipFixtureResolver $fixtureResolver,
        private readonly FedExShipResponseParser $responseParser,
        private readonly FedExConfig $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function expectedMetadata(string $testCaseKey): array
    {
        $fixture = $this->fixtureResolver->fixture($testCaseKey);
        $meta = FedExValidationScenarioCatalog::lockedShipScenarios()[$testCaseKey]
            ?? FedExValidationScenarioCatalog::globalShipScenarios()[$testCaseKey]
            ?? [];

        return [
            'test_case_key' => $testCaseKey,
            'scenario_key' => (string) ($fixture['scenario_key'] ?? $meta['scenario_key'] ?? ''),
            'expected_service_type' => (string) ($fixture['expected_service_type'] ?? $fixture['service_type'] ?? ''),
            'expected_label_format' => strtoupper((string) ($fixture['expected_label_format'] ?? $fixture['label_format'] ?? '')),
            'label_stock_type' => (string) ($fixture['label_stock_type'] ?? $meta['label_stock_type'] ?? ''),
            'expected_package_count' => (int) ($fixture['expected_package_count'] ?? count($fixture['packages'] ?? [])),
            'payment_type' => strtoupper((string) ($fixture['transportation_payment_type'] ?? 'SENDER')),
            'fixture_version' => (string) ($fixture['fixture_version'] ?? ''),
        ];
    }

    public function isValidEventForTestCase(CarrierApiEvent $event, string $testCaseKey): bool
    {
        if ($this->isLegacyGrandfathered($event)) {
            return $this->validateLegacyGrandfathered($event, $testCaseKey)['valid'];
        }

        return $this->validateRequest($event, $testCaseKey)['valid']
            && $this->validateResponse($event, $testCaseKey)['valid'];
    }

    public function isLegacyGrandfathered(CarrierApiEvent $event): bool
    {
        return (bool) data_get($event->response_summary, 'legacy_grandfathered', false);
    }

    /**
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateLegacyGrandfathered(CarrierApiEvent $event, string $testCaseKey): array
    {
        $expected = $this->expectedMetadata($testCaseKey);
        $reasons = [];

        if ($event->action !== CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL) {
            $reasons[] = 'action_not_create_label';
        }

        if (! $event->isSuccessfulHttp() || $event->status !== CarrierApiEvent::STATUS_SUCCEEDED) {
            $reasons[] = 'http_not_successful';
        }

        if ((string) $event->scenario_key !== (string) $expected['scenario_key']) {
            $reasons[] = 'scenario_key_mismatch';
        }

        if ((string) $event->test_case_key !== $testCaseKey) {
            $reasons[] = 'test_case_key_mismatch';
        }

        if ((string) ($event->validation_region ?? 'US') !== 'US') {
            $reasons[] = 'validation_region_not_us';
        }

        $parsed = $this->responseParser->parse(is_array($event->response_body_encrypted) ? $event->response_body_encrypted : []);
        $responseService = strtoupper((string) ($parsed['service_type'] ?? ''));
        if ($responseService === '' || $responseService !== (string) $expected['expected_service_type']) {
            $reasons[] = 'response_service_mismatch';
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{valid: bool, reasons: list<string>, metadata: array<string, mixed>}
     */
    public function validateRequest(CarrierApiEvent $event, string $testCaseKey): array
    {
        if ($this->isLegacyGrandfathered($event)) {
            $legacy = $this->validateLegacyGrandfathered($event, $testCaseKey);

            return [
                'valid' => $legacy['valid'],
                'reasons' => $legacy['reasons'],
                'metadata' => $this->expectedMetadata($testCaseKey),
            ];
        }

        $expected = $this->expectedMetadata($testCaseKey);
        $reasons = [];
        $request = is_array($event->request_body_encrypted) ? $event->request_body_encrypted : [];

        if ($event->action !== CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL) {
            $reasons[] = 'action_not_create_label';
        }

        if (strtoupper((string) $event->http_method) !== 'POST') {
            $reasons[] = 'http_method_not_post';
        }

        $endpoint = (string) ($event->endpoint ?? data_get($event->request_summary, 'endpoint', ''));
        if (! str_contains($endpoint, $this->config->shipCreatePath($event->environment))) {
            $reasons[] = 'endpoint_not_ship_create';
        }

        if (! $event->hasCompleteEvidence()) {
            $reasons[] = 'incomplete_evidence';
        }

        if ($event->status !== CarrierApiEvent::STATUS_SUCCEEDED || ! $event->isSuccessfulHttp()) {
            $reasons[] = 'http_not_successful';
        }

        if ((string) $event->scenario_key !== (string) $expected['scenario_key']) {
            $reasons[] = 'scenario_key_mismatch';
        }

        if ((string) $event->test_case_key !== $testCaseKey) {
            $reasons[] = 'test_case_key_mismatch';
        }

        $payment = data_get($request, 'requestedShipment.shippingChargesPayment');
        if (! is_array($payment) || $payment === [] || $payment === '[REDACTED]') {
            $reasons[] = 'shipping_charges_payment_missing';
        } elseif (strtoupper((string) data_get($payment, 'paymentType')) !== (string) $expected['payment_type']) {
            $reasons[] = 'payment_type_mismatch';
        }

        $requestService = strtoupper((string) data_get($request, 'requestedShipment.serviceType', ''));
        if ($requestService !== (string) $expected['expected_service_type']) {
            $reasons[] = 'request_service_mismatch';
        }

        $imageType = strtoupper((string) data_get($request, 'requestedShipment.labelSpecification.imageType', ''));
        if ($imageType !== (string) $expected['expected_label_format']) {
            $reasons[] = 'request_label_format_mismatch';
        }

        $stockType = (string) data_get($request, 'requestedShipment.labelSpecification.labelStockType', '');
        if ($stockType !== (string) $expected['label_stock_type']) {
            $reasons[] = 'label_stock_mismatch';
        }

        $packageCount = count((array) data_get($request, 'requestedShipment.requestedPackageLineItems', []));
        if ($packageCount !== (int) $expected['expected_package_count']) {
            $reasons[] = 'package_count_mismatch';
        }

        if ($testCaseKey === 'IntegratorUS02' && ! $this->isFridayShipDate((string) data_get($request, 'requestedShipment.shipDatestamp', ''))) {
            $reasons[] = 'us02_ship_date_not_friday';
        }

        if ($testCaseKey === 'IntegratorUS02' && ! $this->containsSpecialServices($request, ['EVENT_NOTIFICATION', 'SATURDAY_DELIVERY'])) {
            $reasons[] = 'us02_special_services_missing';
        }

        if ($testCaseKey === 'IntegratorCA03') {
            if (! $this->isFridayShipDate((string) data_get($request, 'requestedShipment.shipDatestamp', ''))) {
                $reasons[] = 'ca03_ship_date_not_friday';
            }

            if (! $this->containsSpecialServices($request, ['SATURDAY_DELIVERY'])) {
                $reasons[] = 'ca03_saturday_delivery_missing';
            }

            if (! data_get($request, 'requestedShipment.recipients.0.address.residential')) {
                $reasons[] = 'ca03_residential_missing';
            }
        }

        if ($testCaseKey === 'IntegratorUS04') {
            if (! data_get($request, 'requestedShipment.recipients.0.address.residential')) {
                $reasons[] = 'us04_residential_missing';
            }
            if (! $this->containsSpecialServices($request, ['HOME_DELIVERY_PREMIUM'])) {
                $reasons[] = 'us04_home_delivery_premium_missing';
            }
            $declaredValue = data_get($request, 'requestedShipment.requestedPackageLineItems.0.declaredValue');
            if (! is_array($declaredValue)) {
                $reasons[] = 'us04_package_declared_value_missing';
            }
        }

        if ($testCaseKey === 'IntegratorUS05') {
            if ((int) data_get($request, 'requestedShipment.totalPackageCount') !== 2) {
                $reasons[] = 'us05_total_package_count_mismatch';
            }
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
            'metadata' => $expected,
        ];
    }

    /**
     * @return array{valid: bool, reasons: list<string>, parsed: array<string, mixed>}
     */
    public function validateResponse(CarrierApiEvent $event, string $testCaseKey): array
    {
        $expected = $this->expectedMetadata($testCaseKey);
        $parsed = $this->responseParser->parse(is_array($event->response_body_encrypted) ? $event->response_body_encrypted : []);

        if ($this->isLegacyGrandfathered($event)) {
            $legacy = $this->validateLegacyGrandfathered($event, $testCaseKey);

            return [
                'valid' => $legacy['valid'],
                'reasons' => $legacy['reasons'],
                'parsed' => $parsed,
            ];
        }

        $reasons = [];

        $response = is_array($event->response_body_encrypted) ? $event->response_body_encrypted : [];
        $parsed = $this->responseParser->parse($response);

        $responseService = strtoupper((string) ($parsed['service_type'] ?? data_get($event->response_summary, 'response_service_type', '')));
        $expectedService = (string) $expected['expected_service_type'];

        if ($responseService === '' || $responseService !== $expectedService) {
            $reasons[] = 'response_service_mismatch';
        }

        if ($testCaseKey === 'IntegratorUS02' && in_array($responseService, ['GROUND_HOME_DELIVERY', 'FEDEX_GROUND'], true)) {
            $reasons[] = 'us02_wrong_home_delivery_or_ground';
        }

        $labelCount = count($parsed['labels']);
        if ($labelCount !== (int) $expected['expected_package_count']) {
            $reasons[] = 'response_label_count_mismatch';
        }

        $sequences = array_keys($parsed['labels']);
        $expectedSequences = range(1, (int) $expected['expected_package_count']);
        if ($sequences !== $expectedSequences) {
            $reasons[] = 'response_package_sequence_mismatch';
        }

        foreach ($parsed['labels'] as $label) {
            $imageType = strtoupper((string) ($label['image_type'] ?? ''));
            if ($imageType !== '' && $imageType !== (string) $expected['expected_label_format']) {
                $reasons[] = 'response_label_format_mismatch';
                break;
            }
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
            'parsed' => $parsed,
        ];
    }

    /**
     * @param  list<FedExValidationArtifact>  $artifacts
     * @return array{valid: bool, reasons: list<string>}
     */
    public function validateGeneratedArtifacts(CarrierApiEvent $event, string $testCaseKey, array $artifacts): array
    {
        $expected = $this->expectedMetadata($testCaseKey);
        $reasons = [];
        $labelsBySequence = [];

        foreach ($artifacts as $artifact) {
            if ($artifact->artifact_role !== FedExValidationArtifact::ROLE_GENERATED_LABEL) {
                continue;
            }

            $sequence = (int) $artifact->package_sequence;
            if (isset($labelsBySequence[$sequence])) {
                $reasons[] = 'duplicate_generated_label_sequence_'.$sequence;
            }
            $labelsBySequence[$sequence] = $artifact;

            $path = $artifact->absolutePath();
            if ($path === null || ! FedExLabelArtifactValidator::isValid($path, (string) $expected['expected_label_format'])) {
                $reasons[] = 'invalid_generated_label_binary_'.$sequence;
            }
        }

        for ($sequence = 1; $sequence <= (int) $expected['expected_package_count']; $sequence++) {
            if (! isset($labelsBySequence[$sequence])) {
                $reasons[] = 'missing_generated_label_'.$sequence;
            }
        }

        if ((int) $event->id !== (int) ($labelsBySequence[1]?->carrier_api_event_id ?? $event->id)) {
            $reasons[] = 'artifact_event_mismatch';
        }

        return [
            'valid' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  list<string>  $required
     */
    private function containsSpecialServices(array $request, array $required): bool
    {
        $types = array_map(
            strtoupper(...),
            (array) data_get($request, 'requestedShipment.shipmentSpecialServices.specialServiceTypes', []),
        );

        foreach ($required as $service) {
            if (! in_array(strtoupper($service), $types, true)) {
                return false;
            }
        }

        return true;
    }

    private function isFridayShipDate(string $shipDate): bool
    {
        if ($shipDate === '') {
            return false;
        }

        try {
            return now()->parse($shipDate)->dayOfWeek === 5;
        } catch (\Throwable) {
            return false;
        }
    }
}
