<?php

namespace Tests\Unit;

use Tests\TestCase;

class PlatformTypographyTest extends TestCase
{
    public function test_platform_uses_manrope_primary_and_inter_secondary(): void
    {
        $fonts = (string) file_get_contents(base_path('resources/views/partials/platform-fonts.blade.php'));
        $css = (string) file_get_contents(base_path('resources/css/app.css'));

        $this->assertStringContainsString('family=Manrope', $fonts);
        $this->assertStringContainsString('family=Inter', $fonts);
        $this->assertStringNotContainsString('family=Poppins', $fonts);
        $this->assertStringContainsString("--font-heading: 'Manrope'", $css);
        $this->assertStringContainsString("--font-primary: 'Manrope'", $css);
        $this->assertStringContainsString("--font-sans: 'Manrope'", $css);
        $this->assertStringContainsString("--font-secondary: 'Inter'", $css);
        $this->assertStringContainsString('--font-weight-semibold: 500', $css);
        $this->assertStringContainsString('--font-weight-heading: 600', $css);
        $this->assertStringContainsString('.merchant-topbar h1', $css);
        $this->assertStringNotContainsString("--font-heading: 'Poppins'", $css);
        $this->assertStringNotContainsString("--font-sans: 'Inter'", $css);
    }
}
