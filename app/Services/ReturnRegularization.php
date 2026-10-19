<?php

namespace App\Services;

use App\Models\ReturnRegularizationAuthorization;
use App\Models\ToolReturn;
use App\Models\ToolReturnItem;
use App\Models\User;
use App\Support\OperationValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnRegularization
{
    public function request(User $actor, ToolReturnItem $target, mixed $version, string $reason): ReturnRegularizationAuthorization
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $version, $reason) {
            app(SecurityMutation::class)->requirePermissions($operator, 'returns.request_regularization');
            $this->reason($reason);
            $doc = ToolReturn::whereKey($target->return_id)->lockForUpdate()->firstOrFail();
            abort_unless($doc->status === 'draft' && (string) $version === (string) $doc->lock_version, 409, 'El borrador cambió o está cerrado.');
            $item = ToolReturnItem::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $state = app(ReturnEligibility::class)->inspect($item->toArray(), $doc);
            if (! $state['exceptions']) {
                throw ValidationException::withMessages(['reason' => 'Las referencias están activas; no necesitan una excepción.']);
            }
            $this->invalidateItems($operator, [$item->id], 'Solicitud reemplazada.');
            $id = DB::table('return_regularization_authorizations')->insertGetId(['return_item_id' => $item->id, 'reason' => trim($reason), 'fingerprint' => $state['fingerprint'], 'reference_snapshot' => json_encode($state, JSON_THROW_ON_ERROR), 'requested_by' => $operator->id, 'requested_at' => now()]);
            $this->event($operator, $id, 'requested', ['reason' => trim($reason), 'state' => $state]);

            return ReturnRegularizationAuthorization::findOrFail($id);
        }, protectAdministrativeAccess: false);
    }

    public function decide(User $actor, ReturnRegularizationAuthorization $target, bool $approve, string $reason): ReturnRegularizationAuthorization
    {
        return app(SecurityMutation::class)->run($actor, function (User $operator) use ($target, $approve, $reason) {
            app(SecurityMutation::class)->requirePermissions($operator, $approve ? 'returns.authorize_regularization' : 'returns.revoke_regularization');
            $this->reason($reason);
            $item = ToolReturnItem::findOrFail($target->return_item_id);
            $doc = ToolReturn::whereKey($item->return_id)->lockForUpdate()->firstOrFail();
            $authorization = ReturnRegularizationAuthorization::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($doc->status === 'draft' && in_array($authorization->status, $approve ? ['requested'] : ['requested', 'approved'], true), 409, 'La autorización ya no admite esta operación.');
            $update = ['status' => $approve ? 'approved' : 'revoked'];
            if ($approve) {
                $state = app(ReturnEligibility::class)->inspect($item->toArray(), $doc);
                if (! $state['exceptions'] || ! hash_equals($authorization->fingerprint, $state['fingerprint'])) {
                    throw ValidationException::withMessages(['reason' => 'Las referencias cambiaron. Solicita una nueva autorización.']);
                }$update += ['approved_by' => $operator->id, 'approved_at' => now(), 'expires_at' => now()->addDay()];
            }
            DB::table('return_regularization_authorizations')->where('id', $authorization->id)->update($update);
            $this->event($operator, $authorization->id, $update['status'], ['reason' => trim($reason), 'expires_at' => $update['expires_at'] ?? null]);

            return $authorization->fresh();
        }, protectAdministrativeAccess: false);
    }

    public function approvedFor(array $item, ToolReturn $doc): ?int
    {
        $state = app(ReturnEligibility::class)->inspect($item, $doc);
        if (! $state['exceptions']) {
            return null;
        }
        $authorization = ReturnRegularizationAuthorization::where('return_item_id', $item['id'])->where('status', 'approved')->where('fingerprint', $state['fingerprint'])->where('expires_at', '>', now())->lockForUpdate()->first();
        if (! $authorization) {
            throw ValidationException::withMessages(['lines' => 'La recepción de referencias inactivas requiere autorización específica vigente.']);
        }

        return $authorization->id;
    }

    public function consume(User $actor, int $id): void
    {
        if (DB::transactionLevel() === 0 || ! DB::table('loan_item_settlements')->where('regularization_authorization_id', $id)->exists()) {
            throw new \LogicException('La autorización se utiliza junto con su cierre y movimiento físico.');
        }
        if (DB::table('return_regularization_authorizations')->where('id', $id)->where('status', 'approved')->where('expires_at', '>', now())->update(['status' => 'used', 'used_at' => now()]) !== 1) {
            throw ValidationException::withMessages(['lines' => 'La autorización ya no está vigente.']);
        }
        $this->event($actor, $id, 'used', ['reason' => 'Recepción física confirmada y cierre registrado.']);
    }

    public function invalidateItems(User $actor, array $ids, string $reason): void
    {
        foreach (DB::table('return_regularization_authorizations')->whereIn('return_item_id', $ids)->whereIn('status', ['requested', 'approved'])->lockForUpdate()->get() as $row) {
            DB::table('return_regularization_authorizations')->where('id', $row->id)->update(['status' => 'revoked']);
            $this->event($actor, $row->id, 'revoked', ['reason' => $reason]);
        }
    }

    private function reason(string $reason): void
    {
        OperationValidation::make(['reason' => trim($reason)], ['reason' => 'required|string|max:1000'], ['required' => 'El motivo es obligatorio.'])->validate();
    }

    private function event(User $actor, int $id, string $action, array $payload): void
    {
        DB::table('return_regularization_events')->insert(['authorization_id' => $id, 'actor_id' => $actor->id, 'action' => $action, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
