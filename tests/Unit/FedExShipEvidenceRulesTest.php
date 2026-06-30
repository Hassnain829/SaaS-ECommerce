<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExShipPayloadFactory;
use App\Services\Carriers\FedEx\Validation\FedExShipEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExShipTestCaseFixtureService;
use Carbon\Carbon;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FedExShipTestEvidenceFactory;
use Tests\TestCase;

class FedExShipEvidenceRulesTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.ship_sandbox_label_generation_enabled' => true,
            'carriers.fedex.ship_create_path' => '/ship/v1/shipments',
        ]);
    }

    public function test_us02_fixture_payload_matches_workbook_baseline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-26'));
        $account = $this->makeAccount();
        $fixture = app(FedExShipTestCaseFixtureService::class)->fixture('IntegratorUS02');
        $payload = app(FedExShipPayloadFactory::class)->buildShipmentPayload($account, $fixture, 'ZPLII');

        $this->assertSame('PRIORITY_OVERNIGHT', data_get($payload, 'requestedShipment.serviceType'));
        $this->assertSame('ZPLII', data_get($payload, 'requestedShipment.labelSpecification.imageType'));
        $this->assertSame('STOCK_4X6', data_get($payload, 'requestedShipment.labelSpecification.labelStockType'));
        $this->assertSame('SENDER', data_get($payload, 'requestedShipment.shippingChargesPayment.paymentType'));
        $this->assertSame(20.0, (float) data_get($payload, 'requestedShipment.requestedPackageLineItems.0.weight.value'));
        $this->assertSame(10.0, (float) data_get($payload, 'requestedShipment.requestedPackageLineItems.0.dimensions.length'));
        $this->assertContains('EVENT_NOTIFICATION', data_get($payload, 'requestedShipment.shipmentSpecialServices.specialServiceTypes'));
        $this->assertContains('SATURDAY_DELIVERY', data_get($payload, 'requestedShipment.shipmentSpecialServices.specialServiceTypes'));
        $this->assertSame('test001@fedex.com', data_get($payload, 'requestedShipment.emailNotificationDetail.emailNotificationRecipients.0.emailAddress'));
        $this->assertSame('2026-06-26', data_get($payload, 'requestedShipment.shipDatestamp'));
        Carbon::setTestNow();
    }

    public function test_service_mismatch_event_is_not_canonical(): void
    {
        $account = $this->makeAccount();
        $bodies = FedExShipTestEvidenceFactory::eventBodies($account, 'IntegratorUS02');
        $bodies['response']['output']['transactionShipments'][0]['serviceType'] = 'GROUND_HOME_DELIVERY';

        $event = CarrierApiEvent::query()->create([
            'store_id' => $account->store_id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
            'scenario_key' => 'ship_us02_zplii',
            'test_case_key' => 'IntegratorUS02',
            'label_format' => 'ZPLII',
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'http_method' => 'POST',
            'endpoint' => '/ship/v1/shipments',
            'request_body_encrypted' => $bodies['request'],
            'response_body_encrypted' => $bodies['response'],
        ]);

        $rules = app(FedExShipEvidenceRules::class);
        $this->assertFalse($rules->isValidEventForTestCase($event, 'IntegratorUS02'));
        $this->assertContains('response_service_mismatch', $rules->validateResponse($event, 'IntegratorUS02')['reasons']);
    }

    private function makeAccount(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'Ship Rules Store',
            'slug' => 'ship-rules-'.Str::random(6),
            'user_id' => $owner->id,
        ]);
        $fedEx = \App\Models\Carrier::query()->where('code', 'fedex')->firstOrFail();

        return CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx Sandbox',
            'provider_account_number' => '700257037',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));
    }
}
