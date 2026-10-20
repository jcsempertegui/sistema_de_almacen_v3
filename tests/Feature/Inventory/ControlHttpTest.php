<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCorrection;
use App\Models\User;
use App\Services\ControlReports;
use App\Services\InventoryCorrections;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class ControlHttpTest extends TestCase
{
    use CreatesInventory;

    public function test_control_pages_reports_and_exports_respect_permissions(): void
    {
        $c = $this->inventoryContext();
        $receipt = $this->postInventory($c, 'receipt', '3');
        $admin = $c['actor'];
        $auditor = User::factory()->create(['role_id' => 3]);
        $this->signIn($admin)->get(route('corrections.create', 'adjustment'))->assertOk()->assertSee('Cantidad contada');
        $this->get(route('corrections.create', 'compensation'))->assertOk();
        $this->get(route('backups.index'))->assertOk();
        $comp = app(InventoryCorrections::class)->save($admin, null, ['kind' => 'compensation', 'reason' => 'Revisión del documento', 'document_date' => '2026-10-20', 'source_kind' => 'inventory-documents', 'source_id' => $receipt->id]);
        $this->get(route('corrections.show', ['compensation', $comp->id]))->assertOk()->assertSee('Remito o apertura');
        $this->get(route('control-reports.index', 'remittances'))->assertOk()->assertSee('Confirmado');
        $this->get(route('control-reports.index', 'stock'))->assertOk()->assertDontSee('name="from"', false);
        $this->get(route('control-reports.index', 'availability'))->assertOk()->assertDontSee('name="from"', false)->assertDontSee('name="to"', false);
        foreach (array_keys(ControlReports::LABELS) as $kind) {
            $this->get(route('control-reports.index', $kind))->assertOk();
            $this->get(route('control-reports.index', $kind).'?format=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        }
        $this->signIn($auditor)->get(route('corrections.index', 'adjustment'))->assertOk();
        $this->get(route('corrections.create', 'adjustment'))->assertForbidden();
        $this->post(route('corrections.store', 'adjustment'), [])->assertForbidden();
        $this->get(route('backups.index'))->assertForbidden();
        $this->post(route('backups.request'))->assertForbidden();
        $this->get(route('control-reports.index', 'responsibilities'))->assertOk();
    }

    public function test_http_adjustment_workflow_and_invalid_nested_form(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $this->signIn($c['actor']);
        $url = route('corrections.create', 'adjustment');
        $this->from($url)->post(route('corrections.store', 'adjustment'), ['reason' => ['bad'], 'document_date' => ['bad'], 'lines' => 'bad'])->assertSessionHasErrors();
        $this->get($url)->assertOk();
        $data = ['reason' => 'Conteo verificado', 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'counted_quantity' => '4', 'unit_ids' => '']]];
        $this->post(route('corrections.store', 'adjustment'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $doc = InventoryCorrection::firstOrFail();
        $this->get(route('corrections.show', ['adjustment', $doc->id]))->assertOk();
        $this->post(route('corrections.transition', ['adjustment', $doc->id, 'request']), ['lock_version' => 1])->assertRedirect();
        $approver = User::factory()->create(['role_id' => 1]);
        $this->signIn($approver)->post(route('corrections.transition', ['adjustment', $doc->id, 'approve']), ['lock_version' => 1, 'reason' => 'Revisión independiente'])->assertRedirect();
        $this->signIn($c['actor'])->post(route('corrections.transition', ['adjustment', $doc->id, 'execute']), ['lock_version' => 1])->assertRedirect();
        $this->get(route('movements.index'))->assertOk()->assertSee($doc->code);
        $this->assertDatabaseHas('stock_positions', ['product_id' => $c['product']->id, 'quantity' => '4.000000']);
    }

    public function test_csv_neutralizes_formulas_and_filters_cutoff_without_writing(): void
    {
        $this->assertSame("'=HYPERLINK(1)", ControlReports::csvCell('=HYPERLINK(1)'));
        $this->assertSame("'  +1", ControlReports::csvCell('  +1'));
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $this->signIn($c['actor']);
        $response = $this->get(route('control-reports.index', 'stock').'?format=csv&to=2020-01-01');
        $response->assertOk();
        $this->assertStringNotContainsString('3.000000', $response->streamedContent());
        $this->getJson(route('control-reports.index', 'stock').'?to=invalid')->assertUnprocessable();
    }
}
