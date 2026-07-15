<?php

namespace App\Services\Carriers\FedEx\Operations;

/**
 * Parses FedEx Freight LTL create-shipment responses (POST /ship/v1/freight/shipments).
 *
 * Official response paths (Freight LTL OpenAPI / ShipStream SDK):
 * - output.transactionShipments[].serviceType
 * - output.transactionShipments[].masterTrackingNumber
 * - output.transactionShipments[].pieceResponses[].packageSequenceNumber
 * - output.transactionShipments[].pieceResponses[].trackingNumber
 * - output.transactionShipments[].pieceResponses[].packageDocuments[] (labels)
 * - output.transactionShipments[].shipmentDocuments[] (BOL / commercial invoice)
 */
final class FedExFreightLtlResponseParser
{
    public const CONTENT_TYPE_LABEL = 'LABEL';

    public const CONTENT_TYPE_BOL = 'FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING';

    public const CONTENT_TYPE_COMMERCIAL_INVOICE = 'COMMERCIAL_INVOICE';

    /**
     * @param  array<string, mixed>|null  $responseBody
     * @return array{
     *     service_type: ?string,
     *     master_tracking_number: ?string,
     *     master_tracking_number_last4: ?string,
     *     tracking_number_count: int,
     *     labels: array<int, array<string, mixed>>,
     *     documents: list<array<string, mixed>>,
     *     bol_present: bool,
     *     commercial_invoice_present: bool,
     *     response_paths: list<string>
     * }
     */
    public function parse(?array $responseBody): array
    {
        $labels = [];
        $documents = [];
        $serviceType = null;
        $masterTracking = null;
        $trackingNumbers = [];
        $paths = [];

        foreach ((array) data_get($responseBody, 'output.transactionShipments', []) as $shipmentIndex => $shipment) {
            if (! is_array($shipment)) {
                continue;
            }

            $shipmentPath = 'output.transactionShipments.'.$shipmentIndex;
            $paths[] = $shipmentPath;

            $serviceType ??= $this->normalizeServiceType($shipment['serviceType'] ?? null);
            if (is_string($shipment['masterTrackingNumber'] ?? null) && $shipment['masterTrackingNumber'] !== '') {
                $masterTracking ??= $shipment['masterTrackingNumber'];
                $trackingNumbers[$shipment['masterTrackingNumber']] = true;
            }

            foreach ((array) ($shipment['pieceResponses'] ?? []) as $pieceIndex => $piece) {
                if (! is_array($piece)) {
                    continue;
                }

                $piecePath = $shipmentPath.'.pieceResponses.'.$pieceIndex;
                $paths[] = $piecePath;

                $sequence = (int) ($piece['packageSequenceNumber'] ?? ($pieceIndex + 1));
                $trackingNumber = is_string($piece['trackingNumber'] ?? null) && $piece['trackingNumber'] !== ''
                    ? $piece['trackingNumber']
                    : $masterTracking;
                if (is_string($trackingNumber) && $trackingNumber !== '') {
                    $trackingNumbers[$trackingNumber] = true;
                }

                $labelDocument = $this->selectLabelDocument((array) ($piece['packageDocuments'] ?? []));
                if ($labelDocument === null) {
                    continue;
                }

                $paths[] = $piecePath.'.packageDocuments';
                $encoded = is_string($labelDocument['encodedLabel'] ?? null) ? $labelDocument['encodedLabel'] : null;
                $binary = $this->decodeBinary($encoded);

                $labels[$sequence] = [
                    'package_sequence' => $sequence,
                    'tracking_number' => $trackingNumber,
                    'tracking_number_last4' => $this->lastFour($trackingNumber),
                    'document_type' => $labelDocument['contentType'] ?? $labelDocument['docType'] ?? self::CONTENT_TYPE_LABEL,
                    'content_type' => $labelDocument['contentType'] ?? null,
                    'doc_type' => $labelDocument['docType'] ?? null,
                    'image_type' => $this->resolveImageType($labelDocument),
                    'encoded_label' => $encoded,
                    'decoded_bytes' => $binary === null ? 0 : strlen($binary),
                    'response_path' => $piecePath.'.packageDocuments',
                ];
            }

            foreach ((array) ($shipment['shipmentDocuments'] ?? []) as $docIndex => $document) {
                if (! is_array($document)) {
                    continue;
                }

                $docPath = $shipmentPath.'.shipmentDocuments.'.$docIndex;
                $paths[] = $docPath;

                $contentType = strtoupper((string) ($document['contentType'] ?? ''));
                $docType = strtoupper((string) ($document['docType'] ?? ''));
                $encoded = is_string($document['encodedLabel'] ?? null) ? $document['encodedLabel'] : null;
                $binary = $this->decodeBinary($encoded);

                $documents[] = [
                    'content_type' => $contentType !== '' ? $contentType : null,
                    'doc_type' => $docType !== '' ? $docType : null,
                    'image_type' => $this->resolveImageType($document),
                    'document_type' => $contentType !== '' ? $contentType : $docType,
                    'tracking_number' => is_string($document['trackingNumber'] ?? null) ? $document['trackingNumber'] : null,
                    'tracking_number_last4' => $this->lastFour(
                        is_string($document['trackingNumber'] ?? null) ? $document['trackingNumber'] : null
                    ),
                    'encoded_label' => $encoded,
                    'decoded_bytes' => $binary === null ? 0 : strlen($binary),
                    'response_path' => $docPath,
                    'is_bol' => $contentType === self::CONTENT_TYPE_BOL,
                    'is_commercial_invoice' => $contentType === self::CONTENT_TYPE_COMMERCIAL_INVOICE,
                ];
            }
        }

        ksort($labels);

        return [
            'service_type' => $serviceType,
            'master_tracking_number' => $masterTracking,
            'master_tracking_number_last4' => $this->lastFour($masterTracking),
            'tracking_number_count' => count($trackingNumbers),
            'labels' => $labels,
            'documents' => $documents,
            'bol_present' => collect($documents)->contains(fn (array $doc): bool => (bool) ($doc['is_bol'] ?? false)),
            'commercial_invoice_present' => collect($documents)->contains(
                fn (array $doc): bool => (bool) ($doc['is_commercial_invoice'] ?? false)
            ),
            'response_paths' => array_values(array_unique($paths)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return array<string, mixed>|null
     */
    private function selectLabelDocument(array $documents): ?array
    {
        $candidates = [];

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $contentType = strtoupper((string) ($document['contentType'] ?? ''));
            $docType = strtoupper((string) ($document['docType'] ?? ''));

            if (
                $contentType === self::CONTENT_TYPE_LABEL
                || $docType === self::CONTENT_TYPE_LABEL
                || in_array($docType, ['ZPLII', 'ZPL', 'PNG', 'PDF'], true)
                || filled($document['encodedLabel'] ?? null)
            ) {
                $candidates[] = $document;
            }
        }

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if (strtoupper((string) ($candidate['contentType'] ?? '')) === self::CONTENT_TYPE_LABEL) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function resolveImageType(array $document): ?string
    {
        foreach (['imageType', 'docType'] as $key) {
            $value = strtoupper((string) ($document[$key] ?? ''));
            if (in_array($value, ['ZPLII', 'ZPL', 'PNG', 'PDF'], true)) {
                return $value === 'ZPL' ? 'ZPLII' : $value;
            }
        }

        return null;
    }

    private function decodeBinary(?string $encoded): ?string
    {
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $binary = base64_decode($encoded, true);

        return $binary === false ? null : $binary;
    }

    private function normalizeServiceType(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtoupper(trim($value));
    }

    private function lastFour(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return strlen($value) <= 4 ? $value : substr($value, -4);
    }
}
