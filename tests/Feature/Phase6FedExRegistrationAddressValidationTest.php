<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierAccountRegistrationSession;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Validation\FedExValidationEvidenceQueryService;
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExRegistrationAddressValidationTest extends TestCase
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

    public function test_mfa_required_registration_address_event_counts_as_passed_preflight(): void
    {
        [, $store, $account, $session] = $this->integratorAccountFixture('Registration Address Canonical Store');

        CarrierApiEvent::query()->create([
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'registration_session_id' => $session->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'scenario_key' => CarrierApiEvent::SCENARIO_REGISTRATION_ADDRESS,
            'status' => CarrierApiEvent::STATUS_FAILED,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'http_status' => 200,
            'http_method' => 'POST',
            'error_code' => 'registration_mfa_required',
            'request_body_encrypted' => ['accountNumber' => ['value' => '700257037']],
            'response_body_encrypted' => ['output' => ['mfaOptions' => [['option' => 'SMS']]]],
            'response_summary' => ['http_status' => 200, 'mfa_detected' => true],
        ]);

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);
        $check = collect($assessment['checks'])->firstWhere('key', 'registration_address_validation');

        $this->assertSame('passed', $check['status'] ?? null);
        $event = CarrierApiEvent::query()->find($check['event_id']);
        $query = app(FedExValidationEvidenceQueryService::class);

        $this->assertTrue($query->isValidRegistrationAddressEvent($event));
        $this->assertTrue($query->isFinalExportableEvent($event));
        $this->assertFalse($event->isSuccessfulHttp());
    }

    public function test_workspace_shows_registration_address_one_click_run(): void
    {
        [$owner, , $account] = $this->integratorAccountFixture('Registration Address UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $account->store_id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Run Registration Address Validation');
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount, 3: CarrierAccountRegistrationSession}
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
        $session = CarrierAccountRegistrationSession::query()->create([
            'store_id' => $store->id,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'account_number' => '700257037',
            'account_last4' => '7037',
            'registration_address_json' => [
                'provider_account_number' => '700257037',
                'company_name' => 'Demo Digital',
                'contact_name' => 'Demo Owner',
                'email' => 'owner@example.test',
                'phone' => '5555550100',
                'address_line1' => '1751 THOMPSON ST',
                'city' => 'AURORA',
                'state' => 'OH',
                'postal_code' => '44202',
                'country_code' => 'US',
                'residential' => false,
            ],
        ]);

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
            'registration_session_id' => $session->id,
            'settings' => ['default_origin_location_id' => $location->id],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $account->setCredentials([
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
        ]);
        $account->save();

        return [$owner, $store, $account, $session];
    }
}
