<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;

class SolicitudEstadoAtributoCategoria extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('atributo_categoria'));
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean'], 'categoria_id' => ['prohibited'], 'atributo_id' => ['prohibited']];
    }
}
