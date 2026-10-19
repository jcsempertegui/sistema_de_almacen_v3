<?php

namespace App\Actions\Deliveries;

use App\Models\MaterialDelivery;
use App\Models\Product;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventoryDraftValidator;
use App\Services\SecurityMutation;
use App\Support\OperationValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveDelivery
{
    public function handle(User $actor, ?MaterialDelivery $target, array $data): MaterialDelivery
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $data) {
            app(SecurityMutation::class)->requirePermissions($operator, $target ? 'deliveries.update' : 'deliveries.create');
            $doc = $target ? MaterialDelivery::whereKey($target->id)->lockForUpdate()->firstOrFail() : null;
            if ($doc) {
                abort_unless($doc->status === 'draft' && (string) ($data['lock_version'] ?? '') === (string) $doc->lock_version, 409, 'El borrador cambió o está cerrado.');
            }
            OperationValidation::make($data, ['worker_id' => 'required|integer|min:1', 'document_date' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:10000', 'lines' => 'required|array|min:1|max:500', 'expected_line_count' => 'required|integer|min:1|max:500'])->validate();
            if ((int) $data['expected_line_count'] !== count($data['lines'])) {
                throw ValidationException::withMessages(['lines' => 'El formulario está incompleto. Recarga antes de guardar.']);
            }
            $worker = Worker::whereKey($data['worker_id'])->lockForUpdate()->first();
            if (! $worker?->is_active) {
                throw ValidationException::withMessages(['worker_id' => 'Selecciona un trabajador activo.']);
            }
            $lines = [];
            $seen = [];
            $pairs = [];
            foreach (array_values($data['lines']) as $i => $input) {
                OperationValidation::make($input, ['product_id' => 'required|integer|min:1', 'location_id' => 'required|integer|min:1', 'quantity' => 'required', 'unit_ids' => 'present|array', 'unit_ids.*' => 'integer|min:1'])->validate();
                $line = app(InventoryDraftValidator::class)->line($input, 'lines.'.$i, $seen);
                if (Product::whereKey($line['product_id'])->lockForUpdate()->firstOrFail()->is_loanable) {
                    throw ValidationException::withMessages(['lines' => 'Las herramientas prestables se entregan mediante préstamos.']);
                }
                $pair = $line['product_id'].':'.$line['location_id'];
                if (isset($pairs[$pair])) {
                    throw ValidationException::withMessages(['lines' => 'No repitas el producto y la ubicación.']);
                } $pairs[$pair] = true;
                $lines[] = $line;
            }
            $values = ['worker_id' => $worker->id, 'document_date' => $data['document_date'], 'notes' => $data['notes'] ?? null, 'updated_by' => $operator->id, 'updated_at' => now()];
            if ($doc) {
                DB::table('material_deliveries')->where('id', $doc->id)->update($values + ['lock_version' => $doc->lock_version + 1]);
                $ids = $doc->lines()->pluck('id');
                DB::table('material_delivery_line_units')->whereIn('delivery_line_id', $ids)->delete();
                DB::table('material_delivery_lines')->whereIn('id', $ids)->delete();
                $id = $doc->id;
            } else {
                $id = DB::table('material_deliveries')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'created_by' => $operator->id, 'created_at' => now()]);
            }
            foreach ($lines as $i => $line) {
                $units = $line['unit_ids'];
                unset($line['unit_ids']);
                $lineId = DB::table('material_delivery_lines')->insertGetId($line + ['delivery_id' => $id, 'line_number' => $i + 1]);
                foreach ($units as $unit) {
                    DB::table('material_delivery_line_units')->insert(['delivery_line_id' => $lineId, 'delivery_id' => $id, 'product_id' => $line['product_id'], 'individual_unit_id' => $unit]);
                }
            }

            return MaterialDelivery::findOrFail($id);
        }, protectAdministrativeAccess: false);
    }
}
