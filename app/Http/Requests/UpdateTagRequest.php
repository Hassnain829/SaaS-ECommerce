<?php

namespace App\Http\Requests;

use App\Models\Store;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $currentStore = $this->attributes->get('currentStore');
        $tag = $this->route('tag');

        if (! $currentStore instanceof Store || ! $tag instanceof Tag) {
            return false;
        }

        return (int) $tag->store_id === (int) $currentStore->id;
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('slug', ''));
        if ($slug === '') {
            $base = Str::slug((string) $this->input('name')) ?: 'tag';
            $this->merge(['slug' => $base]);
        } else {
            $this->merge(['slug' => $slug]);
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

        /** @var Tag|null $tag */
        $tag = $this->route('tag');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                Rule::unique('tags', 'slug')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($tag?->id),
            ],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
