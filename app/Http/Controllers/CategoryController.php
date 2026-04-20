<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Deleting a category is blocked while products still reference it (see destroy).
     * Merchants must remove the category from products first.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $validated = $request->validated();

        Category::query()->create([
            'store_id' => $currentStore->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'parent_id' => $validated['parent_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('products')
            ->with('success', 'Category “' . $validated['name'] . '” was saved.')
            ->with('success_title', 'Category saved')
            ->with('success_meta', $validated['name']);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
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

        return redirect()
            ->route('products')
            ->with('success', 'Category “' . $validated['name'] . '” was updated.')
            ->with('success_title', 'Category updated')
            ->with('success_meta', $validated['name']);
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureCategoryInCurrentStore($category, $currentStore);

        if ($category->products()->exists()) {
            return redirect()
                ->route('products')
                ->withErrors([
                    'category' => 'This category is still assigned to products. Remove it from those products first.',
                ]);
        }

        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('products')
            ->with('success', "Category “{$name}” was removed.")
            ->with('success_title', 'Category removed')
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

    private function ensureCategoryInCurrentStore(Category $category, Store $currentStore): void
    {
        if ((int) $category->store_id !== (int) $currentStore->id) {
            abort(403, 'This category does not belong to the current store.');
        }
    }
}
