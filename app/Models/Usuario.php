<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['nombre', 'correo', 'contrasena', 'rol_id', 'activo'])]
#[Hidden(['contrasena', 'token_recuerdo'])]
class Usuario extends Authenticatable
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'usuarios';

    protected $rememberTokenName = 'token_recuerdo';

    public function getAuthPasswordName(): string
    {
        return 'contrasena';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, Notifiable;

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function trabajadoresCreados(): HasMany
    {
        return $this->hasMany(Trabajador::class, 'creado_por');
    }

    public function trabajadoresModificados(): HasMany
    {
        return $this->hasMany(Trabajador::class, 'modificado_por');
    }

    public function codigosPermisos(): array
    {
        return $this->activo && $this->rol?->activo ? $this->rol->codigosPermisosAsignados() : [];
    }

    public function tienePermiso(string $codigo): bool
    {
        return in_array($codigo, $this->codigosPermisos(), true);
    }

    public function tieneTodosPermisos(array $codes): bool
    {
        return array_diff($codes, $this->codigosPermisos()) === [];
    }

    /**
     * Get the atributos that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_verificacion_correo' => 'datetime',
            'contrasena' => 'hashed',
            'activo' => 'boolean',
            'version_sesion' => 'integer',
            'rol_id' => 'integer',
        ];
    }
}
