<?php

namespace App\Http\Requests;

class SolicitudEstadoRol extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('rol'));
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
