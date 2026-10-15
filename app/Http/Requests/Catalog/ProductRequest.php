<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductRequest extends SgaFormRequest
{
    public function formUrl(): string
    {
        $category = $this->input('category_id');

        return ($product = $this->route('product'))
            ? route('products.edit', $product)
            : route('products.create', (is_string($category) || is_int($category)) && ctype_digit((string) $category) ? ['category_id' => $category] : []);
    }

    protected function getRedirectUrl()
    {
        return $this->formUrl();
    }

    public function authorize(): bool
    {
        return ($product = $this->route('product')) ? $this->user()->can('update', $product) : $this->user()->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('internal_code'))) {
            $this->merge(['internal_code' => Str::upper(trim($this->input('internal_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'internal_code' => ['required', 'string', 'max:60', Rule::unique('products')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'measurement_unit_id' => ['required', 'integer', 'exists:measurement_units,id'],
            'brand' => ['nullable', 'string', 'max:100'], 'manufacturer_reference' => ['nullable', 'string', 'max:100'],
            'control_mode' => ['required', Rule::in(array_keys(Product::MODES))],
            'is_loanable' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
            'lock_version' => [$this->route('product') ? 'required' : 'prohibited', 'integer', 'min:1'],
            'values' => ['sometimes', 'array', 'max:500'],
            'location_ids' => [$this->route('product') ? 'prohibited' : 'sometimes', 'array', 'max:100'],
            'location_ids.*' => ['required', 'integer', 'distinct', 'exists:locations,id'],
            'duplicate_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['internal_code' => 'código interno', 'category_id' => 'categoría', 'measurement_unit_id' => 'unidad de medida', 'description' => 'descripción', 'control_mode' => 'modo de control', 'brand' => 'marca', 'manufacturer_reference' => 'referencia de fabricante', 'values' => 'atributos técnicos', 'lock_version' => 'versión de edición'];
    }
}
