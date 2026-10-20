<?php

namespace App\Services;

use App\Models\InventoryCorrection;
use App\Models\InventoryDocument;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\MaterialDelivery;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ToolLoan;
use App\Models\ToolLoanItem;
use App\Models\ToolReturn;
use App\Models\User;
use App\Support\OperationValidation;
use App\Support\TechnicalDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryCorrections
{
    public const SOURCES = [
        'inventory-documents' => [InventoryDocument::class, 'document_line_id', 'inventory_document_id'],
        'deliveries' => [MaterialDelivery::class, 'delivery_line_id', 'delivery_id'],
        'loans' => [ToolLoan::class, 'loan_item_id', 'loan_id'],
        'returns' => [ToolReturn::class, 'return_item_id', 'return_id'],
        'corrections' => [InventoryCorrection::class, 'correction_line_id', 'target_correction_id'],
    ];

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['lines' => $message]);
    }

    private function permission(User $actor, string $kind, string $action): void
    {
        app(SecurityMutation::class)->requirePermissions($actor, ($kind === 'adjustment' ? 'adjustments' : 'compensations').'.'.$action);
    }

    private function locked(int $id): InventoryCorrection
    {
        return InventoryCorrection::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function event(User $u, int $authorization, string $action, array $payload = []): void
    {
        DB::table('correction_authorization_events')->insert(['authorization_id' => $authorization, 'actor_id' => $u->id, 'action' => $action, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function sourceMovements(string $kind, int $id)
    {
        abort_unless(isset(self::SOURCES[$kind]), 422, 'Tipo documental inválido.');
        [$class,$column] = self::SOURCES[$kind];
        $doc = $class::whereKey($id)->lockForUpdate()->firstOrFail();
        if (($kind === 'corrections' && ($doc->status !== 'executed' || $doc->kind !== 'adjustment')) || ($kind !== 'corrections' && $doc->status !== 'confirmed')) {
            $this->fail('Selecciona un documento confirmado; no se compensan compensaciones.');
        }
        if (DB::table('document_compensations')->where(self::SOURCES[$kind][2], $id)->lockForUpdate()->first()) {
            $this->fail('El documento ya fue compensado.');
        }

        return InventoryMovement::whereIn($column, $doc->lines()->pluck('id'))->orderBy('id')->lockForUpdate()->get();
    }

    public function movementUnits(InventoryMovement $m): array
    {
        if ($m->loan_item_id !== null || $m->return_item_id !== null) {
            return [(int) $m->origin->individual_unit_id];
        }

        return $m->origin->units()->orderBy('individual_units.id')->pluck('individual_units.id')->map(fn ($id) => (int) $id)->all();
    }

    private function stock(int $product, int $location): object
    {
        return DB::table('stock_positions')->where('product_id', $product)->where('location_id', $location)->lockForUpdate()->first() ?? (object) ['quantity' => '0.000000', 'lock_version' => 0];
    }

    public function save(User $actor, ?InventoryCorrection $target, array $input): InventoryCorrection
    {
        return app(SecurityMutation::class)->run($actor, function (User $u) use ($target, $input) {
            OperationValidation::make($input, ['kind' => 'required|in:adjustment,compensation', 'reason' => 'required|string|max:1000', 'document_date' => 'required|date_format:Y-m-d'])->validate();
            $this->permission($u, $input['kind'], $target ? 'update' : 'create');
            $doc = $target ? $this->locked($target->id) : null;
            if ($doc) {
                abort_unless(! in_array($doc->status, ['executed', 'cancelled']) && $doc->kind === $input['kind'] && (string) ($input['lock_version'] ?? '') === (string) $doc->lock_version, 409, 'La corrección cambió o está cerrada.');
            }
            $lines = [];
            $seen = [];
            if ($input['kind'] === 'adjustment') {
                OperationValidation::make($input, ['lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|integer|min:1', 'lines.*.location_id' => 'required|integer|min:1', 'lines.*.counted_quantity' => 'required', 'lines.*.unit_ids' => 'present|array|max:1000', 'lines.*.unit_ids.*' => 'required|integer|min:1|distinct', 'expected_line_count' => 'required|integer|min:1|max:100'])->validate();
                if ((int) $input['expected_line_count'] !== count($input['lines'])) {
                    $this->fail('El formulario está incompleto.');
                }$pairs = [];
                foreach ($input['lines'] as $i => $raw) {
                    $pair = $raw['product_id'].':'.$raw['location_id'];
                    if (isset($pairs[$pair])) {
                        $this->fail('No repitas producto y ubicación.');
                    }$pairs[$pair] = true;
                    $p = Product::whereKey($raw['product_id'])->lockForUpdate()->firstOrFail();
                    $measure = MeasurementUnit::findOrFail($p->measurement_unit_id);
                    $count = TechnicalDecimal::normalize($raw['counted_quantity'], 'lines.'.$i.'.counted_quantity', $measure->decimal_places);
                    if ($count === null || TechnicalDecimal::compare($count, '0') < 0) {
                        $this->fail('El conteo no puede ser negativo.');
                    }$stock = $this->stock($p->id, (int) $raw['location_id']);
                    $difference = DB::selectOne('SELECT CAST(? AS DECIMAL(18,6))-CAST(? AS DECIMAL(18,6)) AS n', [$count, $stock->quantity])->n;
                    if (TechnicalDecimal::compare($difference, '0') === 0) {
                        $this->fail('El conteo coincide con el saldo: no corresponde un movimiento de ajuste.');
                    }
                    $line = app(InventoryDraftValidator::class)->line(array_replace($raw, ['quantity' => ltrim($difference, '-')]), 'lines.'.$i, $seen);
                    $lines[] = $line + ['direction' => str_starts_with($difference, '-') ? 'out' : 'in', 'observed_quantity' => $stock->quantity, 'observed_version' => $stock->lock_version, 'counted_quantity' => $count];
                }
            } else {
                OperationValidation::make($input, ['source_kind' => 'required|in:inventory-documents,deliveries,loans,returns,corrections', 'source_id' => 'required|integer|min:1'])->validate();
                $sourcePermission = $input['source_kind'] === 'inventory-documents' ? InventoryDocument::findOrFail($input['source_id'])->permission('view') : ['deliveries' => 'deliveries.view', 'loans' => 'loans.view', 'returns' => 'returns.view', 'corrections' => 'adjustments.view'][$input['source_kind']];
                app(SecurityMutation::class)->requirePermissions($u, $sourcePermission);
                $movements = $this->sourceMovements($input['source_kind'], (int) $input['source_id']);
                if ($movements->isEmpty()) {
                    $this->fail('El documento no tiene movimientos compensables.');
                }
                foreach ($movements as $m) {
                    $origin = $m->return_item_id !== null ? ToolLoanItem::findOrFail($m->origin->loan_item_id) : $m->origin;
                    $stock = $this->stock($m->product_id, $m->location_id);
                    $lines[] = ['product_id' => $m->product_id, 'location_id' => $m->location_id, 'quantity' => $m->quantity, 'direction' => $m->direction === 'in' ? 'out' : 'in', 'measurement_unit_id' => $origin->measurement_unit_id, 'unit_symbol' => $origin->unit_symbol, 'decimal_places' => $origin->decimal_places ?? 0, 'control_mode' => $origin->control_mode ?? 'individual', 'observed_quantity' => $stock->quantity, 'observed_version' => $stock->lock_version, 'counted_quantity' => null, 'original_movement_id' => $m->id, 'original_direction' => $m->direction, 'unit_ids' => $this->movementUnits($m)];
                }
            }
            $values = ['kind' => $input['kind'], 'reason' => trim($input['reason']), 'document_date' => $input['document_date'], 'source_kind' => $input['kind'] === 'compensation' ? $input['source_kind'] : null, 'source_id' => $input['kind'] === 'compensation' ? (int) $input['source_id'] : null, 'status' => 'draft', 'updated_by' => $u->id, 'updated_at' => now()];
            if ($doc) {
                $this->invalidate($u, $doc->id);
                DB::table('inventory_corrections')->where('id', $doc->id)->update($values + ['lock_version' => $doc->lock_version + 1]);
                $ids = $doc->lines()->pluck('id');
                DB::table('inventory_correction_line_units')->whereIn('correction_line_id', $ids)->delete();
                DB::table('inventory_correction_lines')->whereIn('id', $ids)->delete();
                $id = $doc->id;
            } else {
                $id = DB::table('inventory_corrections')->insertGetId($values + ['uuid' => (string) Str::uuid(), 'created_by' => $u->id, 'created_at' => now()]);
            }
            foreach ($lines as $line) {
                $units = $line['unit_ids'];
                unset($line['unit_ids']);
                $lineId = DB::table('inventory_correction_lines')->insertGetId($line + ['correction_id' => $id, 'kind' => $input['kind']]);
                foreach ($units as $unit) {
                    DB::table('inventory_correction_line_units')->insert(['correction_line_id' => $lineId, 'individual_unit_id' => $unit, 'product_id' => $line['product_id']]);
                }
            }
            $doc = InventoryCorrection::findOrFail($id);
            $this->fingerprint($doc);
            app(CriticalAudit::class)->record($u, 'correction.saved', 'correction_id', $id, ['version' => $doc->lock_version]);

            return $doc;
        }, protectAdministrativeAccess: false);
    }

    private function fingerprint(InventoryCorrection $doc): string
    {
        $state = ['document' => $doc->only(['id', 'kind', 'lock_version', 'reason', 'document_date', 'source_kind', 'source_id']), 'lines' => []];
        if ($doc->kind === 'compensation') {
            $expected = $this->sourceMovements($doc->source_kind, $doc->source_id)->pluck('id')->all();
            $actual = $doc->lines()->orderBy('original_movement_id')->pluck('original_movement_id')->all();
            sort($expected);
            sort($actual);
            if ($actual !== $expected) {
                $this->fail('La compensación debe incluir todos los movimientos del documento.');
            }
        }
        foreach ($doc->lines()->lockForUpdate()->get() as $line) {
            $p = Product::whereKey($line->product_id)->lockForUpdate()->firstOrFail();
            $loc = Location::whereKey($line->location_id)->lockForUpdate()->firstOrFail();
            $unit = MeasurementUnit::whereKey($line->measurement_unit_id)->lockForUpdate()->firstOrFail();
            $pair = DB::table('product_locations')->where('product_id', $p->id)->where('location_id', $loc->id)->lockForUpdate()->first();
            if (! $p->is_active || ! $loc->is_active || ! $loc->is_storable || ! $unit->is_active || ! $pair?->is_active || $p->measurement_unit_id !== $unit->id || $p->control_mode !== $line->control_mode || $unit->symbol !== $line->unit_symbol || $unit->decimal_places !== $line->decimal_places) {
                $this->fail('Regulariza expresamente el catálogo antes de corregir; las definiciones históricas no se reinterpretan.');
            }
            app(LocationHierarchy::class)->validateParent($loc, true);
            $stock = $this->stock($p->id, $loc->id);
            if ($doc->kind === 'adjustment' && ((int) $stock->lock_version !== (int) $line->observed_version || TechnicalDecimal::compare($stock->quantity, $line->observed_quantity) !== 0)) {
                $this->fail('El saldo cambió después del conteo. Edita y revisa nuevamente.');
            }
            $units = $line->units()->orderBy('individual_units.id')->lockForUpdate()->get();
            if (($line->control_mode === 'individual' && TechnicalDecimal::compare($line->quantity, (string) $units->count()) !== 0) || ($line->control_mode === 'quantity' && $units->isNotEmpty())) {
                $this->fail('Las identidades no corresponden a la cantidad.');
            }
            $identityState = [];
            foreach ($units as $identity) {
                if (! $identity->is_active || $identity->product_id !== $p->id) {
                    $this->fail('La identidad debe estar activa y pertenecer al producto.');
                }$position = DB::table('individual_unit_positions')->where('individual_unit_id', $identity->id)->lockForUpdate()->first();
                $custody = DB::table('individual_unit_custodies')->where('individual_unit_id', $identity->id)->lockForUpdate()->first();
                $original = $line->original_movement_id ? InventoryMovement::findOrFail($line->original_movement_id) : null;
                if ($original?->loan_item_id !== null) {
                    $cycle = app(LoanCycles::class)->current($identity->id, $original->loan_item_id);
                    if ($cycle->opening_loan_item_id === null) {
                        $this->fail('El préstamo tiene ciclos posteriores: no se compensan dependencias automáticamente.');
                    }
                } elseif ($custody?->current_loan_item_id !== null) {
                    $this->fail('La herramienta tiene un préstamo vigente. No puede ajustarse ni compensarse esta operación.');
                }
                if (($line->direction === 'in' && $position?->is_present) || ($line->direction === 'out' && (! $position?->is_present || (int) $position->location_id !== $loc->id))) {
                    $this->fail('La presencia física no permite esta corrección.');
                }
                if ($original) {
                    $source = $original->document_line_id !== null ? 'last_document_line_id' : ($original->delivery_line_id !== null ? 'last_delivery_line_id' : ($original->loan_item_id !== null ? 'last_loan_item_id' : ($original->return_item_id !== null ? 'last_return_item_id' : 'last_correction_line_id')));
                    $originId = $original->document_line_id ?? $original->delivery_line_id ?? $original->loan_item_id ?? $original->return_item_id ?? $original->correction_line_id;
                    if ((int) ($position?->{$source} ?? 0) !== (int) $originId) {
                        $this->fail('La identidad tiene operaciones posteriores; revisa primero sus dependencias.');
                    }
                }
                $identityState[] = ['identity' => $identity->getAttributes(), 'position' => $position, 'custody' => $custody];
            }
            $state['lines'][] = ['line' => $line->getAttributes(), 'product' => $p->getAttributes(), 'location' => $loc->getAttributes(), 'unit' => $unit->getAttributes(), 'pair' => $pair, 'stock' => $stock, 'identities' => $identityState];
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private function invalidate(User $u, int $id): void
    {
        foreach (DB::table('correction_authorizations')->where('correction_id', $id)->whereIn('status', ['requested', 'approved'])->lockForUpdate()->get() as $a) {
            DB::table('correction_authorizations')->where('id', $a->id)->update(['status' => 'revoked']);
            $this->event($u, $a->id, 'revoked', ['reason' => 'Contenido o estado cambiado.']);
        }
    }

    public function transition(User $actor, InventoryCorrection $target, string $action, mixed $version, ?string $reason = null): InventoryCorrection
    {
        return app(SecurityMutation::class)->run($actor, function (User $u) use ($target, $action, $version, $reason) {
            $doc = $this->locked($target->id);
            abort_unless((string) $version === (string) $doc->lock_version, 409, 'La corrección cambió.');
            OperationValidation::make(['action' => $action, 'reason' => $reason], ['action' => 'required|in:request,approve,reject,revoke,cancel', 'reason' => 'nullable|string|max:1000'])->validate();
            if ($action === 'request') {
                $this->permission($u, $doc->kind, 'request');
                abort_unless(in_array($doc->status, ['draft', 'rejected']), 409);
                $hash = $this->fingerprint($doc);
                $a = DB::table('correction_authorizations')->insertGetId(['correction_id' => $doc->id, 'status' => 'requested', 'fingerprint' => $hash, 'document_version' => $doc->lock_version, 'requested_by' => $u->id, 'requested_at' => now()]);
                $this->event($u, $a, 'requested', ['fingerprint' => $hash, 'document' => $doc->toArray(), 'lines' => $doc->lines()->with('units')->get()->toArray()]);
                $status = 'requested';
            } elseif ($action === 'cancel') {
                $this->permission($u, $doc->kind, 'cancel');
                abort_unless(! in_array($doc->status, ['executed', 'cancelled']), 409);
                $this->invalidate($u, $doc->id);
                $status = 'cancelled';
            } else {
                app(SecurityMutation::class)->requirePermissions($u, $action === 'revoke' ? 'corrections.revoke' : 'corrections.authorize');
                OperationValidation::make(['reason' => $reason], ['reason' => 'required|string|max:1000'])->validate();
                $a = DB::table('correction_authorizations')->where('correction_id', $doc->id)->orderByDesc('id')->lockForUpdate()->first();
                abort_unless($a && in_array($a->status, ['requested', 'approved']), 409, 'No hay autorización pendiente.');
                if ($action === 'approve') {
                    abort_unless($a->status === 'requested', 409);
                    if ($a->requested_by === $u->id) {
                        $this->fail('El solicitante no puede autorizar su propia corrección.');
                    }if (! hash_equals($a->fingerprint, $this->fingerprint($doc))) {
                        $this->fail('El contenido o las referencias cambiaron; solicita nuevamente.');
                    }
                    DB::table('correction_authorizations')->where('id', $a->id)->update(['status' => 'approved', 'approved_by' => $u->id, 'reason' => trim($reason), 'approved_at' => now(), 'expires_at' => now()->addHours(24)]);
                    $status = 'authorized';
                    $event = 'approved';
                } else {
                    $event = $action === 'revoke' ? 'revoked' : 'rejected';
                    DB::table('correction_authorizations')->where('id', $a->id)->update(['status' => $event, 'reason' => trim($reason)]);
                    $status = 'rejected';
                }
                $this->event($u, $a->id, $event, ['reason' => $reason, 'access_proof' => app(ApprovalAccess::class)->proof($u)]);
            }
            DB::table('inventory_corrections')->where('id', $doc->id)->update(['status' => $status, 'updated_by' => $u->id, 'updated_at' => now()]);
            app(CriticalAudit::class)->record($u, 'correction.'.$action, 'correction_id', $doc->id, ['reason' => $reason]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }

    public function execute(User $actor, InventoryCorrection $target, mixed $version): InventoryCorrection
    {
        return app(SecurityMutation::class)->run($actor, function (User $u) use ($target, $version) {
            $doc = $this->locked($target->id);
            $this->permission($u, $doc->kind, 'execute');
            if ($doc->status === 'executed') {
                return $doc;
            }
            abort_unless($doc->status === 'authorized' && (string) $version === (string) $doc->lock_version, 409, 'La corrección no está autorizada o cambió.');
            $auth = DB::table('correction_authorizations')->where('correction_id', $doc->id)->where('status', 'approved')->orderByDesc('id')->lockForUpdate()->first();
            if (! $auth || $auth->expires_at <= now()->toDateTimeString() || ! hash_equals($auth->fingerprint, $this->fingerprint($doc))) {
                $this->fail('La autorización caducó o cambió el contenido, catálogo o saldo.');
            }
            $proofEvent = DB::table('correction_authorization_events')->where('authorization_id', $auth->id)->where('action', 'approved')->orderByDesc('id')->lockForUpdate()->first();
            $proof = $proofEvent ? json_decode($proofEvent->payload, true, flags: JSON_THROW_ON_ERROR) : [];
            if (! app(ApprovalAccess::class)->valid($auth->approved_by, 'corrections.authorize', $proof['access_proof'] ?? [])) {
                $this->fail('La capacidad del autorizador cambió. Solicita nueva autorización.');
            }
            foreach ($doc->lines()->lockForUpdate()->get() as $line) {
                $this->post($u, $line);
            }
            if ($doc->kind === 'compensation') {
                DB::table('document_compensations')->insert(['correction_id' => $doc->id, self::SOURCES[$doc->source_kind][2] => $doc->source_id, 'created_at' => now()]);
            }
            DB::table('correction_authorizations')->where('id', $auth->id)->update(['status' => 'used', 'used_at' => now()]);
            $this->event($u, $auth->id, 'used');
            DB::table('inventory_corrections')->where('id', $doc->id)->update(['status' => 'executed', 'executed_by' => $u->id, 'executed_at' => now(), 'updated_by' => $u->id, 'updated_at' => now()]);
            app(CriticalAudit::class)->record($u, 'correction.executed', 'correction_id', $doc->id, ['authorization_id' => $auth->id]);

            return $doc->fresh();
        }, protectAdministrativeAccess: false);
    }

    private function post(User $u, object $line): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Se requiere una transacción.');
        }
        $stock = $this->stock($line->product_id, $line->location_id);
        if ((int) $stock->lock_version === 0) {
            DB::table('stock_positions')->insert(['product_id' => $line->product_id, 'location_id' => $line->location_id, 'quantity' => '0', 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $incoming = $line->direction === 'in';
        $op = $incoming ? '+' : '-';
        $condition = $incoming ? 'quantity <= CAST(999999999999.999999 AS DECIMAL(18,6))-CAST(? AS DECIMAL(18,6))' : 'quantity >= CAST(? AS DECIMAL(18,6))';
        if (DB::update("UPDATE stock_positions SET quantity=quantity {$op} CAST(? AS DECIMAL(18,6)),lock_version=lock_version+1,updated_at=? WHERE product_id=? AND location_id=? AND {$condition}", [$line->quantity, now(), $line->product_id, $line->location_id, $line->quantity]) !== 1) {
            $this->fail('La corrección excede el saldo disponible o la capacidad decimal.');
        }
        DB::table('inventory_movements')->insert(['correction_line_id' => $line->id, 'correction_kind' => $line->kind, 'compensated_movement_id' => $line->original_movement_id, 'product_id' => $line->product_id, 'location_id' => $line->location_id, 'direction' => $line->direction, 'quantity' => $line->quantity, 'created_by' => $u->id, 'posted_at' => now()]);
        foreach ($line->units()->orderBy('individual_units.id')->get() as $unit) {
            $position = DB::table('individual_unit_positions')->where('individual_unit_id', $unit->id)->lockForUpdate()->first();
            DB::table('individual_unit_positions')->updateOrInsert(['individual_unit_id' => $unit->id], ['product_id' => $line->product_id, 'is_present' => $incoming, 'location_id' => $incoming ? $line->location_id : null, 'last_document_line_id' => null, 'last_delivery_line_id' => null, 'last_loan_item_id' => null, 'last_return_item_id' => null, 'last_correction_line_id' => $line->id, 'last_correction_direction' => $line->direction, 'lock_version' => ($position?->lock_version ?? 0) + 1, 'created_at' => $position?->created_at ?? now(), 'updated_at' => now()]);
            $original = $line->original_movement_id ? InventoryMovement::findOrFail($line->original_movement_id) : null;
            if ($original?->loan_item_id !== null) {
                $cycle = app(LoanCycles::class)->current($unit->id, $original->loan_item_id);
                app(LoanCycles::class)->close($cycle, null, $line->id);
                DB::table('individual_unit_custodies')->where('individual_unit_id', $unit->id)->update(['current_cycle_id' => null, 'current_loan_item_id' => null, 'lock_version' => DB::raw('lock_version+1'), 'updated_at' => now()]);
            } elseif ($original?->return_item_id !== null) {
                $closed = DB::table('loan_cycle_closures')->where('return_item_id', $original->return_item_id)->lockForUpdate()->first();
                if (! $closed) {
                    $this->fail('No existe el cierre físico original.');
                }$previous = DB::table('loan_custody_cycles')->find($closed->cycle_id);
                $item = ToolLoanItem::findOrFail($previous->loan_item_id);
                $cycle = app(LoanCycles::class)->open($item, $line->id, $previous->id);
                $changed = DB::table('individual_unit_custodies')->where('individual_unit_id', $unit->id)->whereNull('current_loan_item_id')->update(['current_cycle_id' => $cycle, 'current_loan_item_id' => $item->id, 'lock_version' => DB::raw('lock_version+1'), 'updated_at' => now()]);
                if ($changed !== 1) {
                    $this->fail('La custodia cambió.');
                }
            }
        }
        Event::dispatch('inventory_correction.line_posted', [$line]);
    }
}
