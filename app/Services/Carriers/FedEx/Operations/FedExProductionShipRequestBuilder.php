<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Store;
use App\Services\Carriers\FedEx\Support\FedExHandoffTypeResolver;
use App\Services\Carriers\FedEx\Support\FedExShipperPhoneResolver;
use Illuminate\Validation\ValidationException;

/**
 * Builds fixture-shaped ship payloads for production merchant Ship API calls.
 */
final class FedExProductionShipRequestBuilder
{
    public function __construct(
        private readonly FedExHandoffTypeResolver $handoffTypeResolver,
        private readonly FedExShipperPhoneResolver $shipperPhoneResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildFixture(
        Store $store,
        Order $order,
        Location $origin,
        OrderAddress $recipient,
        array $input,
        ?CarrierAccount $account = null,
    ): array {
        $packages = $this->normalizePackages($input['packages'] ?? []);
        $labelFormat = strtoupper((string) ($input['label_format'] ?? 'PDF'));
        if (! in_array($labelFormat, ['PDF', 'PNG', 'ZPL'], true)) {
            $labelFormat = 'PDF';
        }

        $isReturn = ! empty($input['return_shipment']);

        $shipperPhone = trim((string) ($input['shipper_phone'] ?? ''));
        if ($shipperPhone === '') {
            $shipperPhone = $this->shipperPhoneResolver->resolveAndBackfill($origin, $account);
        }
        $recipientPhone = trim((string) ($recipient->phone ?: ($input['recipient_phone'] ?? '')));

        if ($shipperPhone === '') {
            throw ValidationException::withMessages([
                'shipper_phone' => 'Add a phone number on the ship-from location (or during FedEx connect) before creating a FedEx label.',
            ]);
        }

        if ($recipientPhone === '') {
            throw ValidationException::withMessages([
                'recipient_phone' => 'Add a customer phone number before creating a FedEx label.',
            ]);
        }

        $merchantParty = [
            'person_name' => (string) ($input['shipper_name'] ?? $store->name ?? 'Shipper'),
            'company_name' => (string) ($origin->name ?: $store->name),
            'phone' => $shipperPhone,
            'street_lines' => array_values(array_filter([
                $origin->address_line1,
                $origin->address_line2,
            ])),
            'city' => $origin->city,
            'state' => $origin->state,
            'postal_code' => $origin->postal_code,
            'country_code' => strtoupper((string) ($origin->country_code ?: 'US')),
        ];

        $customerParty = [
            'person_name' => (string) ($recipient->name ?: 'Recipient'),
            'company_name' => $recipient->company,
            'phone' => $recipientPhone,
            'street_lines' => array_values(array_filter([
                data_get($input, 'destination_override.address_line1') ?: $recipient->address_line1,
                data_get($input, 'destination_override.address_line2') ?: $recipient->address_line2,
            ])),
            'city' => data_get($input, 'destination_override.city') ?: $recipient->city,
            'state' => data_get($input, 'destination_override.state')
                ?: ($recipient->province_code ?: $recipient->state),
            'postal_code' => data_get($input, 'destination_override.postal_code') ?: $recipient->postal_code,
            'country_code' => strtoupper((string) (
                data_get($input, 'destination_override.country_code')
                ?: ($recipient->country_code ?: 'US')
            )),
            'residential' => (bool) ($input['residential'] ?? false),
        ];

        // Return labels: customer ships back to merchant warehouse.
        $shipper = $isReturn ? $customerParty : $merchantParty;
        $recipientParty = $isReturn ? array_merge($merchantParty, ['residential' => false]) : $customerParty;

        $specialServices = [];
        $specialTypes = [];
        if (! empty($input['saturday_delivery']) && ! $isReturn) {
            $specialTypes[] = 'SATURDAY_DELIVERY';
        }
        if ($isReturn) {
            $specialTypes[] = 'RETURN_SHIPMENT';
            $specialServices['returnShipmentDetail'] = [
                'returnType' => 'PRINT_RETURN_LABEL',
            ];
        }
        if ($specialTypes !== []) {
            $specialServices['specialServiceTypes'] = array_values(array_unique($specialTypes));
        }

        $fixture = [
            'service_type' => strtoupper((string) ($input['service_type'] ?? 'FEDEX_GROUND')),
            'packaging_type' => strtoupper((string) ($input['packaging_type'] ?? 'YOUR_PACKAGING')),
            'label_format' => $labelFormat,
            'label_stock_type' => (string) ($input['label_stock_type'] ?? 'PAPER_4X6'),
            'pickup_type' => $this->handoffTypeResolver->resolve(
                $store,
                isset($input['pickup_type']) ? (string) $input['pickup_type'] : null,
            ),
            'ship_date' => (string) ($input['ship_date'] ?? now()->toDateString()),
            'shipper' => $shipper,
            'recipient' => $recipientParty,
            'packages' => $packages,
            'total_package_count' => count($packages),
            'preferred_currency' => strtoupper((string) ($input['currency'] ?? $order->currency_code ?? 'USD')),
            'return_shipment' => $isReturn,
        ];

        if ($specialServices !== []) {
            $fixture['shipment_special_services'] = $specialServices;
        }

        if (filled($input['email_notification'] ?? null)) {
            $fixture['email_notification'] = (string) $input['email_notification'];
        }

        if (isset($input['declared_value_amount']) && is_numeric($input['declared_value_amount'])) {
            $fixture['total_declared_value'] = [
                'amount' => (float) $input['declared_value_amount'],
                'currency' => strtoupper((string) ($input['declared_value_currency'] ?? $fixture['preferred_currency'])),
            ];
        }

        $customerReferences = array_values(array_filter([
            filled($order->order_number) ? [
                'customer_reference_type' => 'CUSTOMER_REFERENCE',
                'value' => (string) $order->order_number,
            ] : null,
            filled($input['shipping_reference'] ?? null) ? [
                'customer_reference_type' => 'INVOICE_NUMBER',
                'value' => (string) $input['shipping_reference'],
            ] : null,
        ]));

        if ($customerReferences !== []) {
            foreach ($fixture['packages'] as $index => $package) {
                $fixture['packages'][$index]['customer_references'] = $customerReferences;
            }
        }

        if (! empty($input['signature_option'])) {
            foreach ($fixture['packages'] as $index => $package) {
                $fixture['packages'][$index]['package_special_services'] = [
                    'specialServiceTypes' => ['SIGNATURE_OPTION'],
                    'signatureOptionType' => strtoupper((string) $input['signature_option']),
                ];
            }
        }

        if (isset($input['customs_clearance']) && is_array($input['customs_clearance'])) {
            $fixture['customs_clearance'] = $input['customs_clearance'];
        }

        // Pre-shipment ETD only: attach the FedEx documentId from Trade Documents Upload.
        // Do not mix POST_SHIPMENT_UPLOAD_REQUESTED / documentReference into this path.
        if (! empty($input['etd_enabled']) && filled($input['etd_document_id'] ?? null)) {
            $fixture['shipment_special_services'] = array_merge(
                $fixture['shipment_special_services'] ?? [],
                [
                    'specialServiceTypes' => array_values(array_unique(array_merge(
                        (array) data_get($fixture, 'shipment_special_services.specialServiceTypes', []),
                        ['ELECTRONIC_TRADE_DOCUMENTS'],
                    ))),
                    'etdDetail' => [
                        'attachedDocuments' => [[
                            'documentType' => strtoupper((string) ($input['etd_document_type'] ?? 'COMMERCIAL_INVOICE')),
                            'documentId' => (string) $input['etd_document_id'],
                        ]],
                    ],
                ],
            );
        }

        return $fixture;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizePackages(mixed $packages): array
    {
        if (! is_array($packages) || $packages === []) {
            throw ValidationException::withMessages([
                'packages' => 'Add at least one package with weight and dimensions before creating a FedEx label.',
            ]);
        }

        if (! array_is_list($packages)) {
            $packages = [$packages];
        }

        $normalized = [];
        foreach ($packages as $index => $package) {
            if (! is_array($package)) {
                continue;
            }

            $weight = $package['weight'] ?? null;
            $length = $package['length'] ?? null;
            $width = $package['width'] ?? null;
            $height = $package['height'] ?? null;

            if (! is_numeric($weight) || (float) $weight <= 0) {
                throw ValidationException::withMessages([
                    "packages.{$index}.weight" => 'Each package needs a weight greater than zero.',
                ]);
            }

            if (! is_numeric($length) || (float) $length <= 0
                || ! is_numeric($width) || (float) $width <= 0
                || ! is_numeric($height) || (float) $height <= 0
            ) {
                throw ValidationException::withMessages([
                    "packages.{$index}.dimensions" => 'Each package needs length, width, and height.',
                ]);
            }

            $normalized[] = [
                'sequence_number' => (int) ($package['sequence_number'] ?? ($index + 1)),
                'weight' => max(0.01, (float) $weight),
                'weight_unit' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                'length' => max(1.0, (float) $length),
                'width' => max(1.0, (float) $width),
                'height' => max(1.0, (float) $height),
                'dimension_unit' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'packages' => 'Add at least one package with weight and dimensions before creating a FedEx label.',
            ]);
        }

        return $normalized;
    }
}
