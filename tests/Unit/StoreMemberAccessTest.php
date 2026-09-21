<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Support\StoreMemberAccess;
use App\Support\StorePermission;
use Tests\TestCase;

class StoreMemberAccessTest extends TestCase
{
    public function test_edit_products_enables_view_products(): void
    {
        $normalized = StoreMemberAccess::normalize(['products.edit']);

        $this->assertContains('products.view', $normalized);
        $this->assertContains('products.edit', $normalized);
    }

    public function test_issue_refunds_enables_orders_and_payments_view(): void
    {
        $normalized = StoreMemberAccess::normalize(['customers.refunds']);

        $this->assertContains('customers.view', $normalized);
        $this->assertContains('orders.view', $normalized);
        $this->assertContains('orders.payments', $normalized);
        $this->assertContains('customers.refunds', $normalized);
    }

    public function test_fulfill_orders_enables_order_and_product_view(): void
    {
        $normalized = StoreMemberAccess::normalize(['fulfillment.fulfill']);

        $this->assertContains('orders.view', $normalized);
        $this->assertContains('products.view', $normalized);
        $this->assertContains('fulfillment.fulfill', $normalized);
        $this->assertNotContains('settings.delivery', $normalized);
        $this->assertNotContains('settings.locations', $normalized);
        $this->assertNotContains('settings.taxes', $normalized);
        $this->assertNotContains('settings.discounts', $normalized);
    }

    public function test_view_only_does_not_keep_order_management(): void
    {
        $normalized = StoreMemberAccess::normalize(['products.view', 'orders.view', 'customers.view']);

        $this->assertNotContains('orders.edit', $normalized);
        $this->assertNotContains('fulfillment.fulfill', $normalized);
        $this->assertNotContains('customers.export', $normalized);
    }

    public function test_operations_preset_includes_delivery_without_taxes_or_discounts(): void
    {
        $normalized = StoreMemberAccess::normalize(StoreMemberAccess::presets()[StoreMemberAccess::PRESET_OPERATIONS]);

        $this->assertContains('settings.delivery', $normalized);
        $this->assertContains('fulfillment.fulfill', $normalized);
        $this->assertNotContains('settings.delivery.delete', $normalized);
        $this->assertNotContains('settings.taxes', $normalized);
        $this->assertNotContains('settings.discounts', $normalized);
        $this->assertNotContains('settings.locations', $normalized);
    }

    public function test_delete_delivery_enables_delivery_manage_and_stays_off_in_manager_fallback(): void
    {
        $normalized = StoreMemberAccess::normalize(['settings.delivery.delete']);

        $this->assertContains('settings.delivery', $normalized);
        $this->assertContains('settings.delivery.delete', $normalized);
        $this->assertSame(['settings.delivery'], StoreMemberAccess::normalize(['settings.delivery']));
        $this->assertNotContains('settings.delivery.delete', StoreMemberAccess::managerFallbackKeys());
    }

    public function test_unknown_or_owner_only_keys_are_ignored(): void
    {
        $normalized = StoreMemberAccess::normalize(['orders.view', 'owner.transfer', 'not.a.permission']);

        $this->assertSame(['orders.view'], $normalized);
    }

    public function test_granular_permissions_imply_existing_middleware_keys(): void
    {
        $coarse = StoreMemberAccess::impliedCoarse(['products.edit', 'orders.draft']);

        $this->assertContains(StorePermission::CATALOG_VIEW, $coarse);
        $this->assertContains(StorePermission::CATALOG_MANAGE, $coarse);
        $this->assertContains(StorePermission::ORDERS_VIEW, $coarse);
        $this->assertNotContains(StorePermission::ORDERS_MANAGE, $coarse);
        $this->assertNotContains(StorePermission::SETTINGS_MANAGE, StoreMemberAccess::impliedCoarse(['settings.discounts']));
        $this->assertNotContains(StorePermission::CATALOG_MANAGE, StoreMemberAccess::impliedCoarse(['products.prices']));
    }

