<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * JSON payload for the shared product edit UI (catalog list modal and dedicated edit workspace).
 * Shape must stay aligned with `window.openEdit` / row buttons and the dedicated edit page bootstrap.
 */
final class ProductEditPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function forProduct(Product $product): array
    {
        $product->loadMissing([
            'tags:id,name,store_id',
            'categories:id,name,store_id',
            'variationTypes.options:id,variation_type_id,value,sort_order',
            'variants.options:id,variation_type_id,value',
            'variants.linkedCatalogImage:id,product_id,product_variant_id,image_path,status,sort_order,is_primary',
            'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
        ]);

        $primaryImage = $product->images->first(fn ($img) => $img->is_primary) ?? $product->images->first();
        $primaryVisualState = 'none';
        if ($primaryImage) {
            if ($primaryImage->isReady()) {
                $primaryVisualState = 'ready';
            } elseif ($primaryImage->isPendingVisual()) {
                $primaryVisualState = 'pending';
            } elseif ($primaryImage->isFailed()) {
                $primaryVisualState = 'failed';
            }
        }

        $galleryPaths = $product->images->filter(fn ($img) => $img->isReady())->pluck('image_path')->values()->all();
        $productImageUrl = ($primaryVisualState === 'ready' && $primaryImage && $primaryImage->image_path)
            ? asset('storage/'.$primaryImage->image_path)
            : null;

        $galleryOrdinal = 0;
        $catalogImagesForPicker = $product->images->values()->map(function ($img) use (&$galleryOrdinal) {
            $thumbUrl = $img->isReady() && $img->image_path ? asset('storage/'.$img->image_path) : null;
            $file = $img->image_path ? basename((string) $img->image_path) : '';
            $fileDisp = $file !== '' ? Str::limit($file, 40) : 'Pending upload';
            if ($img->is_primary) {
                $pickerLabel = 'Main product image — '.$fileDisp;
            } else {
                $galleryOrdinal++;
                $pickerLabel = 'Gallery image '.$galleryOrdinal.' — '.$fileDisp;
            }

            return [
                'id' => $img->id,
                'thumb_url' => $thumbUrl,
                'picker_label' => $pickerLabel,
            ];
        })->values()->all();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            'base_price' => (string) $product->base_price,
            'product_type' => $product->product_type,
            'brand_id' => $product->brand_id,
            'tag_ids' => $product->tags->pluck('id')->values()->all(),
            'category_ids' => $product->categories->pluck('id')->values()->all(),
            'custom_fields' => self::editorRowsFromProductMeta($product),
            'stock_alert' => (int) ($product->variants->max('stock_alert') !== null
                ? $product->variants->max('stock_alert')
                : (($product->meta['stock_alert'] ?? 0))),
            'image_url' => $productImageUrl,
            'image_paths' => $galleryPaths,
            'image_urls' => collect($galleryPaths)
                ->map(fn ($path) => asset('storage/'.$path))
                ->values()
                ->all(),
            'catalog_images' => $catalogImagesForPicker,
            'variation_types' => $product->variationTypes->map(fn ($variationType) => [
                'name' => $variationType->name,
                'type' => $variationType->type,
                'options' => $variationType->options->sortBy('sort_order')->pluck('value')->values()->all(),
            ])->values()->all(),
            'variants' => $product->variants->map(function ($variant) use ($product) {
                $optionMap = [];
                foreach ($product->variationTypes as $variationIndex => $variationType) {
                    $selectedOption = $variant->options->first(
                        fn ($option) => (int) $option->variation_type_id === (int) $variationType->id
                    );
                    if ($selectedOption) {
                        $optionMap[$variationIndex] = $variationType->options
                            ->sortBy('sort_order')
                            ->pluck('id')
                            ->search($selectedOption->id);
                    }
                }

                return [
                    'option_map' => $optionMap,
                    'sku' => $variant->sku,
                    'price' => (string) $variant->price,
                    'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : '',
                    'stock' => (string) $variant->stock,
                    'stock_alert' => (int) $variant->stock_alert,
                    'product_image_id' => $variant->linkedCatalogImage?->id,
                ];
            })->values()->all(),
            'update_url' => route('product.update', ['productId' => $product->id]),
            'delete_url' => route('product.destroy', ['productId' => $product->id]),
        ];
    }

    /**
     * @return list<array{key: string, type: string, value: string}>
     */
    private static function editorRowsFromProductMeta(Product $product): array
    {
        $meta = is_array($product->meta) ? $product->meta : [];
        $cf = is_array($meta['custom_fields'] ?? null) ? $meta['custom_fields'] : [];
        $rows = [];
        foreach ($cf as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $rows[] = [
                'key' => $key,
                'type' => self::inferEditorType($value),
                'value' => self::editorValueAsString($value),
            ];
        }
        if ($rows === []) {
            $rows[] = ['key' => '', 'type' => 'text', 'value' => ''];
        }

        return $rows;
    }

    private static function inferEditorType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }
        if (is_array($value)) {
            $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);

            return $isList ? 'list' : 'text';
        }

        return 'text';
    }

    private static function editorValueAsString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_int($value) || is_float($value)) {
            return is_float($value)
                ? rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.')
                : (string) $value;
        }
        if (is_array($value)) {
            $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                return implode(', ', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $value));
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : '';
        }

        return (string) $value;
    }

    /**
     * Merge validated form old input onto the catalog edit payload (dedicated edit page after validation errors).
     *
     * @param  array<string, mixed>  $old
     * @return array<string, mixed>
     */
    public static function withFormOld(Product $product, array $old): array
    {
        $base = self::forProduct($product);
        if ($old === []) {
            return $base;
        }

        foreach (['name', 'description', 'sku', 'base_price', 'stock_alert'] as $key) {
            if (array_key_exists($key, $old)) {
                $base[$key] = $old[$key];
            }
        }

        if (array_key_exists('brand_id', $old)) {
            $brandId = $old['brand_id'];
            $base['brand_id'] = ($brandId === null || $brandId === '') ? null : (int) $brandId;
        }

        if (isset($old['tag_ids']) && is_array($old['tag_ids'])) {
            $base['tag_ids'] = collect($old['tag_ids'])
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        if (isset($old['category_ids']) && is_array($old['category_ids'])) {
            $base['category_ids'] = collect($old['category_ids'])
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        if (array_key_exists('product_type', $old) && is_string($old['product_type'])) {
            $base['product_type'] = $old['product_type'];
        }

        if (! empty($old['variation_types']) && is_array($old['variation_types'])) {
            $mapped = [];
            foreach ($old['variation_types'] as $variationRow) {
                if (! is_array($variationRow)) {
                    continue;
                }
                $options = $variationRow['options'] ?? [];
                $mapped[] = [
                    'name' => (string) ($variationRow['name'] ?? ''),
                    'type' => (string) ($variationRow['type'] ?? 'select'),
                    'options' => is_array($options)
                        ? array_values(array_map(static fn ($v): string => (string) $v, $options))
                        : [],
                ];
            }
            if ($mapped !== []) {
                $base['variation_types'] = $mapped;
            }
        }

        if (isset($old['custom_fields']) && is_array($old['custom_fields'])) {
            $cfRows = [];
            foreach (array_values($old['custom_fields']) as $cfRow) {
                if (! is_array($cfRow)) {
                    continue;
                }
                $cfRows[] = [
                    'key' => (string) ($cfRow['key'] ?? ''),
                    'type' => in_array((string) ($cfRow['type'] ?? 'text'), ['text', 'number', 'boolean', 'list'], true)
                        ? (string) $cfRow['type']
                        : 'text',
                    'value' => (string) ($cfRow['value'] ?? ''),
                ];
            }
            if ($cfRows !== []) {
                $base['custom_fields'] = $cfRows;
            }
        }

        if (! empty($old['variants']) && is_array($old['variants'])) {
            $rows = [];
            foreach (array_values($old['variants']) as $variantRow) {
                if (! is_array($variantRow)) {
                    continue;
                }
                $optionMap = [];
                if (! empty($variantRow['option_map']) && is_array($variantRow['option_map'])) {
                    foreach ($variantRow['option_map'] as $k => $v) {
                        $optionMap[(int) $k] = is_numeric($v) ? (int) $v : $v;
                    }
                }
                $rows[] = [
                    'option_map' => $optionMap,
                    'sku' => (string) ($variantRow['sku'] ?? ''),
                    'price' => isset($variantRow['price']) ? (string) $variantRow['price'] : '',
                    'compare_at_price' => isset($variantRow['compare_at_price']) && $variantRow['compare_at_price'] !== null && $variantRow['compare_at_price'] !== ''
                        ? (string) $variantRow['compare_at_price'] : '',
                    'stock' => isset($variantRow['stock']) ? (string) $variantRow['stock'] : '',
                    'stock_alert' => isset($variantRow['stock_alert']) ? (int) $variantRow['stock_alert'] : 0,
                    'product_image_id' => isset($variantRow['product_image_id']) && $variantRow['product_image_id'] !== '' && $variantRow['product_image_id'] !== null
                        ? (int) $variantRow['product_image_id'] : null,
                ];
            }
            if ($rows !== []) {
                $base['variants'] = $rows;
            }
        }

        return $base;
    }
}
