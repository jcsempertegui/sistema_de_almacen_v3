<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RolesTest extends TestCase
{
    public function test_initial_catalog_and_seeding_do_not_create_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_each_active_role_can_open_panel_but_only_admin_manages_users(): void
    {
        foreach (array_keys(Rol::LABELS) as $codigo) {
            $usuario = Usuario::factory()->create(['rol_id' => Rol::where('codigo', $codigo)->value('id')]);
            $this->signIn($usuario)->get('/panel')->assertOk();
            foreach (['viewAny', 'create'] as $ability) {
                $this->assertSame($codigo === Rol::ADMINISTRATOR, Gate::forUser($usuario)->allows($ability, Usuario::class));
            }
            $this->assertSame($codigo === Rol::ADMINISTRATOR, Gate::forUser($usuario)->allows('update', $usuario));
        }
    }

    public function test_inactive_administrator_has_no_permissions(): void
    {
        $usuario = Usuario::factory()->create(['rol_id' => Rol::where('codigo', Rol::ADMINISTRATOR)->value('id'), 'activo' => false]);
        $this->assertFalse(Gate::forUser($usuario)->allows('access-panel'));
        $this->assertFalse(Gate::forUser($usuario)->allows('create', Usuario::class));
    }

    public function test_non_catalog_role_is_denied_panel_access(): void
    {
        $rol = Rol::create(['codigo' => 'unexpected', 'nombre' => 'Sin permisos']);
        $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
        $this->signIn($usuario)->get('/panel')->assertForbidden();
    }

    public function test_database_rejects_unknown_role_ids(): void
    {
        $this->expectException(QueryException::class);
        Usuario::factory()->create(['rol_id' => 999999]);
    }
}
