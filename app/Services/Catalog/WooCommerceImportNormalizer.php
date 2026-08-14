<?php

namespace App\Services\Catalog;

use App\Support\Catalog\ProductImportHeaderNormalizer;

/**
 * Rewrites a WooCommerce product-export row into catalog import cells.
 */
final class WooCommerceImportNormalizer
{
    public const ROLE_SIMPLE = 'simple';

    public const ROLE_VARIABLE = 'variable';

    public const ROLE_VARIATION = 'variation';

    public const ROLE_UNSUPPORTED = 'unsupported';

    /**
     * @var list<string>
     */
    public const UNSUPPORTED_TYPES = [
        'grouped',
        'external',
        'affiliate',
        'subscription',
        'variable-subscription',
        'variable_subscription',
        'booking',
        'bundle',
        'composite',
        'grouped-product',
    ];

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{id_to_sku: array<string, string>, extra_attribute_headers: list<string>}
     */
    public static function buildContext(array $headers, array $rows, int $importId): array
    {
        $idHeader = self::headerNamed($headers, ['id']);
        $skuHeader = self::headerNamed($headers, ['sku']);
        $typeHeader = self::headerNamed($headers, ['type']);
        $idToSku = [];

        foreach ($rows as $index => $row) {
            $type = self::primaryType((string) ($row[$typeHeader] ?? ''));
            if (in_array($type, [self::ROLE_VARIATION, self::ROLE_UNSUPPORTED, ''], true)) {
                continue;
            }
            $wooId = trim((string) ($row[$idHeader] ?? ''));
            $sku = trim((string) ($row[$skuHeader] ?? ''));
            if ($sku === '') {
                $sku = self::generatedSku($wooId, $importId, $index + 1);
            }
            if ($wooId !== '') {
                $idToSku[$wooId] = $sku;
            }
        }

        return [
            'id_to_sku' => $idToSku,
            'extra_attribute_headers' => self::extraAttributeHeaders($headers),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{id_to_sku: array<string, string>, extra_attribute_headers: list<string>}  $context
     * @return array{
     *     row: array<string, string>,
     *     role: string,
     *     unsupported_reason: ?string,
     *     parent_error: ?string,
     *     extra_attributes: array<string, string>,
     *     generated_sku: bool,
     *     original_slug: string
     * }
     */
    public static function normalize(
        array $headers,
        array $row,
        array $context,
        int $importId,
        int $rowNumber,
    ): array {
        $idHeader = self::headerNamed($headers, ['id']);
        $skuHeader = self::headerNamed($headers, ['sku']);
        $typeHeader = self::headerNamed($headers, ['type']);
        $parentHeader = self::headerNamed($headers, ['parent', 'parent sku']);
        $publishedHeader = self::headerNamed($headers, ['published']);
        $regularHeader = self::headerNamed($headers, ['regular price']);
        $saleHeader = self::headerNamed($headers, ['sale price']);
        $virtualHeader = self::headerNamed($headers, ['virtual']);
        $downloadableHeader = self::headerNamed($headers, ['downloadable']);
        $slugHeader = self::headerNamed($headers, ['slug', 'post name']);

        $wooId = trim((string) ($row[$idHeader] ?? ''));
        $typeRaw = strtolower(trim((string) ($row[$typeHeader] ?? '')));
        $role = self::roleFromType($typeRaw);
        $unsupported = $role === self::ROLE_UNSUPPORTED
            ? 'WooCommerce product type “'.($typeRaw !== '' ? $typeRaw : 'unknown').'” is not imported. Only simple, variable, and variation rows are supported.'
            : null;

        $originalSku = trim((string) ($row[$skuHeader] ?? ''));
        $generatedSku = false;
        $sku = $originalSku;
        if ($sku === '' && $role !== self::ROLE_UNSUPPORTED) {
            $sku = $context['id_to_sku'][$wooId] ?? self::generatedSku($wooId, $importId, $rowNumber);
            $generatedSku = true;
        }
        if ($skuHeader !== '') {
            $row[$skuHeader] = $sku;
        }

        $parentRaw = trim((string) ($row[$parentHeader] ?? ''));
        $parentId = self::parentId($parentRaw);
        $parentError = null;
        if ($role === self::ROLE_VARIATION) {
            if ($parentRaw === '') {
                $parentError = 'This variation is missing a parent product. WooCommerce Parent should be the parent ID (for example id:123).';
            } elseif ($parentId !== '' && ! isset($context['id_to_sku'][$parentId])) {
                $parentError = 'The parent product for this variation was not found in the file. Import the matching variable product row, then try again.';
            }
            $parentSku = $parentId !== ''
                ? (string) ($context['id_to_sku'][$parentId] ?? '')
                : $parentRaw;
            if ($parentSku === '' && $parentId !== '') {
                $parentSku = 'woo-'.$parentId;
            }
            if ($parentHeader !== '') {
                $row[$parentHeader] = $parentSku;
            }
        } elseif ($role === self::ROLE_VARIABLE || $role === self::ROLE_SIMPLE) {
            if ($parentHeader !== '') {
                $row[$parentHeader] = $sku;
            }
        }

        if ($publishedHeader !== '') {
            $row[$publishedHeader] = self::publishedLabel((string) ($row[$publishedHeader] ?? ''));
        }

        $regular = trim((string) ($row[$regularHeader] ?? ''));
        $sale = trim((string) ($row[$saleHeader] ?? ''));
        if ($sale !== '' && $regularHeader !== '') {
            $row[$regularHeader] = $sale;
            if ($saleHeader !== '') {
                $row[$saleHeader] = $regular;
            }
        }

        $extraAttributes = [];
        if ($role === self::ROLE_SIMPLE || $role === self::ROLE_VARIABLE) {
            foreach ([1, 2, 3] as $slot) {
                $valueHeader = self::headerNamed($headers, [
                    'attribute '.$slot.' value(s)',
                    'attribute '.$slot.' value',
                    'attribute '.$slot.' values',
                ]);
                $nameHeader = self::headerNamed($headers, ['attribute '.$slot.' name']);
                $attrName = $nameHeader !== '' ? trim((string) ($row[$nameHeader] ?? '')) : '';
                $attrValue = $valueHeader !== '' ? trim((string) ($row[$valueHeader] ?? '')) : '';
                if ($attrName !== '' || $attrValue !== '') {
                    $label = $attrName !== '' ? $attrName : 'Attribute '.$slot;
                    $extraAttributes[$label] = $attrValue;
                }
                if ($valueHeader !== '') {
                    $row[$valueHeader] = '';
                }
                if ($nameHeader !== '') {
                    $row[$nameHeader] = '';
                }
            }
        }

        $productType = 'physical';
        if (str_contains($typeRaw, 'virtual') || self::isYes((string) ($row[$virtualHeader] ?? ''))) {
            $productType = 'virtual';
        } elseif (str_contains($typeRaw, 'downloadable') || self::isYes((string) ($row[$downloadableHeader] ?? ''))) {
            $productType = 'digital';
        }

        $originalSlug = trim((string) ($row[$slugHeader] ?? ''));
        if ($originalSlug === '') {
            $nameHeader = self::headerNamed($headers, ['name', 'product name', 'title']);
            $originalSlug = \Illuminate\Support\Str::slug(trim((string) ($row[$nameHeader] ?? '')));
        }

        foreach ($context['extra_attribute_headers'] as $header) {
            $value = trim((string) ($row[$header] ?? ''));
            if ($value !== '') {
                $extraAttributes[$header] = $value;
            }
        }

        $wooProductId = $role === self::ROLE_VARIATION
            ? ($parentId !== '' ? $parentId : $wooId)
            : $wooId;

        $row['__woo_product_type'] = $productType;
        $row['__woo_role'] = $role;
        $row['__woo_product_id'] = $wooProductId;
        $row['__woo_variation_id'] = $role === self::ROLE_VARIATION ? $wooId : '';
        $row['__woo_original_slug'] = $originalSlug;
        $row['__woo_source_sku'] = $originalSku !== '' ? $originalSku : $sku;
        $row['__woo_extra_attributes'] = $extraAttributes === [] ? '' : (string) json_encode($extraAttributes, JSON_UNESCAPED_UNICODE);

        return [
            'row' => $row,
            'role' => $role,
            'unsupported_reason' => $unsupported,
            'parent_error' => $parentError,
            'extra_attributes' => $extraAttributes,
            'generated_sku' => $generatedSku,
            'original_slug' => $originalSlug,
        ];
    }

    public static function primaryType(string $type): string
    {
        $parts = preg_split('/\s*,\s*/', strtolower(trim($type))) ?: [];

        return trim((string) ($parts[0] ?? ''));
    }

    public static function roleFromType(string $typeRaw): string
    {
        $primary = self::primaryType($typeRaw);
        if ($primary === '' || $primary === 'simple') {
            return self::ROLE_SIMPLE;
        }
        if ($primary === 'variable') {
            return self::ROLE_VARIABLE;
        }
        if ($primary === 'variation') {
            return self::ROLE_VARIATION;
        }

        foreach (self::UNSUPPORTED_TYPES as $unsupported) {
            if ($primary === $unsupported || str_contains($typeRaw, $unsupported)) {
                return self::ROLE_UNSUPPORTED;
            }
        }

        return self::ROLE_UNSUPPORTED;
    }

    public static function generatedSku(string $wooId, int $importId, int $rowNumber): string
    {
        if ($wooId !== '') {
            return 'woo-'.$wooId;
        }

        return 'woo-imp'.$importId.'-r'.$rowNumber;
    }

    /**
     * @param  list<string|int>  $headers
     * @return list<string>
     */
    public static function extraAttributeHeaders(array $headers): array
    {
        $extra = [];
        foreach ($headers as $header) {
            if (! is_string($header) || $header === '') {
                continue;
            }
            $norm = ProductImportHeaderNormalizer::normalizeForMatch($header);
            if (preg_match('/^attribute ([4-9]|[1-9][0-9]+) (name|value|value\(s\)|values|visible|global)$/', $norm) === 1) {
                $extra[] = $header;
            }
        }

        return $extra;
    }

    /**
     * @param  list<string|int>  $headers
     * @param  list<string>  $synonyms
     */
    public static function headerNamed(array $headers, array $synonyms): string
    {
        foreach ($headers as $header) {
            if (! is_string($header) || $header === '') {
                continue;
            }
            $norm = ProductImportHeaderNormalizer::normalizeForMatch($header);
            if (in_array($norm, $synonyms, true)) {
                return $header;
            }
        }

        return '';
    }

    public static function parentId(string $parent): string
    {
        $parent = trim($parent);
        if ($parent === '') {
            return '';
        }
        if (preg_match('/^id:(\d+)$/i', $parent, $matches) === 1) {
            return $matches[1];
        }
        if (ctype_digit($parent)) {
            return $parent;
        }

        return '';
    }

    private static function publishedLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '1' || strcasecmp($value, 'yes') === 0 || strcasecmp($value, 'true') === 0) {
            return 'published';
        }

        return 'draft';
    }

    private static function isYes(string $value): bool
    {
        $value = strtolower(trim($value));

        return in_array($value, ['1', 'yes', 'true'], true);
    }
}
