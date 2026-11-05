<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\AtributoCategoria;
use App\Support\DecimalTecnico;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SolicitudAtributoCategoria extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return ($association = $this->route('atributo_categoria')) ? $this->user()->can('update', $association) : $this->user()->can('create', AtributoCategoria::class);
    }

    public function rules(): array
    {
        $decimal = function (string $field, mixed $value, Closure $fail) {
            try {
                DecimalTecnico::normalize($value, $field);
            } catch (ValidationException $exception) {
                $fail($exception->errors()[$field][0]);
            }
        };

        return [
            'categoria_id' => ['prohibited'],
            'atributo_id' => $this->route('atributo_categoria') ? ['prohibited'] : ['required', 'integer', Rule::exists('atributos', 'id'), Rule::unique('atributos_categoria')->where('categoria_id', $this->route('categoria')->id)],
            'obligatorio' => ['required', 'boolean'],
            'identificacion_tecnica' => ['required', 'boolean'],
            'orden_presentacion' => ['required', 'integer', 'between:0,65535'],
            'valor_minimo' => ['nullable', $decimal],
            'valor_maximo' => ['nullable', $decimal],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['atributo_id' => 'atributo', 'categoria_id' => 'categoría', 'obligatorio' => 'obligatoriedad', 'identificacion_tecnica' => 'identificación técnica', 'orden_presentacion' => 'orden', 'valor_minimo' => 'mínimo', 'valor_maximo' => 'máximo']);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['orden_presentacion.between' => 'El orden debe estar entre 0 y 65535.']);
    }
}
