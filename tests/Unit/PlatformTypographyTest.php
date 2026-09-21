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

    public function test_merchant_dashboard_uses_owner_shell_type_tokens(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.merchant-dashboard\s*\{[^}]*font-family:\s*var\(--font-sans\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.merchant-dashboard\s+\.mdash-welcome-title\s+h2\s*\{[^}]*font-family:\s*var\(--font-heading\)[^}]*font-size:\s*var\(--text-title\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.merchant-dashboard\s+\.mdash-card-head\s+h3\s*\{[^}]*font-size:\s*var\(--text-section\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.merchant-dashboard\s+\.mdash-metric\s+strong\s*\{[^}]*font-family:\s*var\(--font-heading\)[^}]*font-weight:\s*600/s',
            $css
        );
        $this->assertStringNotContainsString(
            '.merchant-dashboard .mdash-welcome-title h2 {\n    margin: 0;\n    font-family: var(--font-heading);\n    font-size: clamp(1.5rem',
            $css
        );
    }
}
