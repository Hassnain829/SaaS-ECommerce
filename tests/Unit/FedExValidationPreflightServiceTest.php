<?php

namespace Tests\Unit;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExValidationArtifact;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\FedExValidationPreflightService;
use App\Services\Carriers\FedEx\FedExValidationScopeService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedExValidationPreflightServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        config([
            'carriers.fedex.validation_mode_enabled' => true,
            'carriers.fedex.validation_required_scopes' => [
                FedExValidationScopeService::SCOPE_COMPREHENSIVE_RATES,
                FedExValidationScopeService::SCOPE_SHIP,
            ],
        ]);
    }

    public function test_missing_address_validation_blocks_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Missing Address Preflight Store');

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [
                FedExValidationScopeService::SCOPE_ADDRESS_VALIDATION,
                FedExValidationScopeService::SCOPE_SERVICE_AVAILABILITY,
            ],
        );

        $this->assertFalse($assessment['ready']);
        $this->assertTrue(collect($assessment['blockers'])->contains(fn (array $blocker): bool => ($blocker['key'] ?? '') === 'address_validation'));
        $this->assertTrue(collect($assessment['blockers'])->contains(fn (array $blocker): bool => ($blocker['key'] ?? '') === 'service_availability'));
    }

    public function test_passed_address_and_service_availability_checks_pass(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Passed Address Service Store');

        foreach (['address_validation', 'service_availability'] as $scenario) {
            CarrierApiEvent::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'action' => $scenario === 'address_validation'
                    ? CarrierApiEvent::ACTION_FEDEX_ADDRESS_VALIDATION
                    : CarrierApiEvent::ACTION_FEDEX_SERVICE_AVAILABILITY,
                'scenario_key' => $scenario,
                'status' => CarrierApiEvent::STATUS_SUCCEEDED,
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'http_status' => 200,
                'request_body_encrypted' => ['baseline' => true],
                'response_body_encrypted' => ['output' => []],
            ]);
        }

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [
                FedExValidationScopeService::SCOPE_ADDRESS_VALIDATION,
                FedExValidationScopeService::SCOPE_SERVICE_AVAILABILITY,
            ],
        );

        $this->assertSame('passed', collect($assessment['checks'])->firstWhere('key', 'address_validation')['status']);
        $this->assertSame('passed', collect($assessment['checks'])->firstWhere('key', 'service_availability')['status']);
    }

    public function test_rate_quote_http_403_is_blocked_and_prevents_readiness(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Preflight Rate Blocked Store');

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
            'request_body_encrypted' => ['accountNumber' => ['value' => '700257037']],
            'response_body_encrypted' => ['errors' => [['code' => 'FORBIDDEN.ERROR']]],
            'request_summary' => ['endpoint' => '/rate/v1/rates/quotes'],
            'response_summary' => ['http_status' => 403, 'authorization_blocked' => true],
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);

        $this->assertFalse($assessment['ready']);
        $this->assertTrue(collect($assessment['blockers'])->contains(fn (array $blocker): bool => ($blocker['key'] ?? '') === 'rate_quote'));
    }

    public function test_missing_us05_package_two_label_fails_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Preflight MPS Store');

        $this->seedSuccessfulShipEvent($store, $account, 'IntegratorUS05', 'ship_us05_pdf_mps', 'PDF', 1);

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_SHIP],
        );

        $this->assertFalse($assessment['ready']);
        $this->assertTrue(
            collect($assessment['checks'])->contains(fn (array $check): bool => ($check['key'] ?? '') === 'ship_us05_pdf_mps_label_2')
        );
    }

    public function test_tracking_optional_when_scope_excludes_tracking(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('Preflight Tracking Optional Store');

        $assessment = app(FedExValidationPreflightService::class)->assess(
            $store,
            $account,
            [FedExValidationScopeService::SCOPE_COMPREHENSIVE_RATES],
        );

        $this->assertFalse(
            collect($assessment['checks'])->contains(fn (array $check): bool => ($check['key'] ?? '') === 'tracking')
        );
    }

    private function seedSuccessfulShipEvent(
        Store $store,
        CarrierAccount $account,
        string $testCaseKey,
        string $scenarioKey,
        string $labelFormat,
        int $packageSequence,
    ): void {
        $event = CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_FEDEX_SHIP_CREATE_LABEL,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => $labelFormat,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'request_body_encrypted' => ['requestedShipment' => ['serviceType' => 'FEDEX_GROUND']],
            'response_body_encrypted' => ['output' => ['transactionShipments' => []]],
        ]);

        $relativePath = "fedex-validation/{$store->id}/labels/test-{$testCaseKey}-{$packageSequence}.pdf";
        $absolutePath = storage_path('app/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, '%PDF-1.4 test');

        FedExValidationArtifact::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'carrier_api_event_id' => $event->id,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'scenario_key' => $scenarioKey,
            'test_case_key' => $testCaseKey,
            'label_format' => $labelFormat,
            'package_sequence' => $packageSequence,
            'artifact_type' => 'ship_label_'.strtolower($labelFormat),
            'artifact_role' => FedExValidationArtifact::ROLE_GENERATED_LABEL,
            'label' => $testCaseKey.' package '.$packageSequence,
            'file_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'sha256' => hash('sha256', '%PDF-1.4 test'),
        ]);
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
