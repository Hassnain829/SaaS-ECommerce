<?php

namespace Tests\Feature;

use App\Models\CarrierAccount;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryUxBatch2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CarrierSeeder::class);
    }

    public function test_structured_zone_store_persists_one_country_and_prefix_postal_rule(): void
    {
        [$owner, $store] = $this->ownerStore('Batch2 Zone Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.zones.store'), [
                'name' => 'United States',
                'zone_editor_mode' => 'simple',
                'country_code' => 'US',
                'region_codes' => ['TX'],
                'postal_rules_json' => json_encode([
                    ['type' => 'prefix', 'value' => '606'],
                ]),
                'is_active' => '1',
            ])
            ->assertRedirect();

        $zone = ShippingZone::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(['US'], $zone->countries);
        $this->assertSame(['TX'], $zone->regions);
        $this->assertSame(['606*'], $zone->postal_patterns);
    }

    public function test_simple_method_store_reuses_manual_provider_and_sets_availability_flags(): void
    {
        [$owner, $store] = $this->ownerStore('Batch2 Method Store');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.methods.store'), [
                'shipping_zone_id' => $zone->id,
                'name' => 'Standard delivery',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '5.00',
                'available_to_customers' => '1',
            ])
            ->assertRedirect();

        $method = ShippingMethod::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertTrue($method->is_active);
        $this->assertTrue($method->enabled_for_checkout);
        $this->assertSame(ShippingMethod::RATE_FLAT, $method->rate_type);
        $this->assertNotNull($method->carrier_account_id);

        $account = CarrierAccount::query()->findOrFail($method->carrier_account_id);
        $this->assertSame(CarrierAccount::PROVIDER_MANUAL, $account->provider);
        $this->assertSame($store->id, $account->store_id);
    }

    public function test_method_update_does_not_silently_fix_mismatched_visibility_flags(): void
    {
        [$owner, $store] = $this->ownerStore('Batch2 Flag Store');
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Hidden active option',
            'code' => 'hidden-active',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'is_active' => true,
            'enabled_for_checkout' => false,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.shipping.methods.update', $method), [
                'shipping_zone_id' => $zone->id,
                'name' => 'Hidden active option',
                'delivery_price_mode' => 'fixed',
                'flat_rate' => '5.00',
                'enabled_for_checkout' => '0',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $method->refresh();
        $this->assertTrue($method->is_active);
        $this->assertFalse($method->enabled_for_checkout);
    }

    public function test_delivery_page_renders_structured_zone_drawer_fields(): void
    {
        [$owner, $store] = $this->ownerStore('Batch2 UI Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation', ['tab' => 'advanced']))
            ->assertOk()
            ->assertSee('id="zone-field-country"', false)
            ->assertSee('Exact postal code', false)
            ->assertSee('delivery_price_mode', false)
            ->assertSee('data-zone-form', false);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = $this->merchant(Str::slug($name).'-owner@example.test');
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

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create(['email' => $email, 'role_id' => $role->id]);
    }
}
