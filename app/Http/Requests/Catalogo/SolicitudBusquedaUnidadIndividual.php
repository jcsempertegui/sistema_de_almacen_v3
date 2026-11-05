<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\UnidadIndividual;
use Illuminate\Validation\Rule;

class SolicitudBusquedaUnidadIndividual extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', UnidadIndividual::class);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', Rule::in(['active', 'inactive'])], 'producto_id' => ['nullable', 'integer', Rule::exists('productos', 'id')], 'ubicacion_id' => ['nullable', 'integer', Rule::exists('ubicaciones', 'id')]];
    }
}
