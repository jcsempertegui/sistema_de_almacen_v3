<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class AuditoriaCritica
{
    public function record(Usuario $actor, string $accion, string $target, int $id, array $detalle = []): void
    {
        if (! in_array($target, ['correccion_id', 'ejecucion_respaldo_id', 'ensayo_id'], true)) {
            throw new \LogicException('Referencia de auditoría inválida.');
        }
        DB::table('eventos_operacion_critica')->insert(['actor_id' => $actor->id, 'nombre_actor' => $actor->nombre, 'accion' => $accion, $target => $id, 'detalle' => json_encode($detalle, JSON_THROW_ON_ERROR), 'fecha_creacion' => now()]);
    }
}
