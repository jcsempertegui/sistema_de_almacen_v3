<?php

namespace App\Policies;

use App\Models\Usuario;

class PoliticaUbicacionProducto
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('product_locations.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('product_locations.create');
    }

    public function changeStatus(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('product_locations.change_status');
    }
}
