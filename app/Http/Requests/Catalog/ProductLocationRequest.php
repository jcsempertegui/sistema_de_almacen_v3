<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\ProductLocation;

class ProductLocationRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductLocation::class);
    }

    public function rules(): array
    {
        return ['location_id' => ['required', 'integer', 'exists:locations,id'], 'is_active' => ['required', 'boolean'], 'lock_version' => ['required', 'integer', 'min:1']];
    }
}
