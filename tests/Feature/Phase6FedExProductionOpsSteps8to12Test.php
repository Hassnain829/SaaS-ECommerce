<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\FedExTradeDocument;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Support\OrderLifecycle;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6FedExProductionOpsSteps8to12Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
        Cache::flush();
        config([
            'carriers.fedex.enabled' => true,
            'carriers.fedex.integrator_model_a_enabled' => true,
            'carriers.fedex.integrator_production_enabled' => false,
            'carriers.fedex.ops_address_validation_enabled' => true,
            'carriers.fedex.ops_service_availability_enabled' => true,
            'carriers.fedex.ops_negotiated_rates_enabled' => true,
            'carriers.fedex.checkout_rates_enabled' => false,
            'carriers.fedex.ops_ship_labels_enabled' => true,
            'carriers.fedex.ops_tracking_enabled' => false,
            'carriers.fedex.sandbox.client_id' => 'parent-client-id-1234567890',
            'carriers.fedex.sandbox.client_secret' => 'parent-client-secret',
            'carriers.fedex.sandbox.base_url' => 'https://apis-sandbox.fedex.com',
        ]);
    }

    public function test_manage_page_persists_account_capability_switches(): void
    {
        [$owner, $store, $account] = $this->modelAFixture('Caps Toggle Store');
        config([
            'carriers.fedex.checkout_rates_enabled' => true,
            'carriers.fedex.ops_ship_labels_enabled' => true,
            'carriers.fedex.ops_tracking_enabled' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.fedex-integrator.capabilities', $account), [
                'enabled_for_checkout' => '1',
                'checkout_rates' => '1',
                'labels' => '1',
                'tracking' => '1',
            ])
            ->assertRedirect(route('settings.shipping.fedex-integrator.manage', $account));

        $account->refresh();
        $this->assertTrue((bool) $account->enabled_for_checkout);
        $this->assertTrue((bool) data_get($account->capabilities, 'checkout_rates'));
        $this->assertTrue((bool) data_get($account->capabilities, 'labels'));
        $this->assertTrue((bool) data_get($account->capabilities, 'tracking'));
    }

    public function test_capability_post_rejects_when_global_flags_off(): void
    {
        [$owner, $store, $account] = $this->modelAFixture('Caps Reject Store');
        config([
            'carriers.fedex.checkout_rates_enabled' => false,
            'carriers.fedex.ops_ship_labels_enabled' => false,
            'carriers.fedex.ops_tracking_enabled' => false,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('settings.shipping.fedex-integrator.manage', $account))
            ->post(route('settings.shipping.fedex-integrator.capabilities', $account), [
                'enabled_for_checkout' => '1',
                'checkout_rates' => '1',
                'labels' => '1',
                'tracking' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $account->refresh();
        $this->assertFalse((bool) $account->enabled_for_checkout);
        $this->assertFalse((bool) data_get($account->capabilities, 'checkout_rates'));
        $this->assertFalse((bool) data_get($account->capabilities, 'labels'));
        $this->assertFalse((bool) data_get($account->capabilities, 'tracking'));
    }

    public function test_manage_page_never_exposes_credentials_or_full_account_number(): void
    {
        [$owner, $store, $account] = $this->modelAFixture('Manage Secrets Store');
        $account->setCredentials([
            'customer_key' => 'child-secret-key-should-hide',
            'customer_password' => 'child-secret-password-should-hide',
        ]);
        $account->setFedExAccountNumber('700257037');
        $account->save();

        $html = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.shipping.fedex-integrator.manage', $account))
            ->assertOk()
            ->assertSeeText('Account switches')
            ->assertSeeText('Capabilities')
            ->getContent();

        $this->assertStringNotContainsString('child-secret-key-should-hide', $html);
        $this->assertStringNotContainsString('child-secret-password-should-hide', $html);
        $this->assertStringNotContainsString('700257037', $html);
    }

    public function test_order_page_shows_fedex_wizard_and_trade_document_panel(): void
    {
        [$owner, $store, $account, $location] = $this->modelAFixture('Order Wizard Store');
        $order = $this->orderWithShippingAddress($store);
        FedExTradeDocument::query()->create([
            'store_id' => $store->id,
            'order_id' => $order->id,
            'carrier_account_id' => $account->id,
            'document_type' => 'commercial_invoice',
            'status' => FedExTradeDocument::STATUS_UPLOADED,
            'fedex_document_id' => 'DOC-ABCDEF123456',
            'origin_country_code' => 'US',
            'destination_country_code' => 'CA',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('FedEx shipping checks')
            ->assertSeeText('1. Validate address')
            ->assertSeeText('3. Negotiated rates')
            ->assertSeeText('4. Create FedEx label')
            ->assertSeeText('Customs / trade document')
            ->assertSeeText('Trade documents and API status')
            ->assertSeeText('Commercial Invoice')
            ->assertDontSee('child-key-a', false)
            ->assertDontSeeText('700257037');
    }

    public function test_admin_fedex_diagnostics_is_admin_only_and_safe(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);
        $admin = User::factory()->create([
            'email' => 'admin-fedex-ops@example.test',
            'role_id' => $adminRole->id,
        ]);
        $merchant = User::factory()->create([
            'email' => 'merchant-fedex-ops@example.test',
            'role_id' => $userRole->id,
        ]);

        $this->actingAs($merchant)
            ->get(route('admin.fedex.diagnostics'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.fedex.diagnostics'))
            ->assertOk()
            ->assertSeeText('FedEx operations diagnostics')
            ->assertSeeText('Platform flags')
            ->assertSeeText('Uncertain / processing ship operations')
            ->assertSeeText('ETD documents')
            ->assertDontSee('customer_password', false)
            ->assertDontSee('client_secret', false);
    }

    public function test_production_ops_flags_default_off_in_env_example(): void
    {
        $example = file_get_contents(base_path('.env.example'));
        $this->assertIsString($example);
        $this->assertStringContainsString('FEDEX_CHECKOUT_RATES_ENABLED=false', $example);
        $this->assertStringContainsString('FEDEX_OPS_SHIP_LABELS_ENABLED=false', $example);
        $this->assertStringContainsString('FEDEX_OPS_TRACKING_ENABLED=false', $example);
        $this->assertStringContainsString('FEDEX_INTEGRATOR_PRODUCTION_ENABLED=false', $example);
    }

    /**
     * @return array{0: User, 1: Store, 2: CarrierAccount, 3: Location}
     */
    private function modelAFixture(string $name): array
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
        $location = Location::query()->create([
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '90 FedEx Pkwy',
            'city' => 'Collierville',
            'state' => 'TN',
            'postal_code' => '38017',
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
            'display_name' => 'Model A FedEx',
            'provider_account_number' => '700257037',
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
            'default_origin_location_id' => $location->id,
            'fedex_active_store_key' => CarrierAccount::fedExActiveStoreKeyFor(
                (int) $store->id,
                CarrierAccount::ENVIRONMENT_SANDBOX,
            ),
            'enabled_for_checkout' => false,
            'settings' => ['default_origin_location_id' => $location->id],
        ], CarrierAccount::ownershipAttributesForFedExIntegratorProvider()));

        $account->setCredentials([
            'customer_key' => 'child-key-a',
            'customer_password' => 'child-secret-a',
        ]);
        $account->setFedExAccountNumber('700257037');
        $account->save();

        return [$owner, $store, $account->fresh(), $location];
    }

    private function orderWithShippingAddress(Store $store): Order
    {
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'currency_code' => 'USD',
            'subtotal' => 20,
            'shipping' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 20,
            'grand_total' => 20,
        ]);

        OrderAddress::query()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'name' => 'Test Buyer',
            'address_line1' => '123 Main St',
            'city' => 'Memphis',
            'state' => 'TN',
            'province_code' => 'TN',
            'postal_code' => '38116',
            'country_code' => 'US',
            'country' => 'United States',
        ]);

        return $order->fresh(['addresses', 'shipments']);
    }
}
