<?php

namespace Tests\Unit;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExConsolidationService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\FedEx\Validation\FedExConsolidationFixtureService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FedExConsolidationServiceTest extends TestCase
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
            'carriers.fedex.validation_us10_shipper_tin' => 'TIN-US10',
            'carriers.fedex.consolidation_create_path' => '/ship/v1/consolidations',
            'carriers.fedex.consolidation_shipment_path' => '/ship/v1/consolidations/shipments',
            'carriers.fedex.consolidation_confirm_path' => '/ship/v1/consolidations/confirmations',
            'carriers.fedex.consolidation_confirm_results_path' => '/ship/v1/consolidations/confirmationresults',
        ]);
    }

    public function test_live_execution_blocked_by_default(): void
    {
        $account = $this->makeAccount();
        Http::fake();

        try {
            app(FedExConsolidationService::class)->execute($account->store, $account, allowLive: false);
            $this->fail('expected gate');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('allowLive=false', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_uses_consolidation_paths_not_parcel_or_freight(): void
    {
        $config = app(FedExConfig::class);
        $prepared = app(FedExConsolidationService::class)->prepareLocalChain();

        $this->assertSame('/ship/v1/consolidations', $prepared['paths']['create']);
        $this->assertSame('/ship/v1/consolidations/shipments', $prepared['paths']['shipment']);
        $this->assertSame('/ship/v1/consolidations/confirmations', $prepared['paths']['confirm']);
        $this->assertSame('/ship/v1/consolidations/confirmationresults', $prepared['paths']['confirm_results']);
        $this->assertSame('/ship/v1/consolidations', $config->consolidationCreatePath());
        $this->assertStringNotContainsString('/ship/v1/shipments', $prepared['paths']['create']);
        $this->assertStringNotContainsString('freight', $prepared['paths']['create']);
        $this->assertStringNotContainsString('openship', strtolower(json_encode($prepared['paths'])));
        $this->assertCount(6, $prepared['add_shipments']);
        $this->assertSame(FedExConsolidationFixtureService::PLACEHOLDER_INDEX, data_get($prepared['add_shipments'][0], 'consolidationKey.index'));
        $this->assertNotSame(FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_INDEX, data_get($prepared['add_shipments'][0], 'consolidationKey.index'));
        $this->assertNotSame(FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_JOB_ID, data_get($prepared['confirm_results'], 'jobId'));
    }

    public function test_http_fake_successful_chained_flow_injects_dynamic_key_and_job_id(): void
    {
        $account = $this->makeAccount();
        $captured = [
            'create' => null,
            'shipments' => [],
            'confirm' => null,
            'results' => [],
        ];

        Http::fake(function (Request $request) use (&$captured) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-us10', 'expires_in' => 3600], 200);
            }

            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/ship/v1/consolidations')
                && ! str_contains($url, 'shipments')
                && ! str_contains($url, 'confirmation')) {
                $captured['create'] = $request->data();

                return Http::response([
                    'transactionId' => 'create-1',
                    'output' => [
                        'consolidationKey' => [
                            'type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION',
                            'index' => 'LIVE-INDEX-555',
                            'date' => '2026-07-11',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/shipments')) {
                $captured['shipments'][] = $request->data();

                return Http::response([
                    'transactionId' => 'ship-'.count($captured['shipments']),
                    'output' => ['alerts' => []],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmations')
                && ! str_contains($url, 'confirmationresults')) {
                $captured['confirm'] = $request->data();

                return Http::response([
                    'transactionId' => 'confirm-1',
                    'output' => ['jobId' => 'LIVE-JOB-777'],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmationresults')) {
                $captured['results'][] = $request->data();

                return Http::response([
                    'transactionId' => 'results-1',
                    'output' => [
                        'status' => 'COMPLETED',
                        'completedConsolidationDetail' => [
                            'masterTrackingNumber' => 'TRACK-MASTER',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL']]], 404);
        });

        $outcome = app(FedExConsolidationService::class)->execute($account->store, $account, allowLive: true);

        $this->assertTrue($outcome['success']);
        $this->assertSame('LIVE-INDEX-555', data_get($outcome, 'consolidation_key.index'));
        $this->assertSame('LIVE-JOB-777', $outcome['job_id']);
        $this->assertCount(6, $captured['shipments']);
        $this->assertCount(1, $captured['results']);

        foreach ($captured['shipments'] as $shipmentPayload) {
            $this->assertSame('LIVE-INDEX-555', data_get($shipmentPayload, 'consolidationKey.index'));
            $this->assertSame('2026-07-11', data_get($shipmentPayload, 'consolidationKey.date'));
            $this->assertNotSame(FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_INDEX, data_get($shipmentPayload, 'consolidationKey.index'));
        }

        $this->assertSame('LIVE-INDEX-555', data_get($captured['confirm'], 'consolidationKey.index'));
        $this->assertSame('LIVE-JOB-777', data_get($captured['results'][0], 'jobId'));
        $this->assertNotSame(FedExConsolidationFixtureService::HISTORICAL_WORKBOOK_JOB_ID, data_get($captured['results'][0], 'jobId'));
        $this->assertSame(self::ACCOUNT, data_get($captured['create'], 'accountNumber.value'));
    }

    public function test_failed_child_shipment_stops_before_confirm(): void
    {
        $account = $this->makeAccount();
        $confirmCalled = false;
        $shipmentCalls = 0;

        Http::fake(function (Request $request) use (&$confirmCalled, &$shipmentCalls) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-us10', 'expires_in' => 3600], 200);
            }

            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/ship/v1/consolidations')
                && ! str_contains($url, 'shipments')
                && ! str_contains($url, 'confirmation')) {
                return Http::response([
                    'output' => [
                        'consolidationKey' => [
                            'type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION',
                            'index' => 'LIVE-INDEX-1',
                            'date' => '2026-07-11',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/shipments')) {
                $shipmentCalls++;
                if ($shipmentCalls >= 2) {
                    return Http::response(['errors' => [['code' => 'SHIP.FAIL', 'message' => 'failed']]], 400);
                }

                return Http::response(['output' => ['alerts' => []]], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmations')) {
                $confirmCalled = true;

                return Http::response(['output' => ['jobId' => 'SHOULD-NOT-RUN']], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        $outcome = app(FedExConsolidationService::class)->execute($account->store, $account, allowLive: true);

        $this->assertFalse($outcome['success']);
        $this->assertSame('add_shipment_failed', $outcome['halted_reason']);
        $this->assertSame('IntegratorUS10_ADD_SHIPMENT_2', $outcome['failed_shipment']);
        $this->assertFalse($confirmCalled);
        $this->assertArrayNotHasKey('confirm', $outcome['steps']);
        $this->assertSame(2, $shipmentCalls);
    }

    public function test_confirm_results_polling_handles_in_process_then_completed(): void
    {
        $account = $this->makeAccount();
        $resultAttempts = 0;

        Http::fake(function (Request $request) use (&$resultAttempts) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response(['access_token' => 'child-token-us10', 'expires_in' => 3600], 200);
            }

            if (str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/ship/v1/consolidations')
                && ! str_contains($url, 'shipments')
                && ! str_contains($url, 'confirmation')) {
                return Http::response([
                    'output' => [
                        'consolidationKey' => [
                            'type' => 'INTERNATIONAL_PRIORITY_DISTRIBUTION',
                            'index' => 'LIVE-INDEX-2',
                            'date' => '2026-07-11',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/shipments')) {
                return Http::response(['output' => ['alerts' => []]], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmations')
                && ! str_contains($url, 'confirmationresults')) {
                return Http::response(['output' => ['jobId' => 'LIVE-JOB-POLL']], 200);
            }

            if (str_contains($url, '/ship/v1/consolidations/confirmationresults')) {
                $resultAttempts++;

                if ($resultAttempts === 1) {
                    return Http::response(['output' => ['status' => 'IN_PROCESS']], 200);
                }

                return Http::response([
                    'output' => [
                        'status' => 'COMPLETED',
                        'completedConsolidationDetail' => ['masterTrackingNumber' => 'M1'],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected']]], 404);
        });

        $outcome = app(FedExConsolidationService::class)->execute($account->store, $account, allowLive: true);

        $this->assertTrue($outcome['success']);
        $this->assertSame(2, $resultAttempts);
        $this->assertCount(2, data_get($outcome, 'steps.confirm_results.attempts'));
        $this->assertSame('IN_PROCESS', data_get($outcome, 'steps.confirm_results.attempts.0.status'));
        $this->assertSame('COMPLETED', data_get($outcome, 'steps.confirm_results.attempts.1.status'));
    }

    private function makeAccount(): CarrierAccount
    {
        $owner = User::factory()->create();
        $store = Store::query()->create([
            'name' => 'US10 Store',
            'slug' => 'us10-'.Str::random(6),
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
            'display_name' => 'US10 Account',
            'created_by' => $owner->id,
        ]);
        $account->setCredentials([
            'customer_key' => 'child-key-us10',
            'customer_password' => 'child-secret-us10',
        ]);
        $account->save();

        return $account->fresh();
    }
}
