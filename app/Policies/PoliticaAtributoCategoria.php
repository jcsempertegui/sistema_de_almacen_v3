<?php

namespace App\Policies;

use App\Models\AtributoCategoria;
use App\Models\Usuario;

class PoliticaAtributoCategoria
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('category_attributes.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('category_attributes.create');
    }

    public function update(Usuario $usuario, AtributoCategoria $target): bool
    {
        return $usuario->tienePermiso('category_attributes.update');
    }

    public function changeStatus(Usuario $usuario, AtributoCategoria $target): bool
    {
        return $usuario->tienePermiso('category_attributes.change_status');
    }
}
