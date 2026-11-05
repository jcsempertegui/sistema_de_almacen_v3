<?php

namespace App\Actions\Prestamos;

use App\Models\PrestamoHerramienta;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\CiclosPrestamo;
use App\Services\ModificacionSegura;
use App\Services\RegistroExistenciasTrabajador;
use App\Services\ValidadorBorradorInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class ConfirmarPrestamo
{
    public function handle(Usuario $actor, PrestamoHerramienta $target, mixed $version): PrestamoHerramienta
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $version) {
            app(ModificacionSegura::class)->exigirPermisos($operator, 'loans.confirm');
            $doc = PrestamoHerramienta::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($doc->estado === 'confirmed') {
                return $doc;
            }abort_unless($doc->estado === 'draft' && (string) $version === (string) $doc->version_bloqueo, 409, 'El documento cambió o está cancelado.');
            $trabajador = Trabajador::whereKey($doc->trabajador_id)->lockForUpdate()->firstOrFail();
            if (! $trabajador->activo) {
                throw ValidationException::withMessages(['trabajador_id' => 'El trabajador debe estar activo para un nuevo préstamo.']);
            }
            $items = $doc->lineas()->reorder()->orderBy('unidad_individual_id')->lockForUpdate()->get();
            $seen = [];
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['lineas' => 'Incluye al menos una herramienta.']);
            }
            foreach ($items as $item) {
                $line = app(ValidadorBorradorInventario::class)->line(['producto_id' => $item->producto_id, 'ubicacion_id' => $item->ubicacion_id, 'cantidad' => '1', 'unit_ids' => [$item->unidad_individual_id]], 'lineas', $seen);
                if (! Producto::whereKey($item->producto_id)->lockForUpdate()->firstOrFail()->prestable || (int) $line['unidad_medida_id'] !== $item->unidad_medida_id || $line['simbolo_unidad'] !== $item->simbolo_unidad) {
                    throw ValidationException::withMessages(['lineas' => 'La herramienta o su definición no permiten este préstamo.']);
                }
                app(RegistroExistenciasTrabajador::class)->post($operator, ['producto_id' => $item->producto_id, 'ubicacion_id' => $item->ubicacion_id, 'cantidad' => '1'], [$item->unidad_individual_id], 'item_prestamo_id', $item->id);
                DB::table('custodias_unidad_individual')->insertOrIgnore(['unidad_individual_id' => $item->unidad_individual_id, 'producto_id' => $item->producto_id, 'fecha_creacion' => now(), 'fecha_actualizacion' => now()]);
                $cycleValues = app(CiclosPrestamo::class)->enabled() ? ['ciclo_actual_id' => app(CiclosPrestamo::class)->open($item)] : [];
                $changed = DB::table('custodias_unidad_individual')->where('unidad_individual_id', $item->unidad_individual_id)->whereNull('item_prestamo_actual_id')->update($cycleValues + ['item_prestamo_actual_id' => $item->id, 'version_bloqueo' => DB::raw('version_bloqueo+1'), 'fecha_actualizacion' => now()]);
                if ($changed !== 1) {
                    throw ValidationException::withMessages(['lineas' => 'La herramienta ya tiene custodia vigente.']);
                }
                Event::dispatch('worker_operation.line_posted', [$doc, $item]);
            }
            DB::table('prestamos_herramientas')->where('id', $doc->id)->update(['estado' => 'confirmed', 'datos_historicos_trabajador' => json_encode($trabajador->only(['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area']), JSON_THROW_ON_ERROR), 'confirmado_por' => $operator->id, 'fecha_confirmacion' => now(), 'modificado_por' => $operator->id, 'fecha_actualizacion' => now(), 'version_bloqueo' => $doc->version_bloqueo + 1]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }
}
