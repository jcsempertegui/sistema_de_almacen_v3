<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return ($category = $this->route('category')) ? $this->user()->can('update', $category) : $this->user()->can('create', Category::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('categories')->ignore($this->route('category'))],
            'name' => ['required', 'string', 'max:150', Rule::unique('categories')->ignore($this->route('category'))],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['code' => 'código', 'description' => 'descripción']);
    }
}
