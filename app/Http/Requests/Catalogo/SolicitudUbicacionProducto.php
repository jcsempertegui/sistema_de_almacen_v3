<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\UbicacionProducto;

class SolicitudUbicacionProducto extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('create', UbicacionProducto::class);
    }

    public function rules(): array
    {
        return ['ubicacion_id' => ['required', 'integer', 'exists:ubicaciones,id'], 'activo' => ['required', 'boolean'], 'version_bloqueo' => ['required', 'integer', 'min:1']];
    }
}
