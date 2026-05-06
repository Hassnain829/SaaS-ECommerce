<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttributeController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $attributes = $store->attributes()
            ->with(['terms' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->withCount('productAttributes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('user_view.catalog_attributes', [
            'selectedStore' => $store,
            'attributes' => $attributes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'display_type' => ['required', Rule::in(['text', 'select', 'color', 'number'])],
            'is_filterable' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'terms' => ['nullable', 'string', 'max:2000'],
        ]);

        $slug = $this->uniqueAttributeSlug((int) $store->id, $validated['name']);

        $attribute = Attribute::query()->create([
            'store_id' => $store->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'display_type' => $validated['display_type'],
            'sort_order' => (int) $store->attributes()->max('sort_order') + 1,
            'is_filterable' => $request->has('is_filterable'),
            'is_visible' => $request->has('is_visible'),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->createTerms($attribute, (string) ($validated['terms'] ?? ''), $request->user()?->id);

        app(SecurityLogRecorder::class)->record(
            $request,
            'catalog_attribute_created',
            store: $store,
            metadata: ['attribute_id' => $attribute->id, 'attribute_name' => $attribute->name]
        );

        return back()
            ->with('success', 'Attribute saved.')
            ->with('success_title', 'Catalog attributes');
    }

    public function update(Request $request, Attribute $attribute): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $attribute->store_id === (int) $store->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'display_type' => ['required', Rule::in(['text', 'select', 'color', 'number'])],
            'is_filterable' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
        ]);

        $attribute->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueAttributeSlug((int) $store->id, $validated['name'], (int) $attribute->id),
            'display_type' => $validated['display_type'],
            'is_filterable' => $request->has('is_filterable'),
            'is_visible' => $request->has('is_visible'),
            'updated_by' => $request->user()?->id,
        ]);

        return back()
            ->with('success', 'Attribute updated.')
            ->with('success_title', 'Catalog attributes');
    }

    public function destroy(Request $request, Attribute $attribute): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $attribute->store_id === (int) $store->id, 404);

        $attributeName = $attribute->name;
        $attribute->delete();

        app(SecurityLogRecorder::class)->record(
            $request,
            'catalog_attribute_deleted',
            store: $store,
            metadata: ['attribute_name' => $attributeName]
        );

        return back()
            ->with('success', 'Attribute removed from this store.')
            ->with('success_title', 'Catalog attributes');
    }

    public function storeTerm(Request $request, Attribute $attribute): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $attribute->store_id === (int) $store->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'swatch_value' => ['nullable', 'string', 'max:40', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ]);

        AttributeTerm::query()->create([
            'attribute_id' => $attribute->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueTermSlug($attribute, $validated['name']),
            'swatch_value' => $validated['swatch_value'] ?? null,
            'sort_order' => (int) $attribute->terms()->max('sort_order') + 1,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return back()
            ->with('success', 'Term added.')
            ->with('success_title', 'Catalog attributes');
    }

    private function createTerms(Attribute $attribute, string $rawTerms, ?int $userId): void
    {
        $terms = collect(preg_split('/[,;\n]+/', $rawTerms) ?: [])
            ->map(static fn ($term): string => trim((string) $term))
            ->filter()
            ->unique(fn (string $term): string => mb_strtolower($term))
            ->values();

        foreach ($terms as $index => $term) {
            AttributeTerm::query()->create([
                'attribute_id' => $attribute->id,
                'name' => $term,
                'slug' => $this->uniqueTermSlug($attribute, $term),
                'sort_order' => $index + 1,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function uniqueAttributeSlug(int $storeId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'attribute';
        $slug = $base;
        $i = 1;

        while (Attribute::query()
            ->where('store_id', $storeId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function uniqueTermSlug(Attribute $attribute, string $name): string
    {
        $base = Str::slug($name) ?: 'term';
        $slug = $base;
        $i = 1;

        while ($attribute->terms()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
