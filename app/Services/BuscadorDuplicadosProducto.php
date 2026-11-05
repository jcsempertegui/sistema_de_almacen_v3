<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\ValorAtributoProducto;
use App\Support\DecimalTecnico;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class BuscadorDuplicadosProducto
{
    public function candidates(Producto $producto, array $values, Collection $definitions, bool $lock = false): Collection
    {
        $identity = $definitions->filter(fn ($d) => app(ValidadorAtributosProducto::class)->available($d) && $d->identificacion_tecnica);
        if ($identity->isEmpty() || $identity->contains(fn ($d) => ($values[$d->id] ?? null) === null)) {
            return collect();
        }
        $query = Producto::where('categoria_id', $producto->categoria_id)->where('unidad_medida_id', $producto->unidad_medida_id)
            ->where('modo_control', $producto->modo_control)->when($producto->exists, fn ($q) => $q->whereKeyNot($producto->id))->orderBy('id');
        $productos = ($lock ? $query->lockForUpdate() : $query)->get();
        $query = ValorAtributoProducto::whereIn('producto_id', $productos->pluck('id'))->where('activo', true);
        $byProduct = ($lock ? $query->lockForUpdate() : $query)->get()->groupBy('producto_id');

        return $productos->filter(function ($candidate) use ($identity, $values, $byProduct) {
            $stored = $byProduct->get($candidate->id, collect())->keyBy('atributo_categoria_id');
            foreach ($identity as $definition) {
                $row = $stored->get($definition->id);
                if (! $row) {
                    return false;
                }
                $tipo = $definition->atributo->tipo_dato;
                $value = $row->getRawOriginal(ValorAtributoProducto::columnaParaTipo($tipo));
                if ($tipo === 'decimal') {
                    $value = DecimalTecnico::normalize($value, 'values');
                }
                if ($tipo === 'boolean') {
                    $value = (int) $value;
                }
                if ($value !== $values[$definition->id]) {
                    return false;
                }
            }
            $candidate->setRelation('valoresAtributos', $stored->values());

            return true;
        })->values();
    }

    public function token(array $data, array $values, Collection $definitions, Collection $candidates): string
    {
        return Crypt::encryptString($this->digest($data, $values, $definitions, $candidates));
    }

    public function requireReview(array $data, array $values, Collection $definitions, Collection $candidates): void
    {
        if ($candidates->isEmpty()) {
            return;
        }
        try {
            $review = Crypt::decryptString($data['duplicate_token'] ?? '');
        } catch (DecryptException) {
            $review = '';
        }
        if (! hash_equals($this->digest($data, $values, $definitions, $candidates), $review)) {
            throw ValidationException::withMessages(['duplicate_token' => 'Hay productos con características coincidentes. Revisa los candidatos antes de guardar un producto distinto.']);
        }
    }

    private function digest(array $data, array $values, Collection $definitions, Collection $candidates): string
    {
        unset($data['duplicate_token']);
        $detalle = [$data, $values, $definitions->map(fn ($d) => [$d->id, $d->identificacion_tecnica, $d->activo, $d->fecha_actualizacion?->toISOString()])->all(), $candidates->map(fn ($candidate) => [$candidate->id, $candidate->version_bloqueo])->all()];
        $sort = function (array &$array) use (&$sort): void {
            ksort($array);
            foreach ($array as &$value) {
                if (is_array($value)) {
                    $sort($value);
                }
            }
        };
        $sort($detalle);

        return hash('sha256', json_encode($detalle, JSON_THROW_ON_ERROR));
    }
}
