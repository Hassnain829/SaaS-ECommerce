<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExFreightLtlPayloadFactory;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExFreightLtlEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExFreightLtlFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExShipEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExShipFixtureResolver;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExUs08FreightFixtureTest extends TestCase
{
    use RefreshDatabase;

    private const FREIGHT_ACCOUNT = '631234540';

    private const PARCEL_ACCOUNT = '700257037';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.validation_us08_enabled' => true,
            'carriers.fedex.freight_ltl_api_enabled' => true,
            'carriers.fedex.validation_us08_freight_account' => self::FREIGHT_ACCOUNT,
            'carriers.fedex.freight_ltl_ship_path' => '/ship/v1/freight/shipments',
        ]);
    }

    public function test_us08_freight_fixture_and_payload_match_workbook(): void
    {
        $account = $this->makeAccount();
        $fixture = app(FedExFreightLtlFixtureService::class)->fixture('IntegratorUS08');
        $payload = app(FedExFreightLtlPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'ZPLII');
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($payload);

        $this->assertSame('freight_ltl', $fixture['api_family']);
        $this->assertSame('ship_us08_zplii', FedExValidationScenarioCatalog::lockedShipScenarios()['IntegratorUS08']['scenario_key']);
        $this->assertSame('/ship/v1/freight/shipments', app(FedExConfig::class)->freightLtlShipPath());
        $this->assertTrue(app(FedExShipFixtureResolver::class)->isFreightLtlCase('IntegratorUS08'));

        $this->assertSame('LABEL', data_get($payload, 'labelResponseOptions'));
        $this->assertSame(self::FREIGHT_ACCOUNT, data_get($payload, 'accountNumber.value'));
        $this->assertNotSame(self::PARCEL_ACCOUNT, data_get($payload, 'accountNumber.value'));
        $this->assertArrayHasKey('freightRequestedShipment', $payload);
        $this->assertArrayNotHasKey('requestedShipment', $payload);

        $shipment = data_get($payload, 'freightRequestedShipment');
        $this->assertSame('FEDEX_FREIGHT_PRIORITY', data_get($shipment, 'serviceType'));
        $this->assertSame('YOUR_PACKAGING', data_get($shipment, 'packagingType'));
        $this->assertSame('USE_SCHEDULED_PICKUP', data_get($shipment, 'pickupType'));
        $this->assertSame(1000, data_get($shipment, 'totalWeight'));
        $this->assertSame(1, data_get($shipment, 'totalPackageCount'));
        $this->assertSame(['LIST', 'PREFERRED'], data_get($shipment, 'rateRequestType'));
        $this->assertSame('RECIPIENT', data_get($shipment, 'shippingChargesPayment.paymentType'));
        $this->assertSame(self::FREIGHT_ACCOUNT, data_get($shipment, 'shippingChargesPayment.payor.responsibleParty.accountNumber.value'));
        $this->assertSame('SHIPPER', data_get($shipment, 'freightShipmentDetail.role'));
        $this->assertSame('NON_RECOURSE_SHIPPER_SIGNED', data_get($shipment, 'freightShipmentDetail.collectTermsType'));
        $this->assertArrayNotHasKey('carrierCode', (array) data_get($shipment, 'freightShipmentDetail', []));
        $this->assertSame(1, data_get($shipment, 'freightShipmentDetail.totalHandlingUnits'));
        $this->assertSame(0.0, (float) data_get($shipment, 'freightShipmentDetail.clientDiscountPercent'));
        $this->assertSame('USD', data_get($shipment, 'freightShipmentDetail.declaredValuePerUnit.currency'));
        $this->assertSame(0.0, (float) data_get($shipment, 'freightShipmentDetail.declaredValuePerUnit.amount'));
        $this->assertSame('LB', data_get($shipment, 'freightShipmentDetail.declaredValueUnits'));
        $this->assertSame(self::FREIGHT_ACCOUNT, data_get($shipment, 'freightShipmentDetail.fedExFreightAccountNumber.value'));
        $this->assertSame('72601', data_get($shipment, 'freightShipmentDetail.fedExFreightBillingContactAndAddress.address.postalCode'));
        $this->assertSame('Harrison', data_get($shipment, 'freightShipmentDetail.fedExFreightBillingContactAndAddress.address.city'));
        $this->assertSame(['1202 Chalet Lane'], data_get($shipment, 'freightShipmentDetail.fedExFreightBillingContactAndAddress.address.streetLines'));
        $this->assertSame('10', data_get($shipment, 'freightShipmentDetail.lineItem.0.id'));
        $this->assertSame('CLASS_050', data_get($shipment, 'freightShipmentDetail.lineItem.0.freightClass'));
        $this->assertSame('Axles', data_get($shipment, 'freightShipmentDetail.lineItem.0.description'));
        $this->assertArrayNotHasKey('nmfcCode', (array) data_get($shipment, 'freightShipmentDetail.lineItem.0', []));
        $this->assertSame('54321', data_get($shipment, 'freightShipmentDetail.lineItem.0.purchaseOrderNumber'));
        $this->assertSame(1000.0, (float) data_get($shipment, 'freightShipmentDetail.lineItem.0.weight.value'));
        $this->assertSame(1, data_get($shipment, 'freightShipmentDetail.lineItem.0.handlingUnits'));
        $this->assertSame(10, data_get($shipment, 'freightShipmentDetail.lineItem.0.pieces'));
        $this->assertSame('BARREL', data_get($shipment, 'freightShipmentDetail.lineItem.0.subPackagingType'));
        $this->assertSame('ZPLII', data_get($shipment, 'labelSpecification.imageType'));
        $this->assertSame('STOCK_4X6', data_get($shipment, 'labelSpecification.labelStockType'));
        $this->assertSame('SHIPPING_LABEL_FIRST', data_get($shipment, 'labelSpecification.labelOrder'));
        $this->assertContains('FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING', (array) data_get($shipment, 'shippingDocumentSpecification.shippingDocumentTypes'));
        $this->assertTrue((bool) data_get($shipment, 'shippingDocumentSpecification.commercialInvoiceDetail.provideInstructions'));
        $this->assertTrue((bool) data_get($shipment, 'shippingDocumentSpecification.freightBillOfLadingDetail.format.provideInstructions'));
        $this->assertSame('RETURNED', data_get($shipment, 'shippingDocumentSpecification.freightBillOfLadingDetail.format.dispositions.0.dispositionType'));
        $this->assertSame('PAPER_LETTER', data_get($shipment, 'shippingDocumentSpecification.freightBillOfLadingDetail.format.stockType'));
        $this->assertContains('INSIDE_DELIVERY', (array) data_get($shipment, 'freightShipmentSpecialServices.specialServiceTypes'));
        $this->assertSame('QCONFIG', data_get($shipment, 'shipper.contact.personName'));
        $this->assertSame('F-413404', data_get($shipment, 'recipient.contact.personName'));
        $this->assertSame('IntegratorUS09', data_get($shipment, 'recipient.contact.companyName'));
        $this->assertSame('CALIFORNIA', data_get($shipment, 'shipper.address.city'));
        $this->assertFalse((bool) data_get($shipment, 'shipper.address.residential'));
        $this->assertSame('10', data_get($shipment, 'requestedPackageLineItems.0.associatedFreightLineItems.0.id'));
        $this->assertArrayNotHasKey('oneLabelAtATime', $payload);
        $this->assertArrayNotHasKey('hazardousMaterials', (array) data_get($shipment, 'freightShipmentDetail.lineItem.0', []));

        $this->assertSame('[REDACTED]', data_get($sanitized, 'accountNumber.value'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'freightRequestedShipment.freightShipmentDetail.fedExFreightAccountNumber.value'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'freightRequestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber.value'));
        $this->assertIsArray(data_get($sanitized, 'freightRequestedShipment'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'freightRequestedShipment'));
        $this->assertIsArray(data_get($sanitized, 'freightRequestedShipment.freightShipmentDetail'));
        $this->assertSame('CLASS_050', data_get($sanitized, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.freightClass'));
        $this->assertSame('Axles', data_get($sanitized, 'freightRequestedShipment.freightShipmentDetail.lineItem.0.description'));
        $this->assertStringNotContainsString(self::FREIGHT_ACCOUNT, json_encode($sanitized));

        $export = app(FedExShipEvidenceRules::class)->validateSanitizedExport(
            is_array($sanitized) ? $sanitized : [],
            'IntegratorUS08',
        );
        $this->assertTrue($export['valid'], implode(',', $export['reasons']));
    }

    public function test_us08_does_not_use_parcel_ship_payload_factory(): void
    {
        $account = $this->makeAccount();
        $fixture = app(FedExFreightLtlFixtureService::class)->fixture('IntegratorUS08');
        $freightPayload = app(FedExFreightLtlPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'ZPLII');

        $this->assertArrayHasKey('freightRequestedShipment', $freightPayload);
        $this->assertSame(FedExFreightLtlPayloadFactory::class, FedExFreightLtlPayloadFactory::class);
        $this->assertNotSame(FedExShipPayloadFactory::class, FedExFreightLtlPayloadFactory::class);
    }

    public function test_us08_evidence_rules_reject_missing_or_wrong_values(): void
    {
        $rules = app(FedExFreightLtlEvidenceRules::class);
        $account = $this->makeAccount();
        $fixture = app(FedExFreightLtlFixtureService::class)->fixture('IntegratorUS08');
        $base = app(FedExFreightLtlPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'ZPLII');
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($base);

        $ok = $rules->validateSanitizedExport(is_array($sanitized) ? $sanitized : []);
        $this->assertTrue($ok['valid'], implode(',', $ok['reasons']));

        $parcelEnvelope = $rules->validateSanitizedExport([
            'requestedShipment' => ['serviceType' => 'FEDEX_FREIGHT_PRIORITY'],
        ]);
        $this->assertContains('us08_parcel_ship_envelope_used', $parcelEnvelope['reasons']);

        $wrongClass = $rules->validateSanitizedExport(array_replace_recursive(is_array($sanitized) ? $sanitized : [], [
            'freightRequestedShipment' => [
                'freightShipmentDetail' => [
                    'lineItem' => [[
                        'id' => '10',
                        'freightClass' => 'CLASS_070',
                        'description' => 'Axles',
                        'purchaseOrderNumber' => '54321',
                        'weight' => ['units' => 'LB', 'value' => 1000],
                        'handlingUnits' => 1,
                        'pieces' => 10,
                        'subPackagingType' => 'BARREL',
                    ]],
                ],
            ],
        ]));
        $this->assertContains('us08_freight_class_mismatch', $wrongClass['reasons']);
    }

    public function test_preflight_requires_us08_locked_ship_event(): void
    {
        $account = $this->makeAccount();
        $assessment = app(FedExValidationPreflightService::class)->assess($account->store, $account);
        $check = collect($assessment['checks'])->firstWhere('key', 'ship_us08_zplii_event');

        $this->assertNotNull($check);
        $this->assertSame('not_tested', $check['status']);
        $this->assertTrue((bool) $check['required']);
    }

    public function test_preflight_waives_us08_when_disabled(): void
    {
        config(['carriers.fedex.freight_ltl_api_enabled' => false]);
        $account = $this->makeAccount();

        $assessment = app(FedExValidationPreflightService::class)->assess($account->store, $account);
        $this->assertNull(collect($assessment['checks'])->firstWhere('key', 'ship_us08_zplii_event'));
        $this->assertNull(collect($assessment['checks'])->firstWhere('key', 'ship_us08_zplii_bol'));

        $excluded = collect($assessment['checks'])->firstWhere('key', 'ship_us08_zplii_excluded');
        $this->assertNotNull($excluded);
        $this->assertFalse((bool) $excluded['required']);
        $this->assertSame('passed', $excluded['status']);
        $this->assertStringContainsString('not a supported capability of this application', (string) $excluded['explanation']);
        $this->assertFalse(FedExValidationScenarioCatalog::isShipScenarioEnabled('IntegratorUS08'));
        $this->assertArrayNotHasKey('IntegratorUS08', FedExValidationScenarioCatalog::requiredLockedShipScenarios());
        $this->assertArrayHasKey('IntegratorUS07', FedExValidationScenarioCatalog::requiredLockedShipScenarios());
    }

    public function test_us01_through_us07_payloads_remain_unchanged(): void
    {
        $account = $this->makeAccount();
        $factory = app(FedExShipPayloadFactory::class);
        $fixtures = app(FedExShipTestCaseFixtureService::class);

        foreach (['IntegratorUS01', 'IntegratorUS02', 'IntegratorUS03', 'IntegratorUS04', 'IntegratorUS05', 'IntegratorUS06', 'IntegratorUS07'] as $key) {
            if ($key === 'IntegratorUS08') {
                continue;
            }
            $fixture = $fixtures->fixture($key);
            if (($fixture['api_family'] ?? null) === 'freight_ltl') {
                $this->fail($key.' should not be freight');
            }
            $format = (string) ($fixture['label_format'] ?? 'PDF');
            $payload = $factory->buildShipmentPayload($account, $fixture, $format);
            $this->assertArrayHasKey('requestedShipment', $payload);
            $this->assertArrayNotHasKey('freightRequestedShipment', $payload);
        }

        $us07 = $factory->buildShipmentPayload($account, $fixtures->fixture('IntegratorUS07'), 'PDF');
        $this->assertSame('SMART_POST', data_get($us07, 'requestedShipment.serviceType'));
        $this->assertSame('5531', data_get($us07, 'requestedShipment.smartPostInfoDetail.hubId'));
    }

    private function makeAccount(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US08 Store',
            'slug' => 'us08-'.Str::random(6),
            'user_id' => $owner->id,
        ]);
        $fedEx = \App\Models\Carrier::query()->where('code', 'fedex')->firstOrFail();

        return CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'connection_type' => 'manual',
            'status' => 'enabled',
            'provider_account_number' => self::PARCEL_ACCOUNT,
            'display_name' => 'US08 Account',
            'created_by' => $owner->id,
        ]);
    }
}
