<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\ShippingPackagePreset;
use App\Models\ShippingZone;
use App\Models\Store;
use App\Models\StoreUser;
use App\Models\User;
use App\Services\Settings\StoreMemberPermissionSync;
use App\Support\Dashboard\MerchantDashboardPresenter;
use App\Support\OrderLifecycle;
use App\Support\StoreMemberAccess;
use App\Support\StorePermission;
use App\Support\StorePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorePermissionLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_roles_resolve_to_explicit_permissions(): void
    {
        $owner = $this->merchant('owner@example.com');
        $manager = $this->merchant('manager@example.com');
        $staff = $this->merchant('staff@example.com');
        $outsider = $this->merchant('outsider@example.com');
        $store = $this->store($owner);

        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->assertTrue($owner->hasStorePermission($store, StorePermission::SECURITY_MANAGE));
        $this->assertTrue($owner->hasStorePermission($store, StorePermission::BILLING_MANAGE));
        $this->assertTrue($owner->hasStorePermission($store, StorePermission::TEAM_MANAGE));
        $this->assertTrue($owner->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));
        $this->assertTrue($owner->hasStorePermission($store, StorePermission::DEVELOPER_API_MANAGE));

        $this->assertTrue($manager->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::IMPORTS_MANAGE));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::ORDERS_MANAGE));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::CUSTOMERS_MANAGE));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::SETTINGS_VIEW));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::DEVELOPER_API_VIEW));
        $this->assertTrue($manager->hasStorePermission($store, StorePermission::SECURITY_VIEW));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::TEAM_VIEW));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::TEAM_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::DEVELOPER_API_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::SECURITY_MANAGE));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::BILLING_VIEW));
        $this->assertFalse($manager->hasStorePermission($store, StorePermission::BILLING_MANAGE));

        $this->assertTrue($staff->hasStorePermission($store, StorePermission::CATALOG_VIEW));
        $this->assertTrue($staff->hasStorePermission($store, StorePermission::ORDERS_VIEW));
        $this->assertTrue($staff->hasStorePermission($store, StorePermission::CUSTOMERS_VIEW));
        $this->assertTrue($staff->hasStorePermission($store, StorePermission::SETTINGS_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::IMPORTS_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::IMPORTS_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::ORDERS_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::CUSTOMERS_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::TEAM_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::TEAM_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::DEVELOPER_API_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::DEVELOPER_API_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::SECURITY_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::SECURITY_MANAGE));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::BILLING_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, StorePermission::BILLING_MANAGE));

        $this->assertFalse($outsider->hasStorePermission($store, StorePermission::CATALOG_VIEW));
    }

    public function test_stored_member_permissions_drive_middleware_keys(): void
    {
        $owner = $this->merchant('owner@example.com');
        $member = $this->merchant('custom@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $store->members()->attach($member->id, ['role' => Store::ROLE_MEMBER]);

        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $member,
            ['products.edit'],
            StoreMemberAccess::PRESET_CUSTOM,
        );

        $this->assertTrue($member->hasStorePermission($store, StorePermission::CATALOG_VIEW));
        $this->assertTrue($member->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertFalse($member->hasStorePermission($store, StorePermission::ORDERS_VIEW));

        $payload = [
            'name' => 'Custom Access Product',
            'description' => 'Created by a permissioned teammate.',
            'bulk_price' => 12,
            'sku' => 'PERM-CUSTOM-001',
            'product_type' => 'physical',
            'bulk_stock' => 3,
            'stock_alert' => 1,
        ];

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $payload)
            ->assertRedirect(route('products'));
    }

    public function test_permission_middleware_blocks_staff_catalog_mutations_but_allows_manager(): void
    {
        $owner = $this->merchant('owner@example.com');
        $manager = $this->merchant('manager@example.com');
        $staff = $this->merchant('staff@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $payload = [
            'name' => 'Permission Test Product',
            'description' => 'Created through permission middleware.',
            'bulk_price' => 25,
            'sku' => 'PERM-001',
            'product_type' => 'physical',
            'bulk_stock' => 5,
            'stock_alert' => 1,
        ];

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $payload)
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('product.store'), $payload)
            ->assertRedirect(route('products'));

        $this->assertDatabaseHas('products', [
            'store_id' => $store->id,
            'sku' => 'PERM-001',
        ]);
    }

    public function test_sensitive_routes_follow_the_final_permission_matrix(): void
    {
        $owner = $this->merchant('owner@example.com');
        $manager = $this->merchant('manager@example.com');
        $staff = $this->merchant('staff@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $manager, Store::ROLE_MANAGER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('team-members.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('developer-storefront.settings'))
            ->assertOk();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('developer-storefront.token.generate'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('security'))
            ->assertOk();

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('security'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('billingSubscription'))
            ->assertForbidden();
    }

    public function test_listing_and_detail_routes_require_view_permission(): void
    {
        $owner = $this->merchant('view-owner@example.com');
        $member = $this->merchant('customers-only@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['customers.view']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('customers'))
            ->assertOk();
    }

    public function test_products_edit_does_not_unlock_price_inventory_or_delete_routes(): void
    {
        $owner = $this->merchant('edit-owner@example.com');
        $member = $this->merchant('edit-only@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['products.edit']);

        $product = $this->product($store);

        $this->assertTrue($member->hasStorePermission($store, StorePermission::CATALOG_MANAGE));
        $this->assertFalse($member->hasStorePermission($store, 'products.prices'));
        $this->assertFalse($member->hasStorePermission($store, 'products.inventory'));
        $this->assertFalse($member->hasStorePermission($store, 'products.delete'));

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.price', $product), ['base_price' => 42.5])
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->patchJson(route('products.inline.stock', $product), ['stock' => 8])
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('product.destroy', ['productId' => $product->id]))
            ->assertForbidden();
    }

    public function test_discounts_permission_does_not_unlock_payments_carriers_or_tax_mutations(): void
    {
        $owner = $this->merchant('discount-owner@example.com');
        $member = $this->merchant('discount-only@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['settings.discounts']);

        $this->assertFalse($member->hasStorePermission($store, StorePermission::SETTINGS_MANAGE));
        $this->assertFalse($member->hasStorePermission($store, 'settings.payments'));
        $this->assertFalse($member->hasStorePermission($store, 'settings.carriers'));
        $this->assertFalse($member->hasStorePermission($store, 'settings.taxes'));

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.payments.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->put(route('settings.taxes.update'), ['enabled' => '1'])
            ->assertForbidden();
    }

    public function test_orders_draft_does_not_unlock_payments_refunds_or_cancel(): void
    {
        $owner = $this->merchant('draft-owner@example.com');
        $member = $this->merchant('draft-only@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['orders.draft']);

        $this->assertFalse($member->hasStorePermission($store, StorePermission::ORDERS_MANAGE));
        $this->assertFalse($member->hasStorePermission($store, 'orders.payments'));
        $this->assertFalse($member->hasStorePermission($store, 'orders.cancel'));
        $this->assertFalse($member->hasStorePermission($store, 'customers.refunds'));
    }

    public function test_orders_edit_and_products_edit_do_not_unlock_sensitive_cancel_or_import(): void
    {
        $owner = $this->merchant('edit-sens-owner@example.com');
        $member = $this->merchant('edit-sens-member@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, [
            'products.view',
            'products.edit',
            'products.prices',
            'products.inventory',
            'orders.view',
            'orders.draft',
            'orders.edit',
        ]);

        $this->assertFalse($member->hasStorePermission($store, 'orders.cancel'));
        $this->assertFalse($member->hasStorePermission($store, 'products.import'));
        $this->assertFalse($member->hasStorePermission($store, 'website.cutover'));

        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'SENS-CANCEL-1',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PENDING,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => 'buyer@example.test',
            'billing_same_as_shipping' => true,
            'subtotal' => 20,
            'total' => 20,
            'grand_total' => 20,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 0,
            'total_quantity' => 0,
            'placed_at' => now(),
        ]);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('orders.updateStatus', $order), [
                'status' => OrderLifecycle::ORDER_CANCELLED,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products.import.create'))
            ->assertForbidden();
    }

    public function test_orders_export_requires_sensitive_permission(): void
    {
        $owner = $this->merchant('export-orders-owner@example.com');
        $member = $this->merchant('export-orders-member@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['orders.view', 'orders.edit']);

        Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'EXP-1001',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => 'buyer@example.test',
            'billing_same_as_shipping' => true,
            'subtotal' => 40,
            'total' => 40,
            'grand_total' => 40,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertDontSeeText('Export CSV');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders.export'))
            ->assertForbidden();

        $csv = $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('EXP-1001', $csv);
        $this->assertDatabaseHas('security_logs', [
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'event_type' => 'orders_exported',
        ]);
    }

    public function test_suspended_and_invited_memberships_are_rejected(): void
    {
        $owner = $this->merchant('status-owner@example.com');
        $suspended = $this->merchant('suspended@example.com');
        $invited = $this->merchant('invited@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $suspended, ['products.view', 'orders.view'], StoreUser::STATUS_SUSPENDED);
        $this->grant($store, $invited, ['products.view', 'orders.view'], StoreUser::STATUS_INVITED);

        $this->assertFalse($suspended->hasStorePermission($store, 'products.view'));
        $this->assertFalse($invited->hasStorePermission($store, 'products.view'));
        $this->assertFalse($suspended->activeMemberStores()->where('stores.id', $store->id)->exists());
        $this->assertFalse($invited->activeMemberStores()->where('stores.id', $store->id)->exists());

        $this->actingAs($suspended)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('products'))
            ->assertRedirect(route('store-management'));

        $this->actingAs($invited)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('current-store.update'), ['store_id' => $store->id])
            ->assertNotFound();
    }

    public function test_dashboard_omits_restricted_revenue_orders_customers_and_inventory(): void
    {
        $owner = $this->merchant('dash-owner@example.com');
        $member = $this->merchant('dash-website@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['website.view']);

        Customer::query()->create([
            'store_id' => $store->id,
            'email' => 'hidden-customer@example.test',
            'full_name' => 'Hidden Customer',
            'status' => 'active',
        ]);

        Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'HID-9911',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => 'hidden-customer@example.test',
            'billing_same_as_shipping' => true,
            'subtotal' => 123.45,
            'total' => 123.45,
            'grand_total' => 123.45,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);

        $product = $this->product($store, 'Hidden Stock Widget', 'HID-STOCK');
        ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => 'HID-STOCK-V',
            'price' => 12,
            'stock' => 2,
            'stock_alert' => 5,
        ]);

        $dashboard = MerchantDashboardPresenter::forStore($store, '30', $member);

        $this->assertArrayNotHasKey('revenue', $dashboard['metrics']);
        $this->assertArrayNotHasKey('orders', $dashboard['metrics']);
        $this->assertArrayNotHasKey('new_customers', $dashboard['metrics']);
        $this->assertSame([], $dashboard['recent_orders']);
        $this->assertSame([], $dashboard['inventory_watch']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-metric="revenue"', false)
            ->assertDontSee('HID-9911')
            ->assertDontSee('Hidden Customer')
            ->assertDontSee('Hidden Stock Widget')
            ->assertDontSee(route('products'), false)
            ->assertDontSee(route('orders'), false)
            ->assertDontSee(route('customers'), false);
    }

    public function test_adding_a_member_does_not_persist_location_ids(): void
    {
        $owner = $this->merchant('location-owner@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('team-members.store'), [
                'name' => 'No Location Member',
                'email' => 'no-location@example.com',
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
                'store_ids' => [$store->id],
                'location_ids' => [99, 100],
            ])
            ->assertRedirect(route('team-members.index'));

        $member = User::query()->where('email', 'no-location@example.com')->first();
        $this->assertNotNull($member);

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'status' => StoreMemberAccess::STATUS_INVITED,
            'location_ids' => null,
        ]);
    }

    public function test_updating_permissions_does_not_reactivate_a_suspended_member(): void
    {
        $owner = $this->merchant('keep-status-owner@example.com');
        $member = $this->merchant('keep-status@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['products.view'], StoreUser::STATUS_SUSPENDED);

        $this->actingAs($owner)
            ->withSession(['current_store_id' => $store->id])
            ->patch(route('team-members.update', ['user' => $member->id]), [
                'access_preset' => StoreMemberAccess::PRESET_VIEW,
            ])
            ->assertRedirect(route('team-members.index'));

        $this->assertDatabaseHas('store_user', [
            'store_id' => $store->id,
            'user_id' => $member->id,
            'status' => StoreUser::STATUS_SUSPENDED,
        ]);
    }

    public function test_fulfillment_access_does_not_show_or_open_delivery(): void
    {
        $owner = $this->merchant('fulfill-owner@example.com');
        $member = $this->merchant('fulfill-only@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, [
            'fulfillment.fulfill',
            'fulfillment.tracking',
            'fulfillment.inventory_adjust',
        ]);

        $nav = StorePermissionResolver::navFor($member, $store);

        $this->assertTrue($nav['orders']);
        $this->assertTrue($nav['shipments']);
        $this->assertTrue($nav['products']);
        $this->assertFalse($nav['delivery']);
        $this->assertFalse($nav['locations']);
        $this->assertFalse($nav['taxes']);
        $this->assertFalse($nav['discounts']);
        $this->assertFalse($nav['payments']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('>Orders</', false)
            ->assertSee('>Shipments</', false)
            ->assertDontSee('>Delivery</', false)
            ->assertDontSee('>Locations</', false)
            ->assertDontSee('>Taxes</', false)
            ->assertDontSee('>Discounts</', false);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shipments.index'))
            ->assertOk();
    }

    public function test_fulfillment_access_can_create_a_shipment_without_editing_the_order(): void
    {
        $owner = $this->merchant('ship-owner@example.com');
        $member = $this->merchant('ship-member@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, [
            'fulfillment.fulfill',
            'fulfillment.tracking',
            'fulfillment.inventory_adjust',
        ]);

        $product = $this->product($store, 'Fulfillment Widget', 'FUL-WIDGET');
        $variant = ProductVariant::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'sku' => 'FUL-WIDGET-D',
            'price' => 20,
            'stock' => 10,
        ]);
        $order = Order::query()->create([
            'store_id' => $store->id,
            'order_number' => 'FUL-4401',
            'status' => OrderLifecycle::ORDER_CONFIRMED,
            'payment_status' => OrderLifecycle::PAYMENT_PAID,
            'fulfillment_status' => OrderLifecycle::FULFILLMENT_UNFULFILLED,
            'customer_email' => 'paid-customer@example.test',
            'billing_same_as_shipping' => true,
            'subtotal' => 20,
            'total' => 20,
            'grand_total' => 20,
            'currency_code' => 'USD',
            'order_source' => 'manual',
            'channel' => 'dashboard',
            'item_count' => 1,
            'total_quantity' => 1,
            'placed_at' => now(),
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Fulfillment Widget',
            'variant_label' => 'Default option',
            'sku_snapshot' => $variant->sku,
            'product_type_snapshot' => 'physical',
            'quantity' => 1,
            'unit_price' => 20,
            'subtotal' => 20,
            'total' => 20,
        ]);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orders'))
            ->assertOk()
            ->assertSee('FUL-4401')
            ->assertSee('>Fulfill</', false)
            ->assertDontSeeText('Create order');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('orderViewDetails', $order))
            ->assertOk()
            ->assertSeeText('Fulfill order')
            ->assertSeeText('Create shipment')
            ->assertDontSeeText('Update order status');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('orders.shipments.store', $order), [
                'items' => [$item->id => 1],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shipments', [
            'store_id' => $store->id,
            'order_id' => $order->id,
        ]);
    }

    public function test_delivery_access_shows_delivery_without_taxes_or_discounts(): void
    {
        $owner = $this->merchant('delivery-owner@example.com');
        $member = $this->merchant('delivery-only@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['settings.delivery']);

        $nav = StorePermissionResolver::navFor($member, $store);

        $this->assertTrue($nav['delivery']);
        $this->assertFalse($nav['locations']);
        $this->assertFalse($nav['taxes']);
        $this->assertFalse($nav['discounts']);
        $this->assertFalse($nav['orders']);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('>Delivery</', false)
            ->assertDontSee('>Locations</', false)
            ->assertDontSee('>Taxes</', false)
            ->assertDontSee('>Discounts</', false)
            ->assertDontSee('>Orders</', false);

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.delivery.test-address'))
            ->assertOk();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.taxes.index'))
            ->assertForbidden();
    }

    public function test_delivery_manage_does_not_let_a_member_delete_delivery_setup(): void
    {
        $owner = $this->merchant('delivery-delete-owner@example.com');
        $member = $this->merchant('delivery-manage-only@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['settings.delivery']);

        $store->forceFill(['delivery_setup_completed_at' => now()])->save();
        [$zone, $method, $preset] = $this->deliverySetup($store);

        $this->assertFalse($member->hasStorePermission($store, 'settings.delivery.delete'));

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Edit')
            ->assertDontSeeText('Remove area')
            ->assertDontSeeText('Remove option')
            ->assertDontSeeText('Remove unused options');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('settings.shipping.zones.destroy', $zone))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('settings.shipping.methods.destroy', $method))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->post(route('settings.shipping.methods.cleanup-orphans'))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('settings.shipping.package-presets.destroy', $preset))
            ->assertForbidden();

        $this->assertNotSoftDeleted('shipping_zones', ['id' => $zone->id]);
        $this->assertNotSoftDeleted('shipping_methods', ['id' => $method->id]);
        $this->assertNotSoftDeleted('shipping_package_presets', ['id' => $preset->id]);
    }

    public function test_delivery_delete_permission_lets_a_member_remove_a_delivery_area(): void
    {
        $owner = $this->merchant('delivery-delete-grant-owner@example.com');
        $member = $this->merchant('delivery-delete-member@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['settings.delivery.delete']);

        $store->forceFill(['delivery_setup_completed_at' => now()])->save();
        [$zone] = $this->deliverySetup($store);

        $this->assertTrue($member->hasStorePermission($store, 'settings.delivery'));
        $this->assertTrue($member->hasStorePermission($store, 'settings.delivery.delete'));

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertOk()
            ->assertSeeText('Remove area')
            ->assertSeeText('Remove option');

        $this->actingAs($member)
            ->from(route('shippingAutomation'))
            ->withSession(['current_store_id' => $store->id])
            ->delete(route('settings.shipping.zones.destroy', $zone))
            ->assertRedirect(route('shippingAutomation'));

        $this->assertSoftDeleted('shipping_zones', ['id' => $zone->id]);
    }

    public function test_discounts_access_does_not_unlock_delivery_or_taxes(): void
    {
        $owner = $this->merchant('discount-nav-owner@example.com');
        $member = $this->merchant('discount-nav@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->grant($store, $member, ['settings.discounts']);

        $nav = StorePermissionResolver::navFor($member, $store);

        $this->assertTrue($nav['discounts']);
        $this->assertFalse($nav['delivery']);
        $this->assertFalse($nav['locations']);
        $this->assertFalse($nav['taxes']);

        $dashboard = MerchantDashboardPresenter::forStore($store, '30', $member);
        $this->assertFalse($dashboard['permissions']['delivery_manage']);
        $this->assertFalse($dashboard['permissions']['settings_view']);
        $this->assertSame([], collect($dashboard['setup_progress']['steps'] ?? [])->pluck('key')->all());

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('>Discounts</', false)
            ->assertDontSee('>Delivery</', false)
            ->assertDontSee('Set up delivery');

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('shippingAutomation'))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('settings.coupons.index'))
            ->assertOk();
    }

    public function test_legacy_settings_view_does_not_show_delivery_in_the_sidebar(): void
    {
        $owner = $this->merchant('legacy-nav-owner@example.com');
        $staff = $this->merchant('legacy-nav-staff@example.com');
        $store = $this->store($owner);
        $this->attach($store, $owner, Store::ROLE_OWNER);
        $this->attach($store, $staff, Store::ROLE_STAFF);

        $this->assertTrue($staff->hasStorePermission($store, StorePermission::SETTINGS_VIEW));
        $this->assertFalse($staff->hasStorePermission($store, 'settings.delivery'));

        $nav = StorePermissionResolver::navFor($staff, $store);

        $this->assertFalse($nav['delivery']);
        $this->assertFalse($nav['locations']);
        $this->assertFalse($nav['taxes']);
        $this->assertFalse($nav['discounts']);

        $this->actingAs($staff)
            ->withSession(['current_store_id' => $store->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Delivery</', false);
    }

    private function merchant(string $email): User
    {
        $role = Role::firstOrCreate(['name' => 'user']);

        return User::factory()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);
    }

    private function store(User $owner): Store
    {
        return Store::query()->create([
            'user_id' => $owner->id,
            'name' => 'Permission Store',
            'slug' => 'permission-store-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);
    }

    private function attach(Store $store, User $user, string $role, ?string $status = null): void
    {
        $pivot = ['role' => $role];
        if ($status !== null) {
            $pivot['status'] = $status;
        }

        $store->members()->syncWithoutDetaching([
            $user->id => $pivot,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grant(Store $store, User $user, array $permissions, string $status = StoreUser::STATUS_ACTIVE): void
    {
        $this->attach($store, $user, Store::ROLE_MEMBER, $status);

        app(StoreMemberPermissionSync::class)->sync(
            $store,
            $user,
            $permissions,
            StoreMemberAccess::PRESET_CUSTOM,
            null,
            null,
            $status,
        );
    }

    private function product(Store $store, string $name = 'Permission Product', string $sku = 'PERM-P'): Product
    {
        return Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => null,
            'base_price' => 10,
            'sku' => $sku.'-'.fake()->unique()->numberBetween(100, 999),
            'product_type' => 'physical',
            'status' => true,
            'meta' => [],
        ]);
    }

    /**
     * @return array{0: ShippingZone, 1: ShippingMethod, 2: ShippingPackagePreset}
     */
    private function deliverySetup(Store $store): array
    {
        $zone = ShippingZone::query()->create([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => ['US'],
            'regions' => [],
            'postal_patterns' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $method = ShippingMethod::query()->create([
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'name' => 'Standard delivery',
            'code' => 'standard-'.$store->id,
            'rate_type' => ShippingMethod::RATE_FLAT,
            'flat_rate' => 8,
            'is_active' => true,
            'enabled_for_checkout' => true,
            'sort_order' => 0,
        ]);

        $preset = ShippingPackagePreset::query()->create([
            'store_id' => $store->id,
            'name' => 'Default Box',
            'length' => 12,
            'width' => 9,
            'height' => 4,
            'dimension_unit' => 'IN',
            'weight_value' => 1,
            'weight_unit' => 'LB',
            'is_default' => true,
            'is_active' => true,
        ]);

        return [$zone, $method, $preset];
    }
}
