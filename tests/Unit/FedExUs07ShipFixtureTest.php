<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Validation\FedExShipEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExUs07ShipFixtureTest extends TestCase
{
    use RefreshDatabase;

    private const US07_ACCOUNT = '741234573';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.validation_us07_ground_economy_account' => self::US07_ACCOUNT,
        ]);
    }

    public function test_us07_fixture_and_payload_match_workbook_ground_economy_case(): void
    {
        $account = $this->makeAccount();
        $fixture = app(FedExShipTestCaseFixtureService::class)->fixture('IntegratorUS07');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'PDF');
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($payload);

        $this->assertSame('ship_us07_pdf', FedExValidationScenarioCatalog::lockedShipScenarios()['IntegratorUS07']['scenario_key']);
        $this->assertSame('LABEL', data_get($payload, 'labelResponseOptions'));
        $this->assertSame('SMART_POST', data_get($payload, 'requestedShipment.serviceType'));
        $this->assertSame('YOUR_PACKAGING', data_get($payload, 'requestedShipment.packagingType'));
        $this->assertSame('USE_SCHEDULED_PICKUP', data_get($payload, 'requestedShipment.pickupType'));
        $this->assertSame('SENDER', data_get($payload, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertSame(self::US07_ACCOUNT, data_get($payload, 'accountNumber.value'));
        $this->assertSame(
            self::US07_ACCOUNT,
            data_get($payload, 'requestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber.value'),
        );
        $this->assertSame(
            'US',
            data_get($payload, 'requestedShipment.shippingChargesPayment.payor.responsibleParty.address.countryCode'),
        );
        $this->assertSame('PDF', data_get($payload, 'requestedShipment.labelSpecification.imageType'));
        $this->assertSame('COMMON2D', data_get($payload, 'requestedShipment.labelSpecification.labelFormatType'));
        $this->assertSame('PAPER_85X11_TOP_HALF_LABEL', data_get($payload, 'requestedShipment.labelSpecification.labelStockType'));
        $this->assertSame(1, (int) data_get($payload, 'requestedShipment.totalPackageCount'));
        $this->assertSame(1, (int) data_get($payload, 'requestedShipment.requestedPackageLineItems.0.sequenceNumber'));
        $this->assertSame(2.3, (float) data_get($payload, 'requestedShipment.requestedPackageLineItems.0.weight.value'));
        $this->assertSame('LB', data_get($payload, 'requestedShipment.requestedPackageLineItems.0.weight.units'));
        $this->assertNull(data_get($payload, 'requestedShipment.requestedPackageLineItems.0.dimensions'));
        $this->assertNull(data_get($payload, 'requestedShipment.requestedPackageLineItems.0.customerReferences'));
        $this->assertArrayNotHasKey('residential', (array) data_get($payload, 'requestedShipment.recipients.0.address', []));
        $this->assertSame('IntegratorUS08', data_get($payload, 'requestedShipment.recipients.0.contact.companyName'));
        $this->assertSame('Brulington', data_get($payload, 'requestedShipment.recipients.0.address.city'));
        $this->assertSame('ANTHONY JAMES', data_get($payload, 'requestedShipment.shipper.contact.personName'));
        $this->assertSame('RTC', data_get($payload, 'requestedShipment.shipper.contact.companyName'));
        $this->assertSame('Collierville', data_get($payload, 'requestedShipment.shipper.address.city'));

        $smartPost = (array) data_get($payload, 'requestedShipment.smartPostInfoDetail', []);
        $this->assertSame(['indicia', 'hubId'], array_keys($smartPost));
        $this->assertSame('PARCEL_SELECT', $smartPost['indicia']);
        $this->assertSame('5531', $smartPost['hubId']);
        $this->assertIsString($smartPost['hubId']);
        $this->assertArrayNotHasKey('ancillaryEndorsement', $smartPost);

        $this->assertSame('[REDACTED]', data_get($sanitized, 'accountNumber.value'));
        $this->assertSame(
            '[REDACTED]',
            data_get($sanitized, 'requestedShipment.shippingChargesPayment.payor.responsibleParty.accountNumber.value'),
        );
        $this->assertIsArray(data_get($sanitized, 'requestedShipment.shippingChargesPayment'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.shippingChargesPayment'));
        $this->assertIsArray(data_get($sanitized, 'requestedShipment.smartPostInfoDetail'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.smartPostInfoDetail'));
        $this->assertSame('PARCEL_SELECT', data_get($sanitized, 'requestedShipment.smartPostInfoDetail.indicia'));
        $this->assertSame('5531', data_get($sanitized, 'requestedShipment.smartPostInfoDetail.hubId'));
        $this->assertNotSame(self::US07_ACCOUNT, json_encode($sanitized));

        $export = app(FedExShipEvidenceRules::class)->validateSanitizedExport(
            is_array($sanitized) ? $sanitized : [],
            'IntegratorUS07',
        );
        $this->assertTrue($export['valid'], implode(',', $export['reasons']));
    }

    public function test_us07_evidence_rules_reject_missing_or_wrong_values(): void
    {
        $rules = app(FedExShipEvidenceRules::class);
        $base = [
            'accountNumber' => ['value' => '[REDACTED]'],
            'requestedShipment' => [
                'serviceType' => 'SMART_POST',
                'packagingType' => 'YOUR_PACKAGING',
                'pickupType' => 'USE_SCHEDULED_PICKUP',
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                    'payor' => [
                        'responsibleParty' => [
                            'accountNumber' => ['value' => '[REDACTED]'],
                            'address' => ['countryCode' => 'US'],
                        ],
                    ],
                ],
                'labelSpecification' => [
                    'imageType' => 'PDF',
                    'labelStockType' => 'PAPER_85X11_TOP_HALF_LABEL',
                ],
                'totalPackageCount' => 1,
                'requestedPackageLineItems' => [[
                    'sequenceNumber' => 1,
                    'weight' => ['units' => 'LB', 'value' => 2.3],
                ]],
                'smartPostInfoDetail' => [
                    'indicia' => 'PARCEL_SELECT',
                    'hubId' => '5531',
                ],
                'recipients' => [[
                    'address' => [
                        'city' => 'Brulington',
                        'countryCode' => 'US',
                    ],
                ]],
            ],
        ];

        $ok = $rules->validateSanitizedExport($base, 'IntegratorUS07');
        $this->assertTrue($ok['valid'], implode(',', $ok['reasons']));

        $wrongService = $rules->validateSanitizedExport(array_replace_recursive($base, [
            'requestedShipment' => ['serviceType' => 'FEDEX_GROUND'],
        ]), 'IntegratorUS07');
        $this->assertContains('us07_service_type_mismatch', $wrongService['reasons']);

        $missingPayor = $rules->validateSanitizedExport(array_replace_recursive($base, [
            'requestedShipment' => [
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                    'payor' => null,
                ],
            ],
        ]), 'IntegratorUS07');
        $this->assertContains('us07_sender_payor_missing_or_not_redacted', $missingPayor['reasons']);

        $wrongHub = $rules->validateSanitizedExport(array_replace_recursive($base, [
            'requestedShipment' => [
                'smartPostInfoDetail' => [
                    'indicia' => 'PARCEL_SELECT',
                    'hubId' => '9999',
                ],
            ],
        ]), 'IntegratorUS07');
        $this->assertContains('us07_hub_id_mismatch', $wrongHub['reasons']);

        $withAncillary = $rules->validateSanitizedExport(array_replace_recursive($base, [
            'requestedShipment' => [
                'smartPostInfoDetail' => [
                    'indicia' => 'PARCEL_SELECT',
                    'hubId' => '5531',
                    'ancillaryEndorsement' => 'ADDRESS_CORRECTION',
                ],
            ],
        ]), 'IntegratorUS07');
        $this->assertContains('us07_unexpected_ancillary_endorsement', $withAncillary['reasons']);

        $withResidential = $rules->validateSanitizedExport(array_replace_recursive($base, [
            'requestedShipment' => [
                'recipients' => [[
                    'address' => [
                        'city' => 'Brulington',
                        'countryCode' => 'US',
                        'residential' => false,
                    ],
                ]],
            ],
        ]), 'IntegratorUS07');
        $this->assertContains('us07_unexpected_residential_flag', $withResidential['reasons']);

        $withDimensions = $rules->validateSanitizedExport(array_replace_recursive($base, [
            'requestedShipment' => [
                'requestedPackageLineItems' => [[
                    'sequenceNumber' => 1,
                    'weight' => ['units' => 'LB', 'value' => 2.3],
                    'dimensions' => ['length' => 1, 'width' => 1, 'height' => 1, 'units' => 'IN'],
                ]],
            ],
        ]), 'IntegratorUS07');
        $this->assertContains('us07_unexpected_dimensions', $withDimensions['reasons']);

        $whollyRedactedPayment = $rules->validateSanitizedExport(array_replace_recursive($base, [
            'requestedShipment' => [
                'shippingChargesPayment' => '[REDACTED]',
            ],
        ]), 'IntegratorUS07');
        $this->assertContains('us07_shipping_charges_payment_wholly_redacted', $whollyRedactedPayment['reasons']);
    }

    public function test_preflight_requires_us07_locked_ship_event(): void
    {
        $account = $this->makeAccount();
        $assessment = app(FedExValidationPreflightService::class)->assess($account->store, $account);
        $check = collect($assessment['checks'])->firstWhere('key', 'ship_us07_pdf_event');

        $this->assertNotNull($check);
        $this->assertSame('not_tested', $check['status']);
        $this->assertTrue((bool) $check['required']);
    }

    public function test_us01_through_us06_payloads_remain_unchanged_by_us07_support(): void
    {
        $account = $this->makeAccount();
        $factory = app(FedExShipPayloadFactory::class);
        $fixtures = app(FedExShipTestCaseFixtureService::class);

        $us01 = $factory->buildShipmentPayload($account, $fixtures->fixture('IntegratorUS01'), 'PDF');
        $this->assertSame('FEDEX_EXPRESS_SAVER', data_get($us01, 'requestedShipment.serviceType'));
        $this->assertNull(data_get($us01, 'requestedShipment.smartPostInfoDetail'));
        $this->assertNull(data_get($us01, 'requestedShipment.shippingChargesPayment.payor'));
        $this->assertArrayHasKey('residential', (array) data_get($us01, 'requestedShipment.recipients.0.address', []));

        $us02 = $factory->buildShipmentPayload($account, $fixtures->fixture('IntegratorUS02'), 'ZPLII');
        $this->assertSame('PRIORITY_OVERNIGHT', data_get($us02, 'requestedShipment.serviceType'));
        $this->assertNull(data_get($us02, 'requestedShipment.smartPostInfoDetail'));
        $this->assertNull(data_get($us02, 'requestedShipment.shippingChargesPayment.payor'));

        $us03 = $factory->buildShipmentPayload($account, $fixtures->fixture('IntegratorUS03'), 'PDF');
        $this->assertSame('FEDEX_INTERNATIONAL_PRIORITY', data_get($us03, 'requestedShipment.serviceType'));
        $this->assertNull(data_get($us03, 'requestedShipment.smartPostInfoDetail'));

        $us04 = $factory->buildShipmentPayload($account, $fixtures->fixture('IntegratorUS04'), 'PNG');
        $this->assertSame('GROUND_HOME_DELIVERY', data_get($us04, 'requestedShipment.serviceType'));
        $this->assertTrue((bool) data_get($us04, 'requestedShipment.recipients.0.address.residential'));
        $this->assertNull(data_get($us04, 'requestedShipment.smartPostInfoDetail'));
        $this->assertNull(data_get($us04, 'requestedShipment.shippingChargesPayment.payor'));

        $us05 = $factory->buildShipmentPayload($account, $fixtures->fixture('IntegratorUS05'), 'PDF');
        $this->assertSame('RECIPIENT', data_get($us05, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertNotNull(data_get($us05, 'requestedShipment.shippingChargesPayment.payor'));
        $this->assertNull(data_get($us05, 'requestedShipment.smartPostInfoDetail'));

        $us06 = $factory->buildShipmentPayload($account, $fixtures->fixture('IntegratorUS06'), 'PDF');
        $this->assertSame('FEDEX_GROUND', data_get($us06, 'requestedShipment.serviceType'));
        $this->assertContains('RETURN_SHIPMENT', (array) data_get($us06, 'requestedShipment.shipmentSpecialServices.specialServiceTypes'));
        $this->assertNull(data_get($us06, 'requestedShipment.smartPostInfoDetail'));
        $this->assertNull(data_get($us06, 'requestedShipment.shippingChargesPayment.payor'));
        $this->assertSame(0.0, (float) data_get($us06, 'requestedShipment.customsClearanceDetail.commodities.0.unitPrice.amount'));
        $this->assertSame(1.0, (float) data_get($us06, 'requestedShipment.customsClearanceDetail.totalCustomsValue.amount'));
    }

    private function makeAccount(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US07 Store',
            'slug' => 'us07-'.Str::random(6),
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
            'provider_account_number' => '700257037',
            'display_name' => 'US07 Account',
            'created_by' => $owner->id,
        ]);
    }
}
