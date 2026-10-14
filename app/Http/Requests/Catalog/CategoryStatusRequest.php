<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;

class CategoryStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('category'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
