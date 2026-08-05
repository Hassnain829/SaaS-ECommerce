<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Connection\FedExRegistrationInputValidator;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Support\CarrierCountryOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6FedExLiveConnectionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_option_hidden_when_production_not_enabled(): void
    {
        [$owner, $store] = $this->ownerStore();
        $this->makeReadyLocation($store);
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.integrator_production_enabled' => false,
            'carriers.fedex.live.client_id' => '',
            'carriers.fedex.live.client_secret' => '',
        ]);

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.start'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('name="environment"', $html);
        $this->assertStringContainsString('value="sandbox"', $html);
        $this->assertStringContainsString('value="live"', $html);
        $this->assertMatchesRegularExpression('/value="live"[^>]*disabled/', $html);
        $this->assertStringContainsString('FedEx will charge shipping costs directly to your connected FedEx account.', $html);
        $this->assertStringNotContainsString('value="sandbox" type="hidden"', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="environment" value="sandbox">', $html);
    }

    public function test_live_option_enabled_but_sandbox_remains_default_when_production_is_safe(): void
    {
        [$owner, $store] = $this->ownerStore();
        $this->makeReadyLocation($store);
        $this->configureSafeProduction();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.start'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertDoesNotMatchRegularExpression('/value="live"[^>]*\bdisabled\b/', $html);
        $this->assertStringContainsString('Live FedEx account', $html);
        $this->assertMatchesRegularExpression('/value="sandbox"[^>]*\bchecked\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="live"[^>]*\bchecked\b/', $html);
    }

    public function test_live_submission_is_rejected_and_creates_no_session_when_production_disabled(): void
    {
        [$owner, $store] = $this->ownerStore();
        $location = $this->makeReadyLocation($store);
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.integrator_production_enabled' => false,
            'carriers.fedex.sandbox.client_id' => 'sandbox-client',
            'carriers.fedex.sandbox.client_secret' => 'sandbox-secret',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.origin'), [
                'origin_location_id' => $location->id,
                'environment' => 'live',
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('carrier_account_registration_sessions', 0);
    }

    public function test_production_country_options_include_us_and_canada_not_sweden(): void
    {
        $this->assertSame(['US', 'CA'], array_keys(CarrierCountryOptions::fedExProductionOptions()));
        $this->assertArrayHasKey('SE', CarrierCountryOptions::fedExValidationOptions());
        $this->assertTrue(CarrierCountryOptions::isAllowedFedExCountry('CA'));
        $this->assertFalse(CarrierCountryOptions::isAllowedFedExRegistrationCountry('SE', 'live', false));
        $this->assertTrue(CarrierCountryOptions::isAllowedFedExRegistrationCountry('SE', 'sandbox', true));
    }

    public function test_registration_validator_rejects_invalid_us_state(): void
    {
        $result = app(FedExRegistrationInputValidator::class)->validate(
            $this->registrationInput([
                'state' => 'ZZ',
                'country_code' => 'US',
                'postal_code' => '78701',
            ]),
            ['environment' => 'sandbox', 'validation_mode' => false]
        );

        $this->assertArrayHasKey('state', $result['errors']);
    }

    public function test_registration_validator_rejects_invalid_canadian_province_and_postal_code(): void
    {
        $result = app(FedExRegistrationInputValidator::class)->validate(
            $this->registrationInput([
                'state' => 'ZZ',
                'country_code' => 'CA',
                // D is forbidden in Canadian postal-code letter positions.
                'postal_code' => 'D1D 1D1',
            ]),
            ['environment' => 'sandbox', 'validation_mode' => false]
        );

        $this->assertArrayHasKey('state', $result['errors']);
        $this->assertArrayHasKey('postal_code', $result['errors']);
    }

    public function test_registration_validator_normalizes_valid_canadian_address(): void
    {
        $validator = app(FedExRegistrationInputValidator::class);

        $canada = $validator->validate($this->registrationInput([
            'state' => 'ON',
            'postal_code' => 'M5H2N2',
            'country_code' => 'CA',
        ]), ['environment' => 'sandbox', 'validation_mode' => false]);

        $this->assertSame([], $canada['errors']);
        $this->assertSame('CA', $canada['normalized']['country_code']);
        $this->assertSame('ON', $canada['normalized']['state']);
        $this->assertSame('M5H 2N2', $canada['normalized']['postal_code']);
        $this->assertSame('M5H2N2', $canada['normalized']['registration_postal_code_raw']);
    }

    public function test_registration_validator_rejects_sweden_outside_validation_mode(): void
    {
        $validator = app(FedExRegistrationInputValidator::class);

        $sweden = $validator->validate($this->registrationInput([
            'state' => 'AB',
            'postal_code' => '11122',
            'country_code' => 'SE',
        ]), ['environment' => 'live', 'validation_mode' => true]);

        $this->assertArrayHasKey('country_code', $sweden['errors']);
    }

    public function test_each_unsafe_production_configuration_keeps_live_unavailable(): void
    {
        $config = app(FedExConfig::class);
        $unsafeCases = [
            'FedEx disabled' => ['carriers.fedex.enabled' => false],
            'Model A disabled' => ['carriers.fedex.integrator_model_a_enabled' => false],
            'production flag disabled' => ['carriers.fedex.integrator_production_enabled' => false],
            'live client id missing' => ['carriers.fedex.live.client_id' => ''],
            'live client secret missing' => ['carriers.fedex.live.client_secret' => ''],
            'wrong live base URL' => ['carriers.fedex.live.base_url' => 'https://apis-sandbox.fedex.com'],
            'non-exact live base URL' => ['carriers.fedex.live.base_url' => 'https://apis.fedex.com/'],
            'Model B fallback enabled' => ['carriers.fedex.model_b_developer_fallback_enabled' => true],
            'developer mode enabled' => ['carriers.fedex.developer_mode_enabled' => true],
            'validation mode enabled' => ['carriers.fedex.validation_mode_enabled' => true],
            'sandbox fallback enabled' => ['carriers.fedex.sandbox_allow_platform_fallback' => true],
            'live countries blank' => ['carriers.fedex.live_allowed_countries' => ''],
            'live countries malformed' => ['carriers.fedex.live_allowed_countries' => '@@'],
            'live country outside approved scope' => ['carriers.fedex.live_allowed_countries' => 'US,SE'],
        ];

        foreach ($unsafeCases as $label => $override) {
            $this->configureSafeProduction();
            config($override);

            $this->assertFalse($config->productionEnabled(), $label);
            $this->assertNotEmpty($config->productionConfigurationErrors(), $label);
            $this->assertFalse($config->allowsIntegratorEnvironment('live'), $label);
        }
    }

    public function test_fully_safe_production_configuration_passes_readiness(): void
    {
        $this->configureSafeProduction();
        $config = app(FedExConfig::class);

        $this->assertSame([], $config->productionConfigurationErrors());
        $this->assertTrue($config->productionEnabled());
        $this->assertTrue($config->allowsIntegratorEnvironment('live'));
        $this->assertSame(['US', 'CA'], $config->liveAllowedCountries());
        $config->assertProductionReady();
        $this->addToAssertionCount(1);
    }

    public function test_blank_or_invalid_live_country_config_does_not_fall_back(): void
    {
        $config = app(FedExConfig::class);

        config(['carriers.fedex.live_allowed_countries' => '']);
        $this->assertSame([], $config->liveAllowedCountries());

        config(['carriers.fedex.live_allowed_countries' => '@@']);
        $this->assertSame([], $config->liveAllowedCountries());
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name = 'FedEx Batch1 Store'): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $store = Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function makeReadyLocation(Store $store): Location
    {
        return Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main Warehouse',
            'is_default' => true,
            'address_line1' => '100 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country_code' => 'US',
            'phone' => '5125550100',
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationInput(array $overrides = []): array
    {
        return array_merge([
            'provider_account_number' => '123456789',
            'company_name' => 'Maple Co',
            'address_line1' => '100 Queen St W',
            'city' => 'Toronto',
            'state' => 'ON',
            'postal_code' => 'M5H 2N2',
            'country_code' => 'CA',
        ], $overrides);
    }

    private function configureSafeProduction(): void
    {
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.integrator_production_enabled' => true,
            'carriers.fedex.live.client_id' => 'live-client',
            'carriers.fedex.live.client_secret' => 'live-secret',
            'carriers.fedex.live.base_url' => 'https://apis.fedex.com',
            'carriers.fedex.model_b_developer_fallback_enabled' => false,
            'carriers.fedex.developer_mode_enabled' => false,
            'carriers.fedex.validation_mode_enabled' => false,
            'carriers.fedex.sandbox_allow_platform_fallback' => false,
            'carriers.fedex.live_allowed_countries' => 'US,CA',
        ]);
    }
}
