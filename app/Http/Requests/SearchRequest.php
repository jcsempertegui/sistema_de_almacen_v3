<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SearchRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]];
    }
}
