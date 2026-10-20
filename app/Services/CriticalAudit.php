<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CriticalAudit
{
    public function record(User $actor, string $action, string $target, int $id, array $payload = []): void
    {
        if (! in_array($target, ['correction_id', 'backup_run_id', 'rehearsal_id'], true)) {
            throw new \LogicException('Referencia de auditoría inválida.');
        }
        DB::table('critical_operation_events')->insert(['actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, $target => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
