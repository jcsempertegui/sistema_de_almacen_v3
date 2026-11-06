<?php

namespace Tests\Feature\Inventory;

use App\Models\CorreccionInventario;
use App\Models\Usuario;
use App\Services\CorreccionesInventario;
use App\Services\ReportesControl;
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
        $auditor = Usuario::factory()->create(['rol_id' => 3]);
        $this->signIn($admin)->get(route('corrections.create', 'adjustment'))->assertOk()->assertSee('Cantidad contada');
        $this->get(route('corrections.create', 'compensation'))->assertOk();
        $this->get(route('backups.index'))->assertOk();
        $comp = app(CorreccionesInventario::class)->save($admin, null, ['tipo' => 'compensation', 'motivo' => 'Revisión del documento', 'fecha_documento' => '2026-10-20', 'tipo_origen' => 'inventory-documents', 'origen_id' => $receipt->id]);
        $this->get(route('corrections.show', ['compensation', $comp->id]))->assertOk()->assertSee('Remito o apertura');
        $this->get(route('reportes-control.index', 'remittances'))->assertOk()->assertSee('Confirmado');
        $this->get(route('reportes-control.index', 'stock'))->assertOk()->assertDontSee('name="from"', false);
        $this->get(route('reportes-control.index', 'availability'))->assertOk()->assertDontSee('name="from"', false)->assertDontSee('name="to"', false);
        foreach (array_keys(ReportesControl::LABELS) as $clase) {
            $this->get(route('reportes-control.index', $clase))->assertOk();
            $this->get(route('reportes-control.index', ['clase' => $clase, 'format' => 'csv']))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        }
        $this->signIn($auditor)->get(route('corrections.index', 'adjustment'))->assertOk();
        $this->get(route('corrections.create', 'adjustment'))->assertForbidden();
        $this->post(route('corrections.store', 'adjustment'), [])->assertForbidden();
        $this->get(route('backups.index'))->assertForbidden();
        $this->post(route('backups.request'))->assertForbidden();
        $this->get(route('reportes-control.index', 'responsibilities'))->assertOk();
    }

    public function test_http_adjustment_workflow_and_invalid_nested_form(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $this->signIn($c['actor']);
        $url = route('corrections.create', 'adjustment');
        $this->from($url)->post(route('corrections.store', 'adjustment'), ['motivo' => ['bad'], 'fecha_documento' => ['bad'], 'lineas' => 'bad'])->assertSessionHasErrors();
        $this->get($url)->assertOk();
        $data = ['motivo' => 'Conteo verificado', 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad_contada' => '4', 'unit_ids' => '']]];
        $this->post(route('corrections.store', 'adjustment'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $doc = CorreccionInventario::firstOrFail();
        $this->get(route('corrections.show', ['adjustment', $doc->id]))->assertOk();
        $this->post(route('corrections.transition', ['adjustment', $doc->id, 'request']), ['version_bloqueo' => 1])->assertRedirect();
        $approver = Usuario::factory()->create(['rol_id' => 1]);
        $this->signIn($approver)->post(route('corrections.transition', ['adjustment', $doc->id, 'approve']), ['version_bloqueo' => 1, 'motivo' => 'Revisión independiente'])->assertRedirect();
        $this->signIn($c['actor'])->post(route('corrections.transition', ['adjustment', $doc->id, 'execute']), ['version_bloqueo' => 1])->assertRedirect();
        $this->get(route('movements.index'))->assertOk()->assertSee($doc->codigo);
        $this->assertDatabaseHas('existencias_ubicacion', ['producto_id' => $c['producto']->id, 'cantidad' => '4.000000']);
    }

    public function test_csv_neutralizes_formulas_and_filters_cutoff_without_writing(): void
    {
        $this->assertSame("'=HYPERLINK(1)", ReportesControl::csvCell('=HYPERLINK(1)'));
        $this->assertSame("'  +1", ReportesControl::csvCell('  +1'));
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $this->signIn($c['actor']);
        $response = $this->get(route('reportes-control.index', ['clase' => 'stock', 'format' => 'csv', 'to' => '2020-01-01']));
        $response->assertOk();
        $this->assertStringNotContainsString('3.000000', $response->streamedContent());
        $this->getJson(route('reportes-control.index', ['clase' => 'stock', 'to' => 'invalid']))->assertUnprocessable();
    }
}
