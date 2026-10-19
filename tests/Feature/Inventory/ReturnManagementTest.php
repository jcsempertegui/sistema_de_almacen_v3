<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\ConfirmInventoryDocument;
use App\Actions\Inventory\SaveInventoryDocument;
use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\ProductLocation;
use App\Models\ToolReturn;
use App\Models\Worker;
use App\Services\ReturnRegularization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class ReturnManagementTest extends TestCase
{
    use CreatesInventory;

    private function loan(): array
    {
        $c = $this->inventoryContext('individual');
        $c['product']->update(['is_loanable' => true]);
        $other = IndividualUnit::factory()->create(['product_id' => $c['product']->id, 'location_id' => null]);
        $data = $this->inventoryData($c, 'receipt', '2');
        $data['lines'][0]['unit_ids'][] = $other->id;
        $receipt = app(SaveInventoryDocument::class)->handle($c['actor'], null, $data);
        app(ConfirmInventoryDocument::class)->handle($c['actor'], $receipt, 1);
        $worker = Worker::factory()->create();
        $loan = app(SaveLoan::class)->handle($c['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-19', 'expected_line_count' => 2, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id], ['individual_unit_id' => $other->id, 'location_id' => $c['location']->id]]]);
        $loan = app(ConfirmLoan::class)->handle($c['actor'], $loan, 1);

        return $c + compact('loan', 'worker', 'other');
    }

    private function draft(array $c, int $item, ?int $location = null): ToolReturn
    {
        return app(SaveReturn::class)->handle($c['actor'], null, ['loan_id' => $c['loan']->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $item, 'location_id' => $location ?? $c['location']->id]]]);
    }

    public function test_partial_then_final_return_with_inactive_worker_and_different_destination(): void
    {
        $c = $this->loan();
        $c['worker']->update(['is_active' => false]);
        $items = $c['loan']->lines()->get();
        $location = Location::factory()->create();
        ProductLocation::factory()->create(['product_id' => $c['product']->id, 'location_id' => $location->id]);
        $doc = $this->draft($c, $items[0]->id, $location->id);
        app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
        app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
        $this->assertSame('partial', $c['loan']->fresh()->responsibility_status);
        $this->assertDatabaseCount('loan_item_settlements', 1);
        $this->assertDatabaseHas('individual_unit_positions', ['individual_unit_id' => $items[0]->individual_unit_id, 'is_present' => 1, 'location_id' => $location->id, 'last_loan_item_id' => null, 'last_return_item_id' => $doc->lines()->first()->id]);
        $this->assertNull(IndividualUnit::findOrFail($items[0]->individual_unit_id)->location_id);
        $last = $this->draft($c, $items[1]->id);
        app(ConfirmReturn::class)->handle($c['actor'], $last, 1);
        $this->assertSame('returned', $c['loan']->fresh()->responsibility_status);
        $this->assertDatabaseCount('inventory_movements', 5);
        $this->assertSame($c['loan']->worker_snapshot, $last->fresh()->worker_snapshot);
        $physical = $c;
        $physical['location'] = $location;
        $this->postInventory($physical, 'issue', '1');
        $this->postInventory($physical, 'receipt', '1');
        $this->assertDatabaseHas('individual_unit_positions', ['individual_unit_id' => $c['unit']->id, 'is_present' => 1, 'last_return_item_id' => null, 'last_loan_item_id' => null]);
    }

    public function test_sql_rejects_closure_without_movement_and_competing_draft_cannot_return_twice(): void
    {
        $c = $this->loan();
        $original = $c['loan']->lines()->first();
        $a = $this->draft($c, $original->id);
        $b = $this->draft($c, $original->id);
        $item = $a->lines()->first();
        try {
            DB::table('loan_item_settlements')->insert(['loan_item_id' => $original->id, 'return_item_id' => $item->id, 'individual_unit_id' => $original->individual_unit_id]);
            $this->fail('Closure without physical movement accepted');
        } catch (QueryException $e) {
            $this->assertSame('23000', (string) $e->getCode());
        }
        app(ConfirmReturn::class)->handle($c['actor'], $a, 1);
        try {
            app(ConfirmReturn::class)->handle($c['actor'], $b, 1);
            $this->fail('Duplicate return accepted');
        } catch (ValidationException) {
            $this->assertSame('draft', $b->fresh()->status);
        }
        $this->assertDatabaseCount('loan_item_settlements', 1);
    }

    public function test_exceptional_return_requires_specific_approval_and_never_reactivates_catalog(): void
    {
        $c = $this->loan();
        $original = $c['loan']->lines()->first();
        DB::table('individual_units')->where('id', $original->individual_unit_id)->update(['is_active' => false]);
        DB::table('products')->where('id', $c['product']->id)->update(['is_active' => false]);
        $doc = $this->draft($c, $original->id);
        $item = $doc->lines()->first();
        try {
            app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
            $this->fail('Unapproved exception accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('loan_item_settlements', 0);
        }
        $auth = app(ReturnRegularization::class)->request($c['actor'], $item, 1, 'Recepción de herramienta retirada del catálogo');
        $auth = app(ReturnRegularization::class)->decide($c['actor'], $auth, true, 'Identidad y destino revisados');
        app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
        $this->assertSame('used', $auth->fresh()->status);
        $this->assertDatabaseCount('return_regularization_events', 3);
        $this->assertDatabaseHas('products', ['id' => $c['product']->id, 'is_active' => false]);
        $this->assertDatabaseHas('individual_units', ['id' => $original->individual_unit_id, 'is_active' => false]);
        $this->assertDatabaseHas('loan_item_settlements', ['loan_item_id' => $original->id, 'regularization_authorization_id' => $auth->id]);
        $this->expectException(HttpException::class);
        app(ReturnRegularization::class)->decide($c['actor'], $auth, false, 'Intento de revocación posterior');
    }

    public function test_revocation_expiration_and_content_changes_invalidate_exception(): void
    {
        $c = $this->loan();
        $original = $c['loan']->lines()->first();
        DB::table('individual_units')->where('id', $original->individual_unit_id)->update(['is_active' => false]);
        $doc = $this->draft($c, $original->id);
        $item = $doc->lines()->first();
        $auth = app(ReturnRegularization::class)->request($c['actor'], $item, 1, 'Solicitar recepción');
        $auth = app(ReturnRegularization::class)->decide($c['actor'], $auth, true, 'Revisado');
        app(ReturnRegularization::class)->decide($c['actor'], $auth, false, 'Destino pendiente de revisión');
        try {
            app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
            $this->fail('Revoked authorization accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('loan_item_settlements', 0);
        }
        $auth = app(ReturnRegularization::class)->request($c['actor'], $item, 1, 'Nueva revisión');
        $auth = app(ReturnRegularization::class)->decide($c['actor'], $auth, true, 'Revisado');
        $this->travel(25)->hours();
        try {
            app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
            $this->fail('Expired authorization accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('loan_item_settlements', 0);
        }$this->travelBack();
        DB::table('products')->where('id', $c['product']->id)->increment('lock_version');
        $this->expectException(ValidationException::class);
        app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
    }

    public function test_injected_failure_rolls_back_return_custody_closure_and_authorization(): void
    {
        $c = $this->loan();
        $original = $c['loan']->lines()->first();
        DB::table('individual_units')->where('id', $original->individual_unit_id)->update(['is_active' => false]);
        $doc = $this->draft($c, $original->id);
        $auth = app(ReturnRegularization::class)->request($c['actor'], $doc->lines()->first(), 1, 'Recepción excepcional');
        $auth = app(ReturnRegularization::class)->decide($c['actor'], $auth, true, 'Revisado');
        Event::listen('worker_operation.line_posted', fn () => throw new \RuntimeException('Injected return failure'));
        try {
            app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected return failure', $e->getMessage());
        }
        $this->assertDatabaseCount('inventory_movements', 3);
        $this->assertDatabaseCount('loan_item_settlements', 0);
        $this->assertSame('approved', $auth->fresh()->status);
        $this->assertDatabaseHas('individual_unit_custodies', ['individual_unit_id' => $original->individual_unit_id, 'current_loan_item_id' => $original->id]);
    }

    public function test_inactive_existing_pair_can_be_authorized_but_inactive_destination_cannot(): void
    {
        $c = $this->loan();
        $original = $c['loan']->lines()->first();
        DB::table('product_locations')->where('product_id', $c['product']->id)->where('location_id', $c['location']->id)->update(['is_active' => false]);
        $doc = $this->draft($c, $original->id);
        $auth = app(ReturnRegularization::class)->request($c['actor'], $doc->lines()->first(), 1, 'Asociación retirada');
        app(ReturnRegularization::class)->decide($c['actor'], $auth, true, 'Destino comprobado');
        app(ConfirmReturn::class)->handle($c['actor'], $doc, 1);
        $this->assertDatabaseHas('product_locations', ['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'is_active' => false]);
        DB::table('locations')->where('id', $c['location']->id)->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $this->draft($c, $c['loan']->lines()->latest('id')->first()->id);
    }

    public function test_editing_draft_revokes_approval_without_erasing_its_history(): void
    {
        $c = $this->loan();
        $original = $c['loan']->lines()->first();
        DB::table('individual_units')->where('id', $original->individual_unit_id)->update(['is_active' => false]);
        $doc = $this->draft($c, $original->id);
        $item = $doc->lines()->first();
        $auth = app(ReturnRegularization::class)->request($c['actor'], $item, 1, 'Recibir herramienta');
        app(ReturnRegularization::class)->decide($c['actor'], $auth, true, 'Revisado');
        $doc = app(SaveReturn::class)->handle($c['actor'], $doc, ['lock_version' => 1, 'loan_id' => $c['loan']->id, 'document_date' => '2026-10-19', 'notes' => 'Revisión posterior', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $original->id, 'location_id' => $c['location']->id]]]);
        $this->assertSame($item->id, $doc->lines()->first()->id);
        $this->assertSame('revoked', $auth->fresh()->status);
        $this->assertDatabaseCount('return_regularization_events', 3);
        $this->expectException(ValidationException::class);
        app(ConfirmReturn::class)->handle($c['actor'], $doc, 2);
    }
}
