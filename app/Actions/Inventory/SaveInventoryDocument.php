<?php

namespace App\Actions\Inventory;

use App\Models\InventoryDocument;
use App\Models\User;
use App\Services\InventoryDraftValidator;
use App\Services\SecurityMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveInventoryDocument
{
    public function __construct(private SecurityMutation $security, private InventoryDraftValidator $validator) {}

    public function handle(User $actor, ?InventoryDocument $target, array $data, ?string $importHash = null): InventoryDocument
    {
        return $this->security->run($actor, function (User $operator) use ($target, $data, $importHash) {
            $document = $target ? InventoryDocument::whereKey($target->id)->lockForUpdate()->firstOrFail() : new InventoryDocument(['kind' => $data['kind'] ?? '']);
            $this->security->requirePermissions($operator, $document->permission($target ? 'update' : 'create'));
            if ($importHash !== null) {
                $this->security->requirePermissions($operator, 'initial_stock.import');
                abort_unless(! $target && $document->kind === 'opening' && preg_match('/^[a-f0-9]{64}$/D', $importHash), 422);
            }
            abort_unless(! $target || $document->status === 'draft', 409, 'Solo se editan borradores.');
            if ($target) {
                abort_unless((string) ($data['lock_version'] ?? '') === (string) $document->lock_version, 409, 'El documento cambió. Recarga la página.');
                if (($data['kind'] ?? '') !== $document->kind) {
                    throw ValidationException::withMessages(['kind' => 'No puedes cambiar el tipo de un documento existente.']);
                }
            }
            $validated = $this->validator->validate($data);
            $lines = $validated['lines'];
            unset($validated['lines']);
            $document->fill($validated);
            $document->updated_by = $operator->id;
            if (! $target) {
                $document->uuid = (string) Str::uuid();
                $document->status = 'draft';
                $document->source = $importHash ? 'csv' : 'manual';
                $document->source_sha256 = $importHash;
                $document->created_by = $operator->id;
                $document->lock_version = 1;
            } else {
                $document->lock_version++;
                DB::table('inventory_document_line_units')->where('document_id', $document->id)->delete();
                $document->lines()->delete(); // Only unconfirmed, unreferenced draft lines.
            }
            $document->save();
            foreach ($lines as $i => $dataLine) {
                $ids = $dataLine['unit_ids'];
                unset($dataLine['unit_ids']);
                $line = $document->lines()->create($dataLine + ['line_number' => $i + 1]);
                foreach ($ids as $id) {
                    DB::table('inventory_document_line_units')->insert(['document_line_id' => $line->id, 'document_id' => $document->id, 'product_id' => $line->product_id, 'individual_unit_id' => $id]);
                }
            }

            return $document;
        }, protectAdministrativeAccess: false);
    }
}
