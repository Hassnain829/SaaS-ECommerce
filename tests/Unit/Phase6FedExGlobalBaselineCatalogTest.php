<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Validation\FedExCanadaShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExGlobalShipCaseCatalog;
use App\Services\Carriers\FedEx\Validation\FedExShipFixtureResolver;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use App\Services\Carriers\FedEx\Validation\Payload\FedExCustomsClearanceBuilder;
use Tests\TestCase;

class Phase6FedExGlobalBaselineCatalogTest extends TestCase
{
    public function test_canada_catalog_matches_workbook_inventory(): void
    {
        $cases = FedExGlobalShipCaseCatalog::casesByRegion()[FedExGlobalShipCaseCatalog::REGION_CA];
        $keys = collect($cases)->pluck('case_key')->all();

        $this->assertSame([
            'IntegratorCA01',
            'IntegratorCA02',
            'IntegratorCA03',
            'IntegratorCA04',
            'IntegratorCA05',
        ], $keys);

        $this->assertNotContains('IntegratorCA06', $keys);

        $this->assertSame('IntegratorCA01', FedExGlobalShipCaseCatalog::transactionRepresentatives('CA')['PDF']);
        $this->assertSame('IntegratorCA02', FedExGlobalShipCaseCatalog::transactionRepresentatives('CA')['PNG']);
        $this->assertSame('IntegratorCA05', FedExGlobalShipCaseCatalog::transactionRepresentatives('CA')['ZPLII']);
    }

    public function test_canada_fixtures_match_expected_formats_and_services(): void
    {
        $fixtures = app(FedExCanadaShipTestCaseFixtureService::class)->fixtures();

        $this->assertSame('PDF', $fixtures['IntegratorCA01']['label_format']);
        $this->assertSame('FEDEX_EXPRESS_SAVER', $fixtures['IntegratorCA01']['service_type']);

        $this->assertSame('PNG', $fixtures['IntegratorCA02']['label_format']);
        $this->assertSame('PRIORITY_OVERNIGHT', $fixtures['IntegratorCA02']['service_type']);
        $this->assertSame('FEDEX_TUBE', $fixtures['IntegratorCA02']['packaging_type']);

        $this->assertSame('PDF', $fixtures['IntegratorCA03']['label_format']);
        $this->assertSame('saturday_delivery_friday', $fixtures['IntegratorCA03']['ship_date_strategy']);
        $this->assertTrue($fixtures['IntegratorCA03']['recipient']['residential']);

        $this->assertSame('PDF', $fixtures['IntegratorCA04']['label_format']);
        $this->assertSame('THIRD_PARTY', $fixtures['IntegratorCA04']['transportation_payment_type']);

        $this->assertSame('ZPLII', $fixtures['IntegratorCA05']['label_format']);
        $this->assertSame('FEDEX_GROUND', $fixtures['IntegratorCA05']['service_type']);
    }

    public function test_canada_payload_factory_uses_workbook_account_and_customs(): void
    {
        $resolver = app(FedExShipFixtureResolver::class);
        $factory = new FedExShipPayloadFactory($resolver, new FedExCustomsClearanceBuilder);
        $account = new \App\Models\CarrierAccount(['provider_account_number' => '700257037']);

        $ca04 = $factory->buildShipmentPayload($account, $resolver->fixture('IntegratorCA04'), 'PDF');
        $this->assertSame('614365501', data_get($ca04, 'accountNumber.value'));
        $this->assertSame('150067600', data_get($ca04, 'requestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber.value'));
        $this->assertSame('DIRECT', data_get($ca04, 'requestedShipment.requestedPackageLineItems.0.packageSpecialServices.signatureOptionType'));
        $this->assertSame('THIRD_PARTY', data_get($ca04, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertSame('THIRD_PARTY', data_get($ca04, 'requestedShipment.customsClearanceDetail.dutiesPayment.paymentType'));
        $this->assertSame('Dictionaries', data_get($ca04, 'requestedShipment.customsClearanceDetail.commodities.0.description'));

        $ca01 = $factory->buildShipmentPayload($account, $resolver->fixture('IntegratorCA01'), 'PDF');
        $this->assertSame('614365501', data_get($ca01, 'accountNumber.value'));
        $this->assertSame('CA', data_get($ca01, 'requestedShipment.shipper.address.countryCode'));
    }

    public function test_global_scenario_catalog_excludes_rate_only_case(): void
    {
        $scenarios = FedExValidationScenarioCatalog::globalShipScenarios();

        $this->assertArrayHasKey('IntegratorCA01', $scenarios);
        $this->assertArrayNotHasKey('IntegratorCA06', $scenarios);
        $this->assertTrue($scenarios['IntegratorCA01']['transaction_representative']);
        $this->assertFalse($scenarios['IntegratorCA03']['transaction_representative']);
    }
}
