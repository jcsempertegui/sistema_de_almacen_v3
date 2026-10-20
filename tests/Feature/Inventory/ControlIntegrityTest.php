<?php

namespace Tests\Feature\Inventory;

use App\Actions\Roles\SaveRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryCorrections;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class ControlIntegrityTest extends TestCase
{
    use CreatesInventory;

    public function test_permissions_initialization_preserves_revocations_people_and_unknown_roles(): void
    {
        $this->assertDatabaseCount('permissions', 123);
        $this->assertDatabaseCount('permission_role', 260);
        foreach ([1 => 123, 2 => 96, 3 => 41] as $id => $count) {
            $this->assertCount($count, Role::findOrFail($id)->assignedPermissionCodes());
        }
        $admin = User::factory()->create(['role_id' => 1]);
        $role = Role::findOrFail(2);
        $codes = array_diff($role->assignedPermissionCodes(), ['adjustments.execute']);
        app(SaveRole::class)->handle($admin, $role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $unknown = Role::create(['code' => 'custom_s5', 'name' => 'Administrador']);
        $person = User::factory()->create(['role_id' => $unknown->id]);
        $before = $person->fresh()->getAttributes();
        $links = DB::table('permission_role')->orderBy('id')->get()->toJson();
        $versions = Role::pluck('permissions_version', 'id')->all();
        $logs = DB::table('administrative_audit_logs')->count();
        $migration = require database_path('migrations/2026_10_20_000005_add_control_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertSame($links, DB::table('permission_role')->orderBy('id')->get()->toJson());
        $this->assertSame($versions, Role::pluck('permissions_version', 'id')->all());
        $this->assertSame($logs, DB::table('administrative_audit_logs')->count());
        $this->assertSame($before, $person->fresh()->getAttributes());
        $this->assertSame([], $unknown->assignedPermissionCodes());
    }

    public function test_sql_rejects_fifth_source_without_matching_metadata_and_wrong_product_units(): void
    {
        $c = $this->inventoryContext();
        $doc = app(InventoryCorrections::class)->save($c['actor'], null, ['kind' => 'adjustment', 'document_date' => '2026-10-20', 'reason' => 'Conteo', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'counted_quantity' => '2', 'unit_ids' => []]]]);
        $line = $doc->lines()->first();
        try {
            DB::table('inventory_movements')->insert(['correction_line_id' => $line->id, 'product_id' => $line->product_id, 'location_id' => $line->location_id, 'direction' => 'in', 'quantity' => '2', 'created_by' => $c['actor']->id, 'posted_at' => now()]);
            $this->fail('Missing metadata accepted');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getCode());
        }
        $other = $this->inventoryContext('individual');
        try {
            DB::table('inventory_correction_line_units')->insert(['correction_line_id' => $line->id, 'individual_unit_id' => $other['unit']->id, 'product_id' => $other['product']->id]);
            $this->fail('Wrong product accepted');
        } catch (QueryException $e) {
            $this->assertSame('23000', (string) $e->getCode());
        }
    }

    public function test_revoked_control_permission_invalidates_session(): void
    {
        $manager = User::factory()->create(['role_id' => 2]);
        $admin = User::factory()->create(['role_id' => 1]);
        $this->signIn($manager)->get(route('corrections.create', 'adjustment'))->assertOk();
        $codes = array_diff($manager->role->assignedPermissionCodes(), ['adjustments.execute']);
        app(SaveRole::class)->handle($admin, $manager->role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $this->postJson(route('corrections.store', 'adjustment'), [])->assertUnauthorized();
        $this->assertDatabaseCount('inventory_corrections', 0);
    }

    public function test_new_write_routes_keep_csrf_protection(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $this->signIn($admin);
        $original = $this->app['env'];
        $this->app->instance('env', 'production');
        try {
            $this->post(route('corrections.store', 'adjustment'), [])->assertStatus(419);
            $this->post(route('backups.request'), [])->assertStatus(419);
            $this->assertDatabaseCount('inventory_corrections', 0);
            $this->assertDatabaseCount('backup_runs', 0);
        } finally {
            $this->app->instance('env', $original);
        }
    }
}
