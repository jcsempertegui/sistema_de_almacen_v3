<?php

namespace App\Policies;

use App\Models\UnidadMedida;
use App\Models\Usuario;

class PoliticaUnidadMedida
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('measurement_units.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('measurement_units.create');
    }

    public function update(Usuario $usuario, UnidadMedida $unidad): bool
    {
        return $usuario->tienePermiso('measurement_units.update');
    }

    public function changeStatus(Usuario $usuario, UnidadMedida $unidad): bool
    {
        return $usuario->tienePermiso('measurement_units.change_status');
    }
}
