<?php

namespace App\Actions\Loans;

use App\Models\Product;
use App\Models\ToolLoan;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventoryDraftValidator;
use App\Services\LoanCycles;
use App\Services\SecurityMutation;
use App\Services\WorkerStockPosting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class ConfirmLoan
{
    public function handle(User $actor, ToolLoan $target, mixed $version): ToolLoan
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $version) {
            app(SecurityMutation::class)->requirePermissions($operator, 'loans.confirm');
            $doc = ToolLoan::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($doc->status === 'confirmed') {
                return $doc;
            }abort_unless($doc->status === 'draft' && (string) $version === (string) $doc->lock_version, 409, 'El documento cambió o está cancelado.');
            $worker = Worker::whereKey($doc->worker_id)->lockForUpdate()->firstOrFail();
            if (! $worker->is_active) {
                throw ValidationException::withMessages(['worker_id' => 'El trabajador debe estar activo para un nuevo préstamo.']);
            }
            $items = $doc->lines()->reorder()->orderBy('individual_unit_id')->lockForUpdate()->get();
            $seen = [];
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Incluye al menos una herramienta.']);
            }
            foreach ($items as $item) {
                $line = app(InventoryDraftValidator::class)->line(['product_id' => $item->product_id, 'location_id' => $item->location_id, 'quantity' => '1', 'unit_ids' => [$item->individual_unit_id]], 'lines', $seen);
                if (! Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail()->is_loanable || (int) $line['measurement_unit_id'] !== $item->measurement_unit_id || $line['unit_symbol'] !== $item->unit_symbol) {
                    throw ValidationException::withMessages(['lines' => 'La herramienta o su definición no permiten este préstamo.']);
                }
                app(WorkerStockPosting::class)->post($operator, ['product_id' => $item->product_id, 'location_id' => $item->location_id, 'quantity' => '1'], [$item->individual_unit_id], 'loan_item_id', $item->id);
                DB::table('individual_unit_custodies')->insertOrIgnore(['individual_unit_id' => $item->individual_unit_id, 'product_id' => $item->product_id, 'created_at' => now(), 'updated_at' => now()]);
                $cycleValues = app(LoanCycles::class)->enabled() ? ['current_cycle_id' => app(LoanCycles::class)->open($item)] : [];
                $changed = DB::table('individual_unit_custodies')->where('individual_unit_id', $item->individual_unit_id)->whereNull('current_loan_item_id')->update($cycleValues + ['current_loan_item_id' => $item->id, 'lock_version' => DB::raw('lock_version+1'), 'updated_at' => now()]);
                if ($changed !== 1) {
                    throw ValidationException::withMessages(['lines' => 'La herramienta ya tiene custodia vigente.']);
                }
                Event::dispatch('worker_operation.line_posted', [$doc, $item]);
            }
            DB::table('tool_loans')->where('id', $doc->id)->update(['status' => 'confirmed', 'worker_snapshot' => json_encode($worker->only(['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area']), JSON_THROW_ON_ERROR), 'confirmed_by' => $operator->id, 'confirmed_at' => now(), 'updated_by' => $operator->id, 'updated_at' => now(), 'lock_version' => $doc->lock_version + 1]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }
}
