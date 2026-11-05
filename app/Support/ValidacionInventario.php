<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ValidacionInventario
{
    public static function validate(Request $request, array $rules): array
    {
        return Validator::make($request->all(), $rules, [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'El campo :attribute no alcanza el mínimo permitido.',
            'max' => 'El campo :attribute supera el límite permitido.',
            'in' => 'Selecciona una opción válida para :attribute.',
            'file' => 'Selecciona un archivo válido.',
            'extensions' => 'Selecciona un archivo con extensión CSV.',
            'date_format' => 'Usa una fecha válida con formato AAAA-MM-DD para :attribute.',
            'before_or_equal' => 'La fecha de corte no puede ser posterior a la fecha del documento.',
        ], [
            'file' => 'archivo CSV', 'fecha_documento' => 'fecha del documento', 'fecha_corte' => 'fecha de corte',
            'tipo' => 'tipo', 'estado' => 'estado', 'token' => 'revisión del archivo', 'version_bloqueo' => 'versión del documento',
            'q' => 'búsqueda', 'producto_id' => 'producto', 'ubicacion_id' => 'ubicación',
        ])->validate();
    }
}
