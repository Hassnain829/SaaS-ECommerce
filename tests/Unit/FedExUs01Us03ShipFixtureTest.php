<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Validation\FedExShipEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExUs01Us03ShipFixtureTest extends TestCase
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

    public function test_us01_fixture_and_payload_match_workbook_alcohol_case(): void
    {
        $account = $this->makeAccount();
        $fixture = app(FedExShipTestCaseFixtureService::class)->fixture('IntegratorUS01');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'PDF');
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($payload);

        $this->assertSame('ship_us01_pdf', FedExValidationScenarioCatalog::lockedShipScenarios()['IntegratorUS01']['scenario_key']);
        $this->assertSame('FEDEX_EXPRESS_SAVER', data_get($payload, 'requestedShipment.serviceType'));
        $this->assertSame('YOUR_PACKAGING', data_get($payload, 'requestedShipment.packagingType'));
        $this->assertSame('SENDER', data_get($payload, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertSame('PDF', data_get($payload, 'requestedShipment.labelSpecification.imageType'));
        $this->assertSame('PAPER_85X11_TOP_HALF_LABEL', data_get($payload, 'requestedShipment.labelSpecification.labelStockType'));
        $this->assertSame(36.0, (float) data_get($payload, 'requestedShipment.totalWeight'));
        $this->assertSame(['LIST'], data_get($payload, 'requestedShipment.rateRequestType'));
        $this->assertSame('323401', data_get($payload, 'requestedShipment.recipients.0.contact.personName'));
        $this->assertSame('Integrator', data_get($payload, 'requestedShipment.recipients.0.contact.companyName'));
        $this->assertSame('9012633035', data_get($payload, 'requestedShipment.recipients.0.contact.phoneNumber'));
        $this->assertSame('NEW ORLEANS', data_get($payload, 'requestedShipment.recipients.0.address.city'));
        $this->assertSame('LA', data_get($payload, 'requestedShipment.recipients.0.address.stateOrProvinceCode'));
        $this->assertSame(250.0, (float) data_get($payload, 'requestedShipment.requestedPackageLineItems.0.declaredValue.amount'));
        $this->assertContains('ALCOHOL', (array) data_get($payload, 'requestedShipment.requestedPackageLineItems.0.packageSpecialServices.specialServiceTypes'));
        $this->assertSame(
            'LICENSEE',
            data_get($payload, 'requestedShipment.requestedPackageLineItems.0.packageSpecialServices.alcoholDetail.alcoholRecipientType'),
        );

        $this->assertSame(
            'LICENSEE',
            data_get($sanitized, 'requestedShipment.requestedPackageLineItems.0.packageSpecialServices.alcoholDetail.alcoholRecipientType'),
        );
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.requestedPackageLineItems.0.packageSpecialServices'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'accountNumber.value'));

        $export = app(FedExShipEvidenceRules::class)->validateSanitizedExport(
            is_array($sanitized) ? $sanitized : [],
            'IntegratorUS01',
        );
        $this->assertTrue($export['valid'], implode(',', $export['reasons']));
    }

    public function test_us03_fixture_and_payload_match_workbook_international_case(): void
    {
        $account = $this->makeAccount();
        $fixture = app(FedExShipTestCaseFixtureService::class)->fixture('IntegratorUS03');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'PDF');
        $sanitized = app(FedExValidationEvidenceSanitizer::class)->sanitize($payload);

        $this->assertSame('ship_us03_pdf', FedExValidationScenarioCatalog::lockedShipScenarios()['IntegratorUS03']['scenario_key']);
        $this->assertSame('FEDEX_INTERNATIONAL_PRIORITY', data_get($payload, 'requestedShipment.serviceType'));
        $this->assertSame('YOUR_PACKAGING', data_get($payload, 'requestedShipment.packagingType'));
        $this->assertSame('SENDER', data_get($payload, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertSame('PDF', data_get($payload, 'requestedShipment.labelSpecification.imageType'));
        $this->assertSame('GB', data_get($payload, 'requestedShipment.recipients.0.address.countryCode'));
        $this->assertSame('LONDON', data_get($payload, 'requestedShipment.recipients.0.address.city'));
        $this->assertSame('W1T1JY', data_get($payload, 'requestedShipment.recipients.0.address.postalCode'));
        $this->assertSame('PERSONAL_STATE', data_get($payload, 'requestedShipment.shipper.tins.0.tinType'));
        $this->assertSame('123456789', data_get($payload, 'requestedShipment.shipper.tins.0.number'));
        $this->assertFalse((bool) data_get($payload, 'requestedShipment.customsClearanceDetail.isDocumentOnly'));
        $this->assertSame(55.0, (float) data_get($payload, 'requestedShipment.customsClearanceDetail.totalCustomsValue.amount'));
        $this->assertArrayNotHasKey('insuranceCharge', (array) data_get($payload, 'requestedShipment.customsClearanceDetail', []));
        $this->assertSame('SENDER', data_get($payload, 'requestedShipment.customsClearanceDetail.dutiesPayment.paymentType'));
        $this->assertIsArray(data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.comments'));
        $this->assertSame(['FEDEX BUSINESS'], data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.comments'));
        $this->assertSame(50.0, (float) data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.insuranceCharge.amount'));
        $this->assertSame('USD', data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.insuranceCharge.currency'));
        $this->assertSame('SAMPLE', data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.shipmentPurpose'));
        $this->assertSame('OTHER', data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.taxesOrMiscellaneousChargeType'));
        $this->assertSame(25.0, (float) data_get($payload, 'requestedShipment.customsClearanceDetail.commercialInvoice.taxesOrMiscellaneousCharge.amount'));
        $this->assertSame('Dictionaries ', data_get($payload, 'requestedShipment.customsClearanceDetail.commodities.0.description'));
        $this->assertSame('NO EEI 30.37(f)', data_get($payload, 'requestedShipment.customsClearanceDetail.exportDetail.exportComplianceStatement'));
        $this->assertSame('NOT_REQUIRED', data_get($payload, 'requestedShipment.customsClearanceDetail.exportDetail.b13AFilingOption'));
        $this->assertSame(55.0, (float) data_get($payload, 'requestedShipment.requestedPackageLineItems.0.declaredValue.amount'));
        $this->assertSame('Integrator', data_get($payload, 'requestedShipment.requestedPackageLineItems.0.customerReferences.0.value'));

        $this->assertSame('[REDACTED]', data_get($sanitized, 'requestedShipment.shipper.tins.0.number'));
        $this->assertSame('PERSONAL_STATE', data_get($sanitized, 'requestedShipment.shipper.tins.0.tinType'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'requestedShipment.customsClearanceDetail.dutiesPayment.payor.responsibleParty.accountNumber.value'));
        $this->assertNotSame('[REDACTED]', data_get($sanitized, 'requestedShipment.customsClearanceDetail'));
        $this->assertSame(['FEDEX BUSINESS'], data_get($sanitized, 'requestedShipment.customsClearanceDetail.commercialInvoice.comments'));
        $this->assertSame(50.0, (float) data_get($sanitized, 'requestedShipment.customsClearanceDetail.commercialInvoice.insuranceCharge.amount'));
        $this->assertArrayNotHasKey('insuranceCharge', (array) data_get($sanitized, 'requestedShipment.customsClearanceDetail', []));
        $this->assertSame('Dictionaries ', data_get($sanitized, 'requestedShipment.customsClearanceDetail.commodities.0.description'));
        $this->assertSame('[REDACTED]', data_get($sanitized, 'accountNumber.value'));

        $export = app(FedExShipEvidenceRules::class)->validateSanitizedExport(
            is_array($sanitized) ? $sanitized : [],
            'IntegratorUS03',
        );
        $this->assertTrue($export['valid'], implode(',', $export['reasons']));
    }

    public function test_us01_and_us03_sanitized_export_fail_when_mandatory_fields_missing(): void
    {
        $rules = app(FedExShipEvidenceRules::class);

        $missingAlcohol = $rules->validateSanitizedExport([
            'requestedShipment' => [
                'shippingChargesPayment' => ['paymentType' => 'SENDER'],
                'labelSpecification' => ['imageType' => 'PDF'],
                'requestedPackageLineItems' => [[
                    'packageSpecialServices' => ['specialServiceTypes' => []],
                ]],
            ],
        ], 'IntegratorUS01');
        $this->assertFalse($missingAlcohol['valid']);
        $this->assertContains('us01_alcohol_special_service_missing', $missingAlcohol['reasons']);

        $missingCustoms = $rules->validateSanitizedExport([
            'requestedShipment' => [
                'shippingChargesPayment' => ['paymentType' => 'SENDER'],
                'labelSpecification' => ['imageType' => 'PDF'],
            ],
        ], 'IntegratorUS03');
        $this->assertFalse($missingCustoms['valid']);
        $this->assertContains('us03_customs_clearance_missing', $missingCustoms['reasons']);

        $whollyRedactedCustoms = $rules->validateSanitizedExport([
            'requestedShipment' => [
                'shippingChargesPayment' => ['paymentType' => 'SENDER'],
                'labelSpecification' => ['imageType' => 'PDF'],
                'customsClearanceDetail' => '[REDACTED]',
                'shipper' => ['tins' => [['tinType' => 'PERSONAL_STATE', 'number' => '[REDACTED]']]],
            ],
        ], 'IntegratorUS03');
        $this->assertFalse($whollyRedactedCustoms['valid']);
        $this->assertContains('us03_customs_clearance_wholly_redacted', $whollyRedactedCustoms['reasons']);
    }

    private function makeAccount(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US01 US03 Store',
            'slug' => 'us01-us03-'.Str::random(6),
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
            'display_name' => 'US01 US03 Account',
            'created_by' => $owner->id,
        ]);
    }
}
