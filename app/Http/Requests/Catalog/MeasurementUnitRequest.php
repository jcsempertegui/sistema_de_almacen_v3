<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\MeasurementUnit;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MeasurementUnitRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return ($unit = $this->route('measurement_unit')) ? $this->user()->can('update', $unit) : $this->user()->can('create', MeasurementUnit::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
        if (is_string($this->input('symbol'))) {
            $this->merge(['symbol' => trim($this->input('symbol'))]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('measurement_units')->ignore($this->route('measurement_unit'))],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:20', Rule::unique('measurement_units')->ignore($this->route('measurement_unit'))],
            'dimension' => ['required', Rule::in(array_keys(MeasurementUnit::DIMENSIONS))],
            'decimal_places' => ['required', 'integer', 'between:0,6'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['decimal_places.between' => 'La precisión decimal debe estar entre 0 y 6.']);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['code' => 'código', 'symbol' => 'símbolo', 'dimension' => 'dimensión', 'decimal_places' => 'precisión decimal']);
    }
}
