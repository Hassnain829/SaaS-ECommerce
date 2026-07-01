<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Capabilities\FedExCapabilityRegistry;
use App\Services\Carriers\FedEx\Validation\FedExBrandComplianceService;
use App\Services\Carriers\FedEx\Validation\FedExCapabilityEvidenceService;
use App\Services\Carriers\FedEx\Validation\FedExFinalSubmissionService;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExBrandComplianceTest extends TestCase
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

    public function test_legal_notice_is_exact_required_text(): void
    {
        $service = app(FedExBrandComplianceService::class);

        $this->assertSame(
            'FedEx service marks are owned by Federal Express Corporation and are used by permission.',
            $service->legalNotice(),
        );
        $this->assertTrue($service->legalNoticeIsExact($service->legalNotice()));
        $this->assertFalse($service->legalNoticeIsExact('FedEx marks are owned by FedEx.'));
    }

    public function test_unapproved_logo_blocks_package_eight_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx Brand Block Store');

        $brand = app(FedExBrandComplianceService::class);
        $expectedStatus = $brand->logoIsAvailable() && $brand->logoIsApprovedSource() ? 'passed' : 'blocked';

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account, null, includePackageEight: true);
        $logoCheck = collect($assessment['checks'])->firstWhere('key', 'branding_logo_asset');

        $this->assertSame($expectedStatus, $logoCheck['status'] ?? null);
        $this->assertFalse($assessment['ready']);
    }

    public function test_capabilities_page_shows_legal_notice_without_account_secrets(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Capabilities Store');

        $response = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.capabilities', [$account, 'evidence_mode' => 1]));

        $response->assertOk()
            ->assertSeeText('FedEx service marks are owned by Federal Express Corporation and are used by permission.')
            ->assertSeeText('Supported FedEx services')
            ->assertDontSeeText('700257037')
            ->assertDontSeeText('client_secret');
    }

    public function test_customer_facing_capabilities_exclude_validation_only_services(): void
    {
        $registry = app(FedExCapabilityRegistry::class);
        $customer = $registry->customerFacingCapabilities();

        foreach ($customer['services'] ?? [] as $service) {
            $this->assertContains($service['status'] ?? '', [
                FedExCapabilityRegistry::STATUS_PRODUCTION_ENABLED,
                FedExCapabilityRegistry::STATUS_PRODUCTION_CONDITIONAL,
            ]);
        }

        $allServices = $registry->services();
        $validationOnly = collect($allServices)->where('status', FedExCapabilityRegistry::STATUS_VALIDATION_ONLY)->count();
        $this->assertGreaterThan(0, $validationOnly);
        $this->assertLessThan(count($allServices), count($customer['services'] ?? []));
    }

    public function test_branding_screenshot_types_are_required_in_preflight(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx Screenshot Preflight Store');

        $checks = app(FedExCapabilityEvidenceService::class)->preflightChecks($store, $account);

        $this->assertCount(3, $checks);
        $this->assertTrue(collect($checks)->every(fn (array $check): bool => ($check['status'] ?? '') === 'incomplete'));
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
