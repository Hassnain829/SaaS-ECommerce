<?php

namespace App\Http\Requests;

use App\Models\Store;
use App\Models\TaxRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('currentStore') instanceof Store;
    }

    protected function prepareForValidation(): void
    {
        $country = TaxRate::normalizeCountryCode($this->input('country_code'));
        $region = TaxRate::normalizeRegionCode($this->input('region_code'));

        $this->merge([
            'country_code' => $country,
            'region_code' => $region,
            'is_active' => $this->boolean('is_active', true),
            'priority' => $this->input('priority', 100),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Store|null $store */
        $store = $this->attributes->get('currentStore');
        $storeId = $store?->id;

        return [
            'country_code' => [
                'required',
                'string',
                'size:2',
                'alpha',
                Rule::unique('tax_rates')
                    ->where(fn ($query) => $query
                        ->where('store_id', $storeId)
                        ->where('country_code', $this->input('country_code'))
                        ->where('region_code', $this->input('region_code', ''))),
            ],
            'region_code' => ['nullable', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:120'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country_code.size' => 'Enter a two-letter country code.',
            'country_code.alpha' => 'Enter a two-letter country code.',
            'country_code.unique' => 'A tax rate already exists for this country and region.',
            'rate_percent.min' => 'Enter a rate between 0 and 100.',
            'rate_percent.max' => 'Enter a rate between 0 and 100.',
            'rate_percent.decimal' => 'Enter a rate between 0 and 100 with up to four decimal places.',
        ];
    }
}
