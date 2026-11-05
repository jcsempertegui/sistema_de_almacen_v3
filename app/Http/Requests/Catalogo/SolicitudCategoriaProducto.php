<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\Producto;

class SolicitudCategoriaProducto extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return ($producto = $this->route('producto')) ? $this->user()->can('update', $producto) : $this->user()->can('create', Producto::class);
    }

    public function rules(): array
    {
        return ['categoria_id' => ['nullable', 'integer', 'exists:categorias,id']];
    }
}
