<?php

namespace Tests\Support;

use App\Models\CarrierAccount;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use Carbon\Carbon;

final class FedExShipTestEvidenceFactory
{
    public static function validPdfBinary(): string
    {
        return "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
    }

    public static function validZplBinary(): string
    {
        return "^XA^FO50,50^FDTest^FS^XZ";
    }

    public static function validPngBinary(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    /**
     * @return array{request: array<string, mixed>, response: array<string, mixed>}
     */
    public static function eventBodies(CarrierAccount $account, string $testCaseKey, ?Carbon $now = null): array
    {
        $fixtureService = app(FedExShipTestCaseFixtureService::class);
        $fixture = $fixtureService->fixture($testCaseKey);
        $labelFormat = (string) $fixture['label_format'];

        Carbon::setTestNow($now ?? Carbon::parse('2026-06-26')); // Friday
        $shipDateOverride = ($fixture['ship_date_strategy'] ?? null) === 'next_valid_friday'
            ? ['ship_date' => $fixtureService->nextValidFriday($now ?? Carbon::parse('2026-06-26'))]
            : [];

        $request = app(FedExShipPayloadFactory::class)->buildShipmentPayload(
            $account,
            $fixture,
            $labelFormat,
            $shipDateOverride,
        );

        $packageCount = (int) ($fixture['expected_package_count'] ?? count($fixture['packages'] ?? []));
        $pieceResponses = [];
        for ($sequence = 1; $sequence <= $packageCount; $sequence++) {
            $encoded = base64_encode(match ($labelFormat) {
                'PNG' => self::validPngBinary(),
                'ZPLII', 'ZPL' => self::validZplBinary(),
                default => self::validPdfBinary(),
            });

            $pieceResponses[] = [
                'packageSequenceNumber' => $sequence,
                'trackingNumber' => '79461234567'.$sequence,
                'packageDocuments' => [[
                    'docType' => 'LABEL',
                    'encodedLabel' => $encoded,
                    'imageType' => $labelFormat,
                ]],
            ];
        }

        $response = [
            'transactionId' => 'fedex-ship-test-'.$testCaseKey,
            'output' => [
                'transactionShipments' => [[
                    'serviceType' => $fixture['service_type'],
                    'masterTrackingNumber' => '794612345678',
                    'pieceResponses' => $pieceResponses,
                ]],
            ],
        ];

        Carbon::setTestNow();

        return [
            'request' => $request,
            'response' => $response,
        ];
    }
}
