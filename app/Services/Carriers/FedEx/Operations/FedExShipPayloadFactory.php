<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\Validation\FedExShipFixtureResolver;
use App\Services\Carriers\FedEx\Validation\Payload\FedExCustomsClearanceBuilder;

class FedExShipPayloadFactory
{
    public function __construct(
        private readonly FedExShipFixtureResolver $fixtureResolver,
        private readonly FedExCustomsClearanceBuilder $customsClearanceBuilder,
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
        $accountNumber = (string) ($fixture['account_number'] ?? $account->provider_account_number ?? '');
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

        if ($customs = $this->customsClearanceBuilder->build($fixture, $accountNumber)) {
            $requestedShipment['customsClearanceDetail'] = $customs;
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
                $detail['deliveryDate'] = $this->fixtureResolver->homeDeliveryPremiumDeliveryDate($shipDate);
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
            ];

            if ($this->packageHasDimensions($package)) {
                $item['dimensions'] = [
                    'length' => max(0.01, (float) ($package['length'] ?? 9)),
                    'width' => max(0.01, (float) ($package['width'] ?? 6)),
                    'height' => max(0.01, (float) ($package['height'] ?? 2)),
                    'units' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
                ];
            }

            if (isset($package['group_package_count'])) {
                $item['groupPackageCount'] = (int) $package['group_package_count'];
            }

            if (isset($package['declared_value']) && is_array($package['declared_value'])) {
                $item['declaredValue'] = [
                    'amount' => (float) ($package['declared_value']['amount'] ?? 0),
                    'currency' => (string) ($package['declared_value']['currency'] ?? 'USD'),
                ];
            }

            if ($references = $this->buildCustomerReferences($package['customer_references'] ?? [])) {
                $item['customerReferences'] = $references;
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
     * @param  list<array<string, mixed>>  $references
     * @return list<array<string, mixed>>
     */
    private function buildCustomerReferences(array $references): array
    {
        $items = [];

        foreach ($references as $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $type = $reference['customerReferenceType'] ?? $reference['customer_reference_type'] ?? null;
            $value = $reference['value'] ?? null;

            if (filled($type) && filled($value)) {
                $items[] = [
                    'customerReferenceType' => (string) $type,
                    'value' => (string) $value,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function packageHasDimensions(array $package): bool
    {
        return isset($package['length']) || isset($package['width']) || isset($package['height']);
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>|null
     */
    private function buildPackageSpecialServices(array $package): ?array
    {
        $services = $package['package_special_services'] ?? null;

        if (! is_array($services) || $services === []) {
            return null;
        }

        $types = array_map(
            strtoupper(...),
            (array) ($services['specialServiceTypes'] ?? []),
        );

        if (in_array('SIGNATURE_OPTION', $types, true)) {
            $option = $services['signatureOptionType']
                ?? data_get($services, 'signatureOptionDetail.signatureOptionType')
                ?? data_get($services, 'signatureOptionDetail.optionType');

            if (filled($option)) {
                return [
                    'specialServiceTypes' => ['SIGNATURE_OPTION'],
                    'signatureOptionType' => strtoupper((string) $option),
                ];
            }
        }

        return $services;
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<string, mixed>
     */
    private function buildShippingChargesPayment(array $fixture, string $accountNumber): array
    {
        $paymentType = strtoupper((string) ($fixture['transportation_payment_type'] ?? 'SENDER'));
        $payment = ['paymentType' => $paymentType];

        if (in_array($paymentType, ['RECIPIENT', 'THIRD_PARTY'], true)) {
            $payorAccount = (string) ($fixture['transportation_payment_account'] ?? $accountNumber);
            $payor = [
                'responsibleParty' => [
                    'accountNumber' => ['value' => $payorAccount],
                ],
            ];

            if ($contact = $this->payorContact($fixture['transportation_payor'] ?? null)) {
                $payor['responsibleParty']['contact'] = $contact;
            }

            if ($address = $this->payorAddress($fixture['transportation_payor'] ?? null)) {
                $payor['responsibleParty']['address'] = $address;
            }

            $payment['payor'] = $payor;
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>|null  $payor
     * @return array<string, mixed>|null
     */
    private function payorContact(?array $payor): ?array
    {
        if (! is_array($payor)) {
            return null;
        }

        $contact = array_filter([
            'personName' => $payor['person_name'] ?? null,
            'companyName' => $payor['company_name'] ?? null,
        ]);

        return $contact !== [] ? $contact : null;
    }

    /**
     * @param  array<string, mixed>|null  $payor
     * @return array<string, mixed>|null
     */
    private function payorAddress(?array $payor): ?array
    {
        if (! is_array($payor) || ! filled($payor['country_code'] ?? null)) {
            return null;
        }

        return [
            'countryCode' => strtoupper((string) $payor['country_code']),
        ];
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
            return $this->fixtureResolver->nextValidFriday();
        }

        if (($fixture['ship_date_strategy'] ?? null) === 'saturday_delivery_friday') {
            return (string) ($overrides['ship_date'] ?? $this->fixtureResolver->nextSaturdayDeliveryFriday());
        }

        return now()->toDateString();
    }
}
