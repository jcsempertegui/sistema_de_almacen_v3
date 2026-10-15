<?php

namespace Tests\Feature\Catalog;

use App\Actions\Roles\SaveRole;
use App\Models\AdministrativeAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationPermissionInitializationTest extends TestCase
{
    private function codes(): array
    {
        return ['locations.view', 'locations.create', 'locations.update', 'locations.change_status'];
    }

    public function test_initial_capabilities_are_only_those_approved_for_each_role(): void
    {
        foreach ([1, 2] as $id) {
            $this->assertSame([], array_diff($this->codes(), Role::findOrFail($id)->assignedPermissionCodes()));
        }
        $auditor = Role::findOrFail(3)->assignedPermissionCodes();
        $this->assertContains('locations.view', $auditor);
        foreach (array_diff($this->codes(), ['locations.view']) as $code) {
            $this->assertNotContains($code, $auditor);
        }
        $this->assertDatabaseCount('permissions', 37);
        $this->assertDatabaseCount('permission_role', 70);
    }

    public function test_repeat_initialization_preserves_revocations_unknown_roles_and_existing_people(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $manager = Role::findOrFail(2);
        $codes = array_diff($manager->assignedPermissionCodes(), ['locations.create', 'locations.update', 'attributes.create']);
        app(SaveRole::class)->handle($admin, $manager, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $unknown = Role::create(['code' => 'location_unknown', 'name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $unknown->id]);
        $beforeUser = $user->fresh()->getAttributes();
        $beforeLinks = DB::table('permission_role')->orderBy('id')->get()->toJson();
        $beforeRoles = Role::orderBy('id')->get()->toJson();
        $logs = AdministrativeAuditLog::count();
        $migration = require database_path('migrations/2026_10_14_000005_add_location_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertSame($beforeLinks, DB::table('permission_role')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeRoles, Role::orderBy('id')->get()->toJson());
        $this->assertSame($logs, AdministrativeAuditLog::count());
        $this->assertSame($beforeUser, $user->fresh()->getAttributes());
        $this->assertSame([], $unknown->assignedPermissionCodes());
    }

    public function test_only_missing_grants_change_versions_audit_and_sessions_without_modifying_prior_data(): void
    {
        $actor = User::factory()->create(['role_id' => 2]);
        $this->signIn($actor);
        $ids = Permission::whereIn('code', $this->codes())->pluck('id')->all();
        DB::table('permission_role')->where('role_id', 2)->whereIn('permission_id', $ids)->delete();
        $oldLinks = DB::table('permission_role')->whereNotIn('permission_id', $ids)->orderBy('id')->get()->toJson();
        $versions = Role::orderBy('id')->pluck('permissions_version', 'id')->all();
        $auditCount = AdministrativeAuditLog::count();
        $migration = require database_path('migrations/2026_10_14_000005_add_location_permissions.php');
        $migration->up();
        $this->assertSame($versions[2] + 1, Role::findOrFail(2)->permissions_version);
        foreach ([1, 3] as $id) {
            $this->assertSame($versions[$id], Role::findOrFail($id)->permissions_version);
        }
        $this->assertSame($oldLinks, DB::table('permission_role')->whereNotIn('permission_id', $ids)->orderBy('id')->get()->toJson());
        $this->assertSame($auditCount + 1, AdministrativeAuditLog::count());
        $this->assertSame('migration', AdministrativeAuditLog::latest('id')->firstOrFail()->origin);
        $this->getJson(route('locations.index'))->assertUnauthorized();
        $this->signIn($actor->fresh())->get(route('locations.create'))->assertOk();
        $migration->up();
        $this->assertSame($auditCount + 1, AdministrativeAuditLog::count());
    }
}
