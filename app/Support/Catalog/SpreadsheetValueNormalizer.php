<?php

namespace App\Support\Catalog;

/**
 * Normalizes messy spreadsheet cell values before validation and persistence.
 */
final class SpreadsheetValueNormalizer
{
    /**
     * Strip common currency / label noise and thousands separators for numeric parsing.
     */
    public static function stripNumericNoise(string $value): string
    {
        $value = trim($value);
        // Remove letters (PKR, USD, EUR, Rs, etc.) — keep digits, minus, dot, comma
        $value = preg_replace('/[^\d\.,\-]/u', '', $value) ?? '';
        $value = str_replace(',', '', $value);

        return trim($value);
    }

    public static function normalizeInteger(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        $clean = self::stripNumericNoise($s);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return null;
        }

        if (! is_numeric($clean)) {
            return null;
        }

        $f = (float) $clean;
        if (! is_finite($f)) {
            return null;
        }

        $rounded = round($f);
        if (abs($f - $rounded) > 1e-6) {
            return null;
        }

        if ($rounded > PHP_INT_MAX || $rounded < PHP_INT_MIN) {
            return null;
        }

        return (int) $rounded;
    }

    public static function isValidIntegerCell(?string $raw): bool
    {
        return self::normalizeInteger($raw) !== null || trim((string) $raw) === '';
    }

    public static function normalizeDecimal(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }

        $clean = self::stripNumericNoise($s);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return null;
        }

        if (! is_numeric($clean)) {
            return null;
        }

        $f = (float) $clean;

        return is_finite($f) ? $f : null;
    }

    public static function isValidDecimalCell(?string $raw): bool
    {
        if (trim((string) $raw) === '') {
            return true;
        }

        return self::normalizeDecimal($raw) !== null;
    }

    public static function normalizeBoolean(?string $raw): ?bool
    {
        if ($raw === null) {
            return null;
        }
        $s = strtolower(trim((string) $raw));
        if ($s === '') {
            return null;
        }

        if (in_array($s, ['1', 'true', 'yes', 'y', 'on', 'published', 'active', 'visible'], true)) {
            return true;
        }
        if (in_array($s, ['0', 'false', 'no', 'n', 'off', 'draft', 'hidden', 'inactive'], true)) {
            return false;
        }

        return null;
    }
}
