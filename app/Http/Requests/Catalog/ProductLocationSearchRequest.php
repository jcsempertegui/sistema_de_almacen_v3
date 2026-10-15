<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\ProductLocation;
use Illuminate\Validation\Rule;

class ProductLocationSearchRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProductLocation::class);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]];
    }
}
