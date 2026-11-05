<?php

namespace App\Actions\Entregas;

use App\Models\EntregaMaterial;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\ValidadorBorradorInventario;
use App\Support\ValidacionOperacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarEntrega
{
    public function handle(Usuario $actor, ?EntregaMaterial $target, array $data): EntregaMaterial
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $data) {
            app(ModificacionSegura::class)->exigirPermisos($operator, $target ? 'deliveries.update' : 'deliveries.create');
            $doc = $target ? EntregaMaterial::whereKey($target->id)->lockForUpdate()->firstOrFail() : null;
            if ($doc) {
                abort_unless($doc->estado === 'draft' && (string) ($data['version_bloqueo'] ?? '') === (string) $doc->version_bloqueo, 409, 'El borrador cambió o está cerrado.');
            }
            ValidacionOperacion::make($data, ['trabajador_id' => 'required|integer|min:1', 'fecha_documento' => 'required|date_format:Y-m-d', 'observaciones' => 'nullable|string|max:10000', 'lineas' => 'required|array|min:1|max:500', 'expected_line_count' => 'required|integer|min:1|max:500'])->validate();
            if ((int) $data['expected_line_count'] !== count($data['lineas'])) {
                throw ValidationException::withMessages(['lineas' => 'El formulario está incompleto. Recarga antes de guardar.']);
            }
            $trabajador = Trabajador::whereKey($data['trabajador_id'])->lockForUpdate()->first();
            if (! $trabajador?->activo) {
                throw ValidationException::withMessages(['trabajador_id' => 'Selecciona un trabajador activo.']);
            }
            $lineas = [];
            $seen = [];
            $pairs = [];
            foreach (array_values($data['lineas']) as $i => $input) {
                ValidacionOperacion::make($input, ['producto_id' => 'required|integer|min:1', 'ubicacion_id' => 'required|integer|min:1', 'cantidad' => 'required', 'unit_ids' => 'present|array', 'unit_ids.*' => 'integer|min:1'])->validate();
                $line = app(ValidadorBorradorInventario::class)->line($input, 'lineas.'.$i, $seen);
                if (Producto::whereKey($line['producto_id'])->lockForUpdate()->firstOrFail()->prestable) {
                    throw ValidationException::withMessages(['lineas' => 'Las herramientas prestables se entregan mediante préstamos.']);
                }
                $pair = $line['producto_id'].':'.$line['ubicacion_id'];
                if (isset($pairs[$pair])) {
                    throw ValidationException::withMessages(['lineas' => 'No repitas el producto y la ubicación.']);
                } $pairs[$pair] = true;
                $lineas[] = $line;
            }
            $values = ['trabajador_id' => $trabajador->id, 'fecha_documento' => $data['fecha_documento'], 'observaciones' => $data['observaciones'] ?? null, 'modificado_por' => $operator->id, 'fecha_actualizacion' => now()];
            if ($doc) {
                DB::table('entregas_materiales')->where('id', $doc->id)->update($values + ['version_bloqueo' => $doc->version_bloqueo + 1]);
                $ids = $doc->lineas()->pluck('id');
                DB::table('unidades_linea_entrega')->whereIn('linea_entrega_id', $ids)->delete();
                DB::table('lineas_entrega_material')->whereIn('id', $ids)->delete();
                $id = $doc->id;
            } else {
                $id = DB::table('entregas_materiales')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'creado_por' => $operator->id, 'fecha_creacion' => now()]);
            }
            foreach ($lineas as $i => $line) {
                $unidades = $line['unit_ids'];
                unset($line['unit_ids']);
                $lineId = DB::table('lineas_entrega_material')->insertGetId($line + ['entrega_id' => $id, 'numero_linea' => $i + 1]);
                foreach ($unidades as $unidad) {
                    DB::table('unidades_linea_entrega')->insert(['linea_entrega_id' => $lineId, 'entrega_id' => $id, 'producto_id' => $line['producto_id'], 'unidad_individual_id' => $unidad]);
                }
            }

            return EntregaMaterial::findOrFail($id);
        }, protectAdministrativeAccess: false);
    }
}
