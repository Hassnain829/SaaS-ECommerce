<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SaasPackage;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\User;
use App\Services\Billing\StoreSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoreEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_list_packages_but_merchant_cannot(): void
    {
        $admin = $this->createAdminUser();
        $merchant = $this->createMerchantUser();

        $this->actingAs($merchant)
            ->post(route('admin-billing.packages.store'), [
                'name' => 'Starter',
                'price' => 29.00,
                'billing_interval' => 'month',
                'currency' => 'USD',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin-billing.packages.store'), [
                'name' => 'Starter',
                'price' => 29.00,
                'billing_interval' => 'month',
                'currency' => 'USD',
                'is_active' => '1',
                'features_text' => "Catalog\nOrders",
            ])
            ->assertRedirect(route('admin-billing'));

        $this->assertDatabaseHas('saas_packages', [
            'name' => 'Starter',
            'price_cents' => 2900,
            'billing_interval' => 'month',
            'is_active' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin-billing'))
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee('USD 29.00');
    }

    public function test_grant_trial_sets_trial_ends_at_and_extending_moves_access_forward(): void
    {
        $admin = $this->createAdminUser();
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Trial Store');
        $package = SaasPackage::query()->create([
            'name' => 'Growth',
            'slug' => 'growth',
            'price_cents' => 4900,
            'billing_interval' => 'month',
            'currency' => 'USD',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->travelTo(now()->startOfDay());

        $this->actingAs($admin)
            ->post(route('admin-tenant.access', $store), [
                'action' => 'grant_trial',
                'package_id' => $package->id,
                'trial_days' => 10,
                'notes' => 'Initial trial',
            ])
            ->assertRedirect(route('admin-tenant.show', $store));

        $subscription = StoreSubscription::query()->where('store_id', $store->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame(StoreSubscription::STATUS_TRIAL, $subscription->status);
        $this->assertSame(10, $subscription->trial_days);
        $this->assertTrue($subscription->trial_ends_at->equalTo(now()->addDays(10)));
        $this->assertTrue($subscription->access_ends_at->equalTo(now()->addDays(10)));

        $firstEnd = $subscription->access_ends_at->copy();

        $this->actingAs($admin)
            ->post(route('admin-tenant.access', $store), [
                'action' => 'grant_trial',
                'package_id' => $package->id,
                'trial_days' => 5,
            ])
            ->assertRedirect(route('admin-tenant.show', $store));

        $subscription->refresh();
        $this->assertTrue($subscription->access_ends_at->equalTo($firstEnd->copy()->addDays(5)));
    }

    public function test_middleware_blocks_merchant_routes_when_expired_or_suspended_but_allows_profile_and_admin(): void
    {
        $admin = $this->createAdminUser();
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Gated Store');
        $store->forceFill(['onboarding_completed' => true])->save();

        $service = app(StoreSubscriptionService::class);
        $service->grantTrial($store, 3, null, $admin);

        $subscription = StoreSubscription::query()->where('store_id', $store->id)->firstOrFail();
        $subscription->forceFill([
            'access_ends_at' => now()->subDay(),
            'trial_ends_at' => now()->subDay(),
        ])->save();

        $this->assertFalse($service->isAccessible($store->fresh()));

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('store.access-expired'));

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('store.access-expired'))
            ->assertOk()
            ->assertSee('Access to this store has ended')
            ->assertSee('Gated Store');

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('profileSettings'))
            ->assertRedirect(route('generalSettings', ['tab' => 'account']));

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('generalSettings', ['tab' => 'account']))
            ->assertOk();

        $service->suspend($store, $admin, 'Payment pending');

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertRedirect(route('store.access-expired'));

        $this->actingAs($admin)
            ->get(route('admin-dashboard'))
            ->assertOk()
            ->assertSee('Platform overview')
            ->assertSee('Expired access');

        $this->actingAs($admin)
            ->get(route('admin-tenant'))
            ->assertOk()
            ->assertSee('Gated Store');
    }

    public function test_store_without_subscription_remains_accessible(): void
    {
        $merchant = $this->createMerchantUser();
        $store = $this->createMemberStore($merchant, 'Open Store');
        $store->forceFill(['onboarding_completed' => true])->save();

        $this->assertTrue(app(StoreSubscriptionService::class)->isAccessible($store));

        $this->actingAs($merchant)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk();
    }

    protected function createAdminUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'admin']);

        return User::factory()->create([
            'email' => $email ?? 'admin-'.fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
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
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
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
