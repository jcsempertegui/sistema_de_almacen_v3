<?php

namespace App\Actions\Devoluciones;

use App\Models\DevolucionHerramienta;
use App\Models\PrestamoHerramienta;
use App\Models\Usuario;
use App\Services\CiclosPrestamo;
use App\Services\ModificacionSegura;
use App\Services\RegistroExistenciasTrabajador;
use App\Services\RegularizacionDevolucion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class ConfirmarDevolucion
{
    public function handle(Usuario $actor, DevolucionHerramienta $target, mixed $version): DevolucionHerramienta
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $version) {
            app(ModificacionSegura::class)->exigirPermisos($operator, 'returns.confirm');
            $doc = DevolucionHerramienta::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($doc->estado === 'confirmed') {
                return $doc;
            }abort_unless($doc->estado === 'draft' && (string) $version === (string) $doc->version_bloqueo, 409, 'El documento cambió o está cancelado.');
            $prestamo = PrestamoHerramienta::whereKey($doc->prestamo_id)->lockForUpdate()->firstOrFail();
            abort_unless($prestamo->estado === 'confirmed', 409);
            $items = $doc->lineas()->reorder()->orderBy('unidad_individual_id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['lineas' => 'Incluye al menos una herramienta.']);
            }
            foreach ($items as $item) {
                $authorization = app(RegularizacionDevolucion::class)->approvedFor($item->toArray(), $doc);
                app(RegistroExistenciasTrabajador::class)->post($operator, ['producto_id' => $item->producto_id, 'ubicacion_id' => $item->ubicacion_id, 'cantidad' => '1'], [$item->unidad_individual_id], 'item_devolucion_id', $item->id, true, $item->item_prestamo_id);
                $cycles = app(CiclosPrestamo::class);
                $cycle = $cycles->enabled() ? $cycles->current($item->unidad_individual_id, $item->item_prestamo_id) : null;
                if (! $cycle || $cycle->item_prestamo_apertura_id !== null) {
                    DB::table('cierres_item_prestamo')->insert(['item_prestamo_id' => $item->item_prestamo_id, 'item_devolucion_id' => $item->id, 'unidad_individual_id' => $item->unidad_individual_id, 'autorizacion_regularizacion_id' => $authorization, 'fecha_creacion' => now()]);
                }
                if ($cycle) {
                    $cycles->close($cycle, $item->id, null, $authorization);
                }
                $cycleValues = $cycle ? ['ciclo_actual_id' => null] : [];
                if (DB::table('custodias_unidad_individual')->where('unidad_individual_id', $item->unidad_individual_id)->where('item_prestamo_actual_id', $item->item_prestamo_id)->update($cycleValues + ['item_prestamo_actual_id' => null, 'version_bloqueo' => DB::raw('version_bloqueo+1'), 'fecha_actualizacion' => now()]) !== 1) {
                    throw ValidationException::withMessages(['lineas' => 'La custodia cambió.']);
                }
                if ($authorization) {
                    app(RegularizacionDevolucion::class)->consume($operator, $authorization);
                } else {
                    app(RegularizacionDevolucion::class)->invalidateItems($operator, [$item->id], 'Recepción ordinaria confirmada.');
                }
                Event::dispatch('worker_operation.line_posted', [$doc, $item]);
            }
            DB::table('devoluciones_herramientas')->where('id', $doc->id)->update(['estado' => 'confirmed', 'datos_historicos_trabajador' => json_encode($prestamo->datos_historicos_trabajador, JSON_THROW_ON_ERROR), 'confirmado_por' => $operator->id, 'fecha_confirmacion' => now(), 'modificado_por' => $operator->id, 'fecha_actualizacion' => now(), 'version_bloqueo' => $doc->version_bloqueo + 1]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }
}
