<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Store;
use App\Support\Catalog\CatalogToolsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Deleting a category is blocked while products still reference it (see destroy).
     * Merchants must remove the category from products first.
     */
    public function store(StoreCategoryRequest $request): JsonResponse|RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $validated = $request->validated();

        $category = Category::query()->create([
            'store_id' => $currentStore->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => $validated['status'],
        ]);
        $category->load('parent:id,name');

        $message = 'Category “'.$validated['name'].'” was saved.';

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('category', 'created', $this->itemPayload($category), $message, 201);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Category saved', $validated['name']);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse|RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureCategoryInCurrentStore($category, $currentStore);
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => $validated['status'],
        ]);
        $category->load('parent:id,name');

        $message = 'Category “'.$validated['name'].'” was updated.';

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('category', 'updated', $this->itemPayload($category), $message);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Category updated', $validated['name']);
    }

    public function destroy(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureCategoryInCurrentStore($category, $currentStore);

        if ($category->products()->exists()) {
            $blocked = 'This category is still assigned to products. Remove it from those products first.';

            if ($request->expectsJson()) {
                return CatalogToolsResponse::jsonError($blocked);
            }

            return CatalogToolsResponse::redirectWithErrors($request, ['category' => $blocked]);
        }

        $name = $category->name;
        $id = (int) $category->id;
        $category->delete();

        $message = "Category “{$name}” was removed.";

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('category', 'deleted', [
                'id' => $id,
                'name' => $name,
                'assignable' => false,
            ], $message);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Category removed', 'Catalog updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(Category $category): array
    {
        $label = $category->parent
            ? $category->name.' (in '.$category->parent->name.')'
            : $category->name;

        return [
            'id' => (int) $category->id,
            'name' => $category->name,
            'label' => $label,
            'status' => $category->status,
            'slug' => $category->slug,
            'sort_order' => (int) $category->sort_order,
            'products_count' => (int) ($category->products_count ?? 0),
            'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
            'parent_name' => $category->parent?->name,
            'assignable' => ($category->status ?? '') === 'active',
            'update_url' => route('categories.update', $category),
            'destroy_url' => route('categories.destroy', $category),
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

    private function ensureCategoryInCurrentStore(Category $category, Store $currentStore): void
    {
        if ((int) $category->store_id !== (int) $currentStore->id) {
            abort(403, 'This category does not belong to the current store.');
        }
    }
}
