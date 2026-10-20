<?php

namespace App\Actions\Returns;

use App\Models\ToolLoan;
use App\Models\ToolLoanItem;
use App\Models\ToolReturn;
use App\Models\User;
use App\Services\LoanCycles;
use App\Services\ReturnEligibility;
use App\Services\ReturnRegularization;
use App\Services\SecurityMutation;
use App\Support\OperationValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveReturn
{
    public function handle(User $actor, ?ToolReturn $target, array $data): ToolReturn
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $data) {
            app(SecurityMutation::class)->requirePermissions($operator, $target ? 'returns.update' : 'returns.create');
            $doc = $target ? ToolReturn::whereKey($target->id)->lockForUpdate()->firstOrFail() : null;
            if ($doc) {
                abort_unless($doc->status === 'draft' && (string) ($data['lock_version'] ?? '') === (string) $doc->lock_version, 409, 'El borrador cambió o está cerrado.');
            }
            OperationValidation::make($data, ['loan_id' => 'required|integer|min:1', 'document_date' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:10000', 'lines' => 'required|array|min:1|max:1000', 'lines.*.loan_item_id' => 'required|integer|min:1|distinct', 'lines.*.location_id' => 'required|integer|min:1', 'expected_line_count' => 'required|integer|min:1|max:1000'])->validate();
            if ((int) $data['expected_line_count'] !== count($data['lines'])) {
                throw ValidationException::withMessages(['lines' => 'El formulario está incompleto.']);
            }
            $loan = ToolLoan::whereKey($data['loan_id'])->lockForUpdate()->firstOrFail();
            abort_unless($loan->status === 'confirmed', 409, 'Selecciona un préstamo confirmado.');
            if ($doc && $doc->loan_id !== $loan->id) {
                throw ValidationException::withMessages(['loan_id' => 'El préstamo del borrador no se sustituye.']);
            }
            $values = ['worker_id' => $loan->worker_id, 'loan_id' => $loan->id, 'document_date' => $data['document_date'], 'notes' => $data['notes'] ?? null, 'updated_by' => $operator->id, 'updated_at' => now()];
            if ($doc) {
                DB::table('tool_returns')->where('id', $doc->id)->update($values + ['lock_version' => $doc->lock_version + 1]);
            } else {
                $id = DB::table('tool_returns')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'created_by' => $operator->id, 'created_at' => now()]);
                $doc = ToolReturn::findOrFail($id);
            }
            $doc = $doc->fresh();
            $old = $doc->lines()->lockForUpdate()->get()->keyBy('loan_item_id');
            $kept = [];
            app(ReturnRegularization::class)->invalidateItems($operator, $old->pluck('id')->all(), 'El borrador fue editado.');
            foreach ($data['lines'] as $input) {
                $original = ToolLoanItem::whereKey($input['loan_item_id'])->lockForUpdate()->firstOrFail();
                $item = ['return_id' => $doc->id, 'loan_id' => $loan->id, 'loan_item_id' => $original->id, 'individual_unit_id' => $original->individual_unit_id, 'product_id' => $original->product_id, 'location_id' => (int) $input['location_id']];
                if (app(LoanCycles::class)->enabled()) {
                    $item['custody_cycle_id'] = app(LoanCycles::class)->current($original->individual_unit_id, $original->id)->id;
                }
                app(ReturnEligibility::class)->inspect($item, $doc);
                if (isset($old[$original->id])) {
                    $id = $old[$original->id]->id;
                    DB::table('tool_return_items')->where('id', $id)->update($item);
                } else {
                    $id = DB::table('tool_return_items')->insertGetId($item);
                }$kept[] = $id;
            }
            foreach ($old as $item) {
                if (! in_array($item->id, $kept, true)) {
                    if ($item->authorizations()->exists()) {
                        throw ValidationException::withMessages(['lines' => 'Un ítem con historial de regularización se conserva. Cancela el borrador para excluirlo.']);
                    }DB::table('tool_return_items')->where('id', $item->id)->delete();
                }
            }

            return $doc;
        }, protectAdministrativeAccess: false);
    }
}
