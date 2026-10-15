<?php

namespace App\Actions\Catalog;

use App\Models\CatalogAttribute;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\User;
use App\Services\AttributeReferences;
use App\Services\SecurityMutation;
use App\Support\TechnicalDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SaveCategoryAttribute
{
    public function __construct(private SecurityMutation $security, private AttributeReferences $references) {}

    public function handle(User $actor, Category $category, ?CategoryAttribute $target, array $data, bool $statusOnly = false): CategoryAttribute
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($category, $target, $data, $statusOnly) {
                $parent = Category::whereKey($category->id)->lockForUpdate()->firstOrFail();
                $association = $target ? CategoryAttribute::whereKey($target->id)->lockForUpdate()->firstOrFail() : new CategoryAttribute;
                abort_if($target && $association->category_id !== $parent->id, 404);
                $this->security->requirePermissions($operator, ! $target ? 'category_attributes.create' : ($statusOnly ? 'category_attributes.change_status' : 'category_attributes.update'));
                if (array_key_exists('category_id', $data) || ($target && array_key_exists('attribute_id', $data))) {
                    throw ValidationException::withMessages(['attribute_id' => 'La categoría y el atributo de una asociación existente no se pueden sustituir.']);
                }
                $association->category_id = $parent->id;
                $association->fill(array_intersect_key($data, array_flip($statusOnly ? ['is_active'] : ['attribute_id', 'is_required', 'is_identity', 'display_order', 'min_value', 'max_value', 'is_active'])));
                $attribute = CatalogAttribute::whereKey($association->attribute_id)->lockForUpdate()->first();
                if (! $attribute) {
                    throw ValidationException::withMessages(['attribute_id' => 'Selecciona un atributo existente.']);
                }
                $unit = $attribute->measurementUnit()->lockForUpdate()->first();
                $enabling = ! $target || ($association->isDirty('is_active') && $association->is_active);
                if ($enabling && (! $parent->is_active || ! $attribute->is_active || ($unit && ! $unit->is_active))) {
                    throw ValidationException::withMessages(['attribute_id' => 'Para asociar o activar, la categoría, el atributo y su unidad técnica deben estar activos.']);
                }
                if ($target && $association->isDirty('is_active')) {
                    $this->security->requirePermissions($operator, 'category_attributes.change_status');
                }
                if (! $statusOnly) {
                    $precision = $unit?->decimal_places ?? 6;
                    $minimum = TechnicalDecimal::normalize($association->getAttributes()['min_value'] ?? null, 'min_value', $precision);
                    $maximum = TechnicalDecimal::normalize($association->getAttributes()['max_value'] ?? null, 'max_value', $precision);
                    if ($attribute->data_type !== 'decimal' && ($minimum !== null || $maximum !== null)) {
                        throw ValidationException::withMessages(['min_value' => 'Solo los atributos decimales admiten límites.']);
                    }
                    if ($minimum !== null && $maximum !== null && TechnicalDecimal::compare($minimum, $maximum) > 0) {
                        throw ValidationException::withMessages(['max_value' => 'El máximo debe ser mayor o igual al mínimo.']);
                    }
                    $association->min_value = $minimum;
                    $association->max_value = $maximum;
                    if ($target && $association->isDirty(['is_identity', 'min_value', 'max_value']) && $this->references->associationHasValues($association->id)) {
                        throw ValidationException::withMessages(['is_identity' => 'Esta configuración tiene valores históricos. Revisa el impacto antes de cambiar su identificación o sus límites.']);
                    }
                    if (($association->isDirty('is_required') && $association->is_required) && $this->references->categoryHasIncompleteProducts($association)) {
                        throw ValidationException::withMessages(['is_required' => 'Completa primero el atributo en todos los productos activos de la categoría.']);
                    }
                }
                if (! $target) {
                    $association->created_by = $operator->id;
                }
                if (! $association->exists || $association->isDirty()) {
                    $association->updated_by = $operator->id;
                    $association->save();
                }

                return $association;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['attribute_id' => 'Este atributo ya está asociado a la categoría, incluso si está inactivo. Edita o reactiva la asociación existente.']);
        }
    }
}
