<?php

namespace App\Policies;

use App\Models\DocumentoOperacionTrabajador;
use App\Models\Usuario;

abstract class PoliticaOperacionTrabajador
{
    protected string $prefix;

    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso($this->prefix.'.view');
    }

    public function view(Usuario $usuario, DocumentoOperacionTrabajador $doc): bool
    {
        return $this->viewAny($usuario);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso($this->prefix.'.create');
    }

    public function update(Usuario $usuario, DocumentoOperacionTrabajador $doc): bool
    {
        return $doc->estado === 'draft' && $usuario->tienePermiso($this->prefix.'.update');
    }

    public function confirm(Usuario $usuario, DocumentoOperacionTrabajador $doc): bool
    {
        return $doc->estado !== 'cancelled' && $usuario->tienePermiso($this->prefix.'.confirm');
    }

    public function cancel(Usuario $usuario, DocumentoOperacionTrabajador $doc): bool
    {
        return $doc->estado === 'draft' && $usuario->tienePermiso($this->prefix.'.cancel_draft');
    }
}
