<?php

namespace Tests\Feature;

use App\Models\ConnectedSiteCutover;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Services\Inventory\DefaultLocationService;
use App\Services\Inventory\InventorySyncService;
use App\Support\OrderLifecycle;
use App\Support\ProductTypeBehavior;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_page_shows_go_live_checklist_and_blocks_activation_when_stripe_is_missing(): void
    {
        [$owner, $store] = $this->ownerStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('Go live checklist')
            ->assertSee('Mark website live becomes available')
            ->assertSee('Connect Stripe for this store')
            ->assertDontSee('deactivate plugin', false)
            ->assertDontSee('Delete WooCommerce');

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.cutover.activate'))
            ->assertSessionHasErrors('cutover');

        $this->assertDatabaseMissing('connected_site_cutovers', [
            'store_id' => $store->id,
            'status' => ConnectedSiteCutover::STATUS_ACTIVATED,
        ]);
    }

    public function test_owner_can_acknowledge_backup_and_activation_stays_blocked_without_live_gates(): void
    {
        [$owner, $store] = $this->ownerStore();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.cutover.acknowledge'), [
                'acknowledgement' => 'backup',
            ])
            ->assertRedirect(route('developer-storefront.settings'));

        $cutover = ConnectedSiteCutover::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertNotNull($cutover->backup_acknowledged_at);
        $this->assertSame($owner->id, (int) $cutover->backup_acknowledged_by);
        $this->assertNotSame(ConnectedSiteCutover::STATUS_ACTIVATED, $cutover->status);
    }

    public function test_manager_and_staff_cannot_activate_or_acknowledge_and_store_b_cannot_see_store_a(): void
    {
        $owner = $this->merchant('cutover-owner@example.test');
        $manager = $this->merchant('cutover-manager@example.test');
        $staff = $this->merchant('cutover-staff@example.test');
        $storeA = $this->store($owner, 'Cutover Store A');
        $storeB = $this->store($owner, 'Cutover Store B');
        $this->attach($storeA, $owner, Store::ROLE_OWNER);
        $this->attach($storeA, $manager, Store::ROLE_MANAGER);
        $this->attach($storeA, $staff, Store::ROLE_STAFF);
        $this->attach($storeB, $owner, Store::ROLE_OWNER);

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $storeA->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk()
            ->assertSee('Only a store owner can confirm backup steps');

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('developer-storefront.cutover.acknowledge'), ['acknowledgement' => 'backup'])
            ->assertForbidden();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('developer-storefront.cutover.activate'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeA->id])
            ->post(route('developer-storefront.cutover.acknowledge'), ['acknowledgement' => 'backup']);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $storeB->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk();

        $this->assertDatabaseHas('connected_site_cutovers', [
            'store_id' => $storeA->id,
        ]);
        $storeBCutover = ConnectedSiteCutover::query()->where('store_id', $storeB->id)->first();
        $this->assertTrue($storeBCutover === null || $storeBCutover->backup_acknowledged_at === null);
    }

    public function test_owner_can_activate_when_live_gates_pass_and_rollback_does_not_delete_orders(): void
    {
        [$owner, $store] = $this->readyStore();
        $order = Order::query()->where('store_id', $store->id)->firstOrFail();

        foreach (['backup', 'tax_off', 'cache', 'rollback', 'woo_archive'] as $ack) {
            $this->actingAs($owner)
                ->withSession(['current_store_id' => $store->id])
                ->post(route('developer-storefront.cutover.acknowledge'), ['acknowledgement' => $ack])
                ->assertRedirect(route('developer-storefront.settings'));
        }

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.cutover.activate'))
            ->assertRedirect(route('developer-storefront.settings'))
            ->assertSessionHas('success');

        $cutover = ConnectedSiteCutover::query()->where('store_id', $store->id)->firstOrFail();
        $this->assertSame(ConnectedSiteCutover::STATUS_ACTIVATED, $cutover->status);
        $this->assertSame($order->id, (int) $cutover->smoke_order_id);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.cutover.rollback'))
            ->assertRedirect(route('developer-storefront.settings'));

        $this->assertSame(ConnectedSiteCutover::STATUS_ROLLED_BACK, $cutover->fresh()->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'store_id' => $store->id,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
        ]);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function readyStore(): array
    {
        [$owner, $store] = $this->ownerStore();
        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('developer-storefront.website.update'), [
                'website_url' => 'http://127.0.0.1:8080',
            ])
            ->assertRedirect();

        app(ConnectedSiteService::class)->issuePrimaryCredential($store);
        $site = app(ConnectedSiteService::class)->primarySite($store->fresh());
        $site->update([
            'last_seen_at' => now(),
            'last_health_at' => now(),
            'plugin_version' => '1.7.1',
            'last_health' => [
                'ok' => true,
                'url_match' => true,
                'production_ready' => true,
                'conflicts' => [],
            ],
        ]);

        $location = app(DefaultLocationService::class)->ensureForStore($store);
        $location->update([
            'address_line1' => '10 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '73301',
            'country_code' => 'US',
            'is_active' => true,
            'is_default' => true,
            'fulfills_online_orders' => true,
        ]);

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
            'code' => 'standard',
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 5,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $this->connectReadyStripeForCheckout($store);
        TaxSetting::query()->updateOrCreate(
            ['store_id' => $store->id],
            [
                'enabled' => false,
                'prices_include_tax' => false,
                'default_product_taxable' => true,
                'shipping_taxable' => false,
                'calculation_address' => TaxSetting::CALCULATION_ADDRESS_SHIPPING,
            ]
        );

        $product = Product::query()->create([
            'store_id' => $store->id,
            'name' => 'Cutover Test Product',
            'slug' => 'cutover-test-'.Str::random(6),
            'base_price' => 20,
            'sku' => 'CUT-'.Str::random(4),
            'product_type' => 'physical',
            ...ProductTypeBehavior::defaultColumnsFor('physical'),
            'status' => true,
            'is_taxable' => true,
            'meta' => [],
        ]);
        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => $product->sku.'-D',
            'price' => 20,
            'stock' => 4,
        ]);
        app(InventorySyncService::class)->ensureDefaultLevelForVariant($variant, 4);

        Order::query()->create([
            'store_id' => $store->id,
            'order_number' => '#CUT-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'subtotal' => 20,
            'total' => 20,
            'grand_total' => 20,
            'currency_code' => 'USD',
            'order_source' => 'platform_checkout',
            'channel' => 'wordpress',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);

        return [$owner, $store->fresh()];
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function ownerStore(): array
    {
        $owner = $this->merchant('cutover-ready-owner@example.test');
        $store = $this->store($owner, 'Cutover Ready Store');
        $this->attach($store, $owner, Store::ROLE_OWNER);

        return [$owner, $store];
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner, string $name): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role): void
    {
        $store->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }
}
