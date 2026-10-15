<?php

namespace App\Actions\Catalog;

use App\Models\CatalogAttribute;
use App\Models\MeasurementUnit;
use App\Models\User;
use App\Services\SecurityMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SaveCatalogAttribute
{
    public function __construct(private SecurityMutation $security) {}

    public function handle(User $actor, ?CatalogAttribute $target, array $data, bool $statusOnly = false): CatalogAttribute
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($target, $data, $statusOnly) {
                $attribute = $target ? CatalogAttribute::whereKey($target->id)->lockForUpdate()->firstOrFail() : new CatalogAttribute;
                $this->security->requirePermissions($operator, ! $target ? 'attributes.create' : ($statusOnly ? 'attributes.change_status' : 'attributes.update'));
                $attribute->fill(array_intersect_key($data, array_flip($statusOnly ? ['is_active'] : ['code', 'name', 'data_type', 'measurement_unit_id', 'help_text', 'is_active'])));
                if ($target && $attribute->isDirty(['data_type', 'measurement_unit_id']) && $attribute->categoryAttributes()->exists()) {
                    throw ValidationException::withMessages(['data_type' => 'Este atributo tiene asociaciones actuales o históricas. Conserva su tipo y unidad; registra otro atributo si cambia su significado.']);
                }
                if ($attribute->measurement_unit_id && $attribute->data_type !== 'decimal') {
                    throw ValidationException::withMessages(['measurement_unit_id' => 'Solo los atributos decimales admiten una unidad técnica.']);
                }
                if ($attribute->measurement_unit_id) {
                    $unit = MeasurementUnit::whereKey($attribute->measurement_unit_id)->lockForUpdate()->first();
                    if (! $unit || (! $unit->is_active && (! $target || $attribute->is_active || $attribute->isDirty('measurement_unit_id')))) {
                        throw ValidationException::withMessages(['measurement_unit_id' => 'Selecciona una unidad técnica activa.']);
                    }
                }
                if ($target && $attribute->isDirty('is_active')) {
                    $this->security->requirePermissions($operator, 'attributes.change_status');
                    if (! $attribute->is_active && $attribute->categoryAttributes()->where('is_active', true)->exists()) {
                        throw ValidationException::withMessages(['is_active' => 'Desactiva primero las asociaciones activas de este atributo en las categorías.']);
                    }
                }
                if (! $target) {
                    $attribute->created_by = $operator->id;
                }
                if (! $attribute->exists || $attribute->isDirty()) {
                    $attribute->updated_by = $operator->id;
                    $attribute->save();
                }

                return $attribute;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'El código del atributo ya está registrado.']);
        }
    }
}
