<?php

namespace App\Services\Carriers\FedEx\Validation;

final class FedExLabelArtifactValidator
{
    /**
     * @return array{valid: bool, reason: ?string, detected_dpi: ?int}
     */
    public static function validateScan(string $absolutePath, int $claimedDpi): array
    {
        if ($claimedDpi < 600) {
            return ['valid' => false, 'reason' => 'claimed_dpi_below_minimum', 'detected_dpi' => null];
        }

        if (! is_file($absolutePath) || filesize($absolutePath) <= 0) {
            return ['valid' => false, 'reason' => 'empty_file', 'detected_dpi' => null];
        }

        $mime = (string) mime_content_type($absolutePath);
        if (! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true)) {
            return ['valid' => false, 'reason' => 'unsupported_mime', 'detected_dpi' => null];
        }

        $detectedDpi = self::detectRasterDpi($absolutePath, $mime);
        if ($detectedDpi !== null && $detectedDpi < 600) {
            return ['valid' => false, 'reason' => 'detected_dpi_below_minimum', 'detected_dpi' => $detectedDpi];
        }

        return ['valid' => true, 'reason' => null, 'detected_dpi' => $detectedDpi];
    }

    public static function isValid(string $absolutePath, string $expectedFormat): bool
    {
        return self::validateGeneratedLabel($absolutePath, $expectedFormat)['valid'];
    }

    /**
     * @return array{valid: bool, reason: ?string}
     */
    public static function validateGeneratedLabel(string $absolutePath, string $expectedFormat): array
    {
        if (! is_file($absolutePath) || filesize($absolutePath) <= 0) {
            return ['valid' => false, 'reason' => 'empty_file'];
        }

        $contents = file_get_contents($absolutePath) ?: '';
        $format = strtoupper($expectedFormat);

        return match ($format) {
            'PNG' => self::validatePng($contents),
            'ZPL', 'ZPLII' => self::validateZpl($contents),
            default => self::validatePdf($contents),
        };
    }

    /**
     * @return array{valid: bool, reason: ?string}
     */
    private static function validatePng(string $contents): array
    {
        if (! str_starts_with($contents, "\x89PNG\r\n\x1a\n")) {
            return ['valid' => false, 'reason' => 'invalid_png_signature'];
        }

        if (@getimagesizefromstring($contents) === false) {
            return ['valid' => false, 'reason' => 'undecodable_png'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * @return array{valid: bool, reason: ?string}
     */
    private static function validateZpl(string $contents): array
    {
        if (self::looksLikeBase64Wrapper($contents)) {
            return ['valid' => false, 'reason' => 'zpl_still_base64_wrapped'];
        }

        $trimmed = ltrim($contents);
        if ($trimmed === '') {
            return ['valid' => false, 'reason' => 'empty_zpl'];
        }

        if (! str_contains($trimmed, '^XA') || ! str_contains($trimmed, '^XZ')) {
            return ['valid' => false, 'reason' => 'missing_zpl_markers'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * @return array{valid: bool, reason: ?string}
     */
    private static function validatePdf(string $contents): array
    {
        if (! str_starts_with($contents, '%PDF-')) {
            return ['valid' => false, 'reason' => 'invalid_pdf_signature'];
        }

        if (! str_contains($contents, '%%EOF')) {
            return ['valid' => false, 'reason' => 'missing_pdf_terminator'];
        }

        return ['valid' => true, 'reason' => null];
    }

    private static function looksLikeBase64Wrapper(string $contents): bool
    {
        $trimmed = trim($contents);

        return $trimmed !== ''
            && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $trimmed) === 1
            && ! str_contains($trimmed, '^XA');
    }

    private static function detectRasterDpi(string $absolutePath, string $mime): ?int
    {
        if ($mime === 'application/pdf') {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if (! is_array($info)) {
            return null;
        }

        $dpi = (int) round(max((float) ($info['resolution_x'] ?? 0), (float) ($info['resolution_y'] ?? 0)));
        if ($dpi <= 0) {
            return null;
        }

        return $dpi;
    }
}
