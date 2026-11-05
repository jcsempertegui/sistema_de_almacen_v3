<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudBusqueda;
use App\Models\Ubicacion;
use Illuminate\Validation\Rule;

class SolicitudBusquedaUbicacion extends SolicitudBusqueda
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Ubicacion::class);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'tipo' => ['nullable', Rule::in(array_keys(Ubicacion::TYPES))],
            'padre_id' => ['nullable', 'integer', Rule::exists('ubicaciones', 'id')],
            'roots_only' => ['nullable', 'boolean'],
        ]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['tipo' => 'tipo de ubicación', 'padre_id' => 'ubicación padre', 'roots_only' => 'solo raíces']);
    }
}
