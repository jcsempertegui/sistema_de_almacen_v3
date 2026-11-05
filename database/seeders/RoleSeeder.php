<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Rol::LABELS as $codigo => $nombre) {
            Rol::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre]);
        }
    }
}
