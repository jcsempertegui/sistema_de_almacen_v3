<?php

namespace App\Policies;

use App\Models\Ubicacion;
use App\Models\Usuario;

class PoliticaUbicacion
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('locations.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('locations.create');
    }

    public function update(Usuario $usuario, Ubicacion $ubicacion): bool
    {
        return $usuario->tienePermiso('locations.update');
    }

    public function changeStatus(Usuario $usuario, Ubicacion $ubicacion): bool
    {
        return $usuario->tienePermiso('locations.change_status');
    }
}
