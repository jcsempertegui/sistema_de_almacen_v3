<?php

namespace Tests\Feature\Inventory;

use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Models\MaterialDelivery;
use App\Models\ReturnRegularizationAuthorization;
use App\Models\ToolReturn;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class WorkerOperationHttpTest extends TestCase
{
    use CreatesInventory;

    public function test_invalid_nested_input_can_be_redisplayed_without_a_server_error(): void
    {
        $c = $this->inventoryContext('individual');
        $this->signIn($c['actor']);
        $url = route('worker-operations.create', 'deliveries');
        $this->from($url)->post(route('worker-operations.store', 'deliveries'), ['worker_id' => ['invalid'], 'document_date' => ['invalid'], 'notes' => ['invalid'], 'lines' => 'invalid', 'expected_line_count' => 1])->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('Guardar borrador');
        $data = ['worker_id' => Worker::factory()->create()->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'quantity' => '1', 'unit_ids' => 'invalid']]];
        $this->from($url)->post(route('worker-operations.store', 'deliveries'), $data)->assertSessionHasErrors();
        $this->get($url)->assertOk();
        $data['expected_line_count'] = 2;
        $data['lines'][0]['unit_ids'] = [];
        $data['lines'][2] = $data['lines'][0];
        $data['lines'][2]['quantity'] = '0';
        $this->from($url)->post(route('worker-operations.store', 'deliveries'), $data)->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('lines[1][quantity]', false);
        $this->assertDatabaseCount('material_deliveries', 0);
    }

    public function test_delivery_forms_validation_edit_cancel_confirmation_history_and_availability(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '5');
        $worker = Worker::factory()->create();
        $this->signIn($c['actor']);
        $this->get(route('worker-operations.create', 'deliveries'))->assertOk()->assertSee('Guardar borrador');
        $data = ['worker_id' => $worker->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'quantity' => '2']]];
        $this->post(route('worker-operations.store', 'deliveries'), $data)->assertSessionHasNoErrors();
        $doc = MaterialDelivery::firstOrFail();
        $this->get(route('worker-operations.edit', ['deliveries', $doc->id]))->assertOk();
        $this->get(route('worker-operations.show', ['deliveries', $doc->id]))->assertOk();
        $this->post(route('worker-operations.confirm', ['deliveries', $doc->id]), ['lock_version' => 1])->assertSessionHasNoErrors();
        $this->get(route('movements.index'))->assertOk()->assertSee($doc->code);
        $this->get(route('stock.index'))->assertOk();
        $this->get(route('availability.index'))->assertOk()->assertSee('3.000000');
        $this->get(route('worker-operations.index', 'deliveries'))->assertOk()->assertSee($doc->code);
        $this->post(route('worker-operations.cancel', ['deliveries', $doc->id]), ['lock_version' => 2, 'reason' => 'No permitido'])->assertStatus(409);
        $this->post(route('worker-operations.store', 'deliveries'), $data)->assertSessionHasNoErrors();
        $draft = MaterialDelivery::latest('id')->firstOrFail();
        $this->post(route('worker-operations.cancel', ['deliveries', $draft->id]), ['lock_version' => 1, 'reason' => 'Prueba de conservación'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $draft->fresh()->status);
    }

    public function test_auditor_and_revoked_permissions_block_direct_requests(): void
    {
        $user = User::factory()->create(['role_id' => 3]);
        $this->signIn($user);
        foreach (['deliveries', 'loans', 'returns'] as $kind) {
            $this->get(route('worker-operations.index', $kind))->assertOk();
            $this->get(route('worker-operations.create', $kind))->assertForbidden();
            $this->post(route('worker-operations.store', $kind), [])->assertForbidden();
        }
        $this->get(route('availability.index'))->assertOk();
        $this->assertDatabaseCount('material_deliveries', 0);
    }

    public function test_loan_return_forms_http_and_source_history(): void
    {
        $c = $this->inventoryContext('individual');
        $c['product']->update(['is_loanable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $this->signIn($c['actor']);
        $loan = app(SaveLoan::class)->handle($c['actor'], null, ['worker_id' => Worker::factory()->create()->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]]);
        $loan = app(ConfirmLoan::class)->handle($c['actor'], $loan, 1);
        $this->get(route('worker-operations.create', 'loans'))->assertOk();
        $this->get(route('worker-operations.create', 'returns'))->assertOk();
        $this->get(route('worker-operations.show', ['loans', $loan->id]))->assertOk();
        $data = ['loan_id' => $loan->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $loan->lines()->first()->id, 'location_id' => $c['location']->id]]];
        $this->post(route('worker-operations.store', 'returns'), $data)->assertSessionHasNoErrors();
        $doc = ToolReturn::firstOrFail();
        $this->get(route('worker-operations.show', ['returns', $doc->id]))->assertOk();
        $this->post(route('worker-operations.confirm', ['returns', $doc->id]), ['lock_version' => 1])->assertSessionHasNoErrors();
        $this->get(route('movements.index'))->assertOk()->assertSee($loan->code)->assertSee($doc->code);
        $this->get(route('availability.index'))->assertOk()->assertSee($c['unit']->identifier);
    }

    public function test_csrf_rejects_worker_confirmation_without_token(): void
    {
        $c = $this->inventoryContext();
        $this->app['env'] = 'local';
        try {
            $this->signIn($c['actor'])->post(route('worker-operations.confirm', ['deliveries', 1]), ['lock_version' => 1])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_manager_can_request_and_receive_but_only_admin_approves_regularization(): void
    {
        $c = $this->inventoryContext('individual');
        $c['product']->update(['is_loanable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $manager = User::factory()->create(['role_id' => 2]);
        $data = ['worker_id' => Worker::factory()->create()->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]];
        $loan = app(SaveLoan::class)->handle($manager, null, $data);
        $loan = app(ConfirmLoan::class)->handle($manager, $loan, 1);
        DB::table('individual_units')->where('id', $c['unit']->id)->update(['is_active' => false]);
        $this->signIn($manager)->post(route('worker-operations.store', 'returns'), ['loan_id' => $loan->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $loan->lines()->first()->id, 'location_id' => $c['location']->id]]])->assertSessionHasNoErrors();
        $doc = ToolReturn::firstOrFail();
        $item = $doc->lines()->first();
        $this->post(route('return-regularizations.request', $item), ['lock_version' => 1, 'reason' => 'Recepción física de herramienta inactiva'])->assertSessionHasNoErrors();
        $auth = ReturnRegularizationAuthorization::firstOrFail();
        $this->post(route('return-regularizations.decide', [$auth, 'approve']), ['reason' => 'Intento sin permiso'])->assertForbidden();
        $this->signIn($c['actor'])->post(route('return-regularizations.decide', [$auth, 'approve']), ['reason' => 'Identidad verificada'])->assertSessionHasNoErrors();
        $this->get(route('worker-operations.show', ['returns', $doc->id]))->assertOk()->assertSee('Aprobada');
        $this->signIn($manager)->post(route('worker-operations.confirm', ['returns', $doc->id]), ['lock_version' => 1])->assertSessionHasNoErrors();
        $this->get(route('availability.index'))->assertOk()->assertSee('inactiva');
        $this->assertDatabaseCount('loan_item_settlements', 1);
    }
}
