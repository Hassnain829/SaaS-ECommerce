<?php

namespace Tests\Unit;

use App\Support\Catalog\ProductRichText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductRichTextTest extends TestCase
{
    #[Test]
    public function it_renders_plain_text_with_line_breaks(): void
    {
        $html = ProductRichText::toSafeHtml("Line one\nLine two");

        $this->assertStringContainsString('Line one', $html);
        $this->assertStringContainsString('<br>', $html);
        $this->assertStringContainsString('Line two', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function it_renders_woocommerce_style_html_and_strips_scripts(): void
    {
        $raw = '<span style="font-weight: 400;">Bottle Size: 100 ml</span>\n\n<ul><li><b>Fast-acting</b> relief</li></ul><script>alert(1)</script>';

        $html = ProductRichText::toSafeHtml($raw);

        $this->assertStringContainsString('Bottle Size: 100 ml', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>', $html);
        $this->assertStringContainsString('<b>', $html);
        $this->assertStringContainsString('Fast-acting', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('\\n', $html);
    }

    #[Test]
    public function it_prepares_html_for_storage_sanitized(): void
    {
        $stored = ProductRichText::prepareForStorage('<p>Safe</p><script>bad()</script>');

        $this->assertNotNull($stored);
        $this->assertStringContainsString('<p>', $stored);
        $this->assertStringContainsString('Safe', $stored);
        $this->assertStringNotContainsString('<script', strtolower((string) $stored));
    }

    #[Test]
    public function it_keeps_plain_text_storage_without_wrapping_tags(): void
    {
        $stored = ProductRichText::prepareForStorage("Simple product note\nSecond line");

        $this->assertSame("Simple product note\nSecond line", $stored);
    }
}
