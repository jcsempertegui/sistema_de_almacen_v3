<?php

namespace App\Actions\Inventario;

use App\Models\DocumentoInventario;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use Illuminate\Support\Facades\Validator;

class CancelarDocumentoInventario
{
    public function __construct(private ModificacionSegura $security) {}

    public function handle(Usuario $actor, DocumentoInventario $target, array $data): DocumentoInventario
    {
        return $this->security->run($actor, function (Usuario $operator) use ($target, $data) {
            $documento = DocumentoInventario::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->security->exigirPermisos($operator, $documento->permiso('cancel_draft'));
            abort_unless($documento->estado === 'draft', 409, 'Solo puedes cancelar un borrador.');
            abort_unless((string) ($data['version_bloqueo'] ?? '') === (string) $documento->version_bloqueo, 409, 'El documento cambió. Recarga la página.');
            $motivo = Validator::make(['motivo' => is_string($data['motivo'] ?? null) ? trim($data['motivo']) : null], ['motivo' => ['required', 'string', 'max:500']], ['required' => 'Indica el motivo de cancelación.', 'max' => 'El motivo admite hasta 500 caracteres.'])->validate()['motivo'];
            $documento->forceFill(['estado' => 'cancelled', 'motivo_cancelacion' => $motivo, 'cancelado_por' => $operator->id, 'fecha_cancelacion' => now(), 'modificado_por' => $operator->id, 'version_bloqueo' => $documento->version_bloqueo + 1])->save();

            return $documento;
        }, protectAdministrativeAccess: false);
    }
}
