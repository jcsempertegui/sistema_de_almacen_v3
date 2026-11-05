<?php

namespace App\Policies;

use App\Models\Usuario;

class PoliticaUnidadIndividual
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('individual_units.view');
    }

    public function view(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('individual_units.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('individual_units.create');
    }

    public function update(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('individual_units.update');
    }

    public function changeStatus(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('individual_units.change_status');
    }
}
