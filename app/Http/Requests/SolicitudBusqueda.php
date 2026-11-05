<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SolicitudBusqueda extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', Rule::in(['active', 'inactive'])]];
    }
}
