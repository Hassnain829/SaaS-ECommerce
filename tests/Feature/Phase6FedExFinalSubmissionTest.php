<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Validation\FedExFinalSubmissionService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExFinalSubmissionTest extends TestCase
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

    public function test_final_preflight_route_does_not_mutate_and_reports_blocked_state(): void
    {
        [$owner, , $account] = $this->integratorAccountFixture('FedEx Final Preflight Route Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $account->store_id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.final-preflight', $account))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('error');

        $assessment = app(FedExFinalSubmissionService::class)->runFinalPreflight($account->store, $account);
        $this->assertTrue($assessment['final_submission_preflight'] ?? false);
        $this->assertTrue($assessment['no_api_mutations'] ?? false);
        $this->assertFalse($assessment['ready'] ?? true);
    }

    public function test_snapshot_creation_is_blocked_when_final_preflight_not_ready(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx Snapshot Block Store');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class);

        app(FedExFinalSubmissionService::class)->createSnapshot($store, $account);
    }

    public function test_workspace_shows_package_eight_final_submission_section(): void
    {
        [$owner, $store, $account] = $this->integratorAccountFixture('FedEx Package Eight UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Package 8 - Final submission')
            ->assertSeeText('Run Final Submission Preflight')
            ->assertSeeText('Branding screenshots');
    }

    public function test_global_territory_checks_mark_lac_amea_eu_not_applicable(): void
    {
        [, $store, $account] = $this->integratorAccountFixture('FedEx Scope Store');

        $assessment = app(\App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService::class)
            ->assess($store, $account, null, includePackageEight: true);

        foreach (['global_region_lac_scope', 'global_region_amea_scope', 'global_region_eu_scope'] as $key) {
            $check = collect($assessment['checks'])->firstWhere('key', $key);
            $this->assertSame('not_applicable', $check['status'] ?? null, $key);
        }
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
