<?php

namespace App\Http\Requests;

use App\Models\Brand;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $currentStore = $this->attributes->get('currentStore');
        $brand = $this->route('brand');

        if (! $currentStore instanceof Store || ! $brand instanceof Brand) {
            return false;
        }

        return (int) $brand->store_id === (int) $currentStore->id;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug')) {
            $base = Str::slug((string) $this->input('name')) ?: 'brand';
            $this->merge(['slug' => $base]);
        }

        $this->merge([
            'featured' => $this->boolean('featured'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Store|null $store */
        $store = $this->attributes->get('currentStore');
        $storeId = $store?->id;

        /** @var Brand|null $brand */
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:180',
                Rule::unique('brands', 'slug')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($brand?->id),
            ],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,inactive,draft'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
