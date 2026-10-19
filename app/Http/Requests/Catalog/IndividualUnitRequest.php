<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\IndividualUnit;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndividualUnitRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return ($unit = $this->route('individual_unit')) ? $this->user()->can('update', $unit) : $this->user()->can('create', IndividualUnit::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('identifier'))) {
            $this->merge(['identifier' => Str::upper(trim($this->input('identifier')))]);
        }
        if (is_string($this->input('serial_number'))) {
            $this->merge(['serial_number' => trim($this->input('serial_number')) === '' ? null : trim($this->input('serial_number'))]);
        }
    }

    public function rules(): array
    {
        $unit = $this->route('individual_unit');

        return [
            'identifier' => ['required', 'string', 'max:64', Rule::unique('individual_units')->ignore($unit)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'notes' => ['nullable', 'string', 'max:10000'], 'is_active' => ['required', 'boolean'],
            'lock_version' => [$unit ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['identifier' => 'identificador', 'product_id' => 'producto', 'serial_number' => 'número de serie', 'location_id' => 'ubicación inicial', 'notes' => 'observaciones', 'lock_version' => 'versión de edición']);
    }
}
