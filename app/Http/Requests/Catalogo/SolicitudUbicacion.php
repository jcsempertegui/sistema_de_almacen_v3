<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\Ubicacion;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudUbicacion extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return ($ubicacion = $this->route('ubicacion')) ? $this->user()->can('update', $ubicacion) : $this->user()->can('create', Ubicacion::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('codigo'))) {
            $this->merge(['codigo' => Str::upper(trim($this->input('codigo')))]);
        }
        if (! $this->has('padre_id')) {
            $this->merge(['padre_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:60', Rule::unique('ubicaciones')->ignore($this->route('ubicacion'))],
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::in(array_keys(Ubicacion::TYPES))],
            'padre_id' => ['nullable', 'integer', Rule::exists('ubicaciones', 'id')],
            'permite_almacenar' => ['required', 'boolean'],
            'descripcion' => ['nullable', 'string', 'max:10000'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['codigo' => 'código', 'tipo' => 'tipo de ubicación', 'padre_id' => 'ubicación padre', 'permite_almacenar' => 'permite almacenar', 'descripcion' => 'descripción']);
    }
}
