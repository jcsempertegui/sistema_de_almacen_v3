<?php

namespace Tests\Feature\Catalog;

use App\Actions\Roles\SaveRole;
use App\Models\AdministrativeAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogPermissionInitializationTest extends TestCase
{
    public function test_initial_role_capabilities_include_only_the_approved_catalog_permissions(): void
    {
        $codes = ['categories.view', 'categories.create', 'categories.update', 'categories.change_status', 'measurement_units.view', 'measurement_units.create', 'measurement_units.update', 'measurement_units.change_status'];
        foreach ([1, 2] as $id) {
            $this->assertSame([], array_diff($codes, Role::findOrFail($id)->assignedPermissionCodes()));
        }
        $auditor = Role::findOrFail(3)->assignedPermissionCodes();
        $this->assertContains('categories.view', $auditor);
        $this->assertContains('measurement_units.view', $auditor);
        foreach (array_diff($codes, ['categories.view', 'measurement_units.view']) as $code) {
            $this->assertNotContains($code, $auditor);
        }
        $this->assertDatabaseCount('permissions', 44);
    }

    public function test_repeat_initialization_preserves_revocations_unknown_roles_users_and_audit(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $manager = Role::findOrFail(2);
        $codes = array_diff($manager->assignedPermissionCodes(), ['categories.create', 'measurement_units.update']);
        app(SaveRole::class)->handle($admin, $manager, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $unknown = Role::create(['code' => 'desconocido', 'name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $unknown->id]);
        $userBefore = $user->fresh()->getAttributes();
        $version = $manager->fresh()->permissions_version;
        $logs = AdministrativeAuditLog::count();
        $links = DB::table('permission_role')->count();
        $migration = require database_path('migrations/2026_10_13_000003_add_category_and_measurement_unit_permissions.php');
        $migration->up();
        $migration->up();
        $this->seed(DatabaseSeeder::class);
        $this->assertNotContains('categories.create', $manager->fresh()->assignedPermissionCodes());
        $this->assertNotContains('measurement_units.update', $manager->fresh()->assignedPermissionCodes());
        $this->assertSame($version, $manager->fresh()->permissions_version);
        $this->assertSame($logs, AdministrativeAuditLog::count());
        $this->assertSame($links, DB::table('permission_role')->count());
        $this->assertSame([], $unknown->assignedPermissionCodes());
        $this->assertSame($userBefore, $user->fresh()->getAttributes());
    }

    public function test_initialization_of_missing_links_versions_and_audits_only_changed_roles(): void
    {
        // Simulate the pre-rollout state in this rollback-only test transaction.
        $ids = Permission::where('code', 'like', 'categories.%')->orWhere('code', 'like', 'measurement_units.%')->pluck('id')->all();
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        $versions = Role::orderBy('id')->pluck('permissions_version', 'id')->all();
        $auditCount = AdministrativeAuditLog::count();
        $migration = require database_path('migrations/2026_10_13_000003_add_category_and_measurement_unit_permissions.php');
        $migration->up();
        foreach (Role::orderBy('id')->get() as $role) {
            $this->assertSame($versions[$role->id] + 1, $role->permissions_version);
        }
        $this->assertSame($auditCount + 3, AdministrativeAuditLog::count());
        $this->assertDatabaseCount('permission_role', 86);
        $migration->up();
        $this->assertSame($auditCount + 3, AdministrativeAuditLog::count());
    }

    public function test_new_grants_invalidate_existing_sessions_without_touching_other_roles(): void
    {
        $manager = User::factory()->create(['role_id' => 2]);
        $this->signIn($manager);
        $versions = Role::orderBy('id')->pluck('permissions_version', 'id')->all();
        $ids = Permission::where('code', 'like', 'categories.%')->orWhere('code', 'like', 'measurement_units.%')->pluck('id')->all();
        DB::table('permission_role')->where('role_id', 2)->whereIn('permission_id', $ids)->delete();
        $logs = AdministrativeAuditLog::count();
        $migration = require database_path('migrations/2026_10_13_000003_add_category_and_measurement_unit_permissions.php');
        $migration->up();
        $this->assertSame($versions[2] + 1, Role::findOrFail(2)->permissions_version);
        foreach ([1, 3] as $id) {
            $this->assertSame($versions[$id], Role::findOrFail($id)->permissions_version);
        }
        $this->assertSame($logs + 1, AdministrativeAuditLog::count());
        $this->getJson(route('categories.index'))->assertUnauthorized();
        $this->signIn($manager->fresh())->get(route('categories.create'))->assertOk();
    }
}
