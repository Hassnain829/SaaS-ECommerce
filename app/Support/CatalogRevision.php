<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CatalogRevision
{
    public static function forStore(Store $store): string
    {
        $stamps = [
            Product::query()->where('store_id', $store->id)->max('updated_at'),
            ProductVariant::query()->where('store_id', $store->id)->max('updated_at'),
            Category::query()->where('store_id', $store->id)->max('updated_at'),
        ];

        if (Schema::hasTable('product_categories')) {
            $stamps[] = DB::table('product_categories')
                ->join('products', 'products.id', '=', 'product_categories.product_id')
                ->where('products.store_id', $store->id)
                ->max('product_categories.updated_at');
        }

        if (Schema::hasTable('product_images')) {
            $stamps[] = DB::table('product_images')
                ->join('products', 'products.id', '=', 'product_images.product_id')
                ->where('products.store_id', $store->id)
                ->max('product_images.updated_at');
        }

        $timestamp = 0;
        foreach ($stamps as $stamp) {
            if ($stamp === null || $stamp === '') {
                continue;
            }
            $timestamp = max($timestamp, (int) strtotime((string) $stamp));
        }

        $count = Product::query()
            ->where('store_id', $store->id)
            ->where('status', true)
            ->count();

        $stock = (int) ProductVariant::query()
            ->where('store_id', $store->id)
            ->sum('stock');

        return 'cat-'.$store->id.'-'.$timestamp.'-'.$count.'-'.$stock;
    }
}
