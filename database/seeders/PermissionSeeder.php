<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::LABELS as $code => $name) {
            Permission::firstOrCreate(['code' => $code], ['name' => $name, 'description' => $name.'.']);
        }
    }
}
