<?php

namespace App\Actions\Inventory;

use App\Models\InventoryDocument;
use App\Models\User;
use App\Services\SecurityMutation;
use Illuminate\Support\Facades\Validator;

class CancelInventoryDocument
{
    public function __construct(private SecurityMutation $security) {}

    public function handle(User $actor, InventoryDocument $target, array $data): InventoryDocument
    {
        return $this->security->run($actor, function (User $operator) use ($target, $data) {
            $document = InventoryDocument::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->security->requirePermissions($operator, $document->permission('cancel_draft'));
            abort_unless($document->status === 'draft', 409, 'Solo puedes cancelar un borrador.');
            abort_unless((string) ($data['lock_version'] ?? '') === (string) $document->lock_version, 409, 'El documento cambió. Recarga la página.');
            $reason = Validator::make(['reason' => is_string($data['reason'] ?? null) ? trim($data['reason']) : null], ['reason' => ['required', 'string', 'max:500']], ['required' => 'Indica el motivo de cancelación.', 'max' => 'El motivo admite hasta 500 caracteres.'])->validate()['reason'];
            $document->forceFill(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_by' => $operator->id, 'cancelled_at' => now(), 'updated_by' => $operator->id, 'lock_version' => $document->lock_version + 1])->save();

            return $document;
        }, protectAdministrativeAccess: false);
    }
}
