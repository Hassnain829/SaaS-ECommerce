<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Operations\FedExFreightLtlResponseParser;
use Tests\Support\FedExShipTestEvidenceFactory;
use Tests\TestCase;

class FedExFreightLtlResponseParserTest extends TestCase
{
    public function test_parser_extracts_zplii_label_and_straight_bill_of_lading(): void
    {
        $zpl = FedExShipTestEvidenceFactory::validZplBinary();
        $bol = FedExShipTestEvidenceFactory::validPdfBinary();
        $ci = "%PDF-1.4\ninvoice\n%%EOF";

        $parsed = app(FedExFreightLtlResponseParser::class)->parse([
            'output' => [
                'transactionShipments' => [[
                    'serviceType' => 'FEDEX_FREIGHT_PRIORITY',
                    'masterTrackingNumber' => '794600001111',
                    'pieceResponses' => [[
                        'packageSequenceNumber' => 1,
                        'trackingNumber' => '794600001111',
                        'packageDocuments' => [[
                            'contentType' => 'LABEL',
                            'docType' => 'ZPLII',
                            'imageType' => 'ZPLII',
                            'encodedLabel' => base64_encode($zpl),
                        ]],
                    ]],
                    'shipmentDocuments' => [
                        [
                            'contentType' => 'FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING',
                            'docType' => 'PDF',
                            'imageType' => 'PDF',
                            'encodedLabel' => base64_encode($bol),
                        ],
                        [
                            'contentType' => 'COMMERCIAL_INVOICE',
                            'docType' => 'PDF',
                            'imageType' => 'PDF',
                            'encodedLabel' => base64_encode($ci),
                        ],
                    ],
                ]],
            ],
        ]);

        $this->assertSame('FEDEX_FREIGHT_PRIORITY', $parsed['service_type']);
        $this->assertSame(1, $parsed['tracking_number_count']);
        $this->assertSame('1111', $parsed['master_tracking_number_last4']);
        $this->assertArrayHasKey(1, $parsed['labels']);
        $this->assertSame('ZPLII', $parsed['labels'][1]['image_type']);
        $this->assertSame(strlen($zpl), $parsed['labels'][1]['decoded_bytes']);
        $this->assertSame(
            'output.transactionShipments.0.pieceResponses.0.packageDocuments',
            $parsed['labels'][1]['response_path']
        );
        $this->assertTrue($parsed['bol_present']);
        $this->assertTrue($parsed['commercial_invoice_present']);
        $this->assertSame(
            'output.transactionShipments.0.shipmentDocuments.0',
            collect($parsed['documents'])->firstWhere('is_bol', true)['response_path'] ?? null
        );
        $this->assertSame(
            'output.transactionShipments.0.shipmentDocuments.1',
            collect($parsed['documents'])->firstWhere('is_commercial_invoice', true)['response_path'] ?? null
        );
    }
}
