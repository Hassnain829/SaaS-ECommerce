<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CheckoutMode;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Shipping\DeliveryOptionService;
use App\Services\Tax\TaxConfigurationService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryUxBatch3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
    }

    public function test_delivery_setup_wizard_ship_from_page_renders(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Wizard Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.ship-from'))
            ->assertOk()
            ->assertSeeText('Where do you ship from?')
            ->assertSeeText('Save and continue');
    }

    public function test_delivery_setup_wizard_deliver_to_page_renders(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Deliver To Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.deliver-to'))
            ->assertOk()
            ->assertSeeText('Where do you deliver?')
            ->assertSeeText('Save and continue');
    }

    public function test_wizard_ship_from_step_persists_location_and_advances(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Persist Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.ship-from'), [
                'name' => 'Main warehouse',
                'type' => 'warehouse',
                'address_line1' => '100 Main St',
                'city' => 'Dallas',
                'state' => 'TX',
                'postal_code' => '75002',
                'country_code' => 'US',
                'fulfills_online_orders' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.deliver-to'));

        $this->assertDatabaseHas('locations', [
            'store_id' => $store->id,
            'name' => 'Main warehouse',
            'city' => 'Dallas',
            'country_code' => 'US',
        ]);
    }

    public function test_full_wizard_flow_persists_records_and_review_shows_saved_values(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Full Flow Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.ship-from'), [
                'name' => 'Chicago warehouse',
                'type' => 'warehouse',
                'address_line1' => '200 Lake St',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country_code' => 'US',
                'fulfills_online_orders' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.deliver-to'));

        $this->actingAs($owner)
            ->post(route('settings.delivery.setup.deliver-to'), [
                'name' => 'Illinois delivery',
                'zone_editor_mode' => 'simple',
                'country_code' => 'US',
                'region_codes' => ['IL'],
                'postal_rules_json' => json_encode([['type' => 'prefix', 'value' => '606']]),
                'is_active' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.delivery-option'));

        $zone = ShippingZone::query()->where('store_id', $store->id)->where('name', 'Illinois delivery')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('settings.delivery.setup.delivery-option'), [
                'shipping_zone_id' => $zone->id,
                'name' => 'Standard delivery',
                'delivery_speed_label' => '3-5 business days',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '6.50',
                'available_to_customers' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.review'));

        $this->actingAs($owner)
            ->get(route('settings.delivery.setup.review'))
            ->assertOk()
            ->assertSeeText('Chicago warehouse')
            ->assertSeeText('Illinois delivery')
            ->assertSeeText('Standard delivery')
            ->assertSeeText('Checkout tax (read-only)');

        $this->actingAs($owner)
            ->post(route('settings.delivery.setup.finish'))
            ->assertRedirect(route('shippingAutomation'));
    }

    public function test_wizard_blocks_legacy_multi_country_zone_updates(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Legacy Zone Store');
        $legacyZone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'North America',
            'countries' => ['US', 'CA'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.deliver-to'), [
                'shipping_zone_id' => $legacyZone->id,
                'name' => 'North America',
                'zone_editor_mode' => 'simple',
                'country_code' => 'US',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('shipping_zone_id');

        $legacyZone->refresh();
        $this->assertSame(['US', 'CA'], $legacyZone->countries);
    }

    public function test_wizard_preserves_non_manual_carrier_on_method_update(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Carrier Preserve Store');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $fedExCarrier = Carrier::query()->where('code', 'fedex')->firstOrFail();

        $fedExAccount = CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedExCarrier->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx account',
            'ownership_mode' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
            'credentials_source' => CarrierAccount::CREDENTIALS_MERCHANT_ENCRYPTED,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_MERCHANT_CREDENTIALS,
            'billing_owner' => CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => CarrierAccount::CONNECTION_CONNECTED,
            'status' => CarrierAccount::STATUS_ENABLED,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $fedExAccount->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 12,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.delivery-option'), [
                'shipping_method_id' => $method->id,
                'shipping_zone_id' => $zone->id,
                'name' => 'FedEx Ground (updated label)',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '12.00',
                'available_to_customers' => '1',
            ])
            ->assertRedirect(route('settings.delivery.setup.review'));

        $method->refresh();
        $this->assertSame($fedExAccount->id, $method->carrier_account_id);
        $this->assertSame('FedEx Ground (updated label)', $method->name);
    }

    public function test_wizard_rejects_cross_store_zone_reference(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Batch3 Store A');
        [, $storeB] = $this->ownerStore('Batch3 Store B');
        $otherZone = ShippingZone::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Other store zone',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('settings.delivery.setup.delivery-option'), [
                'shipping_zone_id' => $otherZone->id,
                'name' => 'Invalid option',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '5.00',
                'available_to_customers' => '1',
            ])
            ->assertSessionHasErrors('shipping_zone_id');
    }

    public function test_staff_can_view_test_address_but_cannot_run_wizard_writes(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Staff Store');
        $staff = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user']);
        $staff->update(['role_id' => $role->id]);
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.test-address'))
            ->assertOk()
            ->assertSeeText('Test a customer address');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.ship-from'), [
                'name' => 'Blocked',
                'type' => 'warehouse',
                'address_line1' => '1 Main',
                'city' => 'Austin',
                'country_code' => 'US',
            ])
            ->assertForbidden();

        $this->assertSame(0, Location::query()->where('store_id', $store->id)->where('name', 'Blocked')->count());
    }

    public function test_wizard_finish_does_not_mutate_tax_settings(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Tax Store');
        $taxSetting = app(TaxConfigurationService::class)->ensureSettingsForStore($store);
        $taxSetting->update(['enabled' => false]);
        $updatedAt = $taxSetting->fresh()->updated_at;

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.finish'))
            ->assertRedirect(route('settings.delivery.setup.review'))
            ->assertSessionHasErrors('delivery_setup');

        $taxSetting->refresh();
        $this->assertSame($updatedAt?->toDateTimeString(), $taxSetting->updated_at?->toDateTimeString());
    }

    public function test_delivery_hub_get_is_read_only(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Readonly Hub Store');
        $locationCount = Location::query()->where('store_id', $store->id)->count();
        $taxCount = \App\Models\TaxSetting::query()->where('store_id', $store->id)->count();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk();

        $this->assertSame($locationCount, Location::query()->where('store_id', $store->id)->count());
        $this->assertSame($taxCount, \App\Models\TaxSetting::query()->where('store_id', $store->id)->count());
    }

    public function test_wizard_created_delivery_option_is_checkout_usable(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Checkout E2E Store');

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.ship-from'), [
                'name' => 'E2E warehouse',
                'type' => 'warehouse',
                'address_line1' => '500 Commerce St',
                'city' => 'Dallas',
                'state' => 'TX',
                'postal_code' => '75002',
                'country_code' => 'US',
                'fulfills_online_orders' => '1',
            ]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.deliver-to'), [
                'name' => 'Texas delivery',
                'zone_editor_mode' => 'simple',
                'country_code' => 'US',
                'region_codes' => ['TX'],
                'is_active' => '1',
            ]);

        $zone = ShippingZone::query()->where('store_id', $store->id)->where('name', 'Texas delivery')->firstOrFail();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.delivery-option'), [
                'shipping_zone_id' => $zone->id,
                'name' => 'Wizard Standard',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '8.00',
                'available_to_customers' => '1',
            ]);

        $method = ShippingMethod::query()->where('store_id', $store->id)->where('name', 'Wizard Standard')->firstOrFail();

        $options = app(DeliveryOptionService::class)->optionsFor(
            $store,
            ['country_code' => 'US', 'state' => 'TX', 'postal_code' => '75002'],
            25.0,
            'USD',
        );

        $this->assertNotEmpty($options);
        $this->assertTrue(collect($options)->contains(fn (array $option): bool => ($option['shipping_method_id'] ?? null) === $method->id));
    }

    public function test_test_address_tool_is_read_only(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Diagnostic Store');
        $zoneCountBefore = ShippingZone::query()->where('store_id', $store->id)->count();
        $methodCountBefore = ShippingMethod::query()->where('store_id', $store->id)->count();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.test-address'), [
                'country_code' => 'US',
                'region_code' => 'TX',
                'postal_code' => '75002',
                'order_subtotal' => '25',
            ])
            ->assertOk()
            ->assertSeeText('Results');

        $this->assertSame($zoneCountBefore, ShippingZone::query()->where('store_id', $store->id)->count());
        $this->assertSame($methodCountBefore, ShippingMethod::query()->where('store_id', $store->id)->count());
    }

    public function test_delivery_hub_shows_setup_and_test_address_links(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Hub Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Set up delivery')
            ->assertSeeText('Test a customer address')
            ->assertSeeText('Connect delivery provider')
            ->assertSee(route('shipping.carriers.connect.index'), false);
    }

    public function test_wizard_created_delivery_option_appears_in_storefront_delivery_options_api(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 Storefront API Store');
        $store->forceFill([
            'settings' => ['checkout_mode' => CheckoutMode::PLATFORM],
            'developer_storefront_token_hash' => hash('sha256', 'wizard-e2e-token'),
            'developer_storefront_token_created_at' => now(),
        ])->save();

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Wizard API Product',
            'slug' => 'wizard-api-product-'.Str::random(6),
            'base_price' => 15,
            'sku' => 'WIZ-'.Str::random(4),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-V',
            'price' => 15,
            'stock' => 10,
        ]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.ship-from'), [
                'name' => 'API warehouse',
                'type' => 'warehouse',
                'address_line1' => '500 Commerce St',
                'city' => 'Dallas',
                'state' => 'TX',
                'postal_code' => '75002',
                'country_code' => 'US',
                'fulfills_online_orders' => '1',
            ]);

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.deliver-to'), [
                'name' => 'Texas API delivery',
                'zone_editor_mode' => 'simple',
                'country_code' => 'US',
                'region_codes' => ['TX'],
                'is_active' => '1',
            ]);

        $zone = ShippingZone::query()->where('store_id', $store->id)->where('name', 'Texas API delivery')->firstOrFail();

        $this->actingAs($owner)->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.delivery-option'), [
                'shipping_zone_id' => $zone->id,
                'name' => 'Wizard API Standard',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '9.50',
                'available_to_customers' => '1',
            ]);

        $method = ShippingMethod::query()->where('store_id', $store->id)->where('name', 'Wizard API Standard')->firstOrFail();

        $this->withToken('wizard-e2e-token')
            ->postJson('/api/v1/checkout', [
                'source_channel' => 'dev_storefront',
                'currency_code' => 'USD',
                'customer' => [
                    'full_name' => 'API Buyer',
                    'email' => 'api.buyer@example.test',
                ],
                'shipping_address' => [
                    'address_line1' => '100 Test St',
                    'city' => 'Dallas',
                    'state' => 'TX',
                    'postal_code' => '75002',
                    'country' => 'US',
                ],
                'billing_address' => ['same_as_shipping' => true],
                'items' => [
                    ['variant_id' => $variant->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $checkout = \App\Models\Checkout::query()->where('store_id', $store->id)->firstOrFail();

        $this->withToken('wizard-e2e-token')
            ->postJson('/api/v1/checkout/'.$checkout->id.'/delivery-options', [
                'shipping_address' => [
                    'country' => 'US',
                    'state' => 'TX',
                    'postal_code' => '75002',
                ],
            ])
            ->assertOk()
            ->assertJsonFragment([
                'shipping_method_id' => $method->id,
                'name' => 'Wizard API Standard',
            ]);
    }

    public function test_wizard_layout_exposes_progress_navigation_for_accessibility(): void
    {
        [$owner, $store] = $this->ownerStore('Batch3 A11y Store');

        $html = (string) $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.ship-from'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="Delivery setup progress"', $html);
        $this->assertStringContainsString('aria-current="step"', $html);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner->update(['role_id' => $role->id]);

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

        return [$owner, $store];
    }
}
