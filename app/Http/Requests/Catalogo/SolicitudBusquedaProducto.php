<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\Producto;
use Illuminate\Validation\Rule;

class SolicitudBusquedaProducto extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Producto::class);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:150'], 'estado' => ['nullable', Rule::in(['active', 'inactive'])], 'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'], 'unidad_medida_id' => ['nullable', 'integer', 'exists:unidades_medida,id'], 'modo_control' => ['nullable', Rule::in(array_keys(Producto::MODES))]];
    }
}
