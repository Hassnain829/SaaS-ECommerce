<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use Carbon\Carbon;

class FedExShipPayloadFactory
{
    public function __construct(
        private readonly FedExShipTestCaseFixtureService $fixtureService,
    ) {}

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildShipmentPayload(
        CarrierAccount $account,
        array $fixture,
        ?string $labelFormat = null,
        array $overrides = [],
    ): array {
        $fixture = array_replace_recursive($fixture, $overrides);
        $accountNumber = (string) ($account->provider_account_number ?: ($fixture['account_number'] ?? ''));
        $labelFormat = strtoupper(trim((string) ($labelFormat ?? $fixture['label_format'] ?? 'PDF')));
        $labelStockType = (string) ($fixture['label_stock_type'] ?? 'PAPER_4X6');
        $shipDate = (string) ($overrides['ship_date'] ?? $this->resolveShipDate($fixture));

        $requestedShipment = [
            'shipDatestamp' => $shipDate,
            'pickupType' => (string) ($fixture['pickup_type'] ?? 'USE_SCHEDULED_PICKUP'),
            'serviceType' => (string) ($fixture['service_type'] ?? 'FEDEX_GROUND'),
            'packagingType' => (string) ($fixture['packaging_type'] ?? 'YOUR_PACKAGING'),
            'shipper' => $this->party($fixture['shipper'] ?? []),
            'recipients' => [$this->party($fixture['recipient'] ?? [], true)],
            'shippingChargesPayment' => $this->buildShippingChargesPayment($fixture, $accountNumber),
            'requestedPackageLineItems' => $this->buildPackageLineItems($fixture['packages'] ?? []),
        ];

        if (isset($fixture['total_package_count'])) {
            $requestedShipment['totalPackageCount'] = (int) $fixture['total_package_count'];
        }

        if ($shipmentSpecialServices = $this->buildShipmentSpecialServices($fixture, $shipDate)) {
            $requestedShipment['shipmentSpecialServices'] = $shipmentSpecialServices;
        }

        if ($emailNotification = $this->buildEmailNotificationDetail($fixture)) {
            $requestedShipment['emailNotificationDetail'] = $emailNotification;
        }

        if ($mpsControls = $this->buildMpsControls($fixture)) {
            $requestedShipment['processingOption'] = $mpsControls;
        }

        if (filled($labelFormat)) {
            $requestedShipment['labelSpecification'] = $this->buildLabelSpecification($labelFormat, $labelStockType);
        }

        return [
            'labelResponseOptions' => filled($labelFormat) ? 'LABEL' : 'URL_ONLY',
            'requestedShipment' => $requestedShipment,
            'accountNumber' => ['value' => $accountNumber],
        ];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>|null
     */
    private function buildShipmentSpecialServices(array $fixture, string $shipDate): ?array
    {
        $services = $fixture['shipment_special_services'] ?? $fixture['special_services'] ?? null;
        if (! is_array($services) || $services === []) {
            return null;
        }

        $payload = $services;

        if (($fixture['home_delivery_premium_delivery_date_strategy'] ?? null) === 'one_week_after_ship_date') {
            $detail = $payload['homeDeliveryPremiumDetail'] ?? [];
            if (is_array($detail) && ! isset($detail['deliveryDate'])) {
                $detail['deliveryDate'] = $this->fixtureService->homeDeliveryPremiumDeliveryDate($shipDate);
                $payload['homeDeliveryPremiumDetail'] = $detail;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>|null
     */
    private function buildEmailNotificationDetail(array $fixture): ?array
    {
        $detail = $fixture['email_notification_detail'] ?? null;
        if (is_array($detail) && $detail !== []) {
            return $detail;
        }

        if (filled($fixture['email_notification'] ?? null)) {
            return [
                'recipients' => [[
                    'emailAddress' => (string) $fixture['email_notification'],
                    'notificationEventType' => ['ON_SHIPMENT'],
                    'notificationFormatType' => 'HTML',
                ]],
            ];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     * @return list<array<string, mixed>>
     */
    private function buildPackageLineItems(array $packages): array
    {
        $items = [];

        foreach ($packages as $index => $package) {
            if (! is_array($package)) {
                continue;
            }

            $item = [
                'sequenceNumber' => (int) ($package['sequence_number'] ?? ($index + 1)),
                'weight' => [
                    'units' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                    'value' => max(0.01, (float) ($package['weight'] ?? 1)),
                ],
                'dimensions' => [
                    'length' => max(0.01, (float) ($package['length'] ?? 9)),
                    'width' => max(0.01, (float) ($package['width'] ?? 6)),
                    'height' => max(0.01, (float) ($package['height'] ?? 2)),
                    'units' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
                ],
            ];

            if (isset($package['group_package_count'])) {
                $item['groupPackageCount'] = (int) $package['group_package_count'];
            }

            if (isset($package['declared_value']) && is_array($package['declared_value'])) {
                $item['declaredValue'] = [
                    'amount' => (float) ($package['declared_value']['amount'] ?? 0),
                    'currency' => (string) ($package['declared_value']['currency'] ?? 'USD'),
                ];
            }

            if ($packageSpecialServices = $this->buildPackageSpecialServices($package)) {
                $item['packageSpecialServices'] = $packageSpecialServices;
            }

            $items[] = $item;
        }

        return $items !== [] ? $items : [[
            'sequenceNumber' => 1,
            'weight' => ['units' => 'LB', 'value' => 1.0],
            'dimensions' => ['length' => 9, 'width' => 6, 'height' => 2, 'units' => 'IN'],
        ]];
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>|null
     */
    private function buildPackageSpecialServices(array $package): ?array
    {
        $services = $package['package_special_services'] ?? null;

        return is_array($services) && $services !== [] ? $services : null;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function buildShippingChargesPayment(array $fixture, string $accountNumber): array
    {
        $paymentType = strtoupper((string) ($fixture['transportation_payment_type'] ?? 'SENDER'));
        $payment = ['paymentType' => $paymentType];

        if ($paymentType === 'RECIPIENT') {
            $payment['payor'] = [
                'responsibleParty' => [
                    'accountNumber' => [
                        'value' => (string) ($fixture['transportation_payment_account'] ?? $accountNumber),
                    ],
                ],
            ];
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>|null
     */
    private function buildMpsControls(array $fixture): ?array
    {
        $option = $fixture['processing_option'] ?? null;

        return is_array($option) && $option !== [] ? $option : null;
    }

    private function buildLabelSpecification(string $format, string $stockType): array
    {
        $format = strtoupper(trim($format));

        return match ($format) {
            'PNG' => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'PNG',
                'labelStockType' => $stockType,
            ],
            'ZPL', 'ZPLII' => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'ZPLII',
                'labelStockType' => $stockType,
            ],
            default => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'PDF',
                'labelStockType' => $stockType,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $party
     * @return array<string, mixed>
     */
    private function party(array $party, bool $recipient = false): array
    {
        $contact = array_filter([
            'personName' => $party['person_name'] ?? null,
            'companyName' => $party['company_name'] ?? null,
            'phoneNumber' => $party['phone'] ?? null,
            'phoneExtension' => $party['phone_extension'] ?? null,
        ]);

        $address = array_filter([
            'streetLines' => array_values(array_filter($party['street_lines'] ?? [])),
            'city' => $party['city'] ?? null,
            'stateOrProvinceCode' => $party['state'] ?? null,
            'postalCode' => $party['postal_code'] ?? null,
            'countryCode' => strtoupper((string) ($party['country_code'] ?? 'US')),
            'residential' => $recipient ? (bool) ($party['residential'] ?? false) : null,
        ], static fn (mixed $value): bool => $value !== null);

        return array_filter([
            'contact' => $contact !== [] ? $contact : null,
            'address' => $address,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function resolveShipDate(array $fixture): string
    {
        if (($fixture['ship_date_strategy'] ?? null) === 'next_valid_friday') {
            return $this->fixtureService->nextValidFriday();
        }

        return now()->toDateString();
    }
}
