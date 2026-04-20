<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Store;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Deleting a tag removes `product_tags` rows (FK cascade on `tag_id`).
     * Products stay in the catalog; they simply no longer carry this label.
     */
    public function store(StoreTagRequest $request): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $userId = $request->user()?->id;
        $validated = $request->validated();

        Tag::query()->create([
            'store_id' => $currentStore->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()
            ->route('products')
            ->with('success', 'Tag “' . $validated['name'] . '” was saved.')
            ->with('success_title', 'Tag saved')
            ->with('success_meta', $validated['name']);
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureTagInCurrentStore($tag, $currentStore);
        $userId = $request->user()?->id;
        $validated = $request->validated();

        $tag->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'updated_by' => $userId,
        ]);

        return redirect()
            ->route('products')
            ->with('success', 'Tag “' . $validated['name'] . '” was updated.')
            ->with('success_title', 'Tag updated')
            ->with('success_meta', $validated['name']);
    }

    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureTagInCurrentStore($tag, $currentStore);

        $name = $tag->name;
        $tag->delete();

        return redirect()
            ->route('products')
            ->with('success', "Tag “{$name}” was removed.")
            ->with('success_title', 'Tag removed')
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

    private function ensureTagInCurrentStore(Tag $tag, Store $currentStore): void
    {
        if ((int) $tag->store_id !== (int) $currentStore->id) {
            abort(403, 'This tag does not belong to the current store.');
        }
    }
}
