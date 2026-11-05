<?php

namespace App\Actions\Trabajadores;

use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuardarTrabajador
{
    public function __construct(private ModificacionSegura $security) {}

    public function handle(Usuario $actor, ?Trabajador $target, array $data, bool $statusOnly = false): Trabajador
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($target, $data, $statusOnly) {
                $trabajador = $target ? Trabajador::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Trabajador;
                $permiso = ! $target ? 'workers.create' : ($statusOnly ? 'workers.change_status' : 'workers.update');
                $this->security->exigirPermisos($operator, $permiso);
                $trabajador->fill(array_intersect_key($data, array_flip($statusOnly ? ['activo'] : ['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area', 'activo'])));
                if ($target && $trabajador->isDirty('activo')) {
                    $this->security->exigirPermisos($operator, 'workers.change_status');
                }
                if (! $target) {
                    $trabajador->creado_por = $operator->id;
                }
                $trabajador->modificado_por = $operator->id;
                $trabajador->save();

                return $trabajador;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            $duplicateCode = Trabajador::where('codigo', $data['codigo'] ?? $target?->codigo)->when($target, fn ($query) => $query->whereKeyNot($target->id))->exists();
            throw ValidationException::withMessages([$duplicateCode ? 'codigo' : 'documento_identidad' => 'El código o documento de identidad ya está registrado.']);
        }
    }
}
