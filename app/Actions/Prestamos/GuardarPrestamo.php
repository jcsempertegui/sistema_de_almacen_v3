<?php

namespace App\Actions\Prestamos;

use App\Models\PrestamoHerramienta;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\ValidadorBorradorInventario;
use App\Support\ValidacionOperacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarPrestamo
{
    public function handle(Usuario $actor, ?PrestamoHerramienta $target, array $data): PrestamoHerramienta
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $data) {
            app(ModificacionSegura::class)->exigirPermisos($operator, $target ? 'loans.update' : 'loans.create');
            $doc = $target ? PrestamoHerramienta::whereKey($target->id)->lockForUpdate()->firstOrFail() : null;
            if ($doc) {
                abort_unless($doc->estado === 'draft' && (string) ($data['version_bloqueo'] ?? '') === (string) $doc->version_bloqueo, 409, 'El borrador cambió o está cerrado.');
            }
            ValidacionOperacion::make($data, ['trabajador_id' => 'required|integer|min:1', 'fecha_documento' => 'required|date_format:Y-m-d', 'fecha_limite' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_documento', 'observaciones' => 'nullable|string|max:10000', 'lineas' => 'required|array|min:1|max:1000', 'lineas.*.unidad_individual_id' => 'required|integer|min:1', 'lineas.*.ubicacion_id' => 'required|integer|min:1', 'expected_line_count' => 'required|integer|min:1|max:1000'])->validate();
            if ((int) $data['expected_line_count'] !== count($data['lineas'])) {
                throw ValidationException::withMessages(['lineas' => 'El formulario está incompleto.']);
            }
            $trabajador = Trabajador::whereKey($data['trabajador_id'])->lockForUpdate()->first();
            if (! $trabajador?->activo) {
                throw ValidationException::withMessages(['trabajador_id' => 'Selecciona un trabajador activo.']);
            }
            $items = [];
            $seen = [];
            foreach ($data['lineas'] as $input) {
                $unidad = UnidadIndividual::whereKey($input['unidad_individual_id'])->lockForUpdate()->firstOrFail();
                $line = app(ValidadorBorradorInventario::class)->line(['producto_id' => $unidad->producto_id, 'ubicacion_id' => $input['ubicacion_id'], 'cantidad' => '1', 'unit_ids' => [$unidad->id]], 'lineas', $seen);
                if (! Producto::whereKey($unidad->producto_id)->lockForUpdate()->firstOrFail()->prestable) {
                    throw ValidationException::withMessages(['lineas' => 'Selecciona una herramienta individual prestable.']);
                }
                $items[] = ['unidad_individual_id' => $unidad->id, 'producto_id' => $unidad->producto_id, 'ubicacion_id' => $line['ubicacion_id'], 'unidad_medida_id' => $line['unidad_medida_id'], 'simbolo_unidad' => $line['simbolo_unidad']];
            }
            $values = ['trabajador_id' => $trabajador->id, 'fecha_documento' => $data['fecha_documento'], 'fecha_limite' => $data['fecha_limite'] ?? null, 'observaciones' => $data['observaciones'] ?? null, 'modificado_por' => $operator->id, 'fecha_actualizacion' => now()];
            if ($doc) {
                DB::table('prestamos_herramientas')->where('id', $doc->id)->update($values + ['version_bloqueo' => $doc->version_bloqueo + 1]);
                DB::table('items_prestamo_herramienta')->where('prestamo_id', $doc->id)->delete();
                $id = $doc->id;
            } else {
                $id = DB::table('prestamos_herramientas')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'creado_por' => $operator->id, 'fecha_creacion' => now()]);
            }
            foreach ($items as $item) {
                DB::table('items_prestamo_herramienta')->insert($item + ['prestamo_id' => $id]);
            }

            return PrestamoHerramienta::findOrFail($id);
        }, protectAdministrativeAccess: false);
    }
}
