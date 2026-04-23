<?php

namespace App\Support\Catalog;

use App\Catalog\ProductImportField;

/**
 * Shrinks per-row JSON stored on product_import_rows so bulk inserts stay under
 * MySQL max_allowed_packet and similar limits, while keeping enough for retries/debug.
 */
final class ProductImportRowPayloadSanitizer
{
    /**
     * @param  list<string>  $headers
     * @param  list<string>  $cells
     * @param  array<string, string>  $columnMapping
     * @param  list<array{source: string, key: string, scope: string}>  $normalizedCustomMappings
     * @return array{payload: array{cells: list<string>, meta: array<string, mixed>}}
     */
    public static function slimForInsert(
        array $headers,
        array $cells,
        array $columnMapping,
        array $normalizedCustomMappings,
    ): array {
        $headerCount = count($headers);
        $cells = array_values($cells);
        while (count($cells) < $headerCount) {
            $cells[] = '';
        }
        if (count($cells) > $headerCount) {
            $cells = array_slice($cells, 0, $headerCount);
        }

        $headerToFields = [];
        foreach ($columnMapping as $field => $headerName) {
            if (! is_string($headerName) || $headerName === '') {
                continue;
            }
            if (! isset($headerToFields[$headerName])) {
                $headerToFields[$headerName] = [];
            }
            $headerToFields[$headerName][] = (string) $field;
        }

        $customSources = [];
        foreach ($normalizedCustomMappings as $row) {
            if (! is_array($row)) {
                continue;
            }
            $src = trim((string) ($row['source'] ?? ''));
            if ($src !== '') {
                $customSources[$src] = true;
            }
        }

        $out = [];
        $anyTruncated = false;
        foreach ($headers as $i => $headerName) {
            $headerName = is_string($headerName) ? $headerName : '';
            $raw = isset($cells[$i]) ? (string) $cells[$i] : '';
            $before = $raw;
            $raw = self::redactLocalPaths($raw);

            $fields = $headerToFields[$headerName] ?? [];
            $isMapped = $headerName !== '' && $fields !== [];
            $isCustom = $headerName !== '' && isset($customSources[$headerName]);

            $maxLen = self::resolveMaxLength($fields, $isMapped, $isCustom);
            if (self::fieldsContain($fields, ProductImportField::IMAGE_URLS)) {
                $raw = self::truncateImageUrlCell($raw);
            }
            $raw = self::mbTrunc($raw, $maxLen);
            if ($raw !== $before) {
                $anyTruncated = true;
            }
            $out[] = $raw;
        }

        $payload = [
            'cells' => $out,
            'meta' => [
                'truncated' => $anyTruncated,
                'header_count' => $headerCount,
            ],
        ];

        self::enforceMaxEncodedBytes($payload);

        return ['payload' => $payload];
    }

    /**
     * @param  list<string>  $fields
     */
    private static function fieldsContain(array $fields, string $needle): bool
    {
        return in_array($needle, $fields, true);
    }

    /**
     * @param  list<string>  $fields
     */
    private static function resolveMaxLength(array $fields, bool $isMapped, bool $isCustom): int
    {
        if ($isCustom) {
            return max(200, (int) config('product_import.row_payload_max_chars_custom_source', 2000));
        }
        if (! $isMapped) {
            return max(100, (int) config('product_import.row_payload_max_chars_unmapped', 400));
        }

        $default = (int) config('product_import.row_payload_max_chars_mapped_default', 2000);
        $max = 0;
        foreach ($fields as $field) {
            $max = max($max, self::maxCharsForMappedField((string) $field, $default));
        }

        return $max > 0 ? $max : $default;
    }

    private static function maxCharsForMappedField(string $field, int $default): int
    {
        if ($field === ProductImportField::DESCRIPTION) {
            return max(200, (int) config('product_import.row_payload_max_chars_description', 4000));
        }
        if ($field === ProductImportField::SHORT_DESCRIPTION) {
            return max(200, (int) config('product_import.row_payload_max_chars_short_description', 1500));
        }
        if ($field === ProductImportField::IMAGE_URLS) {
            return max(500, (int) config('product_import.row_payload_max_chars_image_urls_field', 8000));
        }
        if (in_array($field, [
            ProductImportField::PRODUCT_NAME,
            ProductImportField::SKU,
            ProductImportField::VARIANT_SKU,
            ProductImportField::BARCODE,
            ProductImportField::BASE_PRICE,
            ProductImportField::STOCK,
            ProductImportField::LOW_STOCK_THRESHOLD,
            ProductImportField::PRODUCT_TYPE,
            ProductImportField::STATUS,
            ProductImportField::VISIBILITY,
        ], true)) {
            return max(64, (int) config('product_import.row_payload_max_chars_short_field', 512));
        }
        if (in_array($field, [
            ProductImportField::CATEGORY,
            ProductImportField::BRAND,
            ProductImportField::TAGS,
            ProductImportField::COMPARE_AT_PRICE,
            ProductImportField::COST_PRICE,
        ], true)) {
            return max(200, (int) config('product_import.row_payload_max_chars_medium_field', 2000));
        }

        return max(200, $default);
    }

    private static function redactLocalPaths(string $value): string
    {
        if (strlen($value) < 80) {
            return $value;
        }
        $patterns = [
            '/[A-Za-z]:\\\\[^|]+\\\\[^|]{10,}/u',
            '/(?:\\\\|\/)(?:storage|private|product-imports)[^|]{20,}/i',
            '/\/(?:var|home|Users)\/[^|]{20,}/',
        ];
        foreach ($patterns as $p) {
            $value = preg_replace($p, '[local-path omitted]', $value) ?? $value;
        }

        return $value;
    }

    private static function truncateImageUrlCell(string $value): string
    {
        $maxUrls = max(1, min(100, (int) config('product_import.row_payload_max_image_urls_kept', 20)));
        $perUrl = max(64, min(2000, (int) config('product_import.row_payload_max_chars_per_image_url', 512)));
        $maxTotal = max(500, (int) config('product_import.row_payload_max_chars_image_urls_field', 8000));

        $parts = preg_split('/[\r\n|;,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $u = trim($p);
            if ($u === '') {
                continue;
            }
            if (count($out) >= $maxUrls) {
                break;
            }
            $out[] = self::mbTrunc($u, $perUrl);
        }
        $joined = implode('|', $out);

        return self::mbTrunc($joined, $maxTotal);
    }

    private static function mbTrunc(string $value, int $maxChars): string
    {
        if ($maxChars < 1 || mb_strlen($value) <= $maxChars) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maxChars - 1)).'…';
    }

    /**
     * @param  array{cells: list<string>, meta: array<string, mixed>}  $payload
     */
    private static function enforceMaxEncodedBytes(array &$payload): void
    {
        $maxBytes = max(4096, min(262144, (int) config('product_import.row_payload_max_json_bytes', 32768)));
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded !== false && strlen($encoded) <= $maxBytes) {
            return;
        }

        $payload['meta']['size_reduced'] = true;
        foreach ($payload['cells'] as $i => $cell) {
            $payload['cells'][$i] = self::mbTrunc((string) $cell, 200);
        }
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded !== false && strlen($encoded) <= $maxBytes) {
            return;
        }

        $hc = count($payload['cells']);
        $payload['cells'] = array_fill(0, $hc, '');
        $payload['meta']['cells_cleared_for_size'] = true;
    }
}
