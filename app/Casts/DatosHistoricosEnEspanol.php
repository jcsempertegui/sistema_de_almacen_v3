<?php

namespace App\Casts;

use App\Support\EsquemaEspanol;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Traduce las claves al consultar; conserva íntegro el JSON histórico almacenado. */
class DatosHistoricosEnEspanol implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }
        $datos = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        $plan = EsquemaEspanol::diccionario();
        $mapa = ['attribute' => 'atributo', 'category_attribute' => 'atributo_categoria', 'measurement_unit' => 'unidad_medida', 'category' => 'categoria', 'role' => 'rol', 'permissions' => 'permisos'];
        foreach ($plan['columnas'] as $tabla => $columnas) {
            if (! in_array($tabla, $plan['excepciones_tecnicas'], true)) {
                $mapa = array_merge($mapa, $columnas);
            }
        }
        $traducir = function ($entrada) use (&$traducir, $mapa) {
            if (! is_array($entrada)) {
                return $entrada;
            }
            $salida = [];
            foreach ($entrada as $clave => $valor) {
                $salida[$mapa[$clave] ?? $clave] = $traducir($valor);
            }

            return $salida;
        };

        return $traducir($datos);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
