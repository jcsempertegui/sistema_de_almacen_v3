<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;

final class ValidacionOperacion
{
    public const MESSAGES = ['required' => 'El campo :attribute es obligatorio.', 'present' => 'Debes informar :attribute.', 'integer' => 'El campo :attribute debe ser un identificador entero.', 'min' => 'El campo :attribute no alcanza el mínimo permitido.', 'max' => 'El campo :attribute supera el límite permitido.', 'array' => 'El campo :attribute debe ser una lista.', 'string' => 'El campo :attribute debe ser texto.', 'date_format' => 'Usa una fecha válida AAAA-MM-DD en :attribute.', 'after_or_equal' => 'El vencimiento no puede ser anterior al préstamo.', 'distinct' => 'No repitas la misma herramienta.', 'in' => 'Selecciona una opción válida para :attribute.'];

    public static function make(array $data, array $rules, array $messages = []): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($data, $rules, $messages + self::MESSAGES, ['trabajador_id' => 'trabajador', 'prestamo_id' => 'préstamo', 'fecha_documento' => 'fecha', 'fecha_limite' => 'vencimiento', 'motivo' => 'motivo', 'lineas' => 'líneas', 'expected_line_count' => 'cantidad de líneas', 'version_bloqueo' => 'versión del documento']);
    }
}
