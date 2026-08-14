<?php

namespace App\Support\Catalog;

/**
 * Detects a standard WooCommerce product CSV export from header labels.
 */
final class WooCommerceCsvDetector
{
    /**
     * @param  list<string|int|float>  $headers
     */
    public static function detect(array $headers): bool
    {
        $normalized = [];
        foreach ($headers as $header) {
            if (! is_string($header) || $header === '') {
                continue;
            }
            $normalized[ProductImportHeaderNormalizer::normalizeForMatch($header)] = true;
        }

        $hasId = isset($normalized['id']);
        $hasType = isset($normalized['type']);
        $hasRegularPrice = isset($normalized['regular price']);
        $wooSignals = 0;
        foreach ([
            'published',
            'parent',
            'images',
            'visibility in catalog',
            'attribute 1 name',
            'in stock?',
            'tax status',
            'shipping class',
        ] as $signal) {
            if (isset($normalized[$signal])) {
                $wooSignals++;
            }
        }

        return $hasId && $hasType && $hasRegularPrice && $wooSignals >= 2;
    }
}
