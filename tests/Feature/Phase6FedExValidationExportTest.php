<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceExporter;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class Phase6FedExValidationExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.validation_mode_enabled' => true,
        ]);
    }

    public function test_diagnostic_export_includes_rate_403_and_marks_incomplete(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx Diagnostic Export Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'scenario_key' => 'rate_quote',
            'status' => CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 403,
            'error_code' => 'fedex_authorization_blocked',
            'endpoint' => '/rate/v1/rates/quotes',
            'http_method' => 'POST',
            'request_body_encrypted' => ['rateRequestControlParameters' => ['returnTransitTimes' => true]],
            'response_body_encrypted' => ['errors' => [['code' => 'FORBIDDEN.ERROR', 'message' => 'Not authorized']]],
            'request_summary' => ['endpoint' => '/rate/v1/rates/quotes'],
            'response_summary' => ['http_status' => 403, 'authorization_blocked' => true],
        ]);

        $zipPath = app(FedExValidationEvidenceExporter::class)->exportDiagnostic(
            store: $store,
            account: $account,
            region: 'US',
        );

        $zip = new ZipArchive;
        $zip->open($zipPath);
        $readme = (string) $zip->getFromName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/README.md');
        $rateResponse = (string) $zip->getFromName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/04_rates/response.json');
        $preflight = (string) $zip->getFromName('FedEx_Integrator_Validation_BaasPlatformFedExSandbox/preflight-report.json');
        $zip->close();

        $this->assertStringContainsString('INCOMPLETE', $readme);
        $this->assertStringContainsString('403', $rateResponse);
        $this->assertStringNotContainsString('event":null', $rateResponse);
        $this->assertStringNotContainsString('child-secret-a', $rateResponse);
        $this->assertSame('1.0', json_decode($preflight, true)['schema_version'] ?? null);
        $this->assertFalse(json_decode($preflight, true)['ready'] ?? true);
    }

    public function test_final_export_rejected_when_rate_is_403(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx Final Export Blocked Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_RATE_QUOTE,
            'scenario_key' => 'rate_quote',
            'status' => CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 403,
            'error_code' => 'fedex_authorization_blocked',
            'request_body_encrypted' => ['rateRequestControlParameters' => []],
            'response_body_encrypted' => ['errors' => [['code' => 'FORBIDDEN.ERROR']]],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(FedExValidationEvidenceExporter::class)->exportFinal(
            store: $store,
            account: $account,
            region: 'US',
        );
    }

    public function test_final_export_route_is_rejected_when_preflight_not_ready(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Final Route Blocked Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation.export.final', $account))
            ->assertStatus(422);

        $this->assertFalse(app(FedExValidationPreflightService::class)->assess($store, $account)['ready']);
    }

    public function test_diagnostic_export_route_downloads_zip(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Diagnostic Route Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation.export.diagnostic', $account))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount}
     */
    private function integratorAccountFixture(string $name): array
    {
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '1751 THOMPSON ST',
            'city' => 'AURORA',
            'state' => 'OH',
            'postal_code' => '44202',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx integrator account',
            'provider_account_number' => '700257037',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
            'settings' => ['default_origin_location_id' => $location->id],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $account->setCredentials([
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
        ]);
        $account->save();

        return [$owner, $store, $account];
    }
}
