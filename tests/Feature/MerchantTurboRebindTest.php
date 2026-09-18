<?php

namespace Tests\Feature;

use Tests\TestCase;

class MerchantTurboRebindTest extends TestCase
{
    public function test_app_js_closes_layers_without_hiding_slide_drawers(): void
    {
        $appJs = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString("import './team-workspace.js'", $appJs);
        $this->assertStringContainsString('resetCachedMerchantUi', $appJs);
        $this->assertStringContainsString('closeMerchantLayers', $appJs);
        $this->assertStringContainsString('showMerchantLayer', $appJs);
        $this->assertStringContainsString("el.classList.contains('ui-drawer-panel')", $appJs);
        $this->assertStringContainsString("el.classList.add('translate-x-full')", $appJs);
        $this->assertStringContainsString("el.classList.remove('hidden', 'is-open', 'flex')", $appJs);
        $this->assertStringContainsString("el.classList.remove('hidden', 'translate-x-full')", $appJs);
        $this->assertStringContainsString("document.addEventListener('turbo:render'", $appJs);
        $this->assertStringContainsString("document.addEventListener('turbo:before-cache'", $appJs);
        $this->assertStringContainsString("const nav = document.getElementById('merchantNav')", $appJs);
        $this->assertStringContainsString("'.js-open-create-store-modal'", $appJs);
        $this->assertStringContainsString("[data-lc-open-add]", $appJs);
        $this->assertStringContainsString("[data-dc-open-add]", $appJs);
        $this->assertStringContainsString("[data-wc-open-replace-key]", $appJs);
        $this->assertStringNotContainsString('delete el.dataset.turboBound', $appJs);
        $this->assertStringNotContainsString("document.addEventListener('DOMContentLoaded'", $appJs);
    }

    public function test_team_workspace_uses_document_delegation_instead_of_domcontentloaded_binds(): void
    {
        $js = (string) file_get_contents(base_path('resources/js/team-workspace.js'));
        $page = (string) file_get_contents(base_path('resources/views/user_view/team_members.blade.php'));

        $this->assertStringContainsString("document.addEventListener('click', handleClick)", $js);
        $this->assertStringContainsString("document.addEventListener('turbo:load', boot)", $js);
        $this->assertStringContainsString("document.addEventListener('turbo:render', boot)", $js);
        $this->assertStringContainsString('turbo:before-cache', $js);
        $this->assertStringContainsString('__teamWorkspaceDocBound', $js);
        $this->assertStringContainsString("remove('hidden', 'translate-x-full')", $js);
        $this->assertStringContainsString('data-team-page', $page);
        $this->assertStringContainsString("@push('overlays')", $page);
        $this->assertStringNotContainsString("document.addEventListener('DOMContentLoaded'", $page);
        $this->assertStringNotContainsString('<script>', $page);
    }

    public function test_overlay_pages_boot_on_turbo_load_and_render(): void
    {
        $helper = (string) file_get_contents(base_path('resources/views/partials/merchant-turbo.blade.php'));
        $this->assertStringContainsString('window.bootMerchantPage', $helper);
        $this->assertStringContainsString("document.addEventListener('turbo:load', run)", $helper);
        $this->assertStringContainsString("document.addEventListener('turbo:render', run)", $helper);

        $pages = [
            'resources/views/user_view/locations.blade.php' => 'locations',
            'resources/views/user_view/settings/coupons.blade.php' => 'coupons',
            'resources/views/user_view/settings/taxes.blade.php' => 'taxes',
            'resources/views/user_view/notifications.blade.php' => 'notifications',
            'resources/views/user_view/payment_settings.blade.php' => 'payments',
            'resources/views/user_view/developer_storefront.blade.php' => 'website-connect',
        ];

        foreach ($pages as $path => $key) {
            $source = (string) file_get_contents(base_path($path));
            $this->assertStringContainsString("window.bootMerchantPage('{$key}'", $source, $path.' should boot after Turbo visits');
        }

        $locations = (string) file_get_contents(base_path('resources/views/user_view/locations.blade.php'));
        $this->assertStringContainsString('window.__locationsOpenAdd', $locations);
        $coupons = (string) file_get_contents(base_path('resources/views/user_view/settings/coupons.blade.php'));
        $this->assertStringContainsString('window.__couponsOpenAdd', $coupons);
        $this->assertStringContainsString("classList.remove('hidden')", $coupons);
    }
}
