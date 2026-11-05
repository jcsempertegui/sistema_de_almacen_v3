<?php

namespace App\Policies;

use App\Models\AtributoCatalogo;
use App\Models\Usuario;

class PoliticaAtributoCatalogo
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('attributes.view');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('attributes.create');
    }

    public function update(Usuario $usuario, AtributoCatalogo $target): bool
    {
        return $usuario->tienePermiso('attributes.update');
    }

    public function changeStatus(Usuario $usuario, AtributoCatalogo $target): bool
    {
        return $usuario->tienePermiso('attributes.change_status');
    }
}
