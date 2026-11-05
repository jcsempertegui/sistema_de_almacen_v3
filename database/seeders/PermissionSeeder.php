<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Support\CatalogoPermisos;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogoPermisos::LABELS as $codigo => $nombre) {
            Permiso::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'descripcion' => $nombre.'.']);
        }
    }
}
