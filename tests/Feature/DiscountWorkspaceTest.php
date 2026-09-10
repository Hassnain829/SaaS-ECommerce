<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiscountWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_renders_store_coupons_and_actionable_controls(): void
    {
        $owner = $this->merchant('discounts-workspace@example.test');
        $store = $this->storeFor($owner, 'Discount Workspace Store');
        $otherOwner = $this->merchant('discounts-other@example.test');
        $otherStore = $this->storeFor($otherOwner, 'Other Discount Store');

        $this->coupon($store, [
            'code' => 'WELCOME10',
            'name' => 'Welcome offer',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10,
        ]);
        $this->coupon($store, [
            'code' => 'FALL15',
            'name' => 'Autumn launch',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 15,
            'starts_at' => now()->addDays(5),
        ]);
        $this->coupon($store, [
            'code' => 'SUMMER20',
            'name' => 'Summer campaign',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 20,
            'expires_at' => now()->subDay(),
        ]);
        $this->coupon($store, [
            'code' => 'PAUSE5',
            'name' => 'Paused coupon',
            'type' => Coupon::TYPE_FIXED,
            'value' => 5,
            'is_active' => false,
        ]);
        $this->coupon($otherStore, [
            'code' => 'OTHER99',
            'name' => 'Other store coupon',
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.coupons.index'))
            ->assertOk()
            ->assertSee('Discount overview', false)
            ->assertSee('Coupon codes', false)
            ->assertSee('Create discount', false)
            ->assertSee('One coupon can be applied per platform checkout.', false)
            ->assertSee('WELCOME10', false)
            ->assertSee('Starts next', false)
            ->assertSee('FALL15', false)
            ->assertSee('SUMMER20', false)
            ->assertSee('PAUSE5', false)
            ->assertSee('Welcome offer', false)
            ->assertSee('data-status="active"', false)
            ->assertSee('data-status="scheduled"', false)
            ->assertSee('data-status="expired"', false)
            ->assertSee('data-status="inactive"', false)
            ->assertSee('data-dc-open-add', false)
            ->assertSee(route('settings.coupons.store'), false)
            ->assertSee('data-ui-confirm', false)
            ->assertDontSee('OTHER99', false)
            ->assertDontSee('onsubmit="return confirm', false);
    }

    public function test_staff_can_view_coupons_but_cannot_manage_them(): void
    {
        $owner = $this->merchant('discounts-staff-owner@example.test');
        $store = $this->storeFor($owner, 'Staff Discount Store');
        $this->coupon($store, ['code' => 'STAFFVIEW']);

        $staff = User::factory()->create([
            'email' => 'discounts-staff@example.test',
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);
        $store->members()->attach($staff->id, ['role' => Store::ROLE_STAFF]);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.coupons.index'))
            ->assertOk()
            ->assertSee('STAFFVIEW', false)
            ->assertSee('You can view coupon codes, but you do not have permission to change them.', false)
            ->assertSee('View only', false)
            ->assertDontSee('class="dc-edit"', false);

        $coupon = Coupon::query()->forStore($store->id)->firstOrFail();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.coupons.store'), $this->payload(['code' => 'BLOCKED']))
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.coupons.toggle', $coupon))
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('settings.coupons.destroy', $coupon))
            ->assertForbidden();
    }

    public function test_owner_can_toggle_coupon_status_in_the_same_store_only(): void
    {
        $owner = $this->merchant('discounts-toggle@example.test');
        $store = $this->storeFor($owner, 'Toggle Discount Store');
        $otherOwner = $this->merchant('discounts-toggle-other@example.test');
        $otherStore = $this->storeFor($otherOwner, 'Toggle Other Store');

        $coupon = $this->coupon($store, ['code' => 'TOGGLEME', 'is_active' => true]);
        $foreign = $this->coupon($otherStore, ['code' => 'FOREIGN1', 'is_active' => true]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.coupons.toggle', $coupon))
            ->assertRedirect(route('settings.coupons.index'))
            ->assertSessionHas('success');

        $this->assertFalse((bool) $coupon->fresh()->is_active);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.coupons.toggle', $coupon))
            ->assertRedirect(route('settings.coupons.index'));

        $this->assertTrue((bool) $coupon->fresh()->is_active);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('settings.coupons.toggle', $foreign))
            ->assertNotFound();
    }

    public function test_store_timezone_start_time_becomes_active_after_that_local_time(): void
    {
        $owner = $this->merchant('discounts-timezone@example.test');
        $store = $this->storeFor($owner, 'Karachi Discount Store');
        $store->update(['timezone' => 'Asia/Karachi']);

        $startedAt = now('Asia/Karachi')->subMinutes(2);
        $upcomingAt = now('Asia/Karachi')->addHour();
        $started = $startedAt->format('Y-m-d H:i:s');
        $upcoming = $upcomingAt->format('Y-m-d H:i:s');

        $live = $this->coupon($store, [
            'code' => 'NOWLIVE',
            'starts_at' => $started,
        ]);
        $later = $this->coupon($store, [
            'code' => 'LATERON',
            'starts_at' => $upcoming,
        ]);

        $this->assertSame('active', $live->merchantStatus(null, 'Asia/Karachi'));
        $this->assertSame('scheduled', $later->merchantStatus(null, 'Asia/Karachi'));

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.coupons.index'))
            ->assertOk()
            ->assertSee('NOWLIVE', false)
            ->assertSee('data-status="active"', false)
            ->assertSee('LATERON', false)
            ->assertSee('data-status="scheduled"', false)
            ->assertSee($startedAt->format('g:i A'), false)
            ->assertSee('Leave these blank unless you need a minimum order', false)
            ->assertDontSee('Asia/Karachi', false);
    }

    public function test_order_requirements_are_optional_when_creating_a_coupon(): void
    {
        $owner = $this->merchant('discounts-optional-rules@example.test');
        $store = $this->storeFor($owner, 'Optional Rules Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.coupons.store'), [
                'code' => 'OPENCART',
                'name' => 'Open cart offer',
                'type' => Coupon::TYPE_PERCENTAGE,
                'value' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('settings.coupons.index'))
            ->assertSessionHas('success');

        $coupon = Coupon::query()->forStore($store->id)->where('code', 'OPENCART')->firstOrFail();
        $this->assertSame('0.00', (string) $coupon->minimum_order_amount);
        $this->assertNull($coupon->maximum_discount_amount);
    }

    public function test_validation_errors_keep_submitted_coupon_code(): void
    {
        $owner = $this->merchant('discounts-validation@example.test');
        $store = $this->storeFor($owner, 'Validation Discount Store');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->followingRedirects()
            ->from(route('settings.coupons.index'))
            ->post(route('settings.coupons.store'), $this->payload([
                'code' => 'keepme12',
                'name' => '',
                'coupon_form' => 'add',
            ]))
            ->assertOk()
            ->assertSee('KEEPME12', false)
            ->assertSee('discountForm', false);
    }

    public function test_product_sku_eligibility_is_saved_from_the_workspace(): void
    {
        $owner = $this->merchant('discounts-sku@example.test');
        $store = $this->storeFor($owner, 'SKU Discount Store');
        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Coupon SKU Product',
            'slug' => 'coupon-sku-product-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'SKU-100',
            'product_type' => 'physical',
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Promo category',
            'slug' => 'promo-category-'.Str::random(4),
            'status' => true,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.coupons.store'), $this->payload([
                'code' => 'SKUONLY',
                'applies' => 'products',
                'product_skus' => 'SKU-100',
            ]))
            ->assertRedirect(route('settings.coupons.index'))
            ->assertSessionHas('success');

        $skuCoupon = Coupon::query()->forStore($store->id)->where('code', 'SKUONLY')->firstOrFail();
        $this->assertEqualsCanonicalizing([$product->id], $skuCoupon->products()->pluck('products.id')->all());
        $this->assertSame([], $skuCoupon->categories()->pluck('categories.id')->all());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.coupons.store'), $this->payload([
                'code' => 'CATONLY',
                'applies' => 'categories',
                'category_ids' => [$category->id],
            ]))
            ->assertRedirect(route('settings.coupons.index'));

        $categoryCoupon = Coupon::query()->forStore($store->id)->where('code', 'CATONLY')->firstOrFail();
        $this->assertSame([], $categoryCoupon->products()->pluck('products.id')->all());
        $this->assertEqualsCanonicalizing([$category->id], $categoryCoupon->categories()->pluck('categories.id')->all());

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->from(route('settings.coupons.index'))
            ->post(route('settings.coupons.store'), $this->payload([
                'code' => 'NEEDSKU',
                'applies' => 'products',
                'product_skus' => '',
            ]))
            ->assertSessionHasErrors('product_skus');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SAVE10',
            'name' => 'Save ten',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10,
            'minimum_order_amount' => 0,
            'is_active' => 1,
        ], $overrides);
    }

    private function coupon(Store $store, array $overrides = []): Coupon
    {
        return Coupon::query()->create(array_merge([
            'store_id' => $store->id,
            'code' => 'SAVE10',
            'name' => 'Test coupon',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10,
            'minimum_order_amount' => 0,
            'is_active' => true,
        ], $overrides));
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
