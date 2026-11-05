<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\AtributoCatalogo;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudAtributoCatalogo extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return ($atributo = $this->route('atributo_catalogo')) ? $this->user()->can('update', $atributo) : $this->user()->can('create', AtributoCatalogo::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('codigo'))) {
            $this->merge(['codigo' => Str::upper(trim($this->input('codigo')))]);
        }
        // Omitting an optional unidad explicitly clears it; the accion checks history.
        if (! $this->has('unidad_medida_id')) {
            $this->merge(['unidad_medida_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50', Rule::unique('atributos')->ignore($this->route('atributo_catalogo'))],
            'nombre' => ['required', 'string', 'max:100'],
            'tipo_dato' => ['required', Rule::in(array_keys(AtributoCatalogo::TYPES))],
            'unidad_medida_id' => ['nullable', 'integer', Rule::exists('unidades_medida', 'id')],
            'texto_ayuda' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['codigo' => 'código', 'tipo_dato' => 'tipo de dato', 'unidad_medida_id' => 'unidad técnica', 'texto_ayuda' => 'texto de ayuda']);
    }
}
