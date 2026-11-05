<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class SolicitudFormularioSga extends FormRequest
{
    public function messages(): array
    {
        return [
            'in' => 'Selecciona un valor válido para :attribute.',
            'array' => 'El campo :attribute debe ser una lista.',
            'distinct' => 'El campo :attribute contiene valores repetidos.',
            'prohibited' => 'No se permite modificar el campo :attribute.',
            'regex' => 'El formato de :attribute no es válido.',
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'email' => 'Ingresa un correo electrónico válido.',
            'max.string' => 'El campo :attribute admite hasta :max caracteres.',
            'min.string' => 'El campo :attribute requiere al menos :min caracteres.',
            'unique' => 'El valor de :attribute ya está registrado.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'boolean' => 'Selecciona un estado válido.',
            'exists' => 'Selecciona un valor válido para :attribute.',
            'confirmed' => 'La confirmación de :attribute no coincide.',
            'contrasena.regex' => 'La contraseña debe incluir una letra mayúscula, una minúscula y un número.',
        ];
    }

    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'correo' => 'correo electrónico', 'contrasena' => 'contraseña', 'rol_id' => 'rol', 'activo' => 'estado', 'codigo' => 'código', 'nombres' => 'nombres', 'apellidos' => 'apellidos', 'documento_identidad' => 'documento de identidad', 'cargo' => 'cargo', 'area' => 'área', 'q' => 'búsqueda', 'estado' => 'estado'];
    }
}
