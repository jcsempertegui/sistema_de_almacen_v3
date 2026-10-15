<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;

class ProductStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('product'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean'], 'lock_version' => ['required', 'integer', 'min:1']];
    }
}
