<?php

namespace Tests\Feature\Catalog;

use App\Actions\Roles\SaveRole;
use App\Models\AdministrativeAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttributePermissionInitializationTest extends TestCase
{
    private function codes(): array
    {
        return ['attributes.view', 'attributes.create', 'attributes.update', 'attributes.change_status', 'category_attributes.view', 'category_attributes.create', 'category_attributes.update', 'category_attributes.change_status'];
    }

    public function test_initial_roles_receive_approved_capabilities_and_auditor_only_reads(): void
    {
        foreach ([1, 2] as $id) {
            $this->assertSame([], array_diff($this->codes(), Role::findOrFail($id)->assignedPermissionCodes()));
        }
        $auditor = Role::findOrFail(3)->assignedPermissionCodes();
        $this->assertContains('attributes.view', $auditor);
        $this->assertContains('category_attributes.view', $auditor);
        foreach (array_diff($this->codes(), ['attributes.view', 'category_attributes.view']) as $code) {
            $this->assertNotContains($code, $auditor);
        }
        $this->assertDatabaseCount('permissions', 81);
        $this->assertDatabaseCount('permission_role', 166);
    }

    public function test_reinitialization_preserves_revoked_old_and_new_permissions_unknown_roles_and_users(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $manager = Role::findOrFail(2);
        $codes = array_diff($manager->assignedPermissionCodes(), ['attributes.create', 'category_attributes.update', 'categories.create']);
        app(SaveRole::class)->handle($admin, $manager, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $unknown = Role::create(['code' => 'unknown_technical', 'name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $unknown->id]);
        $before = $user->fresh()->getAttributes();
        $links = DB::table('permission_role')->orderBy('id')->get()->toJson();
        $roles = Role::orderBy('id')->get()->toJson();
        $logs = AdministrativeAuditLog::count();
        $migration = require database_path('migrations/2026_10_14_000003_add_attribute_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertSame($links, DB::table('permission_role')->orderBy('id')->get()->toJson());
        $this->assertSame($roles, Role::orderBy('id')->get()->toJson());
        $this->assertSame($logs, AdministrativeAuditLog::count());
        $this->assertSame([], $unknown->assignedPermissionCodes());
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_new_links_invalidate_affected_sessions_and_preserve_existing_records_and_permissions(): void
    {
        $actor = User::factory()->create(['role_id' => 2]);
        $this->signIn($actor);
        $ids = Permission::whereIn('code', $this->codes())->pluck('id')->all();
        DB::table('permission_role')->where('role_id', 2)->whereIn('permission_id', $ids)->delete();
        $oldLinks = DB::table('permission_role')->whereNotIn('permission_id', $ids)->orderBy('id')->get()->toJson();
        $versions = Role::orderBy('id')->pluck('permissions_version', 'id')->all();
        $userBefore = $actor->fresh()->getAttributes();
        $auditCount = AdministrativeAuditLog::count();
        $migration = require database_path('migrations/2026_10_14_000003_add_attribute_permissions.php');
        $migration->up();
        $this->assertSame($versions[2] + 1, Role::findOrFail(2)->permissions_version);
        foreach ([1, 3] as $id) {
            $this->assertSame($versions[$id], Role::findOrFail($id)->permissions_version);
        }
        $this->assertSame($oldLinks, DB::table('permission_role')->whereNotIn('permission_id', $ids)->orderBy('id')->get()->toJson());
        $this->assertSame($userBefore, $actor->fresh()->getAttributes());
        $this->assertSame($auditCount + 1, AdministrativeAuditLog::count());
        $log = AdministrativeAuditLog::latest('id')->firstOrFail();
        $this->assertSame('migration', $log->origin);
        $this->assertSame(2, $log->role_id);
        $this->getJson(route('attributes.index'))->assertUnauthorized();
        $this->signIn($actor->fresh())->get(route('attributes.create'))->assertOk();
        $migration->up();
        $this->assertSame($auditCount + 1, AdministrativeAuditLog::count());
    }
}
