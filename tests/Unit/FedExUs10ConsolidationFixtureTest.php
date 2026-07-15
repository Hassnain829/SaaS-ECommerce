<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Operations\FedExConsolidationPayloadFactory;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExShipFixtureResolver;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Tests\TestCase;

class FedExUs10ConsolidationFixtureTest extends TestCase
{
    private const CONSOLIDATION_ACCOUNT = '510087100';

    private const PARCEL_ACCOUNT = '700257037';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'carriers.fedex.validation_us10_enabled' => true,
            'carriers.fedex.validation_us10_consolidation_account' => self::CONSOLIDATION_ACCOUNT,
            'carriers.fedex.validation_us10_shipper_tin' => 'TIN-US10-123',
            'carriers.fedex.consolidation_create_path' => '/ship/v1/consolidations',
            'carriers.fedex.consolidation_shipment_path' => '/ship/v1/consolidations/shipments',
            'carriers.fedex.consolidation_confirm_path' => '/ship/v1/consolidations/confirmations',
            'carriers.fedex.consolidation_confirm_results_path' => '/ship/v1/consolidations/confirmationresults',
        ]);
    }

    public function test_create_consolidation_fixture_matches_workbook(): void
    {
        $fixture = app(FedExConsolidationFixtureService::class)->fixture('IntegratorUS10_CREATE_CONSOLIDATION');
        $payload = app(FedExConsolidationPayloadFactory::class)->buildCreateConsolidation($fixture);
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($payload);

        $this->assertSame('consolidation', $fixture['api_family']);
        $this->assertSame('IntegratorUS10_Create consolidation', $fixture['customer_transaction_id']);
        $this->assertSame('/ship/v1/consolidations', app(FedExConfig::class)->consolidationCreatePath());
        $this->assertSame(self::CONSOLIDATION_ACCOUNT, data_get($payload, 'accountNumber.value'));
        $this->assertNotSame(self::PARCEL_ACCOUNT, data_get($payload, 'accountNumber.value'));
        $this->assertSame(
            FedExConsolidationFixtureService::WORKBOOK_THIRD_PARTY_ACCOUNT,
            data_get($payload, 'requestedConsolidation.soldTo.accountNumber.value'),
        );
        $this->assertSame(
            FedExConsolidationFixtureService::WORKBOOK_THIRD_PARTY_ACCOUNT,
            data_get($payload, 'requestedConsolidation.shippingChargesPayment.payor.responsibleParty.accountNumber.value'),
        );
        $this->assertSame(
            FedExConsolidationFixtureService::WORKBOOK_THIRD_PARTY_ACCOUNT,
            data_get($payload, 'requestedConsolidation.customsClearanceDetail.dutiesPayment.billingDetails.accountNumber.value'),
        );
        $this->assertSame('INTERNATIONAL_PRIORITY_DISTRIBUTION', data_get($payload, 'requestedConsolidation.consolidationType'));
        $this->assertContains('INTERNATIONAL_CONTROLLED_EXPORT_SERVICE', (array) data_get($payload, 'requestedConsolidation.specialServicesRequested.specialServiceTypes'));
        $this->assertSame('DEA_486', data_get($payload, 'requestedConsolidation.specialServicesRequested.internationalControlledExportDetail.type'));
        $this->assertSame('123', data_get($payload, 'requestedConsolidation.specialServicesRequested.internationalControlledExportDetail.licenseOrPermitNumber'));
        $this->assertSame('2024-12-18', data_get($payload, 'requestedConsolidation.specialServicesRequested.internationalControlledExportDetail.licenseOrPermitExpirationDate'));
        $this->assertSame('MEMPHIS', data_get($payload, 'requestedConsolidation.shipper.address.city'));
        $this->assertSame('XIAO LI', data_get($payload, 'requestedConsolidation.shipper.contact.personName'));
        $this->assertSame('PERSONAL_NATIONAL', data_get($payload, 'requestedConsolidation.shipper.tins.0.tinType'));
        $this->assertSame('TOTAL_FREIGHT_CHARGES', data_get($payload, 'requestedConsolidation.consolidationDataSources.0.consolidationDataType'));
        $this->assertSame('ACCUMULATED', data_get($payload, 'requestedConsolidation.consolidationDataSources.0.consolidationDataSourceType'));
        $this->assertContains('CONSOLIDATED_COMMERCIAL_INVOICE', (array) data_get($payload, 'requestedConsolidation.consolidationDocumentSpecification.consolidationDocumentTypes'));
        $this->assertSame('PAPER_LETTER', data_get($payload, 'requestedConsolidation.consolidationDocumentSpecification.consolidatedCommercialInvoiceDetail.documentFormat.stockType'));
        $this->assertSame('PDF', data_get($payload, 'requestedConsolidation.consolidationDocumentSpecification.consolidatedCommercialInvoiceDetail.documentFormat.docType'));
        $this->assertSame('TORONTO', data_get($payload, 'requestedConsolidation.soldTo.address.city'));
        $this->assertSame('THIRD_PARTY', data_get($payload, 'requestedConsolidation.shippingChargesPayment.paymentType'));
        $this->assertSame('THIRD_PARTY', data_get($payload, 'requestedConsolidation.customsClearanceDetail.dutiesPayment.paymentType'));
        $this->assertSame(200, data_get($payload, 'requestedConsolidation.customsClearanceDetail.customsValue.amount'));
        $this->assertSame('YWGI', data_get($payload, 'requestedConsolidation.internationalDistributionDetail.clearanceFacilityLocationId'));
        $this->assertSame('CUSTOMS_VALUE', data_get($payload, 'requestedConsolidation.internationalDistributionDetail.declarationCurrencies.0.value'));
        $this->assertSame('PNG', data_get($payload, 'requestedConsolidation.labelSpecification.imageType'));
        $this->assertArrayNotHasKey('requestedShipment', $payload);
        $this->assertArrayNotHasKey('freightRequestedShipment', $payload);

        $this->assertSame('[REDACTED]', data_get($sanitized, 'accountNumber.value'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'requestedConsolidation.shipper.tins.0.number'));
        $this->assertIsArray(data_get($sanitized, 'requestedConsolidation'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedConsolidation'));
        $this->assertSame('THIRD_PARTY', data_get($sanitized, 'requestedConsolidation.shippingChargesPayment.paymentType'));
        $this->assertSame('consolidation_us10_create', FedExValidationScenarioCatalog::lockedConsolidationScenarios()['IntegratorUS10_CREATE_CONSOLIDATION']['scenario_key']);
        $this->assertTrue(app(FedExShipFixtureResolver::class)->isConsolidationCase('IntegratorUS10_CREATE_CONSOLIDATION'));
    }

    public function test_six_add_shipment_fixtures_preserve_workbook_differences(): void
    {
        $factory = app(FedExConsolidationPayloadFactory::class);
        $fixtures = app(FedExConsolidationFixtureService::class);

        $expected = [
            1 => ['dropoff' => false, 'commodity_id' => false, 'leading_space' => false],
            2 => ['dropoff' => false, 'commodity_id' => true, 'leading_space' => false],
            3 => ['dropoff' => true, 'commodity_id' => true, 'leading_space' => true],
            4 => ['dropoff' => false, 'commodity_id' => false, 'leading_space' => false],
            5 => ['dropoff' => true, 'commodity_id' => false, 'leading_space' => false],
            6 => ['dropoff' => true, 'commodity_id' => true, 'leading_space' => false],
        ];

        foreach ($expected as $sequence => $flags) {
            $key = 'IntegratorUS10_ADD_SHIPMENT_'.$sequence;
            $fixture = $fixtures->fixture($key);
            $payload = $factory->buildAddShipment($fixture);

            $this->assertSame('IntegratorUS10_Add shipment '.$sequence, $fixture['customer_transaction_id']);
            $this->assertSame(FedExConsolidationFixtureService::PLACEHOLDER_INDEX, data_get($payload, 'consolidationKey.index'));
            $this->assertNotSame(FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_INDEX, data_get($payload, 'consolidationKey.index'));
            $this->assertSame('INTERNATIONAL_PRIORITY_DISTRIBUTION', data_get($payload, 'requestedShipment.serviceType'));
            $this->assertSame(35, data_get($payload, 'requestedShipment.requestedPackageLineItems.0.groupPackageCount'));
            $this->assertArrayNotHasKey('physicalPackaging', (array) data_get($payload, 'requestedShipment.requestedPackageLineItems.0', []));
            $this->assertSame('Textbooks', data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.description'));
            $this->assertSame(self::CONSOLIDATION_ACCOUNT, data_get($payload, 'accountNumber.value'));
            $this->assertSame(
                FedExConsolidationFixtureService::WORKBOOK_THIRD_PARTY_ACCOUNT,
                data_get($payload, 'requestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber.value'),
            );

            if ($flags['dropoff']) {
                $this->assertSame('REGULAR_PICKUP', data_get($payload, 'requestedShipment.dropoffType'));
            } else {
                $this->assertArrayNotHasKey('dropoffType', (array) data_get($payload, 'requestedShipment', []));
            }

            if ($flags['commodity_id']) {
                $this->assertSame('commodity Id', data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.commodityId'));
            } else {
                $this->assertArrayNotHasKey('commodityId', (array) data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0', []));
            }

            if ($flags['leading_space']) {
                $this->assertSame(' Suite 101', data_get($payload, 'requestedShipment.shipper.address.streetLines.1'));
                $this->assertSame(' Suite 101', data_get($payload, 'requestedShipment.origin.address.streetLines.1'));
            } else {
                $this->assertSame('Suite 101', data_get($payload, 'requestedShipment.shipper.address.streetLines.1'));
            }

            $scenarioKey = FedExValidationScenarioCatalog::lockedConsolidationScenarios()[$key]['scenario_key'];
            $this->assertSame('consolidation_us10_add_shipment_'.$sequence, $scenarioKey);
        }
    }

    public function test_us01_through_us09_catalog_entries_unchanged(): void
    {
        $locked = FedExValidationScenarioCatalog::lockedShipScenarios();
        $this->assertSame('ship_us01_pdf', $locked['IntegratorUS01']['scenario_key']);
        $this->assertSame('ship_us07_pdf', $locked['IntegratorUS07']['scenario_key']);
        $this->assertSame('ship_us08_zplii', $locked['IntegratorUS08']['scenario_key']);
        $this->assertSame('ship_us09_image_pdf', $locked['IntegratorUS09_IMAGE']['scenario_key']);
        $this->assertSame('ship_us09_document_pdf', $locked['IntegratorUS09_DOCUMENT']['scenario_key']);
        $this->assertArrayNotHasKey('IntegratorUS10_CREATE_CONSOLIDATION', $locked);
    }
}
