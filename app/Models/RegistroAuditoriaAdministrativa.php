<?php

namespace App\Models;

use App\Casts\DatosHistoricosEnEspanol;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'nombre_actor', 'rol_id', 'usuario_id', 'accion', 'origen', 'direccion_ip', 'datos_anteriores', 'datos_posteriores', 'fecha_creacion'])]
class RegistroAuditoriaAdministrativa extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'registros_auditoria_administrativa';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['datos_anteriores' => DatosHistoricosEnEspanol::class, 'datos_posteriores' => DatosHistoricosEnEspanol::class, 'fecha_creacion' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La auditoría no admite modificaciones.'));
        static::deleting(fn () => throw new \LogicException('La auditoría no admite eliminaciones.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actor_id');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
