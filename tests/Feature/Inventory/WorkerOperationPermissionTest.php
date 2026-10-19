<?php

namespace Tests\Feature\Inventory;

use App\Actions\Roles\SaveRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\WorkerOperationPermissions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkerOperationPermissionTest extends TestCase
{
    public function test_defaults_and_repeat_migration_preserve_revocations_unknown_roles_and_people(): void
    {
        $this->assertDatabaseCount('permissions', 81);
        $this->assertDatabaseCount('permission_role', 166);
        foreach ([1 => 81, 2 => 66, 3 => 19] as $id => $count) {
            $this->assertCount($count, Role::findOrFail($id)->assignedPermissionCodes());
        }
        foreach (array_keys(WorkerOperationPermissions::LABELS) as $code) {
            $this->assertSame(str_ends_with($code, '.view'), in_array($code, Role::findOrFail(3)->assignedPermissionCodes(), true));
        }
        $admin = User::factory()->create(['role_id' => 1]);
        $manager = Role::findOrFail(2);
        $codes = array_diff($manager->assignedPermissionCodes(), ['loans.confirm']);
        app(SaveRole::class)->handle($admin, $manager, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $unknown = Role::create(['code' => 'unknown_s4', 'name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $unknown->id]);
        $before = $user->fresh()->getAttributes();
        $versions = Role::pluck('permissions_version', 'id')->all();
        $links = DB::table('permission_role')->orderBy('id')->get()->toJson();
        $logs = DB::table('administrative_audit_logs')->count();
        $migration = require database_path('migrations/2026_10_19_000008_add_worker_operation_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertSame($links, DB::table('permission_role')->orderBy('id')->get()->toJson());
        $this->assertSame($versions, Role::pluck('permissions_version', 'id')->all());
        $this->assertSame($logs, DB::table('administrative_audit_logs')->count());
        $this->assertSame([], $unknown->assignedPermissionCodes());
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_revocation_invalidates_worker_operation_session(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $manager = User::factory()->create(['role_id' => 2]);
        $this->signIn($manager)->get(route('worker-operations.create', 'loans'))->assertOk();
        $codes = array_diff($manager->role->assignedPermissionCodes(), ['loans.confirm']);
        app(SaveRole::class)->handle($admin, $manager->role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $this->postJson(route('worker-operations.confirm', ['loans', 1]), ['lock_version' => 1])->assertUnauthorized();
        $this->assertDatabaseCount('inventory_movements', 0);
    }
}
