<?php

namespace Tests\Feature\Inventory;

use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Trabajador;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class LoanManagementTest extends TestCase
{
    use CreatesInventory;

    public function test_loan_claims_custody_once_and_blocks_external_reentry(): void
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $data = ['trabajador_id' => Trabajador::factory()->create()->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]];
        $a = app(GuardarPrestamo::class)->handle($c['actor'], null, $data);
        $b = app(GuardarPrestamo::class)->handle($c['actor'], null, $data);
        app(ConfirmarPrestamo::class)->handle($c['actor'], $a, 1);
        app(ConfirmarPrestamo::class)->handle($c['actor'], $a, 1);
        $this->assertSame('pending', $a->fresh()->estado_responsabilidad);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertDatabaseHas('custodias_unidad_individual', ['unidad_individual_id' => $c['unidad']->id, 'item_prestamo_actual_id' => $a->lineas()->first()->id]);
        try {
            app(ConfirmarPrestamo::class)->handle($c['actor'], $b, 1);
            $this->fail('Double prestamo accepted');
        } catch (ValidationException) {
            $this->assertSame('draft', $b->fresh()->estado);
        }
        try {
            $this->postInventory($c, 'receipt', '1');
            $this->fail('External reentry accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('movimientos_inventario', 2);
        }
        $this->assertDatabaseHas('posiciones_unidad_individual', ['presente' => 0, 'ultima_linea_documento_id' => null, 'ultimo_item_prestamo_id' => $a->lineas()->first()->id]);
    }

    public function test_nonloanable_and_inactive_worker_are_rejected(): void
    {
        $c = $this->inventoryContext('individual');
        $w = Trabajador::factory()->create();
        $data = ['trabajador_id' => $w->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]];
        try {
            app(GuardarPrestamo::class)->handle($c['actor'], null, $data);
            $this->fail('Nonloanable accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('prestamos_herramientas', 0);
        }
        $c['producto']->update(['prestable' => true]);
        $doc = app(GuardarPrestamo::class)->handle($c['actor'], null, $data);
        $w->update(['activo' => false]);
        $this->expectException(ValidationException::class);
        app(ConfirmarPrestamo::class)->handle($c['actor'], $doc, 1);
    }
}
