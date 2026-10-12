<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RolesTest extends TestCase
{
    public function test_catalog_is_fixed_and_seeding_does_not_create_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_each_active_role_can_open_panel_but_only_admin_manages_users(): void
    {
        foreach (array_keys(Role::LABELS) as $code) {
            $user = User::factory()->create(['role_id' => Role::where('code', $code)->value('id')]);
            $this->signIn($user)->get('/panel')->assertOk();
            foreach (['viewAny', 'create'] as $ability) {
                $this->assertSame($code === Role::ADMINISTRATOR, Gate::forUser($user)->allows($ability, User::class));
            }
            $this->assertSame($code === Role::ADMINISTRATOR, Gate::forUser($user)->allows('update', $user));
        }
    }

    public function test_inactive_administrator_has_no_permissions(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('code', Role::ADMINISTRATOR)->value('id'), 'is_active' => false]);
        $this->assertFalse(Gate::forUser($user)->allows('access-panel'));
        $this->assertFalse(Gate::forUser($user)->allows('create', User::class));
    }

    public function test_non_catalog_role_is_denied_panel_access(): void
    {
        $role = Role::create(['code' => 'unexpected', 'name' => 'Sin permisos']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->signIn($user)->get('/panel')->assertForbidden();
    }

    public function test_database_rejects_unknown_role_ids(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['role_id' => 999999]);
    }
}
