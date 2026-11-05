<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrestamoHerramienta extends DocumentoOperacionTrabajador
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'prestamos_herramientas';

    protected $prefix = 'PR';

    public function lineas(): HasMany
    {
        return $this->hasMany(ItemPrestamoHerramienta::class, 'prestamo_id')->orderBy('id');
    }

    public function getEstadoResponsabilidadAttribute(): string
    {
        if ($this->estado !== 'confirmed') {
            return $this->estado;
        }
        $total = $this->lineas()->count();
        if (Schema::hasTable('compensaciones_documento') && DB::table('compensaciones_documento')->where('prestamo_id', $this->id)->exists()) {
            return 'compensated';
        }
        $closed = $total - $this->lineas()->pending()->count();

        return $closed === $total ? 'returned' : ($closed > 0 ? 'partial' : 'pending');
    }
}
