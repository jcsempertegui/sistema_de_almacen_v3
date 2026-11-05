<?php

namespace App\Http\Requests;

class SolicitudEstadoUsuario extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('usuario'));
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
