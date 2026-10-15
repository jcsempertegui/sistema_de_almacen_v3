<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SearchRequest;
use App\Models\CatalogAttribute;
use Illuminate\Validation\Rule;

class AttributeSearchRequest extends SearchRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['data_type' => ['nullable', Rule::in(array_keys(CatalogAttribute::TYPES))]]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['data_type' => 'tipo de dato']);
    }
}
