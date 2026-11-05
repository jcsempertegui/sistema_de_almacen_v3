<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\Usuario;

class PoliticaCategoria
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('categories.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('categories.create');
    }

    public function update(Usuario $usuario, Categoria $categoria): bool
    {
        return $usuario->tienePermiso('categories.update');
    }

    public function changeStatus(Usuario $usuario, Categoria $categoria): bool
    {
        return $usuario->tienePermiso('categories.change_status');
    }
}
