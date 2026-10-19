<?php

namespace App\Actions\WorkerOperations;

use App\Models\ToolReturn;
use App\Models\User;
use App\Models\WorkerOperationDocument;
use App\Services\ReturnRegularization;
use App\Services\SecurityMutation;
use App\Support\OperationValidation;
use App\Support\WorkerOperationTypes;
use Illuminate\Support\Facades\DB;

class CancelWorkerOperation
{
    public function handle(User $actor, WorkerOperationDocument $target, mixed $version, string $reason): WorkerOperationDocument
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $version, $reason) {
            app(SecurityMutation::class)->requirePermissions($operator, WorkerOperationTypes::kind($target).'.cancel_draft');
            $doc = $target::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($doc->status === 'draft' && (string) $version === (string) $doc->lock_version, 409, 'El borrador cambió o está cerrado.');
            OperationValidation::make(['reason' => trim($reason)], ['reason' => 'required|string|max:500'], ['required' => 'El motivo es obligatorio.'])->validate();
            if ($doc instanceof ToolReturn) {
                app(ReturnRegularization::class)->invalidateItems($operator, $doc->lines()->pluck('id')->all(), 'Borrador cancelado: '.trim($reason));
            }
            DB::table($doc->getTable())->where('id', $doc->id)->update(['status' => 'cancelled', 'cancellation_reason' => trim($reason), 'cancelled_by' => $operator->id, 'cancelled_at' => now(), 'updated_by' => $operator->id, 'updated_at' => now(), 'lock_version' => $doc->lock_version + 1]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }
}
