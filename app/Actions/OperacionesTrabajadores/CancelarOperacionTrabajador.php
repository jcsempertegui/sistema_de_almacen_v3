<?php

namespace App\Actions\OperacionesTrabajadores;

use App\Models\DevolucionHerramienta;
use App\Models\DocumentoOperacionTrabajador;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\RegularizacionDevolucion;
use App\Support\TiposOperacionTrabajador;
use App\Support\ValidacionOperacion;
use Illuminate\Support\Facades\DB;

class CancelarOperacionTrabajador
{
    public function handle(Usuario $actor, DocumentoOperacionTrabajador $target, mixed $version, string $motivo): DocumentoOperacionTrabajador
    {
        return app(ModificacionSegura::class)->run($actor, function (Usuario $operator) use ($target, $version, $motivo) {
            app(ModificacionSegura::class)->exigirPermisos($operator, TiposOperacionTrabajador::tipo($target).'.cancel_draft');
            $doc = $target::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($doc->estado === 'draft' && (string) $version === (string) $doc->version_bloqueo, 409, 'El borrador cambió o está cerrado.');
            ValidacionOperacion::make(['motivo' => trim($motivo)], ['motivo' => 'required|string|max:500'], ['required' => 'El motivo es obligatorio.'])->validate();
            if ($doc instanceof DevolucionHerramienta) {
                app(RegularizacionDevolucion::class)->invalidateItems($operator, $doc->lineas()->pluck('id')->all(), 'Borrador cancelado: '.trim($motivo));
            }
            DB::table($doc->getTable())->where('id', $doc->id)->update(['estado' => 'cancelled', 'motivo_cancelacion' => trim($motivo), 'cancelado_por' => $operator->id, 'fecha_cancelacion' => now(), 'modificado_por' => $operator->id, 'fecha_actualizacion' => now(), 'version_bloqueo' => $doc->version_bloqueo + 1]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }
}
