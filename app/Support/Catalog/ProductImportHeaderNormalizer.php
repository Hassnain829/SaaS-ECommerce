<?php

namespace App\Support\Catalog;

/**
 * Cleans spreadsheet header labels for storage and consistent auto-mapping.
 */
final class ProductImportHeaderNormalizer
{
    /**
     * Trim, strip BOM (first cell), remove zero-width characters, normalize spaces.
     * Preserves casing so column keys match row data in the file.
     */
    public static function trimForStorage(string $header): string
    {
        $h = str_replace("\xC2\xA0", ' ', $header);
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
        $h = self::stripZeroWidthAndBom($h);
        $h = trim($h);
        $h = preg_replace('/\s+/u', ' ', $h) ?? $h;

        return trim($h);
    }

    /**
     * @param  list<string|int|float>  $headers
     * @return list<string|int|float>
     */
    public static function sanitizeHeaderRow(array $headers): array
    {
        $out = [];
        foreach ($headers as $i => $h) {
            if (! is_string($h)) {
                $out[] = $h;

                continue;
            }
            $out[] = self::trimForStorage($h);
        }

        return $out;
    }

    /**
     * Lowercase, unify separators, collapse spaces — for synonym matching only.
     */
    public static function normalizeForMatch(string $header): string
    {
        $h = self::trimForStorage($header);
        $h = str_replace(['_', '-', '–', '—', '.'], ' ', $h);
        $h = preg_replace('/\s+/', ' ', $h) ?? $h;

        return strtolower(trim($h));
    }

    /**
     * True when two non-empty headers are identical ignoring ASCII case (would collide in row arrays).
     *
     * @param  list<string|int|float>  $headers
     */
    public static function hasCaseInsensitiveDuplicateHeaders(array $headers): bool
    {
        $seen = [];
        foreach ($headers as $h) {
            if (! is_string($h) || $h === '') {
                continue;
            }
            $k = strtolower($h);
            if (isset($seen[$k])) {
                return true;
            }
            $seen[$k] = true;
        }

        return false;
    }

    private static function stripZeroWidthAndBom(string $header): string
    {
        return preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}]/u', '', $header) ?? $header;
    }
}
