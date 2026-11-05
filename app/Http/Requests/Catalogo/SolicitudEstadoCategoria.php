<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;

class SolicitudEstadoCategoria extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('categoria'));
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
