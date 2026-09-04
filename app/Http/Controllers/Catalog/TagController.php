<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Store;
use App\Models\Tag;
use App\Support\Catalog\CatalogToolsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Deleting a tag removes `product_tags` rows (FK cascade on `tag_id`).
     * Products stay in the catalog; they simply no longer carry this label.
     */
    public function store(StoreTagRequest $request): JsonResponse|RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $userId = $request->user()?->id;
        $validated = $request->validated();

        $tag = Tag::query()->create([
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

        $message = 'Tag “'.$validated['name'].'” was saved.';

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('tag', 'created', $this->itemPayload($tag), $message, 201);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Tag saved', $validated['name']);
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse|RedirectResponse
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

        $message = 'Tag “'.$validated['name'].'” was updated.';

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('tag', 'updated', $this->itemPayload($tag), $message);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Tag updated', $validated['name']);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse|RedirectResponse
    {
        $currentStore = $this->requireCurrentStore($request);
        $this->ensureTagInCurrentStore($tag, $currentStore);

        $name = $tag->name;
        $id = (int) $tag->id;
        $tag->delete();

        $message = "Tag “{$name}” was removed.";

        if ($request->expectsJson()) {
            return CatalogToolsResponse::json('tag', 'deleted', [
                'id' => $id,
                'name' => $name,
                'assignable' => false,
            ], $message);
        }

        return CatalogToolsResponse::redirect($request, $message, 'Tag removed', 'Catalog updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(Tag $tag): array
    {
        return [
            'id' => (int) $tag->id,
            'name' => $tag->name,
            'label' => $tag->name,
            'status' => $tag->status,
            'color' => $tag->color,
            'sort_order' => (int) $tag->sort_order,
            'products_count' => (int) ($tag->products_count ?? 0),
            'slug' => $tag->slug,
            'description' => (string) ($tag->description ?? ''),
            'assignable' => true,
            'update_url' => route('tags.update', $tag),
            'destroy_url' => route('tags.destroy', $tag),
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

    private function ensureTagInCurrentStore(Tag $tag, Store $currentStore): void
    {
        if ((int) $tag->store_id !== (int) $currentStore->id) {
            abort(403, 'This tag does not belong to the current store.');
        }
    }
}