    public function test_sensitive_permissions_are_off_in_presets(): void
    {
        foreach ([StoreMemberAccess::PRESET_VIEW, StoreMemberAccess::PRESET_OPERATIONS, StoreMemberAccess::PRESET_FULL_OPERATIONAL] as $preset) {
            $keys = StoreMemberAccess::normalize(StoreMemberAccess::presets()[$preset]);
            foreach (StoreMemberAccess::sensitiveKeys() as $sensitive) {
                $this->assertNotContains($sensitive, $keys, $preset.' should not include '.$sensitive);
            }
        }
    }

    public function test_sensitive_permissions_are_listed_apart_from_standard_groups(): void
    {
        foreach (StoreMemberAccess::groups() as $group) {
            foreach ($group['permissions'] as $permission) {
                $this->assertFalse($permission['sensitive']);
            }
        }

        $sensitive = array_column(StoreMemberAccess::sensitivePermissions(), 'key');
        $this->assertContains('customers.refunds', $sensitive);
        $this->assertContains('customers.export', $sensitive);
        $this->assertContains('customers.delete', $sensitive);
        $this->assertContains('products.delete', $sensitive);
        $this->assertContains('products.import', $sensitive);
        $this->assertContains('products.inventory.force', $sensitive);
        $this->assertContains('orders.cancel', $sensitive);
        $this->assertContains('orders.export', $sensitive);
        $this->assertContains('notifications.manage', $sensitive);
        $this->assertContains('website.cutover', $sensitive);
        $this->assertContains('settings.delivery.delete', $sensitive);
        $this->assertContains('orders.payments', $sensitive);
        $this->assertContains('fulfillment.labels.purchase', $sensitive);
        $this->assertContains('settings.payments', $sensitive);
        $this->assertContains('settings.carriers', $sensitive);
        $this->assertNotContains('website.token', $sensitive);
        $this->assertContains('team.manage', $sensitive);
        $this->assertNotContains('stores.close', $sensitive);
        $this->assertNotContains('integrations.api', StoreMemberAccess::teamGrantableKeys());
        $this->assertNotContains('integrations.webhooks', StoreMemberAccess::teamGrantableKeys());
        $this->assertNotContains('stores.close', StoreMemberAccess::teamGrantableKeys());
        $this->assertNotContains('stores.create', $sensitive);
        $this->assertNotContains('stores.create', StoreMemberAccess::teamGrantableKeys());
        $this->assertSame([], StoreMemberAccess::normalize(['stores.create']));
        $this->assertSame([], StoreMemberAccess::normalize(['stores.close', 'integrations.api']));
        $this->assertNotContains('billing.manage', $sensitive);
        $this->assertNotContains('billing.view', $sensitive);
        $this->assertNotContains('billing.manage', StoreMemberAccess::teamGrantableKeys());
        $this->assertNotContains('billing.view', StoreMemberAccess::teamGrantableKeys());
        $this->assertSame([], StoreMemberAccess::normalize(['billing.manage']));
        $this->assertNotContains('billing.manage', StoreMemberAccess::catalog()['sensitive']);
        $this->assertNotContains('billing.view', StoreMemberAccess::catalog()['sensitive']);
        $this->assertNotContains('stores.create', StoreMemberAccess::catalog()['sensitive']);
    }

    public function test_merge_editable_permissions_preserves_keys_the_actor_cannot_grant(): void
    {
        $existing = StoreMemberAccess::normalize(['customers.refunds', 'products.view']);
        $requested = StoreMemberAccess::normalize(['products.view', 'orders.view']);
        $grantable = ['products.view', 'orders.view', 'customers.view'];

        $merged = StoreMemberAccess::mergeEditablePermissions($existing, $requested, $grantable);

        $this->assertContains('products.view', $merged);
        $this->assertContains('orders.view', $merged);
        $this->assertContains('customers.refunds', $merged);
        $this->assertContains('orders.payments', $merged);
    }

    public function test_merge_editable_permissions_cannot_add_ungrantable_keys(): void
    {
        $merged = StoreMemberAccess::mergeEditablePermissions(
            ['products.view'],
            ['products.view', 'customers.refunds', 'team.manage'],
            ['products.view', 'orders.view', 'customers.view']
        );

        $this->assertContains('products.view', $merged);
        $this->assertNotContains('customers.refunds', $merged);
        $this->assertNotContains('team.manage', $merged);
    }

