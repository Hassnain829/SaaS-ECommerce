<?php

namespace App\Support\Catalog;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Product description / short-description rich text.
 *
 * Supports plain text and WooCommerce-style HTML safely:
 * - normalize spreadsheet escape sequences (literal "\n")
 * - sanitize HTML on write and display
 * - render plain text with preserved line breaks
 */
final class ProductRichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        // Real newlines first, then literal escape sequences common in CSV exports.
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/\\\\r\\\\n|\\\\n|\\\\r/u', "\n", $value) ?? $value;
        $value = preg_replace('/\\\\t/u', "\t", $value) ?? $value;

        return trim($value);
    }

    public static function containsHtml(string $value): bool
    {
        return (bool) preg_match('/<\s*[a-zA-Z](?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', $value);
    }

    public static function sanitize(string $value): string
    {
        return trim(self::sanitizer()->sanitize($value));
    }

    /**
     * Normalize and (when HTML) sanitize before persisting.
     */
    public static function prepareForStorage(?string $value): ?string
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return null;
        }

        if (self::containsHtml($normalized)) {
            $clean = self::sanitize($normalized);

            return $clean === '' ? null : $clean;
        }

        return $normalized;
    }

    /**
     * Trusted HTML safe for Blade {!! !!} output.
     * Plain text is escaped and line breaks preserved.
     */
    public static function toSafeHtml(?string $value): string
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return '';
        }

        if (self::containsHtml($normalized)) {
            return self::sanitize($normalized);
        }

        return nl2br(e($normalized), false);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer instanceof HtmlSanitizer) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowStaticElements()
            ->allowRelativeLinks(true)
            ->allowRelativeMedias(true)
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            // WooCommerce often wraps copy in span/div; keep presentational class only.
            ->allowAttribute('class', '*')
            ->withMaxInputLength(200_000);

        self::$sanitizer = new HtmlSanitizer($config);

        return self::$sanitizer;
    }
}
