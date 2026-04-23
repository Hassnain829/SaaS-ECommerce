<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Store;
use Illuminate\Support\Str;

/**
 * Per-import in-memory cache for taxonomy resolution to avoid repeated identical DB lookups.
 */
final class ProductImportTaxonomyCache
{
    /** @var array<string, int> lowercase name => id */
    private array $categories = [];

    /** @var array<string, int> lowercase name => id */
    private array $tags = [];

    /** @var array<string, int> lowercase name => id */
    private array $brands = [];

    public function __construct(
        private readonly Store $store,
        private readonly ?int $userId,
    ) {}

    public function categoryId(string $name): int
    {
        $name = trim($name);
        $key = mb_strtolower($name);
        if ($key === '') {
            return 0;
        }
        if (isset($this->categories[$key])) {
            return $this->categories[$key];
        }

        $existing = Category::query()
            ->where('store_id', $this->store->id)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();
        if ($existing) {
            return $this->categories[$key] = (int) $existing->id;
        }

        $base = Str::slug($name) ?: 'category';
        $slug = $this->uniqueCategorySlug($base);

        return $this->categories[$key] = (int) Category::query()->create([
            'store_id' => $this->store->id,
            'name' => $name,
            'slug' => $slug,
            'parent_id' => null,
            'sort_order' => 0,
            'status' => 'active',
        ])->id;
    }

    public function tagId(string $name): int
    {
        $name = trim($name);
        $key = mb_strtolower($name);
        if ($key === '') {
            return 0;
        }
        if (isset($this->tags[$key])) {
            return $this->tags[$key];
        }

        $existing = Tag::query()
            ->where('store_id', $this->store->id)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();
        if ($existing) {
            return $this->tags[$key] = (int) $existing->id;
        }

        $base = Str::slug($name) ?: 'tag';
        $slug = $this->uniqueTagSlug($base);

        return $this->tags[$key] = (int) Tag::query()->create([
            'store_id' => $this->store->id,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'sort_order' => 0,
            'created_by' => $this->userId,
            'updated_by' => $this->userId,
        ])->id;
    }

    public function brandId(string $name): int
    {
        $name = trim($name);
        $key = mb_strtolower($name);
        if ($key === '') {
            return 0;
        }
        if (isset($this->brands[$key])) {
            return $this->brands[$key];
        }

        $existing = Brand::query()
            ->where('store_id', $this->store->id)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();
        if ($existing) {
            return $this->brands[$key] = (int) $existing->id;
        }

        $base = Str::slug($name) ?: 'brand';
        $slug = $this->uniqueBrandSlug($base);

        return $this->brands[$key] = (int) Brand::query()->create([
            'store_id' => $this->store->id,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'sort_order' => 0,
            'featured' => false,
            'created_by' => $this->userId,
            'updated_by' => $this->userId,
        ])->id;
    }

    private function uniqueBrandSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Brand::query()->where('store_id', $this->store->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function uniqueTagSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Tag::query()->where('store_id', $this->store->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Category::query()->where('store_id', $this->store->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
