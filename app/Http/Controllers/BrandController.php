<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $userId = $request->user()?->id;

        $validated = $request->validated();

        Brand::query()->create([
            'store_id' => $currentStore->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'featured' => $validated['featured'] ?? false,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()
            ->route('products')
            ->with('success', 'Brand “' . $validated['name'] . '” was saved for ' . $currentStore->name . '.')
            ->with('success_title', 'Brand saved')
            ->with('success_meta', $validated['name']);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureBrandInCurrentStore($brand, $currentStore);

        $userId = $request->user()?->id;
        $validated = $request->validated();

        $brand->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'featured' => $validated['featured'] ?? false,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'updated_by' => $userId,
        ]);

        return redirect()
            ->route('products')
            ->with('success', 'Brand “' . $validated['name'] . '” was updated.')
            ->with('success_title', 'Brand updated')
            ->with('success_meta', $validated['name']);
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureBrandInCurrentStore($brand, $currentStore);

        if ($brand->products()->exists()) {
            return redirect()
                ->route('products')
                ->withErrors([
                    'brand' => 'This brand is still assigned to products. Remove or change the brand on those products first.',
                ]);
        }

        $name = $brand->name;
        $brand->delete();

        return redirect()
            ->route('products')
            ->with('success', "Brand “{$name}” was removed.")
            ->with('success_title', 'Brand removed')
            ->with('success_meta', 'Catalog updated');
    }

    private function requireCurrentStore(Request $request): Store
    {
        $currentStore = $request->attributes->get('currentStore');

        if (! $currentStore instanceof Store) {
            abort(404, 'No active store was found for this request.');
        }

        return $currentStore;
    }

    private function ensureBrandInCurrentStore(Brand $brand, Store $currentStore): void
    {
        if ((int) $brand->store_id !== (int) $currentStore->id) {
            abort(403, 'This brand does not belong to the current store.');
        }
    }
}
