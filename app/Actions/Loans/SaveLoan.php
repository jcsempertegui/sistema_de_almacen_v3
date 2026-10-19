<?php

namespace App\Actions\Loans;

use App\Models\IndividualUnit;
use App\Models\Product;
use App\Models\ToolLoan;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventoryDraftValidator;
use App\Services\SecurityMutation;
use App\Support\OperationValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveLoan
{
    public function handle(User $actor, ?ToolLoan $target, array $data): ToolLoan
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $data) {
            app(SecurityMutation::class)->requirePermissions($operator, $target ? 'loans.update' : 'loans.create');
            $doc = $target ? ToolLoan::whereKey($target->id)->lockForUpdate()->firstOrFail() : null;
            if ($doc) {
                abort_unless($doc->status === 'draft' && (string) ($data['lock_version'] ?? '') === (string) $doc->lock_version, 409, 'El borrador cambió o está cerrado.');
            }
            OperationValidation::make($data, ['worker_id' => 'required|integer|min:1', 'document_date' => 'required|date_format:Y-m-d', 'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:document_date', 'notes' => 'nullable|string|max:10000', 'lines' => 'required|array|min:1|max:1000', 'lines.*.individual_unit_id' => 'required|integer|min:1', 'lines.*.location_id' => 'required|integer|min:1', 'expected_line_count' => 'required|integer|min:1|max:1000'])->validate();
            if ((int) $data['expected_line_count'] !== count($data['lines'])) {
                throw ValidationException::withMessages(['lines' => 'El formulario está incompleto.']);
            }
            $worker = Worker::whereKey($data['worker_id'])->lockForUpdate()->first();
            if (! $worker?->is_active) {
                throw ValidationException::withMessages(['worker_id' => 'Selecciona un trabajador activo.']);
            }
            $items = [];
            $seen = [];
            foreach ($data['lines'] as $input) {
                $unit = IndividualUnit::whereKey($input['individual_unit_id'])->lockForUpdate()->firstOrFail();
                $line = app(InventoryDraftValidator::class)->line(['product_id' => $unit->product_id, 'location_id' => $input['location_id'], 'quantity' => '1', 'unit_ids' => [$unit->id]], 'lines', $seen);
                if (! Product::whereKey($unit->product_id)->lockForUpdate()->firstOrFail()->is_loanable) {
                    throw ValidationException::withMessages(['lines' => 'Selecciona una herramienta individual prestable.']);
                }
                $items[] = ['individual_unit_id' => $unit->id, 'product_id' => $unit->product_id, 'location_id' => $line['location_id'], 'measurement_unit_id' => $line['measurement_unit_id'], 'unit_symbol' => $line['unit_symbol']];
            }
            $values = ['worker_id' => $worker->id, 'document_date' => $data['document_date'], 'due_date' => $data['due_date'] ?? null, 'notes' => $data['notes'] ?? null, 'updated_by' => $operator->id, 'updated_at' => now()];
            if ($doc) {
                DB::table('tool_loans')->where('id', $doc->id)->update($values + ['lock_version' => $doc->lock_version + 1]);
                DB::table('tool_loan_items')->where('loan_id', $doc->id)->delete();
                $id = $doc->id;
            } else {
                $id = DB::table('tool_loans')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'created_by' => $operator->id, 'created_at' => now()]);
            }
            foreach ($items as $item) {
                DB::table('tool_loan_items')->insert($item + ['loan_id' => $id]);
            }

            return ToolLoan::findOrFail($id);
        }, protectAdministrativeAccess: false);
    }
}
