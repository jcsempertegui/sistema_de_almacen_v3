<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\UnidadMedida;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudUnidadMedida extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return ($unidad = $this->route('unidad_medida')) ? $this->user()->can('update', $unidad) : $this->user()->can('create', UnidadMedida::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('codigo'))) {
            $this->merge(['codigo' => Str::upper(trim($this->input('codigo')))]);
        }
        if (is_string($this->input('simbolo'))) {
            $this->merge(['simbolo' => trim($this->input('simbolo'))]);
        }
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', Rule::unique('unidades_medida')->ignore($this->route('unidad_medida'))],
            'nombre' => ['required', 'string', 'max:100'],
            'simbolo' => ['required', 'string', 'max:20', Rule::unique('unidades_medida')->ignore($this->route('unidad_medida'))],
            'dimension' => ['required', Rule::in(array_keys(UnidadMedida::DIMENSIONS))],
            'decimales' => ['required', 'integer', 'between:0,6'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['decimales.between' => 'La precisión decimal debe estar entre 0 y 6.']);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['codigo' => 'código', 'simbolo' => 'símbolo', 'dimension' => 'dimensión', 'decimales' => 'precisión decimal']);
    }
}
