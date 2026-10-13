<?php

namespace App\Http\Requests;

class RoleStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('role'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
