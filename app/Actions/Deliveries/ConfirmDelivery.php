<?php

namespace App\Actions\Deliveries;

use App\Models\MaterialDelivery;
use App\Models\Product;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventoryDraftValidator;
use App\Services\SecurityMutation;
use App\Services\WorkerStockPosting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class ConfirmDelivery
{
    public function handle(User $actor, MaterialDelivery $target, mixed $version): MaterialDelivery
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $version) {
            app(SecurityMutation::class)->requirePermissions($operator, 'deliveries.confirm');
            $doc = MaterialDelivery::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($doc->status === 'confirmed') {
                return $doc;
            }
            abort_unless($doc->status === 'draft' && (string) $version === (string) $doc->lock_version, 409, 'El documento cambió o está cancelado.');
            $worker = Worker::whereKey($doc->worker_id)->lockForUpdate()->firstOrFail();
            if (! $worker->is_active) {
                throw ValidationException::withMessages(['worker_id' => 'El trabajador debe estar activo para recibir una nueva entrega.']);
            }
            $lines = $doc->lines()->reorder()->orderBy('product_id')->orderBy('location_id')->lockForUpdate()->get();
            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Incluye al menos una línea.']);
            } $seen = [];
            foreach ($lines as $line) {
                $ids = $line->units()->lockForUpdate()->pluck('individual_units.id')->all();
                $current = app(InventoryDraftValidator::class)->line(['product_id' => $line->product_id, 'location_id' => $line->location_id, 'quantity' => $line->quantity, 'unit_ids' => $ids], 'lines', $seen);
                foreach (['measurement_unit_id', 'unit_symbol', 'decimal_places', 'control_mode'] as $field) {
                    if ((string) $current[$field] !== (string) $line->$field) {
                        throw ValidationException::withMessages(['lines' => 'La definición cambió. Guarda nuevamente el borrador.']);
                    }
                }
                if (Product::whereKey($line->product_id)->lockForUpdate()->firstOrFail()->is_loanable) {
                    throw ValidationException::withMessages(['lines' => 'Usa un préstamo para herramientas prestables.']);
                }
                app(WorkerStockPosting::class)->post($operator, $line->toArray(), $ids, 'delivery_line_id', $line->id);
                Event::dispatch('worker_operation.line_posted', [$doc, $line]);
            }
            DB::table('material_deliveries')->where('id', $doc->id)->update(['status' => 'confirmed', 'worker_snapshot' => json_encode($worker->only(['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area']), JSON_THROW_ON_ERROR), 'confirmed_by' => $operator->id, 'confirmed_at' => now(), 'updated_by' => $operator->id, 'updated_at' => now(), 'lock_version' => $doc->lock_version + 1]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }
}
