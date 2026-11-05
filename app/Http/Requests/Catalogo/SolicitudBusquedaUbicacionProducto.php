<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\UbicacionProducto;
use Illuminate\Validation\Rule;

class SolicitudBusquedaUbicacionProducto extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', UbicacionProducto::class);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:150'], 'estado' => ['nullable', Rule::in(['active', 'inactive'])]];
    }
}
