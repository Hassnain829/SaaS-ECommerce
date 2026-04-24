<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Str;

/**
 * Single-product recovery for preserved import_extra rows (no schema changes).
 */
final class ImportExtraWorkspaceActions
{
    /**
     * @return array{ok: bool, message: string, key?: string}
     */
    public static function promoteToAdditionalDetails(Product $product, Store $store, string $sourceKey): array
    {
        if ((int) $product->store_id !== (int) $store->id) {
            return ['ok' => false, 'message' => 'Product does not belong to this store.'];
        }

        $meta = is_array($product->meta) ? $product->meta : [];
        $extra = is_array($meta['import_extra'] ?? null) ? $meta['import_extra'] : [];
        if (! array_key_exists($sourceKey, $extra)) {
            return ['ok' => false, 'message' => 'That imported field was not found on this product.'];
        }

        $value = $extra[$sourceKey];
        if (ProductCustomFieldHelper::isEmptyStoredValue($value)) {
            return ['ok' => false, 'message' => 'That field has no value to copy.'];
        }

        $baseKey = self::normalizePromotedKey($sourceKey);
        if ($baseKey === '' || ! ProductCustomFieldHelper::isValidKey($baseKey)) {
            return ['ok' => false, 'message' => 'Could not create a safe field name from this column. Pick a shorter name in the editor if needed.'];
        }
        if (! ProductCustomFieldHelper::isAllowedKey($baseKey)) {
            return ['ok' => false, 'message' => 'That name is reserved for catalog data. Rename it in the full product editor.'];
        }

        $cf = is_array($meta['custom_fields'] ?? null) ? $meta['custom_fields'] : [];
        $targetKey = self::uniqueCustomFieldKey($baseKey, $cf);

        $cf[$targetKey] = self::coercePromotedValue($value);
        $meta['custom_fields'] = $cf;
        $product->update(['meta' => $meta]);

        return [
            'ok' => true,
            'message' => 'Added to editable additional details. The original import reference is still listed under Advanced imported data.',
            'key' => $targetKey,
        ];
    }

    /**
     * @return array{ok: bool, message: string, category_id?: int}
     */
    public static function applyAsCatalogCategory(Product $product, Store $store, string $sourceKey): array
    {
        if ((int) $product->store_id !== (int) $store->id) {
            return ['ok' => false, 'message' => 'Product does not belong to this store.'];
        }

        if (! ImportExtraColumnHints::looksLikeCategoryKey($sourceKey)) {
            return ['ok' => false, 'message' => 'This field is not offered as a category shortcut. Create categories manually or map the column on your next import.'];
        }

        $meta = is_array($product->meta) ? $product->meta : [];
        $extra = is_array($meta['import_extra'] ?? null) ? $meta['import_extra'] : [];
        if (! array_key_exists($sourceKey, $extra)) {
            return ['ok' => false, 'message' => 'That imported field was not found on this product.'];
        }

        $raw = $extra[$sourceKey];
        $names = self::splitCategoryNames($raw);
        if ($names === []) {
            return ['ok' => false, 'message' => 'No category text was found in that field.'];
        }

        $categoryIds = [];
        foreach ($names as $name) {
            $categoryIds[] = self::findOrCreateStoreCategory($store, $name);
        }
        $categoryIds = array_values(array_unique(array_filter($categoryIds)));

        if ($categoryIds === []) {
            return ['ok' => false, 'message' => 'Could not create categories from that value.'];
        }

        $product->categories()->syncWithoutDetaching($categoryIds);

        return [
            'ok' => true,
            'message' => 'Catalog categories updated from this imported value. The spreadsheet reference is still available under Advanced imported data.',
            'category_id' => $categoryIds[0],
        ];
    }

    private static function normalizePromotedKey(string $sourceKey): string
    {
        $k = strtolower(trim($sourceKey));
        $k = preg_replace('/\s+/', '_', $k) ?? $k;
        $k = preg_replace('/[^a-z0-9_.-]/', '_', $k) ?? $k;
        $k = preg_replace('/_+/', '_', $k) ?? $k;
        $k = trim((string) $k, '._-');

        return substr($k, 0, 128);
    }

    /**
     * @param  array<string, mixed>  $existingCustomFields
     */
    private static function uniqueCustomFieldKey(string $base, array $existingCustomFields): string
    {
        if (! array_key_exists($base, $existingCustomFields)) {
            return $base;
        }
        $i = 2;
        while ($i < 500) {
            $candidate = $base.'_'.$i;
            if (strlen($candidate) > 128) {
                $candidate = substr($base, 0, 120).'_'.$i;
            }
            if (! array_key_exists($candidate, $existingCustomFields)) {
                return $candidate;
            }
            $i++;
        }

        return $base.'_'.Str::random(4);
    }

    private static function coercePromotedValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);

            return $isList ? implode(', ', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $value)) : $value;
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function splitCategoryNames(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_array($raw)) {
            $flat = [];
            foreach ($raw as $item) {
                if (is_scalar($item) && trim((string) $item) !== '') {
                    $flat[] = trim((string) $item);
                }
            }

            return $flat;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }
        $parts = preg_split('/\s*[,;|]\s*/', $s) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn ($p) => $p !== ''));
    }

    private static function findOrCreateStoreCategory(Store $store, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        $existing = Category::query()
            ->where('store_id', $store->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $slug = Str::slug($name);
        $base = $slug !== '' ? $slug : 'category';
        $unique = $base;
        $n = 2;
        while (Category::query()->where('store_id', $store->id)->where('slug', $unique)->exists()) {
            $unique = $base.'-'.$n;
            $n++;
        }

        $cat = Category::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => $unique,
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ]);

        return (int) $cat->id;
    }
}
