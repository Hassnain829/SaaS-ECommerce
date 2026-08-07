<?php

namespace App\Services\Carriers\FedEx\DTO;

/**
 * Production shipment input for negotiated rates (not validation fixtures).
 */
final class FedExShipmentRateRequest
{
    /**
     * @param  array{postal_code?: string|null, country_code: string, city?: string|null, state?: string|null, residential?: bool|null}  $shipper
     * @param  array{postal_code?: string|null, country_code: string, city?: string|null, state?: string|null, residential?: bool|null, address_line1?: string|null, address_line2?: string|null}  $recipient
     * @param  list<array{weight: float|int|string, weight_unit?: string, length?: float|int|string|null, width?: float|int|string|null, height?: float|int|string|null, dimension_unit?: string}>  $packages
     * @param  list<string>  $rateRequestTypes
     * @param  list<string>|null  $carrierCodes
     * @param  list<array<string, mixed>>  $commodities  Optional for duties/taxes estimates
     */
    public function __construct(
        public readonly array $shipper,
        public readonly array $recipient,
        public readonly array $packages,
        public readonly string $shipDate,
        public readonly string $pickupType = 'DROPOFF_AT_FEDEX_LOCATION',
        public readonly string $packagingType = 'YOUR_PACKAGING',
        public readonly ?string $serviceType = null,
        public readonly array $rateRequestTypes = ['ACCOUNT', 'LIST'],
        public readonly bool $returnTransitTimes = true,
        public readonly ?array $carrierCodes = null,
        public readonly array $commodities = [],
        public readonly ?string $preferredCurrency = null,
        public readonly ?int $orderId = null,
        public readonly ?int $shipmentPackageId = null,
        public readonly ?string $idempotencySubject = null,
        public readonly ?string $rateDisplayOption = null,
    ) {}

    /**
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $destination
     * @param  array<string, mixed>  $package  Single package shorthand
     */
    public static function fromOriginDestinationPackage(
        array $origin,
        array $destination,
        array $package,
        string $shipDate,
        ?string $serviceType = null,
        ?bool $residential = null,
        string $packagingType = 'YOUR_PACKAGING',
        ?int $orderId = null,
        ?string $idempotencySubject = null,
    ): self {
        $recipient = [
            'postal_code' => $destination['postal_code'] ?? null,
            'country_code' => strtoupper((string) ($destination['country_code'] ?? 'US')),
            'city' => $destination['city'] ?? null,
            'state' => $destination['state'] ?? null,
            'residential' => $residential ?? (array_key_exists('residential', $destination) ? (bool) $destination['residential'] : null),
            'address_line1' => $destination['address_line1'] ?? $destination['street'] ?? null,
            'address_line2' => $destination['address_line2'] ?? null,
        ];

        return new self(
            shipper: [
                'postal_code' => $origin['postal_code'] ?? null,
                'country_code' => strtoupper((string) ($origin['country_code'] ?? 'US')),
                'city' => $origin['city'] ?? null,
                'state' => $origin['state'] ?? null,
            ],
            recipient: $recipient,
            packages: [[
                'weight' => $package['weight'] ?? 1,
                'weight_unit' => $package['weight_unit'] ?? 'LB',
                'length' => $package['length'] ?? null,
                'width' => $package['width'] ?? null,
                'height' => $package['height'] ?? null,
                'dimension_unit' => $package['dimension_unit'] ?? 'IN',
            ]],
            shipDate: $shipDate,
            packagingType: strtoupper($packagingType),
            serviceType: filled($serviceType) ? strtoupper(trim((string) $serviceType)) : null,
            orderId: $orderId,
            idempotencySubject: $idempotencySubject,
        );
    }

    /**
     * @param  list<string>  $rateRequestTypes
     * @param  list<string>|null  $carrierCodes
     */
    public function withPickupAndRateOptions(
        string $pickupType,
        array $rateRequestTypes,
        bool $returnTransitTimes = true,
        ?array $carrierCodes = null,
        ?string $rateDisplayOption = null,
    ): self {
        return new self(
            shipper: $this->shipper,
            recipient: $this->recipient,
            packages: $this->packages,
            shipDate: $this->shipDate,
            pickupType: $pickupType,
            packagingType: $this->packagingType,
            serviceType: $this->serviceType,
            rateRequestTypes: $rateRequestTypes,
            returnTransitTimes: $returnTransitTimes,
            carrierCodes: $carrierCodes,
            commodities: $this->commodities,
            preferredCurrency: $this->preferredCurrency,
            orderId: $this->orderId,
            shipmentPackageId: $this->shipmentPackageId,
            idempotencySubject: $this->idempotencySubject,
            rateDisplayOption: $rateDisplayOption,
        );
    }
}
