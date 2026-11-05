<?php

namespace App\Policies;

use App\Models\CorreccionInventario;
use App\Models\Usuario;

class PoliticaCorreccionInventario
{
    private function prefix(CorreccionInventario $d): string
    {
        return $d->tipo === 'adjustment' ? 'adjustments' : 'compensations';
    }

    public function view(Usuario $u, CorreccionInventario $d): bool
    {
        return $u->tienePermiso($this->prefix($d).'.view');
    }

    public function update(Usuario $u, CorreccionInventario $d): bool
    {
        return ! in_array($d->estado, ['executed', 'cancelled']) && $u->tienePermiso($this->prefix($d).'.update');
    }

    public function execute(Usuario $u, CorreccionInventario $d): bool
    {
        return $u->tienePermiso($this->prefix($d).'.execute');
    }
}
