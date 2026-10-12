<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Role extends Model
{
    public const ADMINISTRATOR = 'administrador';

    public const WAREHOUSE_MANAGER = 'encargado';

    public const AUDITOR = 'consulta_auditor';

    public const LABELS = [
        self::ADMINISTRATOR => 'Administrador',
        self::WAREHOUSE_MANAGER => 'Encargado de almacén',
        self::AUDITOR => 'Consulta/Auditor',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
