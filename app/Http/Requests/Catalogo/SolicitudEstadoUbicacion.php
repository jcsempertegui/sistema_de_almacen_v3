<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;

class SolicitudEstadoUbicacion extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('ubicacion'));
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
