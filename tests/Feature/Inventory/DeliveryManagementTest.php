<?php

namespace Tests\Feature\Inventory;

use App\Actions\Deliveries\ConfirmDelivery;
use App\Actions\Deliveries\SaveDelivery;
use App\Models\Worker;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class DeliveryManagementTest extends TestCase
{
    use CreatesInventory;

    private function data(array $c, string $quantity = '2'): array
    {
        return ['worker_id' => Worker::factory()->create()->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'quantity' => $quantity, 'unit_ids' => $c['unit'] ? [$c['unit']->id] : []]]];
    }

    public function test_delivery_draft_edit_confirm_retry_and_historical_worker(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '5');
        $data = $this->data($c);
        $doc = app(SaveDelivery::class)->handle($c['actor'], null, $data);
        $this->assertDatabaseCount('inventory_movements', 1);
        $data['lock_version'] = 1;
        $data['lines'][0]['quantity'] = '3';
        $doc = app(SaveDelivery::class)->handle($c['actor'], $doc, $data);
        $this->assertSame(2, $doc->lock_version);
        $doc = app(ConfirmDelivery::class)->handle($c['actor'], $doc, 2);
        app(ConfirmDelivery::class)->handle($c['actor'], $doc, 1);
        $this->assertDatabaseHas('stock_positions', ['quantity' => '2.000000']);
        $this->assertDatabaseCount('inventory_movements', 2);
        $snapshot = $doc->worker_snapshot;
        Worker::findOrFail($data['worker_id'])->update(['nombres' => 'Otro']);
        $this->assertSame($snapshot, $doc->fresh()->worker_snapshot);
    }

    public function test_individual_delivery_records_only_one_physical_outgoing_and_source(): void
    {
        $c = $this->inventoryContext('individual');
        $this->postInventory($c, 'receipt', '1');
        $doc = app(SaveDelivery::class)->handle($c['actor'], null, $this->data($c, '1'));
        app(ConfirmDelivery::class)->handle($c['actor'], $doc, 1);
        $this->assertDatabaseHas('individual_unit_positions', ['individual_unit_id' => $c['unit']->id, 'is_present' => 0, 'last_document_line_id' => null, 'last_delivery_line_id' => $doc->lines()->first()->id]);
        $this->assertDatabaseCount('inventory_documents', 1);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_insufficient_stock_rolls_back_and_loanables_are_rejected(): void
    {
        $c = $this->inventoryContext();
        $doc = app(SaveDelivery::class)->handle($c['actor'], null, $this->data($c));
        try {
            app(ConfirmDelivery::class)->handle($c['actor'], $doc, 1);
            $this->fail('Missing stock accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('stock_positions', 0);
        }
        $this->assertSame('draft', $doc->fresh()->status);
        $i = $this->inventoryContext('individual');
        $i['product']->update(['is_loanable' => true]);
        $this->expectException(ValidationException::class);
        app(SaveDelivery::class)->handle($i['actor'], null, $this->data($i, '1'));
    }

    public function test_failure_after_posting_rolls_back_stock_and_movement(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '5');
        $doc = app(SaveDelivery::class)->handle($c['actor'], null, $this->data($c));
        Event::listen('worker_operation.line_posted', fn () => throw new \RuntimeException('Injected failure'));
        try {
            app(ConfirmDelivery::class)->handle($c['actor'], $doc, 1);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected failure', $e->getMessage());
        }
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('stock_positions', ['quantity' => '5.000000']);
        $this->assertSame('draft', $doc->fresh()->status);
    }

    public function test_revoked_permission_is_checked_inside_confirmation(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '5');
        $doc = app(SaveDelivery::class)->handle($c['actor'], null, $this->data($c));
        $id = DB::table('permissions')->where('code', 'deliveries.confirm')->value('id');
        DB::table('permission_role')->where('role_id', 1)->where('permission_id', $id)->update(['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        app(ConfirmDelivery::class)->handle($c['actor'], $doc, 1);
    }
}
