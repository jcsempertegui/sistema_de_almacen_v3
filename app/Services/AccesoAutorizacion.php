<?php

namespace App\Services;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class AccesoAutorizacion
{
    public function proof(Usuario $u): array
    {
        return ['usuario_id' => $u->id, 'rol_id' => $u->rol_id, 'role_version' => $u->rol->version_permisos, 'version_sesion' => $u->version_sesion];
    }

    public function valid(int $id, string $codigo, array $proof): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('La aprobación requiere lecturas actuales dentro de una transacción.');
        }
        $usuario = Usuario::whereKey($id)->lockForUpdate()->first();
        if (! $usuario?->activo) {
            return false;
        }$rol = Rol::whereKey($usuario->rol_id)->lockForUpdate()->first();
        if (! $rol?->activo) {
            return false;
        }$usuario->setRelation('rol', $rol);

        return $proof === $this->proof($usuario) && $usuario->tienePermiso($codigo);
    }
}
