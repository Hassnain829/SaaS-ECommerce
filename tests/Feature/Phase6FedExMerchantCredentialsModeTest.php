<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\CarrierAccountStatusPresenter;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExMerchantCredentialsModeTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ACCOUNT_NUMBER = '510087240';

    private const TEST_CLIENT_ID = 'l7a1b2c3d4e5f678901234567890abcd';

    private const TEST_CLIENT_SECRET = 'test-merchant-fedex-secret-key-value';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExFeature(true, platformConfigured: false);
    }

    public function test_fedex_wizard_defaults_to_merchant_credentials_form(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Credentials Form Store');
        $location = $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertOk()
            ->assertSeeText('Connect your own FedEx Developer credentials');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', [
                'carrier' => 'fedex',
                'step' => 'fedex_details',
                'origin_location_id' => $location->id,
            ]))
            ->assertOk()
            ->assertSee('name="fedex_client_id"', false)
            ->assertSee('name="fedex_client_secret"', false)
            ->assertSeeText('FedEx billing stays between you and FedEx')
            ->assertSeeText('Labels are not enabled in this phase')
            ->assertDontSee('name="company_name"', false)
            ->assertDontSee('Credential Registration', false);
    }

    public function test_owner_can_save_merchant_fedex_credentials_through_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Save Credentials Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);

        $this->assertSame(CarrierAccount::OWNERSHIP_MERCHANT_OWNED, $account->ownership_mode);
        $this->assertSame(CarrierAccount::CREDENTIALS_MERCHANT_ENCRYPTED, $account->credentials_source);
        $this->assertSame(CarrierAccount::CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS, $account->connection_mode);
        $this->assertSame(CarrierAccount::BILLING_OWNER_MERCHANT, $account->billing_owner);
        $this->assertSame(self::TEST_ACCOUNT_NUMBER, $account->provider_account_number);
        $this->assertTrue($account->hasMerchantFedExDeveloperCredentials());
        $this->assertFalse($account->supportsLabels());
        $this->assertFalse($account->enabled_for_checkout);
    }

    public function test_merchant_fedex_secret_is_encrypted_not_plain_text(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Encrypt Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);

        $raw = (string) $account->getRawOriginal('credentials_encrypted');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString(self::TEST_CLIENT_SECRET, $raw);
        $this->assertSame(self::TEST_CLIENT_SECRET, $account->merchantFedExClientSecret());
    }

    public function test_saved_credentials_are_not_exposed_in_ui(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Mask UI Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSee($account->maskedAccountNumber(), false)
            ->assertSee($account->maskedMerchantClientId(), false)
            ->assertDontSee(self::TEST_ACCOUNT_NUMBER, false)
            ->assertDontSee(self::TEST_CLIENT_ID, false)
            ->assertDontSee(self::TEST_CLIENT_SECRET, false);
    }

    public function test_connection_check_uses_merchant_credentials_not_platform_or_registration(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx OAuth Merchant Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);
        $oauthPayload = null;

        Http::fake(function ($request) use (&$oauthPayload) {
            $url = $request->url();

            if (str_contains($url, '/registration/v2/address/keysgeneration')) {
                throw new \RuntimeException('Credential Registration must not be called for merchant credentials mode.');
            }

            if (str_contains($url, '/oauth/token')) {
                $oauthPayload = $request->data();

                return Http::response([
                    'access_token' => 'merchant-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected FedEx URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'))
            ->assertSessionHas('success');

        $this->assertSame('client_credentials', data_get($oauthPayload, 'grant_type'));
        $this->assertSame(self::TEST_CLIENT_ID, data_get($oauthPayload, 'client_id'));
        $this->assertSame(self::TEST_CLIENT_SECRET, data_get($oauthPayload, 'client_secret'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/registration/v2/address/keysgeneration'));

        $account->refresh();
        $this->assertTrue($account->isConnected());
        $this->assertFalse($account->supportsRates());
        $this->assertFalse($account->supportsLabels());
        $this->assertFalse($account->supportsPickup());
        $this->assertFalse($account->supportsTracking());
        $this->assertFalse($account->enabled_for_checkout);
    }

    public function test_successful_merchant_oauth_shows_connected_using_merchant_credentials(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Connected Copy Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);
        $this->fakeMerchantOAuthHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'));

        $account->refresh();
        $presenter = CarrierAccountStatusPresenter::for($account);
        $this->assertSame('Connected for testing', $presenter->connectionStatusLabel());
        $this->assertContains('Connected using merchant credentials', $presenter->merchantCapabilityLabels());
        $this->assertContains('Billing handled by merchant', $presenter->merchantCapabilityLabels());
        $this->assertContains('Labels not enabled', $presenter->merchantCapabilityLabels());
    }

    public function test_failed_merchant_oauth_keeps_account_saved_with_friendly_error(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Failed OAuth Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);

        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'errors' => [['code' => 'AUTHENTICATION.FAILED', 'message' => 'Invalid client credentials']],
            ], 401),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $account->connection_status);
        $this->assertSame(CarrierAccount::CREDENTIALS_MERCHANT_ENCRYPTED, $account->credentials_source);
        $this->assertStringContainsString('FedEx credentials could not be verified', (string) $account->last_error_message);
    }

    public function test_carrier_api_events_redact_merchant_secret_token_and_full_client_id(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Event Redaction Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);
        $this->fakeMerchantOAuthHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ]);

        $events = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('carrier_account_id', $account->id)
            ->get();

        $this->assertTrue($events->isNotEmpty());

        foreach ($events as $event) {
            $encoded = json_encode([
                $event->request_summary,
                $event->response_summary,
                $event->error_message,
            ]);

            $this->assertStringNotContainsString(self::TEST_CLIENT_SECRET, (string) $encoded);
            $this->assertStringNotContainsString(self::TEST_CLIENT_ID, (string) $encoded);
            $this->assertStringNotContainsString(self::TEST_ACCOUNT_NUMBER, (string) $encoded);
            $this->assertStringNotContainsString('merchant-test-access-token', (string) $encoded);
        }
    }

    public function test_store_a_cannot_use_store_b_origin_for_fedex_wizard_save(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('FedEx Origin A');
        [, $storeB] = $this->ownerStore('FedEx Origin B');
        $locationB = $this->readyLocation($storeB);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('shipping.carriers.connect.fedex.details'), $this->fedExWizardPayload($locationB->id))
            ->assertSessionHasErrors(['origin_location_id']);
    }

    public function test_staff_cannot_save_fedex_credentials_through_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Staff Store');
        $staff = $this->staffUser('fedex-staff@example.test');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $location = $this->readyLocation($store);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), $this->fedExWizardPayload($location->id))
            ->assertForbidden();
    }

    public function test_fedex_wizard_available_without_platform_credentials_when_feature_enabled(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx No Platform Store');
        $this->configureFedExFeature(true, platformConfigured: false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertOk()
            ->assertSeeText('Connect FedEx credentials');
    }

    /**
     * @return array<string, mixed>
     */
    private function fedExWizardPayload(int $originLocationId): array
    {
        return [
            'origin_location_id' => $originLocationId,
            'display_name' => 'Main FedEx account',
            'provider_account_number' => self::TEST_ACCOUNT_NUMBER,
            'fedex_client_id' => self::TEST_CLIENT_ID,
            'fedex_client_secret' => self::TEST_CLIENT_SECRET,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
        ];
    }

    private function saveFedExAccountViaWizard(User $owner, Store $store, Location $location): CarrierAccount
    {
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.origin', 'fedex'), [
                'origin_location_id' => $location->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), $this->fedExWizardPayload($location->id))
            ->assertRedirect();

        return CarrierAccount::query()
            ->where('store_id', $store->id)
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->firstOrFail();
    }

    private function readyLocation(Store $store): Location
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
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = $this->merchant(Str::slug($name).'-owner@example.test');
        $store = $this->store($owner, $name);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        return [$owner, $store];
    }

    private function configureFedExFeature(bool $enabled, bool $platformConfigured): void
    {
        config([
            'carriers.fedex.enabled' => $enabled,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => $platformConfigured ? 'test-platform-client-id' : '',
            'carriers.fedex.sandbox.client_secret' => $platformConfigured ? 'test-platform-client-secret' : '',
            'carriers.fedex.live.base_url' => 'https://apis.fedex.com',
            'carriers.fedex.sandbox_allow_platform_fallback' => false,
            'carriers.fedex.model_b_developer_fallback_enabled' => true,
            'carriers.fedex.default_connection_model' => 'merchant_developer',
        ]);
    }

    private function fakeMerchantOAuthHappyPath(): void
    {
        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'merchant-test-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);
    }

    private function staffUser(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
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
}
