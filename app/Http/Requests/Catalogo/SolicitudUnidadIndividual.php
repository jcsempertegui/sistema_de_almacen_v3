<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\UnidadIndividual;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudUnidadIndividual extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return ($unidad = $this->route('unidad_individual')) ? $this->user()->can('update', $unidad) : $this->user()->can('create', UnidadIndividual::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('identificador'))) {
            $this->merge(['identificador' => Str::upper(trim($this->input('identificador')))]);
        }
        if (is_string($this->input('numero_serie'))) {
            $this->merge(['numero_serie' => trim($this->input('numero_serie')) === '' ? null : trim($this->input('numero_serie'))]);
        }
    }

    public function rules(): array
    {
        $unidad = $this->route('unidad_individual');

        return [
            'identificador' => ['required', 'string', 'max:64', Rule::unique('unidades_individuales')->ignore($unidad)],
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'ubicacion_id' => ['nullable', 'integer', Rule::exists('ubicaciones', 'id')],
            'observaciones' => ['nullable', 'string', 'max:10000'], 'activo' => ['required', 'boolean'],
            'version_bloqueo' => [$unidad ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['identificador' => 'identificador', 'producto_id' => 'producto', 'numero_serie' => 'número de serie', 'ubicacion_id' => 'ubicación inicial', 'observaciones' => 'observaciones', 'version_bloqueo' => 'versión de edición']);
    }
}
