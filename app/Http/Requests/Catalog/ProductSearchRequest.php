<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;

class ProductSearchRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Product::class);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'category_id' => ['nullable', 'integer', 'exists:categories,id'], 'measurement_unit_id' => ['nullable', 'integer', 'exists:measurement_units,id'], 'control_mode' => ['nullable', Rule::in(array_keys(Product::MODES))]];
    }
}
