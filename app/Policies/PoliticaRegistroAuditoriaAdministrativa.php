<?php

namespace App\Policies;

use App\Models\Usuario;

class PoliticaRegistroAuditoriaAdministrativa
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('audit.view');
    }
}
