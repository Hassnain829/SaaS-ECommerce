<?php

namespace Tests\Feature;

use Tests\TestCase;

class PlatformBrandingTest extends TestCase
{
    public function test_brand_assets_are_published(): void
    {
        $this->assertFileExists(public_path('images/brand/retailo-wordmark.png'));
        $this->assertFileExists(public_path('images/brand/retailo-wordmark-on-dark.png'));
        $this->assertFileExists(public_path('images/brand/retailo-wordmark-white.png'));
        $this->assertFileExists(public_path('images/brand/retailo-icon.png'));
        $this->assertFileExists(public_path('images/brand/retailo-icon-192.png'));
        $this->assertFileExists(public_path('images/brand/favicon-32.png'));
        $this->assertFileExists(public_path('favicon.ico'));
    }

    public function test_signin_and_register_show_retailo_logo_and_favicon(): void
    {
        $signin = $this->get(route('signin'))
            ->assertOk()
            ->assertSee('images/brand/retailo-wordmark.png', false)
            ->assertSee('images/brand/favicon-32.png', false)
            ->assertSee('Retailo workspace', false)
            ->getContent() ?: '';

        $this->assertStringContainsString('theme-color', $signin);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('images/brand/retailo-wordmark.png', false)
            ->assertSee('images/brand/retailo-wordmark-on-dark.png', false);
    }

    public function test_merchant_and_admin_shells_use_retailo_logo(): void
    {
        $logo = file_get_contents(base_path('resources/views/components/platform/logo.blade.php')) ?: '';
        $merchant = file_get_contents(base_path('resources/views/layouts/user/user-sidebar.blade.php')) ?: '';
        $admin = file_get_contents(base_path('resources/views/layouts/admin/admin-Sidebar.blade.php')) ?: '';
        $css = file_get_contents(base_path('resources/css/app.css')) ?: '';

        $this->assertStringContainsString('images/brand/retailo-wordmark.png', $logo);
        $this->assertStringContainsString('<x-platform.logo', $merchant);
        $this->assertStringContainsString('variant="wordmark-on-dark"', $admin);
        $this->assertStringContainsString('--color-brand: #005645', $css);
        $this->assertStringContainsString('--color-brand-accent: #c9ff5c', $css);
    }
}
