<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $currentStore = $this->attributes->get('currentStore');
        $category = $this->route('category');

        if (! $currentStore instanceof Store || ! $category instanceof Category) {
            return false;
        }

        return (int) $category->store_id === (int) $currentStore->id;
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug', ''));
        if ($slug === '') {
            $base = Str::slug((string) $this->input('name')) ?: 'category';
            $this->merge(['slug' => $base]);
        } else {
            $this->merge(['slug' => $slug]);
        }

        $parent = $this->input('parent_id');
        if ($parent === '' || $parent === null) {
            $this->merge(['parent_id' => null]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Store|null $store */
        $store = $this->attributes->get('currentStore');
        $storeId = $store?->id;

        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:160',
                Rule::unique('categories', 'slug')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($category?->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'not_in:' . ($category?->id ?? 0),
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('store_id', $storeId)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
