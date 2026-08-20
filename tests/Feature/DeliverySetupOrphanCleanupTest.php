<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\DeliverySetupStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliverySetupOrphanCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_delivery_area_also_removes_its_checkout_options(): void
    {
        [$owner, $store] = $this->ownerStore('Cascade Zone Delete Store');
        $this->readyLocation($store);

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
            'name' => 'Standard shipping',
            'code' => 'standard-shipping',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('settings.shipping.zones.destroy', $zone))
            ->assertRedirect();

        $this->assertSoftDeleted('shipping_zones', ['id' => $zone->id]);
        $this->assertSoftDeleted('shipping_methods', ['id' => $method->id]);
    }

    public function test_orphan_option_surfaces_remove_action_and_cleanup_works(): void
    {
        [$owner, $store] = $this->ownerStore('Orphan Method Cleanup Store');
        $location = $this->readyLocation($store);

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $readyMethod = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Ready option',
            'code' => 'ready-option',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 8,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $deletedArea = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Removed area',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $orphan = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $deletedArea->id,
            'name' => 'US methodc',
            'code' => 'us-methodc',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 1,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 1,
        ]);

        // Simulate legacy trash: area soft-deleted without cascading methods.
        $deletedArea->delete();
        $orphan->refresh();

        $methods = $store->shippingMethods()->with('shippingZone')->get();
        $result = app(DeliverySetupStatusService::class)->assess(
            $store,
            collect([$location]),
            collect([$zone]),
            $methods,
            collect(),
            null,
        );

        $orphanHealth = collect($result['health_items'])->first(
            fn (array $item): bool => ($item['id'] ?? '') === 'delivery_option_no_area_'.$orphan->id
        );

        $this->assertNotNull($orphanHealth);
        $this->assertSame($orphan->id, $orphanHealth['remove_method_id'] ?? null);
        $this->assertTrue($result['is_ready']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('US methodc')
            ->assertSeeText('Manage areas and checkout options')
            ->assertSeeText('Remove unused options');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.methods.cleanup-orphans'))
            ->assertRedirect();

        $this->assertSoftDeleted('shipping_methods', ['id' => $orphan->id]);
        $this->assertNotSoftDeleted('shipping_methods', ['id' => $readyMethod->id]);
    }

    public function test_soft_deleted_area_marks_linked_option_as_orphan(): void
    {
        $store = Store::query()->create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Soft Delete Orphan Store',
            'slug' => 'soft-delete-orphan-'.Str::lower(Str::random(6)),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'Old area',
            'countries' => ['US'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Leftover option',
            'code' => 'leftover-option',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 3,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $zone->delete();
        $method->refresh()->load('shippingZone');

        $this->assertTrue($method->isOrphanedFromArea());
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
        return Location::query()->create([
            'store_id' => $store->id,
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
        ]);
    }
}
