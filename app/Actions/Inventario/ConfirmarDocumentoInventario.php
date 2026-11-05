<?php

namespace App\Actions\Inventario;

use App\Models\DocumentoInventario;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\ValidadorBorradorInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConfirmarDocumentoInventario
{
    public function __construct(private ModificacionSegura $security, private ValidadorBorradorInventario $validator) {}

    public function handle(Usuario $actor, DocumentoInventario $target, mixed $version): DocumentoInventario
    {
        return $this->security->run($actor, function (Usuario $operator) use ($target, $version) {
            $documento = DocumentoInventario::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->security->exigirPermisos($operator, $documento->permiso('confirm'));
            // A valid retry still requires current authorization, but not a new draft version.
            if ($documento->estado === 'confirmed') {
                return $documento;
            }
            abort_unless($documento->estado === 'draft', 409, 'El documento está cancelado.');
            abort_unless((string) $version === (string) $documento->version_bloqueo, 409, 'El documento cambió. Recarga antes de confirmar.');
            $lineas = $documento->lineas()->lockForUpdate()->get();
            if ($lineas->isEmpty()) {
                throw ValidationException::withMessages(['lineas' => 'El documento debe contener al menos una línea.']);
            }
            $seen = [];
            foreach ($lineas as $line) {
                // Eager reads would retain an obsolete snapshot under REPEATABLE READ.
                $line->setRelation('unidades', $line->unidades()->lockForUpdate()->get());
                $current = $this->validator->line(['producto_id' => $line->producto_id, 'ubicacion_id' => $line->ubicacion_id, 'cantidad' => $line->cantidad, 'unit_ids' => $line->unidades->pluck('id')->all()], 'lineas.'.$line->numero_linea, $seen);
                foreach (['unidad_medida_id', 'simbolo_unidad', 'decimales', 'modo_control'] as $field) {
                    if ((string) $current[$field] !== (string) $line->$field) {
                        throw ValidationException::withMessages(['lineas' => 'La definición del producto cambió. Revisa y guarda nuevamente el borrador.']);
                    }
                }
            }
            if ($documento->tipo === 'opening') {
                foreach ($lineas->pluck('producto_id')->unique()->sort() as $productId) {
                    if (DB::table('productos_existencia_inicial')->where('producto_id', $productId)->lockForUpdate()->first()
                        || DB::table('movimientos_inventario')->where('producto_id', $productId)->lockForUpdate()->first()) {
                        throw ValidationException::withMessages(['lineas' => 'El producto ya tiene apertura o movimientos. No se permite una segunda apertura.']);
                    }
                    DB::table('productos_existencia_inicial')->insert(['producto_id' => $productId, 'documento_inventario_id' => $documento->id, 'tipo' => 'opening']);
                }
            }
            $incoming = $documento->tipo !== 'issue';
            $time = now();
            foreach ($lineas->sortBy(fn ($line) => sprintf('%020d:%020d', $line->producto_id, $line->ubicacion_id)) as $line) {
                $stock = DB::table('existencias_ubicacion')->where('producto_id', $line->producto_id)->where('ubicacion_id', $line->ubicacion_id);
                if (! (clone $stock)->lockForUpdate()->first()) {
                    DB::table('existencias_ubicacion')->insert(['producto_id' => $line->producto_id, 'ubicacion_id' => $line->ubicacion_id, 'cantidad' => '0', 'version_bloqueo' => 1, 'fecha_creacion' => $time, 'fecha_actualizacion' => $time]);
                }
                // SQL DECIMAL arithmetic: neither floats nor PHP-sized scaled integers.
                $operatorSql = $incoming ? '+' : '-';
                $condition = $incoming ? 'cantidad <= CAST(999999999999.999999 AS DECIMAL(18,6)) - CAST(? AS DECIMAL(18,6))' : 'cantidad >= CAST(? AS DECIMAL(18,6))';
                $changed = DB::update("UPDATE existencias_ubicacion SET cantidad = cantidad {$operatorSql} CAST(? AS DECIMAL(18,6)), version_bloqueo = version_bloqueo + 1, fecha_actualizacion = ? WHERE producto_id = ? AND ubicacion_id = ? AND {$condition}", [$line->cantidad, $time, $line->producto_id, $line->ubicacion_id, $line->cantidad]);
                if ($changed !== 1) {
                    throw ValidationException::withMessages(['lineas' => $incoming ? 'El saldo excedería la capacidad decimal del sistema.' : 'Existencias insuficientes en la ubicación seleccionada.']);
                }
                foreach ($line->unidades->sortBy('id') as $unidad) {
                    if (Schema::hasTable('custodias_unidad_individual') && DB::table('custodias_unidad_individual')->where('unidad_individual_id', $unidad->id)->whereNotNull('item_prestamo_actual_id')->lockForUpdate()->first()) {
                        throw ValidationException::withMessages(['lineas' => 'La herramienta está prestada. Registra su devolución vinculada al préstamo.']);
                    }
                    $position = DB::table('posiciones_unidad_individual')->where('unidad_individual_id', $unidad->id)->lockForUpdate()->first();
                    if (($incoming && $position?->presente) || (! $incoming && (! $position?->presente || $position->ubicacion_id !== $line->ubicacion_id))) {
                        throw ValidationException::withMessages(['lineas' => 'La unidad '.$unidad->identificador.' no tiene una presencia física compatible con esta operación.']);
                    }
                    $newSources = Schema::hasColumn('posiciones_unidad_individual', 'ultimo_item_prestamo_id')
                        ? ['ultima_linea_entrega_id' => null, 'ultimo_item_prestamo_id' => null, 'ultimo_item_devolucion_id' => null] : [];
                    if (Schema::hasColumn('posiciones_unidad_individual', 'ultima_linea_correccion_id')) {
                        $newSources['ultima_linea_correccion_id'] = null;
                    }
                    if (Schema::hasColumn('posiciones_unidad_individual', 'direccion_ultima_correccion')) {
                        $newSources['direccion_ultima_correccion'] = null;
                    }
                    DB::table('posiciones_unidad_individual')->updateOrInsert(['unidad_individual_id' => $unidad->id], $newSources + [
                        'producto_id' => $line->producto_id, 'presente' => $incoming, 'ubicacion_id' => $incoming ? $line->ubicacion_id : null,
                        'ultima_linea_documento_id' => $line->id, 'version_bloqueo' => ($position?->version_bloqueo ?? 0) + 1,
                        'fecha_creacion' => $position?->fecha_creacion ?? $time, 'fecha_actualizacion' => $time,
                    ]);
                }
                DB::table('movimientos_inventario')->insert(['linea_documento_id' => $line->id, 'producto_id' => $line->producto_id, 'ubicacion_id' => $line->ubicacion_id, 'direccion' => $incoming ? 'in' : 'out', 'cantidad' => $line->cantidad, 'creado_por' => $operator->id, 'fecha_registro' => $time]);
                Event::dispatch('inventory.line_posted', [$documento, $line]);
            }
            $documento->forceFill(['estado' => 'confirmed', 'confirmado_por' => $operator->id, 'fecha_confirmacion' => $time, 'modificado_por' => $operator->id, 'version_bloqueo' => $documento->version_bloqueo + 1])->save();

            return $documento;
        }, protectAdministrativeAccess: false);
    }
}
