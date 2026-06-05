<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\Location;
use App\Models\Order;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\FedExConfig;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExSandboxCarrierFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExPlatform(true);
    }

    public function test_owner_can_create_fedex_sandbox_carrier_account(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Owner Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), $this->fedExPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->first();

        $this->assertNotNull($account);
        $this->assertSame('FedEx sandbox account', $account->display_name);
        $this->assertSame(CarrierAccount::CONNECTION_SETUP_REQUIRED, $account->connection_status);
        $this->assertSame('510087240', $account->provider_account_number);
    }

    public function test_staff_cannot_create_fedex_carrier_account(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Staff Block Store');
        $staff = $this->merchant('fedex-staff@example.test');
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), $this->fedExPayload())
            ->assertForbidden();

        $this->assertSame(0, CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->count());
    }

    public function test_cross_store_default_origin_location_is_rejected(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('FedEx Store A Origin');
        [, $storeB] = $this->ownerStore('FedEx Store B Origin');
        $otherLocation = Location::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Other warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '200 Other St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '73301',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), array_merge($this->fedExPayload(), [
                'default_origin_location_id' => $otherLocation->id,
            ]))
            ->assertSessionHasErrors(['default_origin_location_id']);

        $this->assertSame(0, CarrierAccount::query()->where('store_id', $storeA->id)->where('provider', 'fedex')->count());
    }

    public function test_cross_store_carrier_account_test_connection_is_blocked(): void
    {
        [$ownerB, $storeB] = $this->ownerStore('FedEx Cross Test B');
        [, $storeA] = $this->ownerStore('FedEx Cross Test A');
        $this->attach($storeA, $ownerB, Store::ROLE_MANAGER);
        $account = $this->createFedExAccount($storeB);

        $this->actingAs($ownerB)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertForbidden();
    }

    public function test_fedex_credentials_are_encrypted_and_not_exposed(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Encrypt Store');
        $account = $this->createFedExAccount($store);

        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $array = $account->toArray();

        $this->assertArrayNotHasKey('credentials_encrypted', $array);
        $this->assertTrue($account->hasMerchantCredentials());
        $this->assertNotSame('sandbox-child-key', (string) ($array['customer_password'] ?? ''));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertDontSee('sandbox-child-key')
            ->assertDontSee('sandbox-child-password');
    }

    public function test_missing_platform_fedex_config_returns_friendly_message(): void
    {
        $this->configureFedExPlatform(false);
        [$owner, $store] = $this->ownerStore('FedEx Missing Config Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), $this->fedExPayload())
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('FedEx sandbox connection is not available on this platform environment yet');
    }

    public function test_fedex_account_registration_success_stores_encrypted_child_credentials(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Registration Store');
        $account = $this->createFedExAccount($store);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $credentials = $account->credentials();

        $this->assertSame('sandbox-child-key', $credentials['customer_key']);
        $this->assertSame('sandbox-child-password', $credentials['customer_password']);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'provider' => 'fedex',
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_fedex_oauth_token_success_marks_account_connected(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx OAuth Success Store');
        $account = $this->createFedExAccount($store, withCredentials: true);
        $this->fakeFedExOAuthOnly();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->isConnected());
        $this->assertNotNull($account->last_verified_at);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_MERCHANT_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_fedex_oauth_failure_marks_account_failed_without_corrupting_order_state(): void
    {
        [$owner, $store, $order] = $this->orderFixture('FedEx Failure Store');
        $account = $this->createFedExAccount($store, withCredentials: true);

        Http::fake(function ($request) {
            if (! str_contains($request->url(), '/oauth/token')) {
                return Http::response(['errors' => [['message' => 'Unexpected URL']]], 500);
            }

            if (str_contains((string) $request->body(), 'child_key')) {
                return Http::response([
                    'errors' => [['code' => 'AUTH.FAILED', 'message' => 'Invalid credentials']],
                ], 401);
            }

            return Http::response([
                'access_token' => 'fedex-test-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex']);

        $account->refresh();
        $order->refresh();

        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $account->connection_status);
        $this->assertSame(Order::query()->find($order->id)?->fulfillment_status, $order->fulfillment_status);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());
    }

    public function test_carrier_api_events_mask_sensitive_request_and_response_data(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Event Mask Store');
        $account = $this->createFedExAccount($store);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect();

        $event = CarrierApiEvent::query()
            ->where('store_id', $store->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('/registration/v2/address/keysgeneration', data_get($event->request_summary, 'endpoint'));
        $this->assertSame(200, data_get($event->response_summary, 'http_status'));
        $this->assertStringNotContainsString('sandbox-child-password', json_encode($event->request_summary));
        $this->assertStringNotContainsString('sandbox-child-key', json_encode($event->response_summary));
        $this->assertStringNotContainsString('/irc/v2/customerkeys', json_encode($event->request_summary));
        $this->assertStringContainsString('*', (string) data_get($event->request_summary, 'account_number'));
    }

    public function test_default_registration_endpoint_is_v2_not_deprecated_customerkeys(): void
    {
        $path = app(FedExConfig::class)->accountRegistrationPath(CarrierAccount::ENVIRONMENT_SANDBOX);

        $this->assertSame('/registration/v2/address/keysgeneration', $path);
        $this->assertStringNotContainsString('/irc/v2/customerkeys', $path);
        $this->assertStringNotContainsString('/registration/v1/', $path);
        $this->assertFalse(app(FedExConfig::class)->isDeprecatedRegistrationPath($path));
        $this->assertTrue(app(FedExConfig::class)->isDeprecatedRegistrationPath('/irc/v2/customerkeys'));
    }

    public function test_platform_oauth_success_is_logged_separately_from_registration(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Platform OAuth Log Store');
        $account = $this->createFedExAccount($store);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_PLATFORM_OAUTH_TOKEN,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
        $this->assertDatabaseHas('carrier_api_events', [
            'store_id' => $store->id,
            'carrier_account_id' => $account->id,
            'action' => CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION,
            'status' => CarrierApiEvent::STATUS_SUCCEEDED,
        ]);
    }

    public function test_registration_failure_after_platform_oauth_marks_failed_not_connected(): void
    {
        [$owner, $store, $order] = $this->orderFixture('FedEx Registration Fail Store');
        $account = $this->createFedExAccount($store);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'txn-registration-failed-001',
                    'errors' => [[
                        'code' => 'NOT.FOUND.ERROR',
                        'message' => 'The resource you requested is no longer available. Please modify your request and try again.',
                    ]],
                ], 404);
            }

            return Http::response(['errors' => [['message' => 'Unexpected FedEx URL in test']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.test', $account))
            ->assertRedirect()
            ->assertSessionHasErrors(['fedex'])
            ->assertSessionHas('fedex_connection_message', 'FedEx platform credentials are valid, but account registration failed.');

        $account->refresh();
        $order->refresh();

        $this->assertFalse($account->isConnected());
        $this->assertSame(CarrierAccount::CONNECTION_FAILED, $account->connection_status);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());

        $registrationEvent = CarrierApiEvent::query()
            ->where('carrier_account_id', $account->id)
            ->where('action', CarrierApiEvent::ACTION_ACCOUNT_REGISTRATION)
            ->first();

        $this->assertNotNull($registrationEvent);
        $this->assertSame(CarrierApiEvent::STATUS_FAILED, $registrationEvent->status);
        $this->assertSame('/registration/v2/address/keysgeneration', data_get($registrationEvent->request_summary, 'endpoint'));
        $this->assertSame(404, data_get($registrationEvent->response_summary, 'http_status'));
        $this->assertStringNotContainsString('fedex-test-access-token', json_encode($registrationEvent->response_summary));
    }

    public function test_ui_does_not_show_fake_label_live_rate_or_pickup_buttons(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('FedEx sandbox')
            ->assertSeeText('Save FedEx sandbox account')
            ->assertDontSeeText('Buy label')
            ->assertDontSeeText('Generate label')
            ->assertDontSeeText('Live rates')
            ->assertDontSeeText('Pickup scheduling')
            ->assertDontSeeText('Production enabled');
    }

    public function test_live_environment_cannot_be_enabled_in_this_phase(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Live Block Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.fedex.store'), array_merge($this->fedExPayload(), [
                'environment' => 'live',
            ]))
            ->assertSessionHasErrors(['environment']);
    }

    public function test_manual_carrier_accounts_still_work(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Manual Still Works');
        $manual = Carrier::query()->where('code', 'manual-delivery')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.carrier-accounts.store'), [
                'carrier_id' => $manual->id,
                'display_name' => 'Main manual delivery',
                'connection_type' => CarrierAccount::CONNECTION_MANUAL,
                'status' => CarrierAccount::STATUS_ENABLED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('carrier_accounts', [
            'store_id' => $store->id,
            'display_name' => 'Main manual delivery',
            'provider' => CarrierAccount::PROVIDER_MANUAL,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_MANUAL,
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

    /**
     * @return array{0: User, 1: Store, 2: Order}
     */
    private function orderFixture(string $storeName): array
    {
        [$owner, $store] = $this->ownerStore($storeName);
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#FX'.fake()->unique()->numberBetween(1000, 9999),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'customer_email' => 'buyer@example.test',
            'subtotal' => 20,
            'total' => 20,
            'grand_total' => 20,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);

        return [$owner, $store, $order];
    }

    private function createFedExAccount(Store $store, bool $withCredentials = false): CarrierAccount
    {
        $fedEx = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $account = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => 'FedEx sandbox account',
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'provider_account_number' => '510087240',
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'settings' => [
                'registration' => [
                    'company_name' => 'Acme Test Co',
                    'contact_name' => 'Jane Merchant',
                    'address_line1' => '100 Test Lane',
                    'city' => 'Memphis',
                    'state' => 'TN',
                    'postal_code' => '38118',
                    'country_code' => 'US',
                    'phone' => '+19015550100',
                    'email' => 'fedex.test@example.test',
                    'provider_account_number' => '510087240',
                ],
            ],
            'enabled_for_checkout' => false,
        ]);

        if ($withCredentials) {
            $account->setCredentials([
                'customer_key' => 'sandbox-child-key',
                'customer_password' => 'sandbox-child-password',
            ]);
            $account->save();
        }

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    private function fedExPayload(): array
    {
        return [
            'display_name' => 'FedEx sandbox account',
            'environment' => 'sandbox',
            'provider_account_number' => '510087240',
            'company_name' => 'Acme Test Co',
            'contact_name' => 'Jane Merchant',
            'address_line1' => '100 Test Lane',
            'city' => 'Memphis',
            'state' => 'TN',
            'postal_code' => '38118',
            'country_code' => 'US',
            'phone' => '+19015550100',
            'email' => 'fedex.test@example.test',
        ];
    }

    private function configureFedExPlatform(bool $configured): void
    {
        config([
            'carriers.fedex.enabled' => $configured,
            'carriers.fedex.environment' => 'sandbox',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
            'carriers.fedex.sandbox.client_id' => $configured ? 'test-fedex-client-id' : '',
            'carriers.fedex.sandbox.client_secret' => $configured ? 'test-fedex-client-secret' : '',
            'carriers.fedex.sandbox.account_registration_path' => '/registration/v2/address/keysgeneration',
        ]);
    }

    private function fakeFedExHappyPath(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/registration/v2/address/keysgeneration')) {
                return Http::response([
                    'transactionId' => 'txn-registration-success-001',
                    'output' => [
                        'child_Key' => 'sandbox-child-key',
                        'child_secret' => 'sandbox-child-password',
                    ],
                ], 200);
            }

            return Http::response(['errors' => [['message' => 'Unexpected FedEx URL in test: '.$url]]], 500);
        });
    }

    private function fakeFedExOAuthOnly(): void
    {
        Http::fake([
            'https://apis-sandbox.fedex.com/oauth/token' => Http::response([
                'access_token' => 'fedex-test-access-token',
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
