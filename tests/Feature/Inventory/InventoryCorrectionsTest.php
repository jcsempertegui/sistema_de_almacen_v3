<?php

namespace Tests\Feature\Inventory;

use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Actions\Roles\SaveRole;
use App\Models\InventoryCorrection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventoryCorrections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InventoryCorrectionsTest extends TestCase
{
    use CreatesInventory;

    private function adjustment(array $c, string $count = '5'): InventoryCorrection
    {
        return app(InventoryCorrections::class)->save($c['actor'], null, ['kind' => 'adjustment', 'reason' => 'Conteo físico revisado', 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'counted_quantity' => $count, 'unit_ids' => []]]]);
    }

    private function authorize(array $c, InventoryCorrection $doc): InventoryCorrection
    {
        $svc = app(InventoryCorrections::class);
        $doc = $svc->transition($c['actor'], $doc, 'request', $doc->lock_version);
        $admin = User::factory()->create(['role_id' => 1]);

        return $svc->transition($admin, $doc, 'approve', $doc->lock_version, 'Conteo y documento verificados');
    }

    private function compensate(array $c, string $kind, int $id): InventoryCorrection
    {
        return app(InventoryCorrections::class)->save($c['actor'], null, ['kind' => 'compensation', 'reason' => 'Corrección física del documento', 'document_date' => '2026-10-20', 'source_kind' => $kind, 'source_id' => $id]);
    }

    public function test_adjustment_requires_different_authorizer_and_posts_once(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $doc = $this->adjustment($c);
        $svc = app(InventoryCorrections::class);
        $svc->transition($c['actor'], $doc, 'request', 1);
        try {
            $svc->transition($c['actor'], $doc, 'approve', 1, 'Revisado');
            $this->fail('Self authorization');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_movements', 1);
        }
        $admin = User::factory()->create(['role_id' => 1]);
        $doc = $svc->transition($admin, $doc, 'approve', 1, 'Revisado por otra persona');
        $svc->execute($c['actor'], $doc, 1);
        $svc->execute($c['actor'], $doc, 1);
        $this->assertDatabaseHas('stock_positions', ['product_id' => $c['product']->id, 'quantity' => '5.000000']);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('correction_authorizations', ['status' => 'used']);
    }

    public function test_changed_count_revocation_expiration_and_rollback_are_effective(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $svc = app(InventoryCorrections::class);
        $doc = $this->authorize($c, $this->adjustment($c));
        $this->postInventory($c, 'receipt', '1');
        try {
            $svc->execute($c['actor'], $doc, 1);
            $this->fail('Stale count');
        } catch (ValidationException) {
            $this->assertSame('authorized', $doc->fresh()->status);
        }
        $doc = $this->authorize($c, $this->adjustment($c, '6'));
        $this->travel(25)->hours();
        try {
            $svc->execute($c['actor'], $doc, 1);
            $this->fail('Expired');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_movements', 2);
        }$this->travelBack();
        $doc = $this->authorize($c, $this->adjustment($c, '7'));
        Event::listen('inventory_correction.line_posted', fn () => throw new \RuntimeException('Injected correction failure'));
        try {
            $svc->execute($c['actor'], $doc, 1);
            $this->fail('No rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected correction failure', $e->getMessage());
        }$this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame('authorized', $doc->fresh()->status);
    }

    public function test_compensation_preserves_original_and_rejects_duplicate_or_negative_balance(): void
    {
        $c = $this->inventoryContext();
        $receipt = $this->postInventory($c, 'receipt', '3');
        $svc = app(InventoryCorrections::class);
        $doc = $this->authorize($c, $this->compensate($c, 'inventory-documents', $receipt->id));
        $original = DB::table('inventory_movements')->first();
        $svc->execute($c['actor'], $doc, 1);
        $svc->execute($c['actor'], $doc, 1);
        $this->assertEquals($original, DB::table('inventory_movements')->where('id', $original->id)->first());
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('stock_positions', ['product_id' => $c['product']->id, 'quantity' => '0.000000']);
        $this->expectException(ValidationException::class);
        $this->compensate($c, 'inventory-documents', $receipt->id);
    }

    public function test_return_compensation_reopens_cycle_and_second_return_preserves_first_closure(): void
    {
        $c = $this->inventoryContext('individual');
        $c['product']->update(['is_loanable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $worker = Worker::factory()->create();
        $loan = app(SaveLoan::class)->handle($c['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]]);
        $loan = app(ConfirmLoan::class)->handle($c['actor'], $loan, 1);
        $item = $loan->lines()->first();
        $makeReturn = fn () => app(SaveReturn::class)->handle($c['actor'], null, ['loan_id' => $loan->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $item->id, 'location_id' => $c['location']->id]]]);
        $ret = $makeReturn();
        app(ConfirmReturn::class)->handle($c['actor'], $ret, 1);
        $closure = DB::table('loan_item_settlements')->first();
        $doc = $this->authorize($c, $this->compensate($c, 'returns', $ret->id));
        app(InventoryCorrections::class)->execute($c['actor'], $doc, 1);
        $this->assertSame('pending', $loan->fresh()->responsibility_status);
        $this->assertDatabaseCount('loan_custody_cycles', 2);
        $worker->update(['is_active' => false]);
        $again = $makeReturn();
        app(ConfirmReturn::class)->handle($c['actor'], $again, 1);
        $this->assertSame('returned', $loan->fresh()->responsibility_status);
        $this->assertDatabaseCount('loan_cycle_closures', 2);
        $this->assertEquals($closure, DB::table('loan_item_settlements')->first());
        $this->assertDatabaseCount('inventory_movements', 5);
        $this->assertNull($c['unit']->fresh()->location_id);
    }

    public function test_insufficient_stock_rolls_back_an_authorized_compensation(): void
    {
        $c = $this->inventoryContext();
        $receipt = $this->postInventory($c, 'receipt', '3');
        $this->postInventory($c, 'issue', '2');
        $doc = $this->authorize($c, $this->compensate($c, 'inventory-documents', $receipt->id));
        try {
            app(InventoryCorrections::class)->execute($c['actor'], $doc, 1);
            $this->fail('Negative stock');
        } catch (ValidationException) {
            $this->assertDatabaseHas('stock_positions', ['product_id' => $c['product']->id, 'quantity' => '1.000000']);
            $this->assertDatabaseCount('inventory_movements', 2);
            $this->assertDatabaseCount('document_compensations', 0);
            $this->assertSame('authorized', $doc->fresh()->status);
        }
    }

    public function test_loan_compensation_receives_tool_and_closes_custody_without_fabricating_return(): void
    {
        $c = $this->inventoryContext('individual');
        $c['product']->update(['is_loanable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $worker = Worker::factory()->create();
        $loan = app(SaveLoan::class)->handle($c['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]]);
        $loan = app(ConfirmLoan::class)->handle($c['actor'], $loan, 1);
        $doc = $this->authorize($c, $this->compensate($c, 'loans', $loan->id));
        app(InventoryCorrections::class)->execute($c['actor'], $doc, 1);
        $this->assertSame('compensated', $loan->fresh()->responsibility_status);
        $this->assertDatabaseCount('tool_returns', 0);
        $this->assertDatabaseCount('loan_cycle_closures', 1);
        $this->assertDatabaseHas('individual_unit_custodies', ['individual_unit_id' => $c['unit']->id, 'current_cycle_id' => null, 'current_loan_item_id' => null]);
        $this->assertDatabaseHas('stock_positions', ['product_id' => $c['product']->id, 'quantity' => '1.000000']);
        $this->assertNull($c['unit']->fresh()->location_id);
    }

    public function test_permission_loss_of_authorizer_blocks_execution_even_after_operator_relogin(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $doc = $this->authorize($c, $this->adjustment($c));
        $role = Role::findOrFail(1);
        $codes = array_diff($role->assignedPermissionCodes(), ['corrections.authorize']);
        app(SaveRole::class)->handle($c['actor'], $role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $this->expectException(ValidationException::class);
        app(InventoryCorrections::class)->execute($c['actor']->fresh(), $doc, 1);
    }

    public function test_regranting_authorization_permission_does_not_revive_an_old_approval(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $doc = $this->adjustment($c);
        $svc = app(InventoryCorrections::class);
        $svc->transition($c['actor'], $doc, 'request', 1);
        $role = Role::create(['code' => 's5_approver', 'name' => 'Autorizador de prueba']);
        $permission = Permission::where('code', 'corrections.authorize')->firstOrFail();
        DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $permission->id, 'is_active' => true]);
        $approver = User::factory()->create(['role_id' => $role->id]);
        $doc = $svc->transition($approver, $doc, 'approve', 1, 'Autorización inicial');
        app(SaveRole::class)->handle($c['actor'], $role, ['permissions' => []]);
        app(SaveRole::class)->handle($c['actor'], $role->fresh(), ['permissions' => [$permission->id]]);
        $this->assertTrue($approver->fresh()->hasPermission('corrections.authorize'));
        try {
            $svc->execute($c['actor'], $doc, 1);
            $this->fail('Old approval revived after regrant');
        } catch (ValidationException) {
            $this->assertSame('authorized', $doc->fresh()->status);
            $this->assertDatabaseCount('inventory_movements', 1);
        }
    }

    public function test_editing_invalidates_approval_and_cancellation_never_changes_stock(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $doc = $this->authorize($c, $this->adjustment($c));
        $svc = app(InventoryCorrections::class);
        $doc = $svc->save($c['actor'], $doc, ['kind' => 'adjustment', 'lock_version' => 1, 'document_date' => '2026-10-20', 'reason' => 'Nuevo conteo', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'counted_quantity' => '6', 'unit_ids' => []]]]);
        $this->assertSame(2, $doc->lock_version);
        $this->assertDatabaseHas('correction_authorizations', ['status' => 'revoked']);
        $svc->transition($c['actor'], $doc, 'cancel', 2);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('stock_positions', ['product_id' => $c['product']->id, 'quantity' => '3.000000']);
    }
}
