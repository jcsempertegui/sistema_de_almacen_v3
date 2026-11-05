<?php

namespace App\Actions\Inventario;

use App\Models\DocumentoInventario;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\ValidadorBorradorInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarDocumentoInventario
{
    public function __construct(private ModificacionSegura $security, private ValidadorBorradorInventario $validator) {}

    public function handle(Usuario $actor, ?DocumentoInventario $target, array $data, ?string $importHash = null): DocumentoInventario
    {
        return $this->security->run($actor, function (Usuario $operator) use ($target, $data, $importHash) {
            $documento = $target ? DocumentoInventario::whereKey($target->id)->lockForUpdate()->firstOrFail() : new DocumentoInventario(['tipo' => $data['tipo'] ?? '']);
            $this->security->exigirPermisos($operator, $documento->permiso($target ? 'update' : 'create'));
            if ($importHash !== null) {
                $this->security->exigirPermisos($operator, 'initial_stock.import');
                abort_unless(! $target && $documento->tipo === 'opening' && preg_match('/^[a-f0-9]{64}$/D', $importHash), 422);
            }
            abort_unless(! $target || $documento->estado === 'draft', 409, 'Solo se editan borradores.');
            if ($target) {
                abort_unless((string) ($data['version_bloqueo'] ?? '') === (string) $documento->version_bloqueo, 409, 'El documento cambió. Recarga la página.');
                if (($data['tipo'] ?? '') !== $documento->tipo) {
                    throw ValidationException::withMessages(['tipo' => 'No puedes cambiar el tipo de un documento existente.']);
                }
            }
            $validated = $this->validator->validate($data);
            $lineas = $validated['lineas'];
            unset($validated['lineas']);
            $documento->fill($validated);
            $documento->modificado_por = $operator->id;
            if (! $target) {
                $documento->uuid = (string) Str::uuid();
                $documento->estado = 'draft';
                $documento->origen = $importHash ? 'csv' : 'manual';
                $documento->sha256_origen = $importHash;
                $documento->creado_por = $operator->id;
                $documento->version_bloqueo = 1;
            } else {
                $documento->version_bloqueo++;
                DB::table('unidades_linea_documento')->where('documento_id', $documento->id)->delete();
                $documento->lineas()->delete(); // Only unconfirmed, unreferenced draft lineas.
            }
            $documento->save();
            foreach ($lineas as $i => $dataLine) {
                $ids = $dataLine['unit_ids'];
                unset($dataLine['unit_ids']);
                $line = $documento->lineas()->create($dataLine + ['numero_linea' => $i + 1]);
                foreach ($ids as $id) {
                    DB::table('unidades_linea_documento')->insert(['linea_documento_id' => $line->id, 'documento_id' => $documento->id, 'producto_id' => $line->producto_id, 'unidad_individual_id' => $id]);
                }
            }

            return $documento;
        }, protectAdministrativeAccess: false);
    }
}
