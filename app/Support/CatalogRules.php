<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Validation\Rule;

final class CatalogRules
{
    /**
     * Rules for a product's brand_id: optional, must belong to the given store.
     */
    public static function brandIdForStore(Store $store): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('brands', 'id')->where('store_id', $store->id),
        ];
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public static function tagIdsForStore(Store $store): array
    {
        return [
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where(fn ($query) => $query->where('store_id', $store->id)),
            ],
        ];
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public static function categoryIdsForStore(Store $store): array
    {
        return [
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('store_id', $store->id)),
            ],
        ];
    }
}
