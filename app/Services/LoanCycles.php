<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LoanCycles
{
    public function enabled(): bool
    {
        return Schema::hasTable('loan_custody_cycles');
    }

    public function open(object $item, ?int $correction = null, ?int $previous = null): int
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Se requiere transacción.');
        }
        $extra = [];
        if ($correction !== null) {
            $movement = DB::table('inventory_movements')->where('correction_line_id', $correction)->firstOrFail();
            $original = DB::table('inventory_movements')->where('id', $movement->compensated_movement_id)->firstOrFail();
            $extra = ['reversed_return_movement_id' => $original->id, 'source_return_item_id' => $original->return_item_id];
        }

        return DB::table('loan_custody_cycles')->insertGetId($extra + ['loan_item_id' => $item->id, 'individual_unit_id' => $item->individual_unit_id, 'product_id' => $item->product_id, 'opening_loan_item_id' => $correction === null ? $item->id : null, 'opening_correction_line_id' => $correction, 'previous_cycle_id' => $previous, 'created_at' => now()]);
    }

    public function current(int $unit, int $loanItem): object
    {
        $custody = DB::table('individual_unit_custodies')->where('individual_unit_id', $unit)->lockForUpdate()->first();
        $cycle = $custody?->current_cycle_id ? DB::table('loan_custody_cycles')->where('id', $custody->current_cycle_id)->lockForUpdate()->first() : null;
        if (! $cycle || (int) $cycle->loan_item_id !== $loanItem || DB::table('loan_cycle_closures')->where('cycle_id', $cycle->id)->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['lines' => 'La herramienta no tiene este ciclo de préstamo pendiente.']);
        }

        return $cycle;
    }

    public function close(object $cycle, ?int $returnItem, ?int $correction, ?int $authorization = null): void
    {
        DB::table('loan_cycle_closures')->insert(['cycle_id' => $cycle->id, 'loan_item_id' => $cycle->loan_item_id, 'individual_unit_id' => $cycle->individual_unit_id, 'product_id' => $cycle->product_id, 'return_item_id' => $returnItem, 'correction_line_id' => $correction, 'regularization_authorization_id' => $authorization, 'created_at' => now()]);
    }
}
