<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\Store;
use App\Support\Catalog\CatalogToolsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function store(StoreBrandRequest $request): JsonResponse|RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $userId = $request->user()?->id;

        $validated = $request->validated();

        $brand = Brand::query()->create([
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

        $message = 'Brand “'.$validated['name'].'” was saved for '.$currentStore->name.'.';

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('brand', 'created', $this->itemPayload($brand), $message, 201);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Brand saved', $validated['name']);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse|RedirectResponse
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

        $message = 'Brand “'.$validated['name'].'” was updated.';

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('brand', 'updated', $this->itemPayload($brand), $message);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Brand updated', $validated['name']);
    }

    public function destroy(Request $request, Brand $brand): JsonResponse|RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureBrandInCurrentStore($brand, $currentStore);

        if ($brand->products()->exists()) {
            $blocked = 'This brand is still assigned to products. Remove or change the brand on those products first.';

            if ($request->expectsJson()) {
                return CatalogToolsResponse::jsonError($blocked);
            }

            return CatalogToolsResponse::redirectWithErrors($request, ['brand' => $blocked]);
        }

        $name = $brand->name;
        $id = (int) $brand->id;
        $brand->delete();

        $message = "Brand “{$name}” was removed.";

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('brand', 'deleted', [
                'id' => $id,
                'name' => $name,
                'assignable' => false,
            ], $message);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Brand removed', 'Catalog updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(Brand $brand): array
    {
        return [
            'id' => (int) $brand->id,
            'name' => $brand->name,
            'label' => $brand->name,
            'status' => $brand->status,
            'sort_order' => (int) $brand->sort_order,
            'products_count' => (int) ($brand->products_count ?? 0),
            'short_description' => $brand->short_description,
            'slug' => $brand->slug,
            'description' => (string) ($brand->description ?? ''),
            'featured' => (bool) $brand->featured,
            'seo_title' => $brand->seo_title,
            'seo_description' => $brand->seo_description,
            'assignable' => true,
            'update_url' => route('brands.update', $brand),
            'destroy_url' => route('brands.destroy', $brand),
        ];
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
