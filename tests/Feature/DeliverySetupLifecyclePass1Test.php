<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\DeliverySetupLifecycleService;
use App\Services\Delivery\DeliverySetupStatusService;
use Database\Seeders\CarrierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliverySetupLifecyclePass1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CarrierSeeder::class);
    }

    public function test_new_store_setup_index_starts_at_ship_from(): void
    {
        [$owner, $store] = $this->ownerStore('Lifecycle New Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup'))
            ->assertRedirect(route('settings.delivery.setup.ship-from'));
    }

    public function test_partial_setup_index_skips_to_first_missing_step(): void
    {
        [$owner, $store] = $this->ownerStore('Lifecycle Partial Store');
        $this->readyLocation($store);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup'))
            ->assertRedirect(route('settings.delivery.setup.deliver-to'));
    }

    public function test_finish_stamps_delivery_setup_completed_at(): void
    {
        [$owner, $store] = $this->ownerStore('Lifecycle Finish Store');
        $this->makeStructurallyReady($store);

        $this->assertNull($store->fresh()->delivery_setup_completed_at);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.delivery.setup.finish'))
            ->assertRedirect(route('shippingAutomation'));

        $this->assertNotNull($store->fresh()->delivery_setup_completed_at);
    }

    public function test_completed_ready_merchant_is_sent_to_hub_not_wizard(): void
    {
        [$owner, $store] = $this->ownerStore('Lifecycle Ready Hub Store');
        $this->makeStructurallyReady($store);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup'))
            ->assertRedirect(route('shippingAutomation'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.ship-from'))
            ->assertRedirect(route('shippingAutomation'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Ready')
            ->assertSeeText('Delivery areas & options')
            ->assertSeeText('Shipping origin')
            ->assertDontSeeText('Edit delivery setup')
            ->assertDontSeeText('USPS')
            ->assertDontSeeText('DHL')
            ->assertDontSeeText('Advanced settings')
            ->assertDontSeeText('SETTINGS · DELIVERY');
    }

    public function test_completed_but_broken_stays_on_hub_with_needs_attention(): void
    {
        [$owner, $store] = $this->ownerStore('Lifecycle Broken Hub Store');
        $this->makeStructurallyReady($store);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        ShippingMethod::query()->where('store_id', $store->id)->delete();

        $assessment = app(DeliverySetupStatusService::class)->assess(
            $store,
            $store->locations()->get(),
            $store->shippingZones()->get(),
            $store->shippingMethods()->with('shippingZone')->get(),
            collect(),
            null,
        );
        $this->assertFalse($assessment['is_ready']);

        $state = app(DeliverySetupLifecycleService::class)->state($store, false);
        $this->assertSame(DeliverySetupLifecycleService::STATE_CONFIGURED_NEEDS_ATTENTION, $state);

        // Checkout shipping repair stays available after completion when setup is broken.
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.ship-from'))
            ->assertOk()
            ->assertSeeText('Ship from');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.setup.delivery-option'))
            ->assertOk()
            ->assertSeeText('How should customers get shipping prices?');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.checkout-options'))
            ->assertOk()
            ->assertDontSeeText('Current step')
            ->assertDontSeeText('Delivery setup');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Needs attention')
            ->assertSeeText('Continue setup')
            ->assertDontSeeText('Edit delivery setup')
            ->assertDontSeeText('Advanced settings');
    }

    public function test_backfill_stamps_ready_stores_only(): void
    {
        [$ownerReady, $readyStore] = $this->ownerStore('Lifecycle Backfill Ready');
        $this->makeStructurallyReady($readyStore);

        [$ownerEmpty, $emptyStore] = $this->ownerStore('Lifecycle Backfill Empty');

        $readyStore->forceFill(['delivery_setup_completed_at' => null])->save();
        $emptyStore->forceFill(['delivery_setup_completed_at' => null])->save();

        $stamped = app(DeliverySetupLifecycleService::class)->backfillCompletedAtForReadyStores();

        $this->assertGreaterThanOrEqual(1, $stamped);
        $this->assertNotNull($readyStore->fresh()->delivery_setup_completed_at);
        $this->assertNull($emptyStore->fresh()->delivery_setup_completed_at);
    }

    public function test_cross_store_zone_destroy_stays_denied(): void
    {
        [$ownerA, $storeA] = $this->ownerStore('Lifecycle Store A');
        [$ownerB, $storeB] = $this->ownerStore('Lifecycle Store B');

        $zoneB = ShippingZone::query()->create([
            'store_id' => $storeB->id,
            'name' => 'Other store area',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($ownerA)
            ->withSession(['current_store_id' => $storeA->id])
            ->delete(route('settings.shipping.zones.destroy', $zoneB))
            ->assertNotFound();

        $this->assertDatabaseHas('shipping_zones', ['id' => $zoneB->id, 'deleted_at' => null]);
    }

    public function test_broken_configuration_still_fails_operational_readiness(): void
    {
        [$owner, $store] = $this->ownerStore('Lifecycle Cutover Gate Store');
        $this->readyLocation($store);
        $store->forceFill(['delivery_setup_completed_at' => now()])->save();

        $assessment = app(DeliverySetupStatusService::class)->assess(
            $store,
            $store->locations()->get(),
            $store->shippingZones()->get(),
            $store->shippingMethods()->get(),
            collect(),
            null,
        );

        $this->assertFalse($assessment['is_ready']);
        $this->assertNotNull($store->fresh()->delivery_setup_completed_at);
    }

    public function test_setup_index_clears_stale_wizard_session(): void
    {
        [$owner, $store] = $this->ownerStore('Lifecycle Session Clear Store');

        $this->actingAs($owner)
            ->withSession([
                'current_store_id' => $store->id,
                'delivery_wizard.location_id' => 999,
                'delivery_wizard.zone_id' => 888,
                'delivery_wizard.method_id' => 777,
            ])
            ->get(route('settings.delivery.setup'))
            ->assertRedirect(route('settings.delivery.setup.ship-from'));

        $this->assertFalse(session()->has('delivery_wizard.location_id'));
        $this->assertFalse(session()->has('delivery_wizard.zone_id'));
        $this->assertFalse(session()->has('delivery_wizard.method_id'));
    }

    public function test_platform_fedex_health_uses_merchant_friendly_copy(): void
    {
        [$owner, $store] = $this->ownerStore('Lifecycle FedEx Copy Store');
        $this->makeStructurallyReady($store);

        config(['carriers.fedex.checkout_rates_enabled' => false]);

        // Attach a FedEx live method so platform-off messaging can appear.
        $fedEx = \App\Models\Carrier::query()->where('code', 'fedex')->firstOrFail();
        $account = \App\Models\CarrierAccount::query()->create([
            'store_id' => $store->id,
            'carrier_id' => $fedEx->id,
            'provider' => \App\Models\CarrierAccount::PROVIDER_FEDEX,
            'display_name' => 'FedEx account',
            'ownership_mode' => \App\Models\CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
            'credentials_source' => \App\Models\CarrierAccount::CREDENTIALS_MERCHANT_ACCOUNT,
            'connection_mode' => \App\Models\CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'connection_model' => \App\Models\CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
            'fedex_integrator_account' => true,
            'billing_owner' => \App\Models\CarrierAccount::BILLING_OWNER_MERCHANT,
            'environment' => \App\Models\CarrierAccount::ENVIRONMENT_SANDBOX,
            'connection_status' => \App\Models\CarrierAccount::CONNECTION_CONNECTED,
            'status' => \App\Models\CarrierAccount::STATUS_ENABLED,
            'enabled_for_checkout' => true,
            'capabilities' => ['rates' => true, 'checkout_rates' => true, 'labels' => true],
        ]);

        $zone = $store->shippingZones()->first();
        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'carrier_account_id' => $account->id,
            'name' => 'FedEx Ground',
            'code' => 'fedex-ground-'.Str::lower(Str::random(4)),
            'rate_type' => ShippingMethod::RATE_CARRIER_CALCULATED_LATER,
            'carrier_service_code' => 'FEDEX_GROUND',
            'carrier_service_name' => 'FedEx Ground',
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 2,
        ]);

        $result = app(DeliverySetupStatusService::class)->assess(
            $store,
            $store->locations()->get(),
            $store->shippingZones()->get(),
            $store->shippingMethods()->with(['shippingZone', 'carrierAccount'])->get(),
            collect([$account]),
            null,
        );

        $platformItem = collect($result['health_items'])->first(
            fn (array $item): bool => str_starts_with((string) ($item['id'] ?? ''), 'fedex_checkout_platform_off')
        );

        $this->assertNotNull($platformItem);
        $this->assertStringContainsString('temporarily unavailable', (string) ($platformItem['message'] ?? ''));
        $this->assertStringNotContainsString('platform', strtolower((string) ($platformItem['message'] ?? '')));
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $role = Role::firstOrCreate(['name' => 'user']);
        $owner = User::factory()->create([
            'email' => Str::slug($name).'-owner@example.test',
            'role_id' => $role->id,
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
        $store->members()->syncWithoutDetaching([$owner->id => ['role' => Store::ROLE_OWNER]]);

        return [$owner, $store];
    }

    private function readyLocation(Store $store): Location
    {
        $location = $store->locations()->orderByDesc('is_default')->orderBy('id')->first();

        $attributes = [
            'name' => 'Main warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '100 Warehouse Rd',
            'city' => 'Allen',
            'state' => 'TX',
            'postal_code' => '75002',
            'country_code' => 'US',
            'is_default' => true,
            'is_active' => true,
            'fulfills_online_orders' => true,
        ];

        if ($location !== null) {
            $location->update($attributes);

            return $location->fresh();
        }

        return Location::query()->create([
            'store_id' => $store->id,
            ...$attributes,
        ]);
    }

    private function makeStructurallyReady(Store $store): void
    {
        $this->readyLocation($store);

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard delivery',
            'code' => 'standard-delivery-'.Str::lower(Str::random(4)),
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 8,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);
    }
}
