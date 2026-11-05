<?php

namespace App\Policies;

use App\Models\Trabajador;
use App\Models\Usuario;

class PoliticaTrabajador
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('workers.view');
    }

    public function view(Usuario $usuario, Trabajador $trabajador): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('workers.create');
    }

    public function update(Usuario $usuario, Trabajador $trabajador): bool
    {
        return $usuario->tienePermiso('workers.update');
    }

    public function changeStatus(Usuario $usuario, Trabajador $trabajador): bool
    {
        return $usuario->tienePermiso('workers.change_status');
    }
}
