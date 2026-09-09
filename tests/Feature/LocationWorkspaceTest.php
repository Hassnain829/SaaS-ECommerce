<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_selects_a_location_and_shows_actionable_controls(): void
    {
        $owner = $this->merchant('locations-workspace@example.test');
        $store = $this->storeFor($owner, 'Workspace Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.locations.index'));

        $default = $store->locations()->where('is_default', true)->firstOrFail();
        $second = $store->locations()->create([
            'name' => 'Allen TX Warehouse',
            'type' => Location::TYPE_WAREHOUSE,
            'address_line1' => '738 Fawn Valley Dr',
            'city' => 'Allen',
            'state' => 'TX',
            'postal_code' => '75002',
            'country_code' => 'US',
            'is_default' => false,
            'is_active' => true,
            'fulfills_online_orders' => true,
            'pickup_enabled' => false,
            'routing_priority' => 100,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.locations.index', ['location' => $second->id]))
            ->assertOk()
            ->assertSee('Allen TX Warehouse', false)
            ->assertSee($default->name, false)
            ->assertSee('Your locations', false)
            ->assertSee('Pickup services', false)
            ->assertSee('data-lc-open-add', false)
            ->assertSee(route('settings.locations.pickup', $second), false)
            ->assertSee(route('settings.locations.update', $second), false)
            ->assertDontSee('onsubmit="return confirm', false);
    }

    public function test_owner_can_toggle_customer_pickup_for_the_current_store_location(): void
    {
        $owner = $this->merchant('locations-pickup@example.test');
        $store = $this->storeFor($owner, 'Pickup Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.locations.index'));

        $location = $store->locations()->where('is_default', true)->firstOrFail();
        $this->assertFalse((bool) $location->pickup_enabled);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.locations.pickup', $location))
            ->assertRedirect(route('settings.locations.index', ['location' => $location->id]));

        $this->assertTrue((bool) $location->fresh()->pickup_enabled);
    }

    public function test_staff_cannot_toggle_pickup_and_cross_store_pickup_is_hidden(): void
    {
        $owner = $this->merchant('locations-pickup-owner@example.test');
        $staff = $this->merchant('locations-pickup-staff@example.test');
        $store = $this->storeFor($owner, 'Staff Pickup Store');
        $other = $this->storeFor($owner, 'Other Pickup Store');
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.locations.index'));

        $location = $store->locations()->firstOrFail();
        $foreign = $other->locations()->firstOrFail();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.locations.pickup', $location))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.locations.pickup', $foreign))
            ->assertNotFound();
    }

    public function test_validation_errors_keep_submitted_add_location_name(): void
    {
        $owner = $this->merchant('locations-validation@example.test');
        $store = $this->storeFor($owner, 'Validation Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->followingRedirects()
            ->from(route('settings.locations.index'))
            ->post(route('settings.locations.store'), [
                'name' => 'Incomplete warehouse',
                'type' => Location::TYPE_WAREHOUSE,
                'fulfills_online_orders' => '1',
                'location_form' => 'add',
            ])
            ->assertOk()
            ->assertSee('Incomplete warehouse', false)
            ->assertSee('Online fulfillment locations need a complete ship-from address', false);
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function storeFor(User $user, string $name): Store
    {
        $store = Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => '123 Stock Street',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => Store::ROLE_OWNER],
        ]);

        return $store;
    }
}
