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

class Phase6FedExMerchantOwnedConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ACCOUNT_NUMBER = '510087240';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
        $this->configureFedExPlatform(true);
    }

    public function test_fedex_wizard_shows_merchant_owned_billing_and_label_copy(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Wizard Copy Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.index'))
            ->assertOk()
            ->assertSeeText('Connect a merchant-owned FedEx account for account setup and testing')
            ->assertSeeText('Labels and FedEx billing remain handled by the merchant');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', 'fedex'))
            ->assertOk()
            ->assertSeeText('Choose ship-from location');
    }

    public function test_fedex_wizard_details_step_shows_billing_and_label_deferral_copy(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Details Copy Store');
        $location = $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', [
                'carrier' => 'fedex',
                'step' => 'fedex_details',
                'origin_location_id' => $location->id,
            ]))
            ->assertOk()
            ->assertSeeText('FedEx billing stays between you and FedEx')
            ->assertSeeText('Labels are not enabled in this phase')
            ->assertSeeText('Test quotes are for setup verification only');
    }

    public function test_owner_can_save_merchant_owned_fedex_account_through_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Wizard Save Store');
        $location = $this->readyLocation($store);

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

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->firstOrFail();

        $this->assertSame(CarrierAccount::OWNERSHIP_MERCHANT_OWNED, $account->ownership_mode);
        $this->assertSame(CarrierAccount::CONNECTION_OWNER_MERCHANT, $account->connection_owner);
        $this->assertSame(CarrierAccount::BILLING_OWNER_MERCHANT, $account->billing_owner);
        $this->assertSame(CarrierAccount::CREDENTIALS_MERCHANT_ACCOUNT, $account->credentials_source);
        $this->assertNotSame(CarrierAccount::CREDENTIALS_PLATFORM_ENV, $account->credentials_source);
        $this->assertSame($location->id, $account->defaultOriginLocationId());
        $this->assertFalse($account->supportsLabels());
        $this->assertFalse($account->supportsPickup());
        $this->assertFalse($account->supportsTracking());
        $this->assertFalse($account->enabled_for_checkout);
    }

    public function test_fedex_account_number_is_masked_in_ui_and_not_fully_exposed(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Mask UI Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);

        $masked = '*****'.substr(self::TEST_ACCOUNT_NUMBER, -4);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSee('Account '.$masked, false)
            ->assertDontSee(self::TEST_ACCOUNT_NUMBER, false);

        $this->assertSame('Account '.$masked, CarrierAccountStatusPresenter::for($account)->maskedAccountNumberLabel());
    }

    public function test_carrier_api_events_redact_fedex_account_number_and_secrets(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Redaction Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'));

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

            $this->assertStringNotContainsString(self::TEST_ACCOUNT_NUMBER, (string) $encoded);
            $this->assertStringNotContainsString('test-fedex-client-secret', (string) $encoded);
            $this->assertStringNotContainsString('sandbox-child-password', (string) $encoded);
            $this->assertStringNotContainsString('fedex-test-access-token', (string) $encoded);
        }
    }

    public function test_fedex_blocked_validation_saves_account_and_returns_merchant_friendly_message(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Blocked Wizard Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);

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
                    'transactionId' => 'txn-registration-blocked-001',
                    'errors' => [['code' => 'ACCOUNT.VALIDATION', 'message' => 'Account validation failed']],
                ], 422);
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

        $account->refresh();
        $this->assertTrue($account->isBlockedByFedEx());
        $this->assertSame(CarrierAccount::OWNERSHIP_MERCHANT_OWNED, $account->ownership_mode);
        $this->assertFalse($account->supportsLabels());
    }

    public function test_store_a_cannot_use_store_b_origin_for_fedex_wizard_save(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('FedEx Origin Store A');
        [, $storeB] = $this->ownerStore('FedEx Origin Store B');
        $locationB = $this->readyLocation($storeB);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('shipping.carriers.connect.fedex.details'), $this->fedExWizardPayload($locationB->id))
            ->assertSessionHasErrors(['origin_location_id']);

        $this->assertSame(0, CarrierAccount::query()->where('store_id', $storeA->id)->where('provider', 'fedex')->count());
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

    public function test_staff_cannot_save_fedex_merchant_owned_account_through_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Staff Wizard Store');
        $staff = $this->staffUser('fedex-wizard-staff@example.test');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $location = $this->readyLocation($store);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), $this->fedExWizardPayload($location->id))
            ->assertForbidden();
    }

    public function test_shipping_page_shows_merchant_owned_fedex_card_without_api_jargon(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Shipping Card Store');
        $location = $this->readyLocation($store);
        $this->saveFedExAccountViaWizard($owner, $store, $location);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Merchant-owned account')
            ->assertSeeText('Billing handled by merchant')
            ->assertSeeText('USPS testing tools')
            ->assertDontSee('USPS public API', false)
            ->assertDontSee('client secret', false);
    }

    public function test_fedex_wizard_successful_connection_enables_rates_testing_only_not_checkout(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Connected Rates Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);
        $this->fakeFedExHappyPath();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'))
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->isConnected());
        $this->assertTrue($account->supportsRates());
        $this->assertFalse($account->enabled_for_checkout);
        $this->assertFalse($account->supportsLabels());

        $presenter = CarrierAccountStatusPresenter::for($account);
        $this->assertSame('Connected for testing', $presenter->connectionStatusLabel());
        $this->assertContains('Rates: testing only (not checkout)', $presenter->merchantCapabilityLabels());
    }

    public function test_fedex_details_form_uses_country_dropdown(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Country Dropdown Store');
        $location = $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', [
                'carrier' => 'fedex',
                'step' => 'fedex_details',
                'origin_location_id' => $location->id,
            ]))
            ->assertOk()
            ->assertSee('name="country_code"', false)
            ->assertSee('United States', false)
            ->assertDontSee('name="country_code" type="text"', false);
    }

    public function test_invalid_country_un_is_rejected_before_fedex_api_call(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Reject UN Store');
        $location = $this->readyLocation($store);

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), array_merge($this->fedExWizardPayload($location->id), [
                'country_code' => 'UN',
            ]))
            ->assertSessionHasErrors(['country_code']);

        Http::assertNothingSent();
        $this->assertSame(0, CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->count());
    }

    public function test_invalid_country_usa_is_rejected_before_fedex_api_call(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Reject USA Store');
        $location = $this->readyLocation($store);

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), array_merge($this->fedExWizardPayload($location->id), [
                'country_code' => 'USA',
            ]))
            ->assertSessionHasErrors(['country_code']);

        Http::assertNothingSent();
    }

    public function test_invalid_country_united_states_text_is_rejected_before_fedex_api_call(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Reject United States Store');
        $location = $this->readyLocation($store);

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), array_merge($this->fedExWizardPayload($location->id), [
                'country_code' => 'United States',
            ]))
            ->assertSessionHasErrors(['country_code']);

        Http::assertNothingSent();
    }

    public function test_invalid_state_code_is_rejected_before_fedex_api_call(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Reject State Store');
        $location = $this->readyLocation($store);

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), array_merge($this->fedExWizardPayload($location->id), [
                'state' => 'Texas',
            ]))
            ->assertSessionHasErrors(['state']);

        Http::assertNothingSent();
    }

    public function test_invalid_zip_is_rejected_before_fedex_api_call(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Reject ZIP Store');
        $location = $this->readyLocation($store);

        Http::fake();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), array_merge($this->fedExWizardPayload($location->id), [
                'postal_code' => 'ABCDE',
            ]))
            ->assertSessionHasErrors(['postal_code']);

        Http::assertNothingSent();
    }

    public function test_account_number_spaces_and_dashes_are_normalized(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Normalize Account Store');
        $location = $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.origin', 'fedex'), [
                'origin_location_id' => $location->id,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.fedex.details'), array_merge($this->fedExWizardPayload($location->id), [
                'provider_account_number' => '510-087-240',
            ]))
            ->assertRedirect();

        $account = CarrierAccount::query()->where('store_id', $store->id)->where('provider', 'fedex')->firstOrFail();
        $this->assertSame('510087240', $account->provider_account_number);
    }

    public function test_registration_payload_sends_country_code_us_not_un(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Payload Country Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);
        $capturedPayload = null;

        Http::fake(function ($request) use (&$capturedPayload) {
            $url = $request->url();

            if (str_contains($url, '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($url, '/registration/v2/address/keysgeneration')) {
                $capturedPayload = json_decode($request->body(), true);

                return Http::response(['transactionId' => 'txn-422', 'errors' => [['code' => 'INVALID.INPUT.EXCEPTION']]], 422);
            }

            return Http::response(['errors' => [['message' => 'Unexpected FedEx URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'));

        $this->assertSame('US', data_get($capturedPayload, 'address.countryCode'));
        $this->assertSame('TN', data_get($capturedPayload, 'address.stateOrProvinceCode'));
        $this->assertSame('38118', data_get($capturedPayload, 'address.postalCode'));
        $this->assertNotSame('UN', data_get($capturedPayload, 'address.countryCode'));
    }

    public function test_ship_from_origin_prefill_populates_fedex_address_fields(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Prefill Store');
        $location = $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipping.carriers.connect.show', [
                'carrier' => 'fedex',
                'step' => 'fedex_details',
                'origin_location_id' => $location->id,
                'prefill_from_origin' => 1,
            ]))
            ->assertOk()
            ->assertSee('Use selected ship-from location address', false)
            ->assertSee('100 Warehouse Rd', false)
            ->assertSee('Memphis', false)
            ->assertSee('38118', false);
    }

    public function test_local_registration_validation_failure_skips_fedex_registration_http(): void
    {
        [$owner, $store] = $this->ownerStore('FedEx Local Validation Store');
        $location = $this->readyLocation($store);
        $account = $this->saveFedExAccountViaWizard($owner, $store, $location);

        $settings = is_array($account->settings) ? $account->settings : [];
        $settings['registration']['country_code'] = 'UN';
        $account->forceFill(['settings' => $settings])->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/oauth/token')) {
                return Http::response([
                    'access_token' => 'fedex-test-access-token',
                    'token_type' => 'bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if (str_contains($request->url(), '/registration/v2/address/keysgeneration')) {
                throw new \RuntimeException('FedEx registration should not be called when local validation fails.');
            }

            return Http::response(['errors' => [['message' => 'Unexpected FedEx URL']]], 500);
        });

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shipping.carriers.connect.test', 'fedex'), [
                'carrier_account_id' => $account->id,
            ])
            ->assertRedirect(route('shippingAutomation'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/oauth/token');
        });

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/registration/v2/address/keysgeneration');
        });
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

    private function staffUser(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
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
            'carriers.fedex.account_registration_residential_mode' => 'omit',
            'carriers.fedex.sandbox_allow_platform_fallback' => false,
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
