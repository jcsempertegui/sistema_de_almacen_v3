<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\Product;

class ProductCategoryRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return ($product = $this->route('product')) ? $this->user()->can('update', $product) : $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return ['category_id' => ['nullable', 'integer', 'exists:categories,id']];
    }
}
