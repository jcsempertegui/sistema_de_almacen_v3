<?php

namespace App\Http\Requests;

class SolicitudEstadoTrabajador extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('trabajador'));
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
