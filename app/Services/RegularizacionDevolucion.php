<?php

namespace App\Services;

use App\Models\AutorizacionRegularizacionDevolucion;
use App\Models\DevolucionHerramienta;
use App\Models\ItemDevolucionHerramienta;
use App\Models\Usuario;
use App\Support\ValidacionOperacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegularizacionDevolucion
{
    public function request(Usuario $actor, ItemDevolucionHerramienta $target, mixed $version, string $motivo): AutorizacionRegularizacionDevolucion
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $version, $motivo) {
            app(ModificacionSegura::class)->exigirPermisos($operator, 'returns.request_regularization');
            $this->motivo($motivo);
            $doc = DevolucionHerramienta::whereKey($target->devolucion_id)->lockForUpdate()->firstOrFail();
            abort_unless($doc->estado === 'draft' && (string) $version === (string) $doc->version_bloqueo, 409, 'El borrador cambió o está cerrado.');
            $item = ItemDevolucionHerramienta::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $state = app(HabilitacionDevolucion::class)->inspect($item->toArray(), $doc);
            if (! $state['exceptions']) {
                throw ValidationException::withMessages(['motivo' => 'Las referencias están activas; no necesitan una excepción.']);
            }
            $this->invalidateItems($operator, [$item->id], 'Solicitud reemplazada.');
            $id = DB::table('autorizaciones_regularizacion_devolucion')->insertGetId(['item_devolucion_id' => $item->id, 'motivo' => trim($motivo), 'huella' => $state['huella'], 'datos_historicos_referencia' => json_encode($state, JSON_THROW_ON_ERROR), 'solicitado_por' => $operator->id, 'fecha_solicitud' => now()]);
            $this->event($operator, $id, 'requested', ['motivo' => trim($motivo), 'state' => $state]);

            return AutorizacionRegularizacionDevolucion::findOrFail($id);
        }, protectAdministrativeAccess: false);
    }

    public function decide(Usuario $actor, AutorizacionRegularizacionDevolucion $target, bool $approve, string $motivo): AutorizacionRegularizacionDevolucion
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $approve, $motivo) {
            app(ModificacionSegura::class)->exigirPermisos($operator, $approve ? 'returns.authorize_regularization' : 'returns.revoke_regularization');
            $this->motivo($motivo);
            $item = ItemDevolucionHerramienta::findOrFail($target->item_devolucion_id);
            $doc = DevolucionHerramienta::whereKey($item->devolucion_id)->lockForUpdate()->firstOrFail();
            $authorization = AutorizacionRegularizacionDevolucion::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($doc->estado === 'draft' && in_array($authorization->estado, $approve ? ['requested'] : ['requested', 'approved'], true), 409, 'La autorización ya no admite esta operación.');
            $update = ['estado' => $approve ? 'approved' : 'revoked'];
            if ($approve) {
                $state = app(HabilitacionDevolucion::class)->inspect($item->toArray(), $doc);
                if (! $state['exceptions'] || ! hash_equals($authorization->huella, $state['huella'])) {
                    throw ValidationException::withMessages(['motivo' => 'Las referencias cambiaron. Solicita una nueva autorización.']);
                }$update += ['autorizado_por' => $operator->id, 'fecha_autorizacion' => now(), 'fecha_vencimiento' => now()->addDay()];
            }
            DB::table('autorizaciones_regularizacion_devolucion')->where('id', $authorization->id)->update($update);
            $this->event($operator, $authorization->id, $update['estado'], ['motivo' => trim($motivo), 'fecha_vencimiento' => $update['fecha_vencimiento'] ?? null]);

            return $authorization->fresh();
        }, protectAdministrativeAccess: false);
    }

    public function approvedFor(array $item, DevolucionHerramienta $doc): ?int
    {
        $state = app(HabilitacionDevolucion::class)->inspect($item, $doc);
        if (! $state['exceptions']) {
            return null;
        }
        $authorization = AutorizacionRegularizacionDevolucion::where('item_devolucion_id', $item['id'])->where('estado', 'approved')->where('huella', $state['huella'])->where('fecha_vencimiento', '>', now())->lockForUpdate()->first();
        if (! $authorization) {
            throw ValidationException::withMessages(['lineas' => 'La recepción de referencias inactivas requiere autorización específica vigente.']);
        }

        return $authorization->id;
    }

    public function consume(Usuario $actor, int $id): void
    {
        if (DB::transactionLevel() === 0 || ! DB::table('cierres_item_prestamo')->where('autorizacion_regularizacion_id', $id)->exists()) {
            throw new \LogicException('La autorización se utiliza junto con su cierre y movimiento físico.');
        }
        if (DB::table('autorizaciones_regularizacion_devolucion')->where('id', $id)->where('estado', 'approved')->where('fecha_vencimiento', '>', now())->update(['estado' => 'used', 'fecha_utilizacion' => now()]) !== 1) {
            throw ValidationException::withMessages(['lineas' => 'La autorización ya no está vigente.']);
        }
        $this->event($actor, $id, 'used', ['motivo' => 'Recepción física confirmada y cierre registrado.']);
    }

    public function invalidateItems(Usuario $actor, array $ids, string $motivo): void
    {
        foreach (DB::table('autorizaciones_regularizacion_devolucion')->whereIn('item_devolucion_id', $ids)->whereIn('estado', ['requested', 'approved'])->lockForUpdate()->get() as $row) {
            DB::table('autorizaciones_regularizacion_devolucion')->where('id', $row->id)->update(['estado' => 'revoked']);
            $this->event($actor, $row->id, 'revoked', ['motivo' => $motivo]);
        }
    }

    private function motivo(string $motivo): void
    {
        ValidacionOperacion::make(['motivo' => trim($motivo)], ['motivo' => 'required|string|max:1000'], ['required' => 'El motivo es obligatorio.'])->validate();
    }

    private function event(Usuario $actor, int $id, string $accion, array $detalle): void
    {
        DB::table('eventos_regularizacion_devolucion')->insert(['autorizacion_id' => $id, 'actor_id' => $actor->id, 'accion' => $accion, 'detalle' => json_encode($detalle, JSON_THROW_ON_ERROR), 'fecha_creacion' => now()]);
    }
}
