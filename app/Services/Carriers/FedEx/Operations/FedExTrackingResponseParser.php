<?php

namespace App\Services\Carriers\FedEx\Operations;

/**
 * Normalizes FedEx BIV tracking payloads into merchant-safe timeline data.
 */
final class FedExTrackingResponseParser
{
    /**
     * @param  array<string, mixed>|null  $responseBody
     * @return array{
     *     status: ?string,
     *     estimated_delivery: ?string,
     *     delivered_at: ?string,
     *     exception: ?string,
     *     timeline: list<array{code: ?string, description: string, occurred_at: ?string, city: ?string, state: ?string}>
     * }
     */
    public function parse(?array $responseBody): array
    {
        $complete = data_get($responseBody, 'output.completeTrackResults.0.trackResults.0');
        if (! is_array($complete)) {
            $complete = data_get($responseBody, 'output.trackResults.0');
        }
        if (! is_array($complete)) {
            return [
                'status' => null,
                'estimated_delivery' => null,
                'delivered_at' => null,
                'exception' => null,
                'timeline' => [],
            ];
        }

        $status = data_get($complete, 'latestStatusDetail.description')
            ?? data_get($complete, 'latestStatusDetail.statusByLocale')
            ?? data_get($complete, 'latestStatusDetail.code');

        $estimated = data_get($complete, 'dateAndTimes', []);
        $estimatedDelivery = null;
        $deliveredAt = null;
        if (is_array($estimated)) {
            foreach ($estimated as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $type = strtoupper((string) ($entry['type'] ?? ''));
                $dateTime = $entry['dateTime'] ?? null;
                if (! is_string($dateTime) || $dateTime === '') {
                    continue;
                }
                if (in_array($type, ['ESTIMATED_DELIVERY', 'EXPECTED_DELIVERY'], true)) {
                    $estimatedDelivery = $dateTime;
                }
                if ($type === 'ACTUAL_DELIVERY') {
                    $deliveredAt = $dateTime;
                }
            }
        }

        $exception = data_get($complete, 'latestStatusDetail.ancillaryDetails.0.reasonDescription')
            ?? data_get($complete, 'error.message');

        $timeline = [];
        foreach ((array) data_get($complete, 'scanEvents', []) as $event) {
            if (! is_array($event)) {
                continue;
            }
            $timeline[] = [
                'code' => isset($event['eventType']) ? (string) $event['eventType'] : null,
                'description' => (string) ($event['eventDescription'] ?? $event['derivedStatus'] ?? 'Update'),
                'occurred_at' => isset($event['date']) ? (string) $event['date'] : null,
                'city' => data_get($event, 'scanLocation.city'),
                'state' => data_get($event, 'scanLocation.stateOrProvinceCode'),
            ];
        }

        return [
            'status' => is_string($status) ? $status : null,
            'estimated_delivery' => $estimatedDelivery,
            'delivered_at' => $deliveredAt,
            'exception' => is_string($exception) ? $exception : null,
            'timeline' => $timeline,
        ];
    }
}
