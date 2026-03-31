<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CurrentStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:user', 'current.store'])
            ->get('/_test/current-store', function (Request $request) {
                $currentStore = $request->attributes->get('currentStore');
                $availableStores = $request->attributes->get('availableStores');

                return response()->json([
                    'current_store_id' => $currentStore?->id,
                    'available_store_ids' => $availableStores?->pluck('id')->all() ?? [],
                ]);
            });

        Route::middleware(['web', 'auth', 'role:user', 'current.store'])
            ->get('/_test/current-store-view', fn () => view('user_view.welcome'));
    }

    public function test_middleware_selects_first_accessible_store_ordered_by_name_when_session_store_is_missing(): void
    {
        $merchant = $this->createMerchantUser();

        $zetaStore = $this->createMemberStore($merchant, 'Zeta Store');
        $alphaStore = $this->createMemberStore($merchant, 'Alpha Store');

        $response = $this->actingAs($merchant)->get('/_test/current-store');

        $response->assertOk()
            ->assertJson([
                'current_store_id' => $alphaStore->id,
                'available_store_ids' => [$alphaStore->id, $zetaStore->id],
            ]);

        $response->assertSessionHas('current_store_id', $alphaStore->id);

        $viewResponse = $this->actingAs($merchant)
            ->withSession(['current_store_id' => $alphaStore->id])
            ->get('/_test/current-store-view');

        $viewResponse->assertOk()
            ->assertViewHas('currentStore', fn ($store) => $store?->is($alphaStore))
            ->assertViewHas('availableStores', fn ($stores) => $stores->pluck('id')->all() === [$alphaStore->id, $zetaStore->id]);
    }

    public function test_merchant_can_switch_to_another_store_they_belong_to(): void
    {
        $merchant = $this->createMerchantUser();

        $alphaStore = $this->createMemberStore($merchant, 'Alpha Store');
        $betaStore = $this->createMemberStore($merchant, 'Beta Store');
        $token = 'test-switch-token';

        $this->actingAs($merchant)
            ->withSession([
                '_token' => $token,
                'current_store_id' => $alphaStore->id,
            ]);

        $response = $this->from('/products')->post(route('current-store.update'), [
            '_token' => $token,
            'store_id' => $betaStore->id,
        ]);

        $response->assertRedirect('/products');
        $response->assertSessionHas('current_store_id', $betaStore->id);
        $response->assertSessionHas('success', "Switched to store '{$betaStore->name}'.");
    }

    public function test_merchant_cannot_switch_to_a_store_they_do_not_belong_to(): void
    {
        $merchant = $this->createMerchantUser();
        $otherMerchant = $this->createMerchantUser('other-merchant@example.com');

        $accessibleStore = $this->createMemberStore($merchant, 'Accessible Store');
        $forbiddenStore = $this->createMemberStore($otherMerchant, 'Forbidden Store');
        $token = 'test-forbidden-token';

        $this->actingAs($merchant)
            ->withSession([
                '_token' => $token,
                'current_store_id' => $accessibleStore->id,
            ]);

        $response = $this->post(route('current-store.update'), [
            '_token' => $token,
            'store_id' => $forbiddenStore->id,
        ]);

        $response->assertNotFound();
        $this->assertSame($accessibleStore->id, session('current_store_id'));
    }

    protected function createMerchantUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    protected function createMemberStore(User $user, string $name): Store
    {
        $store = Store::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug() . '-' . fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);

        $store->members()->attach($user->id, ['role' => 'owner']);

        return $store;
    }
}
