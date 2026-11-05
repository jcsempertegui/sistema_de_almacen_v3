<?php

namespace Tests\Feature;

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Entregas\ConfirmarEntrega;
use App\Actions\Entregas\GuardarEntrega;
use App\Actions\Inventario\ConfirmarDocumentoInventario;
use App\Actions\Inventario\GuardarDocumentoInventario;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Permiso;
use App\Models\Trabajador;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use App\Services\CatalogoReportes;
use App\Services\CorreccionesInventario;
use App\Services\ReportesControl;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class DetailedReportsTest extends TestCase
{
    use CreatesInventory;

    private function xpath(string $html): DOMXPath
    {
        $documento = new DOMDocument;
        @$documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($documento);
    }

    private function delivery(array $context, Trabajador $trabajador, bool $confirm = true, string $observaciones = 'Dotación semestral'): object
    {
        $doc = app(GuardarEntrega::class)->handle($context['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'observaciones' => $observaciones, 'expected_line_count' => 1,
            'lineas' => [['producto_id' => $context['producto']->id, 'ubicacion_id' => $context['ubicacion']->id, 'cantidad' => '2', 'unit_ids' => []]]]);

        return $confirm ? app(ConfirmarEntrega::class)->handle($context['actor'], $doc, 1) : $doc;
    }

    public function test_delivery_details_keep_historical_worker_and_line_units_with_named_filters(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '10');
        $trabajador = Trabajador::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez', 'cargo' => 'Instrumentista', 'area' => 'Mantenimiento']);
        $doc = $this->delivery($c, $trabajador);
        $trabajador->update(['nombres' => 'Nombre actualizado', 'cargo' => 'Otro cargo']);
        $response = $this->signIn($c['actor'])->get(route('reportes-control.index', ['clase' => 'deliveries', 'trabajador_id' => $trabajador->id, 'categoria_id' => $c['producto']->categoria_id]))->assertOk()
            ->assertSee('Ana Pérez')->assertSee('Instrumentista')->assertSee($doc->codigo)->assertSee($c['producto']->nombre)
            ->assertSee('Una fila por línea o ítem del documento.');
        $row = $response->viewData('rows')->first();
        $this->assertSame('Ana Pérez', $row->trabajador);
        $this->assertSame('2.000000', $row->cantidad);
        $this->assertSame($c['producto']->unidadMedida->simbolo, $row->unidad);
        $this->assertSame(1, $response->viewData('summary')['Documentos']);
        $this->assertSame(1, $this->xpath($response->getContent())->query('//select[@name="trabajador_id"]')->length);
        $this->assertSame(0, $this->xpath($response->getContent())->query('//input[@name="trabajador_id"]')->length);
        $csv = $this->get(route('reportes-control.index', ['clase' => 'deliveries', 'trabajador_id' => $trabajador->id, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Cargo documental', $csv);
        $this->assertStringContainsString('Ana Pérez', $csv);
        $this->assertStringContainsString($doc->codigo, $csv);
        $this->assertStringContainsString('Dotación semestral', $csv);
    }

    public function test_multiple_identifiers_do_not_multiply_document_quantities(): void
    {
        $c = $this->inventoryContext('individual');
        $other = UnidadIndividual::factory()->create(['producto_id' => $c['producto']->id, 'ubicacion_id' => null]);
        $data = $this->inventoryData($c, 'receipt', '2');
        $data['lineas'][0]['unit_ids'] = [$c['unidad']->id, $other->id];
        $doc = app(GuardarDocumentoInventario::class)->handle($c['actor'], null, $data);
        app(ConfirmarDocumentoInventario::class)->handle($c['actor'], $doc, 1);
        $rows = app(ReportesControl::class)->query('remittances', [])->get();
        $this->assertCount(1, $rows);
        $this->assertSame('2.000000', $rows[0]->cantidad);
        $this->assertStringContainsString($other->identificador, $rows[0]->identificadores);
        $this->assertStringContainsString($c['unidad']->identificador, $rows[0]->identificadores);
        $movements = app(ReportesControl::class)->query('movements', [])->get();
        $this->assertCount(1, $movements);
        $this->assertSame('2.000000', $movements[0]->cantidad);
    }

    public function test_kardex_preserves_opening_balance_before_local_date_filter(): void
    {
        $c = $this->inventoryContext();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 03:30:00', 'UTC'));
        $this->postInventory($c, 'receipt', '10'); // September 30 in Bolivia.
        $this->travelTo(CarbonImmutable::parse('2026-10-01 04:30:00', 'UTC'));
        $this->postInventory($c, 'receipt', '5');
        $this->travelTo(CarbonImmutable::parse('2026-10-01 05:00:00', 'UTC'));
        $this->postInventory($c, 'issue', '2');
        $rows = app(ReportesControl::class)->query('movements', ['from' => '2026-10-01', 'to' => '2026-10-01', 'producto_id' => $c['producto']->id])->get();
        $this->assertCount(2, $rows);
        $catalog = app(CatalogoReportes::class);
        $this->assertSame('15', $catalog->cell('saldo', $rows[0]->saldo));
        $this->assertSame('13', $catalog->cell('saldo', $rows[1]->saldo));
        $this->assertSame('01/10/2026 00:30:00', $catalog->cell('registrado', $rows[0]->registrado));
        $this->signIn($c['actor'])->get(route('reportes-control.index', ['clase' => 'stock', 'to' => '2026-09-30']))->assertOk();
        $stock = app(ReportesControl::class)->query('stock', ['to' => '2026-09-30'])->first();
        $this->assertSame('10', $catalog->cell('cantidad', $stock->cantidad));
    }

    public function test_pending_reports_follow_reopened_custody_after_a_compensated_return(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'America/La_Paz'));
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $trabajador = Trabajador::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Mendoza']);
        $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'fecha_limite' => '2026-10-21', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
        $return = app(GuardarDevolucion::class)->handle($c['actor'], null, ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-22', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $prestamo->lineas()->first()->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        app(ConfirmarDevolucion::class)->handle($c['actor'], $return, 1);
        $this->assertSame(0, app(ReportesControl::class)->query('responsibilities', [])->count());
        $this->assertSame('Devuelto', app(ReportesControl::class)->query('loans', [])->first()->situacion);
        $corrections = app(CorreccionesInventario::class);
        $correccion = $corrections->save($c['actor'], null, ['tipo' => 'compensation', 'motivo' => 'Recepción registrada por error', 'fecha_documento' => '2026-10-31', 'tipo_origen' => 'returns', 'origen_id' => $return->id]);
        $correccion = $corrections->transition($c['actor'], $correccion, 'request', $correccion->version_bloqueo);
        $approver = Usuario::factory()->create(['rol_id' => 1, 'nombre' => 'Laura Administradora']);
        $correccion = $corrections->transition($approver, $correccion, 'approve', $correccion->version_bloqueo, 'Revisión por otra persona');
        $corrections->execute($c['actor'], $correccion, $correccion->version_bloqueo);
        $this->assertSame($correccion->codigo, app(ReportesControl::class)->query('returns', [])->first()->compensado_por);
        $this->assertSame('Laura Administradora', app(ReportesControl::class)->query('corrections', [])->first()->autorizador);
        $this->assertNotNull(app(ReportesControl::class)->query('movements', [])->whereNotNull('movimiento_original')->first());
        $trabajador->update(['activo' => false]);
        $rows = app(ReportesControl::class)->query('responsibilities', ['custody' => 'overdue'])->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Luis Mendoza', $rows[0]->trabajador);
        $this->assertSame('Vencido', $rows[0]->situacion);
        $this->assertSame('Vencido', app(ReportesControl::class)->query('loans', [])->first()->situacion);
        $this->signIn($c['actor']);
        foreach (['responsibilities', 'loans', 'returns', 'availability', 'corrections', 'movements', 'audit'] as $clase) {
            $this->get(route('reportes-control.index', $clase))->assertOk();
            $this->assertNotEmpty($this->get(route('reportes-control.index', ['clase' => $clase, 'format' => 'csv']))->assertOk()->streamedContent());
        }
        $this->get(route('reportes-control.index', 'audit'))->assertSee('Corrección ejecutada');
    }

    public function test_print_and_csv_include_the_full_filtered_result_instead_of_the_first_page(): void
    {
        $c = $this->inventoryContext();
        $trabajador = Trabajador::factory()->create();
        for ($i = 0; $i < 31; $i++) {
            $this->delivery($c, $trabajador, false);
        }
        $this->signIn($c['actor']);
        $normal = $this->get(route('reportes-control.index', 'deliveries'))->assertOk();
        $this->assertCount(30, $normal->viewData('rows'));
        $print = $this->get(route('reportes-control.index', ['clase' => 'deliveries', 'format' => 'print', 'trabajador_id' => $trabajador->id]))->assertOk();
        $this->assertCount(31, $print->viewData('rows'));
        $this->assertSame(31, $this->xpath($print->getContent())->query('//article[contains(@class,"report-print-document")]')->length);
        $print->assertSee('Informe completo de la consulta')->assertSee('Imprimir / Guardar PDF');
        $this->assertSame(31, $print->viewData('summary')['Documentos']);
        $csv = $this->get(route('reportes-control.index', ['clase' => 'deliveries', 'format' => 'csv']))->streamedContent();
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $count = 0;
        while (fgetcsv($stream, null, ';', '"', '') !== false) {
            $count++;
        }
        fclose($stream);
        $this->assertSame(32, $count); // Header plus 31 lineas.
    }

    public function test_group_navigation_uses_first_allowed_report_and_denies_other_tabs_and_exports(): void
    {
        $codes = array_map(fn ($clase) => app(ReportesControl::class)->permiso($clase), array_keys(ReportesControl::LABELS));
        $ids = Permiso::whereIn('codigo', array_diff($codes, ['reports.returns.view']))->pluck('id');
        DB::table('permiso_rol')->where('rol_id', 1)->whereIn('permiso_id', $ids)->update(['activo' => false]);
        $usuario = Usuario::factory()->create(['rol_id' => 1]);
        $response = $this->signIn($usuario)->get(route('reportes-control.index', 'returns'))->assertOk()->assertDontSee('Historial de préstamos');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//aside//section[@aria-label="Reportes"]//a')->length);
        $this->assertSame(1, $xpath->query('//aside//section[@aria-label="Reportes"]//a[@aria-current="page"]')->length);
        $this->get(route('reportes-control.index', 'loans'))->assertForbidden();
        $export = Permiso::where('codigo', 'reports.returns.export')->firstOrFail();
        DB::table('permiso_rol')->where('rol_id', 1)->where('permiso_id', $export->id)->update(['activo' => false]);
        $this->get(route('reportes-control.index', ['clase' => 'returns', 'format' => 'csv']))->assertForbidden();
        $this->get(route('reportes-control.index', ['clase' => 'returns', 'format' => 'print']))->assertForbidden();
    }

    public function test_empty_csv_has_spanish_headers_and_unsafe_values_remain_escaped(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor']);
        $empty = $this->get(route('reportes-control.index', ['clase' => 'deliveries', 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Descripción del artículo', $empty);
        $trabajador = Trabajador::factory()->create(['nombres' => '=HYPERLINK(1)', 'apellidos' => '<script>alert(1)</script>']);
        $this->delivery($c, $trabajador, false, 'Detalle; con salto'."\n".'de línea');
        $this->get(route('reportes-control.index', 'deliveries'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $csv = $this->get(route('reportes-control.index', ['clase' => 'deliveries', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK(1)", $csv);
        $this->getJson(route('reportes-control.index', ['clase' => 'deliveries', 'q' => ['invalid']]))->assertUnprocessable();
        $this->getJson(route('reportes-control.index', ['clase' => 'deliveries', 'from' => '2026-10-31', 'to' => '2026-10-01']))->assertUnprocessable();
    }

    public function test_category_and_type_filters_preserve_document_numbers_and_distinct_summary(): void
    {
        $c = $this->inventoryContext();
        $other = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $this->postInventory($c, 'issue', '1');
        $this->postInventory($other, 'receipt', '9');
        $response = $this->signIn($c['actor'])->get(route('reportes-control.index', ['clase' => 'remittances', 'categoria_id' => $c['producto']->categoria_id, 'tipo' => 'receipt']))->assertOk();
        $this->assertCount(1, $response->viewData('rows'));
        $this->assertSame(1, $response->viewData('summary')['Documentos']);
        $this->assertStringStartsWith('RG-IN-', $response->viewData('rows')->first()->documento);
        $this->assertSame('3.000000', $response->viewData('rows')->first()->cantidad);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(5, $xpath->query('//aside//section[@aria-label="Reportes"]//a')->length);
    }

    public function test_report_access_does_not_grant_document_access_or_display_a_forbidden_link(): void
    {
        $c = $this->inventoryContext();
        $trabajador = Trabajador::factory()->create();
        $doc = $this->delivery($c, $trabajador, false);
        $this->signIn($c['actor'])->get(route('reportes-control.index', 'deliveries'))->assertOk()
            ->assertSee('href="'.route('operaciones-trabajadores.show', ['deliveries', $doc->id]).'"', false);
        $permiso = Permiso::where('codigo', 'deliveries.view')->firstOrFail();
        DB::table('permiso_rol')->where('rol_id', 1)->where('permiso_id', $permiso->id)->update(['activo' => false]);
        $this->get(route('reportes-control.index', 'deliveries'))->assertOk()->assertSee($doc->codigo)
            ->assertDontSee('href="'.route('operaciones-trabajadores.show', ['deliveries', $doc->id]).'"', false);
        $this->get(route('operaciones-trabajadores.show', ['deliveries', $doc->id]))->assertForbidden();
    }

    public function test_two_document_lines_keep_their_own_quantities_and_count_the_document_once(): void
    {
        $c = $this->inventoryContext();
        $other = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '10');
        $this->postInventory($other, 'receipt', '10');
        $trabajador = Trabajador::factory()->create();
        $doc = app(GuardarEntrega::class)->handle($c['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 2, 'lineas' => [
            ['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad' => '2', 'unit_ids' => []],
            ['producto_id' => $other['producto']->id, 'ubicacion_id' => $other['ubicacion']->id, 'cantidad' => '3', 'unit_ids' => []],
        ]]);
        app(ConfirmarEntrega::class)->handle($c['actor'], $doc, 1);
        $response = $this->signIn($c['actor'])->get(route('reportes-control.index', 'deliveries'))->assertOk();
        $this->assertSame(['Registros' => 2, 'Documentos' => 1, 'Artículos' => 2, 'Trabajadores' => 1], $response->viewData('summary'));
        $this->assertSame(['2.000000', '3.000000'], $response->viewData('rows')->pluck('cantidad')->all());
    }
}
