<?php

namespace App\Support;

use App\Catalog\ProductImportField;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Merchant "additional details" stored on products.meta.custom_fields and variant meta.
 * Key rules align with catalog import custom mappings (no schema changes).
 */
final class ProductCustomFieldHelper
{
    /**
     * @return array<string, true>
     */
    public static function reservedKeySet(): array
    {
        static $set = null;
        if ($set !== null) {
            return $set;
        }

        $reserved = [];
        foreach (array_keys(ProductImportField::labels()) as $slug) {
            $reserved[strtolower((string) $slug)] = true;
        }
        foreach ([
            'catalog',
            'import_extra',
            'custom_fields',
            'import_variant_options',
            'stock_alert',
            'default_stock',
            'image_path',
            'image_paths',
            'image_urls',
        ] as $top) {
            $reserved[$top] = true;
        }
        $set = $reserved;

        return $set;
    }

    public static function isValidKey(string $key): bool
    {
        return preg_match('/^[a-zA-Z0-9_.-]{1,128}$/', $key) === 1;
    }

    public static function isAllowedKey(string $key): bool
    {
        return ! isset(self::reservedKeySet()[strtolower($key)]);
    }

    public static function isEmptyStoredValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     * @return array<string, mixed>
     */
    public static function associativeFromEditorRows(?array $rows): array
    {
        if ($rows === null || $rows === []) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! self::isValidKey($key) || ! self::isAllowedKey($key)) {
                continue;
            }
            $type = strtolower(trim((string) ($row['type'] ?? 'text')));
            if (! in_array($type, ['text', 'number', 'boolean', 'list'], true)) {
                $type = 'text';
            }
            $raw = (string) ($row['value'] ?? '');
            $decoded = self::decodeEditorValue($type, $raw);
            if (self::isEmptyStoredValue($decoded)) {
                continue;
            }
            $out[$key] = $decoded;
        }

        return $out;
    }

    public static function decodeEditorValue(string $type, string $raw): mixed
    {
        $raw = trim($raw);
        if ($type === 'boolean') {
            $lower = strtolower($raw);
            if ($lower === '') {
                return null;
            }
            if (in_array($lower, ['1', 'true', 'yes', 'on', 'y'], true)) {
                return true;
            }
            if (in_array($lower, ['0', 'false', 'no', 'off', 'n'], true)) {
                return false;
            }

            return null;
        }
        if ($type === 'number') {
            if ($raw === '' || ! is_numeric($raw)) {
                return null;
            }
            $floatVal = (float) $raw;

            return (float) (int) $floatVal === $floatVal ? (int) $floatVal : $floatVal;
        }
        if ($type === 'list') {
            if ($raw === '') {
                return null;
            }
            $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $p): bool => $p !== ''));
            if ($parts === []) {
                return null;
            }
            $parts = array_slice($parts, 0, 50);
            $out = [];
            foreach ($parts as $p) {
                $out[] = mb_substr(strip_tags($p), 0, 200);
            }

            return $out;
        }

        // text
        if ($raw === '') {
            return null;
        }

        return mb_substr(strip_tags($raw), 0, 2000);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  list<string>  $keys
     * @return list<array{key: string, label: string, text: string}>
     */
    public static function listHighlightsForKeys(array $meta, array $keys): array
    {
        $cf = is_array($meta['custom_fields'] ?? null) ? $meta['custom_fields'] : [];
        $chips = [];
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key === '' || ! self::isValidKey($key) || ! array_key_exists($key, $cf)) {
                continue;
            }
            $text = ProductDetailPresenter::formatScalarForDisplay($cf[$key]);
            if ($text === '') {
                continue;
            }
            $chips[] = [
                'key' => $key,
                'label' => ProductDetailPresenter::humanizeKey($key),
                'text' => $text,
            ];
        }

        return $chips;
    }

    /**
     * Portable filter: JSON text contains key token and value token (Day 17 — simple).
     */
    public static function metaJsonContainsCustomField(\Illuminate\Database\Eloquent\Builder $query, string $key, string $value): void
    {
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || $value === '') {
            return;
        }
        $likeKey = '%"'.$key.'"%';
        $likeVal = '%'.addcslashes($value, '%_\\').'%';
        $query->where('products.meta', 'like', $likeKey)
            ->where('products.meta', 'like', $likeVal);
    }

    /**
     * Collect distinct additional-detail keys used on products and variants in a store (recent rows, capped for performance).
     *
     * @return list<string>
     */
    public static function detectCustomFieldKeysForStore(int $storeId, int $maxRowsPerSource = 400): array
    {
        $keys = [];

        $productMetas = Product::query()
            ->where('store_id', $storeId)
            ->orderByDesc('id')
            ->limit($maxRowsPerSource)
            ->pluck('meta');

        foreach ($productMetas as $meta) {
            self::collectKeysFromCustomFieldsBucket(is_array($meta) ? ($meta['custom_fields'] ?? null) : null, $keys);
        }

        $variantMetas = ProductVariant::query()
            ->whereHas('product', static fn ($q) => $q->where('store_id', $storeId))
            ->orderByDesc('id')
            ->limit($maxRowsPerSource)
            ->pluck('meta');

        foreach ($variantMetas as $meta) {
            self::collectKeysFromCustomFieldsBucket(is_array($meta) ? ($meta['custom_fields'] ?? null) : null, $keys);
        }

        $list = array_keys($keys);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * @param  array<string, true>  $keys
     */
    private static function collectKeysFromCustomFieldsBucket(mixed $customFields, array &$keys): void
    {
        if (! is_array($customFields)) {
            return;
        }
        foreach (array_keys($customFields) as $k) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if (! self::isValidKey($k) || ! self::isAllowedKey($k)) {
                continue;
            }
            $keys[$k] = true;
        }
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{value: string, label: string}>
     */
    public static function keyOptionsForSelect(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key === '' || ! self::isValidKey($key) || ! self::isAllowedKey($key)) {
                continue;
            }
            $out[] = [
                'value' => $key,
                'label' => ProductDetailPresenter::humanizeKey($key),
            ];
        }

        return $out;
    }
}
