<?php

namespace App\Policies;

use App\Models\Usuario;
use App\Services\ModificacionSegura;

class PoliticaUsuario
{
    public function __construct(private ModificacionSegura $security) {}

    public function viewAny(Usuario $actor): bool
    {
        return $actor->tienePermiso('users.view');
    }

    public function create(Usuario $actor): bool
    {
        return $actor->tieneTodosPermisos(['users.create', 'users.assign_role']);
    }

    public function update(Usuario $actor, Usuario $target): bool
    {
        return $this->allows($actor, $target, 'users.update');
    }

    public function changeStatus(Usuario $actor, Usuario $target): bool
    {
        return $this->allows($actor, $target, 'users.change_status');
    }

    public function assignRole(Usuario $actor, Usuario $target): bool
    {
        return $this->allows($actor, $target, 'users.assign_role');
    }

    public function changePassword(Usuario $actor, Usuario $target): bool
    {
        return $this->allows($actor, $target, 'users.change_password');
    }

    private function allows(Usuario $actor, Usuario $target, string $permiso): bool
    {
        return $actor->tienePermiso($permiso) && $this->security->canManageUser($actor, $target);
    }
}
