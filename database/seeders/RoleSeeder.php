<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::LABELS as $code => $name) {
            Role::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
