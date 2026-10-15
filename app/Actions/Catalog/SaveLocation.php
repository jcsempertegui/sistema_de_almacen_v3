<?php

namespace App\Actions\Catalog;

use App\Models\Location;
use App\Models\User;
use App\Services\LocationHierarchy;
use App\Services\LocationReferences;
use App\Services\SecurityMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SaveLocation
{
    public function __construct(private SecurityMutation $security, private LocationHierarchy $hierarchy, private LocationReferences $references) {}

    public function handle(User $actor, ?Location $target, array $data, bool $statusOnly = false): Location
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($target, $data, $statusOnly) {
                $location = $target ? Location::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Location;
                $this->security->requirePermissions($operator, ! $target ? 'locations.create' : ($statusOnly ? 'locations.change_status' : 'locations.update'));
                $location->fill(array_intersect_key($data, array_flip($statusOnly ? ['is_active'] : ['code', 'name', 'type', 'parent_id', 'is_storable', 'description', 'is_active'])));
                if ($target && $location->isDirty('is_active')) {
                    $this->security->requirePermissions($operator, 'locations.change_status');
                }
                $this->hierarchy->validateParent($location, ! $target || $location->isDirty('parent_id') || $location->is_active);
                if ($target && $location->isDirty(['parent_id', 'type']) && $this->references->hasHistoricalReferences($this->hierarchy->subtreeIds($location->id, lock: true))) {
                    throw ValidationException::withMessages(['location' => 'Esta ubicación o sus descendientes tienen referencias históricas. Conserva su padre y tipo.']);
                }
                if ($target && $location->isDirty('is_storable') && ! $location->is_storable && $this->references->hasHistoricalReferences([$location->id])) {
                    throw ValidationException::withMessages(['is_storable' => 'No puedes impedir el almacenamiento mientras existan referencias de esta ubicación.']);
                }
                if ($target && $location->isDirty('is_active') && ! $location->is_active) {
                    if ($this->references->hasActiveProductLocations([$location->id])) {
                        throw ValidationException::withMessages(['is_active' => 'Desactiva primero las asociaciones activas con productos.']);
                    }
                    if ($location->children()->where('is_active', true)->lockForUpdate()->first(['id'])) {
                        throw ValidationException::withMessages(['is_active' => 'No puedes desactivar una ubicación con hijos activos. Desactívalos primero.']);
                    }
                    if ($this->references->hasAssignedUnits($this->hierarchy->subtreeIds($location->id, lock: true))) {
                        throw ValidationException::withMessages(['is_active' => 'No puedes desactivar una ubicación con unidades asignadas en ella o sus descendientes.']);
                    }
                }
                if (! $target) {
                    $location->created_by = $operator->id;
                }
                if (! $location->exists || $location->isDirty()) {
                    $location->updated_by = $operator->id;
                    $location->save();
                }

                return $location;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'El código de ubicación ya está registrado.']);
        }
    }
}
