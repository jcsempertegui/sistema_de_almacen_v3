<?php

namespace App\Actions\Catalog;

use App\Models\MeasurementUnit;
use App\Models\User;
use App\Services\CatalogReferences;
use App\Services\SecurityMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SaveMeasurementUnit
{
    public function __construct(private SecurityMutation $security, private CatalogReferences $references) {}

    public function handle(User $actor, ?MeasurementUnit $target, array $data, bool $statusOnly = false): MeasurementUnit
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($target, $data, $statusOnly) {
                $unit = $target ? MeasurementUnit::whereKey($target->id)->lockForUpdate()->firstOrFail() : new MeasurementUnit;
                $this->security->requirePermissions($operator, ! $target ? 'measurement_units.create' : ($statusOnly ? 'measurement_units.change_status' : 'measurement_units.update'));
                $unit->fill(array_intersect_key($data, array_flip($statusOnly ? ['is_active'] : ['code', 'name', 'symbol', 'dimension', 'decimal_places', 'is_active'])));
                if ($target && $unit->isDirty(['code', 'symbol', 'dimension', 'decimal_places']) && $this->references->unitHasAnyReferences($unit->id)) {
                    throw ValidationException::withMessages(['measurement_unit' => 'Esta unidad ya tiene referencias. Conserva su código, símbolo, dimensión y precisión; registra otra unidad si cambia su significado.']);
                }
                if ($target && $unit->isDirty('is_active')) {
                    $this->security->requirePermissions($operator, 'measurement_units.change_status');
                    if (! $unit->is_active && $this->references->unitHasActiveReferences($unit->id)) {
                        throw ValidationException::withMessages(['is_active' => 'No puedes desactivar una unidad utilizada por productos o atributos activos.']);
                    }
                }
                if (! $target) {
                    $unit->created_by = $operator->id;
                }
                if (! $unit->exists || $unit->isDirty()) {
                    $unit->updated_by = $operator->id;
                    $unit->save();
                }

                return $unit;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            $query = MeasurementUnit::where('code', $data['code'] ?? $target?->code)->when($target, fn ($query) => $query->whereKeyNot($target->id));
            throw ValidationException::withMessages([$query->exists() ? 'code' : 'symbol' => 'El código o símbolo de unidad ya está registrado.']);
        }
    }
}
