<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudBusqueda;
use App\Models\AtributoCatalogo;
use Illuminate\Validation\Rule;

class SolicitudBusquedaAtributo extends SolicitudBusqueda
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['tipo_dato' => ['nullable', Rule::in(array_keys(AtributoCatalogo::TYPES))]]);
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['tipo_dato' => 'tipo de dato']);
    }
}
