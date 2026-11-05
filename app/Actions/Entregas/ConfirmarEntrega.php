<?php

namespace App\Actions\Entregas;

use App\Models\EntregaMaterial;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\RegistroExistenciasTrabajador;
use App\Services\ValidadorBorradorInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class ConfirmarEntrega
{
    public function handle(Usuario $actor, EntregaMaterial $target, mixed $version): EntregaMaterial
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $version) {
            app(ModificacionSegura::class)->exigirPermisos($operator, 'deliveries.confirm');
            $doc = EntregaMaterial::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($doc->estado === 'confirmed') {
                return $doc;
            }
            abort_unless($doc->estado === 'draft' && (string) $version === (string) $doc->version_bloqueo, 409, 'El documento cambió o está cancelado.');
            $trabajador = Trabajador::whereKey($doc->trabajador_id)->lockForUpdate()->firstOrFail();
            if (! $trabajador->activo) {
                throw ValidationException::withMessages(['trabajador_id' => 'El trabajador debe estar activo para recibir una nueva entrega.']);
            }
            $lineas = $doc->lineas()->reorder()->orderBy('producto_id')->orderBy('ubicacion_id')->lockForUpdate()->get();
            if ($lineas->isEmpty()) {
                throw ValidationException::withMessages(['lineas' => 'Incluye al menos una línea.']);
            } $seen = [];
            foreach ($lineas as $line) {
                $ids = $line->unidades()->lockForUpdate()->pluck('unidades_individuales.id')->all();
                $current = app(ValidadorBorradorInventario::class)->line(['producto_id' => $line->producto_id, 'ubicacion_id' => $line->ubicacion_id, 'cantidad' => $line->cantidad, 'unit_ids' => $ids], 'lineas', $seen);
                foreach (['unidad_medida_id', 'simbolo_unidad', 'decimales', 'modo_control'] as $field) {
                    if ((string) $current[$field] !== (string) $line->$field) {
                        throw ValidationException::withMessages(['lineas' => 'La definición cambió. Guarda nuevamente el borrador.']);
                    }
                }
                if (Producto::whereKey($line->producto_id)->lockForUpdate()->firstOrFail()->prestable) {
                    throw ValidationException::withMessages(['lineas' => 'Usa un préstamo para herramientas prestables.']);
                }
                app(RegistroExistenciasTrabajador::class)->post($operator, $line->toArray(), $ids, 'linea_entrega_id', $line->id);
                Event::dispatch('worker_operation.line_posted', [$doc, $line]);
            }
            DB::table('entregas_materiales')->where('id', $doc->id)->update(['estado' => 'confirmed', 'datos_historicos_trabajador' => json_encode($trabajador->only(['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area']), JSON_THROW_ON_ERROR), 'confirmado_por' => $operator->id, 'fecha_confirmacion' => now(), 'modificado_por' => $operator->id, 'fecha_actualizacion' => now(), 'version_bloqueo' => $doc->version_bloqueo + 1]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }
}
