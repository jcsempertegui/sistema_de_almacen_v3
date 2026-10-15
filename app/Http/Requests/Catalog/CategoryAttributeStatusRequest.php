<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;

class CategoryAttributeStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('category_attribute'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean'], 'category_id' => ['prohibited'], 'attribute_id' => ['prohibited']];
    }
}
