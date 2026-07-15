<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExConsolidationPayloadFactory;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationEvidenceRules;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceSanitizer;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\Validation\FedExValidationScenarioCatalog;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExConsolidationEvidenceRulesTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNT = '510087100';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.validation_us10_enabled' => true,
            'carriers.fedex.validation_us10_consolidation_account' => self::ACCOUNT,
            'carriers.fedex.validation_us10_shipper_tin' => 'TIN-SECRET-99',
        ]);
    }

    public function test_accounts_and_tins_redacted_while_structures_remain_visible(): void
    {
        $fixtures = app(FedExConsolidationFixtureService::class);
        $factory = app(FedExConsolidationPayloadFactory::class);
        $rules = app(FedExConsolidationEvidenceRules::class);
        $sanitizer = app(FedExValidationEvidenceSanitizer::class);

        $create = $factory->buildCreateConsolidation($fixtures->fixture('IntegratorUS10_CREATE_CONSOLIDATION'));
        $sanitizedCreate = $sanitizer->sanitize($create);

        $this->assertSame('[REDACTED]', data_get($sanitizedCreate, 'accountNumber.value'));
        $this->assertSame('[REDACTED]', data_get($sanitizedCreate, 'requestedConsolidation.shipper.tins.0.number'));
        $this->assertSame('[REDACTED]', data_get($sanitizedCreate, 'requestedConsolidation.shippingChargesPayment.payor.responsibleParty.accountNumber.value'));
        $this->assertIsArray(data_get($sanitizedCreate, 'requestedConsolidation'));
        $this->assertNotSame('[REDACTED]', data_get($sanitizedCreate, 'requestedConsolidation'));
        $this->assertSame('THIRD_PARTY', data_get($sanitizedCreate, 'requestedConsolidation.shippingChargesPayment.paymentType'));
        $this->assertSame('INTERNATIONAL_PRIORITY_DISTRIBUTION', data_get($sanitizedCreate, 'requestedConsolidation.consolidationType'));
        $this->assertStringNotContainsString(self::ACCOUNT, json_encode($sanitizedCreate));
        $this->assertStringNotContainsString('TIN-SECRET-99', json_encode($sanitizedCreate));

        $createExport = $rules->validateSanitizedExport(is_array($sanitizedCreate) ? $sanitizedCreate : [], 'create');
        $this->assertTrue($createExport['valid'], implode(',', $createExport['reasons']));

        $add = $factory->buildAddShipment($fixtures->withConsolidationKey(
            $fixtures->fixture('IntegratorUS10_ADD_SHIPMENT_3'),
            [
                'type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION',
                'index' => 'SENSITIVE-INDEX',
                'date' => '2026-07-11',
            ],
        ));
        $sanitizedAdd = $sanitizer->sanitize($add);
        $this->assertSame('[REDACTED]', data_get($sanitizedAdd, 'consolidationKey.index'));
        $this->assertSame('INTERNATIONAL_PRIORITY_DISTRIBUTION', data_get($sanitizedAdd, 'consolidationKey.type'));
        $this->assertSame('2026-07-11', data_get($sanitizedAdd, 'consolidationKey.date'));
        $this->assertIsArray(data_get($sanitizedAdd, 'requestedShipment'));
        $this->assertNotSame('[REDACTED]', data_get($sanitizedAdd, 'requestedShipment'));
        $this->assertSame('Textbooks', data_get($sanitizedAdd, 'requestedShipment.customsClearanceDetail.commodities.0.description'));
        $this->assertSame(' Suite 101', data_get($sanitizedAdd, 'requestedShipment.shipper.address.streetLines.1'));

        $addExport = $rules->validateSanitizedExport(is_array($sanitizedAdd) ? $sanitizedAdd : [], 'add_shipment');
        $this->assertTrue($addExport['valid'], implode(',', $addExport['reasons']));

        $confirm = $factory->buildConfirmConsolidation($fixtures->withConsolidationKey(
            $fixtures->fixture('IntegratorUS10_CONFIRM_CONSOLIDATION'),
            ['type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION', 'index' => 'X', 'date' => '2026-07-11'],
        ));
        $sanitizedConfirm = $sanitizer->sanitize($confirm);
        $confirmExport = $rules->validateSanitizedExport(is_array($sanitizedConfirm) ? $sanitizedConfirm : [], 'confirm');
        $this->assertTrue($confirmExport['valid'], implode(',', $confirmExport['reasons']));

        $results = $factory->buildConfirmResults($fixtures->withJobId(
            $fixtures->fixture('IntegratorUS10_CONFIRM_RESULTS'),
            'JOB-SENSITIVE',
        ));
        $sanitizedResults = $sanitizer->sanitize($results);
        $this->assertSame('[REDACTED]', data_get($sanitizedResults, 'jobId'));
        $resultsExport = $rules->validateSanitizedExport(is_array($sanitizedResults) ? $sanitizedResults : [], 'confirm_results');
        $this->assertTrue($resultsExport['valid'], implode(',', $resultsExport['reasons']));
    }

    public function test_each_evidence_requirement_remains_separate_in_catalog_and_preflight(): void
    {
        $scenarios = FedExValidationScenarioCatalog::lockedConsolidationScenarios();
        $this->assertCount(9, $scenarios);
        $this->assertSame('consolidation_us10_create', $scenarios['IntegratorUS10_CREATE_CONSOLIDATION']['scenario_key']);
        $this->assertSame('consolidation_us10_add_shipment_1', $scenarios['IntegratorUS10_ADD_SHIPMENT_1']['scenario_key']);
        $this->assertSame('consolidation_us10_add_shipment_6', $scenarios['IntegratorUS10_ADD_SHIPMENT_6']['scenario_key']);
        $this->assertSame('consolidation_us10_confirm', $scenarios['IntegratorUS10_CONFIRM_CONSOLIDATION']['scenario_key']);
        $this->assertSame('consolidation_us10_confirm_results', $scenarios['IntegratorUS10_CONFIRM_RESULTS']['scenario_key']);

        $keys = array_column(array_values($scenarios), 'scenario_key');
        $this->assertCount(9, array_unique($keys));

        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US10 Evidence Store',
            'slug' => 'us10-ev-'.Str::random(6),
            'user_id' => $owner->id,
        ]);
        $fedEx = \App\Models\Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'connection_type' => 'manual',
            'status' => 'enabled',
            'provider_account_number' => '700257037',
            'display_name' => 'US10 Evidence',
            'created_by' => $owner->id,
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);
        foreach ($keys as $scenarioKey) {
            $check = collect($assessment['checks'])->firstWhere('key', $scenarioKey.'_event');
            $this->assertNotNull($check, $scenarioKey.' missing');
            $this->assertTrue((bool) $check['required']);
            $this->assertSame('not_tested', $check['status']);
        }

        $shipUs01 = collect($assessment['checks'])->firstWhere('key', 'ship_us01_pdf_event');
        $this->assertNotNull($shipUs01);
    }

    public function test_preflight_waives_us10_when_disabled(): void
    {
        config(['carriers.fedex.validation_us10_enabled' => false]);

        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US10 Disabled Store',
            'slug' => 'us10-off-'.Str::random(6),
            'user_id' => $owner->id,
        ]);
        $fedEx = \App\Models\Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_model' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'connection_type' => 'manual',
            'status' => 'enabled',
            'provider_account_number' => '700257037',
            'display_name' => 'US10 Disabled',
            'created_by' => $owner->id,
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);
        $this->assertNull(collect($assessment['checks'])->firstWhere('key', 'consolidation_us10_create_event'));
        $this->assertNull(collect($assessment['checks'])->firstWhere('key', 'consolidation_us10_child_labels'));

        $excluded = collect($assessment['checks'])->firstWhere('key', 'consolidation_us10_excluded');
        $this->assertNotNull($excluded);
        $this->assertFalse((bool) $excluded['required']);
        $this->assertSame('passed', $excluded['status']);
        $this->assertStringContainsString('not a supported capability of this application', (string) $excluded['explanation']);
        $this->assertFalse(FedExValidationScenarioCatalog::isConsolidationEnabled());
        $this->assertSame([], FedExValidationScenarioCatalog::requiredConsolidationScenarios());
    }

    public function test_rejects_wholly_redacted_consolidation_objects(): void
    {
        $rules = app(FedExConsolidationEvidenceRules::class);

        $bad = $rules->validateSanitizedExport([
            'accountNumber' => ['value' => '[REDACTED]'],
            'requestedConsolidation' => '[REDACTED]',
        ], 'create');
        $this->assertContains('us10_requested_consolidation_wholly_redacted', $bad['reasons']);

        $badShipment = $rules->validateSanitizedExport([
            'accountNumber' => ['value' => '[REDACTED]'],
            'consolidationKey' => ['type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION', 'index' => '[REDACTED]', 'date' => '2026-07-11'],
            'requestedShipment' => '[REDACTED]',
        ], 'add_shipment');
        $this->assertContains('us10_requested_shipment_wholly_redacted', $badShipment['reasons']);
    }
}
