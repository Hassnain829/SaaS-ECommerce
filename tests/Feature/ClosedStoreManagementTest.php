<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClosedStoreManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_see_closed_store_in_closed_section(): void
    {
        [$owner, $store] = $this->ownerStore('Closed Visible Store');
        $store->delete();

        $this->actingAs($owner)
            ->get(route('store-management'))
            ->assertOk()
            ->assertSeeText('Closed stores')
            ->assertSeeText('Closed Visible Store')
            ->assertSeeText('Restore Store')
            ->assertSeeText('Delete Permanently');
    }

    public function test_closed_store_does_not_appear_in_active_store_grid(): void
    {
        [$owner, $store] = $this->ownerStore('Grid Hidden Store');
        $store->delete();

        $response = $this->actingAs($owner)->get(route('store-management'));
        $response->assertOk();
        $response->assertSeeText('Grid Hidden Store');
        $response->assertSee('id="closed-stores"', false);
        $this->assertStringNotContainsString(
            'data-store-name="Grid Hidden Store"',
            (string) $response->getContent()
        );
    }

    public function test_manager_cannot_permanently_delete_closed_store(): void
    {
        [$owner, $store] = $this->ownerStore('Manager Purge Block');
        $manager = $this->merchant('manager-purge@example.com');
        $this->attach($store, $manager, Store::ROLE_MANAGER);
        $store->delete();

        $this->actingAs($manager)
            ->withPasswordConfirmed()
            ->delete(route('store.permanent-destroy', ['storeId' => $store->id]), [
                'confirm_store_name' => $store->name,
            ])
            ->assertForbidden();

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }

    public function test_staff_cannot_restore_closed_store(): void
    {
        [$owner, $store] = $this->ownerStore('Staff Restore Block');
        $staff = $this->merchant('staff-restore@example.com');
        $this->attach($store, $staff, Store::ROLE_STAFF);
        $store->delete();

        $this->actingAs($staff)
            ->post(route('store.restore', ['storeId' => $store->id]))
            ->assertForbidden();

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }

    public function test_owner_can_restore_closed_store(): void
    {
        [$owner, $store] = $this->ownerStore('Restore Me Store');
        $store->delete();

        $this->actingAs($owner)
            ->post(route('store.restore', ['storeId' => $store->id]))
            ->assertRedirect(route('store-management'))
            ->assertSessionHas('success_title', 'Store restored');

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'store_restored',
        ]);
    }

    public function test_restored_store_returns_to_active_store_listing(): void
    {
        [$owner, $store] = $this->ownerStore('Back To Active Store');
        $store->delete();

        $this->actingAs($owner)
            ->post(route('store.restore', ['storeId' => $store->id]));

        $this->actingAs($owner)
            ->get(route('store-management'))
            ->assertOk()
            ->assertSee('data-store-name="Back To Active Store"', false)
            ->assertDontSee('id="closed-stores"', false);
    }

    public function test_active_store_cannot_use_permanent_delete_endpoint(): void
    {
        [$owner, $store] = $this->ownerStore('Still Active Store');

        $this->actingAs($owner)
            ->withPasswordConfirmed()
            ->delete(route('store.permanent-destroy', ['storeId' => $store->id]), [
                'confirm_store_name' => $store->name,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'deleted_at' => null,
        ]);
    }

    public function test_closed_store_permanent_delete_routes_through_store_purge_service(): void
    {
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('Service Purge Store');
        $path = 'products/'.$store->id.'/purge.jpg';
        Storage::disk('public')->put($path, 'bytes');
        $this->makeProduct($store, 'Purge Product', $path);
        $store->delete();

        $this->actingAs($owner)
            ->withPasswordConfirmed()
            ->delete(route('store.permanent-destroy', ['storeId' => $store->id]), [
                'confirm_store_name' => $store->name,
            ])
            ->assertRedirect(route('store-management'))
            ->assertSessionHas('success_title', 'Store permanently deleted');

        $this->assertDatabaseMissing('stores', ['id' => $store->id]);
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'store_purged',
            'user_id' => $owner->id,
        ]);
    }

    public function test_permanent_delete_controller_delegates_to_purge_service(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Store/ClosedStoreManagementController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('StorePurgeService', $source);
        $this->assertStringContainsString('$purgeService->purge(', $source);
        $this->assertStringNotContainsString('forceDelete', $source);
    }

    public function test_merchant_permanent_delete_aborts_when_file_cleanup_fails(): void
    {
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('Cleanup Fail Store');
        $path = 'products/'.$store->id.'/fail.jpg';
        Storage::disk('public')->put($path, 'bytes');
        $product = $this->makeProduct($store, 'Fail Product', $path);
        $store->delete();

        Storage::shouldReceive('disk')->andReturnUsing(function (string $disk) use ($path) {
            $fake = Storage::fake($disk);
            if ($disk === 'public') {
                $fake->put($path, 'bytes');
            }

            $proxy = Mockery::mock($fake);
            $proxy->shouldReceive('exists')->andReturnUsing(fn (string $candidate) => $fake->exists($candidate));
            $proxy->shouldReceive('delete')->andReturn(false);
            $proxy->shouldReceive('deleteDirectory')->andReturn(true);
            $proxy->shouldReceive('path')->andReturnUsing(fn (string $candidate) => $fake->path($candidate));

            return $proxy;
        });

        $this->actingAs($owner)
            ->withPasswordConfirmed()
            ->from(route('store-management'))
            ->delete(route('store.permanent-destroy', ['storeId' => $store->id]), [
                'confirm_store_name' => $store->name,
            ])
            ->assertRedirect(route('store-management').'#closed-stores')
            ->assertSessionHas('error');

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_merchant_permanent_delete_aborts_on_unsafe_cross_store_artifact(): void
    {
        Storage::fake('public');

        [$owner, $storeA] = $this->ownerStore('Unsafe Merchant A');
        $storeB = $this->makeStore($owner, 'Unsafe Merchant B');
        $storeB->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        $pathB = 'products/'.$storeB->id.'/owned-by-b.jpg';
        Storage::disk('public')->put($pathB, 'b-bytes');

        $productA = $this->makeProduct($storeA, 'Hostile Product A');
        ProductImage::query()->create([
            'product_id' => $productA->id,
            'image_path' => $pathB,
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ProductImage::STATUS_READY,
        ]);

        $storeA->delete();

        $this->actingAs($owner)
            ->withPasswordConfirmed()
            ->from(route('store-management'))
            ->delete(route('store.permanent-destroy', ['storeId' => $storeA->id]), [
                'confirm_store_name' => $storeA->name,
            ])
            ->assertRedirect(route('store-management').'#closed-stores')
            ->assertSessionHas('error');

        Storage::disk('public')->assertExists($pathB);
        $this->assertSoftDeleted('stores', ['id' => $storeA->id]);
        $this->assertDatabaseHas('stores', ['id' => $storeB->id, 'deleted_at' => null]);
    }

    public function test_store_a_owner_cannot_restore_or_delete_store_b(): void
    {
        $ownerA = $this->merchant('owner-a@example.com');
        $ownerB = $this->merchant('owner-b@example.com');

        $storeA = $this->makeStore($ownerA, 'Store A Only');
        $storeA->members()->attach($ownerA->id, ['role' => Store::ROLE_OWNER]);

        $storeB = $this->makeStore($ownerB, 'Store B Only');
        $storeB->members()->attach($ownerB->id, ['role' => Store::ROLE_OWNER]);
        $storeB->delete();

        $this->actingAs($ownerA)
            ->post(route('store.restore', ['storeId' => $storeB->id]))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->withPasswordConfirmed()
            ->delete(route('store.permanent-destroy', ['storeId' => $storeB->id]), [
                'confirm_store_name' => $storeB->name,
            ])
            ->assertForbidden();

        $this->assertSoftDeleted('stores', ['id' => $storeB->id]);
    }

    public function test_permanent_delete_requires_exact_store_name_confirmation(): void
    {
        [$owner, $store] = $this->ownerStore('Typed Name Required');
        $store->delete();

        $this->actingAs($owner)
            ->withPasswordConfirmed()
            ->from(route('store-management'))
            ->delete(route('store.permanent-destroy', ['storeId' => $store->id]), [
                'confirm_store_name' => 'Wrong Name',
            ])
            ->assertRedirect(route('store-management'))
            ->assertSessionHasErrors('confirm_store_name');

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }

    public function test_permanent_delete_requires_recent_password_confirmation(): void
    {
        [$owner, $store] = $this->ownerStore('Password Gate Store');
        $store->delete();

        $this->actingAs($owner)
            ->delete(route('store.permanent-destroy', ['storeId' => $store->id]), [
                'confirm_store_name' => $store->name,
            ])
            ->assertRedirect(route('password.confirm'));

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }

    public function test_closed_stores_route_redirects_to_management_hub_section(): void
    {
        $owner = $this->merchant();

        $this->actingAs($owner)
            ->get(route('stores.closed'))
            ->assertRedirect(route('store-management').'#closed-stores');
    }

    public function test_security_audit_evidence_remains_after_successful_merchant_purge(): void
    {
        Storage::fake('public');

        [$owner, $store] = $this->ownerStore('Audit Retention Store');
        SecurityLog::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'store_fixture',
            'severity' => SecurityLog::SEVERITY_INFO,
            'created_at' => now(),
        ]);

        $store->delete();

        $this->actingAs($owner)
            ->withPasswordConfirmed()
            ->delete(route('store.permanent-destroy', ['storeId' => $store->id]), [
                'confirm_store_name' => $store->name,
            ])
            ->assertRedirect(route('store-management'));

        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'store_fixture',
            'store_id' => null,
        ]);
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'store_purged',
            'user_id' => $owner->id,
            'store_id' => null,
        ]);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(string $name): array
    {
        $owner = $this->merchant();
        $store = $this->makeStore($owner, $name);
        $store->members()->attach($owner->id, ['role' => Store::ROLE_OWNER]);

        return [$owner, $store];
    }

    private function merchant(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $user, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->attach($user->id, ['role' => $role]);
    }

    private function makeProduct(Store $store, string $name, ?string $imagePath = null): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => null,
            'base_price' => 10,
            'sku' => 'SKU-'.strtoupper(Str::random(6)),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
        $product->variants()->create([
            'sku' => $product->sku,
            'price' => 10,
            'stock' => 5,
            'stock_alert' => 1,
        ]);

        if ($imagePath !== null) {
            ProductImage::query()->create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => 0,
                'is_primary' => true,
                'status' => ProductImage::STATUS_READY,
            ]);
        }

        return $product;
    }

    private function withPasswordConfirmed(): self
    {
        return $this->withSession(['auth.password_confirmed_at' => time()]);
    }
}
