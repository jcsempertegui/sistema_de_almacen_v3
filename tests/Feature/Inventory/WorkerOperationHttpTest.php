<?php

namespace Tests\Feature\Inventory;

use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\AutorizacionRegularizacionDevolucion;
use App\Models\DevolucionHerramienta;
use App\Models\EntregaMaterial;
use App\Models\Trabajador;
use App\Models\Usuario;
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
        $url = route('operaciones-trabajadores.create', 'deliveries');
        $this->from($url)->post(route('operaciones-trabajadores.store', 'deliveries'), ['trabajador_id' => ['invalid'], 'fecha_documento' => ['invalid'], 'observaciones' => ['invalid'], 'lineas' => 'invalid', 'expected_line_count' => 1])->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('Guardar borrador');
        $data = ['trabajador_id' => Trabajador::factory()->create()->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad' => '1', 'unit_ids' => 'invalid']]];
        $this->from($url)->post(route('operaciones-trabajadores.store', 'deliveries'), $data)->assertSessionHasErrors();
        $this->get($url)->assertOk();
        $data['expected_line_count'] = 2;
        $data['lineas'][0]['unit_ids'] = [];
        $data['lineas'][2] = $data['lineas'][0];
        $data['lineas'][2]['cantidad'] = '0';
        $this->from($url)->post(route('operaciones-trabajadores.store', 'deliveries'), $data)->assertSessionHasErrors();
        $this->get($url)->assertOk()->assertSee('lineas[1][cantidad]', false);
        $this->assertDatabaseCount('entregas_materiales', 0);
    }

    public function test_delivery_forms_validation_edit_cancel_confirmation_history_and_availability(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '5');
        $trabajador = Trabajador::factory()->create();
        $this->signIn($c['actor']);
        $this->get(route('operaciones-trabajadores.create', 'deliveries'))->assertOk()->assertSee('Guardar borrador');
        $data = ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad' => '2']]];
        $this->post(route('operaciones-trabajadores.store', 'deliveries'), $data)->assertSessionHasNoErrors();
        $doc = EntregaMaterial::firstOrFail();
        $this->get(route('operaciones-trabajadores.edit', ['deliveries', $doc->id]))->assertOk();
        $this->get(route('operaciones-trabajadores.show', ['deliveries', $doc->id]))->assertOk();
        $this->post(route('operaciones-trabajadores.confirm', ['deliveries', $doc->id]), ['version_bloqueo' => 1])->assertSessionHasNoErrors();
        $this->get(route('movements.index'))->assertOk()->assertSee($doc->codigo);
        $this->get(route('stock.index'))->assertOk();
        $this->get(route('availability.index'))->assertOk()->assertSee('3.000000');
        $this->get(route('operaciones-trabajadores.index', 'deliveries'))->assertOk()->assertSee($doc->codigo);
        $this->post(route('operaciones-trabajadores.cancel', ['deliveries', $doc->id]), ['version_bloqueo' => 2, 'motivo' => 'No permitido'])->assertStatus(409);
        $this->post(route('operaciones-trabajadores.store', 'deliveries'), $data)->assertSessionHasNoErrors();
        $draft = EntregaMaterial::latest('id')->firstOrFail();
        $this->post(route('operaciones-trabajadores.cancel', ['deliveries', $draft->id]), ['version_bloqueo' => 1, 'motivo' => 'Prueba de conservación'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $draft->fresh()->estado);
    }

    public function test_auditor_and_revoked_permissions_block_direct_requests(): void
    {
        $usuario = Usuario::factory()->create(['rol_id' => 3]);
        $this->signIn($usuario);
        foreach (['deliveries', 'loans', 'returns'] as $clase) {
            $this->get(route('operaciones-trabajadores.index', $clase))->assertOk();
            $this->get(route('operaciones-trabajadores.create', $clase))->assertForbidden();
            $this->post(route('operaciones-trabajadores.store', $clase), [])->assertForbidden();
        }
        $this->get(route('availability.index'))->assertOk();
        $this->assertDatabaseCount('entregas_materiales', 0);
    }

    public function test_loan_return_forms_http_and_source_history(): void
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $this->signIn($c['actor']);
        $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => Trabajador::factory()->create()->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
        $this->get(route('operaciones-trabajadores.create', 'loans'))->assertOk();
        $this->get(route('operaciones-trabajadores.create', 'returns'))->assertOk();
        $this->get(route('operaciones-trabajadores.show', ['loans', $prestamo->id]))->assertOk();
        $data = ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $prestamo->lineas()->first()->id, 'ubicacion_id' => $c['ubicacion']->id]]];
        $this->post(route('operaciones-trabajadores.store', 'returns'), $data)->assertSessionHasNoErrors();
        $doc = DevolucionHerramienta::firstOrFail();
        $this->get(route('operaciones-trabajadores.show', ['returns', $doc->id]))->assertOk();
        $this->post(route('operaciones-trabajadores.confirm', ['returns', $doc->id]), ['version_bloqueo' => 1])->assertSessionHasNoErrors();
        $this->get(route('movements.index'))->assertOk()->assertSee($prestamo->codigo)->assertSee($doc->codigo);
        $this->get(route('availability.index'))->assertOk()->assertSee($c['unidad']->identificador);
    }

    public function test_csrf_rejects_worker_confirmation_without_token(): void
    {
        $c = $this->inventoryContext();
        $this->app['env'] = 'local';
        try {
            $this->signIn($c['actor'])->post(route('operaciones-trabajadores.confirm', ['deliveries', 1]), ['version_bloqueo' => 1])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_manager_can_request_and_receive_but_only_admin_approves_regularization(): void
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $manager = Usuario::factory()->create(['rol_id' => 2]);
        $data = ['trabajador_id' => Trabajador::factory()->create()->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]];
        $prestamo = app(GuardarPrestamo::class)->handle($manager, null, $data);
        $prestamo = app(ConfirmarPrestamo::class)->handle($manager, $prestamo, 1);
        DB::table('unidades_individuales')->where('id', $c['unidad']->id)->update(['activo' => false]);
        $this->signIn($manager)->post(route('operaciones-trabajadores.store', 'returns'), ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $prestamo->lineas()->first()->id, 'ubicacion_id' => $c['ubicacion']->id]]])->assertSessionHasNoErrors();
        $doc = DevolucionHerramienta::firstOrFail();
        $item = $doc->lineas()->first();
        $this->post(route('regularizaciones-devolucion.request', $item), ['version_bloqueo' => 1, 'motivo' => 'Recepción física de herramienta inactiva'])->assertSessionHasNoErrors();
        $auth = AutorizacionRegularizacionDevolucion::firstOrFail();
        $this->post(route('regularizaciones-devolucion.decide', [$auth, 'approve']), ['motivo' => 'Intento sin permiso'])->assertForbidden();
        $this->signIn($c['actor'])->post(route('regularizaciones-devolucion.decide', [$auth, 'approve']), ['motivo' => 'Identidad verificada'])->assertSessionHasNoErrors();
        $this->get(route('operaciones-trabajadores.show', ['returns', $doc->id]))->assertOk()->assertSee('Aprobada');
        $this->signIn($manager)->post(route('operaciones-trabajadores.confirm', ['returns', $doc->id]), ['version_bloqueo' => 1])->assertSessionHasNoErrors();
        $this->get(route('availability.index'))->assertOk()->assertSee('inactiva');
        $this->assertDatabaseCount('cierres_item_prestamo', 1);
    }
}
