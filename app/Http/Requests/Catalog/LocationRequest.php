<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;
use App\Models\Location;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LocationRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return ($location = $this->route('location')) ? $this->user()->can('update', $location) : $this->user()->can('create', Location::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
        if (! $this->has('parent_id')) {
            $this->merge(['parent_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', Rule::unique('locations')->ignore($this->route('location'))],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Location::TYPES))],
            'parent_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'is_storable' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['code' => 'código', 'type' => 'tipo de ubicación', 'parent_id' => 'ubicación padre', 'is_storable' => 'permite almacenar', 'description' => 'descripción']);
    }
}
