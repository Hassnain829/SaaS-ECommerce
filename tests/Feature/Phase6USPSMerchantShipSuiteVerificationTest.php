<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\USPS\Support\USPSMerchantWizard;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6USPSMerchantShipSuiteVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureUspsMerchant(shipSuiteEnabled: false);
    }

    public function test_ship_suite_verify_is_blocked_when_feature_flag_is_disabled(): void
    {
        [$owner, $store] = $this->ownerStore('USPS Ship Suite Disabled Store');
        $account = $this->authorizedAccount($store);

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.verify-ship-suite', $account))
            ->assertRedirect()
            ->assertSessionHasErrors('usps');

        Http::assertNothingSent();
        $account->refresh();
        $this->assertSame(CarrierAccount::USPS_ENROLLMENT_NOT_STARTED, $account->usps_enrollment_status);
        $this->assertNull($account->usps_payment_verified_at);
    }

    public function test_ship_suite_verify_runs_enrollment_and_payment_when_enabled(): void
    {
        $this->configureUspsMerchant(shipSuiteEnabled: true);

        [$owner, $store] = $this->ownerStore('USPS Ship Suite Success Store');
        $account = $this->authorizedAccount($store);

        $this->fakeUspsShipSuiteHttp();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.verify-ship-suite', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertSame(CarrierAccount::USPS_ENROLLMENT_VERIFIED, $account->usps_enrollment_status);
        $this->assertNotNull($account->usps_payment_verified_at);
        $this->assertSame(CarrierAccount::USPS_AUTH_CONNECTED, $account->usps_authorization_status);
        $this->assertSame(CarrierAccount::CONNECTION_CONNECTED, $account->connection_status);
        $this->assertFalse((bool) data_get($account->capabilities, 'rates'));
        $this->assertFalse((bool) data_get($account->capabilities, 'labels'));

        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_USPS_SHIP_ENROLLMENT_VERIFY,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_USPS_PAYMENT_AUTHORIZATION,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_ship_suite_verify_uses_ship_enrollment_v3_path(): void
    {
        $this->configureUspsMerchant(shipSuiteEnabled: true);

        [$owner, $store] = $this->ownerStore('USPS Ship Suite Path Store');
        $account = $this->authorizedAccount($store);

        $urls = [];
        $this->fakeUspsShipSuiteHttp($urls);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.verify-ship-suite', $account));

        $this->assertTrue(collect($urls)->contains(
            fn (string $url): bool => str_contains($url, '/ship-enrollment/v3/enrollment')
        ));
        $this->assertTrue(collect($urls)->contains(
            fn (string $url): bool => str_contains($url, '/payments/v3/payment-authorization')
        ));
    }

    public function test_ship_suite_verify_requires_oauth_authorization_first(): void
    {
        $this->configureUspsMerchant(shipSuiteEnabled: true);

        [$owner, $store] = $this->ownerStore('USPS Ship Suite Auth Required Store');
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
        ]);
        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->save();

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.usps-merchant.verify-ship-suite', $account))
            ->assertRedirect()
            ->assertSessionHasErrors('usps');

        Http::assertNothingSent();
    }

    public function test_manage_page_shows_postage_verify_action_when_ship_suite_enabled(): void
    {
        $this->configureUspsMerchant(shipSuiteEnabled: true, oauthEnabled: true);

        [$owner, $store] = $this->ownerStore('USPS Manage Ship Suite Store');
        $account = $this->authorizedAccount($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.usps-merchant.manage', $account))
            ->assertOk()
            ->assertSee('Verify postage account', false)
            ->assertSee(route('settings.shipping.usps-merchant.verify-ship-suite', $account), false);
    }

    private function authorizedAccount(Store $store): CarrierAccount
    {
        $account = $this->createUspsMerchantAccount($store, $this->createOriginLocation($store), [
            'usps_authorization_status' => CarrierAccount::USPS_AUTH_VERIFYING,
        ]);
        $account->setUspsMerchantIdentifiers('49188300', '903800001', '1000445839');
        $account->save();

        $context = $account->connection_context_json ?? [];
        data_set($context, 'usps_merchant.oauth_authorization_verified_at', now()->toIso8601String());
        data_set($context, 'usps_merchant.completed_wizard_steps', [
            USPSMerchantWizard::STEP_ORIGIN,
            USPSMerchantWizard::STEP_IDENTIFIERS,
            USPSMerchantWizard::STEP_AUTHORIZATION,
        ]);
        $account->forceFill(['connection_context_json' => $context])->save();

        return $account->fresh();
    }

    /**
     * @param  list<string>|null  $urls
     */
    private function fakeUspsShipSuiteHttp(?array &$urls = null): void
    {
        Http::fake(function ($request) use (&$urls) {
            $url = $request->url();
            if ($urls !== null) {
                $urls[] = $url;
            }

            if (str_contains($url, '/oauth2/v3/token')) {
                return Http::response([
                    'access_token' => 'platform-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/ship-enrollment/v3/enrollment')) {
                return Http::response([
                    'enrollmentStatus' => 'ENROLLED',
                ], 200);
            }

            if (str_contains($url, '/payments/v3/payment-authorization')) {
                $body = $request->data();
                $roleNames = collect($body['roles'] ?? [])->pluck('roleName')->all();
                $this->assertContains('PAYER', $roleNames);
                $this->assertContains('RATE_HOLDER', $roleNames);
                $this->assertContains('LABEL_OWNER', $roleNames);
                $this->assertContains('PLATFORM', $roleNames);

                return Http::response([
                    'paymentAuthorizationToken' => 'test-payment-authorization-token',
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected USPS request']]], 500);
        });
    }

    private function configureUspsMerchant(bool $shipSuiteEnabled, bool $oauthEnabled = true): void
    {
        config([
            'carriers.usps.enabled' => true,
            'carriers.usps.merchant_connection_enabled' => true,
            'carriers.usps.merchant_oauth_enabled' => $oauthEnabled,
            'carriers.usps.merchant_oauth_icd_confirmed' => true,
            'carriers.usps.merchant_ship_suite_verify_enabled' => $shipSuiteEnabled,
            'carriers.usps.environment' => 'testing',
            'carriers.usps.base_url' => 'https://apis-tem.usps.com',
            'carriers.usps.consumer_key' => 'test-usps-consumer-key',
            'carriers.usps.consumer_secret' => 'test-usps-consumer-secret',
            'carriers.usps.merchant_oauth_consumer_key' => 'test-usps-merchant-oauth-key',
            'carriers.usps.merchant_oauth_consumer_secret' => 'test-usps-merchant-oauth-secret',
            'carriers.usps.platform_crid' => '49188300',
            'carriers.usps.platform_epa' => '1000445839',
            'carriers.usps.platform_master_mid' => '903800001',
            'carriers.usps.ship_enrollment_path' => '/ship-enrollment/v3/enrollment',
            'carriers.usps.payment_authorization_path' => '/payments/v3/payment-authorization',
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
            'usps_active_store_key' => $store->id,
            'default_origin_location_id' => $location->id,
            'connection_context_json' => [
                'usps_merchant' => [
                    'completed_wizard_steps' => [
                        USPSMerchantWizard::STEP_ORIGIN,
                        USPSMerchantWizard::STEP_IDENTIFIERS,
                        USPSMerchantWizard::STEP_AUTHORIZATION,
                    ],
                ],
            ],
            'supported_countries' => ['US'],
            'enabled_for_checkout' => false,
        ], CarrierAccount::ownershipAttributesForUspsMerchantLabelProvider(), $overrides));
    }
}
