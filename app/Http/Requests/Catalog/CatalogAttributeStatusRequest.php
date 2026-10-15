<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;

class CatalogAttributeStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('catalog_attribute'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean'], 'category_id' => ['prohibited'], 'attribute_id' => ['prohibited']];
    }
}
