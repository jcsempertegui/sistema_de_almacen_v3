<?php

namespace App\Actions\Inventory;

use App\Models\InventoryDocument;
use App\Models\User;
use App\Services\InventoryDraftValidator;
use App\Services\SecurityMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConfirmInventoryDocument
{
    public function __construct(private SecurityMutation $security, private InventoryDraftValidator $validator) {}

    public function handle(User $actor, InventoryDocument $target, mixed $version): InventoryDocument
    {
        return $this->security->run($actor, function (User $operator) use ($target, $version) {
            $document = InventoryDocument::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->security->requirePermissions($operator, $document->permission('confirm'));
            // A valid retry still requires current authorization, but not a new draft version.
            if ($document->status === 'confirmed') {
                return $document;
            }
            abort_unless($document->status === 'draft', 409, 'El documento está cancelado.');
            abort_unless((string) $version === (string) $document->lock_version, 409, 'El documento cambió. Recarga antes de confirmar.');
            $lines = $document->lines()->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'El documento debe contener al menos una línea.']);
            }
            $seen = [];
            foreach ($lines as $line) {
                // Eager reads would retain an obsolete snapshot under REPEATABLE READ.
                $line->setRelation('units', $line->units()->lockForUpdate()->get());
                $current = $this->validator->line(['product_id' => $line->product_id, 'location_id' => $line->location_id, 'quantity' => $line->quantity, 'unit_ids' => $line->units->pluck('id')->all()], 'lines.'.$line->line_number, $seen);
                foreach (['measurement_unit_id', 'unit_symbol', 'decimal_places', 'control_mode'] as $field) {
                    if ((string) $current[$field] !== (string) $line->$field) {
                        throw ValidationException::withMessages(['lines' => 'La definición del producto cambió. Revisa y guarda nuevamente el borrador.']);
                    }
                }
            }
            if ($document->kind === 'opening') {
                foreach ($lines->pluck('product_id')->unique()->sort() as $productId) {
                    if (DB::table('initial_stock_products')->where('product_id', $productId)->lockForUpdate()->first()
                        || DB::table('inventory_movements')->where('product_id', $productId)->lockForUpdate()->first()) {
                        throw ValidationException::withMessages(['lines' => 'El producto ya tiene apertura o movimientos. No se permite una segunda apertura.']);
                    }
                    DB::table('initial_stock_products')->insert(['product_id' => $productId, 'inventory_document_id' => $document->id, 'kind' => 'opening']);
                }
            }
            $incoming = $document->kind !== 'issue';
            $time = now();
            foreach ($lines->sortBy(fn ($line) => sprintf('%020d:%020d', $line->product_id, $line->location_id)) as $line) {
                $stock = DB::table('stock_positions')->where('product_id', $line->product_id)->where('location_id', $line->location_id);
                if (! (clone $stock)->lockForUpdate()->first()) {
                    DB::table('stock_positions')->insert(['product_id' => $line->product_id, 'location_id' => $line->location_id, 'quantity' => '0', 'lock_version' => 1, 'created_at' => $time, 'updated_at' => $time]);
                }
                // SQL DECIMAL arithmetic: neither floats nor PHP-sized scaled integers.
                $operatorSql = $incoming ? '+' : '-';
                $condition = $incoming ? 'quantity <= CAST(999999999999.999999 AS DECIMAL(18,6)) - CAST(? AS DECIMAL(18,6))' : 'quantity >= CAST(? AS DECIMAL(18,6))';
                $changed = DB::update("UPDATE stock_positions SET quantity = quantity {$operatorSql} CAST(? AS DECIMAL(18,6)), lock_version = lock_version + 1, updated_at = ? WHERE product_id = ? AND location_id = ? AND {$condition}", [$line->quantity, $time, $line->product_id, $line->location_id, $line->quantity]);
                if ($changed !== 1) {
                    throw ValidationException::withMessages(['lines' => $incoming ? 'El saldo excedería la capacidad decimal del sistema.' : 'Existencias insuficientes en la ubicación seleccionada.']);
                }
                foreach ($line->units->sortBy('id') as $unit) {
                    if (Schema::hasTable('individual_unit_custodies') && DB::table('individual_unit_custodies')->where('individual_unit_id', $unit->id)->whereNotNull('current_loan_item_id')->lockForUpdate()->first()) {
                        throw ValidationException::withMessages(['lines' => 'La herramienta está prestada. Registra su devolución vinculada al préstamo.']);
                    }
                    $position = DB::table('individual_unit_positions')->where('individual_unit_id', $unit->id)->lockForUpdate()->first();
                    if (($incoming && $position?->is_present) || (! $incoming && (! $position?->is_present || $position->location_id !== $line->location_id))) {
                        throw ValidationException::withMessages(['lines' => 'La unidad '.$unit->identifier.' no tiene una presencia física compatible con esta operación.']);
                    }
                    $newSources = Schema::hasColumn('individual_unit_positions', 'last_loan_item_id')
                        ? ['last_delivery_line_id' => null, 'last_loan_item_id' => null, 'last_return_item_id' => null] : [];
                    DB::table('individual_unit_positions')->updateOrInsert(['individual_unit_id' => $unit->id], $newSources + [
                        'product_id' => $line->product_id, 'is_present' => $incoming, 'location_id' => $incoming ? $line->location_id : null,
                        'last_document_line_id' => $line->id, 'lock_version' => ($position?->lock_version ?? 0) + 1,
                        'created_at' => $position?->created_at ?? $time, 'updated_at' => $time,
                    ]);
                }
                DB::table('inventory_movements')->insert(['document_line_id' => $line->id, 'product_id' => $line->product_id, 'location_id' => $line->location_id, 'direction' => $incoming ? 'in' : 'out', 'quantity' => $line->quantity, 'created_by' => $operator->id, 'posted_at' => $time]);
                Event::dispatch('inventory.line_posted', [$document, $line]);
            }
            $document->forceFill(['status' => 'confirmed', 'confirmed_by' => $operator->id, 'confirmed_at' => $time, 'updated_by' => $operator->id, 'lock_version' => $document->lock_version + 1])->save();

            return $document;
        }, protectAdministrativeAccess: false);
    }
}
