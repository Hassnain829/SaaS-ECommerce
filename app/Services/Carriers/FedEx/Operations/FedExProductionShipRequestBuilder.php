<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Store;

/**
 * Builds fixture-shaped ship payloads for production merchant Ship API calls.
 */
final class FedExProductionShipRequestBuilder
{
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
    ): array {
        $packages = $this->normalizePackages($input['packages'] ?? []);
        $labelFormat = strtoupper((string) ($input['label_format'] ?? 'PDF'));
        if (! in_array($labelFormat, ['PDF', 'PNG', 'ZPL'], true)) {
            $labelFormat = 'PDF';
        }

        $isReturn = ! empty($input['return_shipment']);

        $merchantParty = [
            'person_name' => (string) ($input['shipper_name'] ?? $store->name ?? 'Shipper'),
            'company_name' => (string) ($origin->name ?: $store->name),
            'phone' => (string) ($origin->phone ?: ($input['shipper_phone'] ?? '9015550100')),
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
            'phone' => (string) ($recipient->phone ?: ($input['recipient_phone'] ?? '9015550199')),
            'street_lines' => array_values(array_filter([
                $recipient->address_line1,
                $recipient->address_line2,
            ])),
            'city' => $recipient->city,
            'state' => $recipient->province_code ?: $recipient->state,
            'postal_code' => $recipient->postal_code,
            'country_code' => strtoupper((string) ($recipient->country_code ?: 'US')),
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
            'pickup_type' => (string) ($input['pickup_type'] ?? 'USE_SCHEDULED_PICKUP'),
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

        if (! empty($input['etd_enabled']) && filled($input['etd_document_id'] ?? null)) {
            $fixture['shipment_special_services'] = array_merge(
                $fixture['shipment_special_services'] ?? [],
                [
                    'specialServiceTypes' => array_values(array_unique(array_merge(
                        (array) data_get($fixture, 'shipment_special_services.specialServiceTypes', []),
                        ['ELECTRONIC_TRADE_DOCUMENTS'],
                    ))),
                    'etdDetail' => [
                        'attributes' => ['POST_SHIPMENT_UPLOAD_REQUESTED'],
                        'attachedDocuments' => [[
                            'documentType' => 'COMMERCIAL_INVOICE',
                            'documentReference' => (string) $input['etd_document_id'],
                        ]],
                    ],
                ],
            );
        }

        return $fixture;
    }

    /**
     * @param  mixed  $packages
     * @return list<array<string, mixed>>
     */
    private function normalizePackages(mixed $packages): array
    {
        if (! is_array($packages) || $packages === []) {
            return [[
                'weight' => 1,
                'weight_unit' => 'LB',
                'length' => 9,
                'width' => 6,
                'height' => 2,
                'dimension_unit' => 'IN',
            ]];
        }

        if (! array_is_list($packages)) {
            $packages = [$packages];
        }

        $normalized = [];
        foreach ($packages as $index => $package) {
            if (! is_array($package)) {
                continue;
            }
            $normalized[] = [
                'sequence_number' => (int) ($package['sequence_number'] ?? ($index + 1)),
                'weight' => max(0.01, (float) ($package['weight'] ?? 1)),
                'weight_unit' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                'length' => max(1.0, (float) ($package['length'] ?? 9)),
                'width' => max(1.0, (float) ($package['width'] ?? 6)),
                'height' => max(1.0, (float) ($package['height'] ?? 2)),
                'dimension_unit' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
            ];
        }

        return $normalized !== [] ? $normalized : [[
            'weight' => 1,
            'weight_unit' => 'LB',
            'length' => 9,
            'width' => 6,
            'height' => 2,
            'dimension_unit' => 'IN',
        ]];
    }
}
