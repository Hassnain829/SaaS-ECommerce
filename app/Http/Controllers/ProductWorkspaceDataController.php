<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Support\ImportExtraWorkspaceActions;
use App\Support\StorePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Store-scoped recovery actions from product workspace (import_extra → editable / categories).
 */
final class ProductWorkspaceDataController extends Controller
{
    public function promoteImportExtra(Request $request, Product $product): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $product->store_id === (int) $store->id, 404);

        $user = $request->user();
        abort_unless(
            $user !== null && $user->hasStorePermission($store, StorePermission::CATALOG_MANAGE),
            403
        );

        $validated = $request->validate([
            'source_key' => ['required', 'string', 'max:200'],
        ]);

        $result = ImportExtraWorkspaceActions::promoteToAdditionalDetails(
            $product,
            $store,
            $validated['source_key']
        );

        return $result['ok']
            ? redirect()->route('products.show', $product)->with('success', $result['message'])
            : redirect()->route('products.show', $product)->withErrors(['import_extra' => $result['message']]);
    }

    public function applyImportCategory(Request $request, Product $product): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $product->store_id === (int) $store->id, 404);

        $user = $request->user();
        abort_unless(
            $user !== null && $user->hasStorePermission($store, StorePermission::CATALOG_MANAGE),
            403
        );

        $validated = $request->validate([
            'source_key' => ['required', 'string', 'max:200'],
        ]);

        $result = ImportExtraWorkspaceActions::applyAsCatalogCategory(
            $product,
            $store,
            $validated['source_key']
        );

        return $result['ok']
            ? redirect()->route('products.show', $product)->with('success', $result['message'])
            : redirect()->route('products.show', $product)->withErrors(['import_extra' => $result['message']]);
    }
}
