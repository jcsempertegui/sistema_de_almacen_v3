<?php

namespace App\Policies;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ModificacionSegura;

class PoliticaRol
{
    public function __construct(private ModificacionSegura $security) {}

    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('roles.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('roles.create');
    }

    public function update(Usuario $usuario, Rol $rol): bool
    {
        return $this->allows($usuario, $rol, 'roles.update');
    }

    public function changeStatus(Usuario $usuario, Rol $rol): bool
    {
        return $this->allows($usuario, $rol, 'roles.change_status');
    }

    public function assignPermissions(Usuario $usuario, Rol $rol): bool
    {
        return $this->allows($usuario, $rol, 'roles.assign_permissions');
    }

    private function allows(Usuario $usuario, Rol $rol, string $permiso): bool
    {
        return $usuario->tienePermiso($permiso) && $this->security->canManageRole($usuario, $rol);
    }
}