    public function test_membership_labels_are_owner_team_member_or_custom_access(): void
    {
        $this->assertSame('Owner', StoreMemberAccess::membershipLabel(Store::ROLE_OWNER));
        $this->assertSame('Team Member', StoreMemberAccess::membershipLabel(Store::ROLE_MEMBER, StoreMemberAccess::PRESET_VIEW));
        $this->assertSame('Custom Access', StoreMemberAccess::membershipLabel(Store::ROLE_MEMBER, StoreMemberAccess::PRESET_CUSTOM));
    }

    public function test_modules_split_view_and_manage_without_sensitive_keys(): void
    {
        $modules = collect(StoreMemberAccess::modules())->keyBy('key');

        $this->assertSame(['products.view'], $modules['products']['view']);
        $this->assertContains('products.edit', $modules['products']['manage']);
        $this->assertSame([], $modules['fulfillment']['view']);
        $this->assertContains('fulfillment.fulfill', $modules['fulfillment']['manage']);
        $this->assertNotContains('settings.delivery', $modules['fulfillment']['manage']);
        $this->assertSame(['settings.delivery'], $modules['delivery']['manage']);
        $this->assertNotContains('settings.delivery.delete', $modules['delivery']['manage']);
        $this->assertSame(['settings.locations'], $modules['locations']['manage']);
        $this->assertSame(['settings.discounts'], $modules['discounts']['manage']);
        $this->assertSame(['settings.taxes'], $modules['taxes']['manage']);
        $this->assertArrayNotHasKey('store', $modules);
        $this->assertNotContains('products.import', $modules['products']['manage']);
        $this->assertSame(['website.view'], $modules['website']['view']);
        $this->assertSame(['website.manage', 'website.plugin', 'website.token'], $modules['website']['manage']);
        $this->assertNotContains('website.cutover', $modules['website']['manage']);
        $this->assertNotContains('orders.cancel', $modules['orders']['manage']);
        $this->assertNotContains('customers.refunds', $modules['customers']['manage']);
        $this->assertSame(['orders.draft', 'orders.edit'], $modules['orders']['manage']);
        $this->assertSame(['team.view'], $modules['team']['view']);
        $this->assertSame([], $modules['team']['manage']);
        $this->assertSame(['security.view'], $modules['admin']['view']);

        foreach (StoreMemberAccess::modules() as $module) {
            foreach (array_merge($module['view'], $module['manage']) as $key) {
                $this->assertNotContains($key, StoreMemberAccess::sensitiveKeys());
            }
        }
    }

    public function test_promoted_sensitive_keys_are_not_left_in_advanced(): void
    {
        $advanced = collect(StoreMemberAccess::advancedCapabilities())->pluck('key')->all();
        $sensitive = StoreMemberAccess::sensitiveKeys();

        $this->assertContains('products.import', $sensitive);
        $this->assertContains('orders.cancel', $sensitive);
        $this->assertContains('website.cutover', $sensitive);
        $this->assertNotContains('products.import', $advanced);
        $this->assertNotContains('orders.cancel', $advanced);
        $this->assertNotContains('website.cutover', $advanced);
    }

    public function test_manager_fallback_keeps_import_and_cancel_without_cutover(): void
    {
        $fallback = StoreMemberAccess::managerFallbackKeys();

        $this->assertContains('products.import', $fallback);
        $this->assertContains('orders.cancel', $fallback);
        $this->assertContains('products.inventory.force', $fallback);
        $this->assertContains('notifications.manage', $fallback);
        $this->assertNotContains('website.cutover', $fallback);
        $this->assertNotContains('orders.export', $fallback);
        $this->assertNotContains('customers.delete', $fallback);
        $this->assertNotContains('website.manage', $fallback);
        $this->assertNotContains('website.token', $fallback);
    }

    public function test_every_grantable_permission_appears_in_team_ui_surface(): void
    {
        $covered = array_fill_keys(StoreMemberAccess::teamUiCoveredKeys(), true);

        foreach (StoreMemberAccess::teamGrantableKeys() as $key) {
            $this->assertArrayHasKey(
                $key,
                $covered,
                $key.' must appear in modules, sensitive controls, advanced capabilities, or a documented preset'
            );
        }
    }
}
