<?php

namespace Tests\Feature;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Merchant-owned FedEx wizard integration tests (credentials mode).
 *
 * @see Phase6FedExMerchantCredentialsModeTest for detailed credentials-mode coverage
 */
class Phase6FedExMerchantOwnedConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ACCOUNT_NUMBER = '510087240';

    private const TEST_CLIENT_ID = 'l7a1b2c3d4e5f678901234567890abcd';

    private const TEST_CLIENT_SECRET = 'test-merchant-fedex-secret-key-value';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExFeature(true);
    }

    public function test_fedex_wizard_shows_merchant_owned_billing_and_label_copy(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Wizard Copy Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertOk()
            ->assertSeeText('Connect your own FedEx Developer credentials')
            ->assertSeeText('Labels and checkout live rates are not enabled');
    }

    public function test_owner_can_save_merchant_owned_fedex_account_through_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Wizard Save Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);

        $this->assertSame(CarrierAccount::OWNERSHIP_MERCHANT_OWNED, $account->ownership_mode);
        $this->assertSame(CarrierAccount::CREDENTIALS_MERCHANT_ENCRYPTED, $account->credentials_source);
        $this->assertSame(CarrierAccount::BILLING_OWNER_MERCHANT, $account->billing_owner);
        $this->assertSame($location->id, $account->defaultOriginLocationId());
        $this->assertFalse($account->supportsLabels());
        $this->assertFalse($account->enabled_for_checkout);
    }

    public function test_fedex_wizard_successful_connection_does_not_enable_checkout_or_labels(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Connected Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);
        $this->fakeMerchantOAuthHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->isConnected());
        $this->assertFalse($account->supportsRates());
        $this->assertFalse($account->supportsLabels());
        $this->assertFalse($account->enabled_for_checkout);
    }

    public function test_store_a_cannot_test_store_b_fedex_account_through_wizard(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('FedEx Cross Wizard A');
        [$ownerB, $storeB] = $this->ownerStore('FedEx Cross Wizard B');
        $location = $this->readyLocation($storeB);
        $account = $this->saveFedExAccountViaWizard($ownerB, $storeB, $location);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertSessionHasErrors(['carrier_account_id']);
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

    private function configureFedExFeature(bool $enabled): void
    {
        config([
            'carriers.fedex.enabled' => $enabled,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => 'test-platform-client-id',
            'carriers.fedex.sandbox.client_secret' => 'test-platform-client-secret',
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
