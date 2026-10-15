<?php

namespace App\Services;

use App\Models\CatalogAttribute;
use App\Models\CategoryAttribute;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Support\TechnicalDecimal;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductAttributeValidator
{
    public function definitions(int $categoryId, bool $lock = false): Collection
    {
        $query = CategoryAttribute::where('category_id', $categoryId)->orderBy('display_order')->orderBy('id');
        $definitions = ($lock ? $query->lockForUpdate() : $query)->get();
        foreach ($definitions as $definition) {
            $attribute = CatalogAttribute::whereKey($definition->attribute_id);
            $attribute = ($lock ? $attribute->lockForUpdate() : $attribute)->firstOrFail();
            $unit = null;
            if ($attribute->measurement_unit_id) {
                $query = MeasurementUnit::whereKey($attribute->measurement_unit_id);
                $unit = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
            }
            $attribute->setRelation('measurementUnit', $unit);
            $definition->setRelation('attribute', $attribute);
        }

        return $definitions;
    }

    public function available(CategoryAttribute $definition): bool
    {
        return $definition->is_active && $definition->attribute->is_active
            && (! $definition->attribute->measurementUnit || $definition->attribute->measurementUnit->is_active);
    }

    public function normalize(Product $product, array $input, Collection $definitions): array
    {
        $result = [];
        foreach ($input as $id => $raw) {
            $definition = $definitions->firstWhere('id', $id);
            if (! ctype_digit((string) $id) || (string) (int) $id !== (string) $id || ! $definition || ! $this->available($definition)) {
                throw ValidationException::withMessages(['values' => 'Se recibió un atributo ajeno a la categoría o una configuración inactiva. Recarga el formulario.']);
            }
            $result[$definition->id] = $this->value($definition, $raw);
        }
        foreach ($definitions as $definition) {
            if ($product->is_active && $this->available($definition) && $definition->is_required && ($result[$definition->id] ?? null) === null) {
                throw ValidationException::withMessages(['values.'.$definition->id => 'El atributo '.$definition->attribute->name.' es obligatorio.']);
            }
        }

        return $result;
    }

    private function value(CategoryAttribute $definition, mixed $raw): mixed
    {
        $field = 'values.'.$definition->id;
        if ($raw === null || $raw === '') {
            return null;
        }
        $attribute = $definition->attribute;
        if ($attribute->data_type === 'decimal') {
            $value = TechnicalDecimal::normalize($raw, $field, $attribute->measurementUnit?->decimal_places ?? 6);
            if (($definition->min_value !== null && TechnicalDecimal::compare($value, $definition->min_value) < 0)
                || ($definition->max_value !== null && TechnicalDecimal::compare($value, $definition->max_value) > 0)) {
                throw ValidationException::withMessages([$field => 'El valor está fuera de los límites técnicos configurados.']);
            }

            return $value;
        }
        if ($attribute->data_type === 'boolean' && in_array($raw, [true, false, 0, 1, '0', '1'], true)) {
            return (int) $raw;
        }
        if ($attribute->data_type === 'text' && is_string($raw) && mb_strlen(trim($raw)) <= 255) {
            return trim($raw) === '' ? null : trim($raw);
        }
        if ($attribute->data_type === 'date' && is_string($raw) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $raw, $parts)
            && (int) $parts[1] >= 1000 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return $raw;
        }
        throw ValidationException::withMessages([$field => 'El valor no corresponde al tipo técnico configurado.']);
    }
}
