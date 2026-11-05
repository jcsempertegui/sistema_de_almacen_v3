<?php

namespace App\Services;

use App\Models\AtributoCatalogo;
use App\Models\AtributoCategoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Support\DecimalTecnico;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ValidadorAtributosProducto
{
    public function definitions(int $categoryId, bool $lock = false): Collection
    {
        $query = AtributoCategoria::where('categoria_id', $categoryId)->orderBy('orden_presentacion')->orderBy('id');
        $definitions = ($lock ? $query->lockForUpdate() : $query)->get();
        foreach ($definitions as $definition) {
            $atributo = AtributoCatalogo::whereKey($definition->atributo_id);
            $atributo = ($lock ? $atributo->lockForUpdate() : $atributo)->firstOrFail();
            $unidad = null;
            if ($atributo->unidad_medida_id) {
                $query = UnidadMedida::whereKey($atributo->unidad_medida_id);
                $unidad = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
            }
            $atributo->setRelation('unidadMedida', $unidad);
            $definition->setRelation('atributo', $atributo);
        }

        return $definitions;
    }

    public function available(AtributoCategoria $definition): bool
    {
        return $definition->activo && $definition->atributo->activo
            && (! $definition->atributo->unidadMedida || $definition->atributo->unidadMedida->activo);
    }

    public function normalize(Producto $producto, array $input, Collection $definitions): array
    {
        $resultado = [];
        foreach ($input as $id => $raw) {
            $definition = $definitions->firstWhere('id', $id);
            if (! ctype_digit((string) $id) || (string) (int) $id !== (string) $id || ! $definition || ! $this->available($definition)) {
                throw ValidationException::withMessages(['values' => 'Se recibió un atributo ajeno a la categoría o una configuración inactiva. Recarga el formulario.']);
            }
            $resultado[$definition->id] = $this->value($definition, $raw);
        }
        foreach ($definitions as $definition) {
            if ($producto->activo && $this->available($definition) && $definition->obligatorio && ($resultado[$definition->id] ?? null) === null) {
                throw ValidationException::withMessages(['values.'.$definition->id => 'El atributo '.$definition->atributo->nombre.' es obligatorio.']);
            }
        }

        return $resultado;
    }

    private function value(AtributoCategoria $definition, mixed $raw): mixed
    {
        $field = 'values.'.$definition->id;
        if ($raw === null || $raw === '') {
            return null;
        }
        $atributo = $definition->atributo;
        if ($atributo->tipo_dato === 'decimal') {
            $value = DecimalTecnico::normalize($raw, $field, $atributo->unidadMedida?->decimales ?? 6);
            if (($definition->valor_minimo !== null && DecimalTecnico::compare($value, $definition->valor_minimo) < 0)
                || ($definition->valor_maximo !== null && DecimalTecnico::compare($value, $definition->valor_maximo) > 0)) {
                throw ValidationException::withMessages([$field => 'El valor está fuera de los límites técnicos configurados.']);
            }

            return $value;
        }
        if ($atributo->tipo_dato === 'boolean' && in_array($raw, [true, false, 0, 1, '0', '1'], true)) {
            return (int) $raw;
        }
        if ($atributo->tipo_dato === 'text' && is_string($raw) && mb_strlen(trim($raw)) <= 255) {
            return trim($raw) === '' ? null : trim($raw);
        }
        if ($atributo->tipo_dato === 'date' && is_string($raw) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $raw, $parts)
            && (int) $parts[1] >= 1000 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return $raw;
        }
        throw ValidationException::withMessages([$field => 'El valor no corresponde al tipo técnico configurado.']);
    }
}
