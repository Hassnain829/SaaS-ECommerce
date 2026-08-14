<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductUrlRedirect;
use App\Models\Store;
use Illuminate\Support\Str;

final class ProductImportSlugRedirectService
{
    public function assignSlug(
        Store $store,
        string $name,
        ?int $ignoreProductId,
        ?string $preferredSlug,
    ): string {
        $preferred = trim((string) $preferredSlug);
        $base = $preferred !== '' ? Str::slug($preferred) : Str::slug($name);
        $base = $base !== '' ? $base : 'product';
        $slug = $base;
        $counter = 1;
        while (Product::query()
            ->where('store_id', $store->id)
            ->where('slug', $slug)
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function record(
        Store $store,
        Product $product,
        ProductImport $import,
        ?string $originalSlug,
        string $destinationSlug,
    ): void {
        $originalSlug = trim((string) $originalSlug);
        if ($originalSlug === '') {
            return;
        }

        $sourceSlug = Str::slug($originalSlug);
        if ($sourceSlug === '') {
            $sourceSlug = $originalSlug;
        }
        $sourcePath = '/product/'.$sourceSlug.'/';

        ProductUrlRedirect::query()->updateOrCreate(
            [
                'store_id' => $store->id,
                'source_path' => $sourcePath,
            ],
            [
                'product_id' => $product->id,
                'product_import_id' => $import->id,
                'source_slug' => $sourceSlug,
                'destination_slug' => $destinationSlug,
            ]
        );
    }
}
