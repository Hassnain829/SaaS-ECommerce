<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
            ->assertSee('Carrier pickup', false)
            ->assertSee('FedEx carrier pickup', false)
            ->assertSee('Coming soon', false)
            ->assertSee('data-lc-open-add', false)
            ->assertSee(route('settings.locations.update', $second), false)
            ->assertDontSee('Customer pickup', false)
            ->assertDontSee('Allow in-store collection', false)
            ->assertDontSee('onsubmit="return confirm', false);
    }

    public function test_merchant_cannot_enable_customer_pickup_from_the_location_form(): void
    {
        $owner = $this->merchant('locations-no-pickup@example.test');
        $store = $this->storeFor($owner, 'No Pickup Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.locations.store'), [
                'name' => 'Allen warehouse',
                'type' => Location::TYPE_WAREHOUSE,
                'address_line1' => '738 Fawn Valley Dr',
                'city' => 'Allen',
                'state' => 'TX',
                'postal_code' => '75002',
                'country_code' => 'US',
                'fulfills_online_orders' => '1',
                'pickup_enabled' => '1',
            ])
            ->assertRedirect();

        $location = $store->locations()->where('name', 'Allen warehouse')->firstOrFail();
        $this->assertFalse((bool) $location->pickup_enabled);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.locations.update', $location), [
                'name' => 'Allen warehouse',
                'type' => Location::TYPE_WAREHOUSE,
                'address_line1' => '738 Fawn Valley Dr',
                'city' => 'Allen',
                'state' => 'TX',
                'postal_code' => '75002',
                'country_code' => 'US',
                'fulfills_online_orders' => '1',
                'pickup_enabled' => '1',
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $location->fresh()->pickup_enabled);
        $this->assertFalse(Route::has('settings.locations.pickup'));
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
