<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\Categoria;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudCategoria extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return ($categoria = $this->route('categoria')) ? $this->user()->can('update', $categoria) : $this->user()->can('create', Categoria::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('codigo'))) {
            $this->merge(['codigo' => Str::upper(trim($this->input('codigo')))]);
        }
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50', Rule::unique('categorias')->ignore($this->route('categoria'))],
            'nombre' => ['required', 'string', 'max:150', Rule::unique('categorias')->ignore($this->route('categoria'))],
            'descripcion' => ['nullable', 'string', 'max:10000'],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['codigo' => 'código', 'descripcion' => 'descripción']);
    }
}
