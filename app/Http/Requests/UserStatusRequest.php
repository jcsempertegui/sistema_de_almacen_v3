<?php

namespace App\Http\Requests;

class UserStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('user'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
