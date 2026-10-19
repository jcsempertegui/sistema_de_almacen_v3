<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\IndividualUnit;
use Illuminate\Validation\Rule;

class IndividualUnitSearchRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', IndividualUnit::class);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')], 'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')]];
    }
}
