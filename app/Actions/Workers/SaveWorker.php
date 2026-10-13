<?php

namespace App\Actions\Workers;

use App\Models\User;
use App\Models\Worker;
use App\Services\SecurityMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SaveWorker
{
    public function __construct(private SecurityMutation $security) {}

    public function handle(User $actor, ?Worker $target, array $data, bool $statusOnly = false): Worker
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($target, $data, $statusOnly) {
                $worker = $target ? Worker::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Worker;
                $permission = ! $target ? 'workers.create' : ($statusOnly ? 'workers.change_status' : 'workers.update');
                $this->security->requirePermissions($operator, $permission);
                $worker->fill(array_intersect_key($data, array_flip($statusOnly ? ['is_active'] : ['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area', 'is_active'])));
                if ($target && $worker->isDirty('is_active')) {
                    $this->security->requirePermissions($operator, 'workers.change_status');
                }
                if (! $target) {
                    $worker->created_by = $operator->id;
                }
                $worker->updated_by = $operator->id;
                $worker->save();

                return $worker;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            $duplicateCode = Worker::where('codigo', $data['codigo'] ?? $target?->codigo)->when($target, fn ($query) => $query->whereKeyNot($target->id))->exists();
            throw ValidationException::withMessages([$duplicateCode ? 'codigo' : 'documento_identidad' => 'El código o documento de identidad ya está registrado.']);
        }
    }
}
