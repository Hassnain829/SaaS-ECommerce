<?php

namespace App\Http\Requests;

use App\Models\Store;
use App\Models\TaxSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('currentStore') instanceof Store;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'enabled' => $this->boolean('enabled'),
            'prices_include_tax' => $this->boolean('prices_include_tax'),
            'default_product_taxable' => $this->boolean('default_product_taxable'),
            'shipping_taxable' => $this->boolean('shipping_taxable'),
        ];

        if (! $this->has('calculation_address')) {
            $merge['calculation_address'] = TaxSetting::CALCULATION_ADDRESS_SHIPPING;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'prices_include_tax' => ['required', 'boolean'],
            'default_product_taxable' => ['required', 'boolean'],
            'shipping_taxable' => ['required', 'boolean'],
            'calculation_address' => [
                'required',
                'string',
                'max:32',
                Rule::in([TaxSetting::CALCULATION_ADDRESS_SHIPPING]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'calculation_address.in' => 'Tax calculation address must use the customer shipping address in this release.',
        ];
    }
}
