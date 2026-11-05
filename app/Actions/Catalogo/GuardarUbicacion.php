<?php

namespace App\Actions\Catalogo;

use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Services\JerarquiaUbicaciones;
use App\Services\ModificacionSegura;
use App\Services\ReferenciasUbicacion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuardarUbicacion
{
    public function __construct(private ModificacionSegura $security, private JerarquiaUbicaciones $hierarchy, private ReferenciasUbicacion $references) {}

    public function handle(Usuario $actor, ?Ubicacion $target, array $data, bool $statusOnly = false): Ubicacion
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($target, $data, $statusOnly) {
                $ubicacion = $target ? Ubicacion::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Ubicacion;
                $this->security->exigirPermisos($operator, ! $target ? 'locations.create' : ($statusOnly ? 'locations.change_status' : 'locations.update'));
                $ubicacion->fill(array_intersect_key($data, array_flip($statusOnly ? ['activo'] : ['codigo', 'nombre', 'tipo', 'padre_id', 'permite_almacenar', 'descripcion', 'activo'])));
                if ($target && $ubicacion->isDirty('activo')) {
                    $this->security->exigirPermisos($operator, 'locations.change_status');
                }
                $this->hierarchy->validateParent($ubicacion, ! $target || $ubicacion->isDirty('padre_id') || $ubicacion->activo);
                if ($target && $ubicacion->isDirty(['padre_id', 'tipo']) && $this->references->hasHistoricalReferences($this->hierarchy->subtreeIds($ubicacion->id, lock: true))) {
                    throw ValidationException::withMessages(['ubicacion' => 'Esta ubicación o sus descendientes tienen referencias históricas. Conserva su padre y tipo.']);
                }
                if ($target && $ubicacion->isDirty('permite_almacenar') && ! $ubicacion->permite_almacenar && $this->references->hasHistoricalReferences([$ubicacion->id])) {
                    throw ValidationException::withMessages(['permite_almacenar' => 'No puedes impedir el almacenamiento mientras existan referencias de esta ubicación.']);
                }
                if ($target && $ubicacion->isDirty('activo') && ! $ubicacion->activo) {
                    if ($this->references->hasActiveProductLocations([$ubicacion->id])) {
                        throw ValidationException::withMessages(['activo' => 'Desactiva primero las asociaciones activas con productos.']);
                    }
                    if ($ubicacion->hijos()->where('activo', true)->lockForUpdate()->first(['id'])) {
                        throw ValidationException::withMessages(['activo' => 'No puedes desactivar una ubicación con hijos activos. Desactívalos primero.']);
                    }
                    if ($this->references->hasAssignedUnits($this->hierarchy->subtreeIds($ubicacion->id, lock: true))) {
                        throw ValidationException::withMessages(['activo' => 'No puedes desactivar una ubicación con unidades asignadas en ella o sus descendientes.']);
                    }
                }
                if (! $target) {
                    $ubicacion->creado_por = $operator->id;
                }
                if (! $ubicacion->exists || $ubicacion->isDirty()) {
                    $ubicacion->modificado_por = $operator->id;
                    $ubicacion->save();
                }

                return $ubicacion;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo' => 'El código de ubicación ya está registrado.']);
        }
    }
}
