<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\DefaultLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase35StoreSettingsAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_creates_default_location_from_store_defaults_without_duplicates(): void
    {
        $merchant = $this->merchant('phase35-onboarding@example.test');

        $this->actingAs($merchant)
            ->post(route('onboarding-StoreDetails-1.store'), [
                'mode' => 'create',
                'name' => 'Phase 35 Store',
                'primary_market' => 'Middle East',
                'address' => '12 Market Road, Dubai',
                'currency' => 'AED',
                'timezone' => 'Asia/Dubai',
                'category' => 'physical',
                'business_models' => ['Physical Goods'],
            ])
            ->assertRedirect(route('onboarding-Step2-AddProductVariations'));

        $store = Store::query()->where('name', 'Phase 35 Store')->firstOrFail();
        $location = $store->locations()->where('is_default', true)->firstOrFail();

        $this->assertSame('Middle East', $store->settings['primary_market']);
        $this->assertSame('AED', $store->currency);
        $this->assertSame('Asia/Dubai', $store->timezone);
        $this->assertSame('Main location', $location->name);
        $this->assertSame('12 Market Road, Dubai', $location->address_line1);
        $this->assertTrue((bool) $location->is_active);

        app(DefaultLocationService::class)->ensureFromStoreDefaults($store, $merchant);
        app(DefaultLocationService::class)->ensureFromStoreDefaults($store, $merchant);

        $this->assertSame(1, $store->locations()->where('is_default', true)->count());
        $this->assertSame(1, $store->locations()->count());
    }

    public function test_default_location_alignment_fills_blanks_but_does_not_overwrite_merchant_edits(): void
    {
        $owner = $this->merchant('phase35-owner@example.test');
        $store = $this->storeFor($owner, 'Blank Address Store', null);
        $location = $store->locations()->where('is_default', true)->firstOrFail();

        $this->assertSame('Main location', $location->name);
        $this->assertNull($location->address_line1);

        $store->update(['address' => 'Updated store address']);
        app(DefaultLocationService::class)->ensureFromStoreDefaults($store->fresh(), $owner);

        $location = $location->fresh();
        $this->assertSame('Updated store address', $location->address_line1);

        $location->update(['address_line1' => 'Merchant edited warehouse']);
        $store->update(['address' => 'Do not copy this over the edited location']);
        app(DefaultLocationService::class)->ensureFromStoreDefaults($store->fresh(), $owner);

        $this->assertSame('Merchant edited warehouse', $location->fresh()->address_line1);
    }

    public function test_locations_page_explains_boundaries_and_keeps_real_actions_only(): void
    {
        $owner = $this->merchant('phase35-locations@example.test');
        $store = $this->storeFor($owner, 'Locations Copy Store');
        $store->locations()->create([
            'name' => 'Back room',
            'type' => Location::TYPE_STORE,
            'address_line1' => 'Behind the shop',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.locations.index'))
            ->assertOk()
            ->assertSee('Locations are places where you store or fulfill inventory, such as a warehouse, shop, stock room, restaurant branch, or third-party storage.', false)
            ->assertSee('Locations control where stock is stored. Markets and currencies control where and how you sell. Market-specific selling settings will be added later.', false)
            ->assertSee('What locations are used for', false)
            ->assertSee('Inventory levels', false)
            ->assertSee('Reservations', false)
            ->assertSee('Stock movements', false)
            ->assertSee('Future fulfillment origin', false)
            ->assertSee('What locations do not control', false)
            ->assertSee('Customer markets', false)
            ->assertSee('Selling currencies', false)
            ->assertSee('Regional pricing', false)
            ->assertSee('Storefront availability', false)
            ->assertSee('Add location', false)
            ->assertSee('Edit', false)
            ->assertSee('Make default', false)
            ->assertSee('Deactivate', false)
            ->assertDontSee('Carrier setup', false)
            ->assertDontSee('Configure Shipping', false)
            ->assertDontSee('Assign market', false)
            ->assertDontSee('Assign currency', false)
            ->assertDontSee('Stock transfer', false);
    }

    public function test_staff_can_view_locations_but_cannot_mutate_them(): void
    {
        $owner = $this->merchant('phase35-staff-owner@example.test');
        $staff = $this->merchant('phase35-staff@example.test');
        $store = $this->storeFor($owner, 'Staff View Store');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.locations.index'))
            ->assertOk()
            ->assertSee('You can view locations. Store owners manage location changes.', false)
            ->assertSee('Locations control where stock is stored.', false);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.store'), [
                'name' => 'Blocked location',
                'type' => Location::TYPE_WAREHOUSE,
            ])
            ->assertForbidden();
    }

    public function test_locations_are_scoped_to_the_current_store(): void
    {
        $owner = $this->merchant('phase35-scope@example.test');
        $store = $this->storeFor($owner, 'Alpha Phase 35 Store');
        $otherStore = $this->storeFor($owner, 'Beta Phase 35 Store');
        $otherStore->locations()->where('is_default', true)->firstOrFail()->update([
            'name' => 'Private Beta Warehouse',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.locations.index'))
            ->assertOk()
            ->assertSee('Main location', false)
            ->assertDontSee('Private Beta Warehouse', false);
    }

    public function test_store_settings_and_onboarding_copy_separate_locations_markets_currency_and_timezone(): void
    {
        $owner = $this->merchant('phase35-copy@example.test');
        $store = $this->storeFor($owner, 'Copy Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings'))
            ->assertOk()
            ->assertSee('General Settings', false)
            ->assertSee('Store Profile', false)
            ->assertSee('Public identity and appearance of your storefront.', false)
            ->assertSee('Regional &amp; Financials', false)
            ->assertSee('Business Configuration', false)
            ->assertSee($store->name, false)
            ->assertSee($store->address, false)
            ->assertSee('Default store currency', false)
            ->assertSee("This is your store's base currency for dashboard totals and default pricing. Market-specific currencies will be added later.", false)
            ->assertSee('Default store timezone', false)
            ->assertSee('This timezone is used for dashboard dates, reports, and store operations. Location-specific cutoff times can be added later when fulfillment is enabled.', false)
            ->assertSee('Primary market', false)
            ->assertSee('This is your default selling region. Full multi-market selling, regional currencies, and price lists will be added later.', false)
            ->assertSee('Locations are for stock, not selling rules', false)
            ->assertSee('Manage locations', false)
            ->assertSee(route('settings.locations.index'), false)
            ->assertSee('Configure shipping &amp; delivery', false)
            ->assertSee(route('shippingAutomation'), false)
            ->assertSee('Set delivery zones, delivery methods, carrier accounts, and the locations orders can ship from.', false)
            ->assertDontSee('Integrated Carriers', false)
            ->assertDontSee('Generate New Key', false)
            ->assertDontSee('Automated Tax Calculation', false)
            ->assertDontSee('Manage Markets', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('onboarding-StoreDetails-1'))
            ->assertOk()
            ->assertSee('Default store currency', false)
            ->assertSee('Default store timezone', false)
            ->assertSee('Primary market', false)
            ->assertSee('Full multi-market selling, regional currencies, and price lists will be added later.', false);
    }

    public function test_shipping_page_is_real_setup_and_has_no_fake_preview_controls(): void
    {
        $owner = $this->merchant('phase35-shipping-preview@example.test');
        $store = $this->storeFor($owner, 'Shipping Preview Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSee('Shipping &amp; Delivery', false)
            ->assertSee('Set where this store delivers, which delivery options customers can choose, and how orders are fulfilled.', false)
            ->assertSee('Shipping zones', false)
            ->assertSee('Delivery methods', false)
            ->assertSee('Carriers &amp; accounts', false)
            ->assertSee('Fulfillment locations', false)
            ->assertSee('Add zone', false)
            ->assertSee('Add carrier account', false)
            ->assertSee('Carrier labels, live rates, pickup scheduling, and routing automation will be available after manual fulfillment and delivery methods are stable.', false)
            ->assertDontSee('Shipping automation preview', false)
            ->assertDontSee('Integrated Courier Services - Preview', false)
            ->assertDontSee('Smart Routing Rules - Coming later', false)
            ->assertDontSee('Automation Insights - Demo preview', false)
            ->assertDontSee('Save unavailable', false)
            ->assertDontSee('Export preview', false)
            ->assertDontSee('Connected', false)
            ->assertDontSee('Save Changes', false);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('shippingAutomation'), [
                'carrier' => 'UPS',
                'routing' => 'cheapest',
            ])
            ->assertStatus(405);
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function storeFor(User $user, string $name, ?string $address = '123 Stock Street'): Store
    {
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => $address,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => ['primary_market' => 'Global Market'],
            'onboarding_completed' => true,
        ]);

        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => Store::ROLE_OWNER],
        ]);

        return $store;
    }
}
