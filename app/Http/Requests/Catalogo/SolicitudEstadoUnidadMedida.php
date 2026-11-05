<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;

class SolicitudEstadoUnidadMedida extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('unidad_medida'));
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
