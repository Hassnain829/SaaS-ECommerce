<?php

namespace App\Services\Carriers\FedEx\Operations;

final class FedExShipResponseParser
{
    /**
     * @param  array<string, mixed>|null  $responseBody
     * @return array{
     *     service_type: ?string,
     *     master_tracking_number: ?string,
     *     master_tracking_number_last4: ?string,
     *     labels: array<int, array{
     *         package_sequence: int,
     *         tracking_number: ?string,
     *         tracking_number_last4: ?string,
     *         document_type: ?string,
     *         image_type: ?string,
     *         encoded_label: ?string
     *     }>
     * }
     */
    public function parse(?array $responseBody): array
    {
        $labels = [];
        $serviceType = null;
        $masterTracking = null;

        foreach ((array) data_get($responseBody, 'output.transactionShipments', []) as $shipment) {
            if (! is_array($shipment)) {
                continue;
            }

            $serviceType ??= $this->normalizeServiceType(
                $shipment['serviceType']
                    ?? $shipment['serviceName']
                    ?? data_get($responseBody, 'output.serviceType')
            );

            $masterTracking ??= is_string($shipment['masterTrackingNumber'] ?? null)
                ? $shipment['masterTrackingNumber']
                : null;

            foreach ((array) ($shipment['pieceResponses'] ?? []) as $index => $piece) {
                if (! is_array($piece)) {
                    continue;
                }

                $sequence = (int) ($piece['packageSequenceNumber'] ?? ($index + 1));
                $labelDocument = $this->selectShippingLabelDocument((array) ($piece['packageDocuments'] ?? []));

                if ($labelDocument === null) {
                    continue;
                }

                $trackingNumber = is_string($piece['trackingNumber'] ?? null)
                    ? $piece['trackingNumber']
                    : $masterTracking;

                $labels[$sequence] = [
                    'package_sequence' => $sequence,
                    'tracking_number' => $trackingNumber,
                    'tracking_number_last4' => $this->lastFour($trackingNumber),
                    'document_type' => $labelDocument['docType'] ?? null,
                    'image_type' => $labelDocument['imageType'] ?? null,
                    'encoded_label' => is_string($labelDocument['encodedLabel'] ?? null)
                        ? $labelDocument['encodedLabel']
                        : null,
                ];
            }
        }

        ksort($labels);

        return [
            'service_type' => $serviceType,
            'master_tracking_number' => $masterTracking,
            'master_tracking_number_last4' => $this->lastFour($masterTracking),
            'labels' => $labels,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return array<string, mixed>|null
     */
    private function selectShippingLabelDocument(array $documents): ?array
    {
        $candidates = [];

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $docType = strtoupper((string) ($document['docType'] ?? ''));
            $contentType = strtoupper((string) ($document['contentType'] ?? ''));

            if ($docType === 'LABEL' || $contentType === 'LABEL' || isset($document['encodedLabel'])) {
                $candidates[] = $document;
            }
        }

        if ($candidates === []) {
            return null;
        }

        if (count($candidates) > 1) {
            foreach ($candidates as $candidate) {
                if (strtoupper((string) ($candidate['docType'] ?? '')) === 'LABEL') {
                    return $candidate;
                }
            }
        }

        return $candidates[0];
    }

    private function normalizeServiceType(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtoupper(trim(str_replace(['®', ' '], ['', '_'], $value)));
    }

    private function lastFour(?string $value): ?string
    {
        if (! is_string($value) || strlen($value) < 4) {
            return null;
        }

        return substr($value, -4);
    }
}
