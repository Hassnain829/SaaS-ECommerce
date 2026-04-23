<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lightweight catalog entry point until a full Product View Page ships on the roadmap.
 */
final class ProductQuickViewController extends Controller
{
    public function show(Request $request, Product $product): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $product->store_id === (int) $store->id, 404);

        $product->load([
            'brand:id,name',
            'categories:id,name',
            'tags:id,name',
            'variants' => fn ($q) => $q->orderBy('id')->select('id', 'product_id', 'sku', 'stock', 'price', 'stock_alert'),
        ]);

        return view('user_view.product_quick_view', [
            'selectedStore' => $store,
            'product' => $product,
        ]);
    }
}
