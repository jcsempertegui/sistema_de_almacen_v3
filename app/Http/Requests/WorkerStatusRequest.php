<?php

namespace App\Http\Requests;

class WorkerStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('worker'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
