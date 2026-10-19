<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryDocument;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\InventoryPermissions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InventoryIntegrityTest extends TestCase
{
    use CreatesInventory;

    public function test_sql_constraints_reject_negative_stock_duplicate_movements_and_wrong_unit_membership(): void
    {
        $c = $this->inventoryContext('individual');
        $doc = $this->postInventory($c, quantity: '1');
        $line = $doc->lines()->firstOrFail();
        $other = $this->inventoryContext('individual');
        $duplicate = (array) DB::table('inventory_movements')->first();
        unset($duplicate['id']);
        $operations = [
            fn () => DB::table('stock_positions')->where('product_id', $c['product']->id)->update(['quantity' => '-1']),
            fn () => DB::table('inventory_movements')->insert($duplicate),
            fn () => DB::table('inventory_document_line_units')->insert(['document_line_id' => $line->id, 'document_id' => $doc->id, 'product_id' => $c['product']->id, 'individual_unit_id' => $other['unit']->id]),
            fn () => DB::table('inventory_document_lines')->where('id', $line->id)->update(['quantity' => '0']),
            fn () => DB::table('individual_unit_positions')->where('individual_unit_id', $c['unit']->id)->update(['location_id' => null]),
            fn () => DB::table('products')->where('id', $c['product']->id)->delete(),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('SQL integrity violation accepted');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->errorInfo[0]);
            }
        }
        $this->assertDatabaseHas('stock_positions', ['quantity' => '1.000000']);
    }

    public function test_confirmed_document_and_movement_models_are_immutable(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->postInventory($c);
        foreach ([fn () => $doc->update(['notes' => 'Rewrite']), fn () => $doc->delete(), fn () => InventoryMovement::firstOrFail()->delete(), fn () => $doc->lines()->firstOrFail()->update(['quantity' => '99'])] as $operation) {
            try {
                $operation();
                $this->fail('Immutable history changed');
            } catch (\LogicException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_new_permissions_preserve_revocations_unknown_roles_and_initial_configuration(): void
    {
        $this->assertDatabaseCount('permissions', 62);
        $this->assertDatabaseCount('permission_role', 126);
        $this->assertContains('initial_stock.confirm', Role::findOrFail(1)->assignedPermissionCodes());
        $this->assertNotContains('initial_stock.confirm', Role::findOrFail(2)->assignedPermissionCodes());
        foreach (array_keys(InventoryPermissions::LABELS) as $code) {
            $this->assertSame(str_ends_with($code, '.view'), in_array($code, Role::findOrFail(3)->assignedPermissionCodes(), true));
        }
        $permission = Permission::where('code', 'remittances.create')->firstOrFail();
        DB::table('permission_role')->where('role_id', 2)->where('permission_id', $permission->id)->update(['is_active' => false]);
        $unknown = Role::create(['code' => 'inventory_unknown', 'name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $unknown->id]);
        $before = $user->fresh()->getAttributes();
        $versions = Role::pluck('permissions_version', 'id')->all();
        $audit = DB::table('administrative_audit_logs')->count();
        $migration = require database_path('migrations/2026_10_19_000002_add_inventory_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertNotContains('remittances.create', Role::findOrFail(2)->assignedPermissionCodes());
        $this->assertSame([], $unknown->assignedPermissionCodes());
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertSame($versions, Role::pluck('permissions_version', 'id')->all());
        $this->assertSame($audit, DB::table('administrative_audit_logs')->count());
    }

    public function test_csrf_rejects_confirmation_without_a_token(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->inventoryDraft($c);
        // Force the real middleware path instead of Laravel's unit-test bypass.
        $this->app['env'] = 'local';
        try {
            $this->signIn($c['actor'])->post(route('inventory-documents.confirm', $doc), ['lock_version' => 1])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame('draft', InventoryDocument::findOrFail($doc->id)->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }
}
