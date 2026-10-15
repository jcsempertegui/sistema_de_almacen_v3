<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\CatalogAttribute;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogAttributeRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return ($attribute = $this->route('catalog_attribute')) ? $this->user()->can('update', $attribute) : $this->user()->can('create', CatalogAttribute::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
        // Omitting an optional unit explicitly clears it; the action checks history.
        if (! $this->has('measurement_unit_id')) {
            $this->merge(['measurement_unit_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('attributes')->ignore($this->route('catalog_attribute'))],
            'name' => ['required', 'string', 'max:100'],
            'data_type' => ['required', Rule::in(array_keys(CatalogAttribute::TYPES))],
            'measurement_unit_id' => ['nullable', 'integer', Rule::exists('measurement_units', 'id')],
            'help_text' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['code' => 'código', 'data_type' => 'tipo de dato', 'measurement_unit_id' => 'unidad técnica', 'help_text' => 'texto de ayuda']);
    }
}
