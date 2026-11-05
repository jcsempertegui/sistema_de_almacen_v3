<?php

namespace App\Actions\Roles;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaAdministrativa;
use App\Services\ModificacionSegura;
use App\Support\CatalogoPermisos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class GuardarRol
{
    public function __construct(private ModificacionSegura $security, private AuditoriaAdministrativa $audit) {}

    public function handle(Usuario $actor, ?Rol $target, array $data): Rol
    {
        return $this->security->run($actor, function (Usuario $operator) use ($target, $data) {
            $creating = $target === null;
            $rol = $creating ? new Rol : Rol::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($creating) {
                $this->security->exigirPermisos($operator, 'roles.create');
            } else {
                if (! $operator->tienePermiso('roles.update') && ! $operator->tienePermiso('roles.assign_permissions') && ! $operator->tienePermiso('roles.change_status')) {
                    throw new AuthorizationException('No tienes permiso para modificar roles.');
                }
                $this->security->exigirGestionRol($operator, $rol);
            }
            $before = $creating ? null : $this->audit->datosRol($rol);
            $atributos = array_intersect_key($data, array_flip($creating ? ['codigo', 'nombre', 'descripcion', 'activo'] : ['nombre', 'descripcion', 'activo']));
            $rol->fill($atributos);
            if (! $creating && $rol->isDirty(['nombre', 'descripcion'])) {
                $this->security->exigirPermisos($operator, 'roles.update');
            }
            $statusChanged = ! $creating && $rol->isDirty('activo');
            if ($statusChanged) {
                $this->security->exigirPermisos($operator, 'roles.change_status');
            }
            $oldCodes = $before['permission_codes'] ?? [];
            $codes = $oldCodes;
            $selected = null;
            if (array_key_exists('permisos', $data)) {
                $ids = array_map('intval', $data['permisos']);
                $selected = Permiso::whereIn('id', $ids)->whereIn('codigo', array_keys(CatalogoPermisos::LABELS))->orderBy('codigo')->get();
                if ($selected->count() !== count(array_unique($ids))) {
                    throw ValidationException::withMessages(['permisos' => 'Selecciona permisos válidos.']);
                }
                $codes = $selected->pluck('codigo')->all();
            }
            $permissionsChanged = $codes !== $oldCodes;
            if ($permissionsChanged) {
                $this->security->exigirPermisos($operator, 'roles.assign_permissions');
                $this->security->requireGrant($operator, $codes);
            }
            if (! $creating && ! $rol->isDirty() && ! $permissionsChanged) {
                return $rol;
            }
            if ($statusChanged || (! $creating && $permissionsChanged)) {
                $rol->version_permisos++;
            }
            $rol->modificado_por = $operator->id;
            if ($creating) {
                $rol->creado_por = $operator->id;
            }
            $rol->save();
            if ($permissionsChanged) {
                $newIds = $selected->pluck('id')->all();
                foreach ($rol->permisos()->wherePivot('activo', true)->pluck('permisos.id') as $id) {
                    if (! in_array($id, $newIds, true)) {
                        $rol->permisos()->updateExistingPivot($id, ['activo' => false]);
                    }
                }
                foreach ($newIds as $id) {
                    $rol->permisos()->syncWithoutDetaching([$id => ['activo' => true]]);
                }
            }
            $after = $this->audit->datosRol($rol);
            $after['granted_permissions'] = array_values(array_diff($codes, $oldCodes));
            $after['revoked_permissions'] = array_values(array_diff($oldCodes, $codes));
            $accion = $creating ? 'roles.created' : ($permissionsChanged ? 'roles.permissions_changed' : ($statusChanged ? 'roles.status_changed' : 'roles.updated'));
            $this->audit->record($operator, $accion, $rol, $before, $after);

            return $rol;
        });
    }
}
