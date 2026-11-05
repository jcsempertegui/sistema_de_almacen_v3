<?php

namespace App\Actions\Devoluciones;

use App\Models\DevolucionHerramienta;
use App\Models\ItemPrestamoHerramienta;
use App\Models\PrestamoHerramienta;
use App\Models\Usuario;
use App\Services\CiclosPrestamo;
use App\Services\HabilitacionDevolucion;
use App\Services\ModificacionSegura;
use App\Services\RegularizacionDevolucion;
use App\Support\ValidacionOperacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarDevolucion
{
    public function handle(Usuario $actor, ?DevolucionHerramienta $target, array $data): DevolucionHerramienta
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $data) {
            app(ModificacionSegura::class)->exigirPermisos($operator, $target ? 'returns.update' : 'returns.create');
            $doc = $target ? DevolucionHerramienta::whereKey($target->id)->lockForUpdate()->firstOrFail() : null;
            if ($doc) {
                abort_unless($doc->estado === 'draft' && (string) ($data['version_bloqueo'] ?? '') === (string) $doc->version_bloqueo, 409, 'El borrador cambió o está cerrado.');
            }
            ValidacionOperacion::make($data, ['prestamo_id' => 'required|integer|min:1', 'fecha_documento' => 'required|date_format:Y-m-d', 'observaciones' => 'nullable|string|max:10000', 'lineas' => 'required|array|min:1|max:1000', 'lineas.*.item_prestamo_id' => 'required|integer|min:1|distinct', 'lineas.*.ubicacion_id' => 'required|integer|min:1', 'expected_line_count' => 'required|integer|min:1|max:1000'])->validate();
            if ((int) $data['expected_line_count'] !== count($data['lineas'])) {
                throw ValidationException::withMessages(['lineas' => 'El formulario está incompleto.']);
            }
            $prestamo = PrestamoHerramienta::whereKey($data['prestamo_id'])->lockForUpdate()->firstOrFail();
            abort_unless($prestamo->estado === 'confirmed', 409, 'Selecciona un préstamo confirmado.');
            if ($doc && $doc->prestamo_id !== $prestamo->id) {
                throw ValidationException::withMessages(['prestamo_id' => 'El préstamo del borrador no se sustituye.']);
            }
            $values = ['trabajador_id' => $prestamo->trabajador_id, 'prestamo_id' => $prestamo->id, 'fecha_documento' => $data['fecha_documento'], 'observaciones' => $data['observaciones'] ?? null, 'modificado_por' => $operator->id, 'fecha_actualizacion' => now()];
            if ($doc) {
                DB::table('devoluciones_herramientas')->where('id', $doc->id)->update($values + ['version_bloqueo' => $doc->version_bloqueo + 1]);
            } else {
                $id = DB::table('devoluciones_herramientas')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'creado_por' => $operator->id, 'fecha_creacion' => now()]);
                $doc = DevolucionHerramienta::findOrFail($id);
            }
            $doc = $doc->fresh();
            $old = $doc->lineas()->lockForUpdate()->get()->keyBy('item_prestamo_id');
            $kept = [];
            app(RegularizacionDevolucion::class)->invalidateItems($operator, $old->pluck('id')->all(), 'El borrador fue editado.');
            foreach ($data['lineas'] as $input) {
                $original = ItemPrestamoHerramienta::whereKey($input['item_prestamo_id'])->lockForUpdate()->firstOrFail();
                $item = ['devolucion_id' => $doc->id, 'prestamo_id' => $prestamo->id, 'item_prestamo_id' => $original->id, 'unidad_individual_id' => $original->unidad_individual_id, 'producto_id' => $original->producto_id, 'ubicacion_id' => (int) $input['ubicacion_id']];
                if (app(CiclosPrestamo::class)->enabled()) {
                    $item['ciclo_custodia_id'] = app(CiclosPrestamo::class)->current($original->unidad_individual_id, $original->id)->id;
                }
                app(HabilitacionDevolucion::class)->inspect($item, $doc);
                if (isset($old[$original->id])) {
                    $id = $old[$original->id]->id;
                    DB::table('items_devolucion_herramienta')->where('id', $id)->update($item);
                } else {
                    $id = DB::table('items_devolucion_herramienta')->insertGetId($item);
                }$kept[] = $id;
            }
            foreach ($old as $item) {
                if (! in_array($item->id, $kept, true)) {
                    if ($item->autorizaciones()->exists()) {
                        throw ValidationException::withMessages(['lineas' => 'Un ítem con historial de regularización se conserva. Cancela el borrador para excluirlo.']);
                    }DB::table('items_devolucion_herramienta')->where('id', $item->id)->delete();
                }
            }

            return $doc;
        }, protectAdministrativeAccess: false);
    }
}
