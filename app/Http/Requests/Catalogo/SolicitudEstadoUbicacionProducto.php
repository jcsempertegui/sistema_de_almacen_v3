<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;

class SolicitudEstadoUbicacionProducto extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('ubicacion_producto'));
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean'], 'version_bloqueo' => ['required', 'integer', 'min:1']];
    }
}
