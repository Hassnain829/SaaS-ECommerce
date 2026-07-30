<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\USPS\Support\USPSMerchantWizard;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6USPSMerchantConnectionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureUspsMerchant(true);
    }

    public function test_shipping_page_shows_usps_merchant_section_when_enabled(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Merchant UI Store');
        $this->createOriginLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'advanced']))
            ->assertOk()
            ->assertSeeText('USPS Merchant Account')
            ->assertSeeText('Connect USPS account')
            ->assertSeeText('USPS sandbox diagnostics')
            ->assertDontSeeText('USPS Sandbox Tools');
    }

    public function test_merchant_connect_start_page_renders_requirements_without_secret_fields(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Merchant Start Store');
        $this->createOriginLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.start'))
            ->assertOk()
            ->assertSeeText('Connect USPS')
            ->assertSeeText('Authorize BmyBrand as Label Provider')
            ->assertDontSeeText('Consumer Secret')
            ->assertDontSeeText('EPS password')
            ->assertDontSeeText('USPS password');
    }

    public function test_wizard_stops_at_awaiting_authorization_without_official_usps_flow(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Merchant Wizard Store');
        $location = $this->createOriginLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.origin'), [
                'origin_location_id' => $location->id,
                'display_name' => 'Primary USPS account',
            ])
            ->assertRedirect(route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => CarrierAccount::query()->where('store_id', $store->id)->value('id'),
                'step' => USPSMerchantWizard::STEP_IDENTIFIERS,
            ]));

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->where('connection_mode', CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER)
            ->firstOrFail();

        $this->assertSame(CarrierAccount::USPS_AUTH_SETUP_REQUIRED, $account->usps_authorization_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.identifiers', $account), [
                'merchant_crid' => '49188300',
                'merchant_mid' => '903800001',
                'merchant_epa' => '1000445839',
            ])
            ->assertRedirect(route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account->id,
                'step' => USPSMerchantWizard::STEP_AUTHORIZATION,
            ]));

        $account->refresh();
        $this->assertSame(CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION, $account->usps_authorization_status);
        $this->assertSame(CarrierAccount::CREDENTIALS_USPS_MERCHANT_AUTHORIZATION, $account->credentials_source);
        $this->assertTrue($account->hasUspsMerchantIdentifiers());
        $this->assertSame('****8300', $account->connection_context_json['usps_merchant']['merchant_crid_masked'] ?? null);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.authorization', $account), [
                'requirements_confirmed' => '1',
                'portal_authorization_confirmed' => '1',
            ])
            ->assertNotFound();

        $account->refresh();
        $this->assertSame(CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION, $account->usps_authorization_status);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account,
                'step' => USPSMerchantWizard::STEP_AUTHORIZATION,
            ]))
            ->assertOk()
            ->assertSeeText('Awaiting official USPS authorization')
            ->assertSeeText('Manage USPS Business Account')
            ->assertDontSeeText('Confirm authorization')
            ->assertDontSeeText('I authorized')
            ->assertDontSeeText('Open USPS Business Portal');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.manage', $account))
            ->assertRedirect(route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account->id,
                'step' => USPSMerchantWizard::STEP_AUTHORIZATION,
            ]));
    }

    public function test_identifiers_page_never_shows_password_or_api_secret_fields(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Identifiers UI Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_SETUP_REQUIRED,
            'connection_context_json' => [
                'usps_merchant' => [
                    'completed_wizard_steps' => [USPSMerchantWizard::STEP_ORIGIN],
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account,
                'step' => USPSMerchantWizard::STEP_IDENTIFIERS,
            ]))
            ->assertOk()
            ->assertSeeText('Customer Registration ID (CRID)')
            ->assertSeeText('Enterprise Payment Account (EPA)')
            ->assertDontSeeText('Consumer Secret')
            ->assertDontSeeText('USPS password')
            ->assertDontSeeText('EPS password');
    }

    public function test_reauthorize_returns_merchant_to_authorization_step(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Reauthorize Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.reauthorize', $account))
            ->assertRedirect(route('settings.shipping.usps-merchant.wizard', [
                'carrierAccount' => $account->id,
                'step' => USPSMerchantWizard::STEP_AUTHORIZATION,
            ]));

        $account->refresh();
        $this->assertSame(CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION, $account->usps_authorization_status);
    }

    public function test_disconnect_marks_merchant_account_disabled(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Merchant Disconnect Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.disconnect', $account))
            ->assertRedirect(route('shippingAutomation', ['tab' => 'advanced']))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertSame(CarrierAccount::USPS_AUTH_DISABLED, $account->usps_authorization_status);
        $this->assertNull($account->credentials_encrypted);
    }

    public function test_cross_store_manage_is_blocked(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('USPS Merchant Store A');
        [$ownerB, $storeB] = $this->ownerStore('USPS Merchant Store B');
        $account = $this->createUspsMerchantAccount($storeA, $this->createOriginLocation($storeA), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
        ]);

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeB->id])
            ->get(route('settings.shipping.usps-merchant.manage', $account))
            ->assertNotFound();
    }

    public function test_staff_cannot_start_merchant_connection(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Merchant Staff Store');
        $staff = $this->staffUser($store);
        $this->createOriginLocation($store);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.start'))
            ->assertForbidden();
    }

    public function test_existing_platform_testing_flow_remains_available_in_diagnostics(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Platform Testing Preserved Store');
        $this->createOriginLocation($store);
        $testingAccount = $this->createUspsPlatformTestingAccount($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'advanced']))
            ->assertOk()
            ->assertSeeText('USPS sandbox diagnostics')
            ->assertSeeText('Platform testing connection')
            ->assertSee((string) $testingAccount->display_name);
    }

    private function configureUspsMerchant(bool $enabled): void
    {
        config([
            'carriers.usps.enabled' => true,
            'carriers.usps.merchant_connection_enabled' => $enabled,
            'carriers.usps.environment' => 'testing',
            'carriers.usps.base_url' => 'https://apis-tem.usps.com',
            'carriers.usps.consumer_key' => 'test-usps-consumer-key',
            'carriers.usps.consumer_secret' => 'test-usps-consumer-secret',
        ]);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = $this->merchant(Str::slug($name).'-owner@example.test');
        $store = $this->store($owner, $name);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        return [$owner, $store];
    }

    private function staffUser(Store $store): User
    {
        $staff = $this->merchant('usps-merchant-staff@example.test');
        $this->attach($store, $staff, Store::ROLE_STAFF);

        return $staff;
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);
    }

    private function createOriginLocation(Store $store): Location
    {
        return Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUspsMerchantAccount(Store $store, Location $location, array $overrides = []): CarrierAccount
    {
        $usps = Carrier::query()->where('code', 'usps')->firstOrFail();

        return CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $usps->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => 'Merchant USPS account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_MERCHANT_LABEL_PROVIDER,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_PENDING_VALIDATION,
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_AWAITING_AUTHORIZATION,
            'usps_enrollment_status' => CarrierAccount::USPS_ENROLLMENT_NOT_STARTED,
            'default_origin_location_id' => $location->id,
            'connection_context_json' => [
                'usps_merchant' => [
                    'completed_wizard_steps' => [
                        USPSMerchantWizard::STEP_ORIGIN,
                        USPSMerchantWizard::STEP_IDENTIFIERS,
                    ],
                ],
            ],
            'supported_countries' => ['US'],
            'enabled_for_checkout' => false,
        ], CarrierAccount::ownershipAttributesForUspsMerchantLabelProvider(), $overrides));
    }

    private function createUspsPlatformTestingAccount(Store $store): CarrierAccount
    {
        $usps = Carrier::query()->where('code', 'usps')->firstOrFail();

        return CarrierAccount::query()->create(array_merge([
            'store_id' => $store->id,
            'carrier_id' => $usps->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => 'USPS testing account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_PLATFORM,
            'billing_owner' => CarrierAccount::BILLING_OWNER_PLATFORM,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => false,
        ], CarrierAccount::ownershipAttributesForUspsPlatformTesting()));
    }
}
