<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\Producto;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudProducto extends SolicitudFormularioSga
{
    public function formUrl(): string
    {
        $categoria = $this->input('categoria_id');

        return ($producto = $this->route('producto'))
            ? route('productos.edit', $producto)
            : route('productos.create', (is_string($categoria) || is_int($categoria)) && ctype_digit((string) $categoria) ? ['categoria_id' => $categoria] : []);
    }

    protected function getRedirectUrl()
    {
        return $this->formUrl();
    }

    public function authorize(): bool
    {
        return ($producto = $this->route('producto')) ? $this->user()->can('update', $producto) : $this->user()->can('create', Producto::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('codigo_interno'))) {
            $this->merge(['codigo_interno' => Str::upper(trim($this->input('codigo_interno')))]);
        }
    }

    public function rules(): array
    {
        return [
            'codigo_interno' => ['required', 'string', 'max:60', Rule::unique('productos')->ignore($this->route('producto'))],
            'nombre' => ['required', 'string', 'max:150'], 'descripcion' => ['nullable', 'string', 'max:10000'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'unidad_medida_id' => ['required', 'integer', 'exists:unidades_medida,id'],
            'marca' => ['nullable', 'string', 'max:100'], 'referencia_fabricante' => ['nullable', 'string', 'max:100'],
            'modo_control' => ['required', Rule::in(array_keys(Producto::MODES))],
            'prestable' => ['required', 'boolean'], 'activo' => ['required', 'boolean'],
            'version_bloqueo' => [$this->route('producto') ? 'required' : 'prohibited', 'integer', 'min:1'],
            'values' => ['sometimes', 'array', 'max:500'],
            'location_ids' => [$this->route('producto') ? 'prohibited' : 'sometimes', 'array', 'max:100'],
            'location_ids.*' => ['required', 'integer', 'distinct', 'exists:ubicaciones,id'],
            'duplicate_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['codigo_interno' => 'código interno', 'categoria_id' => 'categoría', 'unidad_medida_id' => 'unidad de medida', 'descripcion' => 'descripción', 'modo_control' => 'modo de control', 'marca' => 'marca', 'referencia_fabricante' => 'referencia de fabricante', 'values' => 'atributos técnicos', 'version_bloqueo' => 'versión de edición'];
    }
}
