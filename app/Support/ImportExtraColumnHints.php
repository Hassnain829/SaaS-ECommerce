<?php

namespace App\Support;

/**
 * Heuristics for merchant recovery actions on preserved import columns.
 */
final class ImportExtraColumnHints
{
    public static function looksLikeCategoryKey(string $key): bool
    {
        $k = strtolower(str_replace([' ', '-'], '_', trim($key)));

        return str_contains($k, 'category')
            || str_contains($k, 'collection')
            || str_contains($k, 'department')
            || str_contains($k, 'taxonomy');
    }

    /**
     * @param  list<string|int>  $headers
     * @return array{variant: bool, image: bool, category_like: bool}
     */
    public static function mappingHeaderSignals(array $headers): array
    {
        $variant = false;
        $image = false;
        $categoryLike = false;

        foreach ($headers as $h) {
            if (! is_string($h) || trim($h) === '') {
                continue;
            }
            $t = strtolower($h);
            if (preg_match('/\b(variant|option|size|color|sku|style)\b/', $t) === 1) {
                $variant = true;
            }
            if (str_contains($t, 'image') || str_contains($t, 'photo') || str_contains($t, 'picture') || str_contains($t, 'url')) {
                $image = true;
            }
            if (self::looksLikeCategoryKey($h)) {
                $categoryLike = true;
            }
        }

        return [
            'variant' => $variant,
            'image' => $image,
            'category_like' => $categoryLike,
        ];
    }
}
