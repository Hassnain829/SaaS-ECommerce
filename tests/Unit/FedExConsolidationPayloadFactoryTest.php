<?php

namespace Tests\Unit;

use App\Services\Carriers\FedEx\Operations\FedExConsolidationPayloadFactory;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;
use Tests\TestCase;

class FedExConsolidationPayloadFactoryTest extends TestCase
{
    private const ACCOUNT = '510087100';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'carriers.fedex.validation_us10_enabled' => true,
            'carriers.fedex.validation_us10_consolidation_account' => self::ACCOUNT,
            'carriers.fedex.validation_us10_shipper_tin' => 'TIN-123',
        ]);
    }

    public function test_dynamic_consolidation_key_and_job_id_injection(): void
    {
        $fixtures = app(FedExConsolidationFixtureService::class);
        $factory = app(FedExConsolidationPayloadFactory::class);

        $dynamicKey = [
            'type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION',
            'index' => 'LIVE-INDEX-999',
            'date' => '2026-07-11',
        ];

        $add = $factory->buildAddShipment($fixtures->withConsolidationKey(
            $fixtures->fixture('IntegratorUS10_ADD_SHIPMENT_1'),
            $dynamicKey,
        ));
        $this->assertSame('LIVE-INDEX-999', data_get($add, 'consolidationKey.index'));
        $this->assertSame('2026-07-11', data_get($add, 'consolidationKey.date'));
        $this->assertNotSame(FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_INDEX, data_get($add, 'consolidationKey.index'));

        $confirm = $factory->buildConfirmConsolidation($fixtures->withConsolidationKey(
            $fixtures->fixture('IntegratorUS10_CONFIRM_CONSOLIDATION'),
            $dynamicKey,
        ));
        $this->assertSame('LIVE-INDEX-999', data_get($confirm, 'consolidationKey.index'));
        $this->assertSame('ALLOW_ASYNCHRONOUS', data_get($confirm, 'processingOptionType'));
        $this->assertSame('PDF', data_get($confirm, 'labelSpecification.imageType'));

        $results = $factory->buildConfirmResults($fixtures->withJobId(
            $fixtures->fixture('IntegratorUS10_CONFIRM_RESULTS'),
            'LIVE-JOB-ABC',
        ));
        $this->assertSame('LIVE-JOB-ABC', data_get($results, 'jobId'));
        $this->assertNotSame(FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_JOB_ID, data_get($results, 'jobId'));
        $this->assertSame(self::ACCOUNT, data_get($results, 'accountNumber.value'));
    }

    public function test_omits_string_null_physical_packaging(): void
    {
        $fixture = app(FedExConsolidationFixtureService::class)->fixture('IntegratorUS10_ADD_SHIPMENT_1');
        $payload = app(FedExConsolidationPayloadFactory::class)->buildAddShipment($fixture);

        $this->assertTrue((bool) data_get($fixture, 'packages.0.omit_physical_packaging'));
        $this->assertArrayNotHasKey('physicalPackaging', (array) data_get($payload, 'requestedShipment.requestedPackageLineItems.0', []));
        $encoded = json_encode($payload) ?: '';
        $this->assertStringNotContainsString('"physicalPackaging":"null"', $encoded);
    }
}
