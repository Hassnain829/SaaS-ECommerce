<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_bulk_delete_current_store_products_only(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $p1 = $this->makeProduct($store, 'A');
        $p2 = $this->makeProduct($store, 'B');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'delete',
                'product_ids' => [$p1->id, $p2->id],
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('products', ['id' => $p1->id]);
        $this->assertSoftDeleted('products', ['id' => $p2->id]);
    }

    public function test_bulk_rejects_cross_store_product_ids(): void
    {
        $owner = $this->makeUser();
        $storeA = $this->makeStore($owner, 'Store A');
        $storeB = $this->makeStore($owner, 'Store B');
        $pA = $this->makeProduct($storeA, 'In A');
        $pB = $this->makeProduct($storeB, 'In B');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('products.bulk'), [
                'action' => 'delete',
                'product_ids' => [$pA->id, $pB->id],
            ])
            ->assertSessionHasErrors('bulk');

        $this->assertDatabaseHas('products', ['id' => $pA->id]);
        $this->assertDatabaseHas('products', ['id' => $pB->id]);
    }

    public function test_staff_cannot_post_bulk_actions(): void
    {
        $owner = $this->makeUser('owner@x.com');
        $staff = $this->makeUser('staff@x.com');
        $store = $this->makeStore($owner);
        $store->members()->syncWithoutDetaching([$staff->id => ['role' => Store::ROLE_STAFF]]);
        $p = $this->makeProduct($store, 'S');

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'delete',
                'product_ids' => [$p->id],
            ])
            ->assertForbidden();
    }

    public function test_bulk_stock_set_records_stock_movements(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Stocky');
        $variant = $product->variants()->first();
        $variant->update(['stock' => 3]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'stock',
                'product_ids' => [$product->id],
                'stock_mode' => 'set',
                'stock_value' => 11,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($msg): bool => str_contains((string) $msg, '1 product'));

        $variant->refresh();
        $this->assertSame(11, (int) $variant->stock);

        $this->assertDatabaseHas('stock_movements', [
            'store_id' => $store->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'previous_stock' => 3,
            'new_stock' => 11,
            'movement_type' => StockMovement::TYPE_EDIT_UPDATE,
        ]);
    }

    public function test_bulk_categories_and_tags_are_store_scoped(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $other = $this->makeStore($owner, 'Other');
        $cat = Category::query()->create([
            'store_id' => $other->id,
            'name' => 'Evil Cat',
            'slug' => 'evil-cat',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);
        $tag = Tag::query()->create([
            'store_id' => $other->id,
            'name' => 'Evil Tag',
            'slug' => 'evil-tag',
            'status' => 'active',
            'sort_order' => 0,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $product = $this->makeProduct($store, 'P');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'categories',
                'product_ids' => [$product->id],
                'category_ids' => [$cat->id],
            ])
            ->assertSessionHasErrors('category_ids');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'tags',
                'product_ids' => [$product->id],
                'tag_ids' => [$tag->id],
            ])
            ->assertSessionHasErrors('tag_ids');
    }

    public function test_bulk_assign_categories_brand_tags_on_current_store(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $cat = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Cat A',
            'slug' => 'cat-a',
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);
        $tag = Tag::query()->create([
            'store_id' => $store->id,
            'name' => 'Tag A',
            'slug' => 'tag-a',
            'status' => 'active',
            'sort_order' => 0,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $brand = Brand::query()->create([
            'store_id' => $store->id,
            'name' => 'Brand A',
            'slug' => 'brand-a',
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $product = $this->makeProduct($store, 'Multi');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'categories',
                'product_ids' => [$product->id],
                'category_ids' => [$cat->id],
            ])
            ->assertRedirect();
        $this->assertTrue($product->fresh()->categories->contains('id', $cat->id));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'tags',
                'product_ids' => [$product->id],
                'tag_ids' => [$tag->id],
            ])
            ->assertRedirect();
        $this->assertTrue($product->fresh()->tags->contains('id', $tag->id));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'brand',
                'product_ids' => [$product->id],
                'brand_id' => $brand->id,
            ])
            ->assertRedirect();
        $this->assertSame($brand->id, (int) $product->fresh()->brand_id);
    }

    public function test_bulk_status_updates_products(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'Drafty');
        $product->update(['status' => false]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('products.bulk'), [
                'action' => 'status',
                'product_ids' => [$product->id],
                'product_status' => 'published',
            ])
            ->assertRedirect();

        $this->assertTrue((bool) $product->fresh()->status);
    }

    public function test_product_quick_view_is_store_scoped(): void
    {
        $owner = $this->makeUser();
        $store = $this->makeStore($owner);
        $product = $this->makeProduct($store, 'QV');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Quick view', false)
            ->assertSee('QV', false);
    }

    private function makeUser(?string $email = null): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'role_id' => $role->id,
        ]);
    }

    private function makeStore(User $user, string $name = 'Test Store'): Store
    {
        $store = Store::create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Addr',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => false,
        ]);
        $store->members()->attach($user->id, ['role' => Store::ROLE_OWNER]);

        return $store;
    }

    private function makeProduct(Store $store, string $name): Product
    {
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
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
            'stock' => 0,
            'stock_alert' => 0,
        ]);

        return $product;
    }
}
