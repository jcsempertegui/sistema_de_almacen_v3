<?php

namespace App\Actions\Returns;

use App\Models\ToolLoan;
use App\Models\ToolReturn;
use App\Models\User;
use App\Services\ReturnRegularization;
use App\Services\SecurityMutation;
use App\Services\WorkerStockPosting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class ConfirmReturn
{
    public function handle(User $actor, ToolReturn $target, mixed $version): ToolReturn
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $version) {
            app(SecurityMutation::class)->requirePermissions($operator, 'returns.confirm');
            $doc = ToolReturn::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($doc->status === 'confirmed') {
                return $doc;
            }abort_unless($doc->status === 'draft' && (string) $version === (string) $doc->lock_version, 409, 'El documento cambió o está cancelado.');
            $loan = ToolLoan::whereKey($doc->loan_id)->lockForUpdate()->firstOrFail();
            abort_unless($loan->status === 'confirmed', 409);
            $items = $doc->lines()->reorder()->orderBy('individual_unit_id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Incluye al menos una herramienta.']);
            }
            foreach ($items as $item) {
                $authorization = app(ReturnRegularization::class)->approvedFor($item->toArray(), $doc);
                app(WorkerStockPosting::class)->post($operator, ['product_id' => $item->product_id, 'location_id' => $item->location_id, 'quantity' => '1'], [$item->individual_unit_id], 'return_item_id', $item->id, true, $item->loan_item_id);
                DB::table('loan_item_settlements')->insert(['loan_item_id' => $item->loan_item_id, 'return_item_id' => $item->id, 'individual_unit_id' => $item->individual_unit_id, 'regularization_authorization_id' => $authorization, 'created_at' => now()]);
                if (DB::table('individual_unit_custodies')->where('individual_unit_id', $item->individual_unit_id)->where('current_loan_item_id', $item->loan_item_id)->update(['current_loan_item_id' => null, 'lock_version' => DB::raw('lock_version+1'), 'updated_at' => now()]) !== 1) {
                    throw ValidationException::withMessages(['lines' => 'La custodia cambió.']);
                }
                if ($authorization) {
                    app(ReturnRegularization::class)->consume($operator, $authorization);
                } else {
                    app(ReturnRegularization::class)->invalidateItems($operator, [$item->id], 'Recepción ordinaria confirmada.');
                }
                Event::dispatch('worker_operation.line_posted', [$doc, $item]);
            }
            DB::table('tool_returns')->where('id', $doc->id)->update(['status' => 'confirmed', 'worker_snapshot' => json_encode($loan->worker_snapshot, JSON_THROW_ON_ERROR), 'confirmed_by' => $operator->id, 'confirmed_at' => now(), 'updated_by' => $operator->id, 'updated_at' => now(), 'lock_version' => $doc->lock_version + 1]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }
}
