<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;

class LocationStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('location'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
