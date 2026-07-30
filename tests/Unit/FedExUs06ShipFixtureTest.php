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

class FedExUs06ShipFixtureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
        ]);
    }

    public function test_us06_fixture_and_payload_match_workbook_return_manager_case(): void
    {
        $account = $this->makeAccount();
        $fixture = app(FedExShipTestCaseFixtureService::class)->fixture('IntegratorUS06');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'PDF');
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($payload);

        $this->assertSame('ship_us06_pdf', FedExValidationScenarioCatalog::lockedShipScenarios()['IntegratorUS06']['scenario_key']);
        $this->assertSame('FEDEX_GROUND', data_get($payload, 'requestedShipment.serviceType'));
        $this->assertSame('YOUR_PACKAGING', data_get($payload, 'requestedShipment.packagingType'));
        $this->assertSame('USE_SCHEDULED_PICKUP', data_get($payload, 'requestedShipment.pickupType'));
        $this->assertSame('SENDER', data_get($payload, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertFalse((bool) data_get($payload, 'requestedShipment.blockInsightVisibility'));
        $this->assertSame('PDF', data_get($payload, 'requestedShipment.labelSpecification.imageType'));
        $this->assertSame('PAPER_85X11_TOP_HALF_LABEL', data_get($payload, 'requestedShipment.labelSpecification.labelStockType'));
        $this->assertSame('Integrator', data_get($payload, 'requestedShipment.shipper.contact.companyName'));
        $this->assertSame('141', data_get($payload, 'requestedShipment.recipients.0.contact.personName'));
        $this->assertSame('Mississauga', data_get($payload, 'requestedShipment.recipients.0.address.city'));
        $this->assertSame('ON', data_get($payload, 'requestedShipment.recipients.0.address.stateOrProvinceCode'));
        $this->assertSame('CA', data_get($payload, 'requestedShipment.recipients.0.address.countryCode'));
        $this->assertTrue((bool) data_get($payload, 'requestedShipment.recipients.0.address.residential'));
        $this->assertContains('RETURN_SHIPMENT', (array) data_get($payload, 'requestedShipment.shipmentSpecialServices.specialServiceTypes'));
        $this->assertSame('PRINT_RETURN_LABEL', data_get($payload, 'requestedShipment.shipmentSpecialServices.returnShipmentDetail.returnType'));
        $this->assertSame(10.0, (float) data_get($payload, 'requestedShipment.requestedPackageLineItems.0.weight.value'));
        $this->assertNull(data_get($payload, 'requestedShipment.requestedPackageLineItems.0.dimensions'));
        $this->assertSame('GSNE.  IOR equals Duties/Taxes payer', data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.specialInstructions'));
        $this->assertSame('SAMPLE', data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.shipmentPurpose'));
        $this->assertSame('EXHIBITION_TRADE_SHOW', data_get($payload, 'requestedShipment.customsClearanceDetail.customsOption.type'));
        $this->assertSame('Dictionaries ', data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.description'));
        $this->assertSame('PC', data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.quantityUnits'));
        $this->assertSame(0.0, (float) data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.unitPrice.amount'));
        $this->assertSame('USD', data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.unitPrice.currency'));
        $this->assertSame(1.0, (float) data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.customsValue.amount'));
        $this->assertSame('USD', data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.customsValue.currency'));
        $this->assertSame(1.0, (float) data_get($payload, 'requestedShipment.customsClearanceDetail.totalCustomsValue.amount'));
        $this->assertSame('USD', data_get($payload, 'requestedShipment.customsClearanceDetail.totalCustomsValue.currency'));
        $this->assertSame(
            (float) data_get($payload, 'requestedShipment.customsClearanceDetail.totalCustomsValue.amount'),
            (float) data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.customsValue.amount'),
        );

        $this->assertSame('PRINT_RETURN_LABEL', data_get($sanitized, 'requestedShipment.shipmentSpecialServices.returnShipmentDetail.returnType'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.shipmentSpecialServices.returnShipmentDetail'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.shippingChargesPayment'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'accountNumber.value'));
        $this->assertSame('EXHIBITION_TRADE_SHOW', data_get($sanitized, 'requestedShipment.customsClearanceDetail.customsOption.type'));
        $this->assertSame(0.0, (float) data_get($sanitized, 'requestedShipment.customsClearanceDetail.commodities.0.unitPrice.amount'));
        $this->assertSame(1.0, (float) data_get($sanitized, 'requestedShipment.customsClearanceDetail.commodities.0.customsValue.amount'));
        $this->assertSame(1.0, (float) data_get($sanitized, 'requestedShipment.customsClearanceDetail.totalCustomsValue.amount'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.customsClearanceDetail.totalCustomsValue'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.customsClearanceDetail.commodities.0.customsValue'));

        $export = app(FedExShipEvidenceRules::class)->validateSanitizedExport(
            is_array($sanitized) ? $sanitized : [],
            'IntegratorUS06',
        );
        $this->assertTrue($export['valid'], implode(',', $export['reasons']));
    }

    public function test_us06_sanitized_export_rejects_missing_or_mismatched_customs_values(): void
    {
        $rules = app(FedExShipEvidenceRules::class);
        $base = [
            'shippingChargesPayment' => ['paymentType' => 'SENDER'],
            'labelSpecification' => ['imageType' => 'PDF'],
            'shipmentSpecialServices' => [
                'specialServiceTypes' => ['RETURN_SHIPMENT'],
                'returnShipmentDetail' => ['returnType' => 'PRINT_RETURN_LABEL'],
            ],
            'customsClearanceDetail' => [
                'customsOption' => ['type' => 'EXHIBITION_TRADE_SHOW'],
                'totalCustomsValue' => ['amount' => 1, 'currency' => 'USD'],
                'commodities' => [[
                    'description' => 'Dictionaries ',
                    'unitPrice' => ['amount' => 0, 'currency' => 'USD'],
                    'customsValue' => ['amount' => 1, 'currency' => 'USD'],
                ]],
            ],
        ];

        $missingTotal = $rules->validateSanitizedExport([
            'requestedShipment' => array_replace_recursive($base, [
                'customsClearanceDetail' => [
                    'totalCustomsValue' => null,
                ],
            ]),
        ], 'IntegratorUS06');
        $this->assertFalse($missingTotal['valid']);
        $this->assertContains('us06_total_customs_value_mismatch', $missingTotal['reasons']);

        $zeroCommodity = $rules->validateSanitizedExport([
            'requestedShipment' => array_replace_recursive($base, [
                'customsClearanceDetail' => [
                    'commodities' => [[
                        'description' => 'Dictionaries ',
                        'unitPrice' => ['amount' => 0, 'currency' => 'USD'],
                        'customsValue' => ['amount' => 0, 'currency' => 'USD'],
                    ]],
                ],
            ]),
        ], 'IntegratorUS06');
        $this->assertFalse($zeroCommodity['valid']);
        $this->assertContains('us06_commodity_customs_value_mismatch', $zeroCommodity['reasons']);
        $this->assertContains('us06_customs_value_total_mismatch', $zeroCommodity['reasons']);

        $mismatchedTotals = $rules->validateSanitizedExport([
            'requestedShipment' => array_replace_recursive($base, [
                'customsClearanceDetail' => [
                    'totalCustomsValue' => ['amount' => 5, 'currency' => 'USD'],
                ],
            ]),
        ], 'IntegratorUS06');
        $this->assertFalse($mismatchedTotals['valid']);
        $this->assertContains('us06_total_customs_value_mismatch', $mismatchedTotals['reasons']);
        $this->assertContains('us06_customs_value_total_mismatch', $mismatchedTotals['reasons']);

        $wrongUnitPrice = $rules->validateSanitizedExport([
            'requestedShipment' => array_replace_recursive($base, [
                'customsClearanceDetail' => [
                    'commodities' => [[
                        'description' => 'Dictionaries ',
                        'unitPrice' => ['amount' => 1, 'currency' => 'USD'],
                        'customsValue' => ['amount' => 1, 'currency' => 'USD'],
                    ]],
                ],
            ]),
        ], 'IntegratorUS06');
        $this->assertFalse($wrongUnitPrice['valid']);
        $this->assertContains('us06_workbook_unit_price_mismatch', $wrongUnitPrice['reasons']);
        $this->assertContains('RETURN_SHIPMENT', (array) data_get($base, 'shipmentSpecialServices.specialServiceTypes'));
        $this->assertSame('PRINT_RETURN_LABEL', data_get($base, 'shipmentSpecialServices.returnShipmentDetail.returnType'));
    }

    public function test_us06_sanitized_export_rejects_missing_return_fields(): void
    {
        $rules = app(FedExShipEvidenceRules::class);

        $missingReturn = $rules->validateSanitizedExport([
            'requestedShipment' => [
                'shippingChargesPayment' => ['paymentType' => 'SENDER'],
                'labelSpecification' => ['imageType' => 'PDF'],
                'shipmentSpecialServices' => [
                    'specialServiceTypes' => [],
                ],
            ],
        ], 'IntegratorUS06');
        $this->assertFalse($missingReturn['valid']);
        $this->assertContains('us06_return_shipment_special_service_missing', $missingReturn['reasons']);

        $whollyRedactedReturn = $rules->validateSanitizedExport([
            'requestedShipment' => [
                'shippingChargesPayment' => ['paymentType' => 'SENDER'],
                'labelSpecification' => ['imageType' => 'PDF'],
                'shipmentSpecialServices' => [
                    'specialServiceTypes' => ['RETURN_SHIPMENT'],
                    'returnShipmentDetail' => '[REDACTED]',
                ],
                'customsClearanceDetail' => [
                    'customsOption' => ['type' => 'EXHIBITION_TRADE_SHOW'],
                    'totalCustomsValue' => ['amount' => 1, 'currency' => 'USD'],
                    'commodities' => [[
                        'description' => 'Dictionaries ',
                        'unitPrice' => ['amount' => 0, 'currency' => 'USD'],
                        'customsValue' => ['amount' => 1, 'currency' => 'USD'],
                    ]],
                ],
            ],
        ], 'IntegratorUS06');
        $this->assertFalse($whollyRedactedReturn['valid']);
        $this->assertContains('us06_return_shipment_detail_wholly_redacted', $whollyRedactedReturn['reasons']);
    }

    public function test_preflight_requires_us06_locked_ship_event(): void
    {
        $account = $this->makeAccount();
        $assessment = app(FedExValidationPreflightService::class)->assess($account->store, $account);
        $check = collect($assessment['checks'])->firstWhere('key', 'ship_us06_pdf_event');

        $this->assertNotNull($check);
        $this->assertSame('not_tested', $check['status']);
        $this->assertTrue((bool) $check['required']);
    }

    private function makeAccount(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US06 Store',
            'slug' => 'us06-'.Str::random(6),
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
            'display_name' => 'US06 Account',
            'created_by' => $owner->id,
        ]);
    }
}
