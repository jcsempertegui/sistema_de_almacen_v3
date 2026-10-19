<?php

namespace Tests\Feature;

use App\Models\AdministrativeAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Support\PermissionCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacSchemaTest extends TestCase
{
    public function test_initial_catalog_and_role_ids_are_preserved(): void
    {
        $this->assertSame([1, 2, 3], Role::whereIn('code', array_keys(Role::LABELS))->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('permissions', count(PermissionCatalog::LABELS));
        $this->assertCount(count(PermissionCatalog::LABELS), Role::where('code', Role::ADMINISTRATOR)->firstOrFail()->assignedPermissionCodes());
        $this->assertCount(66, Role::where('code', Role::WAREHOUSE_MANAGER)->firstOrFail()->assignedPermissionCodes());
        $this->assertCount(19, Role::where('code', Role::AUDITOR)->firstOrFail()->assignedPermissionCodes());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_initialization_preserves_unknown_roles_users_workers_and_revocations(): void
    {
        $role = Role::create(['code' => 'custom_without_grants', 'name' => 'Rol sin privilegios']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $worker = Worker::factory()->create();
        $worker->forceFill(['created_by' => $user->id, 'updated_by' => $user->id])->save();
        $userBefore = $user->fresh()->getAttributes();
        $workerBefore = $worker->fresh()->getAttributes();
        $admin = Role::where('code', Role::ADMINISTRATOR)->firstOrFail();
        $permission = Permission::where('code', 'workers.create')->firstOrFail();
        $admin->permissions()->updateExistingPivot($permission->id, ['is_active' => false]);
        $auditCount = AdministrativeAuditLog::count();
        $migration = require database_path('migrations/2026_10_12_000005_initialize_existing_role_permissions.php');
        $migration->up();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame([], $role->assignedPermissionCodes());
        $this->assertSame($userBefore, $user->fresh()->getAttributes());
        $this->assertSame($workerBefore, $worker->fresh()->getAttributes());
        $this->assertNotContains('workers.create', $admin->assignedPermissionCodes());
        $this->assertSame($auditCount, AdministrativeAuditLog::count());
    }

    public function test_duplicate_permission_codes_are_rejected_by_mariadb(): void
    {
        $this->expectException(QueryException::class);
        Permission::create(['code' => 'panel.view', 'name' => 'Duplicado']);
    }

    public function test_duplicate_role_permission_links_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        DB::table('permission_role')->insert(['role_id' => 1, 'permission_id' => Permission::where('code', 'panel.view')->value('id'), 'is_active' => true]);
    }

    public function test_unknown_permission_foreign_keys_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        DB::table('permission_role')->insert(['role_id' => 1, 'permission_id' => 999999, 'is_active' => true]);
    }

    public function test_audit_requires_exactly_one_existing_subject(): void
    {
        $this->expectException(QueryException::class);
        DB::table('administrative_audit_logs')->insert(['action' => 'invalid', 'origin' => 'test', 'after_data' => '{}']);
    }

    public function test_unknown_role_permission_foreign_keys_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        DB::table('permission_role')->insert(['role_id' => 999999, 'permission_id' => Permission::firstOrFail()->id, 'is_active' => true]);
    }

    public function test_permissions_with_history_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);
        DB::table('permissions')->where('code', 'panel.view')->delete();
    }

    public function test_audit_rejects_unknown_subject_foreign_keys(): void
    {
        $this->expectException(QueryException::class);
        DB::table('administrative_audit_logs')->insert(['role_id' => 999999, 'action' => 'invalid', 'origin' => 'test', 'after_data' => '{}']);
    }

    public function test_audit_rejects_two_subjects(): void
    {
        $user = User::factory()->create(['role_id' => 1]);
        $this->expectException(QueryException::class);
        DB::table('administrative_audit_logs')->insert(['role_id' => 1, 'user_id' => $user->id, 'action' => 'invalid', 'origin' => 'test', 'after_data' => '{}']);
    }
}
