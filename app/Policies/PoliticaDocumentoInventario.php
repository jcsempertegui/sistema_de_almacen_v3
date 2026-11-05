<?php

namespace App\Policies;

use App\Models\DocumentoInventario;
use App\Models\Usuario;

class PoliticaDocumentoInventario
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('remittances.view') || $usuario->tienePermiso('initial_stock.view');
    }

    public function view(Usuario $usuario, DocumentoInventario $documento): bool
    {
        return $usuario->tienePermiso($documento->permiso('view'));
    }

    public function update(Usuario $usuario, DocumentoInventario $documento): bool
    {
        return $documento->estado === 'draft' && $usuario->tienePermiso($documento->permiso('update'));
    }

    public function confirm(Usuario $usuario, DocumentoInventario $documento): bool
    {
        return $documento->estado !== 'cancelled' && $usuario->tienePermiso($documento->permiso('confirm'));
    }

    public function cancel(Usuario $usuario, DocumentoInventario $documento): bool
    {
        return $documento->estado === 'draft' && $usuario->tienePermiso($documento->permiso('cancel_draft'));
    }
}
