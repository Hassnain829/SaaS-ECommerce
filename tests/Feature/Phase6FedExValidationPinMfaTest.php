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
use App\Services\Carriers\FedEx\Validation\FedExValidationPreflightService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExValidationPinMfaTest extends TestCase
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
            'carriers.fedex.mfa_pin_generation_path' => '/registration/v2/customerkeys/pingeneration',
            'carriers.fedex.mfa_pin_validation_path' => '/registration/v2/pin/keysgeneration',
        ]);
    }

    public function test_workspace_shows_email_and_call_pin_mfa_controls(): void
    {
        [$owner, , $account] = $this->integratorAccountFixture('Pin MFA UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $account->store_id])
            ->get(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertOk()
            ->assertSeeText('Email PIN')
            ->assertSeeText('Phone-call PIN')
            ->assertSeeText('Send email pin');
    }

    public function test_validation_email_pin_generation_and_validation_record_evidence(): void
    {
        [$owner, $store, $account, $session] = $this->integratorAccountFixture('Pin MFA Email Store');
        $session->setAccountAuthToken('fedex-account-auth-token-test');
        $session->forceFill([
            'fedex_transaction_id' => 'fedex-reg-mfa-txn-1',
            'account_name' => 'RTC Test Company',
        ])->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response(['access_token' => 'platform-parent-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), '/registration/v2/customerkeys/pingeneration')) {
                return Http::response(['transactionId' => 'fedex-pin-gen-email-1'], 200);
            }

            if (str_contains($request->url(), '/registration/v2/pin/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'fedex-pin-validate-email-1',
                    'output' => [
                        'mfaOptions' => [
                            [
                                'mfaRequired' => true,
                                'accountAuthToken' => 'fedex-account-auth-token-test',
                                'options' => ['secureCode' => ['CALL']],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected URL: '.$request->url()]]], 404);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.mfa.pin.generate', [$account, 'email']))
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('success');

        $generationEvent = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_PIN_GENERATION_EMAIL)
            ->where('mfa_method', 'email')
            ->latest('id')
            ->first();

        $this->assertNotNull($generationEvent);
        $this->assertSame(200, (int) $generationEvent->http_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.validation.run.mfa.pin.validate', [$account, 'email']), [
                'pin' => '123456',
            ])
            ->assertRedirect(route('settings.shipping.carrier-accounts.fedex.validation', $account))
            ->assertSessionHas('success');

        $validationEvent = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->where('scenario_key', CarrierApiEvent::SCENARIO_REGISTRATION_PIN_VALIDATION_EMAIL)
            ->where('mfa_method', 'email')
            ->latest('id')
            ->first();

        $this->assertNotNull($validationEvent);
        $this->assertSame(200, (int) $validationEvent->http_status);

        $account->refresh();
        $this->assertSame(CarrierAccount::CONNECTION_CONNECTED, $account->connection_status);
    }

    public function test_successful_email_pin_events_pass_preflight(): void
    {
        [, $store, $account, $session] = $this->integratorAccountFixture('Pin MFA Preflight Store');

        foreach ([
            CarrierApiEvent::SCENARIO_REGISTRATION_PIN_GENERATION_EMAIL,
            CarrierApiEvent::SCENARIO_REGISTRATION_PIN_VALIDATION_EMAIL,
            CarrierApiEvent::SCENARIO_REGISTRATION_PIN_GENERATION_CALL,
            CarrierApiEvent::SCENARIO_REGISTRATION_PIN_VALIDATION_CALL,
        ] as $scenarioKey) {
            CarrierApiEvent::query()->create([
                'store_id' => $store->id,
                'carrier_account_id' => $account->id,
                'registration_session_id' => $session->id,
                'provider' => CarrierAccount::PROVIDER_FEDEX,
                'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
                'scenario_key' => $scenarioKey,
                'mfa_method' => str_contains($scenarioKey, 'email') ? 'email' : 'call',
                'status' => CarrierApiEvent::STATUS_SUCCEEDED,
                'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
                'http_status' => 200,
                'http_method' => 'POST',
                'request_body_encrypted' => ['endpoint' => '/registration/v2/test'],
                'response_body_encrypted' => ['transactionId' => 'txn-'.$scenarioKey],
                'response_summary' => ['http_status' => 200],
            ]);
        }

        $assessment = app(FedExValidationPreflightService::class)->assess($store, $account);

        foreach ([
            'registration_pin_generation_email',
            'registration_pin_validation_email',
            'registration_pin_generation_call',
            'registration_pin_validation_call',
        ] as $key) {
            $check = collect($assessment['checks'])->firstWhere('key', $key);
            $this->assertSame('passed', $check['status'] ?? null, "Expected {$key} to pass preflight.");
        }
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
