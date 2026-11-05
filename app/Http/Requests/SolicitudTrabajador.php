<?php

namespace App\Http\Requests;

use App\Models\Trabajador;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudTrabajador extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        $trabajador = $this->route('trabajador');

        return $trabajador ? $this->user()->can('update', $trabajador) : $this->user()->can('create', Trabajador::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['codigo', 'documento_identidad'] as $field) {
            if (is_string($this->input($field))) {
                $value = Str::upper(trim($this->input($field)));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:40', Rule::unique('trabajadores')->ignore($this->route('trabajador'))],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'documento_identidad' => ['nullable', 'string', 'max:40', Rule::unique('trabajadores')->ignore($this->route('trabajador'))],
            'cargo' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function passedValidation(): void
    {
        if (($trabajador = $this->route('trabajador')) && (bool) $this->validated('activo') !== $trabajador->activo) {
            abort_unless($this->user()->can('changeStatus', $trabajador), 403, 'No tienes permiso para cambiar el estado.');
        }
    }
}
