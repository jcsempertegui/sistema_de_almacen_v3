<?php

namespace App\Policies;

use App\Models\Usuario;

class PoliticaProducto
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('products.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('products.create');
    }

    public function changeStatus(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('products.change_status');
    }

    public function view(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('products.view');
    }

    public function update(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('products.update');
    }
}
