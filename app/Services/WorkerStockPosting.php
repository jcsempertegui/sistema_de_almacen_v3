<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkerStockPosting
{
    public function post(User $actor, array $line, array $units, string $source, int $sourceId, bool $incoming = false, ?int $loanItem = null): void
    {
        if (DB::transactionLevel() === 0 || ! in_array($source, ['delivery_line_id', 'loan_item_id', 'return_item_id'], true)) {
            throw new \LogicException('La contabilización requiere una transacción y una fuente válida.');
        }
        $time = now();
        $quantity = (string) $line['quantity'];
        $stock = DB::table('stock_positions')->where('product_id', $line['product_id'])->where('location_id', $line['location_id']);
        if (! (clone $stock)->lockForUpdate()->first()) {
            DB::table('stock_positions')->insert(['product_id' => $line['product_id'], 'location_id' => $line['location_id'], 'quantity' => '0', 'lock_version' => 1, 'created_at' => $time, 'updated_at' => $time]);
        }
        $condition = $incoming ? 'quantity <= CAST(999999999999.999999 AS DECIMAL(18,6))-CAST(? AS DECIMAL(18,6))' : 'quantity >= CAST(? AS DECIMAL(18,6))';
        $operator = $incoming ? '+' : '-';
        if (DB::update("UPDATE stock_positions SET quantity=quantity {$operator} CAST(? AS DECIMAL(18,6)), lock_version=lock_version+1, updated_at=? WHERE product_id=? AND location_id=? AND {$condition}", [$quantity, $time, $line['product_id'], $line['location_id'], $quantity]) !== 1) {
            throw ValidationException::withMessages(['lines' => $incoming ? 'La recepción excedería la capacidad del saldo.' : 'Existencias insuficientes en la ubicación elegida.']);
        }
        sort($units);
        foreach ($units as $id) {
            $custody = DB::table('individual_unit_custodies')->where('individual_unit_id', $id)->lockForUpdate()->first();
            if (($loanItem === null && $custody?->current_loan_item_id !== null) || ($loanItem !== null && (int) ($custody?->current_loan_item_id ?? 0) !== $loanItem)) {
                throw ValidationException::withMessages(['lines' => 'La custodia de la herramienta no permite esta operación.']);
            }
            $position = DB::table('individual_unit_positions')->where('individual_unit_id', $id)->lockForUpdate()->first();
            if (($incoming && $position?->is_present) || (! $incoming && (! $position?->is_present || (int) $position->location_id !== (int) $line['location_id']))) {
                throw ValidationException::withMessages(['lines' => 'La identidad no está disponible físicamente para esta operación.']);
            }
            $values = ['product_id' => $line['product_id'], 'is_present' => $incoming, 'location_id' => $incoming ? $line['location_id'] : null, 'last_document_line_id' => null, 'last_delivery_line_id' => null, 'last_loan_item_id' => null, 'last_return_item_id' => null, 'last_'.$source => $sourceId, 'lock_version' => ($position?->lock_version ?? 0) + 1, 'created_at' => $position?->created_at ?? $time, 'updated_at' => $time];
            DB::table('individual_unit_positions')->updateOrInsert(['individual_unit_id' => $id], $values);
        }
        DB::table('inventory_movements')->insert([$source => $sourceId, 'product_id' => $line['product_id'], 'location_id' => $line['location_id'], 'quantity' => $quantity, 'direction' => $incoming ? 'in' : 'out', 'created_by' => $actor->id, 'posted_at' => $time]);
    }
}
